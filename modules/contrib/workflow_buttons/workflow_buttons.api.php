<?php

/**
 * @file
 * Document all supported APIs.
 */

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the ability to alter workflow state after submitting form.
 *
 * @param string $moderation_state
 *   The workflow state to be used when saving the node.
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The entity context to set the state, if needed.
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   The form state.
 */
function hook_workflow_buttons_state_alter(string &$moderation_state, EntityInterface &$entity, FormStateInterface &$form_state) {
}
