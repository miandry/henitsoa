<?php

namespace Drupal\taxonomy_max_depth\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides an interface for a class that alters a form.
 */
interface FormAltererInterface {

  /**
   * Alters the passed form.
   *
   * @param array $form
   *   The form array to alter.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function alter(array &$form, FormStateInterface $form_state);

}
