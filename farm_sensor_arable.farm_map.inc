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

/**
 * Implements hook_farm_map_view().
 */
function farm_sensor_arable_farm_map_view($name, $element) {

  // Add arable sensors to farm_areas maps.
  if ($name == 'farm_areas') {

    // Load all arable sensors.
    $sensors = farm_sensor_arable_sensors();

    // Load arable units.
    $units = farm_sensor_arable_units();

    farm_map_add_behavior('arable_current_status', array('devices' => $sensors, 'units' => $units));
  }
}
