<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\NotificationRecipientResolver;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_notifications\Attribute\NotificationRecipientResolver;
use Drupal\openintranet_notifications\Resolver\NotificationRecipientResolverBase;

/**
 * Resolves recipients from explicit uids passed in the dispatch context.
 *
 * Reads $context['recipients'] (an array of uids or a single uid) and loads
 * each user. Missing and blocked users are skipped.
 */
#[NotificationRecipientResolver(
  id: 'explicit_users_from_token',
  label: new TranslatableMarkup('Explicit users from token'),
  description: new TranslatableMarkup('Resolves recipients from a list of user ids passed in the dispatch context.'),
)]
final class ExplicitUsersFromToken extends NotificationRecipientResolverBase {

  /**
   * {@inheritdoc}
   */
  public function resolve(array $context): array {
    $raw = $context['recipients'] ?? [];
    $uids = is_array($raw) ? $raw : [$raw];
    $uids = array_filter(array_map('intval', $uids));

    return $this->buildUserRecipientsFromUids($uids);
  }

}
