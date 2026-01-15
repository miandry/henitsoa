<?php

namespace Drupal\config_export_import;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\FileStorage;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;
use Drupal\Core\Render\Markup;
use Drupal\Component\Diff\Diff;

class ConfigExportImportManager
{
    public function getConfigContains($filter){
        $configs = \Drupal::configFactory()->listAll();
        $result = [];
        foreach ($configs as $config_name){
            if (is_string($filter) && strpos($config_name, $filter) !== false) {
                $result[] = $config_name ;
            }
            if (is_array($filter) && in_array($config_name, $filter) !== false) {
                $result[] = $config_name ;
            }
        }
        return $result ;
    }
    public function exportConfig($config_name,$path){
        $config_settings = \Drupal::config("config_export_import.settings") ;
        $root =  $config_settings->get('root');
        $config = \Drupal::config($config_name) ;
        $data = $config->getOriginal();
        try {
            $path_base = DRUPAL_ROOT.$root.$path;
            $file_path = $path_base.'/'.$config_name.'.yml' ;
            if (file_exists($file_path)) {
                unlink($file_path);
                $action = ' and replace ';
            }else{
                $action = ' new ';
            }
            $output = Yaml::encode($data);
            $status = $this->generateFileForce($path_base,$config_name.'.yml',$output);
            if($status){
                \Drupal::messenger()->addMessage('Exported '.$action.' Config '.$config_name);
            }
        }
        catch (InvalidDataTypeException $e) {
            \Drupal::messenger()->addError($this->t('Invalid data detected for @name : %error', array('@name' => $config_name, '%error' => $e->getMessage())));
            return;
        }

    }
    public function exportConfigByFullPath($config_name,$path){
        $config = \Drupal::config($config_name) ;
        $data = $config->getOriginal();
        try {
            $file_path = $path.'/'.$config_name.'.yml' ;
            if (file_exists($file_path)) {
                unlink($file_path);
                $action = ' and replace ';
            }else{
                $action = ' new ';
            }
            $output = Yaml::encode($data);
            $status = $this->generateFileForce($path,$config_name.'.yml',$output);
            if($status){
                \Drupal::messenger()->addMessage('Exported '.$action.' Config '.$config_name);
            }
        }
        catch (InvalidDataTypeException $e) {
            \Drupal::messenger()->addError($this->t('Invalid data detected for @name : %error', array('@name' => $config_name, '%error' => $e->getMessage())));
            return;
        }

    }
    // action : skip  or merge or replace
    public function exportConfigMerge($config_name,$path){
        $config_settings = \Drupal::config("config_export_import.settings") ;
        $root =  $config_settings->get('root');
        $config = \Drupal::config($config_name) ;
        $data = $config->getOriginal();
        try {
            $path_base = DRUPAL_ROOT.$root.$path;
            $file_path = $path_base.'/'.$config_name.'.yml' ;
            if (file_exists($file_path)) {
                $source = new FileStorage($path_base);
                $config_data_source = $source->read($config_name);
                $data_merged = $this->array_merge_deep($data,$config_data_source); 
                $output = Yaml::encode($data_merged);
                unlink($file_path);
                $status = $this->generateFileForce($path_base,$config_name.'.yml',$output);
                    if($status){
                        \Drupal::messenger()->addMessage('Merged existing Config '.$config_name);
                    }
            }else{
                $output = Yaml::encode($data);
                $status = $this->generateFileForce($path_base,$config_name.'.yml',$output);
                if($status){
                    \Drupal::messenger()->addMessage('Exported new Config '.$config_name);
                }
            }
        }
        catch (InvalidDataTypeException $e) {
            \Drupal::messenger()->addError($this->t('Invalid data detected for @name : %error', array('@name' => $config_name, '%error' => $e->getMessage())));
            return;
        }

    }
    /** $array1 value  will replace by $array2  array_merge_deep($array1,$array2) */
    public function array_merge_deep() {
            $args = func_get_args();
            return $this->array_merge_deep_base($args);
    }
        public function array_merge_deep_base($arrays) {
            $result = array();
            foreach ($arrays as $array) {
                foreach ($array as $key => $value) {
                    // Renumber integer keys as array_merge_recursive() does. Note that PHP
                    // automatically converts array keys that are integer strings (e.g., '1')
                    // to integers.
                    if (is_integer($key)) {
                        $result[] = $value;
                    }
                    elseif (isset($result[$key]) && is_array($result[$key]) && is_array($value)) {
                        $result[$key] = $this->array_merge_deep_base(array(
                            $result[$key],
                            $value,
                        ));
                    }
                    else {
                        $result[$key] = $value;
                    }
                }
            }
            return $result;
        }
    public function getInternalPath($full_path){
        $path_with_name = $this->removeFullPath($full_path);
        return  dirname($path_with_name) ;
    }
    public function generateFileForce($directory, $filename, $content)
    {
        $fileSystem = \Drupal::service('file_system');
        if (!is_dir($directory)) {
            if ($fileSystem->mkdir($directory, 0777, TRUE) === FALSE) {
                \Drupal::messenger()->addMessage(t('Failed to create directory ' . $directory), 'error');
                return FALSE;
            }
        }else{
            @chmod($directory  , 0777);
        }

        if (file_put_contents($directory . '/' . $filename , $content) === FALSE) {
            \Drupal::messenger()->addMessage(t('Failed to write file ' . $filename), 'error');
            return FALSE;
        }
        return TRUE;
    }
    public function getInfoConfigYaml($file){

        $config_manager = \Drupal::service('config.manager');
        $type = $config_manager->getEntityTypeIdByName(basename($file,'.yml'));
        $entity_manager = $config_manager->getEntityTypeManager();
        $id_key = null;
        if($type){
        $definition = $entity_manager->getDefinition($type);
        $id_key = $definition->getKey('id');
        }
        return [ "idkey"=>$id_key , "type" => $type ];
    }
    public function importExistConfig($path,$config_name,$config_data){
        $config_info = $this->getInfoConfigYaml($path.'/'.$config_name.'.yml');
        if($config_info['type'] && $config_info['idkey']){
            $entity_storage = \Drupal::entityTypeManager()->getStorage($config_info['type']);
            $id = $config_data[$config_info['idkey']];
            $entity = $entity_storage->load($id);
            if ($entity) {
                $entity = $entity_storage->updateFromStorageRecord($entity, $config_data);
                return $entity->save();
            }
            return false;
        } else {
            return \Drupal::configFactory()->getEditable($config_name)->setData($config_data)->save();
        }
    }
    public function importNewConfig($path,$config_name,$config_data){
        $config_info = $this->getInfoConfigYaml($path.'/'.$config_name.'.yml');
        if($config_info['type'] && $config_info['idkey']){
            /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $entity_storage */
            $entity_storage = \Drupal::entityTypeManager()->getStorage($config_info['type']);
            $entity = $entity_storage->createFromStorageRecord($config_data);
            return $entity->save();
        } else {
            return \Drupal::configFactory()->getEditable($config_name)->setData($config_data)->save();
        }
    }
    public function checkDependencyConfig($file)
    {
        $results = [];
        $config_name = basename($file, '.yml');
        $path = dirname($file) ;
        $fileStorage = new FileStorage($path);
        $source = $fileStorage->read($config_name);
        if($source && isset($source['dependencies']) && isset($source['dependencies']['config'])){
            $dependencies = ($source['dependencies']);
            if(!empty($dependencies['config'])){
                foreach ($dependencies['config'] as $key => $config_name_item) {
                    if(!$this->isExistDatabase($config_name_item)){
                        \Drupal::messenger()->addError('Config dependency  '.$config_name_item.' is not in the site , please import it first');
                        return false;
                    }
                }
            }


        }

        return true ;
    }
    public function getDependencyModuleInfo($file){
        $results = [];
        $config_name = basename($file, '.yml');
        $path = dirname($file) ;
        $fileStorage = new FileStorage($path);
        $source = $fileStorage->read($config_name);
        if($source && isset($source['dependencies']) && !empty($source['dependencies']['module'])){
            $dependencies = $source['dependencies'];
            if(!empty($dependencies['module'])){
                    foreach ($dependencies['module'] as $key => $module_name) {
                        $status =  \Drupal::moduleHandler()->moduleExists($module_name);
                        $results[$module_name] = ['module' => $module_name,'install' =>   $status ];

                    }
            }
        }
        return $results ;
    }

