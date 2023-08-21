<?php

namespace Drupal\Tests\opentelemetry\Unit;

use Drupal\opentelemetry\OpentelemetryService;
use Drupal\opentelemetry\OpentelemetryServiceInterface;
use Drupal\opentelemetry\OpentelemetryTraceManager;
use Drupal\test_helpers\Stub\LoggerChannelFactoryStub;
use Drupal\test_helpers\TestHelpers;
use Drupal\Tests\UnitTestCase;
use OpenTelemetry\Contrib\Otlp\Protocols;
use OpenTelemetry\Contrib\Otlp\SpanExporterFactory;
use OpenTelemetry\SDK\Common\Configuration\Variables;
use OpenTelemetry\SDK\Common\Export\TransportInterface;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversDefaultClass \Drupal\opentelemetry\OpentelemetryService
 * @group opentelemetry
 */
class OpentelemetryServiceTest extends UnitTestCase {

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
      OpentelemetryService::SETTING_ENDPOINT => 'http://localhost:4318',
      OpentelemetryService::SETTING_SERVICE_NAME => OpentelemetryService::SERVICE_NAME_FALLBACK,
      OpentelemetryService::SETTING_OTEL_EXPORTER_OTLP_PROTOCOL => 'http/protobuf',
    ];
    TestHelpers::service('config.factory')->stubSetConfig(OpentelemetryService::SETTINGS_KEY, $settinsFallback);
    $service = $this->initTracerService();
    $this->checkServiceSettings($service, $settinsFallback);
    $service->getRootScope()->detach();
  }

  /**
   * @covers ::__construct
   * @covers ::getTracer
   */
  public function testServiceCustomSettings() {
    // Settings from config.
    $settings = [
      OpentelemetryService::SETTING_ENDPOINT => 'https://collector:80',
      OpentelemetryService::SETTING_SERVICE_NAME => 'My Drupal',
      OpentelemetryService::SETTING_OTEL_EXPORTER_OTLP_PROTOCOL => 'http/json',
    ];
    TestHelpers::service('config.factory')->stubSetConfig(OpentelemetryService::SETTINGS_KEY, $settings);
    $service = $this->initTracerService();
    $this->checkServiceSettings($service, $settings);
    $service->getRootScope()->detach();
  }

  /**
   * @covers ::__construct
   * @covers ::getTracer
   */
  public function testServiceCustomSettingsFromEnv() {
    // Settings from config.
    $settings = [
      OpentelemetryService::SETTING_ENDPOINT => 'https://collector:80',
      OpentelemetryService::SETTING_SERVICE_NAME => 'My Drupal',
      OpentelemetryService::SETTING_OTEL_EXPORTER_OTLP_PROTOCOL => 'http/ndjson',
    ];
    putenv(Variables::OTEL_SERVICE_NAME . '=' . $settings[OpentelemetryService::SETTING_SERVICE_NAME]);
    putenv(Variables::OTEL_EXPORTER_OTLP_ENDPOINT . '=' . $settings[OpentelemetryService::SETTING_ENDPOINT]);
    putenv(Variables::OTEL_EXPORTER_OTLP_PROTOCOL . '=' . $settings[OpentelemetryService::SETTING_OTEL_EXPORTER_OTLP_PROTOCOL]);
    $service = $this->initTracerService();
    $this->checkServiceSettings($service, $settings);
    $service->getRootScope()->detach();
  }

  /**
   * @covers ::__construct
   * @covers ::getTracer
   */
  public function testServiceEmptyEndpoint() {
    // Settings from config.
    $settings = [
      OpentelemetryService::SETTING_ENDPOINT => '',
      OpentelemetryService::SETTING_SERVICE_NAME => 'My Drupal',
      OpentelemetryService::SETTING_OTEL_EXPORTER_OTLP_PROTOCOL => 'http/ndjson',
    ];
    putenv(Variables::OTEL_SERVICE_NAME . '=' . $settings[OpentelemetryService::SETTING_SERVICE_NAME]);
    putenv(Variables::OTEL_EXPORTER_OTLP_ENDPOINT . '=' . $settings[OpentelemetryService::SETTING_ENDPOINT]);
    putenv(Variables::OTEL_EXPORTER_OTLP_PROTOCOL . '=' . $settings[OpentelemetryService::SETTING_OTEL_EXPORTER_OTLP_PROTOCOL]);
    $service = $this->initTracerService();
    $this->checkServiceSettings($service, $settings);
    $service->getRootScope()->detach();
  }

  /**
   * Does check of applied service settings with configured values.
   *
   * @param \Drupal\opentelemetry\OpentelemetryServiceInterface $service
   *   An OpentelemetryService.
   * @param array $settings
   *   An array with settings values.
   */
  private function checkServiceSettings(OpentelemetryServiceInterface $service, array $settings) {
    $apiSuffix = '/v1/traces';
    // Getting transport object via chain of dependencies.
    $tracer = $service->getTracer();
    $tracerSharedState = TestHelpers::getPrivateProperty($tracer, 'tracerSharedState');
    $spanProcessor = $tracerSharedState->getSpanProcessor();
    $exporter = TestHelpers::getPrivateProperty($spanProcessor, 'exporter');
    $transport = TestHelpers::getPrivateProperty($exporter, 'transport');

    // For empty endpoint here should be a StreamTransport.
    if (empty($settings[OpentelemetryService::SETTING_ENDPOINT])) {
      $this->assertInstanceOf(TransportInterface::class, $transport);
    }
    else {
      $transportEndpoint = TestHelpers::getPrivateProperty($transport, 'endpoint');
      $transportContentType = TestHelpers::getPrivateProperty($transport, 'contentType');

      $this->assertEquals($settings[OpentelemetryService::SETTING_ENDPOINT] . $apiSuffix, $transportEndpoint);
      $this->assertEquals(Protocols::contentType($settings[OpentelemetryService::SETTING_OTEL_EXPORTER_OTLP_PROTOCOL]), $transportContentType);

      $resourceAttributes = $tracerSharedState->getResource()->getAttributes();
      $this->assertEquals($settings[OpentelemetryService::SETTING_SERVICE_NAME], $resourceAttributes->get('service.name'));
    }
  }

  /**
   * Configures the tracer service with all required dependencies.
   */
  private function initTracerService() {
    $request = new Request();
    TestHelpers::service('request_stack')->push($request);
    TestHelpers::service('logger.channel.opentelemetry', (new LoggerChannelFactoryStub())->get('opentelemetry'));
    TestHelpers::service('plugin.manager.opentelemetry_trace', $this->createMock(OpentelemetryTraceManager::class));
    TestHelpers::service('OpenTelemetry\Contrib\Otlp\SpanExporterFactory', new SpanExporterFactory());
    $spanExporterFactory = TestHelpers::initService('Drupal\opentelemetry\OpenTelemetrySpanExporterFactory');
    $spanExporter = $spanExporterFactory->create();
    $spanProcessor = new SimpleSpanProcessor($spanExporter);
    $tracerProvider = new TracerProvider($spanProcessor);
    TestHelpers::service('OpenTelemetry\SDK\Trace\TracerProvider', $tracerProvider, TRUE);

    /** @var \Drupal\opentelemetry\OpentelemetryServiceInterface $service */
    $service = TestHelpers::initService('opentelemetry');
    return $service;
  }

}
