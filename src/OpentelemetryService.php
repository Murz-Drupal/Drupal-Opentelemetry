<?php

namespace Drupal\opentelemetry;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use OpenTelemetry\API\LoggerHolder;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\SpanContextValidator;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SDK\Common\Configuration\KnownValues;
use OpenTelemetry\SDK\Trace\Span;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Default OpenTelemetry service.
 *
 * @package Drupal\opentelemetry
 */
class OpentelemetryService implements OpentelemetryServiceInterface, EventSubscriberInterface {

  use StringTranslationTrait;

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
   * A setting name to store the log requests option.
   */
  const SETTING_LOG_REQUESTS = 'log_requests';

  /**
   * A setting name to store the disable flag.
   */
  const SETTING_DISABLE = 'disable';

  /**
   * A fallback content type for the endpoint.
   */
  const OTEL_EXPORTER_OTLP_PROTOCOL_FALLBACK = KnownValues::VALUE_HTTP_PROTOBUF;

  /**
   * A fallback value for the service name.
   */
  const SERVICE_NAME_FALLBACK = 'Drupal';

  /**
   * An error message text when the gRPC is not available.
   */
  const GRPC_NA_MESSAGE = 'OpenTelemetry gRPC protocol is not available. Install Composer library <code>open-telemetry/transport-grpc</code> to enable, or change the protocol in the settings.';

  /**
   * The root span.
   *
   * @var \OpenTelemetry\API\Trace\SpanInterface
   */
  protected SpanInterface $rootSpan;

  /**
   * The root scope.
   *
   * @var \OpenTelemetry\Context\ScopeInterface
   */
  protected ScopeInterface $rootScope;

  /**
   * The module settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $settings;

  /**
   * The OpenTelemetry Tracer.
   *
   * @var \OpenTelemetry\API\Trace\TracerInterface
   */
  protected ?TracerInterface $tracer = NULL;

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
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The Request Stack.
   * @param \Drupal\opentelemetry\OpentelemetryLoggerProxy $opentelemetryLoggerProxy
   *   The OpentelemetryLoggerProxy.
   */
  public function __construct(
    protected TracerProviderInterface $tracerProvider,
    protected string $tracerName,
    protected ConfigFactoryInterface $configFactory,
    protected LoggerChannelInterface $logger,
    protected OpentelemetryTraceManager $opentelemetryTraceManager,
    protected RequestStack $requestStack,
    protected OpentelemetryLoggerProxy $opentelemetryLoggerProxy,
  ) {
    $this->settings = $this->configFactory->get(self::SETTINGS_KEY);

    // Doing nothing if the disable flag is active.
    if ($this->settings->get(self::SETTING_DISABLE) ?? FALSE) {
      return;
    }

    // Attaching the Drupal logger to the tracer.
    if ($this->settings->get(self::SETTING_LOGGER_DEDUPLICATION) ?? TRUE) {
      LoggerHolder::set($opentelemetryLoggerProxy);
    }
    else {
      LoggerHolder::set($this->logger);
    }

    $this->tracer = $tracerProvider->getTracer($this->tracerName);
    $this->initRootSpan();
    if ($this->isDebugMode()) {
      // Calling statically to not add the dependency for non debug mode.
      // @codingStandardsIgnoreStart
      \Drupal::messenger()->addStatus(
        $this->t('@name started. The root trace id: <code>@trace_id</code>, span id: <code>@span_id</code>.', [
          '@name' => 'OpentelemetryService',
          '@trace_id' => $this->getTraceId(),
          '@span_id' => $this->rootSpan->getContext()->getSpanId(),
        ])
      );
      // @codingStandardsIgnoreEnd
    }

  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::TERMINATE => ['onTerminate', 100],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function hasTracer(): bool {
    return isset($this->tracer) && !empty($this->tracer);
  }

  /**
   * {@inheritdoc}
   */
  public function getTracer(): ?TracerInterface {
    return $this->tracer ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isPluginEnabled(string $pluginId): ?bool {
    $pluginsEnabled = $this->settings->get(self::SETTING_ENABLED_PLUGINS);
    $pluginStatus = $pluginsEnabled[$pluginId] ?? NULL;
    if ($pluginStatus === NULL) {
      $instance = $this->opentelemetryTraceManager->createInstance($pluginId);
      if ($instance->enabledByDefault()) {
        $pluginStatus = TRUE;
      }
    }
    return match ($pluginStatus) {
      NULL => NULL,
      "0" => FALSE,
      default => TRUE,
    };
  }

  /**
   * {@inheritdoc}
   */
  public function isDebugMode(): bool {
    return $this->settings->get(self::SETTING_DEBUG_MODE) ?: FALSE;
  }

  /**
   * Gets the trace id.
   *
   * @return string
   *   The trace id.
   */
  public function getTraceId(): ?string {
    if (!isset($this->rootSpan)) {
      return NULL;
    }
    return $this->rootSpan->getContext()->getTraceId();
  }

  /**
   * Gets the current span.
   *
   * @return \OpenTelemetry\API\Trace\SpanInterface
   *   The current span object.
   */
  public function getCurrentSpan(): SpanInterface {
    return Span::getCurrent();
  }

  /**
   * Gets the root scope.
   *
   * @return \OpenTelemetry\Context\ScopeInterface
   *   The root scope.
   */
  public function getRootScope(): ScopeInterface {
    return $this->rootScope;
  }

  /**
   * {@inheritdoc}
   */
  public function isLogRequestsEnabled(): bool {
    return $this->settings->get(self::SETTING_LOG_REQUESTS) ?: FALSE;
  }

  /**
   * Initiates the root span.
   */
  public function initRootSpan() {
    $tracer = $this->getTracer();
    $request = $this->requestStack->getMainRequest();
    $spanName = $this->createRequestSpanName($request);
    $parent = TraceContextPropagator::getInstance()->extract($request->headers->all());

    $this->rootSpan = $tracer->spanBuilder($spanName)
      ->setStartTimestamp((int) ($request->server->get('REQUEST_TIME_FLOAT') * 1e9))
      ->setParent($parent)
      ->setAttribute('kind', 'root')
      ->startSpan();

    $this->rootScope = $this->rootSpan->activate();
  }

  /**
   * Creates a span name for request from components.
   *
   * Formats to string: "$method $uri [($type)]".
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A request.
   * @param string $label
   *   The label to add to the span name.
   *
   * @return string
   *   The prepared span name.
   */
  public function createRequestSpanName(Request $request, string $label = NULL): string {
    $name = $request->getMethod() . ' ' . $request->getRequestUri();
    if ($label) {
      $name .= " ($label)";
    }
    return $name;
  }

  /**
   * Ends the root span and detaches the scope.
   *
   * @param \Symfony\Component\HttpKernel\Event\TerminateEvent $event
   *   A TerminateEvent.
   */
  public function onTerminate(TerminateEvent $event) {
    if ($this->isLogRequestsEnabled()) {
      $this->logger->log(RfcLogLevel::DEBUG, 'Request @request, trace id @trace_id', [
        '@request' => $this->rootSpan->getName(),
        '@trace_id' => $this->getTraceId(),
      ]);
    }
    $this->rootSpan->end();
    $this->rootScope->detach();
  }

  /**
   * For case if the terminate event is not fired by some reason.
   */
  public function __destruct() {
    if (isset($this->rootScope) && !empty($this->rootScope)) {
      $span = $this->getCurrentSpan();
      $spanId = $span->getContext()->getSpanId();
      if ($spanId !== SpanContextValidator::INVALID_SPAN) {
        $this->rootScope->detach();
      }
    }
  }

}
