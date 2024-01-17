<?php

namespace Drupal\opentelemetry_logs\Logger;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\opentelemetry\OpentelemetryService;
use OpenTelemetry\API\Logs\EventLogger;
use OpenTelemetry\API\Logs\EventLoggerInterface;
use OpenTelemetry\API\Logs\LoggerInterface;
use OpenTelemetry\API\Logs\LogRecord;
use OpenTelemetry\SDK\Logs\LoggerProviderInterface;
use Psr\Log\LoggerInterface as PsrLogLoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Redirects logging messages to syslog.
 */
class OpentelemetryLogs implements PsrLogLoggerInterface, EventSubscriberInterface {
  use RfcLoggerTrait;

  /**
   * The module settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $settings;

  /**
   * An OpenTelemetry logger.
   *
   * @var \OpenTelemetry\API\Logs\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * An OpenTelemetry event logger.
   *
   * @var \OpenTelemetry\API\Logs\EventLoggerInterface
   */
  protected EventLoggerInterface $eventLogger;

  /**
   * The log event name.
   *
   * @var string
   */
  protected string $eventName;

  /**
   * Constructs the OpentelemetryLogs.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   A config factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The Request Stack.
   * @param \Drupal\Core\Logger\LogMessageParserInterface $parser
   *   The parser to use when extracting message variables.
   * @param \OpenTelemetry\SDK\Logs\LoggerProviderInterface $loggerProvider
   *   The parser to use when extracting message variables.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected RequestStack $requestStack,
    protected LogMessageParserInterface $parser,
    protected LoggerProviderInterface $loggerProvider,
  ) {
    $this->settings = $this->configFactory->get(OpentelemetryService::SETTINGS_KEY);
    // @todo Make it configurable via settings.
    $this->eventName = "drupal-log";
    $this->logger = $this->loggerProvider->getLogger(
      $this->settings->get(OpentelemetryService::SETTING_SERVICE_NAME)
    );
    // // @todo Reconfigure the logger for each new request.
    $currentRequest = $this->requestStack->getCurrentRequest();
    $this->eventLogger = new EventLogger($this->logger, $currentRequest->getHost());
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, string|\Stringable $message, array $context = []): void {
    global $base_url;

    // Populate the message placeholders and then replace them in the message.
    $message_placeholders = $this->parser->parseMessagePlaceholders($message, $context);
    $message = empty($message_placeholders) ? $message : strtr($message, $message_placeholders);

    $record = $context + [
      'message' => $message,
      'base_url' => $base_url,
    ];

    $record = (new LogRecord($record))
      ->setSeverityNumber($level)
      ->setSeverityText($this->getRfcLogLevelAsString($level));

    $this->eventLogger->logEvent($this->eventName, $record);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::TERMINATE => ['onTerminate', -100],
    ];
  }

  /**
   * Ends the root span and detaches the scope.
   *
   * @param \Symfony\Component\HttpKernel\Event\TerminateEvent $event
   *   A TerminateEvent.
   */
  public function onTerminate(TerminateEvent $event) {
    $this->finalize();
  }

  /**
   * Ends the root span and detaches the root scope.
   */
  public function finalize(): void {
    $this->loggerProvider->forceFlush();
  }

  /**
   * Finalizes the service if the terminate event is not fired by some reason.
   */
  public function __destruct() {
    $this->finalize();
  }

  /**
   * Converts a level integer to a string representiation of the RFC log level.
   *
   * @param int $level
   *      The log message level.
   *
   * @return string
   *      String representation of the log level.
   */
  protected function getRfcLogLevelAsString(int $level): string {
    return match ($level) {
      RfcLogLevel::EMERGENCY => LogLevel::EMERGENCY,
      RfcLogLevel::ALERT => LogLevel::ALERT,
      RfcLogLevel::CRITICAL => LogLevel::CRITICAL,
      RfcLogLevel::ERROR => LogLevel::ERROR,
      RfcLogLevel::WARNING => LogLevel::WARNING,
      RfcLogLevel::NOTICE => LogLevel::NOTICE,
      RfcLogLevel::INFO => LogLevel::INFO,
      RfcLogLevel::DEBUG => LogLevel::DEBUG,
    };
  }

}
