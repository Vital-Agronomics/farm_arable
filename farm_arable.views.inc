<?php

/**
 * @file
 * Contains views hooks for farm_arable.module
 */

/**
 * Implements hook_views_data().
 */
function farm_arable_views_data() {

  $data = [];
  $data['farm_arable_daily']['table']['group'] = t('Arable');
  $data['farm_arable_daily']['table']['base'] = [
    'title' => t('Arable daily'),
    'help' => t('Arable daily data provided by the Arable API.'),
    'query_id' => 'arable',
  ];

  $data['farm_arable_daily']['time'] = [
    'title' => t('Time'),
    'help' => t('Timestamp with timezone'),
    'field' => [
      'id' => 'date',
    ],
    'filter' => [
      'id' => 'date'
    ]
  ];

  $data['farm_arable_daily']['meant'] = [
    'title' => t('Mean temperature'),
    'help' => t('Daily mean temperature'),
    'field' => [
      'id' => 'numeric',
      'float' => TRUE,
    ],
  ];

  $data['farm_arable_daily']['mint'] = [
    'title' => t('Minimum temperature'),
    'help' => t('Daily minimum temperature'),
    'field' => [
      'id' => 'numeric',
      'float' => TRUE,
    ],
  ];

  return $data;

}
