<?php

namespace Drupal\config_export_import\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Config\FileStorage;
/**
 * Class ConfigImportForm.
 */
class ConfigImportBatchForm extends FormBase {


//  /**
//   * {@inheritdoc}
//   */
    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'config_import_batch_form';
    }
 
    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
            $helper = \Drupal::service('config_export_import.manager');
            $query = $this->getRequest()->query->all();
            $current_config = [];
            $dependencies_current = [];
            $config_settings = \Drupal::config("config_export_import.settings") ;
            $root =  $config_settings->get('root');

              // *** Import  Files Multiple *** //
              if (isset($query['imports']) && is_array($query['imports']) && !empty($query['imports'])) {
                foreach($query['imports'] as $config_name){
         
                 }
                 foreach($query['imports'] as $config_name){
                    $file_path =  $helper->searchFileInDirectory($config_name,DRUPAL_ROOT .$root);
                    $dependencies_find = $helper->getAllDependencyByFolder(end($file_path));
                    if(!empty($dependencies_find)){
                        $dependencies_current = array_merge( $dependencies_current,   $dependencies_find );
                    }
                    $file = $helper->removeFullPath(end($file_path));
                    $current_config[$config_name] = [
                        "config_name" => $config_name,
                        "file" => $file,
                        'weight' => 1
                    ];
            
                 }
              }
            // *** Import Request File *** //
            if (isset($query['import_file'])
                && isset($query['import_file']['path'])
                && isset($query['import_file']['type'])
                && $query['import_file']['type'] == 'file'
            ) {
                $current_config = [];
                $dependencies_current = [];
                $config_path = DRUPAL_ROOT.$root;
                $file = $config_path.$query['import_file']['file'] ;
                $dependencies_current = $helper->getAllDependencyByFolder($file);
                if(!is_array($dependencies_current) && $dependencies_current == false){
                  $error = false ;
                }
                $config_name = basename($file,'.yml') ;
                $file = $helper->removeFullPath($file);
                $status = $helper->renderStatus($config_name,$file);
                $current_config[$config_name] = [
                    "config_name" => $status,
                    "file" => $file,
                    'weight' => 1
                ];
            }

            // *** Import Request Folder *** //
            $id = \Drupal::currentUser()->id();
            // $results = $helper->readDirectoryFile($config_path,'yml');
            // 2. Get the value somewhere else in the app.
            $tempstore = \Drupal::service('tempstore.private');
            // Get the store collection. 
            $store = $tempstore->get( $id.'_config_import');
            if($store->get('dependencies_current')){
                $dependencies_current = $store->get('dependencies_current');
            }
            if($store->get('current_config')){
                $current_config = $store->get('current_config');
                $status_new = false ;
            }else{
                $status_new = true ;
            }
            if (isset($query['import_dir'])
                && isset($query['import_dir']['path'])
                && isset($query['import_dir']['type'])
                && $query['import_dir']['type'] == 'dir'
            ) {
                if($status_new){
                // Get the key/value pair.
                    $form['actions']['preprocessImport'] = [
                        '#type' => 'submit',
                        '#value' => 'Execute preprocess Import',
                        '#submit' => [[$this, 'preprocessImport']],
                    ];
                    return   $form ;
               }
         

            }

            $form['help'] = [
                '#type' => 'item',
                '#markup' => '<h2>Drag to re-order the config elements according the dependency level <h2>'
            ];
            $config_all =  array_merge($dependencies_current, $current_config) ;
            $module_list =[] ;
            foreach($config_all as $config_checker){
                $path =  $config_checker['file'] ;
                $path_file = DRUPAL_ROOT.$root.$path;
                $module_item = $helper->getDependencyModuleInfo($path_file);
                $module_list = array_merge($module_list ,$module_item);
            }
            $status_module = false ;
            foreach($module_list as $key =>  $value)
            {
                if( $value["install"] == 1 ){
                    $module_list[$key]["install"] =  'TRUE' ;
                }else{
                    $status_module = true ;
                    $module_list[$key]["install"] =   'NEED TO ENABLE';
                }
              
            }
            $text = '';
            if(  $status_module){
                $text = '<span style=color:red> ( SOME MODULE NEED TO ENABLE ) </span>';
            }
            $header = ['Name','installed']; 
            $form['display_module'] = [
                '#type' => 'details',
                '#open' => FALSE,
                '#title' => $this->t('Module required'.$text )
            ];
            $form['display_module']['review_result'] = array('#type' => 'table', 
                                        '#header' => $header, 
                                        '#rows' =>  $module_list,
                                        '#empty' => $this->t('No Module found') 
                                    );
            //  ***** FORM **** //
        //    $form_state->setMethod('GET');
            $form['display_config'] = [
                '#type' => 'details',
                '#open' => FALSE,
                '#title' => $this->t('LIST OF CONFIG FILE')
            ];
            $group_class = 'group-order-weight';
            $form['display_config']['table'] = array(
                '#type' => 'table',
                '#header' => [  
                    'config_render' => t('Config'),
                    'enable' => t('Enabled'),
                    'file path' => t('Path location'),
                    'weight' => t('Weight')
                ],
                '#tableselect' => FALSE,
                '#tabledrag' => [
                  [
                    'action' => 'order',
                    'relationship' => 'sibling',
                    'group' => $group_class,
                  ]
                  ],
                '#empty' => $this->t('No config found')
            );
            $form['display'] = [
                '#type' => 'details',
                '#open' => FALSE,
                '#title' => $this->t('LIST OF DEPENDANCES')
            ];
           
            $form['display']['table_dep'] = array(
                '#type' => 'table',
                '#header' => [  
                    'config_render' => t('Dependencies'),
                    'enable' => t('Enabled'),
                    'file path' => t('Path location'),
                    'weight' => t('Weight')
                ],
                '#tableselect' => FALSE,
                '#tabledrag' => [
                  [
                    'action' => 'order',
                    'relationship' => 'sibling',
                    'group' => $group_class,
                  ]
                  ],
                '#empty' => $this->t('No dependencies found')
            );
            foreach ($current_config as $key => $config){
                $form['display_config']['table'][$key]['#attributes']['class'][] = 'draggable';
               // $form['display_config']['table'][$key]['#weight'] = $config['weight'];
                  // Label col.
                  $form['display_config']['table'][$key]['config_render'] = [
                    '#markup' =>  $config['config_name'],
                    '#validated' => true
                ];
                $form['display_config']['table'][$key]['check']= array(
                    '#type' => 'checkbox',
                    '#default_value' => 1,
                    '#validated' => true
                );
                // ID col.
                $form['display_config']['table'][$key]['file'] = [
                    '#plain_text' => $config['file'],
                    '#validated' => true
                ];
      
                $form['display_config']['table'][$key]['weight'] = [
                    '#type' => 'weight',
                    '#title' => $this->t('Weight'),
                    '#title_display' => 'invisible',
                    '#default_value' => -1,
                    '#attributes' => ['class' => [$group_class]],
                    '#validated' => true
                  ];

            }
            foreach ($dependencies_current as $key => $dep){
                $form['display']['table_dep'][$key]['#attributes']['class'][] = 'draggable';
              //  $form['display']['table_dep'][$key]['#weight'] = $dep['#weight'];
                  // Label col.
                  $form['display']['table_dep'][$key]['config_render'] = [
                    '#markup' =>  $dep['config_name'],
                    '#validated' => true
                ];
                $form['display']['table_dep'][$key]['check']= array(
                    '#type' => 'checkbox',
                    '#default_value' => 1,
                    '#attributes' => array('checked' => 'checked'),
                    '#validated' => true
                );
                // ID col.
                $form['display']['table_dep'][$key]['file'] = [
                    '#plain_text' => $dep['file'],
                    '#validated' => true
                ];
                $form['display']['table_dep'][$key]['weight'] = [
                    '#type' => 'weight',
                    '#title' => $this->t('Weight '),
                    '#title_display' => 'invisible',
                    '#default_value' => -1,
                    '#attributes' => ['class' => [$group_class]],
                    '#validated' => true
                  ];
            }

            // $form['dependencies'] = [
            //     '#type' => 'checkbox',
            //     '#default_value' => 1,
            //     '#title' => t('Import only if config dependencies is new in database'),
            //     '#attributes' => array('checked' => 'checked')
            // ];
            $options['new_only'] = t('Import only if config  is not exist');
            $options['replace'] = t('Replace if config  is exist already');
            $form['dep_condition'] = array(
              '#type' => 'select',
              '#options' => $options,
              '#default_value' =>  1 ,
              '#title' => $this->t('Import condition')
            );
            $form['actions'] = ['#type' => 'actions'];
            $form['actions']['submit'] = [
                '#type' => 'submit',
                '#value' => 'Process import config'
            ];
            $form['actions']['cancel'] = array(
                '#type' => 'link',
                '#title' => $this->t('BACK TO IMPORT LIST'),
                '#url' => $this->buildCancelLinkUrl(),
            );
            $form['dep'] = [
                '#type' => 'item',
                '#value' => $dependencies_current,
                '#validated' => true
            ];
            $form['config'] = [
                '#type' => 'item',
                '#value' => $current_config,
                '#validated' => true
            ];
          $form_state->setCached(FALSE);
           $form['#cache'] = ['max-age' => 0];
        return $form;
    }
    public function preprocessImport(array &$form, FormStateInterface $form_state)
    {
        $helper = \Drupal::service('config_export_import.manager');
        $query = $this->getRequest()->query->all();
        $config_settings = \Drupal::config("config_export_import.settings") ;
        $root =  $config_settings->get('root');

        $current_config = [];
        $dependencies_current = [];
        $path = $query['import_dir']['path'] ;
        $config_path = DRUPAL_ROOT.$root.$path;
        $results = $helper->readDirectoryFile($config_path,'yml');
        $id = \Drupal::currentUser()->id();
        // 2. Get the value somewhere else in the app.
        $tempstore = \Drupal::service('tempstore.private');
        // Get the store collection. 
        $store = $tempstore->get( $id.'_config_import');
        // Get the key/value pair.
     
        $batch = [
            'title' => $this->t('Preprocess Import Config ...'),
            'operations' => [],
            'init_message' => $this->t('Starting ..'),
            'progress_message' => $this->t('Processd @current out of @total.'),
            'error_message' => $this->t('An error occurred during processing.'),
            'finished' => 'Drupal\config_export_import\ConfigExportImportManager::importProcessFinishedCallback',
        ];
        if(!empty($results)){
                    foreach ($results as $key => $file){
                        $childs = $helper->getAllDependencyByFolder($file);
                        if(is_array($childs)){
                            if( $store->get('dependencies_current')){
                                $dependencies_current = $store->get('dependencies_current');
                            }                  
                            $dependencies_current = array_merge($childs , $dependencies_current);
                        }else{
                            $error = true ;
                        }
                        $input['user_id'] = $id ;
                        $input['action'] = 'dependency' ;
                        $input['dependencies_current'] = $dependencies_current ;
                        $batch['operations'][] =  [
                            '\Drupal\config_export_import\Form\ConfigImportBatchForm::preprocessImportExecute',
                            [$input]  
                        ];   
                    }
                    foreach ($results as $key => $file) {
                        $config_name = basename($file,'.yml') ;
                        if( $store->get('dependencies_current')){
                            $dependencies_current = $store->get('dependencies_current');
                        }  
                        $config_dep = array_keys($dependencies_current);
                        if(!in_array($config_name,$config_dep)){
                            $file = $helper->removeFullPath($file);
                            $status = $helper->renderStatus($config_name,$file);
                            $input['action'] = 'config' ;
                            if( $store->get('current_config')){
                                $current_config = $store->get('current_config');
                            }  
                            $current_config[$config_name] = [
                                "config_name" => $status,
                                "file" => $file,
                                'weight' => 1
                            ];
                            $input['current_config'] = $current_config ;
                            $batch['operations'][] =  [
                                '\Drupal\config_export_import\Form\ConfigImportBatchForm::preprocessImportExecute',
                                [$input]  
                            ];
                        }   

                       
                    }
          
        } 
        batch_set($batch);

    }
    public static function preprocessImportExecute($input){
        $id = $input['user_id'] ;
        $tempstore = \Drupal::service('tempstore.private');
        $store = $tempstore->get( $id.'_config_import');
        if($input['action'] == 'dependency'){
            $dependencies_current = $input['dependencies_current'] ;
            $store->set('dependencies_current', $dependencies_current); 
        }
        if($input['action'] == 'config'){
            $current_config = $input['current_config'] ;
            $store->set('current_config', $current_config); 
        }
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
        $helper = \Drupal::service('config_export_import.manager');
        $values = $form_state->getValues();
        $id = \Drupal::currentUser()->id();
        $tempstore = \Drupal::service('tempstore.private');
        $store = $tempstore->get( $id.'_config_import');
        $store->delete('dependencies_current');
        $store->delete('current_config');

        $batch = [
            'title' => $this->t('Import Config From yml...'),
            'operations' => [],
            'init_message' => $this->t('Starting ..'),
            'progress_message' => $this->t('Processd @current out of @total.'),
            'error_message' => $this->t('An error occurred during processing.'),
            'finished' => 'Drupal\config_export_import\ConfigExportImportManager::importFinishedCallback',
        ];
        $config_settings = \Drupal::config("config_export_import.settings");
        $root = $config_settings->get('root');
        $config_path = DRUPAL_ROOT . $root;
        if(!empty($values['dep'])){
            foreach ($values['table_dep'] as $key => $item){
                if(isset($item['check']) && $item['check']==1 ){
                    $file = $values['dep'][$key] ;
                    if($file['file'] != '--'){
                        $file = $file['file'] ;
                        $config_name = basename($file,'.yml');
                        $file = $config_path .$file ;
                        if($values['dep_condition'] && $values['dep_condition'] == 'new_only'){
                            if(!$helper->isExistDatabase( $config_name )){
                               $batch['operations'][] = [$helper->importConfig($file), []];
                            }
                        } 
                        if($values['dep_condition'] && $values['dep_condition'] == 'replace'){
                            $batch['operations'][] = [$helper->importConfig($file,true), []];
                        }
                    }
                }
            }
        }
        if(!empty($values['config'])){
            foreach ($values['table'] as $key => $item){
                if(isset($item['check']) && $item['check']==1 ){
                    $file = $values['config'][$key] ;
                    $file = $file['file'] ;
                    $file = $config_path .$file ;
                    $batch['operations'][] = [$helper->importConfig($file,true), []];
                }
            }
        }

        batch_set($batch);

    }

    protected function getEditableConfigNames()
    {
        return [
            'config_export_import.config_import_batch',
        ];
    }
    /**
     * Builds the cancel link url for the form.
     *
     * @return Url
     *   Cancel url
     */
    private function buildCancelLinkUrl()
    {
        return Url::fromRoute('config_export_import.manual_import');
    }

}
