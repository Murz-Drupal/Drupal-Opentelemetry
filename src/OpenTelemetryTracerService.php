<?php

namespace Drupal\opentelemetry;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use OpenTelemetry\API\Common\Log\LoggerHolder;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Export\TransportInterface;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

/**
 * Default OpenTelemetry service.
 *
 * @package Drupal\opentelemetry
 */
class OpenTelemetryTracerService implements OpenTelemetryTracerServiceInterface {


  /**
   * A configuration key to use for settings.
   */
  const SETTINGS_KEY = 'opentelemetry.settings';

  /**
   * A setting name to store the endpoint url.
   */
  const SETTING_ENDPOINT = 'endpoint';

  /**
   * A setting name to store the service name.
   */
  const SETTING_SERVICE_NAME = 'service_name';

  /**
   * A setting name to store the root span name.
   */
  const SETTING_ROOT_SPAN_NAME = 'root_span_name';

  /**
   * A setting name to store list of enabled plugins.
   */
  const SETTING_ENABLED_PLUGINS = 'span_plugins_enabled';

  /**
   * A setting name to store debug mode status.
   */
  const SETTING_DEBUG_MODE = 'debug_mode';

  /**
   * A fallback value for the endpoint.
   */
  const ENDPOINT_FALLBACK = 'http://localhost:4318/v1/traces';

  /**
   * A fallback value for service name.
   */
  const SERVICE_NAME_FALLBACK = 'Drupal';

  /**
   * A fallback value for the root span name.
   */
  const ROOT_SPAN_NAME_FALLBACK = 'root';

  /**
   * A configuration key to use for settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $settings;

  /**
   * An OpenTelemetry transport.
   *
   * @var \OpenTelemetry\SDK\Common\Export\TransportInterface
   */
  protected TransportInterface $transport;

  /**
   * An OpenTelemetry Tracer.
   *
   * @var \OpenTelemetry\API\Trace\TracerInterface
   */
  protected TracerInterface $tracer;

  /**
   * Constructs a new OpenTelemetry service.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   A config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger channel factory.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected LoggerChannelFactoryInterface $loggerChannelFactory,
  ) {
    $this->settings = $this->configFactory->get(self::SETTINGS_KEY);

    // @todo Find a better way to set this.
    putenv('OTEL_SERVICE_NAME=' . ($this->settings->get(self::SETTING_SERVICE_NAME) ?: self::SERVICE_NAME_FALLBACK));

    // Attaching the Drupal Logger via proxy to suppress repeating errors.
    $drupalLogger = $this->loggerChannelFactory->get('opentelemetry');

    $logger = new OpenTelemetryLoggerProxy($drupalLogger);
    LoggerHolder::set($logger);

    // @todo Add support for other factories.
    $transportFactory = new OtlpHttpTransportFactory();

    $endpoint = $this->settings->get(self::SETTING_ENDPOINT) ?: self::ENDPOINT_FALLBACK;

    // @todo Add support for custom content type, if needed for users.
    $contentType = 'application/x-protobuf';
    $this->transport = $transportFactory->create($endpoint, $contentType);

    $exporter = new SpanExporter($this->transport);

    $tracerProvider = new TracerProvider(
      new SimpleSpanProcessor($exporter)
    );
    $this->tracer = $tracerProvider->getTracer('io.opentelemetry.contrib.php');
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
  public function getRootSpanName(): string {
    return $this->settings->get(self::SETTING_ROOT_SPAN_NAME) ?: self::ROOT_SPAN_NAME_FALLBACK;
  }

  /**
   * {@inheritdoc}
   */
  public function isPluginEnabled(string $pluginId): ?bool {
    $pluginsEnabled = $this->settings->get(self::SETTING_ENABLED_PLUGINS);
    $pluginStatus = $pluginsEnabled[$pluginId] ?? NULL;
    switch ($pluginStatus) {
      case NULL:
        return NULL;

      case 0:
        return FALSE;

      default:
        return TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isDebugMode(): bool {
    return $this->settings->get(self::SETTING_DEBUG_MODE) ?: FALSE;
  }

}
