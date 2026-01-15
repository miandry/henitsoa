<?php

namespace Drupal\Tests\taxonomy_max_depth\Functional\Form;

use Drupal\taxonomy\VocabularyInterface;

/**
 * Tests the add-on to the vocabulary settings form.
 *
 * @group taxonomy_max_depth
 */
class VocabularyFormTest extends FormTestBase {

  protected function reloadVocabulary(
    VocabularyInterface $vocabulary
  ): VocabularyInterface {
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $this->container->get('entity_type.manager');

    /** @var \Drupal\taxonomy\VocabularyInterface $result */
    $result = $entity_type_manager->getStorage('taxonomy_vocabulary')
      ->load($vocabulary->id());

    return $result;
  }

  /**
   * Tests the vocabulary form.
   */
  public function testForm() {
    /** @var \Drupal\taxonomy\VocabularyInterface $vocabulary */
    $vocabulary = $this->createVocabulary();
    $form_path = '/admin/structure/taxonomy/manage/' . $vocabulary->id();
    $settings_reader = $this->container
      ->get('taxonomy_max_depth.vocabulary_settings_reader');

    $this->drupalGet($form_path);
    $this->submitForm(['taxonomy_max_depth' => 2], 'Save');
    $vocabulary = $this->reloadVocabulary($vocabulary);
    $this->assertEquals(2, $settings_reader->getMaxAncestorDepth($vocabulary));

    // Zero means no hierarchy, so it should be saved as-is.
    $this->drupalGet($form_path);
    $this->submitForm(['taxonomy_max_depth' => 0], 'Save');
    $vocabulary = $this->reloadVocabulary($vocabulary);
    $this->assertSame(0, $settings_reader->getMaxAncestorDepth($vocabulary));

    // Empty string means no limit, so it is saved as NULL.
    $this->drupalGet($form_path);
    $this->submitForm(['taxonomy_max_depth' => ''], 'Save');
    $vocabulary = $this->reloadVocabulary($vocabulary);
    $this->assertNull($settings_reader->getMaxAncestorDepth($vocabulary));
  }

}
