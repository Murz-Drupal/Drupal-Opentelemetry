<?php

namespace Drupal\opentelemetry;

use OpenTelemetry\API\Trace\TracerInterface;

/**
 * Provides a common method for getting a tracer.
 */
trait TracerProviderTrait {

  /**
   * Get an instance of the tracer.
   *
   * @return \OpenTelemetry\API\Trace\TracerInterface
   *   The tracer instance.
   */
  protected function getTracer(): TracerInterface {
    return $this->tracerProvider->getTracer($this->tracerName);
  }

}
