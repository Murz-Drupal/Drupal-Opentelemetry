<?php

namespace Drupal\opentelemetry_trace_db\EventSubscriber;

use Drupal\Core\Database\Event\DatabaseEvent;
use Drupal\Core\Database\Event\StatementExecutionEndEvent;
use Drupal\Core\Database\Event\StatementExecutionStartEvent;
use Drupal\opentelemetry\OpentelemetryServiceInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SemConv\TraceAttributes;
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
   * @var \OpenTelemetry\API\Trace\SpanInterface
   */
  protected SpanInterface $queryCounter;

  /**
   * Constructs the Database Event Subscriber.
   *
   * @param \Drupal\opentelemetry\OpentelemetryServiceInterface $openTelemetry
   *   An OpenTelemetry service.
   */
  public function __construct(
    protected OpentelemetryServiceInterface $openTelemetry,
  ) {
    // This produces a ServiceCircularReferenceException exception.
    // @todo Investigate this.
    // $this->queryCounter = 0;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    if (!class_exists(StatementExecutionStartEvent::class)) {
      return [];
    }
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
    if (!$tracer = $this->openTelemetry->getTracer()) {
      return;
    }
    // @todo Rework this to $this->queryCounter.
    static $queryCounter;
    $queryCounter ??= 0;
    $queryCounter++;

    // @todo Rework this to get driver name properly.
    static $driver;
    // @phpstan-ignore-next-line
    $driver ??= \Drupal::database()->driver();

    $tracer = $this->openTelemetry->getTracer();
    $this->span = $tracer->spanBuilder('query-' . $queryCounter)->setSpanKind(SpanKind::KIND_CLIENT)->startSpan();

    $this->span->setAttribute(TraceAttributes::DB_SYSTEM, $driver);
    $this->span->setAttribute(TraceAttributes::DB_NAME, $event->target);
    $this->span->setAttribute(TraceAttributes::DB_STATEMENT, $event->queryString);
  }

  /**
   * Subscribes to a statement execution finished event.
   *
   * @param \Drupal\Core\Database\Event\StatementExecutionEndEvent $event
   *   The database event.
   */
  public function onStatementExecutionEnd(StatementExecutionEndEvent $event): void {
    if (!$this->openTelemetry->getTracer()) {
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
      !$this->openTelemetry->getTracer()
      || !$this->openTelemetry->isPluginEnabled('database_statement')
      || !class_exists(DatabaseEvent::class)
    ) {
      return;
    }
    // @phpstan-ignore-next-line
    \Drupal::database()->enableEvents(
      [
        StatementExecutionStartEvent::class,
        StatementExecutionEndEvent::class,
      ]
    );
  }

}
