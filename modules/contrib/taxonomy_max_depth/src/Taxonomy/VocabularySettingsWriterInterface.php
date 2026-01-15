<?php

namespace Drupal\taxonomy_max_depth\Taxonomy;

use Drupal\taxonomy\VocabularyInterface;

/**
 * Writes module settings to the vocabulary third-party settings.
 */
interface VocabularySettingsWriterInterface {

  /**
   * Sets max ancestor depth to the vocabulary.
   *
   * @param \Drupal\taxonomy\VocabularyInterface $vocabulary
   *   The vocabulary.
   * @param int|null $max_depth
   *   Max ancestor depth. Pass NULL to disable the limit.
   *
   * @return static
   *   Self.
   */
  public function setMaxAncestorDepth(
    VocabularyInterface $vocabulary,
    int $max_depth = NULL
  ): VocabularySettingsWriterInterface;

}
