<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Plugin\Field\FieldFormatter;

use Drupal\Core\DependencyInjection\AutowiredInstanceTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Forum gallery / comment image-list formatter.
 *
 * Renders a multi-value image field as either:
 * - a hero + thumbnails grid (used on forum_post full view), or
 * - a uniform thumbnail grid with an optional "+N more" overlay (used on
 *   forum_reply comments).
 *
 * Emits the inline JSON URL payload consumed by the existing
 * openintranet_forum/forum.gallery JS lightbox (read from a
 * .js-gallery-data element inside the .js-forum-gallery container).
 */
#[FieldFormatter(
  id: 'openintranet_forum_gallery',
  label: new TranslatableMarkup('Forum gallery (hero + thumbnails + lightbox)'),
  field_types: ['image'],
)]
final class ForumGalleryFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  use AutowiredInstanceTrait;

  /**
   * Hero + thumbnails layout (forum_post gallery).
   */
  public const LAYOUT_HERO = 'hero';

  /**
   * Uniform grid layout with optional "+N more" overlay (forum_reply images).
   */
  public const LAYOUT_GRID = 'grid';

  public function __construct(
    string $plugin_id,
    mixed $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    string $label,
    string $view_mode,
    array $third_party_settings,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return static::createInstanceAutowired(
      $container,
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'hero_image_style' => 'forum_gallery_hero',
      'thumb_image_style' => 'forum_gallery_thumb',
      'layout' => self::LAYOUT_HERO,
      'grid_visible_max' => 3,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element = parent::settingsForm($form, $form_state);
    $image_styles = image_style_options(FALSE);

    $element['layout'] = [
      '#title' => $this->t('Layout'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('layout'),
      '#options' => [
        self::LAYOUT_HERO => $this->t('Hero + thumbnails (first image big)'),
        self::LAYOUT_GRID => $this->t('Uniform grid with "+N more" overlay'),
      ],
    ];
    $element['hero_image_style'] = [
      '#title' => $this->t('Hero image style'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('hero_image_style'),
      '#empty_option' => $this->t('None (original image)'),
      '#options' => $image_styles,
      '#description' => $this->t('Used for the first image in the hero layout. Ignored in uniform grid layout.'),
    ];
    $element['thumb_image_style'] = [
      '#title' => $this->t('Thumbnail image style'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('thumb_image_style'),
      '#empty_option' => $this->t('None (original image)'),
      '#options' => $image_styles,
    ];
    $element['grid_visible_max'] = [
      '#title' => $this->t('Visible thumbnails in grid layout'),
      '#type' => 'number',
      '#min' => 1,
      '#default_value' => $this->getSetting('grid_visible_max'),
      '#description' => $this->t('Remaining items collapse into a "+N more" overlay on the last visible tile.'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];
    $image_styles = image_style_options(FALSE);
    unset($image_styles['']);

    $layout = $this->getSetting('layout');
    $summary[] = $layout === self::LAYOUT_HERO
      ? $this->t('Layout: hero + thumbnails')
      : $this->t('Layout: uniform grid (+N more)');

    if ($layout === self::LAYOUT_HERO) {
      $hero = $this->getSetting('hero_image_style');
      $summary[] = isset($image_styles[$hero])
        ? $this->t('Hero style: @style', ['@style' => $image_styles[$hero]])
        : $this->t('Hero style: original image');
    }

    $thumb = $this->getSetting('thumb_image_style');
    $summary[] = isset($image_styles[$thumb])
      ? $this->t('Thumb style: @style', ['@style' => $image_styles[$thumb]])
      : $this->t('Thumb style: original image');

    if ($layout === self::LAYOUT_GRID) {
      $summary[] = $this->t('Visible tiles: @n', ['@n' => (int) $this->getSetting('grid_visible_max')]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    if ($items->isEmpty()) {
      return [];
    }

    $hero_style_id = $this->resolveStyleId((string) $this->getSetting('hero_image_style'));
    $thumb_style_id = $this->resolveStyleId((string) $this->getSetting('thumb_image_style'));
    $layout = (string) $this->getSetting('layout');

    $image_render_items = [];
    $urls = [];
    foreach ($items as $delta => $item) {
      /** @var \Drupal\image\Plugin\Field\FieldType\ImageItem $item */
      if ($item->isEmpty() || $item->entity === NULL) {
        continue;
      }
      $uri = (string) $item->entity->getFileUri();
      $alt = (string) ($item->alt ?? '');

      $is_hero = $layout === self::LAYOUT_HERO && $delta === 0;
      $image_render_items[] = [
        '#theme' => 'image_style',
        '#style_name' => $is_hero ? $hero_style_id : $thumb_style_id,
        '#uri' => $uri,
        '#alt' => $alt,
        '#width' => $is_hero ? 1200 : 300,
        '#height' => $is_hero ? 463 : 135,
      ];
      $urls[] = $this->fileUrlGenerator->generateAbsoluteString($uri);
    }

    if ($image_render_items === []) {
      return [];
    }

    $element = [
      '#type' => 'component',
      '#component' => 'openintranet_forum:gallery',
      '#props' => [
        'layout' => $layout,
        'items' => $image_render_items,
        'urls' => $urls,
        'grid_visible_max' => max(1, (int) $this->getSetting('grid_visible_max')),
      ],
      '#attached' => [
        'library' => ['openintranet_forum/forum.gallery'],
      ],
      '#cache' => [
        'tags' => $items->getEntity()->getCacheTags(),
      ],
    ];

    return [$element];
  }

  /**
   * Resolves an image style id, falling back to 'large' when missing.
   */
  private function resolveStyleId(string $style_id): string {
    if ($style_id === '') {
      return '';
    }
    $storage = $this->entityTypeManager->getStorage('image_style');
    if ($storage->load($style_id) === NULL) {
      return $storage->load('large') !== NULL ? 'large' : '';
    }
    return $style_id;
  }

}
