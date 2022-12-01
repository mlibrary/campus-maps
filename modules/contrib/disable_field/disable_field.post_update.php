<?php

/**
 * @file
 * Post update functions for Disable Field.
 */

/**
 * Implements hook_removed_post_updates().
 */
function disable_field_removed_post_updates() {
  return [
    'disable_field_post_update_rename_disable_textfield_module_permission' => '3.0.0',
    'disable_field_post_update_merge_role_config_items' => '3.0.0',
  ];
}
