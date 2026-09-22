<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Entity;

use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Entity\Handler\NotificationListBuilder;
use Drupal\openintranet_notifications\Entity\Notification;
use Drupal\openintranet_notifications\Entity\NotificationInterface;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * Tests the notification list builder (Chunk 4B).
 *
 * @group openintranet_notifications
 *
 * @coversDefaultClass \Drupal\openintranet_notifications\Entity\Handler\NotificationListBuilder
 */
final class NotificationListBuilderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'options',
    'dynamic_entity_reference',
    'token',
    'modeler_api',
    'eca',
    'eca_base',
    'eca_content',
    'views',
    'openintranet_notifications',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('openintranet_notification');
    // The 'short' date format used by the list builder lives in system config.
    $this->installConfig(['system']);
  }

  /**
   * The header exposes every documented column.
   *
   * @covers ::buildHeader
   */
  public function testHeaderHasExpectedColumns(): void {
    $list_builder = $this->container->get('entity_type.manager')
      ->getListBuilder('openintranet_notification');
    \assert($list_builder instanceof NotificationListBuilder);
    $header = $list_builder->buildHeader();
    foreach (['id', 'type', 'uid', 'subject', 'priority', 'status', 'created', 'read'] as $key) {
      self::assertArrayHasKey($key, $header);
    }
  }

  /**
   * Rows carry the entity values, with read rendered Yes/No from read_at.
   *
   * @covers ::buildRow
   */
  public function testRowRendersColumnsAndReadState(): void {
    $unread = Notification::create([
      'type' => 'mention',
      'uid' => 42,
      'subject' => 'You were mentioned',
      'priority' => 'high',
      'status' => 'delivered',
    ]);
    $unread->save();

    $read = Notification::create([
      'type' => 'digest',
      'uid' => 7,
      'subject' => 'Weekly digest',
      'priority' => 'low',
      'status' => 'delivered',
    ]);
    $read->setRead();
    $read->save();

    $list_builder = $this->container->get('entity_type.manager')
      ->getListBuilder('openintranet_notification');
    \assert($list_builder instanceof NotificationListBuilder);

    $unread_row = $list_builder->buildRow($unread);
    self::assertSame($unread->id(), $unread_row['id']);
    self::assertSame('mention', (string) $unread_row['type']);
    self::assertSame('42', (string) $unread_row['uid']);
    self::assertSame('You were mentioned', (string) $unread_row['subject']);
    self::assertSame('high', (string) $unread_row['priority']);
    self::assertSame('delivered', (string) $unread_row['status']);
    self::assertNotSame('', (string) $unread_row['created']);
    self::assertSame('No', (string) $unread_row['read']);

    $read_row = $list_builder->buildRow($read);
    self::assertSame('Yes', (string) $read_row['read']);
  }

  /**
   * With no status filter the list returns every notification.
   *
   * @covers ::getEntityIds
   */
  public function testNoFilterReturnsAll(): void {
    $this->seedStatuses();
    $this->setRequestQuery([]);
    self::assertCount(3, $this->invokeGetEntityIds());
  }

  /**
   * A valid status filter narrows the list to that status only.
   *
   * @covers ::getEntityIds
   */
  public function testStatusFilterReturnsOnlyMatching(): void {
    $this->seedStatuses();
    $this->setRequestQuery(['status' => 'failed']);
    $ids = $this->invokeGetEntityIds();
    self::assertCount(1, $ids);

    $notification = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notification')
      ->load(reset($ids));
    \assert($notification instanceof NotificationInterface);
    self::assertSame('failed', $notification->get('status')->value);
  }

  /**
   * An unknown status value is ignored and the list is unfiltered.
   *
   * @covers ::getEntityIds
   */
  public function testInvalidStatusFilterIgnored(): void {
    $this->seedStatuses();
    $this->setRequestQuery(['status' => 'bogus']);
    self::assertCount(3, $this->invokeGetEntityIds());
  }

  /**
   * The render() output prepends a status filter form above the table.
   *
   * @covers ::render
   */
  public function testRenderPrependsFilterForm(): void {
    $this->setRequestQuery([]);
    $list_builder = $this->container->get('entity_type.manager')
      ->getListBuilder('openintranet_notification');
    $build = $list_builder->render();
    self::assertArrayHasKey('filter', $build);
    self::assertArrayHasKey('table', $build);
  }

  /**
   * The status filter vocabulary is single-sourced from the entity's
   * allowed_values, so it cannot drift (regression: 'resolving' was missing).
   *
   * @covers ::statusFilterOptions
   */
  public function testStatusFilterMatchesEntityAllowedValues(): void {
    $allowed = $this->container->get('entity_field.manager')
      ->getBaseFieldDefinitions('openintranet_notification')['status']
      ->getSetting('allowed_values');

    $list_builder = $this->container->get('entity_type.manager')
      ->getListBuilder('openintranet_notification');
    $method = new \ReflectionMethod($list_builder, 'statusFilterOptions');
    $method->setAccessible(TRUE);
    $options = $method->invoke($list_builder);

    self::assertArrayHasKey('resolving', $options);
    self::assertSame($allowed, $options);
  }

  /**
   * Saves three notifications with distinct statuses (created/queued/failed).
   */
  private function seedStatuses(): void {
    foreach (['created', 'queued', 'failed'] as $status) {
      Notification::create([
        'type' => 'mention',
        'uid' => 1,
        'subject' => 'S',
        'priority' => 'normal',
        'status' => $status,
      ])->save();
    }
  }

  /**
   * Sets query parameters on the bootstrapped current request.
   *
   * @param array<string, string> $query
   *   The query parameters.
   */
  private function setRequestQuery(array $query): void {
    $request = $this->container->get('request_stack')->getCurrentRequest();
    $request->query = new InputBag($query);
  }

  /**
   * Invokes the protected getEntityIds() on a fresh list builder instance.
   *
   * @return array<int|string>
   *   The matched entity ids.
   */
  private function invokeGetEntityIds(): array {
    $list_builder = $this->container->get('entity_type.manager')
      ->getListBuilder('openintranet_notification');
    $method = new \ReflectionMethod($list_builder, 'getEntityIds');
    $method->setAccessible(TRUE);
    return array_values($method->invoke($list_builder));
  }

}
