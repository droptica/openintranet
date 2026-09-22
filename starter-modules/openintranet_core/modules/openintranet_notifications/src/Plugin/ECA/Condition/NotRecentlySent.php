<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\ECA\Condition;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaCondition;
use Drupal\eca\Plugin\ECA\Condition\ConditionBase;
use Drupal\openintranet_notifications\Service\Deduplicator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Passes when a dedupe key has not been recorded within its window.
 *
 * Pure check: isDuplicate() reads the dedupe store without recording, so the
 * condition is side-effect-free. An empty resolved key fails the condition.
 */
#[EcaCondition(
  id: 'openintranet_notifications_not_recently_sent',
  label: new TranslatableMarkup('Notification: not recently sent'),
  description: new TranslatableMarkup('Passes when the dedupe key has not been recorded within its window.'),
  version_introduced: '1.0.0',
)]
class NotRecentlySent extends ConditionBase {

  /**
   * The deduplicator.
   *
   * @var \Drupal\openintranet_notifications\Service\Deduplicator
   */
  protected Deduplicator $deduplicator;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->deduplicator = $container->get('openintranet_notifications.deduplicator');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    $key = (string) $this->tokenService->replaceClear($this->configuration['dedupe_key']);
    $result = $key !== '' && !$this->deduplicator->isDuplicate($key);

    return $this->negationCheck($result);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'dedupe_key' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['dedupe_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Dedupe key'),
      '#description' => $this->t('The dedupe key to check (token allowed).'),
      '#default_value' => $this->configuration['dedupe_key'],
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['dedupe_key'] = $form_state->getValue('dedupe_key');
    parent::submitConfigurationForm($form, $form_state);
  }

}
