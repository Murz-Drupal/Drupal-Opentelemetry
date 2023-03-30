<?php

namespace Drupal\Tests\opentelemetry\Unit;

use Drupal\opentelemetry\OpenTelemetryTracerService;
use Drupal\opentelemetry\OpenTelemetryTracerServiceInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\test_helpers\TestHelpers;
use OpenTelemetry\SDK\Resource\Detectors\Environment;

/**
 * @coversDefaultClass \Drupal\opentelemetry\OpenTelemetryTracerService
 * @group opentelemetry
 */
class OpenTelemetryTracerServiceTest extends UnitTestCase {

  /**
   * @covers ::__construct
   * @covers ::getTracer
   * @covers ::getRootSpanName
   */
  public function testService() {

    $settinsFallback = [
      OpenTelemetryTracerService::SETTING_ROOT_SPAN_NAME => OpenTelemetryTracerService::ROOT_SPAN_NAME_FALLBACK,
      OpenTelemetryTracerService::SETTING_ENDPOINT => OpenTelemetryTracerService::ENDPOINT_FALLBACK,
      OpenTelemetryTracerService::SETTING_SERVICE_NAME => OpenTelemetryTracerService::SERVICE_NAME_FALLBACK,
    ];

    /** @var \Drupal\opentelemetry\OpenTelemetryTracerServiceInterface $service */
    $service = TestHelpers::initService('opentelemetry.tracer');
    $this->checkServiceSettings($service, $settinsFallback);

    $settings = [
      OpenTelemetryTracerService::SETTING_ROOT_SPAN_NAME => 'custom',
      OpenTelemetryTracerService::SETTING_ENDPOINT => 'https://collector:80/api/v2/spans',
      OpenTelemetryTracerService::SETTING_SERVICE_NAME => 'My Drupal',
    ];
    TestHelpers::service('config.factory')->stubSetConfig(OpenTelemetryTracerService::SETTINGS_KEY, $settings);
    $service = TestHelpers::initService('opentelemetry.tracer');
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
    $transport = TestHelpers::getPrivateProperty($service, 'transport');
    $transportEndpoint = TestHelpers::getPrivateProperty($transport, 'endpoint');
    $this->assertEquals($settings[OpenTelemetryTracerService::SETTING_ENDPOINT], $transportEndpoint);
    $environmentAttributes = (new Environment())->getResource()->getAttributes();
    $this->assertEquals($settings[OpenTelemetryTracerService::SETTING_SERVICE_NAME], $environmentAttributes->get('service.name'));
  }

}
