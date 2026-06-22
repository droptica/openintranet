<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_notifications\Kernel\Entity;

use Drupal\KernelTests\KernelTestBase;
use Drupal\openintranet_notifications\Entity\Handler\NotificationDeliveryListBuilder;
use Drupal\openintranet_notifications\Entity\NotificationDelivery;
use Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * Tests the notification delivery list builder (Chunk 4B).
 *
 * @group openintranet_notifications
 *
 * @coversDefaultClass \Drupal\openintranet_notifications\Entity\Handler\NotificationDeliveryListBuilder
 */
final class NotificationDeliveryListBuilderTest extends KernelTestBase {

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
    $this->installEntitySchema('openintranet_notif_delivery');
    // The 'short' date format used by the list builder lives in system config.
    $this->installConfig(['system']);

    $this->createDelivery('pending', 'email_core', '0@example.com');
    $this->createDelivery('sent', 'email_core', 'jane@example.com');
    $this->createDelivery('failed', 'email_core', 'broken@example.com');
  }

  /**
   * With no status filter the list returns every delivery.
   *
   * @covers ::getEntityIds
   */
  public function testNoFilterReturnsAll(): void {
    $this->setRequestQuery([]);
    $ids = $this->invokeGetEntityIds();
    self::assertCount(3, $ids);
  }

  /**
   * A valid status filter narrows the list to that status only.
   *
   * @covers ::getEntityIds
   */
  public function testStatusFilterReturnsOnlyMatching(): void {
    $this->setRequestQuery(['status' => 'failed']);
    $ids = $this->invokeGetEntityIds();
    self::assertCount(1, $ids);

    $storage = $this->container->get('entity_type.manager')
      ->getStorage('openintranet_notif_delivery');
    $delivery = $storage->load(reset($ids));
    \assert($delivery instanceof NotificationDeliveryInterface);
    self::assertSame('failed', $delivery->get('status')->value);
  }

  /**
   * An unknown status value is ignored and the list is unfiltered.
   *
   * @covers ::getEntityIds
   */
  public function testInvalidStatusFilterIgnored(): void {
    $this->setRequestQuery(['status' => 'bogus']);
    $ids = $this->invokeGetEntityIds();
    self::assertCount(3, $ids);
  }

  /**
   * The recipient column masks the transport address (no raw PII).
   *
   * @covers ::buildRow
   */
  public function testRecipientAddressIsMasked(): void {
    $this->setRequestQuery([]);
    $list_builder = $this->container->get('entity_type.manager')
      ->getListBuilder('openintranet_notif_delivery');
    \assert($list_builder instanceof NotificationDeliveryListBuilder);

    $delivery = NotificationDelivery::create([
      'notification_id' => 1,
      'recipient_type' => 'email',
      'channel' => 'email_core',
      'address' => 'jane@example.com',
      'status' => 'sent',
      'idempotency_key' => 'mask-1',
    ]);
    $delivery->save();

    $row = $list_builder->buildRow($delivery);
    $recipient = (string) $row['recipient'];
    self::assertStringNotContainsString('jane@example.com', $recipient);
    self::assertStringContainsString('j***@e***.com', $recipient);
  }

  /**
   * The render() output prepends a status filter form above the table.
   *
   * @covers ::render
   */
  public function testRenderPrependsFilterForm(): void {
    $this->setRequestQuery([]);
    $list_builder = $this->container->get('entity_type.manager')
      ->getListBuilder('openintranet_notif_delivery');
    $build = $list_builder->render();
    self::assertArrayHasKey('filter', $build);
    self::assertArrayHasKey('table', $build);
  }

  /**
   * Creates a saved delivery with the given status, channel and address.
   */
  private function createDelivery(string $status, string $channel, string $address): void {
    static $i = 0;
    $i++;
    NotificationDelivery::create([
      'notification_id' => 1,
      'recipient_type' => 'email',
      'channel' => $channel,
      'address' => $address,
      'status' => $status,
      'idempotency_key' => 'idem-' . $i,
    ])->save();
  }

  /**
   * Sets query parameters on the bootstrapped current request.
   *
   * The request stack already carries a request with a session; replacing its
   * query bag keeps that session intact (tearDown reads it).
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
      ->getListBuilder('openintranet_notif_delivery');
    $method = new \ReflectionMethod($list_builder, 'getEntityIds');
    $method->setAccessible(TRUE);
    return array_values($method->invoke($list_builder));
  }

}
