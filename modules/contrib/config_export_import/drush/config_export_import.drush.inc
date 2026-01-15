<?php
use Drupal\Core\Config\FileStorage;
function config_export_import_drush_command()
{
    $items = [];
    $items['cex-folder'] = ['description' => 'Export Config Single file', 'aliases' => ['cexf'], 
    'arguments' => ['path' => 'Path location', 'config_name' => 'Export new only.', ],
     ];
    $items['cex-file'] = ['description' => 'Export Config Single file', 'aliases' => ['cexf'], 'arguments' => ['config_name' => 'Export new only.','path' => 'Path location','action' => 'Dependencies condition']];
    $items['cim-file'] = ['description' => 'Import Config Single file', 'aliases' => ['cimf'], 
    'arguments' => ['key' => 'Folder location','action' => 'Dependencies condition'], 
    ];

    $items['cim-folder'] = [
        'description' => 'Import Config Folder', 'aliases' => ['cimfl'], 
        'arguments' => [
            'folder_name' => 'Folder location',
            'action' => 'Dependencies condition'
        ],
    ];
    $items['cex-info'] = ['description' => 'Import Config Info', 'aliases' => ['cim-info']];

    return $items;
}
function drush_config_export_import_cex_info()
{
    $config_settings = \Drupal::config("config_export_import.settings");
    //   $service = \Drupal::service('config_export_import.manager');
    $root = $config_settings->get('root');
    drush_print('Root path :' . $config_settings->get('root'));

}
function drush_config_export_import_cim_folder($folder_name = NULL, $action = NULL )
{
    $config_settings = \Drupal::config("config_export_import.settings");
    $service = \Drupal::service('config_export_import.manager');
    if ($config_settings->get('root'))
    {
        $root = $config_settings->get('root');
    }
    else
    {
        drush_print('Please fill Config root location in  /admin/config/development/configuration/config-manual-setting ');
        return NULL;
    }
    $folder = [];
    $select_folder = null;
    if ($folder_name == NULL)
    {
        $folder = $service->getFoldeDirectory(DRUPAL_ROOT . $root);
        foreach ($folder as $key => $item)
        {
            $folder[$key] = str_replace(DRUPAL_ROOT . $root, '', $folder[$key]);
        }
        if ($choice = drush_choice($folder, 'Enter the number of the folder you wish to import.'))
        {
            $select_folder = str_replace(DRUPAL_ROOT . $root, '', $choice);;
        }
        else
        {
            return NULL;
        }
        if (!drush_confirm('You choice ' . $select_folder . ', Do you want continue to import ?'))
        {
           return NULL;
        }
    }else{  $select_folder = $folder_name ;}
    $config_path = DRUPAL_ROOT . $root . $select_folder;
     if (!is_dir( $config_path )){
         drush_print($select_folder .' path location is not exist '); 
         return NULL ;
     }
    $config_list = [];
    $results = $service->readDirectoryFile($config_path, 'yml');
    if(empty($results)){
         drush_print('No files found in folder '.$select_folder);
         return NULL ;
    }
    $dependencies_current = [];
    $current_config = [];
    drush_print('Found :');
    foreach ($results as $key => $file)
    {
        $childs = $service->getAllDependencyByFolder($file);
        if (is_array($childs))
        {
            $dependencies_current = array_merge($childs, $dependencies_current);
        }
    }
    $y = 0 ;
    foreach ($results as $key => $file)
    {
        $config_name = basename($file, '.yml');
        $config_dep = array_keys($dependencies_current);
        if (!in_array($config_name, $config_dep))
        {
            $file = $service->removeFullPath($file);
            $current_config[$config_name] = ["config_name" => $config_name, "file" => $file];
            $stat = $service->renderStatusTextImport($file) ; 
            drush_print(' Config yml   '. $file. ' - '.$stat );
            $y ++ ;
            if($y > 50){
                drush_print(' more then 50 config yml ....   ');
                break;
            }
    
        }
    }
    if(!empty($dependencies_current))
     {    $i =0 ;
          foreach ($dependencies_current as $key => $dep)
            {   
                $stat = $service->renderStatusTextImport($dep['file']) ; 
                drush_print(' Config dependencies yml   '. $dep['file'] . ' - '. $stat);
                $i ++ ;
                if($i > 50){
                    drush_print(' more then 50 config yml ....   ');
                    break;
                }
            }
    }
    drush_print('Config ' . sizeof($current_config) . ' files');
    drush_print('Config dependencies ' . sizeof($dependencies_current) . ' files');
    $condition = null;
    $options['new_only'] = t('Import only if config dependency is not exist');
    $options['replace'] = t('Replace if config dependency is exist already');
    if($action == NULL ){
        if ($choice = drush_choice($options, 'Enter the number of the dependency condition you wish to apply.'))
        {
            $condition = $choice;
        }
        else
        {
            return NULL;
        }
    } else {
        $condition = $action ;
    }
    if(!in_array( $condition , ['new_only','replace'])){
         drush_print('Dependencies condition must be : new_only  or replace,for example: drush cim-folder /node new_only ');
         return NULL ;
    }

    $config_list = array_merge($dependencies_current, $current_config);
    foreach (array_values($config_list) as $key => $item)
    {
        $file = $item['file'];
        $config_name = basename($file, '.yml');
        $file = DRUPAL_ROOT . $root . $file;

        $info = ['index' => $key + 1, 'file' => $file, 'config_name' => $config_name, 'condition' => $condition];
        $operations[] = ['config_export_import_folder_batch_process', [$info], ];

    }

    $batch = ['operations' => $operations, 'title' => t('Import content process') , 'init_message' => t('Starting...') , 'progress_message' => t('Completed @current of @total.') , 'error_message' => t('An error occurred') , 'finished' => 'config_export_import_batch', 'file' => drupal_get_path('module', 'config_export_import') . '/drush/config_export_import.batch.inc', ];
    batch_set($batch);
    drush_backend_batch_process();

}

