<?php

namespace Drupal\Tests\taxonomy_max_depth\Functional\Form;

use Drupal\Tests\taxonomy_max_depth\Traits\AssertHelperTrait;

/**
 * Tests max depth validation on the term tree overview form.
 *
 * @group taxonomy_max_depth
 */
class TermOverviewFormTest extends FormTestBase {

  use AssertHelperTrait;

  /**
   * @var \Drupal\taxonomy\Entity\Vocabulary
   */
  protected $vocabulary;

  /**
   * @var \Drupal\taxonomy_max_depth\Taxonomy\VocabularySettingsWriterInterface
   */
  protected $settingsWriter;

  /**
   * @var \Drupal\taxonomy\TermStorageInterface
   */
  protected $termStorage;

  protected function setUp(): void {
    parent::setUp();

    $this->settingsWriter = $this->container
      ->get('taxonomy_max_depth.vocabulary_settings_writer');
    $this->termStorage = $this->container
      ->get('entity_type.manager')
      ->getStorage('taxonomy_term');
  }

  public function testForm() {
    $methods = [
      'doTestCircularDependencyValidation',
      'doTestMaxAncestorDepth',
    ];
    foreach ($methods as $method) {
      $this->vocabulary = $this->createVocabulary();
      $this->{$method}();
    }
  }

  protected function submitTermOverviewFormWithCircularDependency() {
    // Create the following tree of terms:
    // - term 1
    // -- term 1.1
    // - term 2
    // -- term 2.1.
    $term_1 = $this->createTerm($this->vocabulary, ['parent' => 0]);
    $term_1_1 = $this->createTerm($this->vocabulary, [
      'parent' => $term_1->id(),
    ]);
    $term_2 = $this->createTerm($this->vocabulary, ['parent' => 0]);
    $term_2_1 = $this->createTerm($this->vocabulary, [
      'parent' => $term_2->id(),
    ]);

    // Submit the form with the following updates that should trigger circular
    // dependency validation failure:
    // - set term 1 parent to term 2.1
    // - set term 2 parent to term 1.1.
    $term_values = [];
    $term_values[$term_1->id()][0]['parent'] = $term_2_1->id();
    $term_values[$term_2->id()][0]['parent'] = $term_1_1->id();
    $this->submitTermOverviewForm($term_values);
  }

  protected function doTestCircularDependencyValidation() {
    // Turn the limit off and make sure no validation happens.
    $this->setMaxAncestorDepth(NULL);
    $this->submitTermOverviewFormWithCircularDependency();
    $this->assertSuccessMessageContains('saved');

    // Disable hierarchy and make sure the validation is triggered.
    $this->setMaxAncestorDepth(0);
    $this->submitTermOverviewFormWithCircularDependency();
    $this->assertErrorMessageContains('Terms with circular dependencies');

    // Set limit to 2 and make sure we have validation triggered.
    $this->setMaxAncestorDepth(2);
    $this->submitTermOverviewFormWithCircularDependency();
    $this->assertErrorMessageContains('Terms with circular dependencies');
  }

  protected function assertTermParents(int $term_id, array $parent_ids) {
    $this->termStorage->resetCache([$term_id]);

    /** @var \Drupal\taxonomy\TermInterface $term */
    $term = $this->termStorage->load($term_id);
    $actual_parent_ids = array_column(
      $term->get('parent')->getValue(),
      'target_id'
    );
    sort($parent_ids, SORT_NUMERIC);
    sort($actual_parent_ids, SORT_NUMERIC);
    $this->assertEquals($parent_ids, $actual_parent_ids);
  }

  protected function setMaxAncestorDepth(int $max_depth = NULL) {
    $this->settingsWriter->setMaxAncestorDepth($this->vocabulary, $max_depth);
    $this->vocabulary->save();
  }

  protected function submitTermOverviewForm(array $term_values) {
    $path = '/admin/structure/taxonomy/manage/' . $this->vocabulary->id() . '/overview';
    $this->drupalGet($path);

    $hidden_values = [];
    foreach ($term_values as $term_id => $deltas) {
      foreach ($deltas as $delta => $fields) {
        foreach ($fields as $field_name => $field_value) {
          $key = "terms[tid:{$term_id}:{$delta}][term][{$field_name}]";
          $hidden_values[$key] = $field_value;
        }
      }
    }

    $this->setHiddenFieldValues($hidden_values);
    $this->submitForm([], 'Save');
  }

  protected function setHiddenFieldValues(array $values) {
    $assert_session = $this->assertSession();
    $values = $this->castSafeStrings($values);
    foreach ($values as $key => $value) {
      $element = $assert_session->hiddenFieldExists($key);
      $element->setValue($value);
    }
  }

  protected function doTestMaxAncestorDepth() {
    // Create the following tree of terms:
    // - term 1
    // -- term 1.1
    // - term 2
    // -- term 2.1.
    $term_1 = $this->createTerm($this->vocabulary, ['parent' => 0]);
    $term_1_1 = $this->createTerm($this->vocabulary, [
      'parent' => $term_1->id(),
    ]);
    $term_2 = $this->createTerm($this->vocabulary, ['parent' => 0]);
    $term_2_1 = $this->createTerm($this->vocabulary, [
      'parent' => $term_2->id(),
    ]);

    // Disable the hierarchy and make sure we're not able to submit the tree
    // as it is now.
    $this->setMaxAncestorDepth(0);
    $this->submitTermOverviewForm([]);
    $this->assertErrorMessageContains('ancestor');

    // Set max ancestor depth to 1 and make sure we're not able to move the term
    // 2.1 to become a child of the term 1.1, as it would give us the following
    // tree and max ancestor depth for the term 2.1 would be 2:
    // - term 1
    // -- term 1.1
    // --- term 2.1
    $this->setMaxAncestorDepth(1);
    $term_values = [];
    $term_values[$term_2_1->id()][0]['parent'] = $term_1_1->id();
    $this->submitTermOverviewForm($term_values);
    $this->assertErrorMessageContains('ancestor');
    $this->assertTermParents($term_2_1->id(), [$term_2->id()]);

    // Set max ancestor depth to 2 and make sure we're not able to move the term
    // 2 to become a child of the term 1.1, as it would give us the following
    // tree and max ancestor depth for the term 2.1 would be 3:
    // - term 1
    // -- term 1.1
    // --- term 2
    // ---- term 2.1
    $this->setMaxAncestorDepth(2);
    $term_values = [];
    $term_values[$term_2->id()][0]['parent'] = $term_1_1->id();
    $this->submitTermOverviewForm($term_values);
    $this->assertErrorMessageContains('ancestor');
    $this->assertTermParents($term_2->id(), [0]);

    // Make sure we're still able to save any tree with max depth disabled on
    // the vocabulary.
    $this->setMaxAncestorDepth(NULL);
    $term_values = [];
    $term_values[$term_2->id()][0]['parent'] = $term_1_1->id();
    $this->submitTermOverviewForm($term_values);
    $this->assertSuccessMessageContains('saved');
    $this->assertTermParents($term_2->id(), [$term_1_1->id()]);
  }

}
