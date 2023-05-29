<?php

namespace Drupal\opentelemetry\EventSubscriber;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\opentelemetry\OpentelemetryTracerServiceInterface;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\Context\ScopeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Subscribes to kernel events to initialize the root span.
 */
class RequestTraceEventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The root span.
   *
   * @var \OpenTelemetry\API\Trace\SpanInterface
   */
  protected SpanInterface $rootSpan;

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
   * @param \Drupal\opentelemetry\OpentelemetryTracerServiceInterface $openTelemetryTracer
   *   An OpenTelemetry service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   A Drupal Messenger.
   */
  public function __construct(
    protected OpentelemetryTracerServiceInterface $openTelemetryTracer,
    protected MessengerInterface $messenger,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => ['onRequest', 0],
      KernelEvents::VIEW => ['onView', 0],
      KernelEvents::RESPONSE => ['onResponse', 0],
      KernelEvents::FINISH_REQUEST => ['onFinishRequest', 0],
      KernelEvents::TERMINATE => ['onTerminate', 0],
    ];
  }

  /**
   * Initializes the root span and the Request span.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The kernel request event.
   */
  public function onRequest(RequestEvent $event) {
    $this->isDebug = $this->openTelemetryTracer->isDebugMode();
    if (!$tracer = $this->openTelemetryTracer->getTracer()) {
      if ($this->isDebug) {
        \Drupal::messenger()->addError('RequestTrace plugin: Error with tracer initialization.');
      }
      return;
    }
    $request = $event->getRequest();
    $spanName = $this->createSpanName($request->getMethod(), $request->getRequestUri(), 'request');

    $this->activateRootSpan($request);

    $this->requestSpan = $tracer->spanBuilder($spanName)->startSpan();
    $this->requestSpan->setAttributes(
      [
        'http.method' => $request->getMethod(),
        'http.flavor' => $request->getProtocolVersion(),
        'http.url' => $request->getSchemeAndHttpHost() . $request->getRequestUri(),
      ]
    );
    $this->requestSpan->addEvent('Request');
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
    $this->isDebug = $this->openTelemetryTracer->isDebugMode();
    if (!$tracer = $this->openTelemetryTracer->getTracer()) {
      if ($this->isDebug) {
        \Drupal::messenger()->addError('RequestTrace plugin: Error with tracer initialization.');
      }
      return;
    }
    $request = $event->getRequest();
    $spanName = $this->createSpanName($request->getMethod(), $request->getRequestUri(), 'view');

    $this->activateRootSpan($request);

    $this->requestSpan = $tracer->spanBuilder($spanName)->startSpan();
    $this->requestSpan->setAttributes(
      [
        'http.method' => $request->getMethod(),
        'http.flavor' => $request->getProtocolVersion(),
        'http.url' => $request->getSchemeAndHttpHost() . $request->getRequestUri(),
      ]
    );
    $this->requestSpan->addEvent('Request');
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
    $this->requestSpan->setAttribute('http.status_code', $event->getResponse()->getStatusCode());
    $this->requestSpan->addEvent('Response');
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
    $this->requestSpan->addEvent('FinishRequest');
    $this->requestSpan->end();
  }

  /**
   * Ends the root span and detaches the scope.
   *
   * @param \Symfony\Component\HttpKernel\Event\TerminateEvent $event
   *   A TerminateEvent.
   */
  public function onTerminate(TerminateEvent $event) {
    if (!$this->isSpanInitialized) {
      return;
    }
    if (isset($this->rootSpan)) {
      $this->rootSpan->end();
    }
    if (isset($this->scope)) {
      $this->scope->detach();
    }
  }

  /**
   * Activates the root span.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A request.
   */
  private function activateRootSpan(Request $request) {
    if ($this->isSpanInitialized) {
      return;
    }
    if (!$tracer = $this->openTelemetryTracer->getTracer()) {
      if ($this->isDebug) {
        \Drupal::messenger()->addError('RequestTrace plugin: Error with tracer initialization.');
      }
      return;
    }
    $spanName = $this->createSpanName($request->getMethod(), $request->getRequestUri());
    $parent = TraceContextPropagator::getInstance()->extract($request->headers->all());

    $this->rootSpan = $tracer->spanBuilder($spanName)
      ->setStartTimestamp((int) ($request->server->get('REQUEST_TIME_FLOAT') * 1e9))
      ->setParent($parent)
      ->startSpan();

    $traceId = $this->rootSpan->getContext()->getTraceId();

    if ($this->isDebug) {
      \Drupal::messenger()->addStatus(
        $this->t('RequestTrace plugin started. The root trace id: <code>@trace_id</code>, span id: <code>@span_id</code>.', [
          '@trace_id' => $traceId,
          '@span_id' => $this->rootSpan->getContext()->getSpanId(),
        ])
      );
    }

    $this->openTelemetryTracer->setTraceId($traceId);

    $this->scope = $this->rootSpan->activate();
    $this->isSpanInitialized = TRUE;
  }

  /**
   * Creates a span name for request from components.
   *
   * Formats to string: "$method $uri [($type)]".
   *
   * @param string $method
   *   An HTTP method name: GET, PUT, POST, etc.
   * @param string $uri
   *   An URI.
   * @param string $type
   *   A type name, that will added to the end of the name.
   *
   * @return string
   *   The combined span name.
   */
  protected function createSpanName(string $method, string $uri, string $type = NULL): string {
    $name = $method . ' ' . $uri;
    if ($type) {
      $name .= " ($type)";
    }
    return $name;
  }

}
