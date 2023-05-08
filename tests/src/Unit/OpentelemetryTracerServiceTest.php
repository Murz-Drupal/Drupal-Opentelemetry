<?php

namespace Drupal\Tests\opentelemetry\Unit;

use Drupal\opentelemetry\OpentelemetryTracerService;
use Drupal\opentelemetry\OpentelemetryTracerServiceInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\test_helpers\Stub\LoggerChannelFactoryStub;
use Drupal\test_helpers\TestHelpers;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Configuration\Variables;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use Drupal\opentelemetry\OpentelemetryTraceManager;

/**
 * @coversDefaultClass \Drupal\opentelemetry\OpentelemetryTracerService
 * @group opentelemetry
 */
class OpentelemetryTracerServiceTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Resetting env variables.
    putenv(Variables::OTEL_SERVICE_NAME);
  }

  /**
   * @covers ::__construct
   * @covers ::getTracer
   */
  public function testServiceDefaultSettings() {
    $settinsFallback = [
      OpentelemetryTracerService::SETTING_ENDPOINT => OpentelemetryTracerService::ENDPOINT_FALLBACK,
      OpentelemetryTracerService::SETTING_SERVICE_NAME => OpentelemetryTracerService::SERVICE_NAME_FALLBACK,
    ];
    TestHelpers::service('config.factory')->stubSetConfig(OpentelemetryTracerService::SETTINGS_KEY, $settinsFallback);
    $service = $this->initTracerService();
    $this->checkServiceSettings($service, $settinsFallback);
  }

  /**
   * @covers ::__construct
   * @covers ::getTracer
   */
  public function testServiceCustomSettings() {
    $settings = [
      OpentelemetryTracerService::SETTING_ENDPOINT => 'https://collector:80/api/v2/spans',
      OpentelemetryTracerService::SETTING_SERVICE_NAME => 'My Drupal',
    ];
    TestHelpers::service('config.factory')->stubSetConfig(OpentelemetryTracerService::SETTINGS_KEY, $settings);
    $service = $this->initTracerService();
    $this->checkServiceSettings($service, $settings);
  }

  /**
   * Does check of applied service settings with configured values.
   *
   * @param \Drupal\opentelemetry\OpentelemetryTracerServiceInterface $service
   *   An OpentelemetryTracerService.
   * @param array $settings
   *   An array with settings values.
   */
  private function checkServiceSettings(OpentelemetryTracerServiceInterface $service, array $settings) {
    // Getting transport object via chain of dependencies.
    $tracer = $service->getTracer();
    $tracerSharedState = TestHelpers::getPrivateProperty($tracer, 'tracerSharedState');
    $spanProcessor = $tracerSharedState->getSpanProcessor();
    $exporter = TestHelpers::getPrivateProperty($spanProcessor, 'exporter');
    $transport = TestHelpers::getPrivateProperty($exporter, 'transport');
    $transportEndpoint = TestHelpers::getPrivateProperty($transport, 'endpoint');
    $transportContentType = TestHelpers::getPrivateProperty($transport, 'contentType');

    $this->assertEquals($settings[OpentelemetryTracerService::SETTING_ENDPOINT], $transportEndpoint);

    $resourceAttributes = $tracerSharedState->getResource()->getAttributes();
    $this->assertEquals($settings[OpentelemetryTracerService::SETTING_SERVICE_NAME], $resourceAttributes->get('service.name'));
  }

  /**
   * Configures the tracer service with all required dependencies.
   */
  private function initTracerService() {
    TestHelpers::service('logger.channel.opentelemetry', (new LoggerChannelFactoryStub())->get('opentelemetry'));
    TestHelpers::service('plugin.manager.opentelemetry_trace', $this->createMock(OpentelemetryTraceManager::class));
    TestHelpers::service('OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory', new OtlpHttpTransportFactory());
    $transportFactory = TestHelpers::initService('Drupal\opentelemetry\TransportFactory');
    $transport = $transportFactory->create();
    $spanExporter = new SpanExporter($transport);
    $spanProcessor = new SimpleSpanProcessor($spanExporter);
    $tracerProvider = new TracerProvider($spanProcessor);
    TestHelpers::service('OpenTelemetry\SDK\Trace\TracerProvider', $tracerProvider, TRUE);

    /** @var \Drupal\opentelemetry\OpentelemetryTracerServiceInterface $service */
    $service = TestHelpers::initService('opentelemetry.tracer');
    return $service;
  }

}
