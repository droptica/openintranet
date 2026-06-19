<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\NotificationRecipientResolver;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_notifications\Attribute\NotificationRecipientResolver;
use Drupal\openintranet_notifications\Resolver\NotificationRecipientResolverBase;
use Drupal\user\UserInterface;

/**
 * Resolves all active users holding a configured role.
 *
 * Queries active users (status = 1) having the configured role and returns
 * them as recipients. Blocked users never match the query; buildUserRecipient()
 * is the additional safety net (00-synteza §9).
 */
#[NotificationRecipientResolver(
  id: 'role_users',
  label: new TranslatableMarkup('Users with role'),
  description: new TranslatableMarkup('Resolves all active users that hold a given role.'),
)]
final class RoleUsers extends NotificationRecipientResolverBase {

  /**
   * {@inheritdoc}
   */
  public function resolve(array $context): array {
    $role = $this->configuration['role'] ?? '';
    if ($role === '') {
      return [];
    }

    $storage = $this->entityTypeManager->getStorage('user');
    $uids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->condition('roles', $role)
      ->execute();
    if ($uids === []) {
      return [];
    }

    $recipients = [];
    foreach ($storage->loadMultiple($uids) as $user) {
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

}
