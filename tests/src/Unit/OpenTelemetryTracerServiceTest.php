<?php

namespace Drupal\Tests\opentelemetry\Unit;

use Drupal\opentelemetry\OpenTelemetryTracerService;
use Drupal\opentelemetry\OpenTelemetryTracerServiceInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\test_helpers\Stub\LoggerChannelFactoryStub;
use Drupal\test_helpers\TestHelpers;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Configuration\Variables;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

/**
 * @coversDefaultClass \Drupal\opentelemetry\OpenTelemetryTracerService
 * @group opentelemetry
 */
class OpenTelemetryTracerServiceTest extends UnitTestCase {

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
   * @covers ::getRootSpanName
   */
  public function testServiceDefaultSettings() {
    $settinsFallback = [
      OpenTelemetryTracerService::SETTING_ROOT_SPAN_NAME => OpenTelemetryTracerService::ROOT_SPAN_NAME_FALLBACK,
      OpenTelemetryTracerService::SETTING_ENDPOINT => OpenTelemetryTracerService::ENDPOINT_FALLBACK,
      OpenTelemetryTracerService::SETTING_SERVICE_NAME => OpenTelemetryTracerService::SERVICE_NAME_FALLBACK,
    ];
    TestHelpers::service('config.factory')->stubSetConfig(OpenTelemetryTracerService::SETTINGS_KEY, $settinsFallback);
    $service = $this->initTracerService();
    $this->checkServiceSettings($service, $settinsFallback);
  }

  /**
   * @covers ::__construct
   * @covers ::getTracer
   * @covers ::getRootSpanName
   */
  public function testServiceCustomSettings() {
    $settings = [
      OpenTelemetryTracerService::SETTING_ROOT_SPAN_NAME => 'custom',
      OpenTelemetryTracerService::SETTING_ENDPOINT => 'https://collector:80/api/v2/spans',
      OpenTelemetryTracerService::SETTING_SERVICE_NAME => 'My Drupal',
    ];
    TestHelpers::service('config.factory')->stubSetConfig(OpenTelemetryTracerService::SETTINGS_KEY, $settings);
    $service = $this->initTracerService();
    $this->checkServiceSettings($service, $settings);
  }

  /**
   * Does check of applied service settings with configured values.
   *
   * @param \Drupal\opentelemetry\OpenTelemetryTracerServiceInterface $service
   *   An OpenTelemetryTracerService.
   * @param array $settings
   *   An array with settings values.
   */
  private function checkServiceSettings(OpenTelemetryTracerServiceInterface $service, array $settings) {
    $this->assertEquals($settings[OpenTelemetryTracerService::SETTING_ROOT_SPAN_NAME], $service->getRootSpanName());

    // Getting transport object via chain of dependencies.
    $tracer = $service->getTracer();
    $tracerSharedState = TestHelpers::getPrivateProperty($tracer, 'tracerSharedState');
    $spanProcessor = $tracerSharedState->getSpanProcessor();
    $exporter = TestHelpers::getPrivateProperty($spanProcessor, 'exporter');
    $transport = TestHelpers::getPrivateProperty($exporter, 'transport');
    $transportEndpoint = TestHelpers::getPrivateProperty($transport, 'endpoint');
    $transportContentType = TestHelpers::getPrivateProperty($transport, 'contentType');

    $this->assertEquals($settings[OpenTelemetryTracerService::SETTING_ENDPOINT], $transportEndpoint);

    $resourceAttributes = $tracerSharedState->getResource()->getAttributes();
    $this->assertEquals($settings[OpenTelemetryTracerService::SETTING_SERVICE_NAME], $resourceAttributes->get('service.name'));
  }

  /**
   * Configures the tracer service with all required dependencies.
   */
  private function initTracerService() {
    TestHelpers::service('logger.channel.opentelemetry', (new LoggerChannelFactoryStub())->get('opentelemetry'));
    TestHelpers::service('OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory', new OtlpHttpTransportFactory());
    $transportFactory = TestHelpers::initService('Drupal\opentelemetry\TransportFactory');
    $transport = $transportFactory->create();
    $spanExporter = new SpanExporter($transport);
    $spanProcessor = new SimpleSpanProcessor($spanExporter);
    $tracerProvider = new TracerProvider($spanProcessor);
    TestHelpers::service('OpenTelemetry\SDK\Trace\TracerProvider', $tracerProvider, TRUE);

    /** @var \Drupal\opentelemetry\OpenTelemetryTracerServiceInterface $service */
    $service = TestHelpers::initService('opentelemetry.tracer');
    return $service;
  }

}
