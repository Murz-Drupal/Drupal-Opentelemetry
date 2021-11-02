<?php

namespace Drupal\opentelemetry\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

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
    return ['opentelemetry.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('OpenTelemetry endpoint'),
      '#description' => $this->t('URL to opentelemetry endpoint.<br/>Example for local Grafana Tempo instance: <code>http://localhost:9411/api/v2/spans</code>'),
      '#default_value' => $this->config('opentelemetry.settings')->get('endpoint', 'http://localhost:9411/api/v2/spans'),
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
    $mergeRulesJson =
    $this->config('opentelemetry.settings')
      ->set('endpoint', $form_state->getValue('endpoint'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
