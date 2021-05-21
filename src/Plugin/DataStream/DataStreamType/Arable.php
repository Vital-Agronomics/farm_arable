<?php

namespace Drupal\farm_arable\Plugin\DataStream\DataStreamType;

use Drupal\data_stream\Plugin\DataStream\DataStreamType\DataStreamTypeBase;
use Drupal\entity\BundleFieldDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the arable data stream type.
 *
 * @DataStreamType(
 *   id = "arable",
 *   label = @Translation("Arable"),
 * )
 */
class Arable extends DataStreamTypeBase {

  /**
   * Constructs an Arable object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
    );
  }

  /**
   * Get the default arable API key.
   *
   * @return string|NULL
   *   The default arable API key or NULL.
   */
  public static function getDefaultApiKey() {
    return \Drupal::configFactory()->get('farm_arable.settings')->get('default_api_key');
  }

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = [];

    // Define the arable_api_key field.
    $field = BundleFieldDefinition::create('string')
      ->setLabel($this->t('API key'))
      ->setRequired(TRUE)
      ->setDescription($this->t('Arable API key with read access to devices.'))
      ->setDefaultValueCallback(static::class . '::getDefaultApiKey')
      ->setSetting('max_length', 255)
      ->setSetting('text_processing', 0)
      ->setDisplayOptions('form', [
        'weight' => -10,
      ]);
    $fields['arable_api_key'] = $field;

    // Define the arable_device_name field.
    $field = BundleFieldDefinition::create('string')
      ->setLabel($this->t('Device name'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setSetting('text_processing', 0)
      ->setDisplayOptions('form', [
        'weight' => -10,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => -10,
      ]);
    $fields['arable_device_name'] = $field;

    // Define the arable_device_type field.
    $field = BundleFieldDefinition::create('list_string')
      ->setLabel($this->t('Device type'))
      //->setRequired(TRUE)
      ->setSetting(
        'allowed_values',
        [
          'Mark' => t('Mark'),
          'Mark 2' => t('Mark 2')
        ]
      )
      ->setSetting('text_processing', 0)
      ->setDisplayOptions('form', [
        'weight' => -10,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => -10,
      ]);
    $fields['arable_device_type'] = $field;

    // Define the arable_device_model field.
    $field = BundleFieldDefinition::create('integer')
      ->setLabel($this->t('Device model'))
      //->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'weight' => -10,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => -10,
      ]);
    $fields['arable_device_model'] = $field;

    return $fields;
  }

}
