<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_forum\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests standalone creation of the required forum categories.
 */
#[Group('openintranet_forum')]
#[RunTestsInSeparateProcesses]
final class BaseCategoryInstallTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'taxonomy',
    'path_alias',
    'path',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('path_alias');
    $this->installConfig(['field', 'filter']);

    Vocabulary::create([
      'vid' => 'forum_category',
      'name' => 'Forum Category',
    ])->save();

    require_once DRUPAL_ROOT . '/profiles/openintranet/modules/openintranet_forum/openintranet_forum.install';
  }

  /**
   * Creates all categories once and preserves their stable identifiers.
   */
  public function testCategoryCreationIsIdempotent(): void {
    _openintranet_forum_create_base_categories();
    _openintranet_forum_create_base_categories();

    $terms = $this->container->get('entity_type.manager')
      ->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'forum_category']);

    self::assertCount(5, $terms);
    $uuids = array_map(
      static fn ($term): string => $term->uuid(),
      $terms,
    );
    sort($uuids);
    self::assertSame([
      'forumcat-0000-0000-0000-000000000001',
      'forumcat-0000-0000-0000-000000000002',
      'forumcat-0000-0000-0000-000000000003',
      'forumcat-0000-0000-0000-000000000004',
      'forumcat-0000-0000-0000-000000000005',
    ], $uuids);
  }

}
