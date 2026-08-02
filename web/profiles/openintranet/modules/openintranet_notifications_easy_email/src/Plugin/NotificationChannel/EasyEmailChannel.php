<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications_easy_email\Plugin\NotificationChannel;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\easy_email\Service\EmailHandlerInterface;
use Drupal\openintranet_notifications\Attribute\NotificationChannel;
use Drupal\openintranet_notifications\Channel\NotificationChannelBase;
use Drupal\openintranet_notifications\Dto\DeliveryResult;
use Drupal\openintranet_notifications\Dto\NotificationMessage;
use Drupal\openintranet_notifications\Dto\NotificationRecipient;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Delivers notifications through an Easy Email template.
 *
 * Creates an easy_email entity of the configured easy_email_type, addressed to
 * the recipient with the rendered subject/body, and sends it via the Easy Email
 * handler. Transport outcomes are classified — never thrown — so the queue
 * worker decides whether to retry (00-synteza §3.1/§8).
 *
 * @todo The live Easy Email send (real mail/token/render pipeline) is covered by
 *   manual QA; the kernel tests drive create+send against a handler double, see
 *   \Drupal\Tests\openintranet_notifications_easy_email\Kernel\TestEmailHandler.
 */
#[NotificationChannel(
  id: 'email_easy_email',
  label: new TranslatableMarkup('Email (Easy Email)'),
  description: new TranslatableMarkup('Sends the message through a configured Easy Email template.'),
)]
final class EasyEmailChannel extends NotificationChannelBase {

  /**
   * Constructs an EasyEmailChannel.
   *
   * @param array $configuration
   *   The plugin configuration; key 'email_type' holds the easy_email_type id.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\easy_email\Service\EmailHandlerInterface $emailHandler
   *   The Easy Email handler that creates and sends easy_email entities.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager, used to resolve the easy_email_type.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    private readonly EmailHandlerInterface $emailHandler,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('easy_email.handler'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    $emailType = $this->configuredEmailType();
    return $emailType !== '' && $this->loadEmailType($emailType) !== NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getRecipientAddress(NotificationRecipient $recipient): ?string {
    return $this->resolveUserOrEmailAddress($recipient);
  }

  /**
   * {@inheritdoc}
   */
  public function send(NotificationRecipient $recipient, NotificationMessage $message): DeliveryResult {
    $address = $this->getRecipientAddress($recipient);
    if ($address === NULL) {
      return DeliveryResult::permanentFailure('NO_ADDRESS', 'No email address for recipient.');
    }

    $emailType = $this->configuredEmailType();
    if ($emailType === '' || $this->loadEmailType($emailType) === NULL) {
      return DeliveryResult::permanentFailure('NO_EMAIL_TYPE', 'No usable Easy Email type configured.');
    }

    try {
      $email = $this->emailHandler->createEmail(['type' => $emailType]);
      $email->setRecipientAddresses([$address]);
      $email->setSubject($message->subject);
      $email->setHtmlBody($message->body, 'plain_text');
      $result = $this->emailHandler->sendEmail($email);
    }
    catch (\Throwable $e) {
      return DeliveryResult::retryableFailure('EASY_EMAIL_EXCEPTION', $e->getMessage());
    }

    // sendEmail() returns the sent email entities (FALSE on a short-circuit);
    // a message is delivered only when at least one entity reports it as sent.
    if (is_array($result)) {
      foreach ($result as $sent) {
        if ($sent->isSent()) {
          return DeliveryResult::success();
        }
      }
      return DeliveryResult::retryableFailure('EASY_EMAIL_FAILED', 'Easy Email reported the message was not sent.');
    }

    // A non-array (FALSE) is a short-circuit: an already-sent message or a
    // suppressed duplicate (same unique key already delivered) is effectively
    // delivered — classify it as success so the worker does not retry it. Only
    // a genuine non-duplicate FALSE is a retryable failure.
    if ($email->isSent() || $this->emailHandler->duplicateExists($email)) {
      return DeliveryResult::success();
    }
    return DeliveryResult::retryableFailure('EASY_EMAIL_FAILED', 'Easy Email reported the message was not sent.');
  }

  /**
   * The configured easy_email_type id, or an empty string when unset.
   */
  private function configuredEmailType(): string {
    return (string) ($this->configuration['email_type'] ?? '');
  }

  /**
   * Loads the configured easy_email_type, or NULL when it does not exist.
   */
  private function loadEmailType(string $id): ?object {
    return $this->entityTypeManager->getStorage('easy_email_type')->load($id);
  }

}
