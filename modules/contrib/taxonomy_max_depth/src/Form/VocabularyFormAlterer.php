<?php

namespace Drupal\taxonomy_max_depth\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\taxonomy\VocabularyInterface;
use Drupal\taxonomy_max_depth\Taxonomy\VocabularySettingsReaderInterface;
use Drupal\taxonomy_max_depth\Taxonomy\VocabularySettingsWriterInterface;

/**
 * Alters the vocabulary entity form to add module settings.
 */
class VocabularyFormAlterer extends FormAltererBase {

  use StringTranslationTrait;

  /**
   * The vocabulary settings writer.
   *
   * @var \Drupal\taxonomy_max_depth\Taxonomy\VocabularySettingsWriterInterface
   */
  protected $settingsWriter;

  /**
   * A constructor.
   *
   * @param \Drupal\taxonomy_max_depth\Taxonomy\VocabularySettingsReaderInterface $settings_reader
   *   The vocabulary settings reader.
   * @param \Drupal\taxonomy_max_depth\Taxonomy\VocabularySettingsWriterInterface $settings_writer
   *   The vocabulary settings writer.
   */
  public function __construct(
    VocabularySettingsReaderInterface $settings_reader,
    VocabularySettingsWriterInterface $settings_writer
  ) {
    parent::__construct($settings_reader);

    $this->settingsWriter = $settings_writer;
  }

  /**
   * {@inheritdoc}
   */
  public function alter(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\EntityFormInterface $form_object */
    /** @var \Drupal\taxonomy\VocabularyInterface $vocabulary */
    $form_object = $form_state->getFormObject();
    $vocabulary = $form_object->getEntity();
    $max_depth = $this->settingsReader->getMaxAncestorDepth($vocabulary);

    $options = [
      0 => $this->t('0 (no hierarchy)'),
    ];
    $option_values = range(1, 10);
    $options = $options + array_combine($option_values, $option_values);

    $form['taxonomy_max_depth'] = [
      '#type' => 'select',
      '#title' => $this->t('Maximum ancestor depth'),
      '#description' => $this->t('The maximum number of ancestor levels the term could have.'),
      '#options' => $options,
      '#empty_option' => $this->t('Unlimited'),
      '#empty_value' => '',
      '#default_value' => $max_depth ?? '',
    ];

    $form['#entity_builders'][] = $this->prepareFormCallback('buildEntity');
  }

  /**
   * Entity builder that saves the module settings to the vocabulary config.
   *
   * @param string $entity_type
   *   The entity type.
   * @param \Drupal\taxonomy\VocabularyInterface $vocabulary
   *   The vocabulary entity.
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function buildEntity(
    string $entity_type,
    VocabularyInterface $vocabulary,
    array $form,
    FormStateInterface $form_state
  ) {
    $max_depth = $form_state->getValue('taxonomy_max_depth');
    $max_depth = $max_depth === '' ? NULL : $max_depth;
    $this->settingsWriter->setMaxAncestorDepth($vocabulary, $max_depth);
  }

}
