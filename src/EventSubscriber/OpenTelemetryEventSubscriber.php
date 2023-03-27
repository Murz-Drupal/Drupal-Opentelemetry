<?php

namespace Drupal\opentelemetry\EventSubscriber;

use Drupal\opentelemetry\OpenTelemetryServiceInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Subscribes to kernel events to initialize the root span.
 */
class OpenTelemetryEventSubscriber implements EventSubscriberInterface {

  /**
   * Constructs the OpenTelemetry Event Subscriber.
   *
   * @param \Drupal\opentelemetry\OpenTelemetryServiceInterface $openTelemetry
   *   An OpenTelemetry service.
   */
  public function __construct(
    protected OpenTelemetryServiceInterface $openTelemetry
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['onRequest', 0];
    $events[KernelEvents::FINISH_REQUEST][] = ['onFinishRequest', 0];
    return $events;
  }

  /**
   * Initializes the root span.
   *
   * @param Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The kernel request event.
   */
  public function onRequest(RequestEvent $event) {
    $tracer = $this->openTelemetry->getTracer();
    $this->rootSpan = $tracer->spanBuilder($this->openTelemetry->getRootSpanName())->startSpan();
    $this->scope = $this->rootSpan->activate();
  }

  /**
   * Ends the root span.
   *
   * @param \Symfony\Component\HttpKernel\Event\FinishRequestEvent $event
   *   The kernel finish request event.
   */
  public function onFinishRequest(FinishRequestEvent $event) {
    $this->rootSpan->end();
    $this->scope->detach();
  }

}
