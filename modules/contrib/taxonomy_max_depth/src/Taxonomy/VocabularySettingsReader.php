<?php

namespace Drupal\taxonomy_max_depth\Taxonomy;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\VocabularyInterface;
use Drupal\taxonomy_max_depth\Form\FormAltererBase;

/**
 * Reads module settings from the vocabulary third-party settings.
 */
class VocabularySettingsReader implements VocabularySettingsReaderInterface {

  /**
   * The module name for third-party settings on the vocabulary.
   */
  const MODULE_NAME = FormAltererBase::MODULE_NAME;

  /**
   * The key of the max ancestor depth in the third-party settings.
   */
  const MAX_DEPTH_KEY = 'max_depth';

  /**
   * The vocabulary entity type.
   */
  const VOCABULARY_ENTITY_TYPE = 'taxonomy_vocabulary';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * A constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Loads the vocabulary.
   *
   * @param string $vid
   *   The vocabulary ID.
   *
   * @return \Drupal\taxonomy\VocabularyInterface
   *   The loaded vocabulary.
   */
  protected function loadVocabulary(string $vid): VocabularyInterface {
    /** @var \Drupal\taxonomy\VocabularyInterface $vocabulary */
    $vocabulary = $this->entityTypeManager
      ->getStorage(static::VOCABULARY_ENTITY_TYPE)
      ->load($vid);
    if (!$vocabulary) {
      throw new \InvalidArgumentException(sprintf(
        'Vocabulary %s not found.',
        $vid
      ));
    }

    return $vocabulary;
  }

  /**
   * {@inheritdoc}
   */
  public function getMaxAncestorDepth($vocabulary) {
    if (!$vocabulary instanceof VocabularyInterface) {
      $vocabulary = $this->loadVocabulary($vocabulary);
    }

    $settings = $vocabulary->getThirdPartySettings(static::MODULE_NAME);
    return $settings[static::MAX_DEPTH_KEY] ?? NULL;
  }

}
