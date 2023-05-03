<?php

namespace Drupal\Tests\opentelemetry\Kernel;

use Drupal\KernelTests\KernelTestBase;
use OpenTelemetry\API\Trace\TracerProviderInterface;

/**
 * Tests the tracer provider is configured correctly.
 *
 * @group opentelemetry
 */
class TracerProviderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['opentelemetry'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['opentelemetry']);
  }

  /**
   * Tests getting a tracer.
   */
  public function testGetTracer(): void {
    $tracerProvider = $this->container->get('OpenTelemetry\SDK\Trace\TracerProvider');
    assert($tracerProvider instanceof TracerProviderInterface);

    $this->assertNotNull($tracerProvider);

    $tracer = $tracerProvider->getTracer('foo');

    $this->assertNotNull($tracer);
    $scope = $tracer->getInstrumentationScope();

    $this->assertEquals('foo', $scope->getName());
  }

}
