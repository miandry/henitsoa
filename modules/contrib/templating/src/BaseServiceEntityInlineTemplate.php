<?php
namespace Drupal\templating;

use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\block_content\BlockContentInterface;
class BaseServiceEntityInlineTemplate
{
   public function baseTheme($theme){
     $enabled_themes = \Drupal::service('theme_handler')->listInfo();
     $themebase = \Drupal::service('theme_handler')->getBaseThemes($enabled_themes, $theme );
     $base = false;
     if(!empty($themebase)){
       $thmes = array_keys($themebase);
       $base = end($thmes) ;
     }
     return  $base;
   }
    public function isAllowed($path_theme)
    {
        $path_array = explode('/', $path_theme);
        if (!empty($path_array)
            && $path_array[0]
            && $path_array[1]
            && $path_array[0] == 'themes' && $path_array[1] == 'custom'
        ) {
            return true;
        } else {
            return false;
        }

    }
    public function generateFile($directory, $filename, $content)
    {
        $fileSystem = \Drupal::service('file_system');
        if (!is_dir($directory)) {
            if ($fileSystem->mkdir($directory, 0777, true) === false) {
                \Drupal::messenger()->addMessage(t('Failed to create directory ' . $directory), 'error');
                return false;
            }
        }
        if (!@chmod($directory . '/' . $filename, 0777)) {
            \Drupal::messenger()->addMessage(t('Failed to change permission file ' . $filename), 'error');
        }
        if (file_put_contents($directory . '/' . $filename, $content) === false) {
            \Drupal::messenger()->addMessage(t('Failed to write file ' . $filename), 'error');
            return false;
        }
        if (@chmod($directory . '/' . $filename, 0777)) {
            //   \Drupal::messenger()->addMessage(t('Failed to change permission file ' . $filename), 'error');
        }
        return true;
    }
    public function renderName($config_name)
    {
        $name = $this->removePrefix($config_name);
        $new = Markup::create($name . ' ( <span style="color:red"> new </span> )');
        $diff = Markup::create($name . ' ( <a href="#"><span style="color:blue"> exist </span></a> ) ');
        return ($this->isExistLocal($config_name)) ? $diff : $new;
    }
    public function isExistLocal($config_name)
    {
        $path = $this->getConfigRootPath();
        $element = DRUPAL_ROOT . $path . '/' . $config_name . '.yml';
        if (file_exists($element)) {
            return true;
        }
        return false;
    }
    public function searchFileInDirectory($key, $directory)
    {
        $path_file = [];
        if (is_dir($directory)) {
            $it = scandir($directory);
            if (!empty($it)) {
                foreach ($it as $fileinfo) {
                    $element = $directory . "/" . $fileinfo;
                    if (is_dir($element) && substr($fileinfo, 0, strlen('.')) !== '.') {
                        $childs = $this->searchFileInDirectory($key, $element);
                        $path_file = array_merge($childs, $path_file);
                    } else {
                        if ($fileinfo && basename($fileinfo) == $key) {
                            if (file_exists($element)) {
                                $path_file[$key] = $element;
                            }
                        }
                    }
                }
            }
        } else {
            \Drupal::messenger()->addMessage(t('No permission to read directory ' . $directory), 'error');
            @chmod($directory, 0777);
        }
        return $path_file;
    }
    public function minify($item, $type = "css")
    {
        if ($type == "css") {
            return $this->minifyCSS($item);
        }
        if ($type == "js") {
            return $this->minifyJS($item);
        }
        return $item;
    }
    public function minifyCSS($css)
    {
        $css = preg_replace('/\/\*((?!\*\/).)*\*\//', '', $css); // negative look ahead
        $css = preg_replace('/\s{2,}/', ' ', $css);
        $css = preg_replace('/\s*([:;{}])\s*/', '$1', $css);
        $css = preg_replace('/;}/', '}', $css);
        return $css;
    }
    public function minifyJS($javascript)
    {
        return preg_replace(array("/\s+\n/", "/\n\s+/", "/ +/"), array("\n", "\n ", " "),
            $javascript);
    }
    public function getThemeList()
    {
        $themes = \Drupal::service('theme_handler')->listInfo();
        return array_keys($themes);

    }
    public function getThemePath($item)
    {
        $config = \Drupal::config($item);
        $theme_name = $config->get('theme');
        $list = \Drupal::service('extension.list.theme')->getList();
        if (in_array($theme_name, $list)) {
            return \Drupal::service('extension.list.theme')->getPath($theme_name);
        } else {
            return false;
        }

    }

