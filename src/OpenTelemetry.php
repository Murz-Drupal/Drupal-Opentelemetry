<?php
namespace Drupal\opentelemetry;

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
 * Class OpenTelemetry
 *
 * @package Drupal\opentelemetry
 */
class OpenTelemetry {
  public $sampler;
  public $samplingResult;
  public $exporter;
  public $endpoint;

  public function __construct() {

    $this->endpoint = $_ENV['OPENTELEMETRY_ENDPOINT'] ?? 'http://localhost:9411/api/v2/spans';

    $this->sampler = new AlwaysOnSampler();

    $samplerUniqueId = md5((string) microtime(true));

    $this->samplingResult = $this->sampler->shouldSample(
        Context::getCurrent(),
        $samplerUniqueId,
        'io.opentelemetry.example',
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