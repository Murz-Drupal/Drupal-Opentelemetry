<?php

namespace Drupal\opentelemetry\EventSubscriber;

use Drupal\opentelemetry\OpentelemetryServiceInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Trace\Span;
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
   * @param \Drupal\opentelemetry\OpentelemetryServiceInterface $openTelemetry
   *   An OpenTelemetry service.
   */
  public function __construct(
    protected OpentelemetryServiceInterface $openTelemetry
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
    $exception = $event->getThrowable();
    if (!$tracer = $this->openTelemetry->getTracer()) {
      return;
    }
    $endSpan = FALSE;
    $tracer = $this->openTelemetry->getTracer();
    if (!$span = Span::getCurrent()) {
      $span = $tracer->spanBuilder('Exception')->startSpan();
      $endSpan = TRUE;
    }
    $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
    $span->recordException($exception);
    if ($endSpan) {
      $span->end();
    }
  }

}
