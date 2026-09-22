<?php

declare(strict_types=1);

namespace Drupal\openintranet_access\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an OI Access Entity plugin annotation object.
 *
 * Plugin Namespace: Plugin\OiAccessEntity
 *
 * @Annotation
 */
class OiAccessEntityPlugin extends Plugin {

  /**
   * The plugin ID.
   */
  public string $id;

  /**
   * The human-readable name of the plugin.
   *
   * @ingroup plugin_translatable
   */
  public string $label;

  /**
   * The entity type ID this plugin handles.
   */
  public string $entity_type;

}