    public function checkDependencyModule($file){
        $results = [];
        $config_name = basename($file, '.yml');
        $path = dirname($file) ;
        $fileStorage = new FileStorage($path);
        $source = $fileStorage->read($config_name);
        if($source && isset($source['dependencies']) && !empty($source['dependencies']['module'])){
            $dependencies = $source['dependencies'];
            if(!empty($dependencies['module'])){
                    foreach ($dependencies['module'] as $key => $module_name) {
                        $status =  \Drupal::moduleHandler()->moduleExists($module_name);
                        if(!$status){
                            \Drupal::messenger()->addError('Module dependency  '.$module_name.' not enabled in the site');
                            return false ;
                        }
                    }
            }
        }

        return true ;

    }
    public function readYMLInfoFile($file){
        $config_name = basename($file, '.yml');
        $path = dirname($file) ;
        $fileStorage = new FileStorage($path);
        return $fileStorage->read($config_name);
    }
    public function getAllDependencyByDatabaseInArray($config_list){
        $dep_result = [];
        foreach ($config_list as $config_name){
            $dep_result_child = $this->getAllDependencyByDatabase($config_name);
            $dep_result = array_merge($dep_result , $dep_result_child);
        }
        return  $dep_result  ;
    }
    public function getAllDependencyByDatabase($config_name){
        $dep_result = [];
        $dependencies = \Drupal::configFactory()->get($config_name)->get('dependencies');
        if (isset($dependencies['config'])) {
                foreach ($dependencies['config'] as $key => $config_name_dep) {
                    //$this->deleteConfig($config_name);
                    $dep_result[$config_name_dep] = $config_name_dep ;
                    $dep_result_child = $this->getAllDependencyByDatabase($config_name_dep) ;
                    $dep_result = array_merge($dep_result , $dep_result_child);
                }
        }
        return  $dep_result  ;
    }
    public function getAllDependencyByCustomFolder($file,$root){
        $results = [];
        $source = $this->readYMLInfoFile($file);
        if($source && isset($source['dependencies']) && isset($source['dependencies']['config'])
            && !empty($source['dependencies']['config'])){
            $dependencies = $source['dependencies'];
            foreach ($dependencies['config'] as $config_dep){
                $file_path = $this->searchFileInDirectory($config_dep,DRUPAL_ROOT .$root);
                $file_item = '--' ;
                if(!empty($file_path)){
                    $file_item_full = end($file_path) ;
                    $this->checkDependencyModule($file_item_full);
                    $file_item = $this->removeFullPath($file_item_full);
                    $childs = $this->getAllDependencyByCustomFolder($file_item_full,$root);
                    if($childs){
                       $results = array_merge($childs , $results);
                    }
                }else{
                    if(!$this->isExistDatabase($config_dep)){
                        \Drupal::messenger()->addError('Config dependency  '.$config_dep.' is not exist in root folder: ' .$root);
                        return false ;
                    }
                }
                    $results[$config_dep] = [
                        "config_name" => $config_dep,
                        "file" => $file_item
                    ];

            }


        }

        return $results ;


    }
    public function getAllDependencyByFolder($file,$display = true){
        $results = [];
        $source = $this->readYMLInfoFile($file);
        if($source && isset($source['dependencies']) && isset($source['dependencies']['config'])
            && !empty($source['dependencies']['config'])){
            $dependencies = $source['dependencies'];
            $config_settings = \Drupal::config("config_export_import.settings") ;
            $root =  $config_settings->get('root');
            foreach ($dependencies['config'] as $config_dep){
                $file_path = $this->searchFileInDirectory($config_dep,DRUPAL_ROOT .$root);
                $file_item = '--' ;
                if(!empty($file_path)){
                    $file_item_full = end($file_path) ;
                    $this->checkDependencyModule($file_item_full);
                    $file_item = $this->removeFullPath($file_item_full);
                    $childs = $this->getAllDependencyByFolder($file_item_full);
                    if($childs){
                       $results = array_merge($childs , $results);
                    }
                }else{
                    if(!$this->isExistDatabase($config_dep)){
                        \Drupal::messenger()->addError('Config dependency  '.$config_dep.' is not exist in root folder: ' .$root);
                        return false ;
                    }
                }
                if($display){
                    $config_render = $this->renderStatus($config_dep,$file_item);
                    $results[$config_dep] = [
                        "config_name" => $config_render,
                        "file" => $file_item
                    ];
                }else{
                    $results[$config_dep] = [
                        "config_name" => $config_dep,
                        "file" => $file_item
                    ];
                }

            }


        }

        return $results ;


    }
    public function isExistDatabase($config_name){
        $config_storage = \Drupal::service('config.storage');
        return $config_storage->exists($config_name);
    }
    public function isExistLocal($config_name){
        $config_settings = \Drupal::config("config_export_import.settings") ;
        $root =  $config_settings->get('root');
        $file_path = $this->searchFileInDirectory($config_name,DRUPAL_ROOT .$root);
        if(empty($file_path)){
            return false ;
        }else{
            return end($file_path);
        }
    }
    public function compareDiff($config_path){
        $config_settings = \Drupal::config("config_export_import.settings");
        if($config_settings->get('root')){
            $root = $config_settings->get('root');
            $file_path = DRUPAL_ROOT.$root.$config_path;
            $config_name = basename($file_path, '.yml');
            $path_base = dirname($file_path) ;
            if (file_exists($file_path)) {
                $source = new FileStorage($path_base);
                $file_output= $source->read($config_name);         
                $config = \Drupal::config($config_name) ;
    
                $database_output = $config->getOriginal();               
                $from = explode("\n", Yaml::encode($file_output));
                $to = explode("\n", Yaml::encode($database_output));
                $diff = new Diff($from, $to);
                return  $diff->isEmpty() ; // if true same config
            }
        }
        return false ;

    }
    public function renderStatus($config_name,$config_path = '') {
        if($config_path == '/'){$config_path = '';}
        global $base_url;
        $status = $this->compareDiff($config_path) ;
        $same = ($status)? ' <span style=color:green> same </span> ' : '<a target="_blank" href="'.$base_url.'/admin/config/development/configuration/manual-import-diff?config_path='.$config_path .'"><span style="color:blue">  view diff </span></a>';

        $new = Markup::create( $config_name.' ( <span style="color:red"> new </span> )');
        $diff = Markup::create($config_name.' ( db exist - '.$same.' ) ');
        return ($this->isExistDatabase($config_name))? $diff : $new ;
    }
    public function renderStatusTextImport($config_path) {
        $status = $this->compareDiff($config_path) ;
        $same = ($status)? ' same ':' diff ';
        $diff = 'db exist - '.$same ;
        $config_name = basename($config_path, '.yml');
        return ($this->isExistDatabase($config_name))? $diff : 'new' ;
    }
    public function renderStatusTextExport($config_name,$config_path = '') {
        global $base_url;
        if($config_path == '/'){$config_path = '';}
        $config_path = $config_path.'/'.$config_name.'.yml';
        $status = $this->compareDiff($config_path) ;
        $same = ($status)? ' same ' : ' diff ';
        $diff = ' file exist - '.$same ;
        return ($this->isExistLocal($config_name))? $diff : ' new ' ;
    }
    public function renderStatusExport($config_name,$config_path = '/') {
        global $base_url;
        if($config_path == '/'){$config_path = '';}
        $config_path = $config_path.'/'.$config_name.'.yml';
        $status = $this->compareDiff($config_path) ;
        $same = ($status)? ' <span style=color:green> same </span> ' : '<a target="_blank" href="'.$base_url.'/admin/config/development/configuration/manual-import-diff?config_path='.$config_path .'"><span style="color:blue">  view diff </span></a>';

        $new = Markup::create( $config_name.' ( <span style="color:red"> new </span> )');
        $diff = Markup::create($config_name.' ( file exist - '.$same.' ) ');
        return ($this->isExistLocal($config_name))? $diff : $new ;
    }
    public function removeFullPath($root_folder){
        $config_settings = \Drupal::config("config_export_import.settings") ;
        $root =  $config_settings->get('root');
        return str_replace(DRUPAL_ROOT . $root, '', $root_folder);
    }
    public function importConfig($file_path, $force = false){
        if(!is_string($file_path)){
            return false ;
        }
        $pathinfo = pathinfo($file_path);
        $config_name = $pathinfo['filename'];
        $path = $pathinfo['dirname'];
        $source = new FileStorage($path);
        $config_storage = \Drupal::service('config.storage');
        $this->checkDependencyConfig($file_path);
        if($source->exists($config_name)){
            $config_data = $source->read($config_name);
            if($config_storage->exists($config_name)){
               if($force){
                   $status =  $this->importExistConfig($path,$config_name,$config_data);
                   if($status){
                        \Drupal::messenger()->addMessage('updated config '.$config_name);
                   }
               } else {
                     \Drupal::messenger()->addError('Config '.$config_name. ' exist already');
               }
            } else {
                $status = $this->importNewConfig($path,$config_name,$config_data);
                if($status){
                    \Drupal::messenger()->addMessage('Create Config '.$config_name);
                }
            }
       }else{
            \Drupal::messenger()->addError('File yml '.$config_name.' not exist in folder '.$path);
            return false;
       }
    }
    public function deleteLocal($delete,$root){
        $status = false ;
        $messenger = \Drupal::messenger();
        if(isset($delete['type']) && $delete['type'] == 'file' && isset($delete['file'])){
                 $path_file = DRUPAL_ROOT.$root.$delete['file'] ;
                 if (!is_dir( $path_file ) && file_exists($path_file)){
                      $status =  unlink($path_file) ;
                 }
        }else {

        }
        if($status) {
            $message = t('Element '.$delete['file'].' deleted successfully');
            $messenger->addMessage($message,'status');
        } else {
            $message = t('Element '.$delete['file'].' failed to delete');
            $messenger->addMessage($message,'error');
        }
    }
    public function deleteFolder($delete,$root){
        $status = false ;
        $messenger = \Drupal::messenger();
        if(isset($delete['type']) && $delete['type'] == 'dir' && isset($delete['path'])){
                 $path_full = DRUPAL_ROOT.$root.$delete['path'] ;
                 $status =$this->delete_directory( $path_full) ;
        }
        if($status){
            $message = t('Folder '.$delete['path'].' deleted successfully');
            $messenger->addMessage($message,'status');
        }else{
            $message = t('Folder '.$delete['path'].' failed to delete');
            $messenger->addMessage($message,'error');
        }


    }
    public  function searchFileInDirectory($key,$directory,$format = 'json')
    {
        $path_file = [];
        if (is_dir($directory)) {
            $it = scandir($directory);
            if (!empty($it)) {
                foreach ($it as $fileinfo) {
                    $element =  $directory . "/" . $fileinfo;
                    if (is_dir( $element ) && substr($fileinfo, 0, strlen('.')) !== '.') {
                        $childs = $this->searchFileInDirectory($key,$element,$format);
                        $path_file = array_merge($childs , $path_file);
                    }else{
                        if ($fileinfo && basename($fileinfo, '.yml') == $key) {
                            if (file_exists($element)) {
                                $path_file[$key] = $element ;
                            }
                        }
                    }
                }
            }
        }else{
            @chmod($directory  , 0777);
        }
        return $path_file;
    }

