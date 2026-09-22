<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Open Intranet profile post-update hooks.
 */
#[Group('openintranet')]
final class OpenIntranetPostUpdateTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    require_once dirname(__DIR__, 3) . '/openintranet.post_update.php';
  }

  /**
   * Tests that the post-update removes only the legacy Dashboard alias.
   */
  public function testLegacyDashboardAliasIsRemoved(): void {
    $alias = $this->createMock(EntityInterface::class);
    $query = $this->createMock(QueryInterface::class);
    $conditions = [];

    $query->expects(self::once())
      ->method('accessCheck')
      ->with(FALSE)
      ->willReturnSelf();
    $query->expects(self::exactly(2))
      ->method('condition')
      ->willReturnCallback(
        function (string $field, mixed $value) use (&$conditions, $query): QueryInterface {
          $conditions[$field] = $value;
          return $query;
        },
      );
    $query->expects(self::once())
      ->method('execute')
      ->willReturn([99]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::once())
      ->method('getQuery')
      ->willReturn($query);
    $storage->expects(self::once())
      ->method('loadMultiple')
      ->with([99])
      ->willReturn([99 => $alias]);
    $storage->expects(self::once())
      ->method('delete')
      ->with([99 => $alias]);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects(self::once())
      ->method('hasDefinition')
      ->with('path_alias')
      ->willReturn(TRUE);
    $entity_type_manager->expects(self::once())
      ->method('getStorage')
      ->with('path_alias')
      ->willReturn($storage);

    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entity_type_manager);
    \Drupal::setContainer($container);

    openintranet_post_update_remove_dashboard_admin_alias();

    self::assertSame([
      'path' => '/admin/dashboard',
      'alias' => '/admin/openintranet',
    ], $conditions);
  }

}
