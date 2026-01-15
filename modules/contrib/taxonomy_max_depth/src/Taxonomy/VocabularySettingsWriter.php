<?php

namespace Drupal\taxonomy_max_depth\Taxonomy;

use Drupal\taxonomy\VocabularyInterface;

/**
 * Writes module settings to the vocabulary third-party settings.
 */
class VocabularySettingsWriter implements VocabularySettingsWriterInterface {

  /**
   * The module name.
   */
  const MODULE_NAME = VocabularySettingsReader::MODULE_NAME;

  /**
   * The key of the max ancestor depth in the third-party settings.
   */
  const MAX_DEPTH_KEY = VocabularySettingsReader::MAX_DEPTH_KEY;

  /**
   * {@inheritdoc}
   */
  public function setMaxAncestorDepth(
    VocabularyInterface $vocabulary,
    int $max_depth = NULL
  ): VocabularySettingsWriterInterface {
    $module_name = static::MODULE_NAME;
    $key = static::MAX_DEPTH_KEY;

    if (isset($max_depth)) {
      $vocabulary->setThirdPartySetting($module_name, $key, $max_depth);
    }
    else {
      $vocabulary->unsetThirdPartySetting($module_name, $key);
    }

    return $this;
  }

}
