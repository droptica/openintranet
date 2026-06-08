<?php

declare(strict_types=1);

namespace Drupal\openintranet;

use Drupal\book\BookManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Imports the demo book structure into the book outline.
 *
 * The book structure shipped with the default content recipe references
 * nodes by UUID. This service maps those UUIDs to the local node IDs and
 * writes the corresponding book links via the book manager. It is safe to
 * run multiple times: links are not duplicated. On a re-run the contrib book
 * update path reconciles the link's weight and parent, but it does not rewrite
 * the full record (e.g. has_children/depth are recomputed by the book manager,
 * not taken from the source file).
 */
class BookStructureImporter {

  /**
   * Keys whose values are node UUIDs that must be mapped to node IDs.
   */
  private const UUID_KEYS = ['nid', 'bid', 'pid'];

  /**
   * Default values applied to every book link before saving.
   */
  private const LINK_DEFAULTS = [
    'has_children' => 0,
    'weight' => 0,
    'depth' => 1,
  ];

  /**
   * Constructs a BookStructureImporter object.
   *
   * @param \Drupal\book\BookManagerInterface $bookManager
   *   The book manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository, used to resolve nodes by UUID.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel for the openintranet profile.
   * @param string $appRoot
   *   The application root path.
   */
  public function __construct(
    protected BookManagerInterface $bookManager,
    protected EntityRepositoryInterface $entityRepository,
    protected LoggerInterface $logger,
    protected string $appRoot,
  ) {}

  /**
   * Imports the book structure from a YAML file.
   *
   * @param string|null $file
   *   Path to the book structure YAML file. Defaults to the file shipped
   *   with the default content recipe when NULL.
   *
   *   On a re-run, links are reconciled (weight/parent) rather than duplicated;
   *   has_children/depth are managed by the contrib book update path and not
   *   rewritten from the source file.
   *
   * @return array
   *   An associative array with the number of imported and skipped links,
   *   keyed by 'imported' and 'skipped'.
   */
  public function import(?string $file = NULL): array {
    $file ??= $this->appRoot
      . '/../recipes/default_content/book/book.structure.yml';

    if (!file_exists($file)) {
      $this->logger->warning(
        'Book structure file not found at @file. Skipping import.',
        ['@file' => $file],
      );
      return ['imported' => 0, 'skipped' => 0];
    }

    $structure = Yaml::parse(file_get_contents($file));

    // Guard against a corrupt or non-list YAML file: a scalar/NULL result
    // would cause a TypeError in the processing loop below.
    if (!is_array($structure)) {
      $this->logger->warning(
        'Book structure file @file did not parse to an array. Skipping import.',
        ['@file' => $file],
      );
      return ['imported' => 0, 'skipped' => 0];
    }

    // The source file is not guaranteed to list parents before their
    // children. Saving a child before its parent makes the contrib book
    // manager's getBookParents() log a "Parent book link ... missing" warning
    // and fall back to depth 0, mis-nesting the outline. Topologically sort
    // the links (on the raw UUID pid/nid values, before resolution) so every
    // child is processed after its parent.
    $structure = $this->sortParentsFirst($structure);

    $imported = 0;
    $skipped = 0;
    $uuid_map = [];

    foreach ($structure as $link) {
      try {
        if ($this->importLink($link, $uuid_map)) {
          $imported++;
        }
        else {
          $skipped++;
        }
      }
      catch (\Exception $e) {
        $skipped++;
        $this->logger->warning(
          'Failed to import a book link: @message',
          ['@message' => $e->getMessage()],
        );
      }
    }

    return ['imported' => $imported, 'skipped' => $skipped];
  }

  /**
   * Orders links so every child comes after its parent.
   *
   * Works on the raw UUID values (the 'pid'/'nid' keys, before UUID-to-id
   * resolution): roots first (NULL/empty pid), then repeatedly emit links
   * whose parent UUID has already been emitted. Any leftover links (orphans
   * referencing an unknown parent, or links caught in a cycle) are appended at
   * the end so they are still processed; those will trigger the existing
   * skip/fallback warning, which is acceptable for malformed data.
   *
   * @param array $structure
   *   The parsed list of raw book link definitions.
   *
   * @return array
   *   The same links reordered so parents precede their children.
   */
  private function sortParentsFirst(array $structure): array {
    $sorted = [];
    $emitted = [];
    $pending = [];

    // Roots first: links with no parent reference.
    foreach ($structure as $link) {
      $pid = $link['pid'] ?? NULL;
      if (empty($pid)) {
        $sorted[] = $link;
        if (!empty($link['nid'])) {
          $emitted[$link['nid']] = TRUE;
        }
      }
      else {
        $pending[] = $link;
      }
    }

    // Repeatedly emit links whose parent has already been emitted.
    do {
      $progress = FALSE;
      foreach ($pending as $index => $link) {
        if (isset($emitted[$link['pid']])) {
          $sorted[] = $link;
          if (!empty($link['nid'])) {
            $emitted[$link['nid']] = TRUE;
          }
          unset($pending[$index]);
          $progress = TRUE;
        }
      }
    } while ($progress && $pending);

    // Append leftover orphans/cycles so they are still processed.
    foreach ($pending as $link) {
      $sorted[] = $link;
    }

    return $sorted;
  }

  /**
   * Converts and saves a single book link.
   *
   * @param array $link
   *   The raw book link definition with node UUIDs.
   * @param array $uuid_map
   *   A local cache of UUID to node ID resolutions, passed by reference.
   *
   * @return bool
   *   TRUE when the link was saved, FALSE when it was skipped because a
   *   referenced node could not be found.
   */
  private function importLink(array $link, array &$uuid_map): bool {
    $converted = [];

    foreach ($link as $key => $value) {
      // A NULL pid (or any NULL key) means top level.
      if ($value === NULL) {
        $converted[$key] = 0;
        continue;
      }

      // Non-reference keys and empty references are copied verbatim.
      if (!in_array($key, self::UUID_KEYS, TRUE) || empty($value)) {
        $converted[$key] = $value;
        continue;
      }

      $node_id = $this->resolveUuid($value, $uuid_map);

      // Skip links referencing a missing node rather than writing an
      // invalid link with nid/bid 0.
      if ($node_id === 0) {
        $this->logger->warning(
          'Skipping book link (nid UUID @nid): node with UUID @uuid referenced by key @key not found.',
          [
            '@nid' => $link['nid'] ?? '(unknown)',
            '@uuid' => $value,
            '@key' => $key,
          ],
        );
        return FALSE;
      }

      $converted[$key] = $node_id;
    }

    foreach (self::LINK_DEFAULTS as $key => $default_value) {
      $converted[$key] ??= $default_value;
    }

    $existing = $this->bookManager->loadBookLink((int) $converted['nid'], FALSE);
    $is_new = empty($existing) || empty($existing['nid']);

    $this->bookManager->saveBookLink($converted, $is_new);

    return TRUE;
  }

  /**
   * Resolves a node UUID to its local node ID, caching the result.
   *
   * @param string $uuid
   *   The node UUID to resolve.
   * @param array $uuid_map
   *   A local cache of UUID to node ID resolutions, passed by reference.
   *
   * @return int
   *   The node ID, or 0 when no matching node exists.
   */
  private function resolveUuid(string $uuid, array &$uuid_map): int {
    if (!isset($uuid_map[$uuid])) {
      $node = $this->entityRepository->loadEntityByUuid('node', $uuid);
      $uuid_map[$uuid] = $node ? (int) $node->id() : 0;
    }

    return $uuid_map[$uuid];
  }

}
