<?php

namespace Drupal\farm_arable\Form;

use Drupal\asset\Entity\Asset;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\data_stream\Entity\DataStream;
use Drupal\farm_arable\ArableClientInterface;
use Drupal\log\Entity\Log;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A form to connect arable devices to farmOS.
 */
class ConnectArableDeviceForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The arable client service.
   *
   * @var ArableClientInterface $arableClient
   */
  protected $arableClient;

  /**
   * Constructs the ConnectArableDeviceForm.
   *
   * @param EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\farm_arable\ArableClientInterface $arable_client
   *   The Arable client service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ArableClientInterface $arable_client) {
    $this->entityTypeManager = $entity_type_manager;
    $this->arableClient = $arable_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('farm_arable.arable_client'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'farm_arable_connect_arable_device_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Load all arable devices.
    // @todo Use pagination.
    $response = $this->arableClient->request('GET', 'devices', ['query' => ['limit' => 100]]);

    // Bail if can't connect.
    if ($response->getStatusCode() != 200) {
      $this->messenger()->addWarning($this->t('Could not load devices. Check that the Arable API key is valid. Reason: %reason', ['%reason' => $response->getReasonPhrase()]));
      return [];
    }

    // Get devices.
    $devices = Json::decode($response->getBody());

    // Bail if no devices were found.
    if (empty($devices['items'])) {
      $this->messenger()->addWarning($this->t('No devices were found. Make sure the Arable API key as access to 1 or more Arable devices.'));
      return [];
    }

    // Convert to array as select options.
    $device_names = array_column($devices['items'], 'name');
    $device_options = array_combine($device_names, $device_names);
    ksort($device_options);

    // Load existing device names.
    $existing_devices = \Drupal::entityTypeManager()->getStorage('data_stream')->loadByProperties([
      'type' => 'arable',
    ]);
    $existing_device_names = array_map(function ($data_stream) {
      return $data_stream->label();
    }, $existing_devices);

    // Filter out existing devices from device options.
    $device_options = array_filter($device_options, function ($device) use ($existing_device_names) {
      return !in_array($device, $existing_device_names);
    });

    // Display device options.
    $form['device_name'] = [
      '#type' => 'select',
      '#title' => $this->t('Device name'),
      '#description' => $this->t('Select the Arable device to connect. @total_count devices found. @existing_count already connected.', ['@total_count' => count($device_options), '@existing_count' => count($existing_device_names)]),
      '#options' => $device_options,
      '#default_value' => $form_state->getValue('device_name'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'loadDevice'],
        'event' => 'change',
        'wrapper' => 'device-info',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Loading device...'),
        ],
      ],
    ];

    // Start a device info details element.
    $form['device_info'] = [
      '#type' => 'details',
      '#title' => $this->t('Device info'),
      '#prefix' => '<div id="device-info">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
      '#open' => TRUE,
    ];
    $form['device_info']['info'] = [
      '#markup' => $this->t('Select an Arable device to load its info before connecting to farmOS.')
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create'),
      '#states' => [
        'disabled' => [
          ':input[name="device_name"]' => ['value' => ''],
        ],
      ],
    ];

    // Load the selected device.
    $device_name = $form_state->getValue('device_name');
    if (empty($device_name)) {
      return $form;
    }

    // Remove the info message.
    unset($form['device_info']['info']);

    $form['device_info']['device_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Device ID'),
      '#disabled' => TRUE,
    ];

    $form['device_info']['device_type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Device type'),
      '#disabled' => TRUE,
    ];

    $form['device_info']['device_model'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Device model'),
      '#disabled' => TRUE,
    ];

    $form['device_info']['device_state'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Device state'),
      '#disabled' => TRUE,
    ];

    $form['device_info']['last_seen'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last seen'),
      '#disabled' => TRUE,
    ];

    // Populate various values into disabled form fields.
    $response = Json::decode($this->arableClient->request('GET', "devices/$device_name")->getBody());
    $values = [
      'device_id' => 'id',
      'device_type' => 'type',
      'device_model' => 'model',
      'device_state' => 'state',
      'last_seen' => 'last_seen',
    ];
    foreach ($values as $form_key => $response_key) {
      $form['device_info'][$form_key]['#value'] = $response[$response_key];
    }

    // Display device location info.
    // @todo Display location on the map. Meta data in popup.
    $form['device_info']['location'] = [
      '#type' => 'details',
      '#title' => $this->t('Device location'),
      '#open' => TRUE,
    ];

    // Current location name.
    $current_location = $response['current_location']['name'] ?? '';
    $form['device_info']['location']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Current location Name'),
      '#default_value' => $current_location,
      '#disabled' => TRUE,
    ];

    // Current location GPS.
    $gps = $response['current_location']['gps'] ?? [];
    $form['device_info']['location']['gps'] = [
      '#type' => 'textfield',
      '#title' => $this->t('GPS'),
      '#default_value' => join(',', $gps),
      '#disabled' => TRUE,
    ];

    // Checkbox to assign location.
    // @todo Should it be possible to make the Arable sensor "fixed"?
    $form['device_info']['location']['assign_location'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Assign location'),
      '#description' => $this->t('Assign the Arable sensor location when creating the device.'),
      '#default_value' => TRUE,
    ];

    $form['device_info']['location']['use_gps'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use GPS'),
      '#description' => $this->t("Use the reported GPS value as the sensor's location?"),
      '#default_value' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="device_info[location][assign_location]"]' => ['checked' => TRUE],
          ':input[name="device_info[location][gps]"]' => ['filled' => TRUE],
        ],
      ],
    ];

    // Optionally specify a location asset as the location.
    $form['device_info']['location']['asset_location_reference'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Location'),
      '#target_type' => 'asset',
      '#selection_handler' => 'views',
      '#selection_settings' => [
        'view' => [
          'view_name' => 'farm_location_reference',
          'display_name' => 'entity_reference',
        ],
        'match_operator' => 'CONTAINS',
        'match_limit' => 10,
      ],
      '#tags' => TRUE,
      '#validate_reference' => FALSE,
      '#maxlength' => 1024,
      '#states' => [
        'visible' => [
          ':input[name="device_info[location][assign_location]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // @todo Display device "has_bridge".
    // @todo Display device sensors.

    return $form;
  }

  /**
   * Ajax callback to load an individual device info.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return mixed
   *   The form elements to replace.
   */
  public function loadDevice(array &$form, FormStateInterface $form_state) {
    // Replace the device info wrapper.
    return $form['device_info'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Populate necessary values.
    $device_name = $form_state->getValue('device_name');
    $device_info = $form_state->getValue('device_info');
    $keys = [
      'arable_device_id' => 'device_id',
      'arable_device_type' => 'device_type',
      'arable_device_model' => 'device_model',
    ];
    $data_stream_values = [];
    foreach ($keys as $entity_key => $form_key) {
      $data_stream_values[$entity_key] = $device_info[$form_key];
    }

    // Create the new data stream.
    $data_stream_values['type'] = 'arable';
    $data_stream_values['name'] = $device_name;
    $data_stream = DataStream::create($data_stream_values);
    $data_stream->save();

    // Create a new sensor asset referencing the data stream.
    $sensor = Asset::create([
      'type' => 'sensor',
      'name' => "Arable $device_name",
      'data_stream' => $data_stream,
    ]);
    $sensor->save();

    // Assign the sensor location.
    if (!empty($device_info['location']) && !empty($device_info['location']['assign_location'])) {

      // Start a movement log.
      $movement_log = Log::create([
        'type' => 'activity',
        'status' => 'done',
        'name' => $this->t('Assign Arable device location.'),
        'is_movement' => TRUE,
        'asset' => $sensor,
      ]);

      // Specify the geometry.
      if (!empty($device_info['location']['gps'] && !empty($device_info['location']['use_gps']))) {
        $gps = explode(',', $device_info['location']['gps']);
        $geom = "POINT ($gps[0] $gps[1])";
        $movement_log->set('geometry', $geom);
      }

      // Specify the location.
      if (!empty($device_info['location']['asset_location_reference'])) {
        $locations = [];
        $location_ids = array_column($device_info['location']['asset_location_reference'], 'target_id');
        if (!empty($location_ids)) {
          $locations = $this->entityTypeManager->getStorage('asset')->loadMultiple($location_ids);
        }
        $movement_log->set('location', $locations);
      }

      // Save the movement log.
      $movement_log->save();
    }

    // Display message.
    $this->messenger()->addMessage($this->t('Created sensor: <a href=":asset_uri">%asset_label</a>', [
      ':asset_uri' => $sensor->toUrl()->setAbsolute()->toString(),
      '%asset_label' => $sensor->label(),
    ]));
  }

}
