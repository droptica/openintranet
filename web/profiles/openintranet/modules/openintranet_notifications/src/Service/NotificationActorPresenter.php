<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\file\FileInterface;
use Drupal\image\ImageStyleInterface;
use Drupal\openintranet_notifications\Entity\NotificationInterface;
use Drupal\user\UserInterface;

/**
 * Resolves a notification's actor display: name + avatar URL.
 *
 * Shared by the bell block and the inbox so both render the same actor info.
 */
final class NotificationActorPresenter {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
  ) {}

  /**
   * The actor's display name and avatar URL, or NULLs when there is no actor.
   *
   * @param \Drupal\openintranet_notifications\Entity\NotificationInterface $notification
   *   The notification whose actor to present.
   *
   * @return array{name: string|null, avatar: string|null}
   *   The actor display name and avatar URL (both NULL when unresolved).
   */
  public function present(NotificationInterface $notification): array {
    $uid = $notification->get('actor_uid')->target_id;
    if ($uid === NULL) {
      return ['name' => NULL, 'avatar' => NULL];
    }

    $actor = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!$actor instanceof UserInterface) {
      return ['name' => NULL, 'avatar' => NULL];
    }

    return [
      'name' => $actor->getDisplayName(),
      'avatar' => $this->avatarUrl($actor),
    ];
  }

  /**
   * The actor's avatar URL (thumbnail image style), or NULL when none is set.
   */
  private function avatarUrl(UserInterface $actor): ?string {
    if (!$actor->hasField('user_picture') || $actor->get('user_picture')->isEmpty()) {
      return NULL;
    }
    $file = $actor->get('user_picture')->entity;
    if (!$file instanceof FileInterface) {
      return NULL;
    }
    $style = $this->entityTypeManager->getStorage('image_style')->load('thumbnail');
    return $style instanceof ImageStyleInterface
      ? $style->buildUrl($file->getFileUri())
      : $this->fileUrlGenerator->generateString($file->getFileUri());
  }

}
