<?php

namespace Drupal\farm_arable\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\InOperator;

/**
 * Filter handler for Arable device states..
 *
 * @ViewsFilter("arable_location_state")
 */
class ArableLocationState extends InOperator {

  /**
   * {@inheritDoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      //$this->valueOptions = ['Confirmed', 'Inactive', 'Pending'];
      $options = ['Confirmed', 'Inactive', 'Pending'];
      $this->valueOptions = array_combine($options, $options);
    }
    return $this->valueOptions;
  }

}
