<?php

namespace Drupal\opentelemetry;

/**
 * A shim to make a class compatible with Drupal 9.x Psr\Log\LoggerInterface.
 */
trait OpentelemetryLoggerProxyTrait {

  /**
   * A proxy function to make the class compatible with Drupal 9.x and 10.x.
   *
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []): void {
    $this->doLog($level, $message, $context);
  }

  /**
   * Actual doing the log.
   *
   * @param mixed $level
   *   A log level.
   * @param mixed $message
   *   A log message.
   * @param array $context
   *   An array with context.
   */
  abstract public function doLog($level, $message, array $context = []);

}
