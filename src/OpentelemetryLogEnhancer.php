<?php

namespace Drupal\opentelemetry;

use Drupal\Core\Logger\RfcLoggerTrait;
use Psr\Log\LoggerInterface;

/**
 * A custom logger shim to catch an suppress repeating errors.
 */
class OpentelemetryLogEnhancer implements LoggerInterface {
  use RfcLoggerTrait;
  use OpentelemetryLogEnhancerTrait;

  /**
   * A proxy function to make the trait reusable for Drupal 9 and 10+ together.
   *
   * {@inheritdoc}
   */
  public function log($level, string|\Stringable $message, array $context = []): void {
    $this->doLog($level, $message, $context);
  }

}