    public function is_allowed()
    {
        //$current_theme = \Drupal::theme()->getActiveTheme();
        //$theme = $current_theme->getName();

     // pourquoi ??   
     //   $config = \Drupal::config('system.theme');    
     //   $theme = $config->get('default');
        
        $activeThemeName = \Drupal::service('theme.manager')->getActiveTheme();
        $theme = $activeThemeName->getName();


        $config_settings = \Drupal::config("template_inline.settings");
        $disable = $config_settings->get('disable');
        $themes = $config_settings->get('theme');
        if ($disable) {
            return false;
        }
        if($themes == null ){
            $config = \Drupal::config('system.theme');    
            $theme = $config->get('default');
        }
        if($themes && !in_array($theme ,$themes)){
           return false;
        }
        return $theme;
    }
    public function injectionSpacer($output)
    {
        return "{{spacer_top(content)|raw}}" . $output . "{{spacer_bottom(content)|raw}}";
    }
    public function assetInjection($output, $config)
    {
        if ($config) {
            if ($config->get('css') && $config->get('css') != '') {
                $output = '<style>' . $config->get('css') . '</style>' . $output;
            }
            if ($config->get('js') && $config->get('js') != '') {
                $output = $output . '<script>' . $config->get('js') . '</script>';
            }
        }
        return $output;
    }

