<?php

namespace Drupal\taxonomy_max_depth\Taxonomy;

/**
 * Helper for detecting term ancestor or descendant depth.
 */
interface TermTreeDepthHelperInterface {

  /**
   * Returns length of the longest descendant chain.
   *
   * @param array $tree
   *   The descendant tree of the term.
   *
   * @return int
   *   The longest chain length.
   *
   * @see \Drupal\taxonomy\TermStorageInterface::loadTree()
   */
  public function getMaxDescendantDepth(array $tree): int;

  /**
   * Returns length of the longest ancestor chain.
   *
   * @param \Drupal\taxonomy\TermInterface[] $parents
   *   The parents list of a term.
   *
   * @return int
   *   The longest chain length.
   */
  public function getMaxAncestorDepth(array $parents): int;

}
