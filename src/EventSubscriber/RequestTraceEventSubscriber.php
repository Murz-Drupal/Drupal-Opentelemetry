<?php

namespace Drupal\opentelemetry\EventSubscriber;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\opentelemetry\OpentelemetryServiceInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\Propagation\PropagationSetterInterface;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\Contrib\Propagation\TraceResponse\TraceResponsePropagator;
use OpenTelemetry\SemConv\TraceAttributes;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Subscribes to kernel events to initialize the root span.
 */
class RequestTraceEventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The request span.
   *
   * @var \OpenTelemetry\API\Trace\SpanInterface
   */
  protected SpanInterface $requestSpan;

  /**
   * The context scope.
   *
   * @var \OpenTelemetry\Context\ScopeInterface
   */
  protected ScopeInterface $scope;

  /**
   * A flag to inidicate initialization of the span.
   *
   * @var bool
   */
  protected bool $isSpanInitialized = FALSE;

  /**
   * A flag to inidicate the debug mode.
   *
   * @var bool
   */
  protected bool $isDebug = FALSE;

  /**
   * Constructs the OpenTelemetry Event Subscriber.
   *
   * @param \Drupal\opentelemetry\OpentelemetryServiceInterface $openTelemetry
   *   An OpenTelemetry service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   A Drupal Messenger.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   A logger.
   */
  public function __construct(
    protected OpentelemetryServiceInterface $openTelemetry,
    protected MessengerInterface $messenger,
    protected LoggerChannelInterface $logger,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      // Set the priority to 1000 to run before other KernelEvents::REQUEST
      // implementations.
      KernelEvents::REQUEST => ['onRequest', 1000],
      KernelEvents::VIEW => ['onView', 0],
      KernelEvents::RESPONSE => ['onResponse', 0],
      KernelEvents::FINISH_REQUEST => ['onFinishRequest', 0],
    ];
  }

  /**
   * Initializes the root span and the Request span.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The kernel request event.
   */
  public function onRequest(RequestEvent $event) {
    $this->createRequestSpan($event->getRequest(), 'request');
  }

  /**
   * Initializes the root span and the Request span on View event.
   *
   * The VIEW event occurs when the return value of a controller
   * is not a Response instance.
   *
   * @param \Symfony\Component\HttpKernel\Event\ViewEvent $event
   *   The kernel view event.
   */
  public function onView(ViewEvent $event): void {
    $this->createRequestSpan($event->getRequest(), 'view');
  }

  /**
   * Adds a Response and HTTP status code.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   A ResponseEvent.
   */
  public function onResponse(ResponseEvent $event): void {
    if (!$this->isSpanInitialized) {
      return;
    }
    if (!$event->isMainRequest()) {
      return;
    }
    $this->requestSpan->setAttribute(TraceAttributes::HTTP_RESPONSE_STATUS_CODE, $event->getResponse()->getStatusCode());
    $scope = Context::storage()->scope();
    if ($scope) {
      $response = $event->getResponse();
      // Create a PropagationSetterInterface that knows how to inject response
      // headers.
      $propagationSetter = new class implements PropagationSetterInterface {

        /**
         * {@inheritdoc}
         */
        public function set(&$carrier, string $key, string $value): void {
          $carrier->headers->set($key, $value);
        }

      };
      $propagator = new TraceResponsePropagator();
      $propagator->inject($response, $propagationSetter, $scope->context());
    }
  }

  /**
   * Adds a FinishRequest and ends the request span.
   *
   * @param \Symfony\Component\HttpKernel\Event\FinishRequestEvent $event
   *   A FinishRequestEvent.
   */
  public function onFinishRequest(FinishRequestEvent $event): void {
    if (!$this->isSpanInitialized) {
      return;
    }
    $this->requestSpan->end();
  }

  /**
   * Creates the request span with a label.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A request.
   * @param string $label
   *   The label to add to the span name.
   */
  protected function createRequestSpan(Request $request, string $label) {
    if (!$tracer = $this->openTelemetry->getTracer()) {
      if ($this->openTelemetry->isDebugMode()) {
        // Calling statically to not add the dependency for non debug mode.
        // @codingStandardsIgnoreStart
        // @phpstan-ignore-next-line
        \Drupal::messenger()->addError($this->t('RequestTrace plugin: Error with tracer initialization.'));
        // @codingStandardsIgnoreEnd
      }
      return;
    }
    $spanName = $this->openTelemetry->createRequestSpanName($request, $label);
    $this->requestSpan = $tracer->spanBuilder($spanName)->setSpanKind(SpanKind::KIND_CLIENT)->startSpan();
    $attributes = $this->openTelemetry->getTraceAttributesForRequestSpan($request);
    $this->requestSpan->setAttributes($attributes);
    if ($this->openTelemetry->isDebugMode()) {
      // Calling statically to not add the dependency for non debug mode.
      // @codingStandardsIgnoreStart
      // @phpstan-ignore-next-line
      \Drupal::messenger()->addStatus(
        $this->t('@name started. The root trace id: <code>@trace_id</code>, span id: <code>@span_id</code>.', [
          '@name' => 'RequestTrace plugin',
          '@trace_id' => $this->openTelemetry->getTraceId(),
          '@span_id' => $this->requestSpan->getContext()->getSpanId(),
        ])
      );
      // @codingStandardsIgnoreEnd
    }
    $this->isSpanInitialized = TRUE;
  }

}