    public function formatName($name)
    {
        return str_replace('_', '-', $name);
    }
    public function removePrefix($name)
    {
        return str_replace('template.', '', $name);
    }
    public static function getModeViewList($entity_name)
    {
        $mode_view_list = [];
        $mode_views = \Drupal::entityTypeManager()->getStorage('entity_view_mode')->loadMultiple();
        foreach ($mode_views as $key => $item) {
            $type = $item->getTargetType();
            if ($type == $entity_name) {
                $mode_view_list[str_replace($type . '.', '', $key)] = str_replace($type . '.', '', $key);
            }
        }
        return $mode_view_list;
    }
    public function getRegionList(){
        $result = [];
        $config_settings = \Drupal::config("template_inline.settings") ;
        $allowed_theme = $config_settings->get('theme');
        $allowed_theme = array_values($allowed_theme);
        foreach ($allowed_theme as $theme){
            if(!is_numeric($theme)){
              $system_region = system_region_list($theme, $show = REGIONS_ALL);
              foreach ($system_region as $key => $region){
                    $result[$key] = $key ;
              }
            }
        }
        return $result ;
    }
    public function getAllAsset(){
        $activeThemeName = \Drupal::service('theme.manager')->getActiveTheme();
        $theme = $activeThemeName->getName();
        $list = \Drupal::entityTypeManager()->getStorage('node')
                ->loadByProperties(['status'=>1,'type' => 'templating','field_templating_theme' => $theme]);
        $asset['css'] = "";
        $asset['js'] = "";
        foreach ($list as $item){
            if(is_object($item)){
                $title = $item->label();
                $css = $item->field_templating_css->value ;
                $css = $this->minify(" /* " . $title . " */ " . $css);
                $asset['css'] = $css. $asset['css'];


                $js= $item->field_templating_js->value ;
                $js = $this->minify(" /* " . $title . " */ " . $js);
                $asset['js'] = $js. $asset['js'];
            }
        }
        return  $asset ;
    }
    public function assetCSSTemplateTheme($css,$block_name){
        $config_settings = \Drupal::config("template_inline.settings") ;
        $current_css = $config_settings->get('asset_css');
        $current_css[$block_name] = $css ;
        \Drupal::configFactory()->getEditable('template_inline.settings')
        ->set('asset_css', $current_css)
        ->save();
    }
    public function buildCSSTemplateTheme(){
        $config_settings = \Drupal::config("template_inline.settings") ;
        $current_css = $config_settings->get('asset_css');
        $asset_css = "";
        if(!empty($current_css) ){
            foreach ($current_css as $key=> $item){
                $css = $this->minify(" /* " . $key . " */ " . $item);
                $asset_css = $asset_css.$css;
            }
        }

        return  $asset_css ;
    }
    public function getTemplatingByEntity($entity){

        $theme = $this->is_allowed();
        if(!$theme){
            return false;
        }
        $entity_name  = $entity->getEntityTypeId();
        $bundle = $entity->bundle();
        $id = $entity->id();
        $output = false ;
        $mode_view = 'full';
        $hook_name = $entity_name.'--'.$theme.'-'.$bundle."-".$mode_view.".html.twig" ;
        $node_template = ($this->getTemplatingByTitle($hook_name));
        if(is_object($node_template)){
            return $node_template;
        } else {
            return false ;
        }
    }
    public function getTemplatingById($id){
        
        $array = ['type' => 'templating','status' => true ,'nid' => $id];
        $nodes = \Drupal::entityTypeManager()->getStorage('node')
        ->loadByProperties($array);
        return  end($nodes);
    }
    public function getTemplatingByTitle($hook_name){
        $array = ['type' => 'templating','status' => true ,'title' => $hook_name];
        $nodes = \Drupal::entityTypeManager()->getStorage('node')
        ->loadByProperties($array);
        if ( empty($nodes) && strpos($hook_name, 'block-content--') === 0) {
            $hook_name = str_replace('block-content--','block--', $hook_name);
            $array = ['type' => 'templating','status' => true ,'title' => $hook_name];
            $nodes = \Drupal::entityTypeManager()->getStorage('node')
            ->loadByProperties($array);
        }
        return  end($nodes);
    }
    public function is_template_exist($config_name){
        $array = ['type' => 'templating','status' => true ,'title' => $config_name];
        $nodes = \Drupal::entityTypeManager()->getStorage('node')
        ->loadByProperties($array);
        if ( empty($nodes) && strpos($config_name, 'block--') === 0) {
            $hook_name = str_replace('block--','block-content--', $hook_name);
            $array = ['type' => 'templating','status' => true ,'title' => $hook_name];
            $nodes = \Drupal::entityTypeManager()->getStorage('node')
            ->loadByProperties($array);
        }
        if(!empty($nodes)){return true;}else{return false;}
    }
    public function inputChecker($input){
        if ($input instanceof NodeInterface) {
            return 'node' ;
        }
        if($input instanceof BlockContentInterface){
            return 'block_content';
        }
        return false ;

    }
    public function is_field_ready($entity, $field) {
        $bool = FALSE;
        if (is_object($entity) && $entity->hasField($field)) {
          $field_value = $entity->get($field)->getValue();
          if (!empty($field_value)) {
            $bool = TRUE;
          }
        }
        return $bool;
      }
  public function getNodeByAlias($alias)
  {
    /** @var \Drupal\Core\Path\AliasManager $alias_manager */
    $alias_manager = \Drupal::service('path_alias.manager');
    $parts = explode('+', $alias);
    $alias = implode('/', $parts);

    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    try {
      $path = $alias_manager->getPathByAlias($alias);
      $route = Url::fromUserInput($path);
      if ($route && $route->isRouted()) {
        $params = $route->getRouteParameters();
        if (!empty($params['node'])) {
          return $node_storage->load($params['node']);
        }
      }
    } catch (\Exception $e) {
      return null;
    }
    return null;
  }


}

