<?php

namespace Drupal\farm_arable\Form;

use Drupal\asset\Entity\Asset;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\data_stream\Entity\DataStream;
use Drupal\farm_arable\ArableClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A form to connect arable devices to farmOS.
 */
class ConnectArableDeviceForm extends FormBase {

  /**
   * @var ArableClientInterface $arableClient
   */
  protected $arableClient;

  /**
   * Constructs the ConnectArableDeviceForm.
   *
   * @param \Drupal\farm_arable\ArableClientInterface $arable_client
   *   The Arable client service.
   */
  public function __construct(ArableClientInterface $arable_client) {
    $this->arableClient = $arable_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
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
    $response = $this->arableClient->get('devices', ['query' => ['limit' => 100]]);
    $devices = Json::decode($response->getBody());

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

    // Display message.
    $this->messenger()->addMessage($this->t('Created sensor: <a href=":asset_uri">%asset_label</a>', [
      ':asset_uri' => $sensor->toUrl()->setAbsolute()->toString(),
      '%asset_label' => $sensor->label(),
    ]));
  }

}
