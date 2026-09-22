<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\NotificationTemplateRenderer;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_notifications\Attribute\NotificationTemplateRenderer;
use Drupal\openintranet_notifications\Dto\NotificationMessage;
use Drupal\openintranet_notifications\Entity\NotificationTypeInterface;
use Drupal\openintranet_notifications\Renderer\NotificationTemplateRendererBase;

/**
 * Renders templates with Drupal core token replacement.
 *
 * The default renderer: the subject, body and summary templates carry tokens
 * (e.g. [user:name]) replaced from the token data. When the type's template map
 * has an entry for the target channel, it overrides the body template for that
 * channel (00-synteza §8).
 */
#[NotificationTemplateRenderer(
  id: 'token_text',
  label: new TranslatableMarkup('Token text'),
  description: new TranslatableMarkup('Replaces Drupal tokens in the subject, body and summary templates.'),
)]
final class TokenText extends NotificationTemplateRendererBase {

  /**
   * {@inheritdoc}
   */
  public function render(NotificationTypeInterface $type, string $channelId, array $tokenData): NotificationMessage {
    $body_template = $type->getTemplateMap()[$channelId] ?? $type->getBodyTemplate();

    return new NotificationMessage(
      subject: $this->replace($type->getSubjectTemplate(), $tokenData),
      body: $this->replace($body_template, $tokenData),
      summary: $this->replace($type->getSummaryTemplate(), $tokenData),
    );
  }

}
