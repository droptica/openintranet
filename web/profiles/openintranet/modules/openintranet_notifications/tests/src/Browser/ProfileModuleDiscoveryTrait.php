<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Browser;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Makes a profile-contained module discoverable by a minimal test site.
 */
trait ProfileModuleDiscoveryTrait {

  /**
   * {@inheritdoc}
   */
  protected function prepareEnvironment(): void {
    parent::prepareEnvironment();

    $source = DRUPAL_ROOT . '/profiles/openintranet/modules/openintranet_notifications';
    $destination = $this->siteDirectory . '/modules/openintranet_notifications';
    (new Filesystem())->symlink($source, $destination);
  }

}
