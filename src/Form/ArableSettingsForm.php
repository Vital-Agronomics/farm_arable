<?php

namespace Drupal\farm_arable\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides an arable settings form.
 */
class ArableSettingsForm extends ConfigFormbase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'farm_arable.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'farm_arable_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateinterface $form_state) {
    $config = $this->config(static::SETTINGS);

    // Add the default api_key field.
    $form['default_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default Arable API Key'),
      '#description' => $this->t('The default API key used when creating Arable sensor assets.'),
      '#default_value' => $config->get('default_api_key'),
    ];

    // Add form elements to specify data units.
    $form['units'] = [
      '#type' => 'details',
      '#title' => $this->t('Units'),
      '#description' => $this->t('Default units to use when requesting data from the Arable API. See the Arable <a href=":url">documentation</a>.', [':url' => 'https://developer.arable.com/guide/data.html#unit-conversion']),
      '#tree' => TRUE,
      '#open' => TRUE,
    ];

    $form['units']['temp'] = [
      '#type' => 'select',
      '#title' => $this->t('Temperature'),
      '#options' => [
        'c' => $this->t('Celsius'),
        'f' => $this->t('Fahrenheit'),
      ],
      '#default_value' => $config->get('units.temp'),
      '#required' => TRUE,
    ];

    $form['units']['pres'] = [
      '#type' => 'select',
      '#title' => $this->t('Pressure'),
      '#options' => [
        'mb' => $this->t('Millibars'),
        'kp' => $this->t('Kilopascals'),
      ],
      '#default_value' => $config->get('units.pres'),
      '#required' => TRUE,
    ];

    $form['units']['size'] = [
      '#type' => 'select',
      '#title' => $this->t('Size'),
      '#options' => [
        'mm' => $this->t('Millimeters'),
        'in' => $this->t('Inches'),
      ],
      '#default_value' => $config->get('units.size'),
      '#required' => TRUE,
    ];

    $form['units']['ratio'] = [
      '#type' => 'select',
      '#title' => $this->t('Ratios'),
      '#options' => [
        'pct' => $this->t('Percentages'),
        'dec' => $this->t('Decimals'),
      ],
      '#default_value' => $config->get('units.ratio'),
      '#required' => TRUE,
    ];

    $form['units']['speed'] = [
      '#type' => 'select',
      '#title' => $this->t('Speed'),
      '#options' => [
        'mps' => $this->t('Meters per second'),
        'kph' => $this->t('Kilometers per hour'),
        'mph' => $this->t('Miles per hour'),
      ],
      '#default_value' => $config->get('units.speed'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable(static::SETTINGS)
      ->set('default_api_key', $form_state->getValue('default_api_key'))
      ->set('units', $form_state->getValue('units'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
