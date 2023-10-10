<?php

namespace Drupal\opentelemetry\EventSubscriber;

use Drupal\opentelemetry\OpentelemetryService;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Trace\Span;
use Symfony\Component\DependencyInjection\ContainerInterface;
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
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   A Symfony container.
   */
  public function __construct(
    protected ContainerInterface $container,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::EXCEPTION => ['onException', 100],
    ];
  }

  /**
   * Creates a span and event on Exception.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The kernel exception event.
   */
  public function onException(ExceptionEvent $event) {
    if (!$this->openTelemetry = $this->getOpentelemetryService()) {
      return;
    }
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

  /**
   * Gets the opentelemetry service dynamically.
   *
   * @return \Drupal\opentelemetry\OpentelemetryService|null
   *   The opentelemetry service instance, or null if not initialized yet.
   */
  protected function getOpentelemetryService(): ?OpentelemetryService {
    if ($this->container->has('opentelemetry')) {
      return $this->container->get('opentelemetry');
    }
    return NULL;
  }

}
