<?php

namespace Drupal\templating\Form;
use Drupal\node\Entity\Node;
/**
 * Class TemplatingForm.
 */
class TemplatingForm
{   
    public static function formForm($form){
    $services = \Drupal::service('templating.manager');
    $themes = $services->getThemeList();
    $defaultThemeName = \Drupal::config('system.theme')->get('default');
    $theme_options = [];
    foreach ($themes as $theme) {
        $theme_options[$theme] = $theme;
    }
    $form['theme'] = [
        '#type' => 'select',
        '#title' => t('Theme'),
        '#options' => $theme_options,
        '#required' => true,
        '#default_value' => $defaultThemeName,
    ];
    $form['form_entity'] = [
        '#type' => 'select',
        '#title' => t('Entity'),
        '#options' => ["node"=>"node","user"=>"user"],
        '#required' => true,
    ];

    $bundle_list_name = \Drupal::service('entity_type.bundle.info')->getBundleInfo('node');
    $bundle_options = [];
    foreach (array_keys($bundle_list_name) as $type) {
        $bundle_options[$type] = $type;
    }
    $bundle_options["user"] = "user";
    $form['bundle'] = [
        '#type' => 'select',
        '#title' => t('Bundle'),
        '#options' => $bundle_options,
        '#required' => true,
    ];
    $form['id'] = [
        '#type' => 'textfield',
        '#title' => t('ID'),
        '#description' => t('Leave empty , if you want to apply for all'),
        '#default_value' => '',
    ];
    return $form;
    }
    public static function formFormSubmit($values)
    {
        $config_name = null;
        if (isset($values['form_entity']) &&
            isset($values['bundle']) &&
            isset($values['theme'])) {
            if ($values['id'] != "") {
                $item = \Drupal::entityTypeManager()->getStorage($values['entity'])->load(trim($values['id']));
                if (is_object($item)) {
                    $config_name = "form--".$values['form_entity']."-". $values['theme'] . "-".$values['bundle']."-" . trim($values['id']) . "-full.html.twig";
                }
            } else {
                $config_name = "form--".$values['form_entity']."-". $values['theme'] . "-".$values['bundle']."-full.html.twig";
            }
        }
        return [
            "name" => $config_name,
            "entity_type" => $values['form_entity'],
            "bundle" => $values['bundle']
        ];
    }
    public static function userForm($form){
        $services = \Drupal::service('templating.manager');
        $themes = $services->getThemeList();
        $defaultThemeName = \Drupal::config('system.theme')->get('default');
        $theme_options = [];
        foreach ($themes as $theme) {
            $theme_options[$theme] = $theme;
        }
        $form['theme'] = [
            '#type' => 'select',
            '#title' => t('Theme'),
            '#options' => $theme_options,
            '#required' => true,
            '#default_value' => $defaultThemeName,
        ];
        $mode_views = $services::getModeViewList('user');
        $form['mode_view_user'] = [
            '#type' => 'select',
            '#title' => t('Mode view '),
            '#options' => $mode_views,
            '#required' => true,
        ];
        $form['uid'] = [
            '#type' => 'textfield',
            '#title' => t('UID'),
            '#description' => t('Leave empty , if you want to apply for all users'),
            '#default_value' => '',
        ];
        return $form;
    }
    public static function userFormSubmit($values)
    {
        $config_name = null;
        if (isset($values['mode_view_user']) &&
            isset($values['theme'])) {
            if ($values['uid'] != "") {
                $user = \Drupal::entityTypeManager()->getStorage('user')->load(trim($values['uid']));
                if (is_object($user)) {
                    $config_name = "user--" . $values['theme'] . "-user-" . trim($values['uid']) . "-" . trim($values['mode_view_user']) . ".html.twig";
                }
            } else {
                $config_name = "user--" . $values['theme']  . '-user-' . trim($values['mode_view_user']) . '.html.twig';
            }
        }
        return [
            "name" => $config_name,
            "entity_type" => "user",
            "bundle" => "user"
        ];
    }
    public static function customForm($form){
      $services = \Drupal::service('templating.manager');
      $themes = $services->getThemeList();
      $defaultThemeName = \Drupal::config('system.theme')->get('default');
      $theme_options = [];
      foreach ($themes as $theme) {
        $theme_options[$theme] = $theme;
      }
      $form['theme'] = [
        '#type' => 'select',
        '#title' => t('Theme'),
        '#options' => $theme_options,
        '#required' => true,
        '#default_value' => $defaultThemeName,
      ];
      $form['custom'] = [
        '#type' => 'textfield',
        '#title' => t('Custom name'),
        '#description' => t('Example : comment.html.twig'),
        '#default_value' => '',
      ];
      return $form;
    }

