<?php

namespace Drupal\opentelemetry\Plugin\opentelemetry\OpenTelemetryTrace;

use Drupal\Core\Plugin\PluginBase;

/**
 * The base plugin for OpenTelemetry Span plugins.
 */
abstract class OpenTelemetryTraceBase extends PluginBase {

  /**
   * Checks is plugin available by checking all requirements.
   *
   * @return bool
   *   TRUE if all requirements are present.
   */
  public function isAvailable(): bool {
    return TRUE;
  }

  /**
   * Returns a string with description of the unvailability reason.
   */
  public function getUnavailableReason(): ?string {
    return NULL;
  }

}
