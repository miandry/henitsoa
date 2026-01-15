<?php

namespace Drupal\shs\Utility;

use Drupal\taxonomy\Entity\Term;

/**
 * Handle taxonomy lineage sets, e.g section hierarchical select style lineages.
 *
 * This class borrows key functions from hierarchical select
 * to calculate and allow saving the term lineage.
 */
class TermLineage {

  /**
   * Implementation of hook_hierarchical_select_root_level().
   *
   * @param array $params
   *   Array of options.
   */
  public function getRootLevel(array $params) {
    if (!isset($params['vid'])) {
      return [];
    }
    // Get first level terms in vocabulary for base.
    $terms = \Drupal::service('entity_type.manager')
      ->getStorage('taxonomy_term')
      ->loadTree($params['vid'], 0, 1);

    // If the root_term parameter is enabled, then prepend a fake "<root>" term.
    if (isset($params['root_term']) && $params['root_term'] === TRUE) {
      $root_term = new StdClass();
      $root_term->tid = 0;
      $root_term->name = '<' . t('root') . '>';
      $terms = array_merge([$root_term], $terms);
    }
    // Format array.
    return $this->formatTermOptions($terms);
  }

  /**
   * Helper function to construct the lineages given a set of selected items.
   *
   * @param array $selection
   *   Array of taxonomy term ids.
   * @param array $params
   *   Optional. An array of parameters, including vid = machine name.
   *
   * @return array
   *   An array of taxonomy term lineages.
   *
   * @credit Based on Hierarchical Select from Drupal 7
   */
  public function getLineages(array $selection, array $params): array {
    // We have to reconstruct all lineages from the given set of selected items.
    // That means: we have to reconstruct every possible combination!
    $lineages = [];
    $root_level = $this->getRootLevel($params);
    $level = -1;
    $stored_parents = [];

    foreach ($selection as $item) {
      // Create new lineage if the item can be found in the root level.
      if (array_key_exists($item, $root_level)) {
        $level++;
        $lineages[$level][0] = [
          'value' => $item,
          'label' => $root_level[$item],
        ];
        $children = $this->getChildren($item, $params);
        // Try to find all the selected term which is under current root term.
        foreach ($selection as $item2) {
          if (!isset($stored_parents[$item2]) && isset($children[$item2])) {
            $stored_parents[$item2] = [
              'parent' => $lineages[$level][0],
              'label' => $children[$item2],
            ];
          }
        }
      }
      // Add the term in current level when it's children of the parent term.
      elseif (isset($children[$item])) {
        $lineage = ['value' => $item, 'label' => $children[$item]];
        $lineages[$level][] = $lineage;
        // Try to find all the selected term which is under current term.
        $children = $this->getChildren($item, $params);
        foreach ($selection as $key2 => $item2) {
          if (!isset($stored_parents[$item2]) && isset($children[$item2])) {
            $stored_parents[$item2] = [
              'parent' => $lineage,
              'label' => $children[$item2],
            ];
          }
        }
      }
      // If the current term can't be found in the root level and not children
      // of the previous term. That means: Current term sharing the same parent
      // of the previous term and we stored the information already.
      elseif (isset($stored_parents[$item])) {
        $level++;
        $lineage = [
          'value' => $item,
          'label' => $stored_parents[$item]['label'],
        ];
        $lineages[$level][] = $lineage;
        // Try to find all the selected term which is under current term.
        $children = $this->getChildren($item, $params);
        foreach ($selection as $key2 => $item2) {
          if (!isset($stored_parents[$item2]) && isset($children[$item2])) {
            $stored_parents[$item2] = [
              'parent' => $lineage,
              'label' => $children[$item2],
            ];
          }
        }
        // Find all the parent terms.
        while (isset($stored_parents[$item])) {
          if (isset($stored_parents[$item]['parent'])) {
            $lineages[$level][] = [
              'value' => $stored_parents[$item]['parent']['value'],
              'label' => $stored_parents[$item]['parent']['label'],
            ];
            $item = $stored_parents[$item]['parent']['value'];
          }
          else {
            break;
          }
        }
        $lineages[$level] = array_reverse($lineages[$level]);
      }
      // No parent term found for current item, let's find them.
      elseif ($term = Term::load($item)) {
        $level++;
        $lineage = ['value' => $item, 'label' => $term->getName()];
        $lineages[$level][] = $lineage;
        // Try to find all the selected term which is under current term.
        $children = $this->getChildren($item, $params);
        foreach ($selection as $key2 => $item2) {
          if (!isset($stored_parents[$item2]) && isset($children[$item2])) {
            $stored_parents[$item2] = [
              'parent' => $lineage,
              'label' => $children[$item2],
            ];
          }
        }
        if ($parents = $this->getParents($item)) {
          foreach ($parents as $parent_tid => $parent_name) {
            if ($parent_tid === $item) {
              continue;
            }
            $lineages[$level][] = [
              'value' => $parent_tid,
              'label' => $parent_name,
            ];
          }
        }
        $lineages[$level] = array_reverse($lineages[$level]);
      }
    }
    return $lineages;
  }

  /**
   * Get parent terms by tid.
   *
   * @param int $tid
   *   Term ID.
   *
   * @return array
   *   List of term parent tid => name.
   */
  public function getParents(int $tid): array {
    $ancestors = \Drupal::service('entity_type.manager')
      ->getStorage('taxonomy_term')
      ->loadAllParents($tid);
    $parents = [];
    foreach ($ancestors as $ancestor) {
      $parents[$ancestor->id()] = $ancestor->getName();
    }
    return $parents;
  }

  /**
   * Implementation of hook_hierarchical_select_children().
   */
  public function getChildren($parent, $params) {
    if (isset($params['root_term']) && $params['root_term'] && $parent === 0) {
      return [];
    }

    // Get child terms under parent.
    $terms = \Drupal::service('entity_type.manager')
      ->getStorage('taxonomy_term')
      ->loadTree($params['vid'], $parent);

    return $this->formatTermOptions($terms);
  }

  /**
   * Get the child term leaves from each lineage.
   *
   * @param array $lineages
   *   Term lineage data structure.
   *
   * @return array
   *   Lineage leaf term tid => name.
   */
  public function getLineageLeaves(array $lineages) {
    $leaves = [];
    if (empty($lineages)) {
      return $leaves;
    }
    foreach ($lineages as $key => $lineage) {
      $leaf = array_pop($lineage);
      $leaves[$leaf['value']] = $leaf['label'];
    }
    return $leaves;
  }

  /**
   * Get the child term leaves from each lineage.
   *
   * @param array $lineages
   *   Term lineage data structure.
   *
   * @return array
   *   Flat list of lineage tids.
   */
  public function getFlatLineage(array $lineages): array {
    $tids = [];
    if (empty($lineages)) {
      return $tids;
    }
    foreach ($lineages as $lineage) {
      foreach ($lineage as $leaf) {
        $tids[$leaf['value']] = $leaf['label'];
      }
    }
    return $tids;
  }

  /**
   * Transform an array of terms into an associative array of options.
   *
   * For use in a select form item.
   *
   * @param \Drupal\taxonomy\TermInterface[]|object[] $terms
   *   An array of term objects.
   *
   * @return array
   *   An associative array of options, keys are tids, values are term names.
   */
  public function formatTermOptions(array $terms): array {
    $options = [];
    foreach ($terms as $term) {
      if (isset($term->tid, $term->name)) {
        $options[$term->tid] = $term->name;
      }
      else {
        $options[$term->id()] = $term->getName();
      }
    }
    return $options;
  }

}
