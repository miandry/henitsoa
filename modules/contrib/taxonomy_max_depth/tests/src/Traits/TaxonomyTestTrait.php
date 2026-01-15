<?php

namespace Drupal\Tests\taxonomy_max_depth\Traits;

use Drupal\Core\Language\LanguageInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\VocabularyInterface;

/**
 * Provides common helper methods for Taxonomy module tests.
 *
 * It's a copy of the core trait that has different namespace in Drupal 8 & 9.
 */
trait TaxonomyTestTrait {

  /**
   * Returns a new vocabulary with random properties.
   *
   * @return \Drupal\taxonomy\VocabularyInterface
   *   A vocabulary used for testing.
   */
  public function createVocabulary() {
    /** @var \Drupal\taxonomy\VocabularyInterface $vocabulary */
    $vocabulary = Vocabulary::create([
      'name' => $this->randomMachineName(),
      'description' => $this->randomMachineName(),
      'vid' => mb_strtolower($this->randomMachineName()),
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
      'weight' => mt_rand(0, 10),
    ]);
    $vocabulary->save();
    return $vocabulary;
  }

  /**
   * Returns a new term with random properties given a vocabulary.
   *
   * @param \Drupal\taxonomy\VocabularyInterface $vocabulary
   *   The vocabulary object.
   * @param array $values
   *   (optional) An array of values to set, keyed by property name.
   *
   * @return \Drupal\taxonomy\TermInterface
   *   The new taxonomy term object.
   */
  public function createTerm(VocabularyInterface $vocabulary, $values = []) {
    /** @var \Drupal\taxonomy\TermInterface $term */
    $term = Term::create($values + [
        'name' => $this->randomMachineName(),
        'description' => [
          'value' => $this->randomMachineName(),
          // Use the fallback text format.
          'format' => filter_fallback_format(),
        ],
        'vid' => $vocabulary->id(),
        'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
      ]);
    $term->save();
    return $term;
  }

}
