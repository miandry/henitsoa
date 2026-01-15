<?php

namespace Drupal\taxonomy_max_depth\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy\TermStorageInterface;
use Drupal\taxonomy\VocabularyInterface;
use Drupal\taxonomy_max_depth\Taxonomy\TermTreeDepthHelperInterface;
use Drupal\taxonomy_max_depth\Taxonomy\VocabularySettingsReaderInterface;

/**
 * Alters the term form.
 *
 * Adds max depth validation.
 */
class TermFormAlterer extends FormAltererBase {

  use StringTranslationTrait;

  /**
   * The entity type ID of the taxonomy term.
   */
  const TERM_ENTITY_TYPE_ID = 'taxonomy_term';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The term depth helper.
   *
   * @var \Drupal\taxonomy_max_depth\Taxonomy\TermTreeDepthHelperInterface
   */
  protected $termDepthHelper;

  /**
   * A constructor.
   *
   * @param \Drupal\taxonomy_max_depth\Taxonomy\VocabularySettingsReaderInterface $settings_reader
   *   The vocabulary settings reader.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\taxonomy_max_depth\Taxonomy\TermTreeDepthHelperInterface $term_depth_helper
   *   The term depth helper.
   */
  public function __construct(
    VocabularySettingsReaderInterface $settings_reader,
    EntityTypeManagerInterface $entity_type_manager,
    TermTreeDepthHelperInterface $term_depth_helper
  ) {
    parent::__construct($settings_reader);

    $this->entityTypeManager = $entity_type_manager;
    $this->termDepthHelper = $term_depth_helper;
  }

  /**
   * {@inheritdoc}
   */
  public function alter(array &$form, FormStateInterface $form_state) {
    $vocabulary = $form_state->get(['taxonomy', 'vocabulary']);
    if (!$vocabulary instanceof VocabularyInterface) {
      return;
    }

    $max_depth = $this->settingsReader->getMaxAncestorDepth($vocabulary);
    if (!isset($max_depth)) {
      return;
    }

    $form_state->set(static::MODULE_NAME, [
      'max_depth' => $max_depth,
    ]);
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
    /** @var \Drupal\Core\Entity\EntityFormInterface $form_object */
    $form_object = $form_state->getFormObject();
    $term = $form_object->getEntity();
    if (!$term instanceof TermInterface) {
      return;
    }

    $max_depth_allowed = $form_state->get([static::MODULE_NAME, 'max_depth']);
    if (!isset($max_depth_allowed)) {
      return;
    }

    if (empty($max_depth_allowed)) {
      $this->validateDisabledHierarchy($form_state);
    }
    else {
      $this->validateMaxDepth($term, $form_state, $max_depth_allowed);
    }
  }

  /**
   * Validates that the term has no parents except the root.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function validateDisabledHierarchy(
    FormStateInterface $form_state
  ) {
    $new_parent_ids = $form_state->getValue('parent', []);
    foreach ($new_parent_ids as $new_parent_id) {
      if (!empty($new_parent_id)) {
        $message = $this->t(
          'Terms are not allowed to have ancestors on this vocabulary.'
        );
        $form_state->setErrorByName('parent', $message);
        return;
      }
    }
  }

  /**
   * Validates max depth of the term and its children.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The term.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param int $max_depth_allowed
   *   The maximum allowed number of ancestors.
   */
  protected function validateMaxDepth(
    TermInterface $term,
    FormStateInterface $form_state,
    int $max_depth_allowed
  ) {
    $term_storage = $this->entityTypeManager
      ->getStorage(static::TERM_ENTITY_TYPE_ID);
    if (!$term_storage instanceof TermStorageInterface) {
      return;
    }

    $old_parent_ids = $form_state->get(['taxonomy', 'parent']);
    $new_parent_ids = $form_state->getValue('parent', []);
    $added_parent_ids = array_diff($new_parent_ids, $old_parent_ids);
    $added_parent_ids = array_filter($added_parent_ids);
    if (empty($added_parent_ids)) {
      return;
    }

    // The term could reach the max depth because one of its new parents is
    // deep enough.
    $depth_capacity_left = $max_depth_allowed;
    $added_parents = $term_storage->loadMultiple($added_parent_ids);
    $max_ancestor_depth = $this->termDepthHelper
      ->getMaxAncestorDepth($added_parents);
    if ($max_ancestor_depth > $depth_capacity_left) {
      $form_state->setErrorByName(
        'parent',
        $this->formatPlural(
          $max_depth_allowed,
          "The term shouldn't have more than @count ancestor.",
          "The term shouldn't have more than @count ancestors."
        )
      );
      return;
    }

    // New term doesn't have any children, so nothing else to validate.
    if ($term->isNew()) {
      return;
    }

    // Any of the child terms could reach the limit because of new parents plus
    // their current depth until the edited term.
    $depth_capacity_left = $depth_capacity_left - $max_ancestor_depth;
    $children_tree = $term_storage->loadTree(
      $term->bundle(),
      $term->id(),
      $depth_capacity_left + 1
    );
    $max_children_depth = $this->termDepthHelper
      ->getMaxDescendantDepth($children_tree);
    if ($max_children_depth > $depth_capacity_left) {
      $form_state->setErrorByName(
        'parent',
        $this->formatPlural(
          $max_depth_allowed,
          "The term children would have more than @count ancestor allowed.",
          "The term children would have more than @count ancestors allowed."
        )
      );
    }
  }

}
