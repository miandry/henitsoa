<?php

namespace Drupal\Tests\entityreference_filter\Functional\Views;

use Drupal\Tests\entityreference_filter\Functional\EntityReferenceFunctionalTestBase;

/**
 * Tests entityreference filter behavior in views.
 *
 * @group entityreference_filter
 */
class EntityReferenceFilterViewsResultTest extends EntityReferenceFunctionalTestBase {

  /**
   * Tests filter options with no arguments.
   */
  public function testFilterOptionsWithoutArguments() {
    $web_assert = $this->assertSession();

    $this->drupalGet('test-view-no-args');
    $web_assert->statusCodeEquals('200');

    $field_id = 'edit-field-taxonomy-reference-target-id-entityreference-filter';
    $web_assert = $this->assertSession();
    $web_assert->selectExists($field_id);
    $web_assert->optionExists($field_id, 'All');
    $web_assert->optionExists($field_id, '1');
    $web_assert->optionExists($field_id, '2');
    $web_assert->optionExists($field_id, '3');
    $web_assert->optionExists($field_id, '4');
    $web_assert->optionNotExists($field_id, '5');
    $options = $this->getOptions($field_id);
    $this->assertCount(5, $options);
  }

  /**
   * Tests filter options with url arguments.
   */
  public function testFilterOptionsWithUrlArguments() {
    $url_1 = 'test-view-arg-url/1';
    $url_2 = 'test-view-arg-url/5';
    $this->dynamicFilterOptionsWithArgumentsTest($url_1, $url_2);
  }

  /**
   * Tests filter options with context arguments.
   */
  public function testFilterOptionsWithContextualArguments() {
    $url_1 = 'test-view-arg-contextual/1';
    $url_2 = 'test-view-arg-contextual/5';
    $this->dynamicFilterOptionsWithArgumentsTest($url_1, $url_2);
  }

  /**
   * Common test method.
   *
   * @param string $url_1
   *   URL 1 to visit.
   * @param string $url_2
   *   URL 2 to visit.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function dynamicFilterOptionsWithArgumentsTest($url_1, $url_2) {
    $web_assert = $this->assertSession();
    $this->drupalGet($url_1);
    $web_assert->statusCodeEquals('200');

    // 2 options are presented: `1` and `All`.
    $field_id = 'edit-field-taxonomy-reference-target-id-entityreference-filter';
    $web_assert = $this->assertSession();
    $web_assert->selectExists($field_id);
    $web_assert->optionExists($field_id, 'All');
    $web_assert->optionExists($field_id, '1');
    $options = $this->getOptions($field_id);
    $this->assertCount(2, $options);

    // 1 option is presented: `All` and the form field is hidden.
    $web_assert = $this->assertSession();
    $this->drupalGet($url_2);
    $web_assert->statusCodeEquals('200');
    $field_id = 'edit-field-taxonomy-reference-target-id-entityreference-filter';
    $web_assert->selectExists($field_id);
    $elements = $this->cssSelect('.hidden select#edit-field-taxonomy-reference-target-id-entityreference-filter');
    $this->assertEqual(1, count($elements));
    $web_assert->optionExists($field_id, 'All');
  }

}
