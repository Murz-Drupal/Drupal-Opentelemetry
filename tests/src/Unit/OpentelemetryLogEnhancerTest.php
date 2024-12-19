<?php

declare(strict_types=1);

namespace Drupal\Tests\opentelemetry\Unit;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Tests\UnitTestCase;
use Drupal\opentelemetry\OpentelemetryLogEnhancer;
use Drupal\opentelemetry\OpentelemetryLogEnhancerD9;
use Drupal\test_helpers\TestHelpers;

/**
 * @coversDefaultClass \Drupal\opentelemetry\OpentelemetryService
 * @group opentelemetry
 */
class OpentelemetryLogEnhancerTest extends UnitTestCase {

  /**
   * @covers ::__construct
   * @covers ::getTracer
   */
  public function testLogger() {
    $loggerFactory = TestHelpers::service('logger.factory');
    $loggerChannel = \Drupal::logger('test_channel');
    $settings = $this->createMock(ImmutableConfig::class);
    if (version_compare(\Drupal::VERSION, '10.0.0') <= 0) {
      $service = new OpentelemetryLogEnhancerD9($loggerChannel, $settings);
    }
    else {
      $service = new OpentelemetryLogEnhancer($loggerChannel, $settings);
    }
    $skeleton = [
      'type' => 'opentelemetry',
      'severity' => RfcLogLevel::NOTICE,
    ];
    $service->log(RfcLogLevel::NOTICE, 'Foo');
    $expected[] = $skeleton + [
      'message' => 'Foo',
    ];
    $service->log(RfcLogLevel::WARNING, 'Foo');
    $expected[] = $skeleton + [
      'message' => 'Foo',
      'severity' => RfcLogLevel::WARNING,
    ];
    $chunkSize = 50;
    $chunkCount = 2;
    $chunkAdd = 3;
    for ($i = 0; $i < $chunkSize * $chunkCount + $chunkAdd; $i++) {
      $service->log(1, 'Bar');
    }
    for ($i = 0; $i < $chunkCount; $i++) {
      $expected[] = $skeleton + [
        'message' => 'Bar',
        'severity' => RfcLogLevel::WARNING,
      ];
      $expected[] = $skeleton + [
        'message' => 'Bar (repeated %count times)',
        'severity' => RfcLogLevel::WARNING,
        '_context' => ['%count' => $chunkSize - 1],
      ];
    }
    $expected[] = $skeleton + [
      'message' => 'Bar',
      'severity' => RfcLogLevel::WARNING,
    ];
    $expectedLater[] = $skeleton + [
      'message' => 'Bar (repeated %count times)',
      'severity' => RfcLogLevel::WARNING,
      '_context' => ['%count' => 2],
    ];

    $service->log(RfcLogLevel::NOTICE, 'Foo');
    $expectedLater[] = $skeleton + [
      'message' => 'Foo',
    ];
    $service->log(RfcLogLevel::NOTICE, 'Baz');
    $expected[] = $skeleton + [
      'message' => 'Baz',
    ];

    $logsBefore = $loggerFactory->stubGetLogs();
    $this->assertCount(count($expected), $logsBefore);
    TestHelpers::isNestedArraySubsetOf($expected, $logsBefore);

    // Checking logs that appear on the terminate.
    $expected = array_merge($expected, $expectedLater);
    $event = new \stdClass();
    $service->onTerminate();
    $logsAfter = $loggerFactory->stubGetLogs();
    $this->assertCount(count($expected), $logsAfter);
    TestHelpers::isNestedArraySubsetOf($expected, $logsAfter);
  }

}
