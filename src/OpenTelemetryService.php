<?php

namespace Drupal\opentelemetry;

use Drupal\Core\Config\ConfigFactory;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

/**
 * Default OpenTelemetry service.
 *
 * @package Drupal\opentelemetry
 */
class OpenTelemetryService implements OpenTelemetryServiceInterface {


  /**
   * A configuration key to use for settings.
   *
   * @var string
   */
  const SETTINGS_KEY = 'opentelemetry.settings';

  /**
   * A default name for root span to use.
   *
   * @var string
   */
  const ROOT_SPAN_DEFAULT_NAME = 'Drupal Request';

  /**
   * Constructs a new OpenTelemetry service.
   *
   * @param Drupal\Core\Config\ConfigFactory $configFactory
   *   A config factory.
   */
  public function __construct(
    protected ConfigFactory $configFactory,
  ) {
    $this->settings = $this->configFactory->get(self::SETTINGS_KEY);

    // @todo Add support for other factories.
    $this->transportFactory = new OtlpHttpTransportFactory();

    $this->endpoint = $this->settings->get('endpoint', 'http://localhost:4318/v1/traces');

    $contentType = 'application/x-protobuf';
    $this->transport = $this->transportFactory->create($this->endpoint, $contentType);

    $this->exporter = new SpanExporter($this->transport);

    $this->tracerProvider = new TracerProvider(
      new SimpleSpanProcessor(
        $this->exporter
      )
    );
    $this->tracer = $this->tracerProvider->getTracer('io.opentelemetry.contrib.php');
  }

  /**
   * {@inheritdoc}
   */
  public function getTracer(): TracerInterface {
    return $this->tracer;
  }

  /**
   * {@inheritdoc}
   */
  public function getRootSpanName(): string {
    return $this->settings->get('root_span_name', self::ROOT_SPAN_DEFAULT_NAME) ?: self::ROOT_SPAN_DEFAULT_NAME;
  }

}
