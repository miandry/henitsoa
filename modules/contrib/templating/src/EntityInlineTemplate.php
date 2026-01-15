<?php

namespace Drupal\templating;

use Drupal\Component\Diff\Diff;
use Drupal\Core\Url;
use Drupal\Core\Render\Markup;
use Drupal\file\Entity\File;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Entity\Entity\EntityFormDisplay;

class EntityInlineTemplate extends BaseServiceEntityInlineTemplate
{

  function getTemplateView($variables)
  {
    $view_name = $variables['id'];
    $view_display = $variables['display_id'];
    $theme = $this->is_allowed();
    if (!$theme) {
      return false;
    }
    $config_name = "view--" . $theme . '-' . trim($view_name) . '-' . trim($view_display);
    $suggestion_1 = $this->formatName($config_name);
    $templates_views = \Drupal::entityQuery('node')
      ->condition('type', 'templating')
      ->condition('status', '1')
      ->condition('title', $suggestion_1, 'STARTS_WITH')
      ->execute();
    $results = [];
    if (!empty($templates_views)) {
      foreach ($templates_views as $id) {
        $template = \Drupal::entityTypeManager()->getStorage('node')->load($id);
        $view_section = $template->field_templating_mode_view->value;
        $template_content = $template->field_templating_html->value;
        $results[$view_section] = $template_content;
      }

    }
    return $results;
  }


  function getTemplateEntity($entity, $view_mode)
  {
    $output = $this->getTemplatingDatabase($entity, $view_mode);
    return $output;
  }

  function getEntityFromVariable($var, $entity = null)
  {
    if ($entity == "block"  && isset($var["content"]['#block_content'])) {
      $content = $var["content"];
      $entity_result =  (is_object($content['#block_content']))? $content['#block_content'] : $content['content']['#block_content'];
    } else {

      $entity_result =    isset($var['elements']["#" . $entity])?$var['elements']["#" . $entity]:null;
    }

    return $entity_result;

  }
  function getTemplatingDatabaseCustom($hook_name)
  {
    $theme = $this->is_allowed();
    if (!$theme) {
      return false;
    }
    $output = false;
    $content = $this->getTemplatingByTitle($hook_name);
    if (is_object($content)) {
      $output = $content->field_templating_html->value;
    }

    return $output;
  }

  function getTemplatingDatabase($entity, $mode_view = null)
  {
    $theme = $this->is_allowed();
    if (!$theme) {
      return false;
    }
    if($mode_view == null){
      $mode_view = "full";
    }
    $entity_name = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    $id = $entity->id();
    $output = false;
    $hook_name = $this->formatName($entity_name . '--' . $theme . '-' . $bundle . "-" . $mode_view . ".html.twig");
    $content = $this->getTemplatingByTitle($hook_name);
    if (!is_object($content)) {
      $theme_base = $this->baseTheme($theme);
      $hook_name_base =  $this->formatName($entity_name . '--' . $theme_base . '-' . $bundle . "-" . $mode_view . ".html.twig");
      $content_base = $this->getTemplatingByTitle($hook_name_base);
      if (is_object($content_base)) {
        $content = $content_base;
      }
    }
    $hook_name_id = $this->formatName($entity_name . '--' . $theme . '-' . $bundle . "-" . $id . "-" . $mode_view . ".html.twig");
    $content_id = $this->getTemplatingByTitle($hook_name_id);
    if (is_object($content_id)) {
      $content = $content_id;
    }
    if (is_object($content)) {
      $output = $content->field_templating_html->value;
    }

    return $output;
  }

  function getTemplatingPreview($entity, $template)
  {
    $theme = $this->is_allowed();
    if (!$theme) {
      return false;
    }
    $output = $template->field_templating_html->value;
    return $output;
  }

  public function getLastEntityContent($bundle, $type = 'block_content')
  {
    $block_id = 0;
    $query = \Drupal::entityQuery($type);
    $query->condition('type', $bundle);
    $query->range(0, 1);
    $res = $query->execute();
    if (!empty($res)) {
      $block_id = end($res);
      return \Drupal::entityTypeManager()->getStorage($type)->load($block_id);
    }
    return false;

  }

