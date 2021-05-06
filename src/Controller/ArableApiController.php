<?php

namespace Drupal\farm_arable\Controller;

use DateTime;
use Drupal\asset\Entity\AssetInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\farm_arable\ArableClient;
use Drupal\farm_arable\ArableClientInterface;
use Drupal\farm_arable\ArableDeviceClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for requesting data from the Arable API.
 *
 * For Arable API documentation see https://developer.arable.com.
 */
class ArableApiController extends ControllerBase {

  /**
   * The max age to cache Arable data responses.
   */
  const MAX_DATA_CACHE_AGE = 86400;

  /**
   * The arable client.
   *
   * @var \Drupal\farm_arable\ArableClientInterface
   */
  protected $arableClient;

  /**
   * ArableDataController constructor.
   *
   * @param \Drupal\farm_arable\ArableClientInterface $arable_client
   *  The arable client service.
   */
  public function __construct(ArableClientInterface $arable_client) {
    $this->arableClient = $arable_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('farm_arable.arable_client')
    );
  }

  /**
   * Endpoint for making any request to the Arable API.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The original request.
   * @param string $arg1
   *   The first path parameter. Required.
   * @param string $arg2
   *   The second path parameter. Optional.
   * @param string $arg3
   *   The third path parameter. Optional.
   *
   * @return \Psr\Http\Message\ResponseInterface
   */
  public function api(Request $request, string $arg1, string $arg2, string $arg3) {
    // Build path.
    $path = $arg1;
    if (!empty($arg2)) {
      $path .= "/$arg2";
    }
    if (!empty($arg3)) {
      $path .= "/$arg3";
    }

    // Relay the request.
    $query = $request->query->all();
    return $this->arableClient->request('GET', $path, ['query' => $query]);
  }

  /**
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @param \Drupal\asset\Entity\AssetInterface $asset
   * @param string $meta
   *
   * @return \Psr\Http\Message\ResponseInterface|\Symfony\Component\HttpFoundation\Response
   */
  public function assetDeviceInfo(Request $request, AssetInterface $asset, string $meta) {
    // Bail if the asset doesn't have an arable data stream.
    $data_streams = $asset->get('data_stream')->referencedEntities();
    $data_stream = reset($data_streams);
    if (empty($data_stream) || $data_stream->bundle() != 'arable') {
      return new Response('', Response::HTTP_NOT_FOUND);
    }

    // Relay the request.
    $client = new ArableDeviceClient($data_stream);
    return $client->getDeviceInfo($meta, $this->copyRequestParameters($request));
  }

  /**
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @param \Drupal\asset\Entity\AssetInterface $asset
   * @param string $table
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse|\Symfony\Component\HttpFoundation\Response
   */
  public function assetDeviceData(Request $request, AssetInterface $asset, string $table) {
    // Bail if the asset doesn't have an arable data stream.
    $data_streams = $asset->get('data_stream')->referencedEntities();
    $data_stream = reset($data_streams);
    if (empty($data_stream) || $data_stream->bundle() != 'arable') {
      return new Response('', Response::HTTP_NOT_FOUND);
    }

    // Create a device client.
    $client = new ArableDeviceClient($data_stream);

    // Request device info.
    $device_info_response = $client->getDeviceInfo(NULL, ['headers' => ['X-Fields' => '{last_post}']]);
    if ($device_info_response->getStatusCode() === 200) {
      $device_info = Json::decode($device_info_response->getBody());
    }
    $device_data = $client->getDeviceData($table, $this->copyRequestParameters($request));

    // Init new cacheable metadata with no max age.
    $cache_data = new CacheableMetadata();
    $cache_data->setCacheMaxAge(0);
    $cache_data->addCacheContexts(['url.query_args']);

    // Create a new cacheable json response.
    $response = CacheableJsonResponse::fromJsonString($device_data->getBody(), $device_data->getStatusCode(), $device_data->getHeaders());

    // If the device data request was successful, cache the response.
    if ($device_data->getStatusCode() === 200 && !empty($device_info['last_post'])) {
      $now = \Drupal::time()->getCurrentTime();

      // Calculate time since last post.
      $last_post_date = DateTime::createFromFormat(ArableClient::ISO8601U, $device_info['last_post']);
      $last_post = $last_post_date->getTimestamp();
      $last_post_diff = $now - $last_post;

      // Check if an end time was provided.
      $query = $request->query->all();
      $end_time = $now;
      if (!empty($query['end_time'])) {
        $end_date = new DateTime($query['end_time']);
        $end_time = $end_date->getTimestamp();
      }

      // Set max age for daily data.
      // @todo Is "tonight" the best option for this?
      // Does it depend on the timezone the table is aggregated in?
      if (strpos($table, 'daily') !== FALSE) {
        $midnight = (new DateTime())->setTime(24, 0)->getTimestamp();
        $cache_data->setCacheMaxAge($midnight - $now);
      }

      // Set max age for hourly data.
      if (strpos($table, 'hourly') !== FALSE) {
        // Calculate seconds until the device should refresh.
        // It should refresh within 60 minutes, after which we want to request
        // new data. 60 minutes + 2 minutes for delay = 3720 seconds.
        $until_refresh = 3720 - $last_post_diff;

        // If the last post was over an hour ago, only cache for 5 minues.
        $max_age = $until_refresh > 0 ? $until_refresh : 300;
        $cache_data->setCacheMaxAge($max_age);
      }

      // If the query end time is before the last post then we should
      // have all the devices data for the time range. Set max cache age.
      if ($end_time < $last_post) {
        $cache_data->setCacheMaxAge(static::MAX_DATA_CACHE_AGE);
      }
    }

    $response->addCacheableDependency($cache_data);
    return $response;
  }

  /**
   * Helper function to copy parameters from the request.
   *
   * All query parameters and the "X-Fields" header should be copied.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The original request.
   *
   * @return array
   *   Array of request options to return.
   */
  protected function copyRequestParameters(Request $request) {
    // Filter out headers to keep.
    $all_headers = $request->headers->all();
    $headers = array_filter($all_headers, function ($header_name) {
      return in_array(strtolower($header_name), ['x-fields']);
    }, ARRAY_FILTER_USE_KEY);

    // Keep all query params.
    $query = $request->query->all();

    // Return request options.
    return [
      'query' => $query,
      'headers' => $headers,
    ];
  }

}
