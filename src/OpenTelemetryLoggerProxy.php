<?php

namespace Drupal\opentelemetry;

use Drupal\Core\Logger\RfcLoggerTrait;
use Psr\Log\LoggerInterface;

// A workaround to make the logger compatible with Drupal 9.x and 10.x together.
if (version_compare(\Drupal::VERSION, '10.0.0') <= 0) {
  require_once __DIR__ . '/OpenTelemetryLoggerProxyTrait.D9.inc';
}
else {
  require_once __DIR__ . '/OpenTelemetryLoggerProxyTrait.D10.inc';
}

/**
 * A custom logger shim to catch an suppress repeating errors.
 */
class OpenTelemetryLoggerProxy implements LoggerInterface {
  use RfcLoggerTrait;
  use OpenTelemetryLoggerProxyTrait;

  /**
   * Counters for repeatable errors.
   *
   * @var array
   */
  protected array $repeatableErrorsCounts = [];

  /**
   * {@inheritdoc}
   */
  public function __construct(
    protected ?LoggerInterface $systemLogger = NULL,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function doLog($level, $message, array $context = []): void {
    $step = 50;
    // When the collector is not responding, we can receive dozens of messages
    // with 'Export failure' message, that will flood our error log.
    // To workaround just log only the first error and step groups.
    // @todo Try to catch other repeating messages.
    if ($message == 'Export failure') {
      $this->repeatableErrorsCounts[$message] ??= 0;
      $count = ++$this->repeatableErrorsCounts[$message];
      if ($count == 1 || $count % $step == 0) {
        if ($count > 1) {
          $message .= " (Repeated $count times)";
        }
        $this->systemLogger->log($level, $message, $context);
      }
    }
  }

}
