<?php

namespace Drupal\shs_select2\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\shs\Plugin\Field\FieldWidget\OptionsShsWidget;

/**
 * Plugin implementation of the 'options_shs_select2' widget.
 *
 * @FieldWidget(
 *   id = "options_shs_select2",
 *   label = @Translation("Simple hierarchical select: Select2"),
 *   field_types = {
 *     "entity_reference"
 *   },
 *   multiple_values = TRUE
 * )
 */
class OptionsShsSelect2Widget extends OptionsShsWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['#attached']['library'][] = 'shs_select2/shs_select2.form';

    return $element;
  }

}
