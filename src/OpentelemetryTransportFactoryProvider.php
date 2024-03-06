<?php

namespace Drupal\opentelemetry;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use OpenTelemetry\Contrib\Grpc\GrpcTransport;
use OpenTelemetry\Contrib\Otlp\Protocols;
use OpenTelemetry\SDK\Common\Configuration\Configuration;
use OpenTelemetry\SDK\Common\Configuration\Variables;
use OpenTelemetry\SDK\Common\Export\TransportFactoryInterface;
use OpenTelemetry\SDK\Registry;

/**
 * A Plugin to manage OpenTelemetry Span plugins.
 */
class OpentelemetryTransportFactoryProvider {
  use StringTranslationTrait;

  /**
   * Initialized transports per protocol.
   *
   * @var \OpenTelemetry\SDK\Common\Export\TransportFactoryInterface[]
   */
  protected array $transports;

  /**
   * Data type for traces.
   *
   * @var string
   */
  const DATA_TYPE_TRACES = 'TRACES';

  /**
   * Data type for metrics.
   *
   * @var string
   */
  const DATA_TYPE_METRICS = 'METRICS';

  /**
   * Data type for logs.
   *
   * @var string
   */
  const DATA_TYPE_LOGS = 'LOGS';

  /**
   * Compression method.
   *
   * @var string
   */
  const COMPRESSION = 'gzip';

  /**
   * Creates a new TransportFactory instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   A config factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   A messenger.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected MessengerInterface $messenger,
  ) {
    $this->applyConfiguration();
  }

  /**
   * Gets the using transport for a protocol or creates a new one.
   *
   * @param string|null $dataType
   *   The data type for transport: TRACES|METRICS|LOGS|null.
   *   For null - returns the default transport for all data types.
   *
   * @return \OpenTelemetry\SDK\Common\Export\TransportFactoryInterface
   *   A new or already existing transport factory.
   */
  public function get(string $dataType = NULL): TransportFactoryInterface {
    $protocol ??= $this->getProtocol($dataType);
    if (!isset($this->transports[$protocol])) {
      $factoryClass = Registry::transportFactory($protocol);
      $this->transports[$protocol] = new $factoryClass();
    }
    return $this->transports[$protocol];
  }

  /**
   * Fills the environment variables by the module configuration.
   */
  public function applyConfiguration() {
    if (getenv(OpentelemetryService::SETTINGS_SKIP_READING) == TRUE) {
      return;
    }
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

    if (getenv(Variables::OTEL_EXPORTER_OTLP_PROTOCOL) == Protocols::GRPC) {
      if (!class_exists(GrpcTransport::class)) {
        $this->fillEnv(Variables::OTEL_EXPORTER_OTLP_PROTOCOL, Protocols::HTTP_JSON, TRUE);

        // @see https://www.drupal.org/project/coder/issues/3326197
        // @codingStandardsIgnoreStart
        $message = Markup::create(
          $this->t(OpentelemetryService::GRPC_NA_MESSAGE)
          . ' ' . $this->t('Falling back to <code>http/json</code>.')
        );
        // @codingStandardsIgnoreEnd
        $this->messenger->addError($message);
      }
    }

    $this->fillEnv(Variables::OTEL_EXPORTER_OTLP_COMPRESSION, self::COMPRESSION);
  }

  /**
   * Fills an undefined environment variable by value.
   *
   * @param string $name
   *   The environment variable name.
   * @param string|null $value
   *   The value to set.
   * @param bool $force
   *   Forcing setting the value, if the env variable already filled.
   */
  private function fillEnv(string $name, ?string $value, bool $force = FALSE): void {
    if (getenv($name) === FALSE || $force) {
      if ($value === NULL) {
        putenv($name);
      }
      else {
        putenv($name . '=' . $value);
      }
    }
  }

  /**
   * Returns the protocol to use for transport, depending on the data type.
   *
   * @param string|null $dataType
   *   The data type: TRACES|METRICS|LOGS|null.
   *   For null - returns the default protocol for all data types.
   *
   * @return string
   *   The protocol name.
   */
  private function getProtocol(string $dataType = NULL): string {
    return match ($dataType) {
      self::DATA_TYPE_TRACES => Configuration::has(Variables::OTEL_EXPORTER_OTLP_TRACES_PROTOCOL) ?
      Configuration::getEnum(Variables::OTEL_EXPORTER_OTLP_TRACES_PROTOCOL) :
      Configuration::getEnum(Variables::OTEL_EXPORTER_OTLP_PROTOCOL),

      self::DATA_TYPE_METRICS => Configuration::has(Variables::OTEL_EXPORTER_OTLP_METRICS_PROTOCOL) ?
      Configuration::getEnum(Variables::OTEL_EXPORTER_OTLP_METRICS_PROTOCOL) :
      Configuration::getEnum(Variables::OTEL_EXPORTER_OTLP_PROTOCOL),

      self::DATA_TYPE_LOGS => Configuration::has(Variables::OTEL_EXPORTER_OTLP_LOGS_PROTOCOL) ?
      Configuration::getEnum(Variables::OTEL_EXPORTER_OTLP_LOGS_PROTOCOL) :
      Configuration::getEnum(Variables::OTEL_EXPORTER_OTLP_PROTOCOL),

      default => Configuration::getEnum(Variables::OTEL_EXPORTER_OTLP_PROTOCOL),
    };
  }

}
