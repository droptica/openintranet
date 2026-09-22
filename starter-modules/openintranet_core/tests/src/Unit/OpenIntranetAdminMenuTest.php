<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_core\Unit;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Open Intranet administration menu integration.
 */
#[Group('openintranet')]
final class OpenIntranetAdminMenuTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    require_once dirname(__DIR__, 3) . '/openintranet_core.module';
  }

  /**
   * Tests that discovered module links are grouped and normalized.
   */
  public function testAdminLinksAreGroupedByModule(): void {
    $expected_links = [
      'openintranet_access.groups' => ['access', 'Groups', 0],
      'openintranet_access.settings' => ['access', 'Settings', 10],
      'entity.oi_document.collection' => ['documents', 'Documents', 0],
      'entity.oi_folder.collection' => ['documents', 'Folders', 10],
      'openintranet_documents.settings' => ['documents', 'Settings', 20],
      'openintranet_engagement.dashboard' => ['engagement', 'Overview', 0],
      'openintranet_engagement.settings' => ['engagement', 'Settings', 10],
      'openintranet_forum.settings' => ['forum', 'Settings', 0],
      'openintranet_messenger.dashboard' => ['messenger', 'Overview', 0],
      'openintranet_messenger.contacts' => ['messenger', 'Contacts', 10],
      'openintranet_messenger.send' => ['messenger', 'Send notification', 20],
      'openintranet_messenger.log' => ['messenger', 'Notification log', 30],
      'openintranet_messenger.settings' => ['messenger', 'Settings', 40],
      'openintranet_notifications.channel_status' => ['notifications', 'Channels', 0],
      'openintranet_notifications.notification_type.collection' => ['notifications', 'Types', 10],
      'openintranet_notifications.notification.collection' => ['notifications', 'Records', 20],
      'openintranet_notifications.notif_delivery.collection' => ['notifications', 'Deliveries', 30],
      'openintranet_notifications.delivery_bulk' => ['notifications', 'Bulk operations', 40],
      'openintranet_notifications.test' => ['notifications', 'Test notification', 50],
      'openintranet_notifications.settings' => ['notifications', 'Settings', 60],
    ];

    $links = [];
    foreach ($expected_links as $link_id => $expected) {
      $links[$link_id] = [
        'title' => 'Original title',
        'route_name' => 'example.route',
        'parent' => 'original.parent',
        'menu_name' => 'tools',
        'weight' => 100,
      ];
    }

    $links['openintranet_documents.browser'] = [
      'title' => 'Documents',
      'route_name' => 'openintranet_documents.browser',
      'menu_name' => 'main',
      'weight' => 10,
    ];
    $public_link = $links['openintranet_documents.browser'];

    openintranet_core_menu_links_discovered_alter($links);

    foreach (['access', 'documents', 'engagement', 'forum', 'messenger', 'notifications'] as $group_id) {
      $menu_group_id = 'openintranet.admin.' . $group_id;
      self::assertArrayHasKey($menu_group_id, $links);
      self::assertSame('openintranet.admin', $links[$menu_group_id]['parent']);
      self::assertSame('admin', $links[$menu_group_id]['menu_name']);
      self::assertSame('<nolink>', $links[$menu_group_id]['route_name']);
      self::assertSame('openintranet_core', $links[$menu_group_id]['provider']);
    }

    foreach ($expected_links as $link_id => [$group_id, $title, $weight]) {
      self::assertSame('openintranet.admin.' . $group_id, $links[$link_id]['parent']);
      self::assertSame('admin', $links[$link_id]['menu_name']);
      self::assertSame($weight, $links[$link_id]['weight']);
      $link_title = $links[$link_id]['title'];
      self::assertInstanceOf(TranslatableMarkup::class, $link_title);
      self::assertSame($title, $link_title->getUntranslatedString());
    }

    self::assertSame($public_link, $links['openintranet_documents.browser']);
  }

  /**
   * Tests that disabled modules do not produce empty menu groups.
   */
  public function testEmptyGroupsAreNotAdded(): void {
    $links = [
      'openintranet_documents.browser' => [
        'title' => 'Documents',
        'route_name' => 'openintranet_documents.browser',
        'menu_name' => 'main',
      ],
    ];
    $original_links = $links;

    openintranet_core_menu_links_discovered_alter($links);

    self::assertSame($original_links, $links);
  }

}
