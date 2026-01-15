<?php

namespace Drupal\Tests\taxonomy_max_depth\Functional\Form;

use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy\VocabularyInterface;

/**
 * Tests max depth validation on the term create/edit form.
 *
 * @group taxonomy_max_depth
 */
class TermFormTest extends FormTestBase {

  /**
   * @var \Drupal\taxonomy\Entity\Vocabulary
   */
  protected $vocabulary;

  /**
   * @var \Drupal\taxonomy\TermStorageInterface
   */
  protected $termStorage;

  /**
   * @var \Drupal\taxonomy_max_depth\Taxonomy\VocabularySettingsWriterInterface
   */
  protected $settingsWriter;

  protected function setUp(): void {
    parent::setUp();

    $this->vocabulary = $this->createVocabulary();
    $this->settingsWriter = $this->container
      ->get('taxonomy_max_depth.vocabulary_settings_writer');
    $this->termStorage = $this->container->get('entity_type.manager')
      ->getStorage('taxonomy_term');
  }

  protected function submitTermEditForm(TermInterface $term, array $parents) {
    $path = 'taxonomy/term/' . $term->id() . '/edit';
    $this->drupalGet($path);
    $this->submitForm(['parent[]' => $parents], 'Save');
  }

  protected function submitTermCreationForm(
    VocabularyInterface $vocabulary,
    array $parents
  ) {
    $path = 'admin/structure/taxonomy/manage/' . $vocabulary->id() . '/add';
    $values = [
      'name[0][value]' => $this->randomString(),
      'parent[]' => $parents
    ];
    $this->drupalGet($path);
    $this->submitForm($values, 'Save');
  }

  protected function setMaxAncestorDepth(int $max_depth = NULL) {
    $this->settingsWriter->setMaxAncestorDepth($this->vocabulary, $max_depth);
    $this->vocabulary->save();
  }

  /**
   * Makes sure user is still able to save term with valid ancestor chain.
   */
  protected function doTestCreateTermWithValidAncestors() {
    $this->setMaxAncestorDepth(1);

    $top_term = $this->createTerm($this->vocabulary, ['parent' => 0]);

    $this->submitTermCreationForm($this->vocabulary, [$top_term->id()]);

    $children = $this->termStorage->loadTree($this->vocabulary->id(), $top_term->id());
    $this->assertNotEmpty($children);
  }

  /**
   * Makes sure user gets an error in case the ancestor chain is too long.
   */
  protected function doTestCreateTermWithTooDeepAncestors() {
    $this->setMaxAncestorDepth(1);

    $top_term = $this->createTerm($this->vocabulary, ['parent' => 0]);
    $child_term = $this->createTerm($this->vocabulary, ['parent' => $top_term->id()]);

    $this->submitTermCreationForm($this->vocabulary, [$child_term->id()]);
    $this->assertErrorMessageContains("The term shouldn't have more than");

    $children = $this->termStorage->loadTree($this->vocabulary->id(), $child_term->id());
    $this->assertEmpty($children);
  }

  /**
   * Makes sure we're still able to move terms in the tree without errors.
   */
  protected function doTestTermMoveWithValidChains() {
    $this->setMaxAncestorDepth(1);

    $top_term_1 = $this->createTerm($this->vocabulary, ['parent' => 0]);
    $child_term = $this->createTerm($this->vocabulary, ['parent' => $top_term_1->id()]);
    $top_term_2 = $this->createTerm($this->vocabulary, ['parent' => 0]);

    $this->submitTermEditForm($child_term, [$top_term_2->id()]);

    $children_of_term_1 = $this->termStorage->loadTree($this->vocabulary->id(), $top_term_1->id());
    $this->assertEmpty($children_of_term_1);

    $children_of_term_2 = $this->termStorage->loadTree($this->vocabulary->id(), $top_term_2->id());
    $this->assertCount(1, $children_of_term_2);
    $this->assertEquals(reset($children_of_term_2)->tid, $child_term->id());
  }

  /**
   * Makes sure user gets error in case child ancestor chain becomes too long.
   */
  protected function doTestTermMoveWithTooDeepDescendants() {
    $this->setMaxAncestorDepth(2);

    $top_term_1 = $this->createTerm($this->vocabulary, ['parent' => 0]);
    $term_1_child = $this->createTerm($this->vocabulary, ['parent' => $top_term_1->id()]);
    $term_1_grandchild = $this->createTerm($this->vocabulary, ['parent' => $term_1_child->id()]);

    $top_term_2 = $this->createTerm($this->vocabulary, ['parent' => 0]);
    $term_2_child = $this->createTerm($this->vocabulary, ['parent' => $top_term_2->id()]);

    $this->submitTermEditForm($term_1_child, [$term_2_child->id()]);
    $this->assertErrorMessageContains('The term children would have more than');

    $top_term_1_tree = $this->termStorage->loadTree($this->vocabulary->id(), $top_term_1->id());
    $this->assertCount(2, $top_term_1_tree);

    $term_2_child_tree = $this->termStorage->loadTree($this->vocabulary->id(), $term_2_child->id());
    $this->assertEmpty($term_2_child_tree);
  }

  protected function doTestCreateTermWithHierarchyDisabled() {
    $this->setMaxAncestorDepth(0);

    $term_1 = $this->createTerm($this->vocabulary, ['parent' => 0]);

    // Make sure user is not able to create a term with a non-root parent.
    $this->submitTermCreationForm($this->vocabulary, [$term_1->id()]);
    $this->assertErrorMessageContains('Terms are not allowed to have ancestors');
    $term_1_tree = $this->termStorage->loadTree($this->vocabulary->id(), $term_1->id());
    $this->assertEmpty($term_1_tree);

    // Make sure user is still able to create a term without a parent.
    $this->submitTermCreationForm($this->vocabulary, [0]);
    $this->assertSuccessMessageContains('Created new term');
  }

  protected function doTestUpdateTermWithHierarchyDisabled() {
    $this->setMaxAncestorDepth(0);

    $term_1 = $this->createTerm($this->vocabulary, ['parent' => 0]);
    $term_2 = $this->createTerm($this->vocabulary, ['parent' => 0]);

    // Make sure user is not able to update a term with a parent set.
    $this->submitTermEditForm($term_2, [$term_1->id()]);
    $this->assertErrorMessageContains('Terms are not allowed to have ancestors');
    $term_1_tree = $this->termStorage->loadTree($this->vocabulary->id(), $term_1->id());
    $this->assertEmpty($term_1_tree);

    // Make sure user is still able to update a term without a parent set.
    $this->submitTermEditForm($term_2, [0]);
    $this->assertSuccessMessageContains('Updated term');
  }

  public function testForm() {
    $this->doTestCreateTermWithValidAncestors();
    $this->doTestCreateTermWithTooDeepAncestors();
    $this->doTestCreateTermWithHierarchyDisabled();
    $this->doTestTermMoveWithValidChains();
    $this->doTestTermMoveWithTooDeepDescendants();
    $this->doTestUpdateTermWithHierarchyDisabled();
  }

}
