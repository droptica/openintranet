<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Plugin\NotificationTemplateRenderer;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\openintranet_notifications\Attribute\NotificationTemplateRenderer;
use Drupal\openintranet_notifications\Dto\NotificationMessage;
use Drupal\openintranet_notifications\Entity\NotificationTypeInterface;
use Drupal\openintranet_notifications\Renderer\NotificationTemplateRendererBase;

/**
 * Renders templates as inline Twig with the token data as variables.
 *
 * The subject, body and summary templates are Twig source rendered with the
 * token data map (token type => object) as Twig variables (00-synteza §8).
 *
 * Templates are evaluated by the Twig engine, so this renderer is only safe for
 * trusted-admin authored types. It MUST NOT be exposed to untrusted template
 * input.
 */
#[NotificationTemplateRenderer(
  id: 'twig',
  label: new TranslatableMarkup('Twig'),
  description: new TranslatableMarkup('Renders the templates as inline Twig (trusted admin only).'),
)]
final class Twig extends NotificationTemplateRendererBase {

  /**
   * {@inheritdoc}
   */
  public function render(NotificationTypeInterface $type, string $channelId, array $tokenData): NotificationMessage {
    $body_template = $type->getTemplateMap()[$channelId] ?? $type->getBodyTemplate();

    return new NotificationMessage(
      subject: $this->renderInline($type->getSubjectTemplate(), $tokenData),
      body: $this->renderInline($body_template, $tokenData),
      summary: $this->renderInline($type->getSummaryTemplate(), $tokenData),
    );
  }

  /**
   * Renders a template string as inline Twig.
   *
   * @param string $template
   *   The Twig template source.
   * @param array $tokenData
   *   The variables exposed to the template.
   *
   * @return string
   *   The rendered, trimmed markup.
   */
  protected function renderInline(string $template, array $tokenData): string {
    if ($template === '') {
      return '';
    }
    return trim((string) $this->twig->renderInline($template, $tokenData));
  }

}
