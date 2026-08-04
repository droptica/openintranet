<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\AutowiredInstanceTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\openintranet_forum\Service\ForumPostRendererInterface;
use Drupal\openintranet_forum\Service\ForumStatisticsInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the "More from category" forum sidebar block.
 *
 * Renders up to RELATED_LIMIT other posts from the current forum_post's
 * primary category. Driven by an entity context on the current node, with a
 * Bundle constraint so Drupal only offers the block on forum_post routes.
 */
#[Block(
  id: 'openintranet_forum_more_from_category_block',
  admin_label: new TranslatableMarkup('Forum: more from category'),
  category: new TranslatableMarkup('Open Intranet Forum'),
  context_definitions: [
    'node' => new EntityContextDefinition(
      data_type: 'entity:node',
      label: new TranslatableMarkup('Current forum post'),
      required: TRUE,
      constraints: ['Bundle' => ['forum_post']],
    ),
  ],
)]
final class ForumMoreFromCategoryBlock extends BlockBase implements ContainerFactoryPluginInterface {

  use AutowiredInstanceTrait;

  private const RELATED_LIMIT = 3;

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly ModuleExtensionList $moduleExtensionList,
    private readonly ForumStatisticsInterface $statistics,
    private readonly ForumPostRendererInterface $postRenderer,
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
    $node = $this->getContextValue('node');
    if (!$node instanceof NodeInterface) {
      return [];
    }
    if (!$node->hasField('field_forum_category') || $node->get('field_forum_category')->isEmpty()) {
      return [];
    }

    $category_item = $node->get('field_forum_category')->first();
    if ($category_item === NULL) {
      return [];
    }

    /** @var \Drupal\taxonomy\TermInterface|null $term */
    $term = $category_item->get('entity')->getValue();
    if (!$term instanceof TermInterface) {
      return [];
    }

    $node_storage = $this->entityTypeManager->getStorage('node');
    $nids = $node_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'forum_post')
      ->condition('status', 1)
      ->condition('field_forum_category', $term->id())
      ->condition('nid', (int) $node->id(), '<>')
      ->sort('created', 'DESC')
      ->range(0, self::RELATED_LIMIT)
      ->execute();

    if ($nids === []) {
      return [];
    }

    $nid_ints = array_map('intval', $nids);
    $nodes = $node_storage->loadMultiple($nid_ints);
    $vote_totals = $this->statistics->loadVoteSumTotals($nid_ints);

    $items = [];
    foreach ($nodes as $related_node) {
      $owner = $related_node->getOwner();
      $author_url = $this->postRenderer->buildAuthorProfileUrl($owner);
      $picture = $this->postRenderer->buildUserPictureRenderArray($owner);
      $created = (int) $related_node->getCreatedTime();

      $items[] = [
        'url' => $related_node->toUrl()->toString(),
        'title' => $related_node->getTitle(),
        'has_author_picture' => $picture !== [],
        'author_picture' => $picture,
        'author_name' => $owner?->getDisplayName() ?? '',
        'author_profile_url' => $author_url,
        'date' => $this->postRenderer->formatTrendingCardDate($created),
        'datetime_attr' => $this->dateFormatter->format($created, 'html_datetime'),
        'like_count' => $vote_totals[(int) $related_node->id()] ?? 0,
        'reply_count' => (int) ($related_node->get('field_forum_reply_count')->value ?? 0),
        'share_count' => 0,
      ];
    }

    $module_path = $this->moduleExtensionList->getPath('openintranet_forum');

    return [
      '#type' => 'component',
      '#component' => 'openintranet_forum:more-from-category',
      '#props' => [
        'category_name' => $term->getName(),
        'category_url' => base_path() . 'forum/category/' . $term->id(),
        'items' => $items,
        'icon_base' => base_path() . $module_path . '/images/icons',
        'more_url' => base_path() . 'forum/category/' . $term->id(),
        'empty_message' => $this->t('No related posts found.'),
      ],
      '#cache' => [
        'tags' => array_merge(
          ['node_list:forum_post', 'taxonomy_term:' . $term->id()],
          $node->getCacheTags(),
        ),
        'contexts' => ['route'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return array_merge(parent::getCacheContexts(), ['route']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    return 0;
  }

}
