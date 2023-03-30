<?php

namespace Drupal\opentelemetry;

use OpenTelemetry\API\Trace\TracerInterface;

/**
 * Interface for an OpenTelemetry service.
 *
 * @package Drupal\opentelemetry
 */
interface OpenTelemetryTracerServiceInterface {

  /**
   * Returns the tracer instance.
   *
   * @return \OpenTelemetry\API\Trace\TracerInterface
   *   An initialized tracer to use for spans.
   */
  public function getTracer(): ?TracerInterface;

  /**
   * Returns a span name to use for the root span.
   *
   * @return string
   *   The name for the root span.
   */
  public function getRootSpanName(): string;

  /**
   * Returns the status of a OpenTelemetryTrace plugin.
   *
   * @param string $pluginId
   *   The plugin id.
   *
   * @return bool|null
   *   The plugin status:
   *   - NULL if missing in settings.
   *   - TRUE if enabled.
   *   - FALSE if disabled.
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
