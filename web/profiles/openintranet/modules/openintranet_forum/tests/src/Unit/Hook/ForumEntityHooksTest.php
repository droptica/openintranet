<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_forum\Unit\Hook;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\openintranet_forum\Hook\ForumEntityHooks;
use Drupal\openintranet_forum\Service\ForumNotificationsInterface;
use Drupal\openintranet_forum\Service\ForumStatisticsInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Unit tests for forum entity and form hooks.
 */
#[CoversClass(ForumEntityHooks::class)]
final class ForumEntityHooksTest extends UnitTestCase {

  /**
   * Hides revision controls from regular forum authors.
   */
  #[Test]
  public function forumPostFormHidesRevisionInformationForRegularUser(): void {
    $hooks = $this->createHooks(FALSE);
    $form = [
      '#form_id' => 'node_forum_post_form',
      'revision_information' => [
        '#type' => 'details',
      ],
      'actions' => [
        'submit' => [
          '#type' => 'submit',
          '#value' => 'Save',
        ],
        'preview' => [
          '#type' => 'submit',
        ],
      ],
    ];

    $hooks->formNodeForumPostFormAlter($form);

    self::assertFalse($form['revision_information']['#access']);
    self::assertContains('forum-post-form', $form['#attributes']['class']);
    self::assertContains(
      'forum-post-form--single-column',
      $form['#attributes']['class'],
    );
    self::assertContains(
      'btn-outline-secondary',
      $form['actions']['preview']['#attributes']['class'],
    );
    self::assertSame(
      'Create a forum post',
      (string) $form['forum_post_form_header']['title']['#value'],
    );
    self::assertSame(
      'Share a question, idea, or update with your colleagues.',
      (string) $form['forum_post_form_header']['description']['#value'],
    );
    self::assertSame('Publish post', (string) $form['actions']['submit']['#value']);
    self::assertContains(
      'openintranet_forum/forum.post_form',
      $form['#attached']['library'],
    );
  }

  /**
   * Keeps revision controls available to content administrators.
   */
  #[Test]
  public function forumPostFormKeepsRevisionInformationForAdministrator(): void {
    $hooks = $this->createHooks(TRUE);
    $form = [
      'revision_information' => [
        '#type' => 'details',
      ],
    ];

    $hooks->formNodeForumPostFormAlter($form);

    self::assertArrayNotHasKey('#access', $form['revision_information']);
    self::assertNotContains(
      'forum-post-form--single-column',
      $form['#attributes']['class'],
    );
  }

  /**
   * Creates the hooks service with the requested content administration access.
   */
  private function createHooks(bool $canAdministerContent): ForumEntityHooks {
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('hasPermission')
      ->with('administer nodes')
      ->willReturn($canAdministerContent);

    $hooks = new ForumEntityHooks(
      $this->createMock(ForumStatisticsInterface::class),
      $this->createMock(ForumNotificationsInterface::class),
      $this->createMock(ModuleHandlerInterface::class),
      $currentUser,
      $this->createMock(RequestStack::class),
    );
    $hooks->setStringTranslation($this->getStringTranslationStub());

    return $hooks;
  }

}
