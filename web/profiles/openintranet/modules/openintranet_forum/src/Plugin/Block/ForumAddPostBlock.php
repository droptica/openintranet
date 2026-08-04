<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Plugin\Block;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\DependencyInjection\AutowiredInstanceTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the forum add-post call to action block.
 */
#[Block(
  id: 'openintranet_forum_add_post_block',
  admin_label: new TranslatableMarkup('Forum add post'),
  category: new TranslatableMarkup('Open Intranet Forum'),
)]
final class ForumAddPostBlock extends BlockBase implements ContainerFactoryPluginInterface {

  use AutowiredInstanceTrait;

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
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
    return [
      '#type' => 'component',
      '#component' => 'openintranet_forum:add-post-button',
      '#props' => [
        'url' => Url::fromRoute('node.add', ['node_type' => 'forum_post'])->toString(),
        'label' => $this->t('add new post'),
      ],
      '#attached' => [
        'library' => ['openintranet_forum/forum.add-post'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResultInterface {
    return $this->entityTypeManager
      ->getAccessControlHandler('node')
      ->createAccess('forum_post', $account, [], TRUE)
      ->addCacheContexts(['user.permissions']);
  }

}
