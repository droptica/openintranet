# Open Intranet Notifications: Push Framework

Adds a `push` notification channel that delivers through the
[Push Framework](https://www.drupal.org/project/push_framework) contrib module.

This is the **conditional / optional** Push Framework integration described in
00-synteza §2 and §11: it is shipped as a submodule so the core notifications
module never hard-depends on Push Framework, and the channel reports itself
unavailable rather than erroring when Push Framework's model does not fit.

## Impedance mismatch

Our notification contract and Push Framework speak different languages:

- **Our contract** (`NotificationChannelInterface::send(NotificationRecipient,
  NotificationMessage): DeliveryResult`) is a per-call "send this rendered
  message to this recipient now" service.
- **Push Framework is an orchestrator**, not a per-message transport. It owns its
  own channel plugins (`Plugin/PushFrameworkChannel`), its own queue
  (advancedqueue) and its own source plugins, and its sending entry point is
  `ChannelPluginInterface::send(UserInterface $user, ContentEntityInterface
  $entity, array $content, int $attempt): string` returning
  `RESULT_STATUS_{SUCCESS,RETRY,FAILED}`. There is no
  "send-this-message-to-this-user-now" service to call.

The thinnest honest adapter bridges the two by **delegating to Push Framework's
registered channel plugins for the recipient's user account**:

1. The recipient MUST be a Drupal user — push targets a user, so non-user
   recipients are unaddressable (`getRecipientAddress()` returns `NULL`).
2. `send()` iterates `push_framework.channel.plugin.manager->getDefinitions()`,
   instantiates each plugin, skips it unless `isActive()` and
   `applicable($user)`, then calls the plugin's `send($user, $entity, $content,
   0)` and collects the returned statuses.
3. Status mapping back to a `DeliveryResult`:
   - any `RESULT_STATUS_SUCCESS` -> `success()`
   - else any `RESULT_STATUS_RETRY` -> `retryableFailure('PUSH_RETRY', …)`
   - else any `RESULT_STATUS_FAILED` -> `permanentFailure('PUSH_FAILED', …)`
   - no applicable active channel -> `permanentFailure('NO_PUSH_CHANNEL', …)`
   - any thrown error is caught -> `retryableFailure('PUSH_EXCEPTION', …)`

## Known limitation — the notification entity

Push Framework's `ChannelPluginInterface::send()` requires a
`ContentEntityInterface $entity` (the thing the notification is *about*), and it
calls the plugin's own `prepareContent()` / view-builder pipeline against it.
Our `NotificationChannelInterface::send(recipient, message)` does **not** carry
the source notification entity — by design it passes only the already-rendered
`NotificationMessage`.

For Stage 3 the adapter does **not** hack the frozen core contract to smuggle an
entity through. Instead it degrades safely: when no `ContentEntityInterface` is
available it returns `permanentFailure('PUSH_NO_ENTITY', …)` so the worker does
not retry a call that can never succeed. The adapter accepts an entity only when
the message payload carries one under `$message->payload['entity']` (a
best-effort hook for a future worker that has the notification entity in hand).

`@todo` A future contract revision should pass the source notification entity to
`NotificationChannelInterface::send()` so this channel can drive Push Framework's
`prepareContent()` pipeline directly instead of relying on a payload smuggle.
