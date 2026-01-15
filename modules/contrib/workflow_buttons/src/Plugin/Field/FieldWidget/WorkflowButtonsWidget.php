<?php

namespace Drupal\workflow_buttons\Plugin\Field\FieldWidget;

use Drupal\content_moderation\ModerationInformation;
use Drupal\content_moderation\StateTransitionValidation;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsSelectWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'moderation_state_default' widget.
 *
 * @FieldWidget(
 *   id = "workflow_buttons",
 *   label = @Translation("Workflow buttons"),
 *   field_types = {
 *     "string"
 *   }
 * )
 */
class WorkflowButtonsWidget extends OptionsSelectWidget implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;
  /**
   * Current user service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformation
   */
  protected $moderationInformation;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Moderation state transition validation service.
   *
   * @var \Drupal\content_moderation\StateTransitionValidation
   */
  protected $validator;

  /**
   * Constructs a new ModerationStateWidget object.
   *
   * @param string $plugin_id
   *   Plugin id.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   Field definition.
   * @param array $settings
   *   Field settings.
   * @param array $third_party_settings
   *   Third party settings.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\content_moderation\ModerationInformation $moderation_information
   *   Moderation information service.
   * @param \Drupal\content_moderation\StateTransitionValidation $validator
   *   Moderation state transition validation service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager, ModerationInformation $moderation_information, StateTransitionValidation $validator) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->moderationInformation = $moderation_information;
    $this->validator = $validator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('content_moderation.moderation_information'),
      $container->get('content_moderation.state_transition_validation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'show_current_state' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element['show_current_state'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show current state?'),
      '#description' => $this->t('Select if you want to show the current moderation state in the meta section.'),
      '#default_value' => $this->getSetting('show_current_state'),
      '#required' => FALSE,
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->t('Show current moderation state in meta: @value', ['@value' => $this->getSetting('show_current_state') ? 'Yes' : 'No']);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function form(FieldItemListInterface $items, array &$form, FormStateInterface $form_state, $get_delta = NULL) {
    $entity = $items->getEntity();
    if (!$this->moderationInformation->isModeratedEntity($entity)) {
      return [];
    }
    return parent::form($items, $form, $form_state, $get_delta);
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $items->getEntity();

    $workflow = $this->moderationInformation->getWorkflowForEntity($entity);
    /** @var \Drupal\content_moderation\ContentModerationState $default */
    $default = $items->get($delta)->value ? $workflow->getTypePlugin()->getState($items->get($delta)->value) : $workflow->getTypePlugin()->getInitialState($entity);

    /** @var \Drupal\workflows\Transition[] $transitions */
    $transitions = $this->validator->getValidTransitions($entity, $this->currentUser);
    if (!$transitions) {
      return $element;
    }

    $target_states = [];
    $transition_data = [];
    foreach ($transitions as $transition) {
      $target_states[$transition->to()->id()] = $transition->label();
      $transition_data[$transition->to()->id()] = [
        'transition_machine_name' => $transition->id(),
      ];
    }
    $tempstore = \Drupal::service('tempstore.private')->get('workflow_buttons');
    $form_id = $form_state->getBuildInfo()['form_id'];
    $tempstore->set($form_id . '_transition_data', $transition_data);

    $element += [
      '#access' => FALSE,
      '#type' => 'select',
      '#options' => $target_states,
      '#default_value' => $default->id(),
      '#published' => $default->isPublishedState(),
      '#key_column' => $this->column,
    ];
    $element['#element_validate'][] = [get_class($this), 'validateElement'];

    // Following dropbutton's approach, we'll break out our element into buttons
    // in a separate process, which should be called more alter than process?
    $element['#process'][] = [get_called_class(), 'processActions'];

    if ($this->getSetting('show_current_state')) {
      $element['#show_current_state'] = $default->label();
    }
    return $element;
  }

  /**
   * Entity builder updating the node moderation state with the submitted value.
   *
   * @param string $entity_type_id
   *   The entity type identifier.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity updated with the submitted values.
   * @param array $form
   *   The complete form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function updateStatus($entity_type_id, ContentEntityInterface $entity, array $form, FormStateInterface $form_state) {
    $element = $form_state->getTriggeringElement();
    if (isset($element['#moderation_state'])) {
      // Trigger hook_workflow_buttons_alter().
      // Allow modules to alter workflow state before save. Useful when custom
      // buttons have been added via hook_field_widget_WIDGET_TYPE_form_alter().
      \Drupal::service('module_handler')->alter('workflow_buttons_state', $element['#moderation_state'], $entity, $form_state);

      $entity->moderation_state->value = $element['#moderation_state'];
    }
  }

  /**
   * Process callback to alter action buttons.
   */
  public static function processActions($element, FormStateInterface $form_state, array &$form) {
    // This function is called twice for every time AJAX is used to add another
    // entity reference, such as "Add another organization or location".
    // We'll steal most of the button configuration from the default submit
    // button. However, NodeForm also hides that button for admins (as it adds
    // its own, too), so we have to restore it.
    $default_button = $form['actions']['submit'];
    $default_button['#access'] = TRUE;

    // Add a custom button for each transition we're allowing. The #dropbutton
    // property tells FAPI to cluster them all together into a single widget.
    $options = $element['#options'];

    // We pass this as tempstore private data because we don't have easy access
    // to $this->validator->getValidTransitions($entity, $this->currentUser);
    // from our static method here, because $form_storage temporary data turns
    // out to be unstable, at least until a form is actually saved (wiped out by
    // AJAX requests), and this will be stable per-user per-content type anyway.
    $tempstore = \Drupal::service('tempstore.private')->get('workflow_buttons');
    $form_id = $form_state->getBuildInfo()['form_id'];
    $transition_data = $tempstore->get($form_id . '_transition_data');
    if (!$transition_data) {
      $form_id = $form_state->getBuildInfo()['form_id'];
      \Drupal::logger('workflow_buttons')->alert('Something weird is happening, there is no transition data for form @id', ['@id' => $form_id]);
      return;
    }

    // $entity = $form_state->getFormObject()->getEntity();
    // $translatable = !$entity->isNew() && $entity->isTranslatable();
    // Would seem the label is either translatable and translated, or it's not?
    $weight = -100;
    foreach ($options as $id => $label) {
      $button = [
        '#moderation_state' => $id,
        '#weight' => $weight + 10,
      ];

      if (isset($transition_data[$id])) {
        $transition_machine_name = $transition_data[$id]['transition_machine_name'];
      }
      else {
        \Drupal::logger('workflow_buttons')->alert('There is no transition data for @id', ['@id' => $id]);
      }

      // Transition ID as a class.
      $button['#attributes'] = ['class' => ["workflow-buttons-" . $transition_machine_name]];

      // Only first button and any Publish button keep 'primary' button type.
      // @todo Make this so it is not hardcoded on transition ID (machine name).
      if (($weight !== -100) && ($transition_machine_name !== 'publish')) {
        $button['#button_type'] = '';
      }
      elseif ($transition_machine_name === 'delete') {
        // Hardcoding elements of the delete-to-Trash workflow here.
        $button['#button_type'] = 'danger';
        // This styles the delete button much like the default delete link, at
        // least in Claro.
        // See https://www.drupal.org/project/workflow_buttons/issues/3092099
        $button['#attributes'] = [
          'class' => [
            'submit-trash',
            'action-link--danger',
          ],
        ];
        $button['#prefix'] = '<span class="submit-trash action-link action-link--danger action-link--icon-trash"></span>';
        $button['#attached']['library'][] = 'workflow_buttons/delete-button';

        // To avoid confusing admins who can see both delete buttons, update
        // the delete link to be clear that it's the real, permanent delete.
        $form['actions']['delete']['#title'] = t('Permanently delete');
      }

      $button['#value'] = $label;

      $form['actions']['moderation_state_' . $id] = $button + $default_button;
    }

    // Hide the Published checkbox.
    unset($form['status']);

    // Hide the default buttons, including the specialty ones added by
    // NodeForm.
    foreach (['publish', 'unpublish', 'submit'] as $key) {
      $form['actions'][$key]['#access'] = FALSE;
    }

    // Set a callback to transform the button selection back into a field
    // widget, so that it will get saved properly.
    $form['#entity_builders']['update_moderation_state'] = [
      get_called_class(),
      'updateStatus',
    ];

    // Add actions to just the bottom of the form or to both the top and the
    // bottom. If using Gin Admin Theme, the buttons appear by default in the
    // sticky header area, so no matter this module's settings the buttons
    // should *not* be added to the top of the form.
    if (isset($form['gin_actions'])) {
      $form['gin_actions']['actions'] = $form['actions'];
      unset($form['actions_top']);
    }
    $config = \Drupal::config('workflow_buttons.settings');
    if ($config->get('display.top_buttons', TRUE)) {
      if (!isset($form['gin_actions'])) {
        $form['actions_top'] = $form['actions'];
        $form['actions_top']['#weight'] = -900;
      }
      else {
        $form['actions_bottom'] = $form['actions'];
        $form['actions_bottom']['#weight'] = 900;
        if (isset($form['actions_bottom']['gin_sidebar_toggle'])) {
          unset($form['actions_bottom']['gin_sidebar_toggle']);
        }
      }
    }

    // Only include one set of buttons if on a node view or revision route.
    $routes_list = [
      'entity.node.canonical',
      'entity.node.revision',
      'entity.node.latest_version',
    ];
    if (in_array(\Drupal::routeMatch()->getRouteName(), $routes_list)) {
      unset($form['actions_top']);
    }

    // Show the current state in meta section if enabled.
    if (!empty($element['#show_current_state']) && !empty($form['meta'])) {
      $form['meta']['current_moderation_state'] = [
        '#type' => 'item',
        '#title' => t('Moderation State'),
        '#markup' => t('@current_moderation_state', ['@current_moderation_state' => $element['#show_current_state']]),
        '#wrapper_attributes' => [
          'class' => ['container-inline'],
        ],
      ];
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getName() === 'moderation_state';
  }

}
