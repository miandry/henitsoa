<?php

namespace Drupal\Tests\taxonomy_max_depth\Traits;

use Drupal\Component\Render\MarkupInterface;

/**
 * Provides copy of the trait removed in 9.1.x.
 *
 * @todo Stop using it as soon as we drop core 9.0 and lower support.
 */
trait AssertHelperTrait {

  /**
   * Casts MarkupInterface objects into strings.
   *
   * @param string|array $value
   *   The value to act on.
   *
   * @return mixed
   *   The input value, with MarkupInterface objects casted to string.
   */
  protected static function castSafeStrings($value) {
    if ($value instanceof MarkupInterface) {
      $value = (string) $value;
    }
    if (is_array($value)) {
      array_walk_recursive($value, function (&$item) {
        if ($item instanceof MarkupInterface) {
          $item = (string) $item;
        }
      });
    }
    return $value;
  }

}
