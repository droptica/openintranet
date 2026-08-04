<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_forum\Functional;

use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\openintranet_forum\Browser\ProfileModuleDiscoveryTrait;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests a standalone forum module installation.
 */
#[Group('openintranet_forum')]
#[RunTestsInSeparateProcesses]
final class ForumStandaloneInstallTest extends BrowserTestBase {

  use ProfileModuleDiscoveryTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'openintranet_forum',
  ];

  /**
   * {@inheritdoc}
   */
  protected $apcuEnsureUniquePrefix = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $profile = 'minimal';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests config isolation and required base content.
   */
  public function testStandaloneInstallCreatesUsableForum(): void {
    self::assertFalse(
      $this->container->get('module_handler')->moduleExists('default_content'),
      'Forum installation does not require the contrib Default Content module.',
    );

    $term_storage = $this->container->get('entity_type.manager')->getStorage('taxonomy_term');
    $terms = $term_storage->loadByProperties(['vid' => 'forum_category']);
    self::assertCount(5, $terms);
    $names = array_values(array_map(
      static fn ($term): string => $term->label(),
      $terms,
    ));
    sort($names);
    self::assertSame(
      [
        'Announcements',
        'General Discussion',
        'Help & Support',
        'Ideas & Feedback',
        'Off-Topic',
      ],
      $names,
    );

    self::assertTrue(
      $this->config('views.view.forum_feed')->isNew(),
      'Profile-specific forum feed config stays optional without department and office config.',
    );

    self::assertFalse($this->config('flag.flag.bookmark_forum_post')->isNew());
    $authenticated = Role::load('authenticated');
    self::assertNotNull($authenticated);
    self::assertTrue($authenticated->hasPermission('flag bookmark_forum_post'));
    self::assertTrue($authenticated->hasPermission('unflag bookmark_forum_post'));

    $body_field = $this->container->get('entity_type.manager')
      ->getStorage('field_config')
      ->load('node.forum_post.body');
    self::assertNotNull($body_field);
    self::assertSame([], $body_field->getSetting('allowed_formats'));

    $category = reset($terms);
    $post = Node::create([
      'type' => 'forum_post',
      'title' => 'Standalone forum post',
      'uid' => 1,
      'status' => 1,
      'field_forum_category' => $category->id(),
    ]);
    $post->save();

    self::assertNotNull($post->id());
  }

}
