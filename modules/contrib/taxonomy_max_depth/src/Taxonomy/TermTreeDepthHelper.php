<?php

namespace Drupal\taxonomy_max_depth\Taxonomy;

/**
 * Helper for detecting term ancestor or child depth.
 */
class TermTreeDepthHelper implements TermTreeDepthHelperInterface {

  /**
   * {@inheritdoc}
   */
  public function getMaxAncestorDepth(array $parents): int {
    $max_depth = 0;

    $terms = $parents;
    while (!empty($terms)) {
      $max_depth++;

      $level_parents = [];
      foreach ($terms as $term) {
        /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $item_list */
        $item_list = $term->get('parent');
        $term_parents = $item_list->referencedEntities();
        $level_parents = array_merge($level_parents, array_values($term_parents));
      }

      $terms = $level_parents;
    }

    return $max_depth;
  }

  /**
   * {@inheritdoc}
   */
  public function getMaxDescendantDepth(array $tree): int {
    $max_depth = 0;

    foreach ($tree as $item) {
      $item_depth = $item->depth + 1;
      if ($item_depth > $max_depth) {
        $max_depth = $item_depth;
      }
    }

    return $max_depth;
  }

}
