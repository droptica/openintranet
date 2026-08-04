<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_forum\Browser;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Makes the profile-contained forum module discoverable by a test site.
 */
trait ProfileModuleDiscoveryTrait {

  /**
   * {@inheritdoc}
   */
  protected function prepareEnvironment(): void {
    parent::prepareEnvironment();

    $source = DRUPAL_ROOT . '/profiles/openintranet/modules/openintranet_forum';
    $destination = $this->siteDirectory . '/modules/openintranet_forum';
    (new Filesystem())->symlink($source, $destination);
  }

}
