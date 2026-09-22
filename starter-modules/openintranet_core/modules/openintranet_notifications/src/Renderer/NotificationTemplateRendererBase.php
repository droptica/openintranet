<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Renderer;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Template\TwigEnvironment;
use Drupal\Core\Utility\Token;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for notification template renderer plugins.
 *
 * Concrete renderers turn a notification type's templates and the token data
 * into a channel-agnostic NotificationMessage (00-synteza §8). The notification
 * is rendered once and stored; channels read the resulting DTO at send time.
 *
 * @phpstan-consistent-constructor
 *   All renderers inherit this constructor unchanged, so new static() in
 *   create() is safe.
 */
abstract class NotificationTemplateRendererBase extends PluginBase implements NotificationTemplateRendererInterface, ContainerFactoryPluginInterface {

  /**
   * Constructs a NotificationTemplateRendererBase.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Utility\Token $token
   *   The token replacement service.
   * @param \Drupal\Core\Template\TwigEnvironment $twig
   *   The Twig environment.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    protected readonly Token $token,
    protected readonly TwigEnvironment $twig,
    protected readonly RendererInterface $renderer,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('token'),
      $container->get('twig'),
      $container->get('renderer'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Replaces tokens in a template, honouring the recipient langcode (§9).
   *
   * The factory threads the recipient's preferred langcode under the reserved
   * `langcode` token-data key; it is passed to Token::replace so locale- and
   * translation-sensitive tokens (e.g. [node:title] on a translated entity)
   * resolve in the recipient's language. Absent the key, Token falls back to
   * the current language, preserving the prior behaviour.
   *
   * @param string $template
   *   The template carrying tokens.
   * @param array $tokenData
   *   The token replacement data; the reserved `langcode` entry is consumed as
   *   the replacement language rather than passed as token data.
   *
   * @return string
   *   The template with tokens replaced and unresolved tokens cleared.
   */
  protected function replace(string $template, array $tokenData): string {
    $options = ['clear' => TRUE];
    if (!empty($tokenData['langcode']) && is_string($tokenData['langcode'])) {
      $options['langcode'] = $tokenData['langcode'];
    }
    unset($tokenData['langcode']);
    return (string) $this->token->replace($template, $tokenData, $options);
  }

}
