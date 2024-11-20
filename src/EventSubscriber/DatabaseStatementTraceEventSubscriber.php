<?php

namespace Drupal\opentelemetry\EventSubscriber;

use Drupal\Core\Database\Event\DatabaseEvent;
use Drupal\Core\Database\Event\StatementExecutionEndEvent;
use Drupal\Core\Database\Event\StatementExecutionFailureEvent;
use Drupal\Core\Database\Event\StatementExecutionStartEvent;
use Drupal\opentelemetry\Exception\MissingClassDependencyException;
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
    if (!interface_exists(TraceAttributes::class)) {
      throw new MissingClassDependencyException(TraceAttributes::class, 'open-telemetry/sem-conv');
    }
    if (!class_exists(StatementExecutionStartEvent::class)) {
      return [];
    }
    return [
        // Set the priority to 1000 to run before other KernelEvents::REQUEST.
      KernelEvents::REQUEST => ['onKernelRequest', 1000],
      StatementExecutionStartEvent::class => 'onStatementExecutionStart',
      StatementExecutionEndEvent::class => 'onStatementExecutionEnd',
      StatementExecutionFailureEvent::class => 'onStatementExecutionFailure',
    ];
  }

  /**
   * Starts the database query span.
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

    $spanBuilder = $tracer->spanBuilder('query-' . $queryCounter)->setSpanKind(SpanKind::KIND_CLIENT);
    $this->span = $spanBuilder->startSpan();
    $this->span->setAttribute(TraceAttributes::DB_SYSTEM, $driver);
    $this->span->setAttribute(TraceAttributes::DB_NAMESPACE, $event->target);
    $this->span->setAttribute(TraceAttributes::DB_QUERY_TEXT, $event->queryString);
  }

  /**
   * Ends the database query span.
   *
   * @param \Drupal\Core\Database\Event\StatementExecutionEndEvent $event
   *   The database event.
   */
  public function onStatementExecutionEnd(StatementExecutionEndEvent $event): void {
    if (
      !$this->openTelemetry->getTracer()
      || !isset($this->span)
    ) {
      return;
    }
    $this->span->end();
  }

  /**
   * Produces an event if the database query fails.
   *
   * @param \Drupal\Core\Database\Event\StatementExecutionFailureEvent $event
   *   The StatementExecutionFailureEvent event.
   */
  public function onStatementExecutionFailure(StatementExecutionFailureEvent $event): void {
    if (
      !$this->openTelemetry->getTracer()
      || !isset($this->span)
    ) {
      return;
    }
    $exception = new \Exception($event->exceptionMessage, $event->exceptionCode);
    $this->span->recordException($exception);
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
