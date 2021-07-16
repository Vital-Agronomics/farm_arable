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

  // Add views data for arable location info.
  $data['farm_arable_locations']['table']['group'] = t('Arable');
  $data['farm_arable_locations']['table']['base'] = [
    'title' => t('Arable locations'),
    'help' => t('Arable locations provided by the Arable API.'),
    'query_id' => 'arable_locations',
  ];
  $data['farm_arable_locations']['name'] = [
    'title' => t('Location name'),
    'field' => [
      'id' => 'standard',
    ],
  ];
  $data['farm_arable_locations']['id'] = [
    'title' => t('Location id'),
    'field' => [
      'id' => 'standard',
    ],
  ];

  $data['farm_arable_locations']['connected'] = [
    'title' => t('Connected'),
    'field' => [
      'id' => 'boolean',
    ],
    'filter' => [
      'id' => 'boolean',
    ],
  ];

  $data['farm_arable_locations']['data_stream_id'] = [
    'title' => t('Data stream ID'),
    'description' => t('ID of the associated data stream'),
    'field' => [
      'id' => 'standard',
    ],
  ];

  $data['farm_arable_locations']['state'] = [
    'title' => t('State'),
    'field' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'arable_location_state',
    ],
  ];

  $data['farm_arable_locations']['archived'] = [
    'title' => t('Archived'),
    'field' => [
      'id' => 'boolean',
    ],
    'filter' => [
      'id' => 'boolean',
    ],
  ];

  $data['farm_arable_locations']['start_date'] = [
    'title' => t('Start date'),
    'field' => [
      'id' => 'date',
    ],
  ];

  $data['farm_arable_locations']['end_date'] = [
    'title' => t('End date'),
    'field' => [
      'id' => 'date',
    ],
  ];

  $data['farm_arable_locations']['latitude'] = [
    'title' => t('Latitude'),
    'field' => [
      'id' => 'numeric',
    ],
  ];

  $data['farm_arable_locations']['longitude'] = [
    'title' => t('Longitude'),
    'field' => [
      'id' => 'numeric',
    ],
  ];

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
