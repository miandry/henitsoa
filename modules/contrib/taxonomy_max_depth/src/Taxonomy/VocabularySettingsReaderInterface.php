<?php

namespace Drupal\taxonomy_max_depth\Taxonomy;

/**
 * Reads module settings from the vocabulary third-party settings.
 */
interface VocabularySettingsReaderInterface {

  /**
   * Returns max ancestor depth set for the vocabulary.
   *
   * @param string|\Drupal\taxonomy\VocabularyInterface $vocabulary
   *   The vocabulary or its ID.
   *
   * @return int|null
   *   Max ancestor depth or NULL if it's not set on the vocabulary.
   */
  public function getMaxAncestorDepth($vocabulary);

}
