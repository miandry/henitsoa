<?php

namespace Drupal\templating\Form;


use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Edit config variable form.
 */
class ConfigTemplateSetting extends FormBase
{

    protected $step = -1;
    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'config_template_settings_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $config_name = '')
    {


      $config_settings = \Drupal::config("template_inline.settings") ;
      $services = \Drupal::service('templating.manager');
      $themes = $services->getThemeList();
      $defaultThemeName = $config_settings->get('theme');
      $theme_options = [];
      foreach ($themes as $theme) {
        $theme_options[$theme] = $theme;
      }
      $form['theme']= [
        '#type' => 'checkboxes',
        '#title' => t('Themes'),
        '#options' => $theme_options,
        '#default_value' => $defaultThemeName,
      ];
//        $form['enabled_lib'] = array(
//            '#type' => 'checkbox',
//            '#title' => t('Enable Library'),
//            '#default_value' =>  ($config_settings->get('enabled_lib'))? $config_settings->get('enabled_lib'): false
//        );
        $form['enabled'] = array(
            '#type' => 'checkbox',
            '#title' => t('Disable template inline'),
            '#default_value' =>  ($config_settings->get('disable'))? $config_settings->get('disable'): false
        );
        $form['actions'] = ['#type' => 'actions'];
        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Save'),
        ];
        $form['actions']['submit_reset'] = [
            '#type' => 'submit',
            '#value' => $this->t('Rebuild Css'),
            '#submit' => ['::submitButton2'],
        ];

        $form['actions']['cancel'] = array(
            '#type' => 'link',
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
            $url = Url::fromUri('internal:/' . $options['path'], $options);
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
        $values['disable'] = isset($values['disable'])? $values['disable'] : 0 ;
        $this->configFactory()->getEditable('template_inline.settings')
                        ->set('disable', $values['disable'])
                  //      ->set('content_lib', $content_lib )
                       //->set('enabled_lib', $values['enabled_lib'] )
                        ->set('theme', $values['theme'] )
                        ->save();
    }
     /**
   * Submit handler for Submit Button 1.
   */
  public function submitButton2(array &$form, FormStateInterface $form_state) {

    $config = $this->configFactory()->getEditable('template_inline.settings');
    $current_css = $config->get('asset_css');
     // Check if the 'asset_css' value exists before attempting to delete it.
     if ($current_css !== NULL) {
         $config->clear('asset_css')->save();
         \Drupal::messenger()->addStatus($this->t('Css Template Cleaned'));
     }

  }

}
