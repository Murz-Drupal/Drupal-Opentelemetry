<?php

namespace Drupal\opentelemetry;

use OpenTelemetry\API\Trace\TracerInterface;

/**
 * Interface for an OpenTelemetry service.
 *
 * @package Drupal\opentelemetry
 */
interface OpentelemetryServiceInterface {

  /**
   * Returns the tracer instance.
   *
   * @return \OpenTelemetry\API\Trace\TracerInterface
   *   An initialized tracer to use for spans.
   */
  public function getTracer(): ?TracerInterface;

  /**
   * Returns the status of a OpenTelemetryTrace plugin.
   *
   * @param string $pluginId
   *   The plugin id.
   *
   * @return bool|null
   *   The plugin status:
   *   - Null if missing in settings.
   *   - True if enabled.
   *   - False if disabled.
   */
  public function isPluginEnabled(string $pluginId): ?bool;

  /**
   * Returns the debug mode status.
   *
   * @return bool
   *   TRUE if enabled, FALSE if disabled.
   */
  public function isDebugMode(): bool;

}
