<?php

declare(strict_types=1);

namespace Drupal\openintranet_documents\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Document Source plugin annotation.
 *
 * Plugin Namespace: Plugin\DocumentSource.
 *
 * @see \Drupal\openintranet_documents\DocumentSourceManager
 * @see \Drupal\openintranet_documents\Plugin\DocumentSource\DocumentSourceInterface
 * @see \Drupal\openintranet_documents\Plugin\DocumentSource\DocumentSourceBase
 *
 * @Annotation
 */
class DocumentSource extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public string $id;

  /**
   * The human-readable name of the document source.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * A brief description of the document source.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description = '';

  /**
   * Bootstrap icon class (e.g., "bi-google").
   *
   * @var string
   */
  public string $icon = 'bi-file-earmark-plus';

}
