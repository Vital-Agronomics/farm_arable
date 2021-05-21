<?php

namespace Drupal\farm_arable;

use Drupal\data_stream\Entity\DataStreamInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Extends the ArableClient class to provide methods associated with a device.
 */
class ArableDeviceClient extends ArableClient implements ArableDeviceClientInterface {

  /**
   * The data stream associated with the Arable device.
   *
   * @var DataStreamInterface
   */
  protected $dataStream;

  /**
   * {@inheritdoc}
   */
  public function __construct(DataStreamInterface $data_stream, array $config = []) {
    $this->dataStream = $data_stream;
    parent::__construct($data_stream->get('arable_api_key')->value, $config);
  }

  /**
   * {@inheritdoc}
   */
  public function getDeviceInfo(string $meta = NULL, array $options = []): ResponseInterface {
    $path = $this->getDevicePath();
    if (!empty($meta)) {
      $path .= "/$meta";
    }
    return $this->get($path, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function getDeviceData(string $table, array $options = [], bool $default_units = TRUE): ResponseInterface {

    // Build query params.
    $query = [
      'device' => $this->dataStream->get('arable_device_name')->value,
    ];

    // Optionally include default units.
    if ($default_units) {
      $query += static::getDefaultUnits();
    }

    // Override defaults with requested options.
    $request_options = array_replace_recursive(['query' => $query], $options);

    $path = "data/$table";
    return $this->get($path, $request_options);
  }

  /**
   * Helper function to get the base device path.
   *
   * @return string
   *   The device path.
   */
  protected function getDevicePath() {
    return "devices/" . $this->dataStream->get('arable_device_name')->value;
  }

}
