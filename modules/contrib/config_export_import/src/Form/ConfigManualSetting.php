<?php

namespace Drupal\config_export_import\Form;


use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Edit config variable form.
 */
class ConfigManualSetting extends FormBase
{

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'config_manual_settings_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, $config_name = '')
    {
        $config_settings = \Drupal::config("config_export_import.settings") ;
        $form['root'] = [
            '#type' => 'textfield',
            '#title' => $this->t('New Root location: '),
            '#attributes' => ['name' => 'root'],          
            '#description' => 'For example : /sites/default/files/config'
        ];
        $roots = ($config_settings->get('root_list'))? $config_settings->get('root_list') : ['-none-'] ;
        $form['root_list'] = [
            '#type' => 'radios',
            '#required' => false ,
            '#title' => $this->t('Existing Root list Location'),
            '#options' =>  $roots,
            '#default_value' => ($config_settings->get('root'))?$config_settings->get('root'):'-none-' 
        ];

        // $form['import'] = array(
        //     '#type' => 'checkbox',
        //     '#title' => t('Import only if new config in database'),
        //     '#default_value' =>  ($config_settings->get('import'))? $config_settings->get('import'): false ,
        // );
        // $form['export'] = array(
        //     '#type' => 'checkbox',
        //     '#title' => t('Export only if new file config in folder'),
        //     '#default_value' =>  ($config_settings->get('export'))? $config_settings->get('export'): false ,
        // );
        $form['actions'] = ['#type' => 'actions'];
        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Save'),
        ];
        $form['actions']['delete'] = [
            '#type' => 'submit',
            '#value' => 'Delete Config Archive All',
            '#submit' => [[$this, 'deleteProcess']],
        ];
        return $form;

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
        $root_list = [];
        if(isset($values['root']) && $values['root'] !=''){
            $path = DRUPAL_ROOT . $values['root'] ;
            if (!is_dir( $path )){
                $fileSystem = \Drupal::service('file_system');
                if ( $fileSystem->mkdir($path, 0777, TRUE) === FALSE ) {
                    \Drupal::messenger()->addMessage('Failed to create directory' .  $values['root']);
                    return FALSE;
                }
            }
            $config_settings = \Drupal::config("config_export_import.settings") ;
            if($config_settings->get('root_list')){
                $root_list  = $config_settings->get('root_list') ;
            }
            $root_list[$values['root']]= $values['root'];
            $this->configFactory()->getEditable('config_export_import.settings')
                            ->set('root', $values['root'])
                            ->set('root_list',$root_list)
                            ->save();
        }else{
            $config_settings = \Drupal::config("config_export_import.settings") ;
            if($config_settings->get('root_list')){
                $root_list  = $config_settings->get('root_list') ;
            }
            $this->configFactory()->getEditable('config_export_import.settings')
              ->set('root', $values['root_list'])
              ->set('root_list',$root_list)
              ->save(); 
        }
  
        \Drupal::messenger()->addMessage(t('You can start to export or import  our config now !!'));
    }
    public function deleteProcess(array &$form, FormStateInterface $form_state)
    {
        
        $helper = \Drupal::service('config_export_import.manager');
        $file_default_scheme =  \Drupal::config('system.file')->get('default_scheme') ;
        $zip_uri = $file_default_scheme . '://compress'; 
    
        $dir = \Drupal::service('file_system')->realpath($zip_uri);
        if (!is_dir($dir)) {return false ;}
        $helper->delete_directory($dir);
        $message = t('Archive deleted successfully');
        \Drupal::messenger()->addMessage($message);
    }
}
