<?php

declare(strict_types=1);

namespace Drupal\openintranet_forum\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\flag\FlagServiceInterface;

/**
 * Service for forum notifications.
 */
final class ForumNotifications implements ForumNotificationsInterface {

  /**
   * Constructs a ForumNotifications object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   The mail manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger channel factory.
   * @param \Drupal\flag\FlagServiceInterface $flagService
   *   The flag service.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly MailManagerInterface $mailManager,
    private readonly AccountProxyInterface $currentUser,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
    private readonly FlagServiceInterface $flagService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function notifyFollowers(int $nid, string $event): void {
    $config = $this->configFactory->get('openintranet_forum.settings');
    if (!$config->get('notify_on_reply')) {
      return;
    }

    if (!$this->moduleHandler->moduleExists('flag')) {
      return;
    }

    $flag = $this->flagService->getFlagById('follow_forum_post');
    if (!$flag) {
      return;
    }

    try {
      $node = $this->entityTypeManager->getStorage('node')->load($nid);
      if (!$node) {
        return;
      }

      $flaggings = $this->flagService->getEntityFlaggings($flag, $node);
      foreach ($flaggings as $flagging) {
        $user = $flagging->getOwner();

        if ($user->id() == $this->currentUser->id()) {
          continue;
        }

        $this->sendNotificationEmail($user, $node, $event);
      }
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('openintranet_forum')->error('Failed to notify followers: @message', [
        '@message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Sends a notification email to a user.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user to notify.
   * @param \Drupal\node\NodeInterface $node
   *   The forum post node.
   * @param string $event
   *   The event type.
   * @param \Drupal\comment\CommentInterface|null $comment
   *   Optional comment entity.
   */
  private function sendNotificationEmail($user, $node, string $event, $comment = NULL): void {
    $params = [
      'user' => $user,
      'node' => $node,
      'event' => $event,
      'comment' => $comment,
    ];

    $this->mailManager->mail(
      'openintranet_forum',
      'notification',
      $user->getEmail(),
      $user->getPreferredLangcode(),
      $params
    );
  }

}
