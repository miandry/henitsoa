<?php

namespace Drupal\Tests\taxonomy_max_depth\Functional\Form;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\taxonomy_max_depth\Traits\TaxonomyTestTrait;

abstract class FormTestBase extends BrowserTestBase {

  use TaxonomyTestTrait;

  protected static $modules = ['taxonomy_max_depth'];

  protected $defaultTheme = 'stark';

  protected function setUp(): void {
    parent::setUp();

    $user = $this->createUser(['administer taxonomy']);
    $this->drupalLogin($user);
  }

  protected function assertSuccessMessageContains(string $text) {
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-messages] :not([role="alert"])', $text);
  }

  protected function assertErrorMessageContains(string $text) {
    $this->assertSession()
      ->elementTextContains('css', '[data-drupal-messages] [role="alert"]', $text);
  }

}
