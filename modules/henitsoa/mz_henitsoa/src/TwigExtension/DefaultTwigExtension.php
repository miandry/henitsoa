<?php

namespace Drupal\mz_henitsoa\TwigExtension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension for mz_henitsoa.
 */
class DefaultTwigExtension extends AbstractExtension {

  /**
   * {@inheritdoc}
   */
  public function getFunctions(): array {
    return [
      new TwigFunction('bulletin', [$this, 'twigBulletin']),
    ];
  }

  /**
   * Build bulletin data structure.
   */
  public function twigBulletin(array $inscription): array {
    $bulletins = [];

    if (!empty($inscription['field_notes'])) {
      foreach ($inscription['field_notes'] as $item) {
        $note = $item['node'];
        $tid = $note->field_matiere->target_id;

        $bulletins[$tid] = [
          'mat'  => $note->field_matiere->entity->label(),
          'tr1'  => $item['title'],
          'coef' => $note->field_coeffience->value,
        ];
      }
    }

    if (!empty($inscription['field_notes_2'])) {
      foreach ($inscription['field_notes_2'] as $item) {
        $note = $item['node'];
        $tid = $note->field_matiere->target_id;

        $bulletins[$tid]['tr2'] = $item['title'];
      }
    }

    if (!empty($inscription['field_notes_3'])) {
      foreach ($inscription['field_notes_3'] as $item) {
        $note = $item['node'];
        $tid = $note->field_matiere->target_id;

        $bulletins[$tid]['tr3'] = $item['title'];
      }
    }

    return $bulletins;
  }

}
