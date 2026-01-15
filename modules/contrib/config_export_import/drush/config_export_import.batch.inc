<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

function config_export_import_folder_batch_process($info, $context){
    try {
             $service = \Drupal::service('config_export_import.manager');
             $condition =$info['condition'];
             $config_name =$info['config_name'];
             $file =$info['file'];
             if($info['index'] == 'selected' ){
                 $condition = 'replace';
             }
              if($condition == 'new_only'){
                        if(!$service->isExistDatabase( $config_name )){
                             $service->importConfig($file);
                            
                        } 
              }
                        if($condition == 'replace'){
                           $service->importConfig($file,true);
                        }
              
         drush_print('Process import  '.$info['index'].' - '.$config_name .'...');
        
    }
    catch (\Exception $e) {
        $message = "Item :" . $config_name . " Error: " . $e->getMessage();
        \Drupal::logger("config_export_import")->error($message);
    }
}

