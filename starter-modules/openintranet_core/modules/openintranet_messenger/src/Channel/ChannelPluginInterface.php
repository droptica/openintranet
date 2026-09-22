<?php

declare(strict_types=1);

namespace Drupal\openintranet_messenger\Channel;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\openintranet_messenger\Recipient\RecipientInterface;

/**
 * Interface for notification channel plugins.
 */
interface ChannelPluginInterface extends PluginInspectionInterface {

  /**
   * Gets the channel machine name.
   *
   * @return string
   *   The channel ID.
   */
  public function getId(): string;

  /**
   * Gets the human-readable label.
   *
   * @return string
   *   The label.
   */
  public function getLabel(): string;

  /**
   * Gets the channel description.
   *
   * @return string
   *   The description.
   */
  public function getDescription(): string;

  /**
   * Checks if the channel is configured and available.
   *
   * @return bool
   *   TRUE if the channel can be used.
   */
  public function isAvailable(): bool;

  /**
   * Checks if a recipient can receive via this channel.
   *
   * @param \Drupal\openintranet_messenger\Recipient\RecipientInterface $recipient
   *   The recipient.
   *
   * @return bool
   *   TRUE if the recipient has a valid address for this channel.
   */
  public function canSendTo(RecipientInterface $recipient): bool;

  /**
   * Gets the recipient's address for this channel.
   *
   * @param \Drupal\openintranet_messenger\Recipient\RecipientInterface $recipient
   *   The recipient.
   *
   * @return string|null
   *   The address or NULL if not available.
   */
  public function getRecipientAddress(RecipientInterface $recipient): ?string;

  /**
   * Sends a notification.
   *
   * @param \Drupal\openintranet_messenger\Recipient\RecipientInterface $recipient
   *   The recipient.
   * @param string $subject
   *   The notification subject.
   * @param string $message
   *   The notification message.
   *
   * @return bool
   *   TRUE if sent successfully.
   *
   * @throws \Drupal\openintranet_messenger\Exception\ChannelException
   *   If sending fails.
   */
  public function send(RecipientInterface $recipient, string $subject, string $message): bool;

  /**
   * Builds the configuration form for this channel.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form array.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array;

  /**
   * Validates the configuration form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void;

  /**
   * Submits the configuration form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void;

}
