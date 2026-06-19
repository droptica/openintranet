<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\NotificationTemplateRenderer;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_notifications\Attribute\NotificationTemplateRenderer;
use Drupal\openintranet_notifications\Dto\NotificationMessage;
use Drupal\openintranet_notifications\Entity\NotificationTypeInterface;
use Drupal\openintranet_notifications\Renderer\NotificationTemplateRendererBase;

/**
 * Renders a content entity through its view builder into the message body.
 *
 * Renders $tokenData['entity'] in the configured view_mode (default 'full') to
 * markup for the body; the subject is token-replaced from the subject template
 * (00-synteza §8). Useful for rich content channels (e.g. HTML email).
 */
#[NotificationTemplateRenderer(
  id: 'render_array',
  label: new TranslatableMarkup('Render array'),
  description: new TranslatableMarkup('Renders a content entity through its view builder into the message body.'),
)]
final class RenderArray extends NotificationTemplateRendererBase {

  /**
   * {@inheritdoc}
   */
  public function render(NotificationTypeInterface $type, string $channelId, array $tokenData): NotificationMessage {
    $subject = (string) $this->token->replace($type->getSubjectTemplate(), $tokenData, ['clear' => TRUE]);

    return new NotificationMessage(
      subject: $subject,
      body: $this->renderEntity($tokenData['entity'] ?? NULL),
    );
  }

  /**
   * Renders an entity through its view builder to a string.
   *
   * @param mixed $entity
   *   The entity to render, or NULL.
   *
   * @return string
   *   The rendered markup, or an empty string when no entity is given.
   */
  protected function renderEntity(mixed $entity): string {
    if (!$entity instanceof EntityInterface) {
      return '';
    }
    $view_mode = $this->configuration['view_mode'] ?? 'full';
    $build = $this->entityTypeManager
      ->getViewBuilder($entity->getEntityTypeId())
      ->view($entity, $view_mode);

    return (string) $this->renderer->renderInIsolation($build);
  }

}
