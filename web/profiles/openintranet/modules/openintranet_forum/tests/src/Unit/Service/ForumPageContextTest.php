<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_forum\Unit\Service;

use Drupal\block\BlockRepositoryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\openintranet_forum\Service\ForumPageContext;
use Drupal\Tests\UnitTestCase;
use Drupal\views\Entity\View;
use Drupal\views\ViewExecutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit tests for the ForumPageContext view-detection and title helpers.
 */
#[CoversClass(ForumPageContext::class)]
final class ForumPageContextTest extends UnitTestCase {

  /**
   * Builds the service with mocked dependencies and a translation stub.
   *
   * @param string $siteName
   *   The site name the mocked config factory should report.
   */
  private function pageContext(string $siteName = ''): ForumPageContext {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('name')->willReturn($siteName);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('system.site')->willReturn($config);

    $service = new ForumPageContext(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(BlockRepositoryInterface::class),
      $configFactory,
    );
    $service->setStringTranslation($this->getStringTranslationStub());

    return $service;
  }

  /**
   * Builds a mocked view with the given id, display and optional title.
   */
  private function view(string $id, string $display, string $title = ''): ViewExecutable {
    $storage = $this->createMock(View::class);
    $storage->method('id')->willReturn($id);

    $view = $this->createMock(ViewExecutable::class);
    $view->storage = $storage;
    $view->current_display = $display;
    $view->method('getTitle')->willReturn($title);

    return $view;
  }

  /**
   * Recognizes forum listing views rendered on a page display.
   */
  #[Test]
  public function isForumPageViewMatchesForumPageDisplays(): void {
    $context = $this->pageContext();
    self::assertTrue($context->isForumPageView($this->view('forum_latest_posts', 'page_1')));
    self::assertTrue($context->isForumPageView($this->view('forum_category', 'page_1')));
  }

  /**
   * Rejects non-forum views and forum views on a non-page display.
   */
  #[Test]
  public function isForumPageViewRejectsOtherViewsAndDisplays(): void {
    $context = $this->pageContext();
    self::assertFalse($context->isForumPageView($this->view('some_other_view', 'page_1')));
    self::assertFalse($context->isForumPageView($this->view('forum_latest_posts', 'block_1')));
  }

  /**
   * Maps known forum views to their fixed section titles.
   */
  #[Test]
  public function sectionTitleMapsKnownViews(): void {
    $context = $this->pageContext();
    self::assertSame(
      'Latest forum posts',
      (string) $context->getForumPageSectionTitle($this->view('forum_latest_posts', 'page_1')),
    );
    self::assertSame(
      'Most popular posts',
      (string) $context->getForumPageSectionTitle($this->view('forum_popular', 'page_1')),
    );
  }

  /**
   * Falls back to the view title for views without a fixed section title.
   */
  #[Test]
  public function sectionTitleFallsBackToViewTitle(): void {
    $context = $this->pageContext();
    self::assertSame(
      'Some Title',
      (string) $context->getForumPageSectionTitle($this->view('forum_category', 'page_1', 'Some Title')),
    );
  }

  /**
   * Builds the forum page title from the configured site name.
   */
  #[Test]
  public function forumPageTitleUsesSiteName(): void {
    self::assertSame('Acme Forum', (string) $this->pageContext('Acme')->getForumPageTitle());
  }

  /**
   * Falls back to a generic title when the site has no name.
   */
  #[Test]
  public function forumPageTitleFallsBackWhenSiteNameEmpty(): void {
    self::assertSame('Your Forum', (string) $this->pageContext('')->getForumPageTitle());
  }

  /**
   * Treats a region build with only property keys as having no blocks.
   */
  #[Test]
  public function regionHasBlocksIgnoresPropertyKeys(): void {
    self::assertFalse($this->pageContext()->regionHasBlocks(['#sorted' => TRUE, '#cache' => []]));
  }

  /**
   * Detects real block entries in a region build.
   */
  #[Test]
  public function regionHasBlocksDetectsBlockEntries(): void {
    self::assertTrue($this->pageContext()->regionHasBlocks(['#sorted' => TRUE, 'block_id' => []]));
  }

}
