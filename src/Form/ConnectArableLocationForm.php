<?php

namespace Drupal\farm_arable\Form;

use Drupal\asset\Entity\AssetInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\data_stream\Entity\DataStream;
use Drupal\farm_arable\ArableClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A form to connect arable locations to farmOS.
 */
class ConnectArableLocationForm extends FormBase {

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
   * Constructs the ConnectArableLocationForm.
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
    return 'farm_arable_connect_arable_location_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $location_id = NULL) {

    // Get existing arable_location data streams.
    $existing_data_streams = $this->entityTypeManager->getStorage('data_stream')->loadByProperties([
      'type' => 'arable_location',
      'arable_id' => $location_id,
    ]);

    // Check if the arable location was already added.
    if (!empty($existing_data_streams)) {
      $data_stream = reset($existing_data_streams);
      $this->messenger()->addWarning($this->t('This Arable location is already connected: <a href=":link">%label</a>',
        [':link' => $data_stream->toUrl()->toString(), '%label' => $data_stream->label()]));
      return [];
    }

    // Load the arable location info.
    $response = $this->arableClient->request('GET', "locations/$location_id");

    // Bail if can't connect.
    if ($response->getStatusCode() != 200) {
      $this->messenger()->addWarning($this->t('Could not load location. Check that the Arable API key is valid. Reason: %reason', ['%reason' => $response->getReasonPhrase()]));
      return [];
    }

    // Get location info.
    $location = Json::decode($response->getBody());

    // Display the raw location info in a closed details element.
    $form['metadata'] = [
      '#type' => 'details',
      '#title' => 'Metadata',
      '#open' => FALSE,
      '#weight' => 100,
    ];
    $form['metadata']['data'] = [
      '#type' => 'textarea',
      '#disabled' => TRUE,
      '#value' => json_encode($location, JSON_PRETTY_PRINT),
      '#rows' => 20,
    ];

    // Start a location info details element.
    $form['info'] = [
      '#type' => 'details',
      '#title' => $this->t('Location info'),
      '#tree' => TRUE,
      '#open' => TRUE,
    ];

    // Save the location_id to the form state.
    $form['info']['location_id'] = [
      '#type' => 'hidden',
      '#value' => $location_id,
    ];

