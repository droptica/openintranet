<?php

declare(strict_types=1);

namespace Drupal\oin_pf_test_channel\Plugin\PushFrameworkChannel;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\push_framework\ChannelPluginInterface;
use Drupal\push_framework\SourcePluginInterface;
use Drupal\user\UserInterface;

/**
 * Test-only Push Framework channel the push adapter delegates to.
 *
 * Its behaviour is driven by public static flags so a kernel test can make it
 * active/inactive, applicable/not, and return any RESULT_STATUS_* — no real
 * push transport, config or queue is involved.
 *
 * @ChannelPlugin(
 *   id = "oin_pf_test",
 *   title = @Translation("Test channel"),
 *   description = @Translation("Test channel for the push adapter.")
 * )
 */
final class TestChannel extends PluginBase implements ChannelPluginInterface {

  /**
   * Whether the channel reports itself active.
   */
  public static bool $active = TRUE;

  /**
   * Whether the channel is applicable to the recipient.
   */
  public static bool $applicable = TRUE;

  /**
   * The status send() returns (one of the RESULT_STATUS_* constants).
   */
  public static string $sendStatus = ChannelPluginInterface::RESULT_STATUS_SUCCESS;

  /**
   * Set when send() should throw, to prove the adapter never propagates it.
   */
  public static bool $throwOnSend = FALSE;

  /**
   * {@inheritdoc}
   */
  public function getConfigName(): string {
    return 'oin_pf_test_channel.settings';
  }

  /**
   * {@inheritdoc}
   */
  public function isActive(): bool {
    return self::$active;
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return 'Test channel';
  }

  /**
   * {@inheritdoc}
   */
  public function applicable(UserInterface $user): bool {
    return self::$applicable;
  }

  /**
   * {@inheritdoc}
   */
  public function send(UserInterface $user, ContentEntityInterface $entity, array $content, int $attempt): string {
    if (self::$throwOnSend) {
      throw new \RuntimeException('boom');
    }
    return self::$sendStatus;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareContent(UserInterface $user, ContentEntityInterface $entity, ?SourcePluginInterface $plugin = NULL, ?string $oid = NULL): array {
    return ['#markup' => 'test'];
  }

}
