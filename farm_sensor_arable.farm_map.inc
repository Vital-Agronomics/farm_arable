<?php
/**
 * @file
 * Farm Map hooks implemented by the Farm Sensor Arable module.
 */

/**
 * Implements hook_farm_map_behaviors().
 */
function farm_sensor_arable_farm_map_behaviors() {
  return array(
    'arable_current_status' => array(
      'js' => 'js/farmOS.map.behaviors.arable_current_status.js',
    ),
  );
}
