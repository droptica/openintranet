<?php

declare(strict_types=1);

namespace Drupal\openintranet_messenger\Recipient;

use Drupal\openintranet_messenger\MessengerContactInterface;

/**
 * Recipient wrapper for MessengerContact entities.
 */
final class ContactRecipient implements RecipientInterface {

  /**
   * Constructs a ContactRecipient object.
   *
   * @param \Drupal\openintranet_messenger\MessengerContactInterface $contact
   *   The messenger contact entity.
   */
  public function __construct(
    private readonly MessengerContactInterface $contact,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return $this->contact->getName();
  }

  /**
   * {@inheritdoc}
   */
  public function getEmail(): ?string {
    return $this->contact->getEmail();
  }

  /**
   * {@inheritdoc}
   */
  public function getPhone(): ?string {
    return $this->contact->getPhone();
  }

  /**
   * {@inheritdoc}
   */
  public function getPreferredChannel(): string {
    return $this->contact->getPreferredChannel();
  }

  /**
   * {@inheritdoc}
   */
  public function getChannelAddress(string $channel_id): ?string {
    return match ($channel_id) {
      'email' => $this->getEmail(),
      'sms' => $this->getPhone(),
      'slack' => $this->contact->get('slack_id')->value,
      'teams' => $this->contact->get('teams_id')->value,
      'nextcloud' => $this->contact->get('nextcloud_id')->value,
      default => NULL,
    };
  }

  /**
   * {@inheritdoc}
   */
  public function getLangcode(): string {
    return $this->contact->language()->getId();
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceInfo(): array {
    return [
      'type' => 'contact',
      'id' => (int) $this->contact->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function canReceiveVia(string $channel_id): bool {
    $address = $this->getChannelAddress($channel_id);
    return $address !== NULL && $address !== '';
  }

  /**
   * Gets the underlying contact entity.
   *
   * @return \Drupal\openintranet_messenger\MessengerContactInterface
   *   The contact entity.
   */
  public function getContact(): MessengerContactInterface {
    return $this->contact;
  }

}
