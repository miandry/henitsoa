<?php

namespace Drupal\Tests\entityreference_filter\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\entityreference_filter\Traits\EntityReferenceFilterTrait;

/**
 * Tests entityreference filter behavior.
 *
 * @group entityreference_filter
 */
abstract class EntityReferenceFunctionalJavascriptTestBase extends WebDriverTestBase {

  use EntityReferenceFilterTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Node type article.
   *
   * @var string
   */
  public static $nodeTypeArticle = 'article';

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_taxonomy_glossary'];

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'node',
    'taxonomy',
    'views',
    'entityreference_filter',
    'entityreference_filter_test_config',
  ];

  /**
   * Views to import.
   *
   * @var array
   */
  public $viewsToCreate = [
    'test_view',
    'test_entityreference_view_terms',
  ];

  /**
   * {@inheritDoc}
   */
  public function setUp() {
    parent::setUp();

    $this->contentPrepare();
    $this->createTestViews($this->viewsToCreate);
  }

}
