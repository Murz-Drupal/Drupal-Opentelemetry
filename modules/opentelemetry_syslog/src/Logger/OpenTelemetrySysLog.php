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
      $tracer ??= \Drupal::service('opentelemetry');
      // This returns empty trace id for 404 pages, because it's called
      // before the KernelEvents::REQUEST happens.
      // @todo Invent a workaround for this problem.
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
