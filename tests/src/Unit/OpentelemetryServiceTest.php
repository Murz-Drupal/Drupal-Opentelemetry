<?php

namespace Drupal\Tests\opentelemetry\Unit;

use Drupal\opentelemetry\OpentelemetryLoggerProxy;
use Drupal\opentelemetry\OpentelemetryService;
use Drupal\opentelemetry\OpentelemetryServiceInterface;
use Drupal\opentelemetry\OpenTelemetrySpanExporterFactory;
use Drupal\opentelemetry\OpentelemetryTraceManager;
use Drupal\test_helpers\Stub\LoggerChannelFactoryStub;
use Drupal\test_helpers\TestHelpers;
use Drupal\Tests\UnitTestCase;
use OpenTelemetry\API\Trace\SpanContextValidator;
use OpenTelemetry\Contrib\Otlp\Protocols;
use OpenTelemetry\Contrib\Otlp\SpanExporterFactory;
use OpenTelemetry\SDK\Common\Configuration\Defaults;
use OpenTelemetry\SDK\Common\Configuration\Variables;
use OpenTelemetry\SDK\Trace\Span;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

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
   * This test should be first to have not initialized OpenTelemetry instance.
   *
   * @todo Find a way to destroy the OpenTelemetry instance after finishing
   * each test.
   *
   * @covers ::__construct
   * @covers ::getTracer
   */
  public function testDisable() {
    // Check in disabled state.
    $settings = [
      OpentelemetryService::SETTING_DISABLE => TRUE,
    ];
    TestHelpers::service('config.factory')->stubSetConfig(OpentelemetryService::SETTINGS_KEY, $settings);
    $service = $this->initTracerService();
    $scope = $service->getRootScope();
    $this->assertFalse($service->hasTracer());
    $this->assertNull($service->getTracer());
    $span = Span::getCurrent();
    $this->assertEquals(SpanContextValidator::INVALID_TRACE, $span->getContext()->getTraceId());
    $this->assertEquals(SpanContextValidator::INVALID_SPAN, $span->getContext()->getSpanId());
    $service->onTerminate($this->createTerminateEventMock());
    if ($scope = $service->getRootScope()) {
      $scope->detach();
    }
    unset($service);

    // Check in enabled state.
    $settings = [
      OpentelemetryService::SETTING_DISABLE => FALSE,
    ];
    TestHelpers::service('config.factory')->stubSetConfig(OpentelemetryService::SETTINGS_KEY, $settings);
    $service = $this->initTracerService();
    $this->assertTrue($service->hasTracer());
    $this->assertNotNull($service->getTracer());
    $span = Span::getCurrent();
    $this->assertNotEquals(SpanContextValidator::INVALID_TRACE, $span->getContext()->getTraceId());
    $this->assertNotEquals(SpanContextValidator::INVALID_SPAN, $span->getContext()->getSpanId());
    $service->getRootScope()->detach();
    unset($service);
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
    unset($service);
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
    unset($service);
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
    unset($service);
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
    // putenv(Variables::OTEL_EXPORTER_OTLP_ENDPOINT);.
    putenv(Variables::OTEL_EXPORTER_OTLP_ENDPOINT . '=' . $settings[OpentelemetryService::SETTING_ENDPOINT]);
    putenv(Variables::OTEL_EXPORTER_OTLP_PROTOCOL . '=' . $settings[OpentelemetryService::SETTING_OTEL_EXPORTER_OTLP_PROTOCOL]);
    $service = $this->initTracerService();
    $this->checkServiceSettings($service, $settings);
    $service->getRootScope()->detach();
    unset($service);
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
    $transportEndpointDefault = Defaults::OTEL_EXPORTER_OTLP_ENDPOINT . $apiSuffix;
    // Getting transport object via chain of dependencies.
    $tracer = $service->getTracer();
    $tracerSharedState = TestHelpers::getPrivateProperty($tracer, 'tracerSharedState');
    $spanProcessor = $tracerSharedState->getSpanProcessor();
    $exporter = TestHelpers::getPrivateProperty($spanProcessor, 'exporter');
    $transport = TestHelpers::getPrivateProperty($exporter, 'transport');

    $transportEndpoint = TestHelpers::getPrivateProperty($transport, 'endpoint');
    $transportContentType = TestHelpers::getPrivateProperty($transport, 'contentType');

    if (empty($settings[OpentelemetryService::SETTING_ENDPOINT])) {
      $this->assertEquals($transportEndpointDefault, $transportEndpoint);
    }
    else {
      $this->assertEquals($settings[OpentelemetryService::SETTING_ENDPOINT] . $apiSuffix, $transportEndpoint);
    }
    $this->assertEquals(Protocols::contentType($settings[OpentelemetryService::SETTING_OTEL_EXPORTER_OTLP_PROTOCOL]), $transportContentType);

    $resourceAttributes = $tracerSharedState->getResource()->getAttributes();
    $this->assertEquals($settings[OpentelemetryService::SETTING_SERVICE_NAME], $resourceAttributes->get('service.name'));

    $span = Span::getCurrent();
    $this->assertNotEquals(SpanContextValidator::INVALID_TRACE, $span->getContext()->getTraceId());
    $this->assertMatchesRegularExpression(SpanContextValidator::VALID_TRACE, $span->getContext()->getTraceId());
    $this->assertNotEquals(SpanContextValidator::INVALID_SPAN, $span->getContext()->getSpanId());
    $this->assertMatchesRegularExpression(SpanContextValidator::VALID_SPAN, $span->getContext()->getSpanId());
  }

  /**
   * Configures the tracer service with all required dependencies.
   */
  private function initTracerService() {
    $request = new Request();
    TestHelpers::service('request_stack')->push($request);
    TestHelpers::service('logger.channel.opentelemetry', (new LoggerChannelFactoryStub())->get('opentelemetry'));
    TestHelpers::service('plugin.manager.opentelemetry_trace', $this->createMock(OpentelemetryTraceManager::class));
    TestHelpers::service(SpanExporterFactory::class, TestHelpers::initServiceFromYaml(dirname(__FILE__) . '/../../../opentelemetry.services.yml', SpanExporterFactory::class));
    TestHelpers::service(OpentelemetryLoggerProxy::class, TestHelpers::initService(OpentelemetryLoggerProxy::class));
    $spanExporterFactory = TestHelpers::initService(OpenTelemetrySpanExporterFactory::class);
    $spanExporter = $spanExporterFactory->create();
    $spanProcessor = new SimpleSpanProcessor($spanExporter);
    $tracerProvider = new TracerProvider($spanProcessor);
    TestHelpers::service(TracerProvider::class, $tracerProvider, TRUE);

    /** @var \Drupal\opentelemetry\OpentelemetryServiceInterface $service */
    $service = TestHelpers::initService('opentelemetry');
    return $service;
  }

  /**
   * Creates a mock of the TerminateEvent.
   */
  private function createTerminateEventMock() {
    $event = new TerminateEvent(
      $this->createMock(HttpKernelInterface::class),
      $this->createMock(Request::class),
      $this->createMock(Response::class),
    );
    return $event;
  }

}
