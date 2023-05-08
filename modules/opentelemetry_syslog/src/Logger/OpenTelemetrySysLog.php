<?php

namespace Drupal\opentelemetry_syslog\Logger;

use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\syslog\Logger\SysLog;

/**
 * Redirects logging messages to syslog with trace_id value.
 */
class OpenTelemetrySysLog extends SysLog {
  use RfcLoggerTrait;

  /**
   * {@inheritdoc}
   */
  protected function syslogWrapper($level, $entry) {
    // We can't use dependency injection in the class because of circular
    // dependency, so using a static call to the service.
    try {
      $tracer ??= \Drupal::service('opentelemetry.tracer');
      $traceId = $tracer->getTraceId();
      $entry = strtr($entry, [
        '!trace_id' => $traceId,
      ]);
    }
    catch (\Exception $e) {
    }

    parent::syslogWrapper($level, $entry);
  }

}
