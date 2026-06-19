<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\NotificationRecipientResolver;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_notifications\Attribute\NotificationRecipientResolver;
use Drupal\openintranet_notifications\Resolver\NotificationRecipientResolverBase;
use Drupal\user\UserInterface;
use Drupal\views\Entity\View;
use Drupal\views\ResultRow;

/**
 * Resolves recipients from the rows of a configured view.
 *
 * Executes view_id:display_id and, for every result row, extracts a uid (the
 * row's uid field/column, or the row entity when it is a user) and loads it.
 * A missing or invalid view yields an empty set rather than a fatal.
 */
#[NotificationRecipientResolver(
  id: 'view_result_users',
  label: new TranslatableMarkup('Users from view result'),
  description: new TranslatableMarkup('Resolves recipients from the user rows returned by a view.'),
)]
final class ViewResultUsers extends NotificationRecipientResolverBase {

  /**
   * {@inheritdoc}
   */
  public function resolve(array $context): array {
    $view_id = $this->configuration['view_id'] ?? '';
    if ($view_id === '') {
      return [];
    }
    $display_id = $this->configuration['display_id'] ?? 'default';

    $view_config = $this->entityTypeManager->getStorage('view')->load($view_id);
    if (!$view_config instanceof View) {
      return [];
    }
    $executable = $view_config->getExecutable();
    if (!$executable->setDisplay($display_id)) {
      return [];
    }
    $executable->execute($display_id);

    $uids = [];
    foreach ($executable->result as $row) {
      $uid = $this->extractUid($row);
      if ($uid !== NULL) {
        $uids[$uid] = $uid;
      }
    }
    if ($uids === []) {
      return [];
    }

    $recipients = [];
    foreach ($this->entityTypeManager->getStorage('user')->loadMultiple($uids) as $user) {
      if (!$user instanceof UserInterface) {
        continue;
      }
      $recipient = $this->buildUserRecipient($user);
      if ($recipient !== NULL) {
        $recipients[] = $recipient;
      }
    }
    return $recipients;
  }

  /**
   * Extracts a uid from a view result row.
   *
   * @param \Drupal\views\ResultRow $row
   *   The result row.
   *
   * @return int|null
   *   The uid, or NULL when the row carries no user reference.
   */
  private function extractUid(ResultRow $row): ?int {
    $entity = $row->_entity ?? NULL;
    if ($entity instanceof UserInterface) {
      return (int) $entity->id();
    }
    if (isset($row->uid) && is_numeric($row->uid)) {
      return (int) $row->uid;
    }
    return NULL;
  }

}
