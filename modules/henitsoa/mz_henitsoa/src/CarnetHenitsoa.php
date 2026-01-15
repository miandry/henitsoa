<?php

namespace Drupal\mz_henitsoa;

class CarnetHenitsoa 
{   

  //  composer require phpoffice/phpword
    public function carnet($inscription){
        $parser = \Drupal::service('entity_parser.manager') ;
        $inscription = $parser->node_parser($inscription);
        
        $entity_type_manager = \Drupal::service('entity_type.manager');
        $media_name = "carnet.docx" ;
        $query = $entity_type_manager->getStorage('media')->getQuery();
        $query->condition('name', $media_name);
        $entity_ids = $query->execute();
        if(empty( $entity_ids)){
            \Drupal::messenger()->addMessage("Template carnet.docx reference n'exist pas ", 'error');
            return false ;
        }
        $template = $parser->media_parser(end($entity_ids));
        $uri = ($template['field_media_document']['uri']); 
        $stream_wrapper_manager = \Drupal::service('stream_wrapper_manager')->getViaUri($uri);
        $file_path = $stream_wrapper_manager->realpath();  
        $templateProcessor =  new \PhpOffice\PhpWord\TemplateProcessor($file_path);
        $eleve = $parser->node_parser($inscription['field_eleve']['nid']);

        $field_carnet=['field_annee_scolaire','field_nom','field_prenom','field_date_de_naissance'
        ,'field_lieu_de_nai','field_nom_pere','field_profession_pere','field_nom_mere',
        'field_profession_mere','field_tuteur','field_adresse','field_phone','matricule',
        'field_classe','field_numero'];
        foreach ($field_carnet as $key => $field_value) {
            $status = true ;
            if(isset($inscription[$field_value]) && is_string($inscription[$field_value])){
                $templateProcessor->setValue($field_value, $inscription[$field_value]);
                $status = false ;
            }
            if(isset($eleve[$field_value]) && is_string($eleve[$field_value])){
                $templateProcessor->setValue($field_value, $eleve[$field_value]);
                $status = false ;
            }
            if($field_value == 'field_classe'){
                $templateProcessor->setValue($field_value, $inscription[$field_value]['title']);
                $status = false ;
            }
            if($field_value == 'matricule'){
                $templateProcessor->setValue('matricule', $eleve['title']);
                $status = false ;
            }
            if($status){
                $templateProcessor->setValue($field_value, '');
            }
        }
        $this->download($templateProcessor,$file_path,$eleve['title']);

    }
    public function download($templateProcessor, $file_path,$file_new_ouput)
    {
  
        $filename = basename($file_path,'.docx');
        $path = dirname($file_path) ;
        $file_new= $path.'/carnet-'.time().'.docx';
        $templateProcessor->saveAs($file_new);
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=carnet_'.$file_new_ouput.'.docx');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_new));
        flush();
        readfile($file_new);
        unlink($file_new); // deletes the temporary file
        exit;
    }

}
