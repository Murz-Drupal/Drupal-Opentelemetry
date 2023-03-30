<?php

namespace Drupal\opentelemetry\EventSubscriber;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\opentelemetry\OpenTelemetryTracerServiceInterface;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\Context\ScopeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Subscribes to kernel events to initialize the root span.
 */
class RequestTraceEventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Root span storage.
   *
   * @var \OpenTelemetry\API\Trace\SpanInterface
   */
  protected SpanInterface $rootSpan;
  protected SpanInterface $requestSpan;
  protected ScopeInterface $scope;

  /**
   * Constructs the OpenTelemetry Event Subscriber.
   *
   * @param \Drupal\opentelemetry\OpenTelemetryTracerServiceInterface $openTelemetryTracer
   *   An OpenTelemetry service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   A Drupal Messenger.
   */
  public function __construct(
    protected OpenTelemetryTracerServiceInterface $openTelemetryTracer,
    protected MessengerInterface $messenger,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => ['onRequest', 0],
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
    if (!$tracer = $this->openTelemetryTracer->getTracer()) {
      return;
    }
    $request = $event->getRequest();

    $parent = TraceContextPropagator::getInstance()->extract($request->headers->all());
    $this->rootSpan = $tracer->spanBuilder($this->openTelemetryTracer->getRootSpanName())
      ->setStartTimestamp((int) ($request->server->get('REQUEST_TIME_FLOAT') * 1e9))
      ->setParent($parent)
      ->startSpan();
    if ($this->openTelemetryTracer->isDebugMode()) {
      \Drupal::messenger()->addStatus(
        $this->t('RequestTrace plugin started. The root trace id: <code>@trace_id</code>, span id: <code>@span_id</code>.', [
          '@trace_id' => $this->rootSpan->getContext()->getTraceId(),
          '@span_id' => $this->rootSpan->getContext()->getSpanId(),
        ])
      );
    }
    $this->scope = $this->rootSpan->activate();

    $requestSpanName = $request->getMethod() . ' ' . $request->getRequestUri();
    $this->requestSpan = $tracer->spanBuilder($requestSpanName)->startSpan();
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
    if (!$this->openTelemetryTracer->getTracer()) {
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
    if (!$this->openTelemetryTracer->getTracer()) {
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
    if (!$this->openTelemetryTracer->getTracer()) {
      return;
    }
    if (isset($this->rootSpan)) {
      $this->rootSpan->end();
    }
    if (isset($this->scope)) {
      $this->scope->detach();
    }
  }

}
