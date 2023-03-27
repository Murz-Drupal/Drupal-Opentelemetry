<?php

namespace Drupal\opentelemetry;

use OpenTelemetry\API\Trace\TracerInterface;

/**
 * Interface for an OpenTelemetry service.
 *
 * @package Drupal\opentelemetry
 */
interface OpenTelemetryServiceInterface {

  /**
   * Returns the tracer instance.
   *
   * @return \OpenTelemetry\API\Trace\TracerInterface
   *   A tracer object.
   */
  public function getTracer(): TracerInterface;

  /**
   * Returns root span name.
   *
   * @return string
   *   A name for the root span.
   */
  public function getRootSpanName(): string;

}
