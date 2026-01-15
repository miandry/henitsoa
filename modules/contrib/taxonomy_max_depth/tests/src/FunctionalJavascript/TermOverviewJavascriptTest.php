<?php

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\taxonomy_max_depth\Traits\TaxonomyTestTrait;

/**
 * Tests table drag JavaScript settings limited by max depth settings.
 *
 * @group taxonomy_max_depth
 */
class TermOverviewJavascriptTest extends WebDriverTestBase {

  use TaxonomyTestTrait;

  protected static $modules = ['taxonomy_max_depth'];

  protected $defaultTheme = 'stark';

  protected $vocabulary;

  protected $settingsWriter;

  protected function setUp(): void {
    parent::setUp();

    $this->vocabulary = $this->createVocabulary();
    $this->settingsWriter = $this->container
      ->get('taxonomy_max_depth.vocabulary_settings_writer');

    $user = $this->createUser(['administer taxonomy']);
    $this->drupalLogin($user);
  }

  protected function getDrupalSettingsAncestorLimit() {
    $settings = $this->getDrupalSettings();

    $taxonomy_settings = $settings['tableDrag']['taxonomy'];
    $this->assertArrayHasKey('term-depth', $taxonomy_settings);

    if (!array_key_exists('term-parent', $taxonomy_settings)) {
      // In case there is no term-parent item, the parent-child relationship is
      // disabled, which means 0 ancestor limit.
      return 0;
    }

    $item = reset($taxonomy_settings['term-parent']);
    $this->assertSame('parent', $item['relationship']);

    // Normalize zero (no limit) to NULL.
    return $item['limit'] ?: NULL;
  }

  public function testSettings() {
    // Create a couple of terms just to make sure the table drag is initialized.
    for ($i = 0; $i < 2; $i++) {
      $this->createTerm($this->vocabulary, [
        'parent' => [0],
      ]);
    }

    $this->settingsWriter->setMaxAncestorDepth($this->vocabulary, 1);
    $this->vocabulary->save();
    $this->drupalGet('/admin/structure/taxonomy/manage/' . $this->vocabulary->id() . '/overview');
    $limit = $this->getDrupalSettingsAncestorLimit();
    $this->assertSame(1, $limit);

    $this->settingsWriter->setMaxAncestorDepth($this->vocabulary, 0);
    $this->vocabulary->save();
    $this->drupalGet('/admin/structure/taxonomy/manage/' . $this->vocabulary->id() . '/overview');
    $limit = $this->getDrupalSettingsAncestorLimit();
    $this->assertSame(0, $limit);

    $this->settingsWriter->setMaxAncestorDepth($this->vocabulary, NULL);
    $this->vocabulary->save();
    $this->drupalGet('/admin/structure/taxonomy/manage/' . $this->vocabulary->id() . '/overview');
    $limit = $this->getDrupalSettingsAncestorLimit();
    $this->assertSame(NULL, $limit);
  }

}
