<?php

/**
 * @file
 * Hooks provided by the Geofield Map module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the Geofield Map Lat Lon Element Settings.
 *
 * Modules may implement this hook to alter the Settings that are passed into
 * the Geofield Map Element Widget.
 *
 * @param array $map_settings
 *   The array of geofield map element settings.
 * @param array $complete_form
 *   The complete form array.
 * @param array $form_state_values
 *   The form state values array.
 */
function hook_geofield_map_latlon_element_alter(array &$map_settings, array &$complete_form, array &$form_state_values) {
  if ($map_settings['entity_operation'] == 'edit') {
    $map_settings['zoom_focus'] = 10;
  }
}

/**
 * @} End of "addtogroup hooks".
 */
