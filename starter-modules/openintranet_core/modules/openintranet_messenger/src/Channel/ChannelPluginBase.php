<?php

declare(strict_types=1);

namespace Drupal\openintranet_messenger\Channel;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\openintranet_messenger\Recipient\RecipientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for notification channel plugins.
 */
abstract class ChannelPluginBase extends PluginBase implements ChannelPluginInterface, ContainerFactoryPluginInterface {

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Constructs a ChannelPluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    LoggerChannelInterface $logger,
    ConfigFactoryInterface $config_factory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = $logger;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.channel.openintranet_messenger'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getId(): string {
    return $this->pluginId;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return (string) ($this->pluginDefinition['label'] ?? $this->pluginId);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return (string) ($this->pluginDefinition['description'] ?? '');
  }

  /**
   * {@inheritdoc}
   */
  public function canSendTo(RecipientInterface $recipient): bool {
    return $recipient->canReceiveVia($this->pluginId);
  }

  /**
   * {@inheritdoc}
   */
  public function getRecipientAddress(RecipientInterface $recipient): ?string {
    return $recipient->getChannelAddress($this->pluginId);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    // Default implementation does nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    // Default implementation does nothing.
  }

  /**
   * Logs an info message.
   *
   * @param string $message
   *   The message.
   * @param array $context
   *   The context.
   */
  protected function logInfo(string $message, array $context = []): void {
    $this->logger->info($message, $context);
  }

  /**
   * Logs an error message.
   *
   * @param string $message
   *   The message.
   * @param array $context
   *   The context.
   */
  protected function logError(string $message, array $context = []): void {
    $this->logger->error($message, $context);
  }

}
