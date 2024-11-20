<?php

namespace Drupal\opentelemetry;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextStorage;

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

    // Force disable the FiberBoundContextStorage because of the conflict
    // with Drupal Renderer service.
    // @see https://www.drupal.org/project/opentelemetry/issues/3488173
    // @todo Make a proper fix to work well with the FiberBoundContextStorage.
    $contextStorage = new ContextStorage();
    Context::setStorage($contextStorage);

    parent::__construct($subdir, $namespaces, $module_handler, NULL, $plugin_definition_annotation_name);

    $this->alterInfo('opentelemetry_span_plugins');

    $this->setCacheBackend($cache_backend, 'opentelemetry_span_plugins');
  }

}
