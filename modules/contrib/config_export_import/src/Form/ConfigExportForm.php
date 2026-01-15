<?php

namespace Drupal\config_export_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class ContentExportSettingForm.
 */
class ConfigExportForm extends FormBase
{

//  /**
//   * {@inheritdoc}
//   */
    protected function getEditableConfigNames()
    {
        return [
            'config_export_import.config_export',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'config_export_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {

        $helper = \Drupal::service('config_export_import.manager');
        $query = $this->getRequest()->request->all();
        //$form_state->setMethod('GET');
        $form['key'] = [
            '#type' => 'textfield',
            '#required' => TRUE,
            '#title' => $this->t('Config search by key'),
            '#attributes' => ['name' => 'key'],
            '#default_value' => isset($query['key']) ? $query['key'] : ''
        ];
        $form['path'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Config path to export'),
            '#attributes' => ['name' => 'path'],
            '#description' => 'For example : /node/[BUNDLE]',
            '#default_value' => isset($query['path']) ? $query['path'] : ''
        ];
        $output = [];
        $filter = null;
        if (isset($query['op']) && $query['op'] == 'Search' && isset($query['path']) && $query['path'] != '') {
            $form['help'] = [
                '#type' => 'item',
                '#title' => t('Selected key and path'),
                '#markup' => 'Key : ' . $query['key'] . '<br/> Path : ' . $query['path']
            ];
        }
        if (isset($query['key']) && $query['key'] != '') {
            $filter = $query['key'];
        }
        $form['actions'] = ['#type' => 'actions'];
        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Search'),
        ];
        if (isset($query['key'])
            && isset($query['path'])
            && $query['key'] != ''
            && $query['path'] != ''
        ) {
            $form['dep_status'] = array(
                '#type' => 'checkbox',
                '#title' => t('Export with dependencies'),
                '#default_value' =>  1 ,
                '#attributes' => array('checked' => 'checked')
            );
            $options['new_only'] = t('Export only if config dependency file is not exist');
            $options['replace'] = t('Replace if config dependency file is exist already');
            $options['merge'] = t('Merge if config dependency file is exist already');
            $form['dep_condition'] = array(
              '#type' => 'select',
              '#options' => $options,
              '#default_value' =>  1 ,
              '#title' => $this->t('Export condition')
            );
   
            $form['download'] = array(
                '#type' => 'hidden',
                '#title' => t('Download Config'),
                '#default_value' =>  0 ,
                '#attributes' => array('unchecked' => 'unchecked')
            );
            $form['download']['#disabled'] = TRUE;
            $form['actions']['export'] = [
                '#type' => 'submit',
                '#value' => 'Export All',
                '#submit' => [[$this, 'exportProcess']],
            ];
        }
        $output = [];
        if ($filter) {
            $results = $helper->getConfigContains($filter);
            foreach ($results as $key => $result) {
                $output[] = [
                    'id' => $key + 1,
                    'config_name' => $result
                ];

            }

        }
        $depen = $helper->getAllDependencyByDatabaseInArray(array_column($output, 'config_name'));
        $group_class = 'group-order-weight';
        $form['table'] = array(
            '#type' => 'table',
            '#weight' => 999,
            '#header' => [
                'number' => t('Number'),
                'config_name' => t('Config name'),
                'config_path' => t('Path if exist'),
                'enable' => t('Enabled'),
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
            '#empty' => $this->t('No items found')
        );
        $form['dep_config'] = [
            '#type' => 'details',
            '#weight' => 999,
            '#open' => FALSE,
            '#title' => $this->t('CONFIG DEPENDENCIES')
        ];
        $form['dep_config']['table_dep'] = array(
            '#type' => 'table',
            '#header' => [
                'number' => t('Number'),
                'config_name' => t('Config name'),
                'config_path' => t('Path if exist'),
                'enable' => t('Enabled'),
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
            '#empty' => $this->t('No items found')
        );
        $i = 1;
        foreach ($depen as $key => $config_name_dep) {
            $form['dep_config']['table_dep'][$config_name_dep]['#attributes']['class'][] = 'draggable';
            $form['dep_config']['table_dep'][$config_name_dep]['#weight'] = 1;
            // Label col.
            $form['dep_config']['table_dep'][$config_name_dep]['id'] = [
                '#plain_text' => $i,
            ];
            $path_config ='';
            if($helper->isExistLocal($config_name_dep)){
               $paht_full = $helper->isExistLocal($config_name_dep);
               $path_config = $helper->getInternalPath(  $paht_full );
            }
            // Label col.
            $form['dep_config']['table_dep'][$config_name_dep]['config_name'] = [
                '#markup' => $helper->renderStatusExport($config_name_dep, $path_config),
            ];
       
            $form['dep_config']['table_dep'][$config_name_dep]['config_path'] = [
                '#markup' => $path_config,
            ];
            $form['dep_config']['table_dep'][$config_name_dep]['check'] = array(
                '#type' => 'checkbox',
                '#default_value' => 1,
                '#attributes' => array('checked' => 'checked')
            );

            // ID col.
            $form['dep_config']['table_dep'][$config_name_dep]['weight'] = [
                '#type' => 'weight',
                '#title' => $this->t('Weight'),
                '#title_display' => 'invisible',
                '#default_value' => 1,
                '#attributes' => ['class' => [$group_class]],
            ];
            $i++;
        }
        foreach ($output as $key => $config) {
            if (!in_array($config['config_name'], $depen)) {
                $key_item = $config['config_name'];
                $form['table'][$key_item]['#attributes']['class'][] = 'draggable';
                $form['table'][$key_item]['#weight'] = 1;
                // Label col.
                $form['table'][$key_item]['id'] = [
                    '#plain_text' => $config['id'],
                ];
                $path_config ='';
                if($helper->isExistLocal($config['config_name'])){
                   $paht_full = $helper->isExistLocal($config['config_name']);
                   $path_config = $helper->getInternalPath(  $paht_full );
                }
                // Label col.
                $form['table'][$key_item]['config_name'] = [
                    '#markup' => $helper->renderStatusExport($config['config_name'],$path_config),
                ];
             
                $form['table'][$key_item]['config_path'] = [
                    '#markup' =>  $path_config,
                ];
                $form['table'][$key_item]['check'] = array(
                    '#type' => 'checkbox',
                    '#default_value' => 1,
                    '#attributes' => array('checked' => 'checked')
                );

                // ID col.
                $form['table'][$key_item]['weight'] = [
                    '#type' => 'weight',
                    '#title' => $this->t('Weight'),
                    '#title_display' => 'invisible',
                    '#default_value' =>$config['id'],
                    '#attributes' => ['class' => [$group_class]],
                ];
            }
        }

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {

    }

    public function exportProcess(array &$form, FormStateInterface $form_state)
    {
        $helper = \Drupal::service('config_export_import.manager');
        $values = $form_state->getValues();
       // kint($values);die();
        $batch = [
            'title' => $this->t('Export Database Config To yml...'),
            'operations' => [],
            'init_message' => $this->t('Starting ..'),
            'progress_message' => $this->t('Processd @current out of @total.'),
            'error_message' => $this->t('An error occurred during processing.'),
            'finished' => 'Drupal\config_export_import\ConfigExportImportManager::exportFinishedCallback',
        ];
        if (isset($values['path']) && $values['path'] != '') {
            $path = $values['path'];
            $config_lists = [] ;
            $download = $values['download'];
            //Dependencies export
            if(!empty($values['table_dep']) && isset($values['dep_status'])
                && $values['dep_status'] == 1){
                $results_dep = $values['table_dep'];
                foreach ($results_dep as $key => $result) {
                 
                    // checked 
                    if (isset($result['check']) && $result['check'] == 1 && isset($values['dep_condition'])) {
                        $input['key'] = $key ;
                        $input['path'] =  $path  ;
                        $input['download_status'] =  $download ;
                        $input['action'] = $values['dep_condition'] ;
                        $batch['operations'][] =  [
                            '\Drupal\config_export_import\ConfigExportImportManager::processBatchExport',
                            [$input]  
                        ];                  
                   }
                }
            }
            // Selected Config Export  
            if(!empty($values['table'])){
                $results = $values['table'];
                foreach ($results as $key => $result) {
                    if (isset($result['check']) && $result['check'] == 1) {
                        $input['key'] = $key ;
                        $input['path'] =  $path  ;
                        $input['download_status'] =  $download ;
                        $input['action'] = $values['dep_condition'] ;
                        $batch['operations'][] =  [
                            '\Drupal\config_export_import\ConfigExportImportManager::processBatchExport',
                            [$input]  
                        ];  
                    }
                }
            }
            batch_set($batch);
        } else {
            $message = t('Please fill path where you want to export the config');
            \Drupal::messenger()->addError($message);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $form_state->disableRedirect();
    }

}
