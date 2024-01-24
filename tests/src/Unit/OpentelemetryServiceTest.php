<?php

namespace Drupal\Tests\opentelemetry\Unit;

use Drupal\opentelemetry\OpentelemetryService;
use Drupal\opentelemetry\OpentelemetryServiceInterface;
use Drupal\opentelemetry\OpentelemetryTransportFactoryProvider;
use Drupal\test_helpers\Stub\LoggerChannelFactoryStub;
use Drupal\test_helpers\TestHelpers;
use Drupal\Tests\UnitTestCase;
use OpenTelemetry\API\Trace\SpanContextValidator;
use OpenTelemetry\Contrib\Otlp\Protocols;
use OpenTelemetry\SDK\Common\Configuration\Defaults;
use OpenTelemetry\SDK\Common\Configuration\Variables;
use OpenTelemetry\SDK\Common\Time\ClockFactory;
use OpenTelemetry\SDK\Trace\Span;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
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
    $service->finalize();
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
    $service->finalize();
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
    $service->finalize();
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
    // putenv(Variables::OTEL_EXPORTER_OTLP_ENDPOINT);.
    putenv(Variables::OTEL_EXPORTER_OTLP_ENDPOINT . '=' . $settings[OpentelemetryService::SETTING_ENDPOINT]);
    putenv(Variables::OTEL_EXPORTER_OTLP_PROTOCOL . '=' . $settings[OpentelemetryService::SETTING_OTEL_EXPORTER_OTLP_PROTOCOL]);
    $service = $this->initTracerService();
    $this->checkServiceSettings($service, $settings);
    $service->finalize();
    $service->getRootScope()->detach();
  }

  /**
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
    $this->assertFalse($service->hasTracer());
    $this->assertNull($service->getTracer());
    $span = Span::getCurrent();
    $this->assertEquals(SpanContextValidator::INVALID_TRACE, $span->getContext()->getTraceId());
    $this->assertEquals(SpanContextValidator::INVALID_SPAN, $span->getContext()->getSpanId());
    $service->finalize();
    $this->assertNull($service->getRootScope());

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
    $service->finalize();
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
    $spanContext = $span->getContext();
    $spanId = $spanContext->getSpanId();
    $traceId = $spanContext->getTraceId();

    $this->assertNotEquals(SpanContextValidator::INVALID_TRACE, $traceId);
    $this->assertMatchesRegularExpression(SpanContextValidator::VALID_TRACE, $traceId);
    $this->assertNotEquals(SpanContextValidator::INVALID_SPAN, $spanId);
    $this->assertMatchesRegularExpression(SpanContextValidator::VALID_SPAN, $spanId);
  }

  /**
   * Configures the tracer service with all required dependencies.
   */
  private function initTracerService() {
    $request = new Request();
    TestHelpers::service('request_stack')->push($request);
    TestHelpers::service('logger.channel.opentelemetry', (new LoggerChannelFactoryStub())->get('opentelemetry'));
    TestHelpers::service('opentelemetry.transport.factory.provider', initService: TRUE);
    TestHelpers::service('opentelemetry.traces.transport.factory', \Drupal::service('opentelemetry.transport.factory.provider')->get(OpentelemetryTransportFactoryProvider::DATA_TYPE_TRACES));
    TestHelpers::service('opentelemetry.span_exporter.factory', initService: TRUE);
    TestHelpers::service('opentelemetry.logger_proxy', initService: TRUE);
    TestHelpers::service('plugin.manager.opentelemetry_trace', initService: TRUE);

    $spanExporter = TestHelpers::service('opentelemetry.span_exporter.factory')->create();
    $spanProcessor = new BatchSpanProcessor($spanExporter, ClockFactory::getDefault());
    $tracerProvider = new TracerProvider($spanProcessor);
    TestHelpers::service('opentelemetry.tracer_provider', $tracerProvider, TRUE);

    /** @var \Drupal\opentelemetry\OpentelemetryServiceInterface $service */
    $service = TestHelpers::initService('opentelemetry');
    return $service;
  }

}
