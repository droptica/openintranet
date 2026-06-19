<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\openintranet_notifications\Resolver\RecipientResolverManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Resolves a recipient set and writes the uid list to an output token.
 *
 * Either runs a single resolver (resolver_id + configuration) or, when no
 * resolver id is given, runs the notification type's configured resolvers
 * against the source entity context.
 */
#[Action(
  id: 'openintranet_notifications_resolve_recipients',
  label: new TranslatableMarkup('Notification: resolve recipients'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Resolve a recipient set (a single resolver or the type resolvers) and write the uid list to a token.'),
  version_introduced: '1.0.0',
)]
final class ResolveRecipients extends ConfigurableActionBase {

  /**
   * The recipient resolver plugin manager.
   *
   * @var \Drupal\openintranet_notifications\Resolver\RecipientResolverManager
   */
  protected RecipientResolverManager $resolverManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->resolverManager = $container->get('plugin.manager.notification_recipient_resolver');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    $tokenName = trim((string) $this->configuration['token_name']);
    if ($tokenName === '') {
      return;
    }

    $context = [];
    if ($object instanceof EntityInterface) {
      $context['entity'] = $object;
      $context['source_entity'] = $object;
    }

    $definitions = $this->resolverDefinitions();
    $uids = [];
    foreach ($definitions as $definition) {
      if (!$this->resolverManager->hasDefinition($definition['id'])) {
        continue;
      }
      $resolver = $this->resolverManager->createInstance($definition['id'], $definition['configuration'] ?? []);
      foreach ($resolver->resolve($context) as $recipient) {
        if ($recipient->id !== NULL) {
          $uids[(int) $recipient->id] = (int) $recipient->id;
        }
      }
    }

    $this->tokenService->addTokenData($tokenName, array_values($uids));
  }

  /**
   * Builds the resolver definition list to run.
   *
   * @return array<int, array{id: string, configuration: array<string, mixed>}>
   *   The resolver definitions.
   */
  private function resolverDefinitions(): array {
    $resolverId = trim((string) $this->configuration['resolver_id']);
    if ($resolverId !== '') {
      $configuration = $this->configuration['configuration'] ?? [];
      return [['id' => $resolverId, 'configuration' => \is_array($configuration) ? $configuration : []]];
    }

    $typeId = trim((string) $this->tokenService->replaceClear($this->configuration['notification_type']));
    if ($typeId === '') {
      return [];
    }
    /** @var \Drupal\openintranet_notifications\Entity\NotificationTypeInterface|null $type */
    $type = $this->entityTypeManager
      ->getStorage('openintranet_notification_type')
      ->load($typeId);
    return $type === NULL ? [] : $type->getRecipientResolvers();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'resolver_id' => '',
      'configuration' => [],
      'notification_type' => '',
      'token_name' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['resolver_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Resolver id'),
      '#description' => $this->t('A single recipient resolver plugin id. Leave empty to run the type resolvers.'),
      '#default_value' => $this->configuration['resolver_id'],
    ];
    $form['notification_type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Notification type'),
      '#description' => $this->t('Used when no resolver id is given: runs the resolvers of this type.'),
      '#default_value' => $this->configuration['notification_type'],
      '#eca_token_replacement' => TRUE,
    ];
    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Output token name'),
      '#description' => $this->t('The name of the token to store the resolved uid list.'),
      '#default_value' => $this->configuration['token_name'],
      '#eca_token_reference' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['resolver_id'] = $form_state->getValue('resolver_id');
    $this->configuration['notification_type'] = $form_state->getValue('notification_type');
    $this->configuration['token_name'] = $form_state->getValue('token_name');
    parent::submitConfigurationForm($form, $form_state);
  }

}
