<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\openintranet_notifications\Service\DeliveryQueue;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Bulk-operations form: retry failed and cancel pending deliveries (Chunk 4C).
 *
 * A custom tableselect form (VBO is absent). The retry/cancel work is delegated
 * to DeliveryQueue so the enqueue+reset and cancel logic stay in one place,
 * shared with the cancel ECA action and the drush retry command (§7/§16 DRY).
 */
final class DeliveryBulkOperationsForm extends FormBase {

  /**
   * The delivery statuses the retry button acts on.
   */
  private const RETRYABLE_STATUSES = ['failed'];

  /**
   * The delivery statuses the cancel button acts on.
   */
  private const CANCELLABLE_STATUSES = ['pending', 'processing'];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DeliveryQueue $deliveryQueue,
    private readonly RequestStack $request,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('openintranet_notifications.delivery_queue'),
      $container->get('request_stack'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'openintranet_notifications_delivery_bulk';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $storage = $this->entityTypeManager->getStorage('openintranet_notif_delivery');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->sort('id', 'DESC')
      ->range(0, 200);

    $status = (string) $this->request->getCurrentRequest()?->query->get('status', '');
    if ($status !== '') {
      $query->condition('status', $status);
    }
    $ids = $query->execute();

    $options = [];
    /** @var \Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface $delivery */
    foreach ($storage->loadMultiple($ids) as $delivery) {
      $options[$delivery->id()] = [
        'id' => $delivery->id(),
        'notification' => $delivery->get('notification_id')->target_id,
        'channel' => $delivery->get('channel')->value,
        'status' => $delivery->get('status')->value,
        'attempt_count' => (int) $delivery->get('attempt_count')->value,
      ];
    }

    $form['deliveries'] = [
      '#type' => 'tableselect',
      '#header' => [
        'id' => $this->t('ID'),
        'notification' => $this->t('Notification'),
        'channel' => $this->t('Channel'),
        'status' => $this->t('Status'),
        'attempt_count' => $this->t('Attempts'),
      ],
      '#options' => $options,
      '#empty' => $this->t('No deliveries found.'),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['retry'] = [
      '#type' => 'submit',
      '#value' => $this->t('Retry failed'),
      '#submit' => ['::retrySubmit'],
    ];
    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel pending'),
      '#submit' => ['::cancelSubmit'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Each action button carries its own #submit handler; this is unused.
  }

  /**
   * Re-enqueues each selected delivery whose status is failed.
   */
  public function retrySubmit(array &$form, FormStateInterface $form_state): void {
    $retried = 0;
    foreach ($this->loadSelected($form_state) as $delivery) {
      if (\in_array($delivery->get('status')->value, self::RETRYABLE_STATUSES, TRUE)) {
        $this->deliveryQueue->requeue($delivery);
        $retried++;
      }
    }
    $this->messenger()->addStatus($this->formatPlural($retried, 'Re-queued 1 failed delivery.', 'Re-queued @count failed deliveries.'));
  }

  /**
   * Cancels each selected delivery whose status is pending or processing.
   */
  public function cancelSubmit(array &$form, FormStateInterface $form_state): void {
    $cancelled = 0;
    foreach ($this->loadSelected($form_state) as $delivery) {
      if (\in_array($delivery->get('status')->value, self::CANCELLABLE_STATUSES, TRUE)) {
        $this->deliveryQueue->cancel($delivery);
        $cancelled++;
      }
    }
    $this->messenger()->addStatus($this->formatPlural($cancelled, 'Cancelled 1 delivery.', 'Cancelled @count deliveries.'));
  }

  /**
   * Loads the delivery entities the user selected in the tableselect.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The submitted form state.
   *
   * @return array<int, \Drupal\openintranet_notifications\Entity\NotificationDeliveryInterface>
   *   The selected delivery entities.
   */
  private function loadSelected(FormStateInterface $form_state): array {
    $selected = array_filter((array) $form_state->getValue('deliveries'));
    if ($selected === []) {
      return [];
    }
    return $this->entityTypeManager
      ->getStorage('openintranet_notif_delivery')
      ->loadMultiple(array_keys($selected));
  }

}