  public static function customFormSubmit($values)
  {
    $config_name = null;
    if (isset($values['custom'])) {
          $config_name = $values['custom'];
    }
    return [
      "name" => $config_name,
      "entity_type" => "custom"
    ];
  }
    public static function nodeForm($form)
    {
        $services = \Drupal::service('templating.manager');
        $themes = $services->getThemeList();
        $defaultThemeName = \Drupal::config('system.theme')->get('default');
        $theme_options = [];
        foreach ($themes as $theme) {
            $theme_options[$theme] = $theme;
        }
        $form['theme'] = [
            '#type' => 'select',
            '#title' => t('Theme'),
            '#options' => $theme_options,
            '#required' => true,
            '#default_value' => $defaultThemeName,
        ];
        $bundle_list_name = \Drupal::service('entity_type.bundle.info')->getBundleInfo('node');
        $bundle_options = [];
        foreach (array_keys($bundle_list_name) as $type) {
            $bundle_options[$type] = $type;
        }
        $form['bundle'] = [
            '#type' => 'select',
            '#title' => t('Content Type'),
            '#options' => $bundle_options,
            '#required' => true,
        ];
        $mode_views = $services::getModeViewList('node');
        $form['mode_view'] = [
            '#type' => 'select',
            '#title' => t('Mode view '),
            '#options' => $mode_views,
            '#required' => true,
        ];
        $form['nid'] = [
            '#type' => 'textfield',
            '#title' => t('NID'),
            '#description' => t('Leave empty , if you want to apply for all node'),
            '#default_value' => '',
        ];
        return $form;

    }
    public static function nodeFormSubmit($values)
    {
        $config_name = null;
        if (isset($values['bundle'])
            && isset($values['mode_view']) &&
            isset($values['theme'])) {
            $type = $values['bundle'];
            if ($values['nid'] != "") {
                $node = \Drupal::entityTypeManager()->getStorage('node')->load(trim($values['nid']));
                if (is_object($node)) {
                    $config_name = "node--" . $values['theme'] . "-" . trim($type) . "-" . trim($values['nid']) . "-" . trim($values['mode_view']) . ".html.twig";
                }
            } else {
                $config_name = "node--" . $values['theme'] . '-' . trim($values['bundle']) . '-' . trim($values['mode_view']) . '.html.twig';
            }
        }
        return [
            "name" => $config_name,
            "entity_type" => "node",
            "bundle" => $type,
        ];
    }
    public static function pageForm($form)
    {
        $query = \Drupal::entityQuery('node')
        ->condition('type', 'page');
        // Execute the query and get node IDs.
        $nids = $query->execute();
        // Load nodes from the retrieved node IDs.
        $nodes = Node::loadMultiple($nids);

        $page_list = [];
        foreach ($nodes as $key => $node) {
          $alias = \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $node->id());
          $alias = str_replace('/', '_', $alias);
          $page_list[$alias] = $node->title->value;
        }
        $services = \Drupal::service('templating.manager');
        $themes = $services->getThemeList();
        $region_list = $services->getRegionList();
        $theme_options = [];
        foreach ($themes as $theme) {
            $theme_options[$theme] = $theme;
        }
        $defaultThemeName = \Drupal::config('system.theme')->get('default');
        $form['theme'] = [
            '#type' => 'select',
            '#title' => t('Themes'),
            '#options' => $theme_options,
            '#required' => true,
            '#default_value' => $defaultThemeName,
        ];
        $form['page_name'] = [
            '#type' => 'select',
            '#title' => t('Page path'),
            '#options' => $page_list,
            '#required' => true,
        ];
        return $form;
    }
    public static function pageFormSubmit($values)
    {
        $config_name = null;
        if (isset($values['page_name']) &&
            isset($values['theme'])) {
            $theme = $values['theme'] ;
            $alias = $values['page_name'];
            $config_name  =   'page__node_'.$theme.'_'.$alias.".html.twig";
        }
        return [
            "name" => $config_name,
            "bundle" => "page",
            "entity_type" => "node"
        ];
    }
    public static function fieldForm($form)
    {
        $block_type_lists = \Drupal::entityTypeManager()->getStorage('block_content_type')->loadMultiple();
        $block_type = ['none' => 'None'];
        foreach ($block_type_lists as $key => $type) {
            $block_type[$key] = $type->label();
        }
        $block_type_lists = \Drupal::entityTypeManager()->getStorage('block')->loadMultiple();
        $list = [];
        foreach ($block_type_lists as $key => $item) {
            $themes[$item->getTheme()] = $item->getTheme();
            $list[] = $item->getPluginId();
        }
        $defaultThemeName = \Drupal::config('system.theme')->get('default');
        $form['theme'] = [
            '#type' => 'select',
            '#title' => t('Themes'),
            '#options' => $themes,
            '#required' => false,
            '#default_value' => $defaultThemeName,
        ];
        $bundle_list_name = \Drupal::service('entity_type.bundle.info')->getBundleInfo('node');
        $bundle_options = [];
        foreach (array_keys($bundle_list_name) as $type) {
            $bundle_options[$type] = $type;
        }
        $form['parent_bundle'] = [
            '#type' => 'select',
            '#title' => t('Parent Content Type'),
            '#options' => $bundle_options,
            '#required' => true,
        ];
        $form['field_name'] = [
            '#type' => 'textfield',
            '#title' => t('Field name'),
            '#description' => t('For example :  field_body'),
        ];
        return $form;
    }
    
    //field submit
    public static function fieldFormSubmit($values)
    {
        $config_name = null;
        if (isset($values['field_name'])
            && isset($values['parent_bundle']) &&
            isset($values['theme'])) {
            $entity_type = 'node';
            $bundle = trim($values['parent_bundle']);
            $field_name = $values['field_name'];
            $config_name = 'field--' . $values['theme'] . '-' . $entity_type . '-' . $bundle . '-' . $field_name . '.html.twig';

        }
        return [
            "name" => $config_name,
            "entity_type" => "field",
            "bundle" => $field_name,
        ];
    }
    //block
    public static function blockForm($form)
    {
        $block_type_lists = \Drupal::entityTypeManager()->getStorage('block_content_type')->loadMultiple();
        $block_type = ['none' => 'None'];
        foreach ($block_type_lists as $key => $type) {
            $block_type[$key] = $type->label();
        }
        $block_type_lists = \Drupal::entityTypeManager()->getStorage('block')->loadMultiple();
        $list = [];
        foreach ($block_type_lists as $key => $item) {
            $themes[$item->getTheme()] = $item->getTheme();
            $list[] = $item->getPluginId();
        }
        $defaultThemeName = \Drupal::config('system.theme')->get('default');
        $form['theme'] = [
            '#type' => 'select',
            '#title' => t('Themes'),
            '#options' => $themes,
            '#required' => false,
            '#default_value' => $defaultThemeName,
        ];
        $form['blocktype'] = [
            '#type' => 'select',
            '#title' => t('Block Custom Type'),
            '#options' => $block_type,
            '#required' => false,
        ];
        $form['blockid'] = [
            '#type' => 'textfield',
            '#title' => t('Plugin ID or Block ID'),
            '#description' => t('Leave empty , if you want to apply for all block'),
        ];

        return $form;
    }
    public static function blockFormSubmit($values)
    {
        $config_name = null;
        if (isset($values['blocktype'])
            && isset($values['blockid']) &&
            isset($values['theme'])) {
            if ($values['blockid'] != "") {
                $block_custom = \Drupal::entityTypeManager()->getStorage('block_content')->load(trim($values['blockid']));
                if (is_object($block_custom)) {
                    $config_name = "block--" . $values['theme'] . "-" . trim($values['blocktype']) . "-" . trim($values['blockid']) . "-full.html.twig";
                }
            } else {
                $config_name = "block--" . $values['theme'] . '-' . trim($values['blocktype'] . '-full.html.twig');
            }
        }
        return [
            "name" => $config_name,
            "entity_type" => "block_content",
            "bundle" => trim($values['blocktype']),
        ];
    }

    public static function defaultContent($type = 'default')
    {
        $template = '<div class="templating">{{ content }}</div>';
        if ($type == 'page') {
            $template = '<div class="templating">{{ content }}</div>';
        }
        return $template;
    }
    public static function viewForm1($form)
    {
        $all_views = \Drupal::entityTypeManager()->getStorage('view')->loadMultiple();
        foreach ($all_views as $key => $view) {
            $view_list[$key] = $view->label();
        }
        $form['view_name'] = [
            '#type' => 'select',
            '#title' => t('View name'),
            '#options' => $view_list,
            '#required' => true,
        ];
        return $form;
    }
    public static function viewForm2($form, $element)
    {
        $services = \Drupal::service('templating.manager');
        $themes = $services->getThemeList();

        if (isset($element['view_name'])) {
            $services = \Drupal::service('templating.manager');
            $themes = $services->getThemeList();
            $defaultThemeName = \Drupal::config('system.theme')->get('default');
            $theme_options = [];
            foreach ($themes as $theme) {
                $theme_options[$theme] = $theme;
            }
            $form['theme'] = [
                '#type' => 'select',
                '#title' => t('Theme'),
                '#options' => $theme_options,
                '#required' => true,
                '#default_value' => $defaultThemeName,
            ];

            $form['view_name'] = [
                '#type' => 'textfield',
                '#title' => t('View name selected'),
                '#default_value' => $element['view_name'],
            ];

            $view = \Drupal::entityTypeManager()->getStorage('view')->load($element['view_name']);
            $all_display = $view->toArray()['display'];
            foreach (array_keys($all_display) as $v) {
                $view_list[$v] = $v;
            }
            $form['view_display'] = [
                '#type' => 'select',
                '#title' => t('View display name'),
                '#options' => $view_list,
                '#required' => true,
            ];
            $view_section = [
                'rows' => 'rows',
                'exposed' => 'exposed',
                'footer' => 'footer',
                'pager' => 'pager',
                'header' => 'header',
                'title'=>'title',
                'empty'=>'empty'
            ];
            $form['view_section'] = [
                '#type' => 'select',
                '#title' => t('View section'),
                '#options' => $view_section,
                '#required' => true,
            ];
        }

        return $form;
    }
    public static function viewFormSubmit($values)
    {
        $config_name = null;
        if (isset($values['view_display'])
            && isset($values['view_name']) &&
            isset($values['theme'])) {
            $config_name = "view--" . $values['theme'] . '-' . trim($values['view_name']) . '-' . trim($values['view_display']) . '-' . trim($values['view_section']) . '.html.twig';
        }
        return [
            "name" => $config_name,
            "entity_type" => "view",
            "bundle" => trim($values['view_display']),
            "mode_view" =>  trim($values['view_section'])
        ];
    }

}
