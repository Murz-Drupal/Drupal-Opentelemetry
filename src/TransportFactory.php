<?php

namespace Drupal\opentelemetry;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use OpenTelemetry\Contrib\Otlp\Protocols;
use OpenTelemetry\SDK\Common\Configuration\Variables;
use OpenTelemetry\SDK\Common\Export\Stream\StreamTransportFactory;
use OpenTelemetry\SDK\Common\Export\TransportFactoryInterface;
use OpenTelemetry\SDK\Common\Export\TransportInterface;

/**
 * Factory for creating instances of OT Transport using settings.
 */
class TransportFactory {

  /**
   * Creates a new TransportFactory instance.
   *
   * @param \OpenTelemetry\SDK\Common\Export\TransportFactoryInterface $transportFactory
   *   An OTEL TransportFactory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   A config factory.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   A logger.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   A messenger.
   */
  public function __construct(
    protected TransportFactoryInterface $transportFactory,
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
  public function create(): TransportInterface {
    $settings = $this->configFactory->get(OpentelemetryTracerService::SETTINGS_KEY);

    // @todo Find a better way to set this.
    if (!getenv(Variables::OTEL_SERVICE_NAME)) {
      putenv(Variables::OTEL_SERVICE_NAME . '=' . ($settings->get(OpentelemetryTracerService::SETTING_SERVICE_NAME) ?: OpentelemetryTracerService::SERVICE_NAME_FALLBACK));
    }

    $endpoint =
      getenv(Variables::OTEL_EXPORTER_OTLP_ENDPOINT)
      ?: $settings->get(OpentelemetryTracerService::SETTING_ENDPOINT)
      ?: OpentelemetryTracerService::ENDPOINT_FALLBACK;

    $protocol =
      getenv(Variables::OTEL_EXPORTER_OTLP_PROTOCOL)
      // ?: $settings->get(OpentelemetryTracerService::SETTING_OTEL_EXPORTER_OTLP_PROTOCOL)
      ?: OpentelemetryTracerService::OTEL_EXPORTER_OTLP_PROTOCOL_FALLBACK;

    $contentType = Protocols::contentType($protocol);
    try {
      $transport = $this->transportFactory->create($endpoint, $contentType);
    }
    catch (\Exception $e) {
      $this->messenger->addError('OpenTelemetry transport is failed on initialization, activated StreamTransport as a fallback');

      // Creating a dummy transport as a fallback.
      $stream = fopen('/dev/null', 'w', FALSE);
      $transportFactory = new StreamTransportFactory();
      $transport = $transportFactory->create($stream, $contentType);
    }
    return $transport;
  }

}
