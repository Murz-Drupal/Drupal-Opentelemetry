<?php

namespace Drupal\opentelemetry;

use Drupal\Core\Logger\RfcLoggerTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

// A workaround to make the logger compatible with Drupal 9.x and 10.x together.
if (version_compare(\Drupal::VERSION, '10.0.0') <= 0) {
  require_once __DIR__ . '/OpentelemetryLoggerProxyTrait.D9.inc';
}
else {
  require_once __DIR__ . '/OpentelemetryLoggerProxyTrait.D10.inc';
}

/**
 * A custom logger shim to catch an suppress repeating errors.
 */
class OpentelemetryLoggerProxy implements LoggerInterface, EventSubscriberInterface {
  use RfcLoggerTrait;
  use OpentelemetryLoggerProxyTrait;

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
    $this->repeatableErrors[$messageHash] = $logItem;
    if (
      $logItem['count'] == 1
      || $logItem['count'] == $this->chunkSize
    ) {
      $this->doLogWithCount($logItem);
      $logItem['count'] = 0;
    }
  }

  /**
   * Persist logs with count of the same items.
   *
   * @param array $logItem
   *   An array with a log item.
   */
  private function doLogWithCount(array $logItem) {
    if ($logItem['count'] > 2) {
      $logItem['count']--;
      $logItem['message'] .= " (repeated %count times)";
      $logItem['context']['%count'] = $logItem['count'];
    }
    $this->systemLogger->log(
      $logItem['level'],
      $logItem['message'],
      $logItem['context'],
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::TERMINATE => ['onTerminate', 90],
    ];
  }

  /**
   * Logs repreated log entries on the request terminating.
   */
  public function onTerminate() {
    foreach ($this->repeatableErrors ?? [] as $logItem) {
      if ($logItem['count'] > 1 && $logItem['count'] % $this->chunkSize != 0) {
        $this->doLogWithCount($logItem);
      }
    }
  }

}
