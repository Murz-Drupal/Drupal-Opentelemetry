<?php

declare(strict_types=1);

namespace Drupal\opentelemetry_logs_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\RfcLogLevel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for the opentelemetry_logs_test test page.
 */
class OpenTelemetryLogsTestController extends ControllerBase {

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */

  protected LoggerInterface $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    $instance = parent::create($container);
    $instance->logger = $container->get('logger.factory')->get('opentelemetry_logs_test');
    return $instance;
  }

  /**
   * Generates test metrics.
   *
   * @return array
   *   The render array.
   */
  public function __invoke(): array {
    $this->logger->log(RfcLogLevel::NOTICE, 'Foo', ['bar' => 'baz']);
    $this->logger->log(RfcLogLevel::ERROR, 'Error', ['key1' => 'value1', 'key2' => 'value2']);

    return [
      '#markup' => self::class . '::' . __FUNCTION__,
      '#cache' => ['max-age' => 0],
    ];
  }

}
