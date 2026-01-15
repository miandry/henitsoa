<?php

namespace Drupal\config_export_import\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
/**
 * Class ConfigImportForm.
 */
class ConfigImportForm extends FormBase
{


//  /**
//   * {@inheritdoc}
//   */
    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'config_import_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $id = \Drupal::currentUser()->id();
        $tempstore = \Drupal::service('tempstore.private');
        $store = $tempstore->get( $id.'_config_import');
        $store->delete('dependencies_current');
        $store->delete('current_config');


        $helper = \Drupal::service('config_export_import.manager');
        $config_settings = \Drupal::config("config_export_import.settings");
        $root = $config_settings->get('root');
        $query= $helper->getParameter();
        if(isset($query['delete_file'])){
            $delete = $query['delete_file'];
            $helper->deleteLocal($delete,$root) ;
            return new RedirectResponse(Url::fromRoute('config_export_import.manual_import')->toString());
        }
        if(isset($query['delete_dir'])){
            $delete = $query['delete_dir'];
            $helper->deleteFolder($delete,$root) ;
            return new RedirectResponse(Url::fromRoute('config_export_import.manual_import')->toString());
        }

        if ($root) {
            //  ***** FORM **** //
            if(!isset($query['type'])){
                $query['type'] = 'dir';
            }
            $options = ['all' => 'All','dir' => 'Folder' ,'file' => 'File'];
            $form['type'] = [
                '#title' => t('Type'),
                '#attributes' => ['name' => 'type'],
                '#type' => 'select',
                '#default_value' => isset($query['type']) ? $query['type'] : 'All',
                '#options' => $options,
            ];
            $form['key'] = [
                '#type' => 'textfield',
                '#title' => $this->t('Config search by key'),
                '#attributes' => ['name' => 'key'],
                '#default_value' => isset($query['key']) ? $query['key'] : '',
                '#description' => 'Make empty to get all'
            ];
            $form['path'] = [
                '#type' => 'textfield',
                '#title' => $this->t('Config path location'),
                '#attributes' => ['name' => 'path'],
                '#description' => 'For example : /block',
                '#default_value' => isset($query['path']) ? $query['path'] : ''
            ];
            $form['actions'] = ['#type' => 'actions'];
            $form['actions']['submit'] = [
                '#type' => 'submit',
                '#value' => 'Search',

            ];
            $form['actions']['reset'] = array(
                '#type' => 'submit',
                '#value' => t('Reset'),
                '#submit' => [[$this, 'importReset']],
              );
              $form['actions']['import'] = [
                '#type' => 'submit',
                '#value' => 'Import All Files',
                '#submit' => [[$this, 'importProcess']],
            ];
            //  ***** TABLE **** //
            $output = [];
            $group_class = 'group-order-weight';
            $header = [
                'enable' => t('Enabled'),
                'number' => t('Number'),
                'type' => t('Type'),
                'root' => t('Folder'),
                'config_name' => t('Config name'),
                'actions' => t('Actions'),
                'weight' => t('Weight')
            ];
            $filter = null;
            $config_path = DRUPAL_ROOT . $root;
            if (isset($query['path']) && $query['path'] != '') {
                $path = $query['path'];
                $config_path = DRUPAL_ROOT . $root . $path;
            }
            $results = $helper->readDirectory($config_path, 'yml',['level'=>1]);
            $key = 0;
            foreach ($results as $result) {
                $output = $this->_displayImport($output, $key, $query, $result, $root);
                $key++;
            }
            $form['table'] = array(
                '#type' => 'table',
                '#weight' => 999,
                '#header' => $header,
                '#tableselect' => FALSE,
                '#tabledrag' => [
                    [
                        'action' => 'order',
                        'relationship' => 'sibling',
                        'group' => $group_class,
                    ]
                ],
                '#empty' => $this->t('No items found')
            );
            $i = 0 ;
            foreach($output as $key_number => $item){
   
                $key = ($item['config_name'] != ' -- ') ? $item['config_name'] : $i;
                $i ++ ;
                $form['table'][$key]['#attributes']['class'][] = 'draggable';
                $form['table'][$key]['#weight'] = 1;
                    $form['table'][$key]['check'] = [
                        '#type' => 'checkbox',
                        '#default_value' => 1 ,
                        '#attributes' => array('checked' => 'checked')
                    ];

                $form['table'][$key]['number'] = [
                    '#plain_text' => $item['id'],
                ];
                $form['table'][$key]['type'] = [
                    '#plain_text' => $item['type'],
                ];
                $form['table'][$key]['root'] = [
                    '#plain_text' => $item['root'],
                ];
                $form['table'][$key]['config_render'] = [
                    '#markup' => $item['config_render'],
                ];   
                $form['table'][$key]['operation'] = $item['operation'];
                $form['table'][$key]['weight'] = [
                    '#type' => 'weight',
                    '#title' => $this->t('Weight'),
                    '#title_display' => 'invisible',
                    '#default_value' =>1,
                    '#attributes' => ['class' => [$group_class]],
                ];
            }
          
        } else {
            $form['help'] = [
                '#type' => 'item',
                '#markup' => 'Select Fill the Config root path here <a href="/admin/config/development/configuration/config-manual-setting">here</a>'
            ];
        }   
        $form_state->setCached(FALSE);
        $form['#cache'] = ['max-age' => 0];

