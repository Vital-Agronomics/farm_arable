<?php

namespace Drupal\farm_arable\Plugin\views\query;

use Drupal\Component\Serialization\Json;
use Drupal\data_stream\Entity\DataStream;
use Drupal\farm_arable\ArableDeviceClient;
use Drupal\views\Annotation\ViewsQuery;
use Drupal\views\Plugin\views\query\QueryPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;

/**
 * Arable views query plugin which wraps calls to the Arable API in order to
 * expose the results to views.
 *
 * @ViewsQuery(
 *   id = "arable",
 *   title = @Translation("Arable"),
 *   help = @Translation("Query against the Arable API.")
 * )
 */
class Arable extends QueryPluginBase {

  /**
   * {@inheritdoc}
   */
  public function execute(ViewExecutable $view) {
    $data_stream = DataStream::load(1);
    $client = new ArableDeviceClient($data_stream);

    if (isset($this->where)) {
      foreach ($this->where as $where_group => $where) {
        foreach ($where['conditions'] as $condition) {
          // Remove dot from beginning of the string.
          $field_name = ltrim($condition['field'], '.');
          $value = $condition['value'];

          if ($condition['operator'] === 'formula') {
            [$field_name, $condition, $value] = explode(' ', $field_name);
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
          }
          $filters[$field_name] = $value;
        }
      }
    }

    $timezone = \Drupal::currentUser()->getTimeZone();

    $allowed_filters = ['start_time', 'end_time'];
    $filters = $filters ?? [];
    $filters = array_filter($filters, function ($name) use ($allowed_filters) {
      return in_array($name, $allowed_filters);
    }, ARRAY_FILTER_USE_KEY);

    $params = [
      'local_time' => $timezone,
      'limit' => 1000,
    ] + $filters;
    $response = $client->getDeviceData('daily', ['query' => $params]);
    if ($data = Json::decode($response->getBody())) {
      foreach ($data as $index => $row) {
        $row['index'] = $index;
        $row['time'] = strtotime($row['time']);
        $view->result[] = new ResultRow($row);
      }
    }
  }

  public function ensureTable($table, $relationship = NULL) {
    return '';
  }
  public function addField($table, $field, $alias = '', $params = array()) {
    return $field;
  }

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

    $this->where[$group]['conditions'][] = [
      'field'    => $snippet,
      'value'    => $args,
      'operator' => 'formula',
    ];
  }

}
