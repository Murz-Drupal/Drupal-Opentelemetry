<?php

namespace Drupal\opentelemetry\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\opentelemetry\OpentelemetryService;
use OpenTelemetry\Contrib\Grpc\GrpcTransport;
use OpenTelemetry\Contrib\Otlp\Protocols;
use OpenTelemetry\SDK\Common\Configuration\Variables;
use Psr\EventDispatcher\StoppableEventInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Subscribes to kernel events to initialize the root span.
 */
class OpentelemetryConfigureSubscriber implements EventSubscriberInterface {
  use StringTranslationTrait;

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
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => ['applyConfigurationOnEvent', 1000],
      KernelEvents::VIEW => ['applyConfigurationOnEvent', 1000],
    ];
  }

  /**
   * Applies OpenTelemetry configuration on events.
   *
   * @param \Psr\EventDispatcher\StoppableEventInterface $event
   *   Any event.
   */
  public function applyConfigurationOnEvent(StoppableEventInterface $event) {
    $this->applyConfiguration();
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

}