function drush_config_export_import_cim_file($key = NULL , $action = NULL)
{
    $config_settings = \Drupal::config("config_export_import.settings");
    $service = \Drupal::service('config_export_import.manager');
    if ($config_settings->get('root'))
    {
        $root = $config_settings->get('root');
    }
    else
    {
        drush_print('Please fill Config root location in  /admin/config/development/configuration/config-manual-setting ');
        return NULL;
    }
    
    $config_path = DRUPAL_ROOT . $root;
    if ($key == NULL)
    {
        drush_print('Please search key name  you want to import (for example : core.entity_form_display.article ) : ');
        $key = drush_prompt(dt('Search Key'));
        $key = trim($key);

        $results = $service->readDirectoryFile($config_path, 'yml');
        $search_result = [];
        foreach ($results as $file)
        {
            $config_name = basename($file, '.yml');
            if (strpos($config_name, $key) !== false)
            {
                $file_config_path = str_replace(DRUPAL_ROOT . $root, '', $file);
                $stat = $service->renderStatusTextImport($file_config_path) ;
                $search_result[$file] = $config_name .' - '. $stat;
            }

        }
        if(empty($search_result)){
            drush_print('No result found');
            return NULL ;
        }
        if(sizeof($search_result)> 50){
        $i = 1 ;  
        foreach ($search_result as $file)
            {   if($i > 50) { break ; }
                drush_print('- '.$file);
                $i ++ ;
            }   
        }
        while (sizeof($search_result) > 50)
        {
            $search_result = [];
            drush_print('Results are more then 50 items, Please restrict search key name (for example : core.entity_form_display.article ) : ');
            $key = drush_prompt(dt('Search Key'));
            $key = trim($key);
            foreach ($results as $file)
            {
                $config_name = basename($file, '.yml');
                if (strpos($config_name, $key) !== false)
                {
                    $search_result[$file] = $config_name;
                }
            }
            if(empty($search_result)){
            drush_print('No result found');
            return NULL ;
            }
            if(sizeof($search_result)> 50){
                $i = 1 ;  
                foreach ($search_result as $file)
                    {   if($i > 50) { break ; }
                        drush_print('- '.$file);
                        $i ++ ;
                    }   
        }
        }
        $selected = null;
        $file_path = null;
        if ($choice = drush_choice($search_result, 'Enter the number of the config file you wish to import.'))
        {
            $file_path = $choice;
            $selected = str_replace(DRUPAL_ROOT . $root, '', $choice);
        }
        else
        {
            return NULL;
        }

        if (!drush_confirm('You choice ' . $selected . ', do you want continue to import ?'))
        {
            return NULL;
        }
    }else{
         $selected = $key ;
         $status = $service->isExistLocal($key) ;
         if( !$status ){
              drush_print(' Config name ' . $key . ' is not exist in local folder , for example : drush cim-file media.type.video new_only ' );
               return NULL;
         }else{
             $file_path =  $status ;
         }

         
    }
   

    $deps = $service->getAllDependencyByFolder($file_path);
    drush_print('Found :');
    drush_print('Config name ' .basename( $selected,'.yml'));
    drush_print('Config dependencies ' . sizeof($deps));
    $condition = null;
    if($action == NULL){
        $options['new_only'] = t('Import only if config dependency is not exist');
        $options['replace'] = t('Replace if config dependency is exist already');
        if ($choice = drush_choice($options, 'Enter the number of the dependency condition you wish to apply.'))
        {
            $condition = $choice;
        }
        else
        {
            return NULL;
        }
    }else{
         $condition = $action;
         if(!in_array( $condition , ['new_only','replace'])){
            drush_print('Dependencies condition must be : new_only  or replace,for example: drush cim-folder /node new_only ');
            return NULL ;
          }
 
    }

    $current_config =[];
  
    foreach (array_values($deps) as $key => $item)
    {
        $file = $item['file'];
        $config_name = basename($file, '.yml');
        $file = DRUPAL_ROOT . $root . $file;

        $info = ['index' => $key + 1, 'file' => $file, 'config_name' => $config_name, 'condition' => $condition];
        $operations[] = ['config_export_import_folder_batch_process', [$info]];

    }
    $info_selected = ['index' => 'selected',"file" => $file_path,"config_name" => basename( $selected,'.yml'),'condition' => $condition];
    $operations[]= ['config_export_import_folder_batch_process',[$info_selected]];

    $batch = ['operations' => $operations, 'title' => t('Import content process') , 'init_message' => t('Starting...') , 'progress_message' => t('Completed @current of @total.') , 'error_message' => t('An error occurred') , 'finished' => 'config_export_import_batch', 'file' => drupal_get_path('module', 'config_export_import') . '/drush/config_export_import.batch.inc', ];
    batch_set($batch);
    drush_backend_batch_process();
}
function drush_config_export_import_cex_file($config_name = NULL,$path = NULL, $action = NULL)
{
    $config_settings = \Drupal::config("config_export_import.settings");
    $service = \Drupal::service('config_export_import.manager');
    if ($config_settings->get('root'))
    {
        $root = $config_settings->get('root');
    }
    else
    {
        drush_print('Please fill Config root location in  /admin/config/development/configuration/config-manual-setting ');
        return NULL;
    }
    $config_path = DRUPAL_ROOT . $root;
    if ($config_name == NULL)
    {
        drush_print('Please search key name  you want to export (for example : core.entity_form_display.article ) : ');
        $key = drush_prompt(dt('Search Key'));
        $key = trim($key);
        $search_result = $service->getConfigContains($key);      
        if(empty($search_result)){
            drush_print('No result found');
            return NULL ;
        }
        if(sizeof($search_result)> 50){
        $i = 1 ;  
        foreach ($search_result as $file)
            {   if($i > 50) { break ; }
                drush_print('- '.$file);
                $i ++ ;
            }   
        }
        while (sizeof($search_result) > 50)
        {
            $search_result = [];
            drush_print('Results are more then 50 items, Please restrict search key name (for example : core.entity_form_display.article ) : ');
            $key = drush_prompt(dt('Search Key'));
            $key = trim($key);
            $search_result = $service->getConfigContains($key);  
            if(empty($search_result)){
            drush_print('No result found');
            return NULL ;
            }
            if(sizeof($search_result)> 50){
                $i = 1 ;  
                foreach ($search_result as $file)
                    {   if($i > 50) { break ; }
                        drush_print('- '.$file);
                        $i ++ ;
                    }   
           }
        }
        $selected = null;
        $config_name = null;
        if ($choice = drush_choice($search_result, 'Enter the number of the config file you wish to export.'))
        {
            $config_name = $search_result[$choice];
        }
        else
        {
            return NULL;
        }

        if (!drush_confirm('You choice ' . $config_name . ', do you want continue to export ?'))
        {
            return NULL;
        }
    }
    if ($path == NULL)
    {
        $folder_list = $service->getFoldeDirectory(DRUPAL_ROOT . $root);
        drush_print('Existing Folder config list :');
        drush_print('/');
        if (!empty($folder_list))
        {   $folder_exist = [];
           /// $folder_exist[] = "create new folder";
            foreach ($folder_list as $key => $folder)
            {
                $folder = $service->removeFullPath($folder);
                $folder_exist[$key] = $folder;
            }
            $path = null ;
            if ($choice = drush_choice($folder_exist, 'Enter the number of the folder location .'))
            {
               $path = $folder_exist[$choice];
            }
            else
            {
                return NULL;
            }

        }

   
    }
    if ($service->isExistLocal($config_name))
    {
        $current_path = $service->isExistLocal($config_name);
        $current_path = str_replace(DRUPAL_ROOT . $root, '', $current_path);
        drush_print('Config exist already in ' . $current_path);
        if (!drush_confirm('Do you want continue to export ' . $config_name . ' in ' . $path . '?'))
        {
            return NULL;
        }
    }
    $dependencies = $service->getAllDependencyByDatabase($config_name);
    drush_print('Dependencies : ' . implode(' - ', $dependencies));
    if (drush_confirm('Do you want to export with dependency ?'))
    {
        $with_dep = true;
    }
    else
    {
        $with_dep = false;
    }
    if ($with_dep)
    {
        if (drush_confirm('Do you want to export only new dependency config in folder config ?'))
        {
            $new_only = true;
        }
        else
        {
            $new_only = false;
        }
    }
    if ($with_dep && !empty($dependencies))
    {
        foreach ($dependencies as $key => $dep)
        {
            if (!$service->isExistLocal($dep))
            {
                $service->exportConfig($key, $path);
                drush_print('New config ' . $key . ' export successfully ');
            }
            else
            {
                if (!$new_only)
                {
                    $service->exportConfig($key, $path);
                    drush_print('Updated config ' . $key . ' export successfully ');
                }
            }
        }
    }
    $service->exportConfig($config_name, $path);
    if (!$service->isExistLocal($config_name))
    {
        drush_print('New config ' . $config_name . ' export successfully ');
    }
    else
    {
        drush_print('Updated config ' . $config_name . ' export successfully ');
    }
}

