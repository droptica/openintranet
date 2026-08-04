<?php

declare(strict_types=1);

namespace Drupal\Tests\openintranet_forum\Unit\Routing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests security requirements on mutating forum routes.
 */
final class ForumRouteSecurityTest extends TestCase {

  /**
   * Provides secured route expectations.
   *
   * @return array<string, array{string, string, string}>
   *   Route name, entity access requirement, and parameter converter.
   */
  public static function securedRoutes(): array {
    return [
      'comment vote' => [
        'openintranet_forum.comment_vote',
        'comment.view',
        'comment',
      ],
      'post share' => [
        'openintranet_forum.post_share',
        'node.view',
        'node',
      ],
    ];
  }

  /**
   * Mutating routes require login, CSRF, entity access, and conversion.
   */
  #[Test]
  #[DataProvider('securedRoutes')]
  public function mutatingRoutesEnforceEntityAccess(
    string $routeName,
    string $entityAccess,
    string $parameter,
  ): void {
    $routes = Yaml::parseFile(
      DRUPAL_ROOT . '/profiles/openintranet/modules/openintranet_forum/openintranet_forum.routing.yml',
    );
    $route = $routes[$routeName];

    self::assertSame('TRUE', $route['requirements']['_user_is_logged_in']);
    self::assertSame('TRUE', $route['requirements']['_csrf_request_header_token']);
    self::assertSame($entityAccess, $route['requirements']['_entity_access']);
    self::assertSame(
      'entity:' . $parameter,
      $route['options']['parameters'][$parameter]['type'],
    );
    self::assertSame(['POST'], $route['methods']);
  }

}