    public  function readDirectoryLevelOne($directory,$format = 'json')
    {
        $path_file = [];
        if (is_dir($directory)) {
            $it = scandir($directory);
            if (!empty($it)) {
                foreach ($it as $fileinfo) {
                    $element =  $directory . "/" . $fileinfo;
                    if (is_dir( $element ) && substr($fileinfo, 0, strlen('.')) !== '.') {
                        //$childs = $this->readDirectory($element,$format);
                        if($directory != "/"){
                            $path_file[$element] =  [
                                "path" => $element ,
                                "file" => false,
                                "type" => "dir"
                            ];
                        }
                      //  $path_file = array_merge($childs , $path_file);
                    }else{
                        if ($fileinfo && strpos($fileinfo, '.'.$format) !== FALSE) {
                            if (file_exists($element)) {
                                $path_file[$element] =
                                    [
                                        "path" => $directory  ,
                                        "file" => $element ,
                                        "type" => "file"
                                    ];
                            }
                        }
                    }
                }
            }
        }else{
            @chmod($directory  , 0777);
        }
        return $path_file;
    }
    public  function readDirectoryFile($directory,$format = 'json')
    {
        $path_file = [];
        if (is_dir($directory)) {
            $it = scandir($directory);
            if (!empty($it)) {
                foreach ($it as $fileinfo) {
                    $element =  $directory . "/" . $fileinfo;
                    if (is_dir( $element ) && substr($fileinfo, 0, strlen('.')) !== '.') {
                        $childs = $this->readDirectoryFile($element,$format);
                        $path_file = array_merge($childs , $path_file);
                    }else{
                        if ($fileinfo && strpos($fileinfo, '.'.$format) !== FALSE) {
                            if (file_exists($element)) {
                                $path_file[] =$element ;
                            }
                        }
                    }
                }
            }
        }else{
            \Drupal::messenger()->addMessage(t('No permission to read directory ' . $directory), 'error');
            @chmod($directory  , 0777);
        }
        return $path_file;
    }
    public  function getFoldeDirectory($directory)
    {
        $path_file = [];
        if (is_dir($directory)) {
            $it = scandir($directory);
            if (!empty($it)) {
                foreach ($it as $fileinfo) {
                    $element =  $directory . "/" . $fileinfo;
                    if (is_dir( $element ) && substr($fileinfo, 0, strlen('.')) !== '.') {
                        $childs = $this->getFoldeDirectory($element);
                        if($directory != "/"){
                            $path_file[$element] =  $element ;
                        }
                        $path_file = array_merge($childs , $path_file);
                    }
                }
            }
        }else{
            @chmod($directory  , 0777);
        }
        return $path_file;
    }
    public  function readDirectory($directory,$format = 'json')
    {
        $path_file = [];
        if (is_dir($directory)) {
            $it = scandir($directory);
            if (!empty($it)) {
                foreach ($it as $fileinfo) {
                    $element =  $directory . "/" . $fileinfo;
                    if (is_dir( $element ) && substr($fileinfo, 0, strlen('.')) !== '.') {
                        $childs = $this->readDirectory($element,$format);
                        if($directory != "/"){
                            $path_file[$element] =  [
                                "path" => $element ,
                                "file" => false,
                                "type" => "dir"
                            ];
                        }
                        $path_file = array_merge($childs , $path_file);
                    }else{
                        if ($fileinfo && strpos($fileinfo, '.'.$format) !== FALSE) {
                            if (file_exists($element)) {
                                $path_file[$element] =
                                    [
                                        "path" => $directory  ,
                                        "file" => $element ,
                                        "type" => "file"
                                    ];
                            }
                        }
                    }
                }
            }
        }else{
            @chmod($directory  , 0777);
        }
        return $path_file;
    }

