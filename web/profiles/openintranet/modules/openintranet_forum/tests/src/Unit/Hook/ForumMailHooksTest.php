<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_forum\Unit\Hook;

use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\openintranet_forum\Hook\ForumMailHooks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit tests for the forum mail hooks.
 */
#[CoversClass(ForumMailHooks::class)]
final class ForumMailHooksTest extends UnitTestCase {

  /**
   * Builds the new-reply notification message.
   */
  #[Test]
  public function mailBuildsNewReplyNotification(): void {
    $url = $this->createMock(Url::class);
    $url->method('toString')
      ->willReturn('https://intranet.example/forum/post/1');

    $node = $this->createMock(NodeInterface::class);
    $node->method('label')->willReturn('Remote work policy');
    $node->method('toUrl')
      ->with('canonical', ['absolute' => TRUE])
      ->willReturn($url);

    $hook = new ForumMailHooks();
    $hook->setStringTranslation($this->getStringTranslationStub());

    $message = [
      'langcode' => 'en',
      'subject' => '',
      'body' => [],
    ];
    $hook->mail('notification', $message, [
      'event' => 'new_reply',
      'node' => $node,
    ]);

    self::assertSame('New reply to "Remote work policy"', $message['subject']);
    self::assertSame([
      'A new reply was posted to "Remote work policy".',
      'View the discussion: https://intranet.example/forum/post/1',
    ], $message['body']);
  }

  /**
   * Leaves unrelated messages unchanged.
   */
  #[Test]
  public function mailIgnoresUnrelatedMessages(): void {
    $hook = new ForumMailHooks();
    $message = [
      'langcode' => 'en',
      'subject' => 'Existing subject',
      'body' => ['Existing body'],
    ];

    $hook->mail('other', $message, []);

    self::assertSame('Existing subject', $message['subject']);
    self::assertSame(['Existing body'], $message['body']);
  }

}
