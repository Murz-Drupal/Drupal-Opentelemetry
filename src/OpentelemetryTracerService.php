<?php

namespace Drupal\opentelemetry;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use OpenTelemetry\API\Common\Log\LoggerHolder;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\SDK\Common\Configuration\KnownValues;

/**
 * Default OpenTelemetry service.
 *
 * @package Drupal\opentelemetry
 */
class OpentelemetryTracerService implements OpentelemetryTracerServiceInterface {


  /**
   * A configuration key to use for settings.
   */
  const SETTINGS_KEY = 'opentelemetry.settings';

  /**
   * A name for environment variable to skip settings reading.
   *
   * Can be used to skip loading settings, if all configuration is passed
   * through the environment variables.
   */
  const SETTINGS_SKIP_READING = 'DRUPAL_OPENTELEMETRY_SETTINGS_SKIP_READING';

  /**
   * A setting name to store the endpoint url.
   */
  const SETTING_ENDPOINT = 'endpoint';
  /**
   * A setting name to store the endpoint url.
   */
  const SETTING_OTEL_EXPORTER_OTLP_PROTOCOL = 'otel_exporter_otlp_protocol';

  /**
   * A setting name to store the service name.
   */
  const SETTING_SERVICE_NAME = 'service_name';

  /**
   * A setting name to store the logger type.
   */
  const SETTING_LOGGER_DEDUPLICATION = 'logger_deduplication';

  /**
   * A setting name to store list of enabled plugins.
   */
  const SETTING_ENABLED_PLUGINS = 'span_plugins_enabled';

  /**
   * A setting name to store the debug mode status.
   */
  const SETTING_DEBUG_MODE = 'debug_mode';

  /**
   * A setting name to store the authorization header.
   */
  const SETTING_AUTHORIZATION = 'authorization';

  /**
   * A fallback content type for the endpoint.
   */
  const OTEL_EXPORTER_OTLP_PROTOCOL_FALLBACK = KnownValues::VALUE_HTTP_PROTOBUF;

  /**
   * A fallback value for the service name.
   */
  const SERVICE_NAME_FALLBACK = 'Drupal';

  /**
   * The OpenTelemetry Tracer.
   *
   * @var \OpenTelemetry\API\Trace\TracerInterface
   */
  protected ?TracerInterface $tracer = NULL;

  /**
   * The trace id.
   *
   * @var string
   */
  protected ?string $traceId = NULL;

  /**
   * Constructs a new OpenTelemetry service.
   *
   * @param \OpenTelemetry\API\Trace\TracerProviderInterface $tracerProvider
   *   The tracer provider.
   * @param string $tracerName
   *   The tracer name.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   A config factory.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   A logger.
   * @param \Drupal\opentelemetry\OpentelemetryTraceManager $opentelemetryTraceManager
   *   The OpenTelemetry Trace Manager.
   */
  public function __construct(
    protected TracerProviderInterface $tracerProvider,
    protected string $tracerName,
    protected ConfigFactoryInterface $configFactory,
    protected LoggerChannelInterface $logger,
    protected OpentelemetryTraceManager $opentelemetryTraceManager,
  ) {
    $settings = $this->configFactory->get(self::SETTINGS_KEY);

    // Attaching the Drupal logger to the tracer.
    if ($settings->get(self::SETTING_LOGGER_DEDUPLICATION) ?? TRUE) {
      $logger = new OpentelemetryLoggerProxy($this->logger);
      LoggerHolder::set($logger);
    }
    else {
      LoggerHolder::set($this->logger);
    }

    $this->tracer = $tracerProvider->getTracer($this->tracerName);
  }

  /**
   * {@inheritdoc}
   */
  public function getTracer(): ?TracerInterface {
    return $this->tracer;
  }

  /**
   * {@inheritdoc}
   */
  public function isPluginEnabled(string $pluginId): ?bool {
    $settings = $this->configFactory->get(self::SETTINGS_KEY);
    $pluginsEnabled = $settings->get(self::SETTING_ENABLED_PLUGINS);
    $pluginStatus = $pluginsEnabled[$pluginId] ?? NULL;
    if ($pluginStatus === NULL) {
      $instance = $this->opentelemetryTraceManager->createInstance($pluginId);
      if ($instance->enabledByDefault()) {
        $pluginStatus = TRUE;
      }
    }
    return match ($pluginStatus) {
      NULL => NULL,
      0 => FALSE,
      default => TRUE,
    };
  }

  /**
   * {@inheritdoc}
   */
  public function isDebugMode(): bool {
    $settings = $this->configFactory->get(self::SETTINGS_KEY);
    return $settings->get(self::SETTING_DEBUG_MODE) ?: FALSE;
  }

  /**
   * Gets the trace id.
   *
   * @return string
   *   The trace id.
   */
  public function getTraceId(): ?string {
    return $this->traceId;
  }

  /**
   * Sets the trace id.
   *
   * @param string $traceId
   *   The trace id.
   *
   * @return self
   *   Returns the self object.
   */
  public function setTraceId(string $traceId): self {
    $this->traceId = $traceId;
    return $this;
  }

}
