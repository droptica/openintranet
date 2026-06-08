<?php

declare(strict_types=1);

namespace Drupal\openintranet\Drush\Commands;

use Drupal\openintranet\BookStructureImporter;
use Drush\Attributes\Command;
use Drush\Attributes\Usage;
use Drush\Commands\DrushCommands;
use Psr\Container\ContainerInterface;

/**
 * Drush commands for importing the Open Intranet book structure.
 */
final class BookImportCommands extends DrushCommands {

  /**
   * Constructs a BookImportCommands object.
   *
   * @param \Drupal\openintranet\BookStructureImporter $importer
   *   The book structure importer service.
   */
  public function __construct(
    private BookStructureImporter $importer,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('openintranet.book_structure_importer'),
    );
  }

  /**
   * Imports the demo book structure into the book outline.
   */
  #[Command(
    name: 'openintranet:import-book-structure',
    aliases: ['oi:book-import'],
  )]
  #[Usage(
    name: 'drush openintranet:import-book-structure',
    description: 'Imports the book structure from the default content recipe.',
  )]
  public function importBookStructure(): void {
    $result = $this->importer->import();

    $this->logger()->success(dt(
      'Imported @i book link(s), skipped @s.',
      ['@i' => $result['imported'], '@s' => $result['skipped']],
    ));
  }

}
