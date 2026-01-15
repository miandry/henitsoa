<?php

namespace Drupal\taxonomy_max_depth\Form;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\taxonomy_max_depth\Taxonomy\VocabularySettingsReaderInterface;

/**
 * Provides base class for the form alterer classes.
 */
abstract class FormAltererBase implements FormAltererInterface {

  use DependencySerializationTrait;

  /**
   * The module name.
   */
  const MODULE_NAME = 'taxonomy_max_depth';

  /**
   * The vocabulary settings reader.
   *
   * @var \Drupal\taxonomy_max_depth\Taxonomy\VocabularySettingsReaderInterface
   */
  protected $settingsReader;

  /**
   * A constructor.
   *
   * @param \Drupal\taxonomy_max_depth\Taxonomy\VocabularySettingsReaderInterface $settings_reader
   *   The vocabulary settings reader.
   */
  public function __construct(
    VocabularySettingsReaderInterface $settings_reader
  ) {
    $this->settingsReader = $settings_reader;
  }

  /**
   * Prepares the method name to be passed as a form callback.
   *
   * @param string $method_name
   *   The method name on this class.
   *
   * @return callable
   *   The prepared method.
   */
  protected function prepareFormCallback(string $method_name): callable {
    return [$this, $method_name];
  }

}
