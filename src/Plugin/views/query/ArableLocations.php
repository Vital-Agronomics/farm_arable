<?php

namespace Drupal\farm_arable\Plugin\views\query;

use DateTime;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\farm_arable\ArableClient;
use Drupal\farm_arable\ArableClientInterface;
use Drupal\views\Annotation\ViewsQuery;
use Drupal\views\Plugin\views\join\JoinPluginBase;
use Drupal\views\Plugin\views\query\QueryPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * ArableLocations views query plugin which wraps calls to the Arable API
 * in order to expose the results to views.
 *
 * @ViewsQuery(
 *   id = "arable_locations",
 *   title = @Translation("Arable locations"),
 *   help = @Translation("Query against locations from the Arable API.")
 * )
 */
class ArableLocations extends QueryPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The arable client service.
   *
   * @var ArableClientInterface $arableClient
   */
  protected $arableClient;

  /**
   * Constructs an ArableLocation object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param ArableClientInterface $arable_client
   *   The arable client service.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, ArableClientInterface $arable_client) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->arableClient = $arable_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('farm_arable.arable_client'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute(ViewExecutable $view) {

    // Query filters can be applied in the API request query params.
    $query_filters = [];

    // Logic filters must be applied after data is requested.
    $logic_filters = [];

    // Add any filters applied in the view.
    if (isset($this->where)) {
      foreach ($this->where as $where_group => $where) {

        // Loop through conditions.
        foreach ($where['conditions'] as $condition) {

          // Normalize the filter name.
          // Remove dot from beginning of the string.
          $field_name = ltrim($condition['field'], '.');
          $value = $condition['value'];

          // Complex filters my have a formula operator.
          if ($condition['operator'] === 'formula') {

            // Break out values from the field name.
            [$field_name, $condition, $value] = explode(' ', $field_name);

            // Convert the time filters to something that works.
            if ($field_name === 'time') {
              switch ($condition) {
                case '>':
                case '>=':
                  $field_name = 'start_time';
                  break;

                case '<':
                case '<=':
                  $field_name = 'end_time';
                  break;
              }
              $value = date('c', $value);
            }
            $query_filters[$field_name] = $value;
          }

          // Boolean filters that must be applied as logic filters.
          if (in_array($field_name, ['archived', 'connected'])) {
            $value = (bool) $value;
            $logic_filters[$field_name] = $value;
          }

          // The state filter.
          if ($field_name === 'state') {
            $logic_filters[$field_name] = $value;
          }
        }
      }
    }

    // Get existing arable_location data streams.
    $location_data_streams = \Drupal::entityTypeManager()->getStorage('data_stream')->loadByProperties([
      'type' => 'arable_location',
    ]);

    // Map each data stream by the arable_id field.
    $location_data_streams = array_reduce($location_data_streams, function ($carry, $data_stream) {
      $location_id = $data_stream->get('arable_id')->value;
      $carry[$location_id] = $data_stream;
      return $carry;
    }, []);

    // Build list of query params.
    // @todo get all pages of results, no limit param.
    $params = [
      'limit' => 300,
    ] + $query_filters;
    $response = $this->arableClient->request('GET', 'locations', ['query' => $params]);
    if ($data = Json::decode($response->getBody())) {

      // Create a ResultRow for each location.
      foreach ($data['items'] as $index => $row) {

        // Each result must have an index.
        $row['index'] = $index;

        // Convert boolean value.
        $row['archived'] = (bool) $row['archived'];

        // Convert dates to unix timestamps.
        foreach (['start_date', 'end_date'] as $date_field) {
          if (!empty($row[$date_field]) && $datetime = DateTime::createFromFormat(ArableClient::ISO8601U, $row['start_date'])) {
            $row[$date_field] = $datetime->getTimestamp();
          }
          else {
            unset($row[$date_field]);
          }
        }

        // Get longitude and latitude from GPS value.
        if (!empty($row['gps']) && is_array($row['gps'])) {
          $gps = $row['gps'];
          $row['longitude'] = $gps[0];
          $row['latitude'] = $gps[1];
        }

        // Determine if the arable location has been connected.
        // Populate the data_stream_id field if it is connected.
        $row['connected'] = isset($location_data_streams[$row['id']]);
        if ($row['connected']) {
          $row['data_stream_id'] = $location_data_streams[$row['id']]->id();
        }

        // Create the ResultRow.
        $view->result[] = new ResultRow($row);
      }

      foreach ($logic_filters as $key => $value) {
        // Convert the value into an array.
        $values = is_array($value) ? $value : [$value];

        // Filter ResultRows.
        $view->result = array_filter($view->result, function (ResultRow $row) use ($key, $values) {
          $actual_value = $row->{$key};
          return isset($actual_value) && in_array($actual_value, $values);
        });
      }
    }
  }

  // Functions that are required to work with SQL Query views handlers.
  public function ensureTable($table, $relationship = NULL) {
    return '';
  }
  public function addField($table, $field, $alias = '', $params = array()) {
    return $field;
  }
  public function addRelationship($alias, JoinPluginBase $join, $base, $link_point = NULL) {
    return true;
  }

  // Where expressions get a bit of special logic.
  public function addWhere($group, $field, $value = NULL, $operator = NULL) {
    // Ensure all variants of 0 are actually 0. Thus '', 0 and NULL are all
    // the default group.
    if (empty($group)) {
      $group = 0;
    }

    // Check for a group.
    if (!isset($this->where[$group])) {
      $this->setWhereGroup('AND', $group);
    }

    // Finally add the where condition.
    $this->where[$group]['conditions'][] = [
      'field' => $field,
      'value' => $value,
      'operator' => $operator,
    ];
  }
  public function addWhereExpression($group, $snippet, $args = []) {
    // Ensure all variants of 0 are actually 0. Thus '', 0 and NULL are all
    // the default group.
    if (empty($group)) {
      $group = 0;
    }

    // Check for a group.
    if (!isset($this->where[$group])) {
      $this->setWhereGroup('AND', $group);
    }

    // Finally add the where condition.
    $this->where[$group]['conditions'][] = [
      'field'    => $snippet,
      'value'    => $args,
      'operator' => 'formula',
    ];
  }

}
