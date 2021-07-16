<?php

namespace Drupal\farm_arable;

use GuzzleHttp\Client;

/**
 * Extends the Guzzle HTTP client with helper methods for the Arable API.
 */
class ArableClient extends Client implements ArableClientInterface {

  /**
   * ISO time format used for the device "last_seen" and "last_post" value.
   */
  const ISO8601U = "Y-m-d\TH:i:s.uP";

  /**
   * The base URI of the Arable API.
   *
   * @var string
   */
  public static string $arableApiBaseUri = 'https://api.arable.cloud/api/v2/';

  /**
   * ArableClient constructor.
   *
   * @param string $api_key
   *   The Arable API key.
   * @param array $config
   *   Guzzle client config.
   */
  public function __construct(string $api_key, array $config = []) {
    $default_config = [
      'base_uri' => self::$arableApiBaseUri,
      'headers' => [
        'Authorization' => "Apikey $api_key",
      ],
      'http_errors' => FALSE,
    ];
    $config = $default_config + $config;
    parent::__construct($config);
  }

  /**
   * {@inheritdoc}
   */
  public static function getDefaultUnits(): array {
    return \Drupal::config('farm_arable.settings')->get('units') ?? [];
  }

}
