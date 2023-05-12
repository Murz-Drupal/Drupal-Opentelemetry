<?php

namespace Drupal\Tests\opentelemetry\Unit;

use Drupal\opentelemetry\OpentelemetryTracerService;
use Drupal\opentelemetry\OpentelemetryTracerServiceInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\test_helpers\Stub\LoggerChannelFactoryStub;
use Drupal\test_helpers\TestHelpers;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\Protocols;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Configuration\Variables;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use Drupal\opentelemetry\OpentelemetryTraceManager;
use OpenTelemetry\SDK\Common\Export\Stream\StreamTransport;

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
    putenv(Variables::OTEL_EXPORTER_OTLP_ENDPOINT);
    putenv(Variables::OTEL_EXPORTER_OTLP_PROTOCOL);
  }

  /**
   * @covers ::__construct
   * @covers ::getTracer
   */
  public function testServiceDefaultSettings() {
    $settinsFallback = [
      OpentelemetryTracerService::SETTING_ENDPOINT => 'http://localhost:4318/v1/traces',
      OpentelemetryTracerService::SETTING_SERVICE_NAME => OpentelemetryTracerService::SERVICE_NAME_FALLBACK,
      OpentelemetryTracerService::SETTING_OTEL_EXPORTER_OTLP_PROTOCOL => 'grpc',
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
    // Settings from config.
    $settings = [
      OpentelemetryTracerService::SETTING_ENDPOINT => 'https://collector:80/api/v2/spans',
      OpentelemetryTracerService::SETTING_SERVICE_NAME => 'My Drupal',
      OpentelemetryTracerService::SETTING_OTEL_EXPORTER_OTLP_PROTOCOL => 'http/json',
    ];
    TestHelpers::service('config.factory')->stubSetConfig(OpentelemetryTracerService::SETTINGS_KEY, $settings);
    $service = $this->initTracerService();
    $this->checkServiceSettings($service, $settings);
  }

  /**
   * @covers ::__construct
   * @covers ::getTracer
   */
  public function testServiceCustomSettingsFromEnv() {
    // Settings from config.
    $settings = [
      OpentelemetryTracerService::SETTING_ENDPOINT => 'https://collector:80/api/v2/spans',
      OpentelemetryTracerService::SETTING_SERVICE_NAME => 'My Drupal',
      OpentelemetryTracerService::SETTING_OTEL_EXPORTER_OTLP_PROTOCOL => 'http/ndjson',
    ];
    putenv(Variables::OTEL_SERVICE_NAME . '=' . $settings[OpentelemetryTracerService::SETTING_SERVICE_NAME]);
    putenv(Variables::OTEL_EXPORTER_OTLP_ENDPOINT . '=' . $settings[OpentelemetryTracerService::SETTING_ENDPOINT]);
    putenv(Variables::OTEL_EXPORTER_OTLP_PROTOCOL . '=' . $settings[OpentelemetryTracerService::SETTING_OTEL_EXPORTER_OTLP_PROTOCOL]);
    $service = $this->initTracerService();
    $this->checkServiceSettings($service, $settings);
  }

  /**
   * @covers ::__construct
   * @covers ::getTracer
   */
  public function testServiceEmptyEndpoint() {
    // Settings from config.
    $settings = [
      OpentelemetryTracerService::SETTING_ENDPOINT => '',
      OpentelemetryTracerService::SETTING_SERVICE_NAME => 'My Drupal',
      OpentelemetryTracerService::SETTING_OTEL_EXPORTER_OTLP_PROTOCOL => 'http/ndjson',
    ];
    putenv(Variables::OTEL_SERVICE_NAME . '=' . $settings[OpentelemetryTracerService::SETTING_SERVICE_NAME]);
    putenv(Variables::OTEL_EXPORTER_OTLP_ENDPOINT . '=' . $settings[OpentelemetryTracerService::SETTING_ENDPOINT]);
    putenv(Variables::OTEL_EXPORTER_OTLP_PROTOCOL . '=' . $settings[OpentelemetryTracerService::SETTING_OTEL_EXPORTER_OTLP_PROTOCOL]);
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

    // For empty endpoint here should be a StreamTransport
    if (empty($settings[OpentelemetryTracerService::SETTING_ENDPOINT])) {
      $this->assertInstanceOf(StreamTransport::class, $transport);
      $stream = TestHelpers::getPrivateProperty($transport, 'stream');
      $this->assertEquals('/dev/null', stream_get_meta_data($stream)['uri']);
    }
    else {
      $transportEndpoint = TestHelpers::getPrivateProperty($transport, 'endpoint');
      $transportContentType = TestHelpers::getPrivateProperty($transport, 'contentType');

      $this->assertEquals($settings[OpentelemetryTracerService::SETTING_ENDPOINT], $transportEndpoint);
      $this->assertEquals(Protocols::contentType($settings[OpentelemetryTracerService::SETTING_OTEL_EXPORTER_OTLP_PROTOCOL]), $transportContentType);

      $resourceAttributes = $tracerSharedState->getResource()->getAttributes();
      $this->assertEquals($settings[OpentelemetryTracerService::SETTING_SERVICE_NAME], $resourceAttributes->get('service.name'));
    }
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
