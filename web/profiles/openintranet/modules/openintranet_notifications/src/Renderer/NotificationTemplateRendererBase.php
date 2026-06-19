<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Renderer;

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
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    protected readonly Token $token,
    protected readonly TwigEnvironment $twig,
    protected readonly RendererInterface $renderer,
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
    );
  }

}
