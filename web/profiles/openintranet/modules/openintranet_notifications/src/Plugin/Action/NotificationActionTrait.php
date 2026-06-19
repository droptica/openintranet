<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\Action;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\eca\Plugin\DataType\DataTransferObject;

/**
 * Shared token-resolution helpers for the notification ECA actions.
 *
 * Normalizes the loosely-typed values ECA hands back (entities, typed data,
 * lists, DTOs) into the concrete types the Stage 1 services expect.
 *
 * @property \Drupal\eca\Token\TokenInterface $tokenService
 */
trait NotificationActionTrait {

  /**
   * Resolves a token string to a list of recipient user ids.
   *
   * Accepts an empty value (→ []), a single uid, an array/iterable of uids or
   * user entities, or a comma-separated string.
   *
   * @param string $token
   *   The configured recipients token expression.
   *
   * @return array<int, int>
   *   The de-duplicated recipient user ids.
   */
  protected function resolveRecipientUids(string $token): array {
    $token = trim($token);
    if ($token === '') {
      return [];
    }

    $value = $this->tokenService->getOrReplace($token);
    // A DTO wraps a non-token-typed value (e.g. a plain uid array set via
    // addTokenData); toArray() yields its flat property values.
    if ($value instanceof DataTransferObject) {
      $value = $value->toArray();
    }
    elseif ($value instanceof TypedDataInterface) {
      $value = $value->getValue();
    }

    $candidates = \is_iterable($value) ? $value : [$value];
    $uids = [];
    foreach ($candidates as $item) {
      $uid = $this->normalizeUid($item);
      if ($uid !== NULL) {
        $uids[$uid] = $uid;
      }
    }

    return array_values($uids);
  }

  /**
   * Normalizes a single recipient candidate to a uid.
   *
   * @param mixed $item
   *   A uid (int/numeric string), a user entity, or a comma-separated string.
   *
   * @return int|null
   *   The uid, or NULL when not resolvable.
   */
  private function normalizeUid(mixed $item): ?int {
    if ($item instanceof EntityInterface) {
      return (int) $item->id();
    }
    if (\is_int($item) || (\is_string($item) && ctype_digit($item))) {
      return (int) $item;
    }
    return NULL;
  }

  /**
   * Resolves a token string to a single entity.
   *
   * @param string $token
   *   The configured entity token expression.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The referenced entity, or NULL when none resolves.
   */
  protected function resolveEntity(string $token): ?EntityInterface {
    $token = trim($token);
    if ($token === '') {
      return NULL;
    }

    $value = $this->tokenService->getOrReplace($token);
    if ($value instanceof EntityReferenceFieldItemListInterface) {
      $referenced = $value->referencedEntities();
      $value = reset($referenced) ?: NULL;
    }
    if ($value instanceof TypedDataInterface) {
      $value = $value->getValue();
    }
    if (\is_array($value)) {
      $value = reset($value) ?: NULL;
    }

    return $value instanceof EntityInterface ? $value : NULL;
  }

}
