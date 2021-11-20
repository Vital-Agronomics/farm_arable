<?php

namespace Drupal\farm_arable\Plugin\DataStream\DataStreamType;

use Drupal\data_stream\Plugin\DataStream\DataStreamType\DataStreamTypeBase;
use Drupal\entity\BundleFieldDefinition;
use Drupal\farm_field\FarmFieldFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the arable data stream type.
 *
 * @DataStreamType(
 *   id = "arable_location",
 *   label = @Translation("Arable location"),
 * )
 */
class ArableLocation extends DataStreamTypeBase {

  /**
   * The farm_field.factory service.
   *
   * @var \Drupal\farm_field\FarmFieldFactoryInterface
   */
  protected $farmFieldFactory;

  /**
   * Constructs an ArableLocation object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, FarmFieldFactoryInterface $farm_field_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->farmFieldFactory = $farm_field_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('farm_field.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = [];

    // ID field.
    $fields['arable_id'] = $this->farmFieldFactory->bundleFieldDefinition([
      'type' => 'string',
      'label' => $this->t('Arable location ID'),
      'required' => TRUE,
    ]);

    // Moisture capacity.
    $field = BundleFieldDefinition::create('decimal')
      ->setLabel($this->t('Field capacity (%)'))
      ->setDisplayOptions('form', [
        'weight' => 5,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);
    $fields['arable_moisture_capacity'] = $field;

    // Moisture Maximum Allowable Depletion.
    $field = BundleFieldDefinition::create('decimal')
      ->setLabel($this->t('Maximum allowable depletion (%)'))
      ->setDisplayOptions('form', [
        'weight' => 6,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => 6,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);
    $fields['arable_moisture_mad'] = $field;

    // Moisture Permanent Wilting Point.
    $field = BundleFieldDefinition::create('decimal')
      ->setLabel($this->t('Permanent wilting point (%)'))
      ->setDisplayOptions('form', [
        'weight' => 7,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => 7,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);
    $fields['arable_moisture_pwp'] = $field;

    return $fields;
  }

}
