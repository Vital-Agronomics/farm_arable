<?php
/**
 * @file
 * Farm Sensor Arable dashboard pane.
 */

/**
 * Implements hook_farm_dashboard_panes().
 */
function farm_sensor_arable_farm_dashboard_panes() {

  // Start array.
  $panes = array();

  // Add pane for Arable stats.
  $panes['farm_sensor_arable_device_stats'] = array(
    'callback' => 'farm_sensor_arable_device_stats_pane',
    'title' => t('Arable Stats'),
    'group' => 'arable',
  );

  return $panes;
}

/*
 * Callback for loading Arable sensor stats.
 */
function farm_sensor_arable_device_stats_pane() {

  // Container for all stats.
  $form['stats'] = array(
    '#prefix' => '<div>',
    '#suffix' => '</div>',
  );


  // Load stats
  $api_key = variable_get('farm_sensor_arable_default_api_key', '');
  $stats = farm_sensor_arable_device_stats($api_key);

  // Bail if no stats were loaded.
  if (empty($stats)) {
    $form['stats']['#markup'] = '<div class="alert alert-danger" role="alert"><strong>Error loading Arable stats.</strong> Check that your API key is valid. </div>';
    return drupal_render($form);
  }

  // Add "Devices Syncing" fieldset.
  $form['stats']['devices_syncing'] = array(
    '#prefix' => '<div class="col-xs-6 arable-stats-container">',
    '#suffix' => '</div>',
    '#type' => 'fieldset',
    '#title' => t('Devices Syncing'),
    '#attributes' => array('class' => array('dev-syncing')),
  );
  $form['stats']['devices_syncing']['stats'] = array(
    '#prefix' => '<ul>',
    '#suffix' => '</ul>',
    '#markup' => '',
  );
  // Add stats.
  $form['stats']['devices_syncing']['stats']['#markup'] .= '<li><span class="stat-number">' . $stats->state->active->total . '</span><br /><span class="stat-label text-info">Active</span></li>';
  $form['stats']['devices_syncing']['stats']['#markup'] .= '<li><span class="stat-number">' . $stats->state->new->total . '</span><br /><span class="stat-label text-success">New</span></li>';

  // Add "Devices Not Syncing" fieldset.
  $form['stats']['devices_not_syncing'] = array(
    '#prefix' => '<div class="col-xs-6 arable-stats-container">',
    '#suffix' => '</div>',
    '#type' => 'fieldset',
    '#title' => t('Devices not Syncing'),
    '#attributes' => array('class' => array('dev-not-syncing')),
  );
  $form['stats']['devices_not_syncing']['stats'] = array(
    '#prefix' => '<ul>',
    '#suffix' => '</ul>',
    '#markup' => '',
  );
  // Add stats.
  $form['stats']['devices_not_syncing']['stats']['#markup'] .= '<li><span class="stat-number">' . $stats->state->inactive->total . '</span><br /><span class="stat-label text-danger">Inactive</span></li>';
  $form['stats']['devices_not_syncing']['stats']['#markup'] .= '<li><span class="stat-number">' . $stats->state->dormant->total . '</span><br /><span class="stat-label text-warning">Dormant</span></li>';

  // Add Battery fieldset.
  $form['stats']['battery'] = array(
    '#prefix' => '<div class="col-xs-12 arable-stats-container">',
    '#suffix' => '</div>',
    '#type' => 'fieldset',
    '#title' => t('Battery'),
    '#attributes' => array('class' => array('battery')),
  );
  $form['stats']['battery']['stats'] = array(
    '#prefix' => '<ul>',
    '#suffix' => '</ul>',
    '#markup' => '',
  );
  // Add stats.
  $form['stats']['battery']['stats']['#markup'] .= '<li><span class="stat-number">' . $stats->batt_pct->low . '</span><br /><span class="stat-label text-danger"><29%</span></li>';
  $form['stats']['battery']['stats']['#markup'] .= '<li><span class="stat-number">' . $stats->batt_pct->med . '</span><br /><span class="stat-label text-warning">30-59%</span></li>';
  $form['stats']['battery']['stats']['#markup'] .= '<li><span class="stat-number">' . $stats->batt_pct->high . '</span><br /><span class="stat-label text-success">>60%</span></li>';

  drupal_add_css(drupal_get_path('module', 'farm_sensor_arable') . '/farm_sensor_arable.css');
  return drupal_render($form);
}