    /**
     *
     */
    public static function processBatchExport($input,&$context){
        $helper = \Drupal::service('config_export_import.manager');
        $key = $input['key'] ;
        $path = $input['path'] ;
        switch ( $input['action']) {
            case "new_only":
           
                if(!$helper->isExistLocal($key)){
                    $helper->exportConfig($key, $path);  
                }
                break;
            case "replace":
              //  if($helper->isExistLocal($key)){
                    $config_existing_path  = $helper->isExistLocal($key);
                    $config_existing_path_internal = $helper->getInternalPath(  $config_existing_path );
                    $helper->exportConfig($key, $config_existing_path_internal);
            //    }
                break;
            case "merge":
                if($helper->isExistLocal($key)){
                    $config_existing_path  = $helper->isExistLocal($key);
                    $config_existing_path_internal = $helper->getInternalPath(  $config_existing_path );
                    $helper->exportConfigMerge($key, $config_existing_path_internal);
                }
                break;
        }
       // $file_path = $helper->searchFileInDirectory($key,DRUPAL_ROOT . $path);
      //  $input['path_full'] =  !empty($file_path) ? end($file_path):'';
      //  $context['results']['download'] = $input['download_status'];
        $context['results']['items'][] = $input;
    }
    public static function importFinishedCallback($success, $results, $operations) {
        if ($success) {
            $message = t('Config imported successfully');
            \Drupal::messenger()->addMessage($message);
        }
        return new RedirectResponse(Url::fromRoute('config_export_import.manual_import')->toString());
    }
    public static function importProcessFinishedCallback($success, $results, $operations) {
        return new RedirectResponse(Url::fromRoute('config_export_import.manual_import_batch')->toString());
    }
    public static function exportFinishedCallback($success, $results, $operations) {
        if ($success && class_exists('ZipArchive')) {
            $download = isset($results['download'])?$results['download'] : 0 ;
            $message = t('Config export successfully');
            if($download != 1){
                return FALSE;
            }
            $file_default_scheme =  \Drupal::config('system.file')->get('default_scheme') ;
          
            $random = date('Ymd_his');
            $file_name = "config_export_{$random}.zip";
            $zip_uri = $file_default_scheme . '://compress'; 
         
            $destination = \Drupal::service('file_system')->realpath($zip_uri);
            $fileSystem = \Drupal::service('file_system');
            if (!is_dir($destination)) {
                if ($fileSystem->mkdir($destination, 0777, TRUE) === FALSE) {
                    \Drupal::messenger()->addMessage(t('Failed to create directory ' . $destination), 'error');
                    return FALSE;
                }
            }else{
                @chmod($destination  , 0777);
            }
            $zip = new \ZipArchive;
            $zip->open($destination.'/'.$file_name , constant("ZipArchive::CREATE"));
            foreach($results['items'] as $config){
                $file = $config['path_full'];
                if (file_exists($file)) {
                  $filename = basename($file);
                  $zip->addFile($file,$filename);
                }
             }
            $zip->close();
            $url = file_create_url($zip_uri.'/'.$file_name);
            $message = t('Click <a href="@link">here</a> to download export files.', ['@link' => $url]);
            \Drupal::messenger()->addMessage($message);
        }
        return new RedirectResponse(Url::fromRoute('config_export_import.manual_export')->toString());
    }
    function delete_directory($dir) 
    {
        if (!is_dir($dir)) {return false ;}
        system("rm -rf ".escapeshellarg($dir));
        return true;        
    }
    public function getParameter($param = null)
    {
        $method = \Drupal::request()->getMethod();
        if ($param == null) {
            if ($method == "GET") {
                return \Drupal::request()->query->all();
            } elseif ($method == "POST") {
                return \Drupal::request()->request->all();
            } else {
                return null;
            }
        } else {
            if ($method == "GET") {
                return \Drupal::request()->query->get($param);
            } elseif ($method == "POST") {
                return \Drupal::request()->request->get($param);
            } else {
                return null;
            }

        }
    }
    public function move_file($path,$to){
        if(copy($path, $to)){
           unlink($path);
           return true;
        } else {
          return false;
        }
    }

}