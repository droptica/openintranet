<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;

/**
 * Mail hook implementations for the forum module.
 */
final class ForumMailHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_mail().
   */
  #[Hook('mail')]
  public function mail(string $key, array &$message, array $params): void {
    if ($key !== 'notification' || ($params['event'] ?? NULL) !== 'new_reply') {
      return;
    }

    $node = $params['node'] ?? NULL;
    if (!$node instanceof NodeInterface) {
      return;
    }

    $options = ['langcode' => $message['langcode']];
    $replacements = [
      '@title' => $node->label(),
      '@url' => $node->toUrl('canonical', ['absolute' => TRUE])->toString(),
    ];

    $message['subject'] = (string) $this->t('New reply to "@title"', $replacements, $options);
    $message['body'][] = (string) $this->t('A new reply was posted to "@title".', $replacements, $options);
    $message['body'][] = (string) $this->t('View the discussion: @url', $replacements, $options);
  }

}
