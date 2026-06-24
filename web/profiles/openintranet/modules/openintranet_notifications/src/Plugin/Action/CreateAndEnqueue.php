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
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
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
  type: 'entity',
)]
#[EcaAction(
  description: new TranslatableMarkup('Create and enqueue notifications of a type for a recipient set (empty lets the type resolve recipients).'),
  version_introduced: '1.0.0',
)]
final class CreateAndEnqueue extends ConfigurableActionBase {

  use NotificationActionTrait;

  /**
   * Resolver ids that fan out to a whole role / all active users (§8).
   *
   * A dispatch through one of these (with no explicit recipients) is a
   * broadcast and is gated on the 'notify all active users' permission.
   */
  private const BROAD_RESOLVER_IDS = ['role_users', 'all_active_users'];

  /**
   * The notification dispatcher.
   *
   * @var \Drupal\openintranet_notifications\Service\NotificationDispatcher
   */
  protected NotificationDispatcher $dispatcher;

  /**
   * The logger channel factory.
   *
   * Injected as the FACTORY, not the built channel: building the
   * openintranet_notifications channel inside this action's create() recurses
   * through the action plugin discovery and aborts the request. The factory is
   * a leaf service, so resolving it is safe; the channel is fetched lazily only
   * on the rarely-hit broadcast-denied branch.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->dispatcher = $container->get('openintranet_notifications.notification_dispatcher');
    $instance->loggerFactory = $container->get('logger.factory');
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

    $recipientsToken = (string) $this->configuration['recipients'];
    // Broadcast gate (00-synteza §8): a fan-out to a whole role/all-active set
    // must be held by the acting account. Re-check here (not only in access())
    // because ECA may invoke execute() without an access pass; an ungated
    // broadcast must create nothing, so bail before any notification is built.
    if ($this->isBroadcast($typeId, $recipientsToken)
      && !$this->currentUser->hasPermission('notify all active users')) {
      $this->loggerFactory->get('openintranet_notifications')->info('Broadcast notification of type @type denied for user @uid: missing "notify all active users".', [
        '@type' => $typeId,
        '@uid' => $this->currentUser->id(),
      ]);
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

    // The per-thread dedupe disambiguator (00-synteza §12): a string template
    // with embedded tokens (e.g. "comment-thread:[commented_node:nid]"), so it
    // is token-REPLACED (not getOrReplace, which only resolves a whole-string
    // token). The resolved string becomes the dedupe identity in the factory.
    $dedupeContext = trim((string) $this->tokenService->replaceClear((string) $this->configuration['dedupe_context']));
    if ($dedupeContext !== '') {
      $context['dedupe_context'] = $dedupeContext;
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
      'dedupe_context' => '',
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
    $form['dedupe_context'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Dedupe context'),
      '#description' => $this->t('Optional per-thread dedupe disambiguator with tokens (e.g. "comment-thread:[commented_node:nid]"). When set, it becomes the dedupe identity together with the recipient, so all notifications sharing it dedupe within the type window.'),
      '#default_value' => $this->configuration['dedupe_context'],
      '#eca_token_replacement' => TRUE,
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
    $this->configuration['dedupe_context'] = $form_state->getValue('dedupe_context');
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
   * Broadcast dispatches (no explicit recipients + a broad resolver on the
   * type) require 'notify all active users' (00-synteza §8). Every other
   * dispatch (explicit recipients, or per-author/per-field resolvers) is
   * allowed, so normal sends are never gated.
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $account = $account ?? $this->currentUser;

    $typeId = trim((string) $this->tokenService->replaceClear($this->configuration['notification_type']));
    $result = ($typeId !== '' && $this->isBroadcast($typeId, (string) $this->configuration['recipients']))
      ? AccessResult::allowedIfHasPermission($account, 'notify all active users')
      : AccessResult::allowed();

    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * Whether this dispatch fans out to a whole role / all active users (§8).
   *
   * A dispatch is a broadcast when the action carries NO explicit recipients
   * (so the type's own resolvers fan it out) AND the type configures a broad
   * resolver (role_users / all_active_users). An explicit recipients token or a
   * per-author/per-field resolver set is never a broadcast.
   *
   * @param string $typeId
   *   The resolved notification_type id.
   * @param string $recipientsToken
   *   The configured recipients token expression (unresolved).
   *
   * @return bool
   *   TRUE when the dispatch is a broadcast.
   */
  private function isBroadcast(string $typeId, string $recipientsToken): bool {
    // An explicit recipients token means the author chose the recipients; the
    // type's resolvers never run, so it is not a broadcast.
    if (trim($recipientsToken) !== '') {
      return FALSE;
    }

    /** @var \Drupal\openintranet_notifications\Entity\NotificationTypeInterface|null $type */
    $type = $this->entityTypeManager
      ->getStorage('openintranet_notification_type')
      ->load($typeId);
    if ($type === NULL) {
      return FALSE;
    }

    foreach ($type->getRecipientResolvers() as $definition) {
      if (in_array($definition['id'] ?? '', self::BROAD_RESOLVER_IDS, TRUE)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
