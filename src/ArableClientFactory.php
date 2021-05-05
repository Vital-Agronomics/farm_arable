<?php

namespace Drupal\farm_arable;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Factory for the ArableClient service.
 *
 * Instantiates the client the configured default Arable API key.
 *
 * @see \Drupal\farm_arable\ArableClient
 */
class ArableClientFactory {

  /**
   * {@inheritdoc}
   */
  public static function create(ConfigFactoryInterface $config_factory) {
    $config = $config_factory->get('farm_arable.settings');
    $api_key = $config->get('default_api_key');
    return new ArableClient($api_key);
  }

}
