<?php

namespace Drupal\opentelemetry;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\RfcLoggerTrait;
use Psr\Log\LoggerInterface;

/**
 * A custom logger shim to catch an suppress repeating errors.
 */
trait OpentelemetryLogEnhancerTrait {
  use RfcLoggerTrait;

  /**
   * Storage for repeatable errors.
   *
   * @var array
   */
  protected array $repeatableErrors = [];

  /**
   * Chunk size to repeat the error to logs.
   *
   * @var int
   */
  protected int $chunkSize = 50;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    protected LoggerInterface $systemLogger,
    protected ImmutableConfig $settings,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function doLog($level, $message, array $context = []): void {
    // When the collector is not responding, we can receive dozens of messages
    // with 'Export failure' message, that will flood our error log.
    // To workaround just log only the first error and step groups.
    // @todo Try to catch other repeating messages.
    switch ($message) {
      case 'Export failure':
        $exception = $context['exception'];
        $context['@message'] = $exception->getMessage();
        // The function seems not available in this type of exceptions.
        $context['%file'] = $exception->getFile();
        $context['%line'] = $exception->getLine();
        $context['@backtrace_string'] = $exception->getTraceAsString();

        if ($this->settings->get(OpentelemetryService::SETTING_ENDPOINT) === '') {
          $context['%endpoint'] = OpentelemetryService::SETTING_ENDPOINT_FALLBACK;
          $message = "Fallback OpenTelemetry endpoint %endpoint is not available. Please set the custom endpoint in the module settings. Parent exception:" . $message;
        }

        $messageInfoTemplate = "$message: @message in line %line of %file";
        $backtraceTemplate = "<pre>@backtrace_string</pre>";
        $message = "$messageInfoTemplate. $backtraceTemplate";

        $exceptionPrevious = $exception->getPrevious();
        if ($exceptionPrevious) {
          $context['@message_previous'] = $exceptionPrevious->getMessage();
          $message = "$messageInfoTemplate (previous exception message: @message_previous). $backtraceTemplate";
        }

        break;

      case 'Unhandled export error':
        $exception = $context['exception'];
        $context['%exception_message'] = $exception->getMessage();
        $message = "$message: %exception_message";
        break;

      case 'Export partial success':
        $context['%rejected_logs'] = $context['rejected_logs'];
        $context['%error_message'] = $context['error_message'];
        $message = "$message: %error_message (rejected logs: %rejected_logs)";

        break;
    }
    $this->doAggregatedLog($level, $message, $context);
  }

  /**
   * Logs a message with aggregation of similar logs into groups.
   *
   * @param mixed $level
   *   The log level.
   * @param mixed $message
   *   The log message.
   * @param array $context
   *   The log context array.
   */
  private function doAggregatedLog($level, $message, array $context) {
    $placeholders = array_filter($context, fn($key) => str_starts_with($key, '%'), ARRAY_FILTER_USE_KEY);
    $messageRendered = strtr($message, $placeholders);
    $messageHash = md5(implode(':', [
      $level,
      $messageRendered,
    ]));
    $logItem = $this->repeatableErrors[$messageHash] ?? NULL;
    if (!$logItem) {
      $logItem = [
        'count' => 0,
      ];
    }
    $logItem['count']++;
    $logItem['level'] = $level;
    $logItem['message'] = $message;
    $logItem['context'] = $context;
    if ($logItem['count'] == 1) {
      // Log the first occurrence.
      $this->doLogWithCount($logItem);
    }
    if ($logItem['count'] >= $this->chunkSize) {
      // Log the chunked occurrence.
      $this->doLogWithCount($logItem);
      // Reset count.
      $logItem['count'] = 0;
    }
    $this->repeatableErrors[$messageHash] = $logItem;
  }

  /**
   * Persist logs with count of the same items.
   *
   * @param array $logItem
   *   An array with a log item.
   */
  private function doLogWithCount(array $logItem) {
    $duplicatesCount = $logItem['count'] - 1;
    if ($duplicatesCount > 1) {
      $logItem['message'] .= " (repeated %count times)";
      $logItem['context']['%count'] = $duplicatesCount;
    }
    $this->systemLogger->log(
      $logItem['level'],
      $logItem['message'],
      $logItem['context'],
    );
  }

  /**
   * Logs repeated log entries on the request terminating.
   */
  public function onTerminate() {
    foreach ($this->repeatableErrors ?? [] as $logItem) {
      if ($logItem['count'] > 1 && $logItem['count'] % $this->chunkSize != 0) {
        $this->doLogWithCount($logItem);
      }
    }
  }

}
