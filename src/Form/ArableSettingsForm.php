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

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable(static::SETTINGS)
      ->set('default_api_key', $form_state->getValue('default_api_key'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
