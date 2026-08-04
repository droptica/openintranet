<?php

declare(strict_types=1);

namespace Drupal\openintranet_documents\Plugin\DocumentSource;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\openintranet_documents\OiDocumentInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for document source plugins.
 */
abstract class DocumentSourceBase extends PluginBase implements DocumentSourceInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return (string) $this->pluginDefinition['label'];
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
  public function getIcon(): string {
    return $this->pluginDefinition['icon'] ?? 'bi-file-earmark-plus';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getFileIcon(OiDocumentInterface $document): string {
    return $this->getIcon();
  }

  /**
   * {@inheritdoc}
   */
  public function validateSourceForm(array &$form, FormStateInterface $form_state): void {
    // Default implementation does nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function submitSourceForm(array &$form, FormStateInterface $form_state, OiDocumentInterface $document): void {
    // Default implementation does nothing.
  }

}
