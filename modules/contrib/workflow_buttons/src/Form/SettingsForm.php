<?php

namespace Drupal\workflow_buttons\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure global workflow_buttons settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'workflow_buttons_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'workflow_buttons.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $field_type = NULL) {
    $config = $this->config('workflow_buttons.settings');

    $form['top_buttons'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Buttons on top and bottom'),
      '#default_value' => $config->get('display.top_buttons'),
      '#description' => $this->t('Show the workflow buttons at <em>both</em> the top and the bottom of the form.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('workflow_buttons.settings');
    $config->set('display.top_buttons', (bool) $form_state->getValue('top_buttons'));
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
