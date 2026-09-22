<?php

declare(strict_types=1);

namespace Drupal\openintranet_messenger\Recipient;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\user\UserInterface;

/**
 * Recipient wrapper for Drupal User entities.
 */
final class UserRecipient implements RecipientInterface {

  /**
   * The field name for phone number on User entity.
   *
   * @var string
   */
  private string $phoneField;

  /**
   * Constructs a UserRecipient object.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user entity.
   * @param string $phone_field
   *   The field name for phone number.
   */
  public function __construct(
    private readonly UserInterface $user,
    string $phone_field = 'field_phone',
  ) {
    $this->phoneField = $phone_field;
  }

  /**
   * Creates a UserRecipient from config.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user entity.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   *
   * @return self
   *   The UserRecipient instance.
   */
  public static function createFromConfig(UserInterface $user, ConfigFactoryInterface $config_factory): self {
    $phone_field = $config_factory->get('openintranet_messenger.settings')->get('user_phone_field') ?? 'field_phone';
    return new self($user, $phone_field);
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return $this->user->getDisplayName();
  }

  /**
   * {@inheritdoc}
   */
  public function getEmail(): ?string {
    return $this->user->getEmail();
  }

  /**
   * {@inheritdoc}
   */
  public function getPhone(): ?string {
    if (!$this->user->hasField($this->phoneField)) {
      return NULL;
    }

    $value = $this->user->get($this->phoneField)->value;
    return $value ?: NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getPreferredChannel(): string {
    // Check if user has a preferred channel field.
    if ($this->user->hasField('field_preferred_channel')) {
      $value = $this->user->get('field_preferred_channel')->value;
      if ($value) {
        return $value;
      }
    }
    // Default to email for users.
    return 'email';
  }

  /**
   * {@inheritdoc}
   */
  public function getChannelAddress(string $channel_id): ?string {
    return match ($channel_id) {
      'email' => $this->getEmail(),
      'sms' => $this->getPhone(),
      'slack' => $this->getFieldValue('field_slack_id'),
      'teams' => $this->getFieldValue('field_teams_id'),
      'nextcloud' => $this->getFieldValue('field_nextcloud_id'),
      default => NULL,
    };
  }

  /**
   * {@inheritdoc}
   */
  public function getLangcode(): string {
    return $this->user->getPreferredLangcode();
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceInfo(): array {
    return [
      'type' => 'user',
      'id' => (int) $this->user->id(),
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
   * Gets the underlying user entity.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity.
   */
  public function getUser(): UserInterface {
    return $this->user;
  }

  /**
   * Gets a field value from the user if it exists.
   *
   * @param string $field_name
   *   The field name.
   *
   * @return string|null
   *   The field value or NULL.
   */
  private function getFieldValue(string $field_name): ?string {
    if (!$this->user->hasField($field_name)) {
      return NULL;
    }
    $value = $this->user->get($field_name)->value;
    return $value ?: NULL;
  }

}
