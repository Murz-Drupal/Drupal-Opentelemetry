<?php

namespace Drupal\opentelemetry\EventSubscriber;

use Drupal\opentelemetry\OpenTelemetryTracerServiceInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Subscribes to kernel execptions events and generates a Span Event.
 */
class ExceptionTraceEventSubscriber implements EventSubscriberInterface {

  /**
   * Constructs the OpenTelemetry Event Subscriber.
   *
   * @param \Drupal\opentelemetry\OpenTelemetryTracerServiceInterface $openTelemetryTracer
   *   An OpenTelemetry service.
   */
  public function __construct(
    protected OpenTelemetryTracerServiceInterface $openTelemetryTracer
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::EXCEPTION => 'onException',
    ];
  }

  /**
   * Creates a span and event on Exception.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The kernel exception event.
   */
  public function onException(ExceptionEvent $event) {
    if (!$tracer = $this->openTelemetryTracer->getTracer()) {
      return;
    }
    $exception = $event->getThrowable();
    $tracer = $this->openTelemetryTracer->getTracer();
    // @todo Find how to add an event to current span, without a new one.
    $span = $tracer->spanBuilder('exception')->startSpan();
    $span->addEvent(
      'Exception', [
        'code' => $exception->getCode(),
        'message' => $exception->getMessage(),
        'trace' => $exception->getTraceAsString(),
      ]
    );
    $span->end();
  }

}
