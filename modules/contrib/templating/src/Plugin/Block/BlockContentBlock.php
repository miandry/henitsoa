<?php

namespace Drupal\templating\Plugin\Block;

use Drupal\block_content\Plugin\Block\BlockContentBlock as CoreBlockContent;
use Drupal\Core\Form\FormStateInterface;

/**
 *
 */
class BlockContentBlock extends CoreBlockContent {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    // Hide display title in block config.
    $form['label_display']['#access'] = FALSE;
    $form['label_display']['#default_value'] = NULL;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $config = $this->getConfiguration();
    $uuid = $this->getDerivativeId();
    /** @var \Drupal\block_content\Entity\BlockContent $entity */
    if ($id = $this->uuidLookup->get($uuid)) {
      $entity = $this->entityTypeManager->getStorage('block_content')->load($id);
      $this->configuration['vid'] = $entity->getRevisionId();
      $this->configuration['block_id'] = $entity->id();
    }
    else {
      $this->configuration['vid'] = 0;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntity() {
    $uuid = $this->getDerivativeId();
    if (!isset($this->blockContent)) {
      $config = $this->getConfiguration();
      if (!empty($config['vid'])) {
        $vid = $config['vid'];
        $this->blockContent = $this->entityTypeManager->getStorage('block_content')->loadRevision($vid);
      }
      if (empty($this->blockContent)) {
        if ($id = $this->uuidLookup->get($uuid)) {
          $this->blockContent = $this->entityTypeManager->getStorage('block_content')->load($id);
        }
      }
    }
    return $this->blockContent;
  }

  /**
   *
   */
  public function getBlockContentEntity() {
    return $this->getEntity();
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = parent::build();
    $entity = $this->getEntity();
    if (!empty($entity->shared_type->value)) {
      $build['#attributes']['class'][] = $entity->shared_type->value;
    }
    return $build;
  }

}
