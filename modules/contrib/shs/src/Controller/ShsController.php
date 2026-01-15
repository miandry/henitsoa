<?php

namespace Drupal\shs\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\shs\Cache\ShsCacheableJsonResponse;
use Drupal\shs\Cache\ShsTermCacheDependency;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for getting taxonomy terms.
 */
class ShsController extends ControllerBase {

  /**
   * Gets the term data.
   *
   * @param string $identifier
   *   Name of field to load the data for.
   * @param string $bundle
   *   Bundle (vocabulary) identifier to limit the return list to a specific
   *   bundle.
   * @param int $entity_id
   *   Id of parent term to load all children for. Defaults to 0.
   *
   * @return \Drupal\shs\Cache\ShsCacheableJsonResponse
   *   Cacheable Json response.
   */
  public function getTermData($identifier, $bundle, $entity_id = 0) {
    $context = [
      'identifier' => $identifier,
      'bundle' => $bundle,
      'parent' => $entity_id,
    ];
    $response = new ShsCacheableJsonResponse($context);

    $cache_tags = [];
    $result = [];

    $langcode_current = $this->languageManager()->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
    /** @var \Drupal\taxonomy\TermStorageInterface $storage */
    $storage = $this->entityTypeManager()->getStorage('taxonomy_term');

    $translation_enabled = FALSE;
    if ($this->moduleHandler()->moduleExists('content_translation')) {
      /** @var \Drupal\content_translation\ContentTranslationManager $translation_manager */
      $translation_manager = \Drupal::service('content_translation.manager');
      // If translation is enabled for the vocabulary, we need to load the full
      // term objects to get the translation for the current language.
      $translation_enabled = $translation_manager->isEnabled('taxonomy_term', $bundle);
    }

    $access_unpublished = $this->currentUser()->hasPermission('administer taxonomy') || $this->currentUser()->hasPermission('view unpublished terms in ' . $bundle);
    $cache_tags[] = 'access_unpublished:' . $access_unpublished;

    /** @var \Drupal\taxonomy\TermInterface[] $terms */
    $terms = $storage->loadTree($bundle, $entity_id, 1, $translation_enabled);

    foreach ($terms as $term) {
      $langcode = $langcode_current;
      if ($translation_enabled && ($term instanceof TranslatableInterface) && $term->hasTranslation($langcode)) {
        $term = $term->getTranslation($langcode);
      }
      else {
        $langcode = $term->default_langcode;
      }

      $tid = $translation_enabled ? $term->id() : $term->tid;
      $published = $translation_enabled ? $term->isPublished() : $term->status;

      if (!$published && !$access_unpublished) {
        continue;
      }

      $result[] = (object) [
        'tid' => $tid,
        'name' => $translation_enabled ? $term->getName() : $term->name,
        'description__value' => $translation_enabled ? $term->getDescription() : $term->description__value,
        'langcode' => $langcode,
        'hasChildren' => shs_term_has_children($tid),
      ];
      $cache_tags[] = sprintf('taxonomy_term:%d', $tid);
    }

    $response->addCacheableDependency(new ShsTermCacheDependency($cache_tags));
    $response->setData($result, TRUE);

    return $response;
  }

  /**
   * Create term data.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return JsonResponse
   *   Json response.
   */
  public function createTerm(Request $request) {

    $result = NULL;

    // Obtain the data from the json.
    $data = json_decode($request->getContent());
    $value = $data->arguments->value;
    $bundle = $data->arguments->bundle;
    $entity_id = $data->arguments->entity_id;
    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();

    if (!$langcode) {
      $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
    }

    $storage = $this->entityTypeManager()->getStorage('taxonomy_term');

    // Try to find the term.
    $found = NULL;
    $terms = $storage->loadTree($bundle, $entity_id, 1, TRUE);
    foreach ($terms as $term) {
      if ($term->hasTranslation($langcode)) {
        $term = $term->getTranslation($langcode);
      }
      else {
        $langcode = $term->default_langcode;
      }

      if (strcasecmp($term->getName(), $value) === 0) {
        $found = $term;
        break;
      }
    }

    if (!$found) {
      $term = $storage->create([
        'vid' => $bundle,
        'langcode' => $langcode,
        'name' => $value,
        'parent' => [$entity_id],
      ]);
      $term->save();
    }
    else {
      $term = $found;
    }

    $vid = $term->bundle();
    $vocabulary = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->load($vid);

    $result = (object) [
      'tid' => $term->id(),
      'name' => $term->getName(),
      'vocabulary_label' => $vocabulary->label(),
      'description__value' => $term->getDescription(),
      'langcode' => $langcode,
      'hasChildren' => shs_term_has_children($term->id()),
    ];

    $response = new JsonResponse();
    $response->setData($result);

    return $response;
  }

  /**
   * Checks access for a specific request.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return bool
   */
  public function createTermAccess(AccountInterface $account) {
    $request = \Drupal::request();
    $data = json_decode($request->getContent());
    $bundle = $data->arguments->bundle;

    return AccessResult::allowedIfHasPermissions($account, [
      "edit terms in {$bundle}",
      'administer taxonomy',
    ], 'OR');
  }

}
