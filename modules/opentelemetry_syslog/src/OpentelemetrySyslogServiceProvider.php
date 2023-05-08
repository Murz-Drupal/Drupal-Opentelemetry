<?php

namespace Drupal\opentelemetry_syslog;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Replaces the syslog logger to opentelemetry_syslog.
 */
class OpentelemetrySyslogServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('logger.syslog')) {
      $definition = $container->getDefinition('logger.syslog');
      $definition->setClass('Drupal\opentelemetry_syslog\Logger\OpenTelemetrySysLog');
    }
  }

}
