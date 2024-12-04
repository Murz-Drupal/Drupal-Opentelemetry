<?php

declare(strict_types=1);

namespace Drupal\opentelemetry_metrics_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\opentelemetry_metrics\OpentelemetryMetrics;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for the opentelemetry_metrics_test test page.
 */
class OpenTelemetryMetricsTestController extends ControllerBase {

  /**
   * The metrics service.
   *
   * @var \Drupal\opentelemetry_metrics\OpentelemetryMetrics
   */

  protected OpentelemetryMetrics $opentelemetryMetrics;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    $instance = parent::create($container);
    $instance->opentelemetryMetrics = $container->get('opentelemetry.metrics');
    return $instance;
  }

  /**
   * Generates test metrics.
   *
   * @return array
   *   The render array.
   */
  public function view(): array {
    $meter1 = $this->opentelemetryMetrics->getMeter('opentelemetry_metrics_test.meter1');
    $meter2 = $this->opentelemetryMetrics->getMeter('opentelemetry_metrics_test.meter2');

    $counter1 = $meter1->createCounter('counter1', '', 'counter1 description');
    $counter2 = $meter2->createCounter('counter2', 'kg', 'counter2 description');
    for ($i = 0; $i < 7; $i++) {
      $counter1->add(1);
      if ($i % 5 === 0) {
        $this->opentelemetryMetrics->collect();
      }
    }
    $counter2->add(42);

    return [
      '#markup' => self::class . '::' . __FUNCTION__,
      '#cache' => ['max-age' => 0],
    ];
    ;
  }

}
