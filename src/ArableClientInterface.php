<?php

namespace Drupal\farm_arable;

use GuzzleHttp\ClientInterface;

/**
 * Interface for the Arable client.
 *
 * @see \Drupal\farm_arable\ArableClient
 */
interface ArableClientInterface extends ClientInterface {

  /**
   * Helper function to return the configured default Arable units.
   *
   * @return array
   *   An array of unit configuration.
   */
  public static function getDefaultUnits(): array;

}
