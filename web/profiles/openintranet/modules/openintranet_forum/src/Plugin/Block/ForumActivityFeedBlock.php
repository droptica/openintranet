<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\DependencyInjection\AutowiredInstanceTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_forum\Service\ForumActivityFeedInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the personalized forum activity feed block.
 */
#[Block(
  id: 'openintranet_forum_activity_feed_block',
  admin_label: new TranslatableMarkup('Forum activity feed'),
  category: new TranslatableMarkup('Open Intranet Forum'),
)]
final class ForumActivityFeedBlock extends BlockBase implements ContainerFactoryPluginInterface {

  use AutowiredInstanceTrait;

  /**
   * Constructs a ForumActivityFeedBlock object.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly ForumActivityFeedInterface $activityFeed,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return static::createInstanceAutowired($container, $configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return $this->activityFeed->buildCurrentUserFeed(5, 'sidebar', TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResult {
    return AccessResult::allowedIf($account->isAuthenticated())
      ->addCacheContexts(['user']);
  }

}
