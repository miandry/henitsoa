<?php


namespace Drupal\config_export_import\Form;


use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Config\FileStorage;
use Symfony\Component\HttpFoundation\RedirectResponse;
/**
 * Edit config variable form.
 */
class ConfigEditorFile extends FormBase {

  /**
   * {@inheritdoc}
   */
  private $conf = '' ;
  public function getFormId() {
    return 'config_export_import_manual_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $helper = \Drupal::service('config_export_import.manager');
    $config_settings = \Drupal::config("config_export_import.settings");
    $root = $config_settings->get('root');
    $query= \Drupal::request()->query->all();
    $output ='';
    $config_path = '';
    if(isset($query['config_path'])){
    $config_path =  $query['config_path'] ;
    $this->conf = $query['config_path'] ;
    
    //  return new RedirectResponse(Url::fromRoute('config_export_import.manual_import')->toString());
    }
  
      $file_path = DRUPAL_ROOT.$root.$config_path;
      $config_name = basename($file_path, '.yml');
      $path_base = dirname($file_path) ;
      if (file_exists($file_path)) {
        $source = new FileStorage($path_base);
        $file_output= $source->read($config_name);         
        $config = \Drupal::config($config_name) ;
        $database_output = $config->getOriginal();  
      }
   // $data
    $output ='';
    try {
      $output = Yaml::encode($database_output);
    }
    catch (InvalidDataTypeException $e) {
      \Drupal::messenger()->addMessage(t('Invalid data detected for @name : %error', array('@name' => $config_name, '%error' => $e->getMessage())), 'error');
      return;
    }

    $form['current'] = array(
      '#type' => 'details',
      '#title' => $this->t('File Current value for %variable', array('%variable' => $config_name)),
      '#attributes' => array('class' => array('container-inline')),
    );
    $form['current']['value'] = array(
      '#type' => 'item',
      '#markup' => dpr($output, TRUE),
    );

    $form['name'] = array(
      '#type' => 'item',
      '#value' => $config_path  
    );
    $form['new'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('File New value'),
      '#default_value' => $output,
      '#rows' => 24,
      '#required' => TRUE,
    );

    $form['actions'] = array('#type' => 'actions');
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('File Save'),
    );
    $form['actions']['cancel'] = array(
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => $this->buildCancelLinkUrl(),
    );
    

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $value = $form_state->getValue('new');
    // try to parse the new provided value
    try {
      $parsed_value = Yaml::decode($value);
      // Config::setData needs array for the new configuration and
      // a simple string is valid YAML for any reason.
      if (is_array($parsed_value)) {
        $form_state->setValue('parsed_value', $parsed_value);
      }
      else {
        $form_state->setErrorByName('new', $this->t('Invalid input'));
      }
    }
    catch (InvalidDataTypeException $e) {
      $form_state->setErrorByName('new', $this->t('Invalid input: %error', array('%error' => $e->getMessage())));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
 
    try {
      $config_path =  $values['name'];
      $helper = \Drupal::service('config_export_import.manager');
      $config_settings = \Drupal::config("config_export_import.settings");
      $root = $config_settings->get('root');
      $file_path = DRUPAL_ROOT.$root.$config_path;
      $config_name = basename($file_path, '.yml');
      $path_base = dirname($file_path) ;
  
      $output = $values['new'];
      $status = $helper->generateFileForce($path_base,$config_name.'.yml',$output);
      if($status){
          \Drupal::messenger()->addMessage($this->t('File Configuration variable %variable was successfully saved.', array('%variable' => $values['name'])));
      }
  
      $form_state->setRedirectUrl(Url::fromRoute('config_export_import.manual_import'));
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addMessage($e->getMessage(), 'error');
    }
  }

  /**
   * Builds the cancel link url for the form.
   *
   * @return Url
   *   Cancel url
   */
  private function buildCancelLinkUrl() {
    $query = $this->getRequest()->query;

    if ($query->has('destination')) {
      $options = UrlHelper::parse($query->get('destination'));
      $url = Url::fromUri('internal:/' . $options['path'], $options);
    }
    else {
      $url = Url::fromRoute('config_export_import.manual_import');
    }

    return $url;
  }

}
