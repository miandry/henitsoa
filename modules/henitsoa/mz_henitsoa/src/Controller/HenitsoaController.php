<?php

namespace Drupal\mz_henitsoa\Controller;

use Drupal\Core\Controller\ControllerBase;
/**
 * Class HenitsoaController.
 */
class HenitsoaController extends ControllerBase {
  /**
   * Carnet.
   * @return string
   * Return Hello string.
   */
  public function build() {   
    $id = \Drupal::request()->query->get('id'); 
    if($id){
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($id);
      $service = \Drupal::service('carnet_henitsoa'); 
      $service->carnet($node);
      return [
        '#type' => 'markup',
        '#markup' => $this->t('Implement method: carnet')
      ];     
    }else{
      return [
        '#type' => 'markup',
        '#markup' => $this->t('NO ID document')
      ];
    }

  }

}
