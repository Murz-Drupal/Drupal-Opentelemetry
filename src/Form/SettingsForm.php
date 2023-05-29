<?php

namespace Drupal\opentelemetry\Form;

use Drupal\Core\Config\Schema\Mapping;
use Drupal\Core\Config\Schema\Undefined;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\opentelemetry\OpentelemetryTraceManager;
use Drupal\opentelemetry\OpentelemetryTracerService;
use Drupal\opentelemetry\OpentelemetryTracerServiceInterface;
use OpenTelemetry\Contrib\Otlp\Protocols;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure opentelemetry settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The typed opentelemetry settings.
   *
   * @var \Drupal\Core\Config\Schema\Mapping
   */
  private Mapping $settingsTyped;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    protected OpentelemetryTracerServiceInterface $openTelemetryTracer,
    protected OpentelemetryTraceManager $opentelemetryTraceManager,
    protected TypedConfigManagerInterface $configTyped,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      $container->get('opentelemetry.tracer'),
      $container->get('plugin.manager.opentelemetry_trace'),
      $container->get('config.typed'),
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
    return [OpentelemetryTracerService::SETTINGS_KEY];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $spanForm = $this->openTelemetryTracer->getTracer()->spanBuilder('OpenTelemetry settings form')->startSpan();
    $settings = $this->config(OpentelemetryTracerService::SETTINGS_KEY);
    $this->settingsTyped = $this->configTyped->get('opentelemetry.settings');
    $form[OpentelemetryTracerService::SETTING_ENDPOINT] = [
      '#type' => 'url',
      '#title' => $this->getSettingLabel(OpentelemetryTracerService::SETTING_ENDPOINT),
      '#description' => $this->t('URL to the OpenTelemetry endpoint. Set to empty to disable uploading traces. Example for a local OpenTelemetry collector using OTLP HTTP protocol: <code>@example</code>', [
        '@example' => 'http://localhost:4318',
      ]),
      '#default_value' => $settings->get(OpentelemetryTracerService::SETTING_ENDPOINT),
      '#required' => FALSE,
    ];
    $form[OpentelemetryTracerService::SETTING_AUTHORIZATION] = [
      '#type' => 'textfield',
      '#title' => $this->getSettingLabel(OpentelemetryTracerService::SETTING_AUTHORIZATION),
      '#description' => $this->t('The <code>Authorization</code> header value. Example: <code>@example</code>. Keep empty if no authorization is required.', [
        '@example' => 'Bearer: wOMdCaSGS8JZc2Fva5',
      ]),
      '#default_value' => $settings->get(OpentelemetryTracerService::SETTING_AUTHORIZATION),
      '#required' => FALSE,
    ];
    $form[OpentelemetryTracerService::SETTING_OTEL_EXPORTER_OTLP_PROTOCOL] = [
      '#type' => 'select',
      '#title' => $this->getSettingLabel(OpentelemetryTracerService::SETTING_OTEL_EXPORTER_OTLP_PROTOCOL),
      '#description' => $this->t('OpenTelemetry protocol, default value: <code>@url</code>', [
        '@url' => OpentelemetryTracerService::OTEL_EXPORTER_OTLP_PROTOCOL_FALLBACK,
      ]),
      '#default_value' => $settings->get(OpentelemetryTracerService::SETTING_OTEL_EXPORTER_OTLP_PROTOCOL),
      '#options' => [
        Protocols::GRPC => Protocols::GRPC,
        Protocols::HTTP_PROTOBUF => Protocols::HTTP_PROTOBUF,
        Protocols::HTTP_JSON => Protocols::HTTP_JSON,
        Protocols::HTTP_NDJSON => Protocols::HTTP_NDJSON,
      ],
      '#required' => TRUE,
    ];
    $form[OpentelemetryTracerService::SETTING_DEBUG_MODE] = [
      '#type' => 'checkbox',
      '#title' => $this->getSettingLabel(OpentelemetryTracerService::SETTING_DEBUG_MODE),
      '#description' => $this->t('Enables debug mode which outputs trace ids and span ids to the Drupal messenger.'),
      '#default_value' => $settings->get(OpentelemetryTracerService::SETTING_DEBUG_MODE),
    ];
    $form[OpentelemetryTracerService::SETTING_SERVICE_NAME] = [
      '#type' => 'textfield',
      '#title' => $this->getSettingLabel(OpentelemetryTracerService::SETTING_SERVICE_NAME),
      '#description' => $this->t('A name to use for the telemetry resource, example: <code>Drupal</code>.'),
      '#default_value' => $settings->get(OpentelemetryTracerService::SETTING_SERVICE_NAME),
      '#required' => TRUE,
    ];
    $form[OpentelemetryTracerService::SETTING_LOGGER_DEDUPLICATION] = [
      '#type' => 'checkbox',
      '#title' => $this->getSettingLabel(OpentelemetryTracerService::SETTING_LOGGER_DEDUPLICATION),
      '#description' => $this->t("Trace exporter can provide a lot of errors if the connection is failed, that can slow down your site. Enable this option to suppress logging prevent logging identical messages in a row messages in a row."),
      '#default_value' => $settings->get(OpentelemetryTracerService::SETTING_LOGGER_DEDUPLICATION),
    ];
    if ($tracePlugins = $this->opentelemetryTraceManager->getDefinitions()) {
      $pluginsEnabled = $settings->get(OpentelemetryTracerService::SETTING_ENABLED_PLUGINS) ?? [];
      $pluginsAvailable = [];
      $pluginsDescription = [];
      foreach ($tracePlugins as $definition) {
        $id = $definition['id'];
        $pluginsAvailable[$id] = $definition['label'];
        $pluginsDescription[$id] = [
          '#description' => $definition['description'],
        ];
        $instance = $this->opentelemetryTraceManager->createInstance($id);
        if (!$instance->isAvailable()) {
          $pluginsDescription[$id]['#disabled'] = TRUE;
          $pluginsDescription[$id]['#description'] .= '<br/>' . $this->t('Reason for unavailability: @reason', ['@reason' => $instance->getUnavailableReason()]);
        }
      }
      $form[OpentelemetryTracerService::SETTING_ENABLED_PLUGINS] = [
        '#type' => 'checkboxes',
        '#title' => $this->getSettingLabel(OpentelemetryTracerService::SETTING_ENABLED_PLUGINS),
        '#options' => $pluginsAvailable,
        '#default_value' => $pluginsEnabled,
      ];
      foreach ($pluginsDescription as $pluginId => $pluginDescription) {
        $form[OpentelemetryTracerService::SETTING_ENABLED_PLUGINS][$pluginId] = $pluginDescription;
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
    $this->config(OpentelemetryTracerService::SETTINGS_KEY)
      ->set(OpentelemetryTracerService::SETTING_ENDPOINT, $form_state->getValue(OpentelemetryTracerService::SETTING_ENDPOINT))
      ->set(OpentelemetryTracerService::SETTING_OTEL_EXPORTER_OTLP_PROTOCOL, $form_state->getValue(OpentelemetryTracerService::SETTING_OTEL_EXPORTER_OTLP_PROTOCOL))
      ->set(OpentelemetryTracerService::SETTING_DEBUG_MODE, $form_state->getValue(OpentelemetryTracerService::SETTING_DEBUG_MODE))
      ->set(OpentelemetryTracerService::SETTING_SERVICE_NAME, $form_state->getValue(OpentelemetryTracerService::SETTING_SERVICE_NAME))
      ->set(OpentelemetryTracerService::SETTING_ENABLED_PLUGINS, $form_state->getValue(OpentelemetryTracerService::SETTING_ENABLED_PLUGINS))
      ->set(OpentelemetryTracerService::SETTING_AUTHORIZATION, $form_state->getValue(OpentelemetryTracerService::SETTING_AUTHORIZATION))
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Gets a label for a setting from typed settings object.
   */
  private function getSettingLabel(string $key, ?string $fallback = NULL) {
    try {
      $setting = $this->settingsTyped->get($key);
      if ($setting instanceof Undefined) {
        throw new \Exception('Undefined key in schema');
      }
      $label = $setting->getDataDefinition()->getLabel();
    }
    catch (\Throwable $e) {
      $label = $fallback ?: $key;
    }
    return $label;
  }

}
