<?php

namespace Drupal\opentelemetry;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * A Plugin to manage OpenTelemetry Span plugins.
 */
class OpentelemetryTraceManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    $subdir = 'Plugin/opentelemetry/OpenTelemetryTrace';

    // The name of the annotation class that contains the plugin definition.
    $plugin_definition_annotation_name = 'Drupal\opentelemetry\Annotation\OpenTelemetryTrace';

    parent::__construct($subdir, $namespaces, $module_handler, NULL, $plugin_definition_annotation_name);

    $this->alterInfo('opentelemetry_span_plugins');

    $this->setCacheBackend($cache_backend, 'opentelemetry_span_plugins');
  }

}
