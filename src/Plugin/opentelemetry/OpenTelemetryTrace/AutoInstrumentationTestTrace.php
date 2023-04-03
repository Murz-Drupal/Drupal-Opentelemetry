<?php

namespace Drupal\opentelemetry\Plugin\opentelemetry\OpenTelemetryTrace;

/**
 * The Auto-instrumentation Trace.
 *
 * @OpenTelemetryTrace(
 *   id = "auto_instrumentation_test",
 *   label = @Translation("Auto-instrumentation Test"),
 *   description = @Translation("Auto-instrumentation test plugin."),
 * )
 */
class AutoInstrumentationTestTrace extends OpenTelemetryTraceBase {

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    return extension_loaded('otel_instrumentation');
  }

  /**
   * {@inheritdoc}
   */
  public function getUnavailableReason(): ?string {
    if ($this->isAvailable()) {
      return NULL;
    }
    return 'The `otel_instrumentation` PHP extension should be loaded to make auto-instrumentation work. More info here: https://github.com/open-telemetry/opentelemetry-php-instrumentation';
  }

}
