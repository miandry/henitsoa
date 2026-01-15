<?php

namespace Drupal\taxonomy_max_depth\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\taxonomy\VocabularyInterface;
use Drupal\Component\Utility\Unicode;

/**
 * Alters the term overview form.
 *
 * Adds max ancestor depth limit to the form and the table drag UI.
 */
class TermOverviewFormAlterer extends FormAltererBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function alter(array &$form, FormStateInterface $form_state) {
    if (empty($form['terms']['#tabledrag'])) {
      return;
    }

    $vocabulary = $form_state->get(['taxonomy', 'vocabulary']);
    if (!$vocabulary instanceof VocabularyInterface) {
      return;
    }

    $max_depth = $this->settingsReader->getMaxAncestorDepth($vocabulary);
    if (!isset($max_depth)) {
      return;
    }

    // Change help message if no hierarchy allowed.
    if (!$max_depth) {
      $args = [
        '%capital_name' => Unicode::ucfirst($vocabulary->label()),
      ];
      $form['help']['message']['#markup'] = $this->t(
        'You can reorganize the terms in %capital_name using their drag-and-drop handles.',
        $args
      );
    }

    $form_state->set(static::MODULE_NAME, [
      'max_depth' => $max_depth,
    ]);

    $hierarchy_enabled = FALSE;
    foreach ($form['terms']['#tabledrag'] as $index => &$group) {
      $relationship = $group['relationship'] ?? NULL;
      if ($relationship !== 'parent') {
        continue;
      }

      $hierarchy_enabled = TRUE;
      if (empty($max_depth)) {
        unset($form['terms']['#tabledrag'][$index]);
      }
      else {
        $group['limit'] = $max_depth;
      }
    }
    if (empty($hierarchy_enabled)) {
      return;
    }

    $form['#validate'][] = $this->prepareFormCallback('validate');
  }

  /**
   * Form validation callback.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validate(array &$form, FormStateInterface $form_state) {
    $max_depth = $form_state->get([static::MODULE_NAME, 'max_depth']);
    if (!isset($max_depth)) {
      return;
    }

    $terms = $form_state->getValue('terms', []);
    if (empty($terms)) {
      return;
    }

    $term_parents_map = $this->buildTermParentsMap($terms);
    $analysis_result = $this
      ->analyzeTermParentsMap($term_parents_map, $max_depth);

    if (!empty($analysis_result['circular'])) {
      $form_state->setErrorByName(
        'terms',
        $this->t('Terms with circular dependencies were submitted.')
      );
    }
    if (!empty($analysis_result['too_deep'])) {
      if (!empty($max_depth)) {
        $message = $this->formatPlural(
          $max_depth,
          'Terms having more than @count ancestor allowed were submitted.',
          'Terms having more than @count ancestors allowed were submitted.'
        );
      }
      else {
        $message = $this->t(
          'Terms are not allowed to have ancestors on this vocabulary.'
        );
      }

      $form_state->setErrorByName('terms', $message);
    }
  }

  /**
   * Builds a map of term ID to its parent.
   *
   * @param array $terms
   *   Term values submitted from the form.
   *
   * @return array
   *   The map of term ID to their parent or zero for root terms.
   */
  protected function buildTermParentsMap(array $terms): array {
    $term_parents_map = [];

    foreach ($terms as $term_info) {
      $term_id = $term_info['term']['tid'] ?? NULL;
      $parent_id = $term_info['term']['parent'] ?? NULL;
      if (!isset($term_id) || !isset($parent_id)) {
        continue;
      }

      $term_parents_map[$term_id] = $parent_id;
    }

    return $term_parents_map;
  }

  /**
   * Analyzes the terms to parents map for errors.
   *
   * @param array $term_parents_map
   *   The map of term ID to its parent ID or zero.
   * @param int $max_depth
   *   Max depth of ancestors allowed for a term.
   *
   * @return array
   *   Number of errors under the following keys:
   *   - circular: the number of terms with circular dependencies,
   *   - too_deep: the number of terms with ancestors depth exceeding the limit.
   */
  protected function analyzeTermParentsMap(
    array $term_parents_map,
    int $max_depth
  ): array {
    $terms_depth = [];
    $circular_dependencies = 0;
    $exceeding_max_depth = 0;
    foreach (array_keys($term_parents_map) as $term_id) {
      $parents_passed = [
        $term_id => TRUE,
      ];
      $circular_dependency = FALSE;

      $depth = 0;
      $current_tid = $term_id;
      while (!empty($term_parents_map[$current_tid])) {
        $depth++;
        $current_tid = $term_parents_map[$current_tid];

        if (isset($terms_depth[$current_tid])) {
          $depth += $terms_depth[$current_tid];
          break;
        }

        if (isset($parents_passed[$current_tid])) {
          $circular_dependency = TRUE;
          break;
        }
        $parents_passed[$current_tid] = TRUE;
      }

      if ($circular_dependency) {
        $circular_dependencies++;
        continue;
      }

      if ($depth > $max_depth) {
        $exceeding_max_depth++;
      }
      $terms_depth[$term_id] = $depth;
    }

    return [
      'circular' => $circular_dependencies,
      'too_deep' => $exceeding_max_depth,
    ];
  }

}
