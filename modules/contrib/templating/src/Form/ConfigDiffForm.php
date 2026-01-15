<?php

namespace Drupal\templating\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Url;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Component\Diff\Diff;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\Config\FileStorage;
/**
 * Class ConfigDiffForm.
 */
class ConfigDiffForm extends FormBase
{


//  /**
//   * {@inheritdoc}
//   */
    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'templating_diff_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    { $nid = \Drupal::request()->query->get('nid');
      $rows=[];
      if($nid){
      $template =  \Drupal::entityTypeManager()->getStorage('node')->load($nid);

      $service = \Drupal::service('templating.manager');
      $rows =  $service->diff( $template );
        // Add the CSS for the inline diff.
        $form['#attached']['library'][] = 'system/diff';
      $form['text'] = [
        '#markup' => '<h4>Template : '.$template->label().'</h4>',
      ];
      }
        $form['diff'] = [
          '#type' => 'table',
          '#attributes' => [
            'class' => ['diff'],
          ],
          '#header' => [
            ['data' => t('FILE'), 'colspan' => '2'],
            ['data' => t('Template'), 'colspan' => '2'],
          ],
          '#rows' => $rows,
          '#empty' => $this->t('No diff found')
        ];
      $form['actions']['back'] = array(
        '#type' => 'submit',
        '#value' => t('Back to template'),
        '#submit' => array('_back_diff_form_submit'),
      );
        return  $form ;
    }
  function _back_diff_form_submit($form,FormStateInterface &$form_state) {
    $nid = \Drupal::request()->query->get('nid');
    $path = '/node/'.$nid.'/edit?destination=/admin/templating';
    $response = new RedirectResponse($path, 302);
    $response->send();
  }


    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {

    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $form_state->disableRedirect();

    }

    protected function getEditableConfigNames()
    {
        return [
            'templating.templating_diff_import',
        ];
    }

}
