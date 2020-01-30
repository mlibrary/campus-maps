<?php

/**
 * @file
 * Post update functions for Disable Field.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\field\Entity\FieldConfig;
use Drupal\user\Entity\Role;

/**
 * Rename disable field permission.
 *
 * Rename the 'disable textfield module' permission to
 * 'administer disable field settings'.
 *
 * @throws \Drupal\Core\Entity\EntityStorageException
 */
function disable_field_post_update_rename_disable_textfield_module_permission() {
  /** @var \Drupal\user\Entity\Role[] $roles */
  $roles = Role::loadMultiple();
  foreach ($roles as $role) {
    if ($role->hasPermission('disable textfield module')) {
      $role->revokePermission('disable textfield module');
      $role->grantPermission('administer disable field settings');
      $role->save();
    }
  }
}

/**
 * Merge role config items.
 */
function disable_field_post_update_merge_role_config_items(&$sandbox = NULL) {
  \Drupal::classResolver(ConfigEntityUpdater::class)
    ->update($sandbox, 'field_config', function (FieldConfig $field_config) {
      $add_disable_option = $field_config->getThirdPartySetting('disable_field', 'add_disable', FALSE);
      $edit_disable_option = $field_config->getThirdPartySetting('disable_field', 'edit_disable', FALSE);
      if ($add_disable_option === FALSE || $edit_disable_option === FALSE) {
        return FALSE;
      }

      if (!empty($field_config->getThirdPartySetting('disable_field', 'add_disable_roles', []))) {
        $field_config->setThirdPartySetting('disable_field', 'add_roles', array_keys($field_config->getThirdPartySetting('disable_field', 'add_disable_roles', [])));
      }
      elseif (!empty($field_config->getThirdPartySetting('disable_field', 'add_enable_roles', []))) {
        $field_config->setThirdPartySetting('disable_field', 'add_roles', array_keys($field_config->getThirdPartySetting('disable_field', 'add_enable_roles', [])));
      }

      $field_config->unsetThirdPartySetting('disable_field', 'add_disable_roles');
      $field_config->unsetThirdPartySetting('disable_field', 'add_enable_roles');

      if (!empty($field_config->getThirdPartySetting('disable_field', 'edit_disable_roles', []))) {
        $field_config->setThirdPartySetting('disable_field', 'edit_roles', array_keys($field_config->getThirdPartySetting('disable_field', 'edit_disable_roles', [])));
      }
      elseif (!empty($field_config->getThirdPartySetting('disable_field', 'edit_enable_roles', []))) {
        $field_config->setThirdPartySetting('disable_field', 'edit_roles', array_keys($field_config->getThirdPartySetting('disable_field', 'edit_enable_roles', [])));
      }

      $field_config->unsetThirdPartySetting('disable_field', 'edit_disable_roles');
      $field_config->unsetThirdPartySetting('disable_field', 'edit_enable_roles');
      return TRUE;
    });
}
