<?php

namespace Drupal\opentelemetry\Plugin\opentelemetry\OpenTelemetryTrace;

use Drupal\Core\Plugin\PluginBase;

/**
 * The base plugin for OpenTelemetry Span plugins.
 */
abstract class OpentelemetryTraceBase extends PluginBase {

  /**
   * Checks if plugin is available by checking all requirements.
   *
   * @return bool
   *   True if all requirements are present.
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

  /**
   * Checks conditions and reports if the plugin should be enabled by default.
   *
   * @return bool
   *   TRUE if it should be enabled by default.
   */
  public function enabledByDefault(): bool {
    return FALSE;
  }

}
