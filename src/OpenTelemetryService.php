<?php
namespace Drupal\opentelemetry;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Http\RequestStack;
use Http\Adapter\Guzzle6\Client;
use Http\Factory\Guzzle\RequestFactory;
use Http\Factory\Guzzle\StreamFactory;

use OpenTelemetry\Context\Context;
use OpenTelemetry\Contrib\Jaeger\Exporter as JaegerExporter;
use OpenTelemetry\Sdk\Trace\Attributes;
use OpenTelemetry\Sdk\Trace\Clock;
use OpenTelemetry\Sdk\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\Sdk\Trace\SamplingResult;
use OpenTelemetry\Sdk\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\Sdk\Trace\TracerProvider;
use OpenTelemetry\Trace as API;

/**
 * Class OpenTelemetryService.
 *
 * @package Drupal\opentelemetry
 */
class OpenTelemetryService {
  protected $sampler;
  protected $samplingResult;
  protected $exporter;
  protected $endpoint;

  /**
   * Constructs a new OpenTelemetry service.
   *
   * @param Drupal\Core\Config\ConfigFactory $configFactory
   *   Drupal logger.
   * @param Drupal\Core\Http\RequestStack $requestStack
   *   Client for making HTTP Calls.
   */
  public function __construct(ConfigFactory $configFactory, RequestStack $requestStack) {
    $this->endpoint = $configFactory->get('opentelemetry.settings')->get('endpoint', 'http://localhost:9411/api/v2/spans');

    $this->sampler = new AlwaysOnSampler();

    $samplerUniqueId = md5((string) microtime(true));

    $this->samplingResult = $this->sampler->shouldSample(
        Context::getCurrent(),
        $samplerUniqueId,
        'io.opentelemetry.drupal',
        API\SpanKind::KIND_INTERNAL
    );

    $serviceName = "Drupal";
    $this->exporter = new JaegerExporter(
        $serviceName,
        $this->endpoint,
        new Client(),
        new RequestFactory(),
        new StreamFactory()
    );
  }

  public function createTracer() {
    $tracer = (new TracerProvider())
      ->addSpanProcessor(new BatchSpanProcessor($this->exporter, Clock::get()))
      ->getTracer('io.opentelemetry.contrib.php');
    return $tracer;
  }
}
