<?php

namespace Drupal\opentelemetry\Plugin\opentelemetry\OpenTelemetryTrace;

/**
 * The Request span.
 *
 * @OpenTelemetryTrace(
 *   id = "exception",
 *   label = @Translation("Exception"),
 *   description = @Translation("Traces all exceptions."),
 * )
 */
class ExceptionTrace extends OpentelemetryTraceBase {

  /**
   * {@inheritdoc}
   */
  public function enabledByDefault(): bool {
    return TRUE;
  }

}
