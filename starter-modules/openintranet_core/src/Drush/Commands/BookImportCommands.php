<?php

declare(strict_types=1);

namespace Drupal\openintranet_core\Drush\Commands;

use Drupal\openintranet_core\BookStructureImporter;
use Drush\Attributes\Command;
use Drush\Attributes\Usage;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drush commands for importing the Open Intranet book structure.
 */
final class BookImportCommands extends DrushCommands {

  /**
   * Constructs a BookImportCommands object.
   *
   * @param \Drupal\openintranet_core\BookStructureImporter $importer
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
    return new self(
      $container->get('openintranet_core.book_structure_importer'),
    );
  }

  /**
   * Imports the demo book structure into the book outline.
   */
  #[Command(
    name: 'openintranet_core:import-book-structure',
    aliases: ['oi:book-import'],
  )]
  #[Usage(
    name: 'drush openintranet_core:import-book-structure',
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
