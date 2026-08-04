<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Service;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Builds reusable render data for forum post templates and cards.
 *
 * All methods are pure with respect to the passed-in entities; they do not
 * mutate nodes or users. preparePostTemplateVariables() is the only method
 * that writes into a template variables array by reference.
 */
interface ForumPostRendererInterface {

  /**
   * Populates reusable post variables for forum post templates.
   *
   * @param array &$variables
   *   The Twig variables array to enrich. Merged by reference.
   * @param \Drupal\node\NodeInterface $node
   *   The forum post node being rendered.
   * @param string $viewMode
   *   The active view mode (e.g. full, compact, trending_card).
   */
  public function preparePostTemplateVariables(array &$variables, NodeInterface $node, string $viewMode): void;

  /**
   * Returns a plain-text excerpt for compact and teaser cards.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The forum post node.
   * @param int $length
   *   The maximum excerpt length in characters.
   *
   * @return string
   *   The truncated, tag-stripped body summary (empty when unavailable).
   */
  public function buildPostExcerpt(NodeInterface $node, int $length): string;

  /**
   * Builds forum term link metadata for category and tag pills.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The forum post node.
   * @param string $fieldName
   *   The entity reference field to read (e.g. field_forum_category).
   * @param string $pathPrefix
   *   The forum path prefix (e.g. 'category', 'tag').
   *
   * @return array
   *   A list of ['title' => string, 'url' => string] entries.
   */
  public function buildTermLinks(NodeInterface $node, string $fieldName, string $pathPrefix): array;

  /**
   * Builds bookmark flag link render array when the optional flag exists.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The forum post node.
   *
   * @return array
   *   A flag link render array, or [] when flag module or the flag is absent.
   */
  public function buildBookmarkLink(NodeInterface $node): array;

  /**
   * Builds the canonical profile URL for a user, or a fallback.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The account, or NULL.
   * @param string|null $fallback
   *   URL to return when there is no user/uid. NULL uses the site front page;
   *   pass '' for an empty string.
   *
   * @return string
   *   The profile URL or the resolved fallback.
   */
  public function buildAuthorProfileUrl(?AccountInterface $account, ?string $fallback = NULL): string;

  /**
   * Builds the picture render array for a user.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account.
   *
   * @return array
   *   A render array, or [] when the user has no picture.
   */
  public function buildUserPictureRenderArray(UserInterface $account): array;

  /**
   * Formats a creation timestamp as a relative or short-date label.
   *
   * @param int $createdTimestamp
   *   The Unix timestamp to format.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup
   *   Either a translated string for same/previous-day values, or a plain
   *   short-date string (m/d/Y) for older dates.
   */
  public function formatTrendingCardDate(int $createdTimestamp): string|TranslatableMarkup;

}
