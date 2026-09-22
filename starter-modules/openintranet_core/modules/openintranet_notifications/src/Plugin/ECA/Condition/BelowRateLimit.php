<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\ECA\Condition;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaCondition;
use Drupal\eca\Plugin\ECA\Condition\ConditionBase;
use Drupal\openintranet_notifications\Service\RateLimiter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Passes when a recipient is below the rate limit for a channel and type.
 *
 * Side-effect-free: uses the limiter's non-mutating peek so evaluating the
 * condition never consumes the recipient's budget.
 */
#[EcaCondition(
  id: 'openintranet_notifications_below_rate_limit',
  label: new TranslatableMarkup('Notification: below rate limit'),
  description: new TranslatableMarkup('Passes when the recipient is below the rate limit for the channel and notification type.'),
  version_introduced: '1.0.0',
)]
class BelowRateLimit extends ConditionBase {

  /**
   * The rate limiter.
   *
   * @var \Drupal\openintranet_notifications\Service\RateLimiter
   */
  protected RateLimiter $rateLimiter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->rateLimiter = $container->get('openintranet_notifications.rate_limiter');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    $uid = $this->tokenService->replaceClear($this->configuration['uid']);
    $channel = $this->tokenService->replaceClear($this->configuration['channel']);
    $type = $this->tokenService->replaceClear($this->configuration['notification_type']);
    $limit = $this->tokenService->replaceClear($this->configuration['limit']);

    return $this->negationCheck(
      $this->rateLimiter->isWithinLimit((int) $uid, (string) $channel, (string) $type, (int) $limit),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'uid' => '',
      'channel' => '',
      'notification_type' => '',
      'limit' => 0,
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
    $form['channel'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Channel'),
      '#description' => $this->t('The channel plugin id (token allowed).'),
      '#default_value' => $this->configuration['channel'],
      '#eca_token_replacement' => TRUE,
    ];
    $form['notification_type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Notification type'),
      '#description' => $this->t('The notification type machine name (token allowed).'),
      '#default_value' => $this->configuration['notification_type'],
      '#eca_token_replacement' => TRUE,
    ];
    $form['limit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Limit'),
      '#description' => $this->t('The maximum number of sends allowed within the window (token allowed).'),
      '#default_value' => $this->configuration['limit'],
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['uid'] = $form_state->getValue('uid');
    $this->configuration['channel'] = $form_state->getValue('channel');
    $this->configuration['notification_type'] = $form_state->getValue('notification_type');
    $this->configuration['limit'] = $form_state->getValue('limit');
    parent::submitConfigurationForm($form, $form_state);
  }

}
