<?php

namespace Drupal\templating\TwigExtension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension for templating module.
 */
class DefaultTwigExtension extends AbstractExtension {

  /**
   * {@inheritdoc}
   */
  public function getFunctions(): array {
    return [
      new TwigFunction('spacer_top', [self::class, 'spacer_top_twig'], ['is_safe' => ['html']]),
      new TwigFunction('spacer_bottom', [self::class, 'spacer_bottom_twig'], ['is_safe' => ['html']]),
      new TwigFunction('file_exists', [self::class, 'file_exists_twig']),
      new TwigFunction('template', [self::class, 'template_twig']),
      new TwigFunction('render_inline_template', [self::class, 'render_inline_template_twig']),
      new TwigFunction('render_template', [self::class, 'render_template']),
      new TwigFunction('render_template_block', [self::class, 'render_template_block_twig']),
      new TwigFunction('render_page_inline_template', [self::class, 'render_page_inline_template_twig']),
      new TwigFunction('path_templating', [self::class, 'path_templating']),
      new TwigFunction('DRUPAL_ROOT', [self::class, 'DRUPAL_ROOT_TWIG']),
      new TwigFunction('render_css', [self::class, 'render_css_twig']),
      new TwigFunction('render_template_user', [self::class, 'render_template_user']),
      new TwigFunction('render_template_form', [self::class, 'render_template_form']),
      new TwigFunction('include_template', [self::class, 'include_template_twig']),
    ];
  }

  /* ======================
   * TWIG FUNCTIONS
   * ====================== */

  public static function path_templating(): string {
    return \Drupal::service('module_handler')
      ->getModule('templating')
      ->getPath();
  }

  public static function DRUPAL_ROOT_TWIG(): string {
    return \Drupal::root();
  }

  public static function file_exists_twig(string $file_path): bool {
    return file_exists(\Drupal::root() . '/' . ltrim($file_path, '/'));
  }

  public static function render_css_twig($css, $block_name): void {
    \Drupal::service('templating.manager')
      ->assetCSSTemplateTheme($css, $block_name);
  }

  public static function render_template($content) {
    return \Drupal::service('templating.manager')
      ->getRenderTemplateCustom($content);
  }

  public static function render_template_form($content) {
    return \Drupal::service('templating.manager')
      ->getRenderTemplateForm($content);
  }

  public static function include_template_twig($id, array $var = []) {
    $service = \Drupal::service('templating.manager');

    $template = is_numeric($id)
      ? $service->getTemplatingById($id)
      : $service->getTemplatingByTitle($id);

    if (!is_object($template)) {
      return [
        '#type' => 'inline_template',
        '#template' => '<b>Template custom not found</b>',
        '#context' => ['var' => $var],
      ];
    }

    return [
      '#type' => 'inline_template',
      '#template' => $template->field_templating_html->value,
      '#context' => ['var' => $var],
    ];
  }

  /* spacer_top_twig, spacer_bottom_twig, render_template_block_twig,
     render_page_inline_template_twig, render_inline_template_twig,
     template_twig
     👉 inchangés fonctionnellement
  */

}
