<?php

declare(strict_types=1);

namespace Drupal\openintranet_engagement\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\openintranet_engagement\Service\OiEngagementReporterInterface;
use Drupal\openintranet_engagement\Service\OiEngagementSegmenterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for data export.
 */
final class ExportController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Constructs the ExportController.
   *
   * @param \Drupal\openintranet_engagement\Service\OiEngagementReporterInterface $reporter
   *   The reporter service.
   * @param \Drupal\openintranet_engagement\Service\OiEngagementSegmenterInterface $segmenter
   *   The segmenter service.
   */
  public function __construct(
    private readonly OiEngagementReporterInterface $reporter,
    private readonly OiEngagementSegmenterInterface $segmenter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('openintranet_engagement.reporter'),
      $container->get('openintranet_engagement.segmenter'),
    );
  }

  /**
   * Displays the export page.
   *
   * @return array
   *   The render array.
   */
  public function export(): array {
    $segments = $this->segmenter->getAllSegments();
    $segmentOptions = ['' => $this->t('All Segments')];
    foreach ($segments as $key => $def) {
      $segmentOptions[$key] = $def['icon'] . ' ' . $def['label'];
    }

    return [
      '#type' => 'container',
      'description' => [
        '#markup' => '<p>' . $this->t('Export engagement data for reporting and analysis.') . '</p>',
      ],
      'form' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['export-options']],
        'segment' => [
          '#type' => 'select',
          '#title' => $this->t('Segment'),
          '#options' => $segmentOptions,
          '#description' => $this->t('Filter by segment (optional).'),
        ],
        'actions' => [
          '#type' => 'container',
          'csv' => [
            '#type' => 'link',
            '#title' => $this->t('Download CSV'),
            '#url' => \Drupal\Core\Url::fromRoute('openintranet_engagement.export_download', ['type' => 'csv']),
            '#attributes' => ['class' => ['button']],
          ],
        ],
      ],
      'preview' => [
        '#type' => 'details',
        '#title' => $this->t('Preview'),
        '#open' => FALSE,
        'summary' => [
          '#markup' => '<p>' . $this->t('The export will include:') . '</p>
            <ul>
              <li>' . $this->t('User ID, Name, Email') . '</li>
              <li>' . $this->t('Segment classification') . '</li>
              <li>' . $this->t('R, F, V scores (1-5)') . '</li>
              <li>' . $this->t('Total score') . '</li>
              <li>' . $this->t('Last activity date') . '</li>
              <li>' . $this->t('Event count') . '</li>
            </ul>',
        ],
      ],
    ];
  }

  /**
   * Downloads export file.
   *
   * @param string $type
   *   Export type: csv or excel.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The file response.
   */
  public function download(string $type): Response {
    $csv = $this->reporter->exportCsv([], 'users');
    $filename = 'engagement_export_' . date('Y-m-d_His') . '.csv';

    $response = new Response($csv);
    $response->headers->set('Content-Type', 'text/csv');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

    return $response;
  }

}
