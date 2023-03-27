<?php

namespace Drupal\opentelemetry\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\opentelemetry\OpenTelemetryService;

/**
 * Configure opentelemetry settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'opentelemetry_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [OpenTelemetryService::SETTINGS_KEY];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $settings = $this->config(OpenTelemetryService::SETTINGS_KEY);
    $form['endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('OpenTelemetry endpoint'),
      '#description' => $this->t('URL to OpenTelemetry endpoint.<br/>Example for local Grafana Tempo instance: <code>http://localhost:9411/api/v2/spans</code>'),
      '#default_value' => $settings->get('endpoint', 'http://localhost:9411/api/v2/spans'),
    ];
    $form['root_span_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Root span name'),
      '#description' => $this->t('Allows setting a custom name for the root span. If empty, uses default value: @value', [
        '@value' => OpenTelemetryService::ROOT_SPAN_DEFAULT_NAME,
      ]),
      '#default_value' => $settings->get('root_span_name'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config(OpenTelemetryService::SETTINGS_KEY)
      ->set('endpoint', $form_state->getValue('endpoint'))
      ->set('transport', $form_state->getValue('transport'))
      ->set('root_span_name', $form_state->getValue('root_span_name') ?: NULL)
      ->save();
    parent::submitForm($form, $form_state);
  }

}
