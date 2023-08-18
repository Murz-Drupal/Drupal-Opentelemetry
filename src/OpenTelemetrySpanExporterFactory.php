<?php

namespace Drupal\opentelemetry;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use OpenTelemetry\SDK\Common\Configuration\Variables;
use OpenTelemetry\SDK\Trace\SpanExporter\SpanExporterFactoryInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;

/**
 * Factory for creating instances of OT Transport using settings.
 */
class OpenTelemetrySpanExporterFactory {

  /**
   * Creates a new TransportFactory instance.
   *
   * @param \OpenTelemetry\SDK\Trace\SpanExporter\SpanExporterFactoryInterface $spanExporterFactory
   *   An OTEL TransportFactory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   A config factory.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   A logger.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   A messenger.
   */
  public function __construct(
    protected SpanExporterFactoryInterface $spanExporterFactory,
    protected ConfigFactoryInterface $configFactory,
    protected LoggerChannelInterface $logger,
    protected MessengerInterface $messenger,
  ) {
  }

  /**
   * Creates an OT Transport.
   *
   * @return \OpenTelemetry\SDK\Common\Export\TransportInterface
   *   The OT transport.
   */
  public function create(): SpanExporterInterface {
    if (getenv(OpentelemetryService::SETTINGS_SKIP_READING) == FALSE) {
      $settings = $this->configFactory->get(OpentelemetryService::SETTINGS_KEY);

      $this->fillEnv(Variables::OTEL_SERVICE_NAME,
        $settings->get(OpentelemetryService::SETTING_SERVICE_NAME)
        ?: OpentelemetryService::SERVICE_NAME_FALLBACK
      );

      if ($authorization = $settings->get(OpentelemetryService::SETTING_AUTHORIZATION)) {
        $this->fillEnv(Variables::OTEL_EXPORTER_OTLP_HEADERS, "Authorization=$authorization");
      }

      $this->fillEnv(Variables::OTEL_EXPORTER_OTLP_ENDPOINT,
        $settings->get(OpentelemetryService::SETTING_ENDPOINT)
        ?: NULL
      );

      $this->fillEnv(Variables::OTEL_EXPORTER_OTLP_PROTOCOL,
        $settings->get(OpentelemetryService::SETTING_OTEL_EXPORTER_OTLP_PROTOCOL)
        ?: OpentelemetryService::OTEL_EXPORTER_OTLP_PROTOCOL_FALLBACK
      );
    }

    $spanExporter = $this->spanExporterFactory->create();

    return $spanExporter;
  }

  /**
   * Fills an undefined environment variable by value.
   *
   * @param string $name
   *   The environment variable name.
   * @param string|null $value
   *   The value to set.
   */
  private function fillEnv(string $name, ?string $value): void {
    if (getenv($name) === FALSE) {
      if ($value === NULL) {
        putenv($name);
      }
      else {
        putenv($name . '=' . $value);
      }
    }
  }

}
