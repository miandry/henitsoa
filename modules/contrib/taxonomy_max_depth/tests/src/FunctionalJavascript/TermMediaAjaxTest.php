<?php

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\taxonomy_max_depth\Traits\TaxonomyTestTrait;

/**
 * Tests AJAX requests on the term form.
 *
 * @group taxonomy_max_depth
 */
class TermMediaAjaxTest extends WebDriverTestBase {

  use TaxonomyTestTrait;

  const VID = 'media_ajax_test';

  protected static $modules = ['taxonomy_max_depth_media_test'];

  protected $defaultTheme = 'stark';

  protected $vocabulary;

  protected $settingsWriter;

  protected function setUp(): void {
    parent::setUp();

    $this->vocabulary = $this->container->get('entity_type.manager')
      ->getStorage('taxonomy_vocabulary')
      ->load(static::VID);
    $this->settingsWriter = $this->container
      ->get('taxonomy_max_depth.vocabulary_settings_writer');

    $user = $this->createUser(['administer taxonomy']);
    $this->drupalLogin($user);
  }

  public function testTermFormAjax() {
    $term = $this->createTerm($this->vocabulary, [
      'parent' => [0],
    ]);
    $this->settingsWriter->setMaxAncestorDepth($this->vocabulary, 1);
    $this->vocabulary->save();

    $this->drupalGet('/taxonomy/term/' . $term->id() . '/edit');

    // Make sure the first auto-complete input exists and the second doesn't.
    $this->assertSession()
      ->elementExists('css', 'input[name="field_media[0][target_id]"]');
    $this->assertSession()
      ->elementNotExists('css', 'input[name="field_media[1][target_id]"]');

    // Make sure button exists on the page.
    $page = $this->getSession()
      ->getPage();
    $button = $page->findButton('edit-field-media-add-more');
    $this->assertNotNull($button);

    // Submit an AJAX request and let it complete.
    $button->click();
    $this->assertSession()
      ->assertWaitOnAjaxRequest();
    $this->htmlOutput($page->getHtml());

    // Make sure the second input element appeared after AJAX request.
    $this->assertSession()
      ->elementExists('css', 'input[name="field_media[1][target_id]"]');
  }

}
