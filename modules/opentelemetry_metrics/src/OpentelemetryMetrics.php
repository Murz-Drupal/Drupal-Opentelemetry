<?php

namespace Drupal\opentelemetry_metrics;

use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Metrics\MetricReaderInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Provides API to push custom metrics to the OpenTelemetry.
 */
class OpentelemetryMetrics implements EventSubscriberInterface {

  /**
   * The meter provider.
   *
   * @var \OpenTelemetry\SDK\Metrics\MeterProvider
   */
  protected $meterProvider;

  /**
   * Constructs a new OpentelemetryMetrics object.
   *
   * @param \OpenTelemetry\SDK\Metrics\MetricReaderInterface $metricReader
   *   The metric reader.
   * @param \OpenTelemetry\SDK\Resource\ResourceInfo $resource
   *   The resource info.
   */
  public function __construct(
    protected MetricReaderInterface $metricReader,
    protected ResourceInfo $resource,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::TERMINATE => ['onTerminate', -100],
    ];
  }

  /**
   * Ends the root span and detaches the scope.
   *
   * @param \Symfony\Component\HttpKernel\Event\TerminateEvent $event
   *   A TerminateEvent.
   */
  public function onTerminate(TerminateEvent $event) {
    $this->metricReader->shutdown();
  }

  /**
   * Gets a meter by name.
   *
   * @param string $name
   *   The name of the meter.
   *
   * @return \OpenTelemetry\API\Metrics\MeterInterface
   *   The meter.
   */
  public function getMeter(string $name): MeterInterface {
    $metric = $this->getMeterProvider()->getMeter($name);
    return $metric;
  }

  /**
   * Gets the meter provider.
   *
   * @return \OpenTelemetry\SDK\Metrics\MeterProvider
   *   The meter provider.
   */
  private function getMeterProvider() {
    if (!$this->meterProvider) {
      $this->meterProvider = MeterProvider::builder()
        ->setResource($this->resource)
        ->addReader($this->metricReader)
        ->build();
    }
    return $this->meterProvider;
  }

  /**
   * Sends the collected metrics to the OpenTelemetry endpoint.
   */
  public function collect() {
    $this->metricReader->collect();
  }

}
