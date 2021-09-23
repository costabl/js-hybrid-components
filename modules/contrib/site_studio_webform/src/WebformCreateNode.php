<?php

namespace Drupal\site_studio_webform;

use Drupal\node\Entity\Node;
use Drupal\webform\Entity\Webform;

/**
 * Batch API utility functions class.
 */
class WebformCreateNode {

  /**
   * Custom batch API callback function.
   *
   * @param string $webform_id
   *   Webform ID.
   * @param array $context
   *   Content of bactch API.
   */
  public static function webformNodesCreate($webform_id, array &$context) {
    $message = 'Creating Webform Node...';
    $results = [];

    $webform = Webform::load($webform_id);
    $web_title = $webform->label();

    $isexist = self::checkExistingNode($webform_id, $web_title);
    if (!$isexist) {
      $webform_node = Node::create([
        'type' => 'webform',
        'title' => $web_title,
        'webform' => ['target_id' => $webform_id],
      ]);
      $results[] = $webform_node->save();
    }

    $context['message'] = $message;
    $context['results'] = $results;
  }

  /**
   * Batch 'finished' callback.
   */
  public static function webformNodesCreateFinishedCallback($success, $results, $operations) {
    // The 'success' parameter means no fatal PHP errors were detected. All
    // other error management should be handled using 'results'.
    if ($success) {
      $message = t('Webform nodes created.');
    }
    else {
      $message = t('Finished with an error.');
    }
    \Drupal::messenger()->addMessage($message);
  }

  /**
   * Custom function to validate existing wenform node.
   *
   * @param string $webform_id
   *   Webform ID.
   * @param string $web_title
   *   Webform Title.
   *
   * @return bool
   *   Return flag.
   */
  public static function checkExistingNode($webform_id, $web_title) {
    $flag = FALSE;
    $result = \Drupal::service('entity_type.manager')->getStorage('node')->getQuery()
      ->condition('type', 'webform')
      ->condition('title', $web_title, '=')
      ->condition('webform', $webform_id)
      ->execute();

    if (!empty($result)) {
      $flag = TRUE;
    }
    return $flag;
  }

}
