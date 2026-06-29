<?php

declare(strict_types=1);

namespace Drupal\openintranet_notifications\Entity\Handler;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Adds a GET status filter to an EntityListBuilder.
 *
 * Implementers provide statusFilterOptions() (the entity's allowed status
 * values) and a request_stack via $this->requestStack; the trait reads
 * ?status= in getEntityIds() and prepends a filter form in render() (§10).
 */
trait StatusFilterListBuilderTrait {

  /**
   * The request stack used to read the GET status filter.
   */
  protected RequestStack $requestStack;

  /**
   * Returns the status values offered by the filter (value => label).
   *
   * @return array<string, string>
   *   The allowed status values keyed by stored value.
   */
  abstract protected function statusFilterOptions(): array;

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds(): array {
    $query = $this->getStorage()->getQuery()
      ->accessCheck(TRUE)
      ->sort($this->entityType->getKey('id'), 'DESC');

    $status = $this->currentStatusFilter();
    if ($status !== '') {
      $query->condition('status', $status);
    }

    if ($this->limit) {
      $query->pager($this->limit);
    }
    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $build['filter'] = $this->buildFilterForm();
    $build += parent::render();
    return $build;
  }

  /**
   * Returns the validated ?status= filter, or '' when absent or unknown.
   */
  protected function currentStatusFilter(): string {
    $status = (string) $this->requestStack->getCurrentRequest()?->query->get('status', '');
    return isset($this->statusFilterOptions()[$status]) ? $status : '';
  }

  /**
   * Builds the GET status filter form rendered above the listing.
   *
   * @return array<string, mixed>
   *   A render array for the filter form.
   */
  protected function buildFilterForm(): array {
    $current = $this->currentStatusFilter();
    $options = ['' => $this->t('- Any status -')];
    foreach ($this->statusFilterOptions() as $value => $label) {
      $options[$value] = $this->t('@label', ['@label' => $label]);
    }

    return [
      '#type' => 'html_tag',
      '#tag' => 'form',
      '#attributes' => ['method' => 'get', 'class' => ['openintranet-notif-status-filter']],
      'status' => [
        '#type' => 'select',
        '#name' => 'status',
        '#title' => $this->t('Status'),
        '#options' => $options,
        '#value' => $current,
      ],
      'submit' => [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => $this->t('Apply'),
        '#attributes' => ['type' => 'submit'],
      ],
    ];
  }

}
