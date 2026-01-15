<?php

namespace Drupal\templating\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Edit config variable form.
 */
class ConfigTemplateCreate extends FormBase
{

    protected $step = -1;
    protected $element = [];

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'config_template_create_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $config_name = '')
    {

        if ($this->step == -1) {
            $form['template_index'] = [
                '#type' => 'select',
                '#title' => $this->t('Template'),
                '#options' => [
                    0 => 'Block content',
                    1 => 'Node',
                    2 => 'View',
                    3 => 'Custom',
                    4 =>'User',
                    5 =>'Form',
                    6 => 'Field',
                    7 => 'Page',
                ],
                '#required' => true,
            ];
        }
        if ($this->step == 4) {
          $form = TemplatingForm::userForm($form);
        }
        if ($this->step == 1) {
            $form = TemplatingForm::nodeForm($form);
        }
        //views
        if ($this->step == 2) {

            $form = TemplatingForm::viewForm1($form);
            // $form = TemplatingForm::pageForm($form);
        }
        if ($this->step == -3) {
            $form = TemplatingForm::viewForm2($form, $this->elements);
            // $form = TemplatingForm::pageForm($form);
        }
        if ($this->step == 3) {
            $form = TemplatingForm::customForm($form);
        }
        if ($this->step == 6) {
            $form = TemplatingForm::fieldForm($form);
        }
        if ($this->step == 0) {
            $form = TemplatingForm::blockForm($form);
        }
        if ($this->step == 5) {
            $form = TemplatingForm::formForm($form);
        }
        if ($this->step == 7) {
            $form = TemplatingForm::pageForm($form);
        }
        $form['actions'] = ['#type' => 'actions'];
        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Save'),
        ];
        $form['actions']['cancel'] = array(
            '#type' => 'link',
            '#attributes' => [
                'class' => ['button', 'button--danger'],
            ],
            '#title' => $this->t('Back to Template list'),
            '#url' => $this->buildCancelLinkUrl(),
        );
        return $form;

    }

    /**
     * Builds the cancel link url for the form.
     *
     * @return Url
     *   Cancel url
     */
    private function buildCancelLinkUrl()
    {
        $query = $this->getRequest()->query;
        if ($query->has('destination')) {
            $options = UrlHelper::parse($query->get('destination'));
            $url = Url::fromUri('internal:' . $options['path'], $options);
        } else {
            $url = Url::fromRoute('view.templating.page_1');
        }

        return $url;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $values = $form_state->getValues();

        if ($this->step == -1) {
            $form_state->setRebuild();
            $this->step = $values['template_index'];
        } else {

            $theme = isset($values["theme"]) ? $values["theme"] : "";
            $config_name = null;
            $config_name_init = null;

            //views
            if (isset($values['view_name'])) {
                $form_state->setRebuild();
                $this->step = -3;
                $this->elements['view_name'] = $values['view_name'];

            }
            if (isset($values['view_display'])) {
                $configs = TemplatingForm::viewFormSubmit($values);
                $config_name = $configs['name'];
                $config_name_init = $configs['entity_type'];
                $bundle = $configs['bundle'];
                $values['mode_view'] =  $configs['mode_view'];
            }
          // template user
          if (isset($values['mode_view_user'])) {
            $configs = TemplatingForm::userFormSubmit($values);
            if (isset($configs['name'])) {
              $config_name = $configs['name'];
              $config_name_init = $configs['entity_type'];
              $bundle = $configs['bundle'];
            }
          }

              // template block_content
            if (isset($values['form_entity'])) {
                $configs = TemplatingForm::formFormSubmit($values);
                if (isset($configs['name'])) {
                    $config_name = $configs['name'];
                    $config_name_init = $configs['entity_type'];
                    $bundle = $configs['bundle'];
                }
            }

            // template block_content
            if (isset($values['blocktype'])) {
                $configs = TemplatingForm::blockFormSubmit($values);
                if (isset($configs['name'])) {
                    $config_name = $configs['name'];
                    $config_name_init = $configs['entity_type'];
                    $bundle = $configs['bundle'];
                }
            }
            // template block_content
            if (isset($values['bundle'])) {
                $configs = TemplatingForm::nodeFormSubmit($values);
                if (isset($configs['name'])) {
                    $config_name = $configs['name'];
                    $config_name_init = $configs['entity_type'];
                    $bundle = $configs['bundle'];
                }
            }
            // template block_content
            if (isset($values['page_name'])) {
                $configs = TemplatingForm::pageFormSubmit($values);
                if (isset($configs['name'])) {
                    $config_name = $configs['name'];
                    $config_name_init = $configs['entity_type'];
                    $bundle = $configs['bundle'];
                }
            }
            // template field
            if (isset($values['field_name'])) {
                $configs = TemplatingForm::fieldFormSubmit($values);
                if (isset($configs['name'])) {
                    $config_name = $configs['name'];
                    $config_name_init = $configs['entity_type'];
                    $bundle = $configs['bundle'];
                }
            }
          if (isset($values['custom'])) {
            $configs = TemplatingForm::customFormSubmit($values);
            if (isset($configs['name'])) {
              $config_name = $configs['name'];
              $config_name_init = $configs['entity_type'];
              $bundle = "custom";
            }
          }


          // saving section
            if ($config_name && $config_name_init) {
                // $names = $this->configFactory()->listAll("template.");
                $services = \Drupal::service('templating.manager');
                $config_name = $services->formatName($config_name);
                if ($services->is_template_exist($config_name)) {
                    $this->messenger()->addError($this->t('Template name ' . $config_name . ' exist already '));
                    $response = new RedirectResponse("/admin/templating", 302);
                    $response->send();
                    return;
                }
                $entity_type = $config_name_init;
                $uuid = \Drupal::service('uuid');
                $uuid_key = $uuid->generate();
                $array = array(
                    "title" => $config_name,
                    "type" => "templating",
                    "uuid" => $uuid_key,
                );
                $array['field_templating_theme'] = $theme;
                $array['field_templating_bundle'] = $bundle;
                $array['field_templating_entity_type'] = $entity_type;

                if (isset($values["mode_view"])) {
                    $array['field_templating_mode_view'] = $values["mode_view"];
                }
                $object = \Drupal::entityTypeManager()->getStorage('node')->create($array);
                $object->save();

                if (is_object($object)) {
                    $nid = $object->id();
                    $path = '/node' . '/' . $nid . '/edit?destination=/admin/templating';
                    $response = new RedirectResponse($path, 302);
                    $response->send();
                    return;
                } else {
                    $this->messenger()->addError($this->t('Template  ' . $config_name . ' failed to create '));
                }

            } else {
                $this->messenger()->addError($this->t('Template name ' . $config_name . ' not create '));
            }
        }
    }

}
