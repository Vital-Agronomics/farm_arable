<?php

namespace Drupal\farm_arable;

use Drupal\data_stream\Entity\DataStreamInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Provides helper methods to request data from a specific device.
 */
interface ArableDeviceClientInterface extends ArableClientInterface {

  /**
   * ArableDeviceClient constructor.
   *
   * @param DataStreamInterface $data_stream
   *   The data stream associated with the Arable device.
   * @param array $config
   *   Guzzle client config.
   */
  public function __construct(DataStreamInterface $data_stream, array $config = []);

  /**
   * Helper function to get device info.
   *
   * @param string|null $meta
   *   Optional meta info about the device, eg: locations, sensors.
   * @param array $options
   *   Optional request options.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The API response.
   */
  public function getDeviceInfo(string $meta = NULL, array $options = []): ResponseInterface;

  /**
   * Helper function to request device data.
   *
   * @param string $table
   *   The data table.
   * @param array $options
   *   Optional request options.
   * @param bool $default_units
   *   A boolean indicating if default units should be used. Defaults to TRUE.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The API response.
   */
  public function getDeviceData(string $table, array $options = [], bool $default_units = TRUE): ResponseInterface;

}
