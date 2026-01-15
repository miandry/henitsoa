<?php

namespace Drupal\config_export_import\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Component\Diff\Diff;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\Config\FileStorage;
/**
 * Class ConfigDiffForm.
 */
class ConfigDiffForm extends FormBase
{


//  /**
//   * {@inheritdoc}
//   */
    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'config_diff_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $helper = \Drupal::service('config_export_import.manager');
        $config_settings = \Drupal::config("config_export_import.settings");
        $root = $config_settings->get('root');
        $query= $helper->getParameter();
        $rows  = [];
        if($query['config_path']){
          $config_path =  $query['config_path'] ;
          $file_path = DRUPAL_ROOT.$root.$config_path;
          $config_name = basename($file_path, '.yml');
          $path_base = dirname($file_path) ;
          if (file_exists($file_path)) {
              $source = new FileStorage($path_base);
              $file_output= $source->read($config_name);         
              $config = \Drupal::config($config_name) ;

              $database_output = $config->getOriginal();               
              $diffFormatter = \Drupal::service('diff.formatter');

              $from = explode("\n", Yaml::encode( $file_output));
              $to = explode("\n", Yaml::encode($database_output));
              $diff = new Diff($from, $to);          
              $diffFormatter->show_header = FALSE;
              $rows = $diffFormatter->format($diff);
          }
        }
        // Add the CSS for the inline diff.
        $form['#attached']['library'][] = 'system/diff';
        $form['diff'] = [
          '#type' => 'table',
          '#attributes' => [
            'class' => ['diff'],
          ],
          '#header' => [
            ['data' => t('CONFIG FILE'), 'colspan' => '2'],
            ['data' => t('CONFIG DATABASE'), 'colspan' => '2'],
          ],
          '#rows' => $rows,
          '#empty' => $this->t('No diff found')
        ];

        return  $form ;
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
        $form_state->disableRedirect();

    }

    protected function getEditableConfigNames()
    {
        return [
            'config_export_import.config_diff_import',
        ];
    }

}