  function generateLibrary()
  {
    $libs = $this->getLibrary();
    $output = [];
    if ($libs) {
      $url = Url::fromRoute('<current>');
      $str_url = \Drupal::service('path_alias.manager')->getAliasByPath($url->toString());
      foreach ($libs as $node) {

        $current_theme = \Drupal::theme()->getActiveTheme();
        $theme = $current_theme->getName();

        $mytheme = $node->field_theme->value;
        $type = $node->field_lib_type->value;
        $position = $node->field_lib_position->value;
        $paths = $node->field_lib_condition->value;
        $array_paths = explode(PHP_EOL, $paths);
        $array_paths = array_map(
          function ($item) {
            return is_string($item) ? trim($item) : $item;
          },
          $array_paths
        );
        $allowed_path = false;
        if (in_array("*", $array_paths)) {
          $allowed_path = true;
        }
        if (in_array($str_url, $array_paths)) {
          $allowed_path = true;
        }
        if ($allowed_path && $mytheme == $theme) {
          $is_external = $this->is_field_ready($node, 'field_lib_url');
          $url = false;
          // if external or internal
          if ($is_external) {
            $url = trim($node->field_lib_url->value);
          } else {
            $is_file = $this->is_field_ready($node, 'field_lib_file');
            if ($is_file) {
              $file = File::load($node->field_lib_file->target_id);
              if (is_object($file)) {
                $url = URl::fromUri(file_create_url($file->getFileUri()))->toString();
              }
            }
          }
          // if css or js
          if ($url) {
            if(!isset($output[$type . '_' . $position])){ $output[$type . '_' . $position] = "" ;}
            if ($type == "css") {
              $output[$type . '_' . $position] = $output[$type . '_' . $position] . '<link rel="stylesheet" href="' . $url . '" crossorigin="" />';
            }
            if ($type == "js") {
              $output[$type . '_' . $position] = $output[$type . '_' . $position] . '<script src="' . $url . '" crossorigin=""></script>';
            }
          }

        }
      }

    }
    return $output;
  }

  function getLibrary()
  {
    $results = false;
    $libs = \Drupal::entityQuery('node')
      ->condition('type', 'library')
      ->condition('status', '1')
      ->execute();
    $results = [];
    if (!empty($libs)) {
      foreach ($libs as $id) {
        $node = \Drupal::entityTypeManager()->getStorage('node')->load($id);
        if ($node->status && $node->status->value == 1) {
          $results[$id] = $node;
        }
      }

    }
    return $results;
  }

  function htmlPage()
  {
    $node = \Drupal::request()->attributes->get('node');
    $is_ready = $this->is_field_ready($node, 'body');
    if ($node && $node->bundle() == 'html_page' && $is_ready) {
      $body = $node->body->value;
      $var['entity'] = $node;
      return [
        '#type' => 'inline_template',
        '#template' => $body,
        '#context' => $var
      ];
    }
    return null;
  }

  public static function importFinishedCallback($success, $results, $operations)
  {
    if ($success) {
      $message = t('Template export successfully');
      \Drupal::messenger()->addMessage($message);
    }

    return new RedirectResponse(Url::fromRoute('view.templating.page_1')->toString());
  }
  public function getFileNameEntity($template){
    $bundle = $template->field_templating_bundle->value ;
    $entity_name = $template->field_templating_entity_type->value ;
    $bundle =   str_replace('_','-',$bundle);  
    $name_file =  $entity_name ;
    if($bundle ==  'block' || $bundle == 'block_content' 
    || $entity_name == 'block' ||   $entity_name == 'block_content' ){
      $name_file =  'block' ;
    }
    if($bundle == 'page'){
      $name_file =  'page' ;
    }
    return  $name_file ;
  }
  public function topTemplate($template){
    $name = $template->title->value ;
    if (strpos($name, 'block-content') === 0) {
      $name = str_replace('block-content','block',$name);
    }
    $name_file = $this->getFileNameEntity($template);
    $name_css = str_replace('.twig','.css',$name);
    $name_render = str_replace('.','_',$name);
    $txt = '{% extends get_module_path("templating") ~ "/templates/misc/'.$name_file.'.html.twig" %}'.PHP_EOL ;
    $txt = $txt.'{% block templating_content %}'.PHP_EOL ;
    $txt = $txt.'{% set path_css = directory ~ "/templates/templating/css/'.$name_css.'" %}'.PHP_EOL;
    $txt = $txt.'{% set css = include(path_css) %}'.PHP_EOL;
    $txt = $txt.'{{render_css(css,"'.$name_render.'")}}'.PHP_EOL;
    return  $txt ;
  }
  public function footerTemplate($txt) {
    $txt = $txt.PHP_EOL.'{% endblock %}';
    return  $txt ;
  }
  public function exportHtmlTemplating($template){

    $txt = $this->topTemplate($template);
    $file_path = $this->getFilepathTemplating($template);
    $txt = $txt.$template->field_templating_html->value;
    
    $myfile = fopen($file_path, "wr") or \Drupal::logger('templating')->error($file_path . "can not write");
    $txt = $this->footerTemplate($txt);
    fwrite($myfile, $txt);
    fclose($myfile);
      $message = 'Template html in '.$file_path.' export successfully';
      \Drupal::messenger()->addMessage($message);

  }
  public function exportCssTemplating($template){
    $file_path = $this->getFilepathCSSTemplating($template);
   
    $myfile = fopen($file_path, "wr") or \Drupal::logger('templating')->error($file_path . "can not write");
    $txt = $template->field_templating_css->value;
    fwrite($myfile, $txt);
    fclose($myfile);
     $message = 'Template css in '.$file_path.' export successfully';
      \Drupal::messenger()->addMessage($message);

  }
  public function exportTemplating($template)
  {  //kint($template);die();
   
    $this->exportHtmlTemplating($template);
    $this->exportCssTemplating($template);
    return true;
  }

