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
    $results_entities = [];
    $results_fields = [];
    $update = FALSE;
    foreach ($entities as $entity) {
      foreach ($fields as $field_name => $field_value) {
        if ($entity->hasField($field_name)) {
          if ($field_value == $field_name ) { continue; } // this is the case for field images for some reason
          // not sure if this is still valid but leaving in case
          if (isset($field_value['target_id'][0])) {
            $field_value = $field_value['target_id'];
          }
          // this caused a failure in core/entity/plugin/datatype/entityreference. removing.
          if (isset($field_value[0]['target_id']) && isset($field_value['add_more'])) {
            unset($field_value['add_more']);
          }
          // this occurs in fields like office hours.
          if (isset($field_value['value'])) {
            $field_value = $field_value['value'];
          }
          $entity->get($field_name)->setValue($field_value);
          $update = TRUE;
          if (!in_array($field_name, $results_fields)) {
            $results_fields[] = $field_name;
          }
        }
      }
      if ($update) {
        $entity->setNewRevision();
        $entity->save();
        $results_entities[] = $entity->id();
      }
    }
    $context['message'] = $message;
    $context['results']['results_entities'] = $results_entities;
    $context['results']['results_fields'] = $results_fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function bulkUpdateFieldsFinishedCallback($success, $results, $operations) {
    // The 'success' parameter means no fatal PHP errors were detected. All
    // other error management should be handled using 'results'.
    if ($success) {
      $message_field = \Drupal::translation()->formatPlural(
        count($results['results_fields']),
        'One field processed', '@count fields processed'
      );
      $message_entity = \Drupal::translation()->formatPlural(
        count($results['results_entities']),
        'One entity', '@count entities'
      );
      $message = $message_field.' on '.$message_entity;
    }
    else {
      $message = t('Finished with an error.');
    }
    drupal_set_message($message);
  }

}
