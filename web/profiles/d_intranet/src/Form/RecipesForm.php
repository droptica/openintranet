<?php

namespace Drupal\d_intranet\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form to choose optional recipes during installation.
 */
final class RecipesForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'd_intranet_recipes_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#title'] = $this->t('Choose content');

    $form['help'] = [
      '#prefix' => '<p class="d_intranet-installer__subhead">',
      '#markup' => $this->t('You can choose to install example demo content or start with a clean site.'),
      '#suffix' => '</p>',
    ];

    $form['recipes'] = [
      '#prefix' => '<div class="d_intranet-installer__form-group">',
      '#suffix' => '</div>',
      '#type' => 'checkboxes',
      '#options' => [
        'default_content' => $this->t('Install demo content'),
      ],
      '#description' => $this->t('Demo content includes example pages, blog posts, and other content to help you get started.'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Next'),
        '#button_type' => 'primary',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    global $install_state;

    // Initialize parameters if not set.
    if (!isset($install_state['parameters'])) {
      $install_state['parameters'] = [];
    }

    // Reset recipes array.
    $install_state['parameters']['recipes'] = [];

    // Check if default content is selected.
    if (!empty($form_state->getValue('recipes')['default_content'])) {
      $install_state['parameters']['recipes'][] = 'default_content';
    }
  }

}
