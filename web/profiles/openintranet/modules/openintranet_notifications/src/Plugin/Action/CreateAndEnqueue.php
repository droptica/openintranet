<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\openintranet_notifications\Service\NotificationDispatcher;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates and enqueues notifications for a type and recipient set.
 *
 * The 80% case: hands the event's primary object to the dispatcher as the
 * source entity, resolves the optional recipients token, and lets the
 * dispatcher fan out (an empty recipient set runs the type's own resolvers).
 */
#[Action(
  id: 'openintranet_notifications_create_and_enqueue',
  label: new TranslatableMarkup('Notification: create and enqueue'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Create and enqueue notifications of a type for a recipient set (empty lets the type resolve recipients).'),
  version_introduced: '1.0.0',
)]
final class CreateAndEnqueue extends ConfigurableActionBase {

  use NotificationActionTrait;

  /**
   * The notification dispatcher.
   *
   * @var \Drupal\openintranet_notifications\Service\NotificationDispatcher
   */
  protected NotificationDispatcher $dispatcher;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->dispatcher = $container->get('openintranet_notifications.notification_dispatcher');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    $typeId = trim((string) $this->tokenService->replaceClear($this->configuration['notification_type']));
    if ($typeId === '') {
      return;
    }

    $context = [];
    if ($object instanceof EntityInterface) {
      $context['entity'] = $object;
      $context['source_entity'] = $object;
    }
    $actor = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    if ($actor !== NULL) {
      $context['actor'] = $actor;
    }

    // Let the model inject extra typed context (e.g. the commented node) by
    // mapping context keys to token expressions. getOrReplace() preserves the
    // entity object so resolvers receive the entity, not its id.
    foreach ($this->configuration['context'] as $key => $tokenExpr) {
      $resolved = $this->tokenService->getOrReplace((string) $tokenExpr);
      if ($resolved !== NULL && $resolved !== '') {
        $context[$key] = $resolved;
      }
    }

    $recipients = $this->resolveRecipientUids((string) $this->configuration['recipients']);

    $this->dispatcher->dispatchRequest($typeId, $recipients, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'notification_type' => '',
      'recipients' => '',
      'context' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['notification_type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Notification type'),
      '#description' => $this->t('The notification type machine name.'),
      '#default_value' => $this->configuration['notification_type'],
      '#eca_token_replacement' => TRUE,
      '#required' => TRUE,
    ];
    $form['recipients'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Recipients'),
      '#description' => $this->t('A token resolving to a list of recipient user ids. Leave empty to let the type resolve recipients.'),
      '#default_value' => $this->configuration['recipients'],
      '#eca_token_replacement' => TRUE,
    ];
    $form['context'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Context tokens'),
      '#description' => $this->t('One "key: token" pair per line, injecting extra typed context for the type resolvers (e.g. "commented_entity: [commented_node]").'),
      '#default_value' => $this->contextToString($this->configuration['context']),
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['notification_type'] = $form_state->getValue('notification_type');
    $this->configuration['recipients'] = $form_state->getValue('recipients');
    $this->configuration['context'] = $this->contextFromString((string) $form_state->getValue('context'));
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * Formats the context map as "key: token" lines for the form.
   *
   * @param array<string, string> $context
   *   The context-key to token-expression map.
   *
   * @return string
   *   One "key: token" pair per line.
   */
  private function contextToString(array $context): string {
    $lines = [];
    foreach ($context as $key => $tokenExpr) {
      $lines[] = $key . ': ' . $tokenExpr;
    }
    return implode("\n", $lines);
  }

  /**
   * Parses "key: token" lines from the form back into a context map.
   *
   * @param string $value
   *   The submitted textarea value.
   *
   * @return array<string, string>
   *   The context-key to token-expression map.
   */
  private function contextFromString(string $value): array {
    $context = [];
    foreach (preg_split('/\R/', $value) ?: [] as $line) {
      $line = trim($line);
      if ($line === '' || !str_contains($line, ':')) {
        continue;
      }
      [$key, $tokenExpr] = explode(':', $line, 2);
      $key = trim($key);
      if ($key !== '') {
        $context[$key] = trim($tokenExpr);
      }
    }
    return $context;
  }

  /**
   * {@inheritdoc}
   *
   * @todo Gate the "notify all active users" broadcast on a base permission
   *   once a resolver exposes that semantics (Stage 2 scope: default allowed).
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = AccessResult::allowed();
    return $return_as_object ? $result : $result->isAllowed();
  }

}
