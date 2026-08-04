<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\ECA\Condition;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaCondition;
use Drupal\eca\Plugin\ECA\Condition\ConditionBase;
use Drupal\openintranet_notifications\Service\PreferenceResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Passes when a user has a channel enabled for a notification type.
 *
 * Delegates entirely to the preference resolver; carries no own logic.
 */
#[EcaCondition(
  id: 'openintranet_notifications_user_channel_enabled',
  label: new TranslatableMarkup('Notification: user channel enabled'),
  description: new TranslatableMarkup('Passes when the user has the given channel enabled for the notification type.'),
  version_introduced: '1.0.0',
)]
class UserChannelEnabled extends ConditionBase {

  /**
   * The preference resolver.
   *
   * @var \Drupal\openintranet_notifications\Service\PreferenceResolverInterface
   */
  protected PreferenceResolverInterface $preferenceResolver;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->preferenceResolver = $container->get('openintranet_notifications.preference_resolver');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    $uid = $this->tokenService->replaceClear($this->configuration['uid']);
    $type = $this->tokenService->replaceClear($this->configuration['notification_type']);
    $channel = $this->tokenService->replaceClear($this->configuration['channel']);

    return $this->negationCheck(
      $this->preferenceResolver->isEnabled((int) $uid, (string) $type, (string) $channel),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'uid' => '',
      'notification_type' => '',
      'channel' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['uid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Recipient user id'),
      '#description' => $this->t('The recipient user id (token allowed).'),
      '#default_value' => $this->configuration['uid'],
      '#eca_token_replacement' => TRUE,
    ];
    $form['notification_type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Notification type'),
      '#description' => $this->t('The notification type machine name (token allowed).'),
      '#default_value' => $this->configuration['notification_type'],
      '#eca_token_replacement' => TRUE,
    ];
    $form['channel'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Channel'),
      '#description' => $this->t('The channel plugin id (token allowed).'),
      '#default_value' => $this->configuration['channel'],
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['uid'] = $form_state->getValue('uid');
    $this->configuration['notification_type'] = $form_state->getValue('notification_type');
    $this->configuration['channel'] = $form_state->getValue('channel');
    parent::submitConfigurationForm($form, $form_state);
  }

}
