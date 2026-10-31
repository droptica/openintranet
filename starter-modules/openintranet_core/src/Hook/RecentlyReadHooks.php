<?php

declare(strict_types=1);

namespace Drupal\openintranet_core\Hook;

use Drupal\Core\Extension\ProceduralCall;
use Drupal\Core\Hook\Attribute\RemoveHook;

/**
 * Replaces the recently_read module's render-time tracking.
 *
 * The recently_read module records a read in hook_entity_view() and, to make
 * sure the hook runs on every view, marks the entity render array
 * uncacheable. That max-age 0 bubbled up and disabled the Dynamic Page Cache
 * on every content page. Reads are recorded per request instead.
 *
 * @see \Drupal\openintranet_core\EventSubscriber\RecentlyReadSubscriber
 */
#[RemoveHook('entity_view', ProceduralCall::class, 'recently_read_entity_view')]
final class RecentlyReadHooks {

}
