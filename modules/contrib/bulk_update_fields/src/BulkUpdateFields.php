<?php

namespace Drupal\bulk_update_fields;

/**
 * BulkUpdateFields.
 */
class BulkUpdateFields {

  /**
   * {@inheritdoc}
   */
  public static function updateFields($entities, $fields, &$context) {
    $message = 'Updating Fields...';
    $results = [];
    $update = FALSE;
    foreach ($entities as $entity) {
      foreach ($fields as $field_name => $field_value) {
        if ($entity->hasField($field_name)) {
          $field_value = array_filter(array_filter($field_value, "is_numeric", ARRAY_FILTER_USE_KEY));
          $entity->get($field_name)->setValue($field_value);
          $update = TRUE;
        }
      }
      if ($update) {
        $entity->setNewRevision();
        $entity->save();
      }
    }
    $context['message'] = $message;
    $context['results'] = $results;
  }

  /**
   * {@inheritdoc}
   */
  public function bulkUpdateFieldsFinishedCallback($success, $results, $operations) {
    // The 'success' parameter means no fatal PHP errors were detected. All
    // other error management should be handled using 'results'.
    if ($success) {
      $message = \Drupal::translation()->formatPlural(
        count($results),
        'One operations processed.', '@count operations processed.'
      );
    }
    else {
      $message = t('Finished with an error.');
    }
    drupal_set_message($message);
  }

}
