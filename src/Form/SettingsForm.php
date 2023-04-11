<?php

namespace Drupal\opentelemetry\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\opentelemetry\OpenTelemetryTraceManager;
use Drupal\opentelemetry\OpenTelemetryTracerService;
use Drupal\opentelemetry\OpenTelemetryTracerServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure opentelemetry settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    protected OpenTelemetryTracerServiceInterface $openTelemetryTracer,
    protected OpenTelemetryTraceManager $openTelemetryTraceManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      $container->get('opentelemetry.tracer'),
      $container->get('plugin.manager.open_telemetry_span'),
    );
  }

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
    return [OpenTelemetryTracerService::SETTINGS_KEY];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $spanForm = $this->openTelemetryTracer->getTracer()->spanBuilder('OpenTelemetry settings form')->startSpan();
    $settings = $this->config(OpenTelemetryTracerService::SETTINGS_KEY);
    $form[OpenTelemetryTracerService::SETTING_ENDPOINT] = [
      '#type' => 'url',
      '#title' => $this->t('OpenTelemetry endpoint'),
      '#description' => $this->t('URL to the OpenTelemetry endpoint. Example for a local OpenTelemetry collector using OTLP HTTP protocol: <code>' . OpenTelemetryTracerService::ENDPOINT_FALLBACK . '</code>'),
      '#default_value' => $settings->get(OpenTelemetryTracerService::SETTING_ENDPOINT),
      '#required' => TRUE,
    ];
    $form[OpenTelemetryTracerService::SETTING_DEBUG_MODE] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Debug mode'),
      '#description' => $this->t('Enables debug mode which outputs trace ids and span ids to the Drupal messenger.'),
      '#default_value' => $settings->get(OpenTelemetryTracerService::SETTING_DEBUG_MODE),
    ];
    $form[OpenTelemetryTracerService::SETTING_SERVICE_NAME] = [
      '#type' => 'textfield',
      '#title' => $this->t('Resource name'),
      '#description' => $this->t('A name to use for the telemetry resource, eg "Drupal".'),
      '#default_value' => $settings->get(OpenTelemetryTracerService::SETTING_SERVICE_NAME),
      '#required' => TRUE,
    ];
    $form[OpenTelemetryTracerService::SETTING_ROOT_SPAN_NAME] = [
      '#type' => 'textfield',
      '#title' => $this->t('Root Span name'),
      '#description' => $this->t('Allows setting a custom name for the root span, eg "root".'),
      '#default_value' => $settings->get(OpenTelemetryTracerService::SETTING_ROOT_SPAN_NAME),
      '#required' => TRUE,
    ];
    if ($spanPlugins = $this->openTelemetryTraceManager->getDefinitions()) {
      $form[OpenTelemetryTracerService::SETTING_ENABLED_PLUGINS] = [
        '#type' => 'checkboxes',
        '#title' => 'Enabled trace plugins',
        '#default_value' => $settings->get(OpenTelemetryTracerService::SETTING_ENABLED_PLUGINS) ?? [],
        '#options' => [],
      ];
      foreach ($spanPlugins as $definition) {
        $form[OpenTelemetryTracerService::SETTING_ENABLED_PLUGINS]['#options'][$definition['id']] = $definition['label'];
        $form[OpenTelemetryTracerService::SETTING_ENABLED_PLUGINS][$definition['id']]['#description'] = $definition['description'];
        $instance = $this->openTelemetryTraceManager->createInstance($definition['id']);
        if (!$instance->isAvailable()) {
          $form[OpenTelemetryTracerService::SETTING_ENABLED_PLUGINS][$definition['id']]['#disabled'] = TRUE;
          $form[OpenTelemetryTracerService::SETTING_ENABLED_PLUGINS][$definition['id']]['#description'] .= '<br/>' . $this->t('Reason for unavailability: @reason', ['@reason' => $instance->getUnavailableReason()]);
        }
      }
    }
    $spanParentForm = $this->openTelemetryTracer->getTracer()->spanBuilder('parent buildForm')->startSpan();
    $form = parent::buildForm($form, $form_state);
    $spanParentForm->end();
    $spanForm->end();
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config(OpenTelemetryTracerService::SETTINGS_KEY)
      ->set(OpenTelemetryTracerService::SETTING_ENDPOINT, $form_state->getValue(OpenTelemetryTracerService::SETTING_ENDPOINT))
      ->set(OpenTelemetryTracerService::SETTING_DEBUG_MODE, $form_state->getValue(OpenTelemetryTracerService::SETTING_DEBUG_MODE))
      ->set(OpenTelemetryTracerService::SETTING_SERVICE_NAME, $form_state->getValue(OpenTelemetryTracerService::SETTING_SERVICE_NAME))
      ->set(OpenTelemetryTracerService::SETTING_ROOT_SPAN_NAME, $form_state->getValue(OpenTelemetryTracerService::SETTING_ROOT_SPAN_NAME))
      ->set(OpenTelemetryTracerService::SETTING_ENABLED_PLUGINS, $form_state->getValue(OpenTelemetryTracerService::SETTING_ENABLED_PLUGINS))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
