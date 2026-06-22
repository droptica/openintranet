<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Entity\Handler;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface;
use Drupal\openintranet_notifications\Service\AuditLogger;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Lists notification deliveries with a status filter and masked addresses.
 *
 * §4.3 delivery dashboard, §14. Transport addresses are masked via the audit
 * logger so the dashboard never leaks raw PII (§9).
 */
final class NotificationDeliveryListBuilder extends EntityListBuilder {

  /**
   * The delivery statuses offered by the filter (mirrors the base field).
   */
  private const STATUSES = [
    'pending' => 'Pending',
    'processing' => 'Processing',
    'sent' => 'Sent',
    'delivered' => 'Delivered',
    'failed' => 'Failed',
    'skipped' => 'Skipped',
    'cancelled' => 'Cancelled',
  ];

  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly AuditLogger $auditLogger,
    private readonly RequestStack $requestStack,
  ) {
    parent::__construct($entity_type, $storage);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): self {
    return new self(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('date.formatter'),
      $container->get('openintranet_notifications.audit_logger'),
      $container->get('request_stack'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds(): array {
    $query = $this->getStorage()->getQuery()
      ->accessCheck(TRUE)
      ->sort($this->entityType->getKey('id'), 'DESC');

    $status = (string) $this->requestStack->getCurrentRequest()?->query->get('status', '');
    if ($status !== '' && isset(self::STATUSES[$status])) {
      $query->condition('status', $status);
    }

    if ($this->limit) {
      $query->pager($this->limit);
    }
    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $build['filter'] = $this->buildFilterForm();
    $build += parent::render();
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header = [
      'id' => $this->t('ID'),
      'notification_id' => $this->t('Notification'),
      'recipient' => $this->t('Recipient'),
      'channel' => $this->t('Channel'),
      'status' => $this->t('Status'),
      'attempt_count' => $this->t('Attempts'),
      'last_error_code' => $this->t('Last error'),
      'sent' => $this->t('Sent'),
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    assert($entity instanceof NotificationDeliveryInterface);
    $sent = (int) $entity->get('sent')->value;
    $row = [
      'id' => $entity->id(),
      'notification_id' => $entity->get('notification_id')->target_id,
      'recipient' => $this->formatRecipient($entity),
      'channel' => $entity->get('channel')->value,
      'status' => $entity->get('status')->value,
      'attempt_count' => (int) $entity->get('attempt_count')->value,
      'last_error_code' => $entity->get('last_error_code')->value,
      'sent' => $sent > 0 ? $this->dateFormatter->format($sent, 'short') : '',
    ];
    return $row + parent::buildRow($entity);
  }

  /**
   * Builds the "type: masked-address" recipient label for a delivery.
   */
  private function formatRecipient(NotificationDeliveryInterface $entity): string {
    $type = (string) $entity->get('recipient_type')->value;
    $address = $this->auditLogger->maskPii((string) $entity->get('address')->value);
    return $address === '' ? $type : $type . ': ' . $address;
  }

  /**
   * Builds the GET status filter form rendered above the listing.
   *
   * @return array<string, mixed>
   *   A render array for the filter form.
   */
  private function buildFilterForm(): array {
    $current = (string) $this->requestStack->getCurrentRequest()?->query->get('status', '');
    $options = ['' => $this->t('- Any status -')];
    foreach (self::STATUSES as $value => $label) {
      $options[$value] = $this->t('@label', ['@label' => $label]);
    }

    return [
      '#type' => 'html_tag',
      '#tag' => 'form',
      '#attributes' => ['method' => 'get', 'class' => ['openintranet-notif-delivery-filter']],
      'status' => [
        '#type' => 'select',
        '#name' => 'status',
        '#title' => $this->t('Status'),
        '#options' => $options,
        '#value' => isset(self::STATUSES[$current]) ? $current : '',
      ],
      'submit' => [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => $this->t('Apply'),
        '#attributes' => ['type' => 'submit'],
      ],
    ];
  }

}