    // The location name.
    $form['info']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Location name'),
      '#description' => $this->t('The Arable location name.'),
      '#disabled' => TRUE,
      '#value' => $location['name'],
    ];

    // Add a map with the GPS point.
    if (!empty($location['gps'])) {
      $latitude = $location['gps'][0];
      $longitude = $location['gps'][1];
      $wkt = "POINT ($latitude $longitude)";
      $form['map'] = [
        '#type' => 'farm_map',
        '#map_type' => 'geofield',
        '#map_settings' => [
          'wkt' => $wkt,
          'behaviors' => [
            'wkt' => [
              'zoom' => TRUE,
            ],
          ],
        ],
        '#weight' => -100,
      ];

      // Load nearby location assets.
      $assets = $this->getLocationAssets($location['gps']);
      if (!empty($assets)) {
        $form['info']['asset'] = [
          '#type' => 'entity_autocomplete',
          '#title' => $this->t('Asset'),
          '#description' => $this->t('What assets does this Arable location describe? This will be pre-populated assets that overlap the location GPS point.'),
          '#target_type' => 'asset',
          '#selection_handler' => 'views',
          '#selection_settings' => [
            'view' => [
              'view_name' => 'farm_asset_reference',
              'display_name' => 'entity_reference',
            ],
            'match_operator' => 'CONTAINS',
            'match_limit' => 10,
          ],
          '#tags' => TRUE,
          '#validate_reference' => FALSE,
          '#maxlength' => 1024,
          '#default_value' => $assets,
        ];
      }
    }

    // Sensor configuration.
    $form['info']['sensors'] = [
      '#type' => 'details',
      '#title' => $this->t('Sensors'),
      '#open' => TRUE,
    ];

    // Display sensor information if the device has a bridge.
    // @todo is has_bridge reliable?
    if (empty($location['current_device']['has_bridge'])) {
      $form['info']['sensors']['info'] = [
        '#markup' => $this->t('The device in this location does not have any connected sensors.'),
      ];
    }
    else {

      // Check the device has a soil moisture sensor.
      $has_moisture_sensor = FALSE;
      foreach ($location['current_device']['sensors'] as $sensor) {
        if (!empty($sensor['sensor']['measurement_type']) && $sensor['sensor']['measurement_type'] === 'soil_moisture') {
          $has_moisture_sensor = TRUE;
        }
      }

      // Soil moisture info.
      $form['info']['sensors']['soil_moisture'] = [
        '#type' => 'details',
        '#title' => $this->t('Soil moisture settings'),
        '#open' => TRUE,
      ];

      if (!$has_moisture_sensor) {
        $form['info']['sensors']['soil_moisture'] = [
          '#markup' => $this->t('The device in this location does not have any soil moisture sensors.'),
        ];
      }
      else {
        $form['info']['sensors']['soil_moisture']['capacity'] = [
          '#type' => 'number',
          '#title' => $this->t('Field capacity'),
          '#max' => 100,
          '#min' => 0,
        ];

        $form['info']['sensors']['soil_moisture']['mad'] = [
          '#type' => 'number',
          '#title' => $this->t('Maximum allowable depletion'),
          '#max' => 100,
          '#min' => 0,
        ];

        $form['info']['sensors']['soil_moisture']['pwp'] = [
          '#type' => 'number',
          '#title' => $this->t('Permanent wilting point'),
          '#max' => 100,
          '#min' => 0,
        ];
      }
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Connect location'),
      '#weight' => 200,
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Build array of data stream values.
    $data_stream_values = [];
    $location_info = $form_state->getValue('info');
    $data_stream_values['arable_id'] = $location_info['location_id'];
    $data_stream_values['name'] = $location_info['name'];

    // Include asset references.
    if (!empty($location_info['asset'])) {
      $data_stream_values['asset'] = array_column($location_info['asset'], 'target_id');
    }

    // Include soil moisture parameters.
    if (!empty($location_info['sensors']['soil_moisture'])) {
      $soil_moisture = $location_info['sensors']['soil_moisture'];
      $keys = ['capacity', 'mad', 'pwp'];
      foreach ($keys as $form_key) {
        $data_stream_values["arable_moisture_$form_key"] = $soil_moisture[$form_key];
      }
    }

    // Create the new data stream.
    $data_stream_values['type'] = 'arable_location';
    $data_stream = DataStream::create($data_stream_values);
    $data_stream->save();

    // Display message.
    $this->messenger()->addMessage($this->t('Created data stream: <a href=":uri">%label</a>', [
      ':uri' => $data_stream->toUrl()->setAbsolute()->toString(),
      '%label' => $data_stream->label(),
    ]));

    // Set the redirect.
    $url = Url::fromRoute('view.arable_locations.page');
    $form_state->setRedirectUrl($url);
  }

  /**
   * Helper function to load location assets covering the devices reported GPS.
   *
   * @todo Support non-fixed assets.
   *
   * @param array $gps
   *   Array of device GPS data as returned from the Arable API.
   *
   * @return AssetInterface[]
   *   Assets that cover the device's GPS.
   */
  protected function getLocationAssets(array $gps): array {

    // Query for location assets covering the GPS location.
    $asset_storage = $this->entityTypeManager->getStorage('asset');
    $asset_ids = $asset_storage->getQuery()
      ->condition('status', 'active')
      ->condition('is_fixed', TRUE)
      ->condition('is_location', TRUE)
      ->condition('intrinsic_geometry.bottom', $gps[1], '<=')
      ->condition('intrinsic_geometry.top', $gps[1], '>=')
      ->condition('intrinsic_geometry.left', $gps[0], '<=')
      ->condition('intrinsic_geometry.right', $gps[0], '>=')
      ->execute();

    // Load and return the assets.
    return $asset_storage->loadMultiple($asset_ids);
  }

}
