<?php

namespace Drupal\opentelemetry\Form;

use Drupal\Core\Config\Schema\Mapping;
use Drupal\Core\Config\Schema\Undefined;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\opentelemetry\OpentelemetryService;
use Drupal\opentelemetry\OpentelemetryServiceInterface;
use Drupal\opentelemetry\OpentelemetryTraceManager;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Contrib\Grpc\GrpcTransport;
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
  final public function __construct(
    protected OpentelemetryServiceInterface $openTelemetry,
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
      $container->get('opentelemetry'),
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
    return [OpentelemetryService::SETTINGS_KEY];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $tracerActive = $this->openTelemetry->hasTracer();
    if ($tracerActive) {
      $spanForm = $this->openTelemetry->getTracer()->spanBuilder('OpenTelemetry settings form')->setSpanKind(SpanKind::KIND_SERVER)->startSpan();
    }
    $settings = $this->config(OpentelemetryService::SETTINGS_KEY);
    $this->settingsTyped = $this->configTyped->get('opentelemetry.settings');
    $form[OpentelemetryService::SETTING_ENDPOINT] = [
      '#type' => 'url',
      '#title' => $this->getSettingLabel(OpentelemetryService::SETTING_ENDPOINT),
      '#description' => $this->t('URL to the OpenTelemetry endpoint. Example for a local OpenTelemetry collector using OTLP HTTP protocol: <code>@example</code>', [
        '@example' => 'http://localhost:4318',
      ]),
      '#default_value' => $settings->get(OpentelemetryService::SETTING_ENDPOINT),
      '#required' => FALSE,
    ];
    $form[OpentelemetryService::SETTING_DISABLE] = [
      '#type' => 'checkbox',
      '#title' => $this->getSettingLabel(OpentelemetryService::SETTING_DISABLE),
      '#description' => $this->t('Disables the initialization of the OpenTelemetry tracer instance.'),
      '#default_value' => $settings->get(OpentelemetryService::SETTING_DISABLE),
      '#required' => FALSE,
    ];
    $form[OpentelemetryService::SETTING_AUTHORIZATION] = [
      '#type' => 'textfield',
      '#title' => $this->getSettingLabel(OpentelemetryService::SETTING_AUTHORIZATION),
      '#description' => $this->t('The <code>Authorization</code> header value. Example: <code>@example</code>. Keep empty if no authorization is required.', [
        '@example' => 'Bearer: wOMdCaSGS8JZc2Fva5',
      ]),
      '#default_value' => $settings->get(OpentelemetryService::SETTING_AUTHORIZATION),
      '#required' => FALSE,
    ];
    $form[OpentelemetryService::SETTING_OTEL_EXPORTER_OTLP_PROTOCOL] = [
      '#type' => 'radios',
      '#title' => $this->getSettingLabel(OpentelemetryService::SETTING_OTEL_EXPORTER_OTLP_PROTOCOL),
      '#description' => $this->t('OpenTelemetry protocol, default value: <code>@url</code>', [
        '@url' => OpentelemetryService::OTEL_EXPORTER_OTLP_PROTOCOL_FALLBACK,
      ]),
      '#default_value' => $settings->get(OpentelemetryService::SETTING_OTEL_EXPORTER_OTLP_PROTOCOL),
      '#options' => [
        Protocols::HTTP_PROTOBUF => Protocols::HTTP_PROTOBUF . ' ' . $this->t('(HTTP Protobuf, Protocol Buffers)'),
        Protocols::HTTP_JSON => Protocols::HTTP_JSON . ' ' . $this->t('(HTTP JSON)'),
        Protocols::HTTP_NDJSON => Protocols::HTTP_NDJSON . ' ' . $this->t('(HTTP NDJSON, newline delimited JSON)'),
        Protocols::GRPC => Protocols::GRPC . ' ' . $this->t('(gRPC Remote Procedure Calls)'),
      ],
      '#required' => TRUE,
    ];
    if (!class_exists(GrpcTransport::class)) {
      $form[OpentelemetryService::SETTING_OTEL_EXPORTER_OTLP_PROTOCOL][Protocols::GRPC]['#disabled'] = TRUE;
      // @see https://www.drupal.org/project/coder/issues/3326197
      // @codingStandardsIgnoreStart
      $form[OpentelemetryService::SETTING_OTEL_EXPORTER_OTLP_PROTOCOL][Protocols::GRPC]['#description'] =
        $this->t(OpentelemetryService::GRPC_NA_MESSAGE);
      // @codingStandardsIgnoreEnd
    }
    $form[OpentelemetryService::SETTING_DEBUG_MODE] = [
      '#type' => 'checkbox',
      '#title' => $this->getSettingLabel(OpentelemetryService::SETTING_DEBUG_MODE),
      '#description' => $this->t('Enables debug mode which outputs trace ids and span ids to the Drupal messenger.'),
      '#default_value' => $settings->get(OpentelemetryService::SETTING_DEBUG_MODE),
    ];
    $form[OpentelemetryService::SETTING_SERVICE_NAME] = [
      '#type' => 'textfield',
      '#title' => $this->getSettingLabel(OpentelemetryService::SETTING_SERVICE_NAME),
      '#description' => $this->t('A name to use for the telemetry resource, example: <code>Drupal</code>.'),
      '#default_value' => $settings->get(OpentelemetryService::SETTING_SERVICE_NAME),
      '#required' => TRUE,
    ];
    $form[OpentelemetryService::SETTING_LOGGER_DEDUPLICATION] = [
      '#type' => 'checkbox',
      '#title' => $this->getSettingLabel(OpentelemetryService::SETTING_LOGGER_DEDUPLICATION),
      '#description' => $this->t("Trace exporter can provide a lot of errors if the connection is failed, that can slow down your site. Enable this option to suppress logging prevent logging identical messages in a row messages in a row."),
      '#default_value' => $settings->get(OpentelemetryService::SETTING_LOGGER_DEDUPLICATION),
    ];
    if ($tracePlugins = $this->opentelemetryTraceManager->getDefinitions()) {
      $pluginsEnabled = $settings->get(OpentelemetryService::SETTING_ENABLED_PLUGINS) ?? [];
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
      $form[OpentelemetryService::SETTING_ENABLED_PLUGINS] = [
        '#type' => 'checkboxes',
        '#title' => $this->getSettingLabel(OpentelemetryService::SETTING_ENABLED_PLUGINS),
        '#options' => $pluginsAvailable,
        '#default_value' => $pluginsEnabled,
      ];
      foreach ($pluginsDescription as $pluginId => $pluginDescription) {
        $form[OpentelemetryService::SETTING_ENABLED_PLUGINS][$pluginId] = $pluginDescription;
      }
    }
    $form[OpentelemetryService::SETTING_LOG_REQUESTS] = [
      '#type' => 'checkbox',
      '#title' => $this->getSettingLabel(OpentelemetryService::SETTING_LOG_REQUESTS),
      '#description' => $this->t("Log every request to Drupal Logger as a separate debug record."),
      '#default_value' => $settings->get(OpentelemetryService::SETTING_LOG_REQUESTS),
    ];
    if ($tracerActive) {
      $spanParentForm = $this->openTelemetry->getTracer()->spanBuilder('parent buildForm')->setSpanKind(SpanKind::KIND_SERVER)->startSpan();
    }
    $form = parent::buildForm($form, $form_state);
    if ($tracerActive) {
      $spanParentForm->end();
      $spanForm->end();
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config(OpentelemetryService::SETTINGS_KEY)
      ->set(OpentelemetryService::SETTING_ENDPOINT, $form_state->getValue(OpentelemetryService::SETTING_ENDPOINT))
      ->set(OpentelemetryService::SETTING_DISABLE, $form_state->getValue(OpentelemetryService::SETTING_DISABLE))
      ->set(OpentelemetryService::SETTING_OTEL_EXPORTER_OTLP_PROTOCOL, $form_state->getValue(OpentelemetryService::SETTING_OTEL_EXPORTER_OTLP_PROTOCOL))
      ->set(OpentelemetryService::SETTING_DEBUG_MODE, $form_state->getValue(OpentelemetryService::SETTING_DEBUG_MODE))
      ->set(OpentelemetryService::SETTING_SERVICE_NAME, $form_state->getValue(OpentelemetryService::SETTING_SERVICE_NAME))
      ->set(OpentelemetryService::SETTING_LOGGER_DEDUPLICATION, $form_state->getValue(OpentelemetryService::SETTING_LOGGER_DEDUPLICATION))
      ->set(OpentelemetryService::SETTING_ENABLED_PLUGINS, $form_state->getValue(OpentelemetryService::SETTING_ENABLED_PLUGINS))
      ->set(OpentelemetryService::SETTING_AUTHORIZATION, $form_state->getValue(OpentelemetryService::SETTING_AUTHORIZATION))
      ->set(OpentelemetryService::SETTING_LOG_REQUESTS, $form_state->getValue(OpentelemetryService::SETTING_LOG_REQUESTS))
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