  public function diff($template)
  {
    $service = \Drupal::service('templating.manager');
    $file = $service->getFilepathTemplating($template);
    $content_html = file_get_contents($file);
    $txt = $template->field_templating_html->value;
    $diffFormatter = \Drupal::service('diff.formatter');

    $from = explode("\n", $content_html);
    $to = explode("\n", $txt);
    $diff = new Diff($from, $to);
    $diffFormatter->show_header = FALSE;
    return $diffFormatter->format($diff);
  }
  public function getTargetBundleForm($variables){
    if(isset($variables["element"]) &&
    isset($variables["element"]["#process"])
    ){
    foreach ($variables["element"]["#process"] as $key => $item) { 
      if(is_array($item)){
        foreach ($item as $key_child => $child) { 
          if($child instanceof EntityFormDisplay) {
            return ($child->getTargetBundle());
          }
        }
      }
    }
    }
    return false ;
  }
  public function getFilepathTemplating($template)
  {
    $file_name = $template->label();
    if (strpos($file_name, 'block-content') === 0) {
      $file_name = str_replace('block-content','block',$file_name);
    }
    $themeHandler = \Drupal::service('theme_handler');
    $themePath = $themeHandler->getTheme($template->field_templating_theme->value)->getPath(); 
    $directory = (DRUPAL_ROOT . '/' . $themePath . '/templates/templating/' );
    if (!is_dir($directory)) {
      if (!mkdir($directory, 0755, true)) {
          $message = "Failed to create directory ".$directory;
          \Drupal::logger('templating')->error( $message);
          return false ;
      }
    }
    return $directory . $file_name;
  }
  public function getFilepathCSSTemplating($template)
  {
    $file_name = $template->label();
    if (strpos($file_name, 'block-content') === 0) {
      $file_name = str_replace('block-content','block',$file_name);
    }
    $file_name = str_replace('.twig','.css',$file_name);
    $themeHandler = \Drupal::service('theme_handler');
    $themePath = $themeHandler->getTheme($template->field_templating_theme->value)->getPath();
    $directory = (DRUPAL_ROOT . '/' . $themePath . '/templates/templating/css/' );
    if (!is_dir($directory)) {
      if (!mkdir($directory, 0755, true)) {
          $message = "Failed to create directory ".$directory;
          \Drupal::logger('templating')->error( $message);
          return false ;
      }
    }
    return  $directory.$file_name;
  }
  public function getRenderTemplateForm($content){
    $output = false;
    $current_theme = \Drupal::theme()->getActiveTheme();
    $theme = $current_theme->getName();
    if(isset($content["element"]) &&
    isset($content["element"]["#entity_type"]) && 
    isset($content["element"]["#process"]) &&
    $content["element"]["#form_id"] != "user_register_form" &&
    $content["element"]["#form_id"] != "user_login_form" &&
    $content["element"]["#form_id"] != "user_pass_form"
    ){
      $entity_type = $content["element"]["#entity_type"];
      $bundle = "";
      foreach ($content["element"]["#process"] as $key => $item) { 
          if(is_array($item)){
            foreach ($item as $key_child => $child) { 
              if($child instanceof EntityFormDisplay) {
                $bundle = ($child->getTargetBundle());
              }
            }
          }
      }
      $hook_name_base = $this->formatName("form--".$entity_type."-". $theme . "-".$bundle."-full.html.twig");   
      $content_base = $this->getTemplatingByTitle($hook_name_base);
      if (is_object($content_base)) {
        $output = $content_base->field_templating_html->value;
      }
    }
    if ($output) {
        return [
          '#type' => 'inline_template',
          '#template' => $output,
          '#context' => [
            'content' => $content
          ],
        ];
    
    }
    return false ;

  }
  public function getRenderTemplateCustom($content)
  {
    $output = false;
    $current_theme = \Drupal::theme()->getActiveTheme();
    $theme = $current_theme->getName();
    if(isset($content['element']) && isset($content['element']['form_id']) && isset($content['element']['form_id']['#id'])){
      $hook_name_base = $this->formatName("custom--".$theme."-".$content['element']['form_id']['#id'].".html.twig");
      $content_base = $this->getTemplatingByTitle($hook_name_base);
      if (is_object($content_base)) {
        $output = $content_base->field_templating_html->value;
      }
    }
    // comment.html.twig
    if (isset($content['comment_body'])) {
      $hook_name_base = $this->formatName("comment--" . $theme . ".html.twig");
      $content_base = $this->getTemplatingByTitle($hook_name_base);
      if (is_object($content_base)) {
        $output = $content_base->field_templating_html->value;
      }
    }
    if ($output) {
      return [
        '#type' => 'inline_template',
        '#template' => $output,
        '#context' => [
          'var_template' => $content
        ],
      ];
    }
    return false;
  }
  function addLibrary(){
    $library_name = 'templating/confirmjs';
    $library_definition = [
      'version' => '1.x',
      'js' => [
        'https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.2/jquery-confirm.min.js' => [],
      ],
      'dependencies' => [
        'core/jquery',
      ],
    ];
  
    // Add or modify the library definition.
    \Drupal::service('library.discovery')->setLibraryInfo($library_name, $library_definition);
  
  }
}
