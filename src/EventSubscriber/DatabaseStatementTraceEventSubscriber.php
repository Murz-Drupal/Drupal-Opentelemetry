<?php

namespace Drupal\opentelemetry\EventSubscriber;

use Drupal\Core\Database\Event\DatabaseEvent;
use Drupal\Core\Database\Event\StatementExecutionEndEvent;
use Drupal\Core\Database\Event\StatementExecutionStartEvent;
use Drupal\opentelemetry\OpenTelemetryTracerServiceInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Create spans for Database Statement execution Core events.
 */
class DatabaseStatementTraceEventSubscriber implements EventSubscriberInterface {

  /**
   * A span storage.
   *
   * @var \OpenTelemetry\API\Trace\SpanInterface
   */
  protected SpanInterface $span;

  /**
   * The query counter.
   *
   * @var int;
   */
  protected SpanInterface $queryCounter;

  /**
   * Constructs the Database Event Subscriber.
   *
   * @param \Drupal\opentelemetry\OpenTelemetryTracerServiceInterface $openTelemetryTracer
   *   An OpenTelemetry service.
   */
  public function __construct(
    protected OpenTelemetryTracerServiceInterface $openTelemetryTracer,
  ) {
    // This produces a ServiceCircularReferenceException exception.
    // @todo Investigate this.
    // $this->queryCounter = 0;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {

    return [
      KernelEvents::REQUEST => 'onKernelRequest',
      StatementExecutionStartEvent::class => 'onStatementExecutionStart',
      StatementExecutionEndEvent::class => 'onStatementExecutionEnd',
    ];
  }

  /**
   * Subscribes to a statement execution started event.
   *
   * @param \Drupal\Core\Database\Event\StatementExecutionStartEvent $event
   *   The database event.
   */
  public function onStatementExecutionStart(StatementExecutionStartEvent $event): void {
    if (!$tracer = $this->openTelemetryTracer->getTracer()) {
      return;
    }
    // @todo Rework this to $this->queryCounter.
    static $queryCounter;
    $queryCounter ??= 0;
    $queryCounter++;

    // @todo Rework this to get driver name properly.
    static $driver;
    $driver ??= \Drupal::database()->driver();

    $tracer = $this->openTelemetryTracer->getTracer();
    $this->span = $tracer->spanBuilder('query-' . $queryCounter)->startSpan();

    $this->span->setAttribute('db.system', $driver);
    $this->span->setAttribute('db.name', $event->target);
    $this->span->setAttribute('db.statement', $event->queryString);
  }

  /**
   * Subscribes to a statement execution finished event.
   *
   * @param \Drupal\Core\Database\Event\StatementExecutionEndEvent $event
   *   The database event.
   */
  public function onStatementExecutionEnd(StatementExecutionEndEvent $event): void {
    if (!$this->openTelemetryTracer->getTracer()) {
      return;
    }
    $this->span->end();
  }

  /**
   * Enables Statement Execution events in Core.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The kernel request event.
   */
  public function onKernelRequest(RequestEvent $event) {
    if (
      !$this->openTelemetryTracer->getTracer()
      || !$this->openTelemetryTracer->isPluginEnabled('database_statement')
      || !class_exists(DatabaseEvent::class)
    ) {
      return;
    }
    \Drupal::database()->enableEvents(
      [
        StatementExecutionStartEvent::class,
        StatementExecutionEndEvent::class,
      ]
    );

  }

}
