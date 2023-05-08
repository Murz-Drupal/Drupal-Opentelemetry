<?php

namespace Drupal\opentelemetry\Plugin\opentelemetry\OpenTelemetryTrace;

/**
 * The Request span.
 *
 * @OpenTelemetryTrace(
 *   id = "request",
 *   label = @Translation("Request"),
 *   description = @Translation("Traces a Drupal/Symfony request from the first Request event to Terminate event."),
 * )
 */
class RequestTrace extends OpentelemetryTraceBase {

  /**
   * {@inheritdoc}
   */
  public function enabledByDefault(): bool {
    return TRUE;
  }

}