        return $form;
    }
    public function importProcess(array &$form, FormStateInterface $form_state)
    {
        $helper = \Drupal::service('config_export_import.manager');
        $values = $form_state->getValues();
        // Selected Config Export  
        $selected = "?";
        if(!empty($values['table'])){
                $results = $values['table'];

                foreach ($results as $key => $result) {
                    if (isset($result['check']) 
                    && $result['check'] == 1 && !is_numeric($key) && $key != ' -- ') {
                        $selected =   $selected ."imports[]=".$key."&" ;
                    }
                }
        }
        if( $selected == "?"){
            $message = "No files are selected to import ";
            \Drupal::messenger()->addMessage($message,'error');
            $url = Url::fromRoute('config_export_import.manual_import');
        }else{
            $url = Url::fromRoute('config_export_import.manual_import_batch');
        }
        $path = $url->toString(). $selected ;
        $response = new RedirectResponse($path, 302);
        $response->send();
        return;

    }
    public function _displayImport($output, $key, $query, $result, $root)
    {

        if (isset($query['type']) && $query['type'] != 'all') {
            if ($query['type'] == $result['type']) {
               
                if (isset($query['key']) && $query['key'] != "") {
                    $filter = $query['key'];
                    if (is_string($filter) && strpos($result['file'], $filter) !== false) {
                        $output = $this->_itemByType($output, $key, $query, $result, $root);
                    }
                    if ($result['type'] == 'dir' && is_string($filter) && strpos($result['path'], $filter) !== false) {
                        $output = $this->_itemByType($output, $key, $query, $result, $root);
                    }
                } else {
                    $output = $this->_itemByType($output, $key, $query, $result, $root);
                }
            }
        } else {

            if (isset($query['key']) && $query['key'] != '') {
                $filter = $query['key'];
                if (is_string($filter) && strpos($result['file'], $filter) !== false) {
                    $output = $this->_itemByType($output, $key, $query, $result, $root);
                }
                if ($result['type'] == 'dir' && is_string($filter) && strpos($result['path'], $filter) !== false) {
                    $output = $this->_itemByType($output, $key, $query, $result, $root);
                }
            } else {
                $output = $this->_itemByType($output, $key, $query, $result, $root);
            }
        }
        return $output;
    }
    public function _itemByType($output, $key, $query, $result, $root)
    {
        $helper = \Drupal::service('config_export_import.manager');

        if ($result['type'] == 'dir' && $result['path'] == DRUPAL_ROOT . $root) {
          
            return $output;
        }
        $operations = $this->_tableActions($result, $root);
        $config_name = " -- ";
        $file_path = false ;
        $config_render =' --';
        if ($result['file']) {
             $file_path = $result['file'] ;
             $config_name = basename($result['file'], '.yml'); 
             $file_path_internal = $helper->removeFullPath($file_path);     
             $config_render = $helper->renderStatus($config_name, $file_path_internal);
        }
        if ($result['path']) {
            $root_folder = $result['path'];
            $root_folder = str_replace(DRUPAL_ROOT . $root, '', $root_folder);
        }
        $type = $result['type'];
        $type_list = ['dir' => 'Folder', 'file' => 'File'];

        $output[] = [
            'config_name'=> $config_name,
            'file' => $file_path,
            'id' => $key + 1,
            'type' => $type_list[$type],
            'root' => $root_folder,
            'config_render' => $config_render,
            'operation' => array('data' => array('#type' => 'operations', '#links' => $operations)),
        ];

        return $output;
    }

    protected function _tableActions($result, $root)
    {
        $result['path'] = str_replace(DRUPAL_ROOT . $root, '', $result['path']);
        if(isset($result['file'])){
          $result['file'] = str_replace(DRUPAL_ROOT . $root, '', $result['file']);
        }
        $type_label = ($result['type']=='dir')? 'Folder' : 'File' ;
        $import_label = $result['type'] ;
        if($result['type'] =='dir'){
            $operations['view'] = array(
                'title' => $this->t('View '.$type_label),
                'url' => Url::fromRoute('config_export_import.manual_import', array('path' => $result['path'],'type' => 'all'))
            );
        }
        $operations['import'] = array(
            'title' => $this->t('Import '.$type_label),
            'url' => Url::fromRoute('config_export_import.manual_import_batch', array('import_'.$import_label => $result))
        );
        if($result['type']=='file'){
            $operations['edit'] = array(
                'title' => $this->t('Edit  '.$type_label),
                'url' => Url::fromRoute('config_export_import.config_manual_editor', array('config_path' => $result['file']))
            );
        }
   

        $operations['remove'] = array(
            'title' => $this->t('Delete '.$type_label),
            'url' => Url::fromRoute('config_export_import.manual_import', array('delete_'.$import_label => $result))
        );
        return $operations;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {

    }
    public function importReset(array &$form, FormStateInterface $form_state) {
        $path = Url::fromRoute('config_export_import.manual_import')->toString();
        $response = new RedirectResponse($path, 302);
        $response->send();
        return;
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
            'config_export_import.config_import',
        ];
    }

}
