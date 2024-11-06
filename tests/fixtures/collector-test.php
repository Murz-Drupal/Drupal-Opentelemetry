#!/usr/bin/env php
<?php

/**
 * @file
 * A command line script to test OpenTelemetry collector.
 */

/* Put the collector endpoint into 'OPENTELEMETRY_ENDPOINT' environment variable
 * like this:
 * OPENTELEMETRY_ENDPOINT=http://collector:4318/v1/traces ./collector-test.php
 */

declare(strict_types=1);

use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

$drupalRoot = __DIR__;
while (!file_exists($drupalRoot . '/core/lib/Drupal.php')) {
  $drupalRoot = dirname($drupalRoot);
  if ($drupalRoot == '') {
    throw new \Exception('Drupal root directory cannot be found.');
  }
}

require $drupalRoot . '/autoload.php';

putenv('OTEL_SERVICE_NAME=OpenTelemetry/test.php');

$endpoint = getenv('OPENTELEMETRY_ENDPOINT' ?: 'http://localhost:4318/v1/traces');

$contentType = 'application/x-protobuf';

$transportFactory = new OtlpHttpTransportFactory();
$transport = $transportFactory->create(
  $endpoint,
  $contentType,
  [],
  NULL,
  1,
);

$exporter = new SpanExporter($transport);

echo 'Starting OTLP tracer' . PHP_EOL;

$tracerProvider = new TracerProvider(
  new SimpleSpanProcessor(
    $exporter
  )
);
$tracer = $tracerProvider->getTracer('io.opentelemetry.contrib.php');

$rootSpan = $tracer->spanBuilder('root')->startSpan();

echo 'Trace started, trace id: ' . $rootSpan->getContext()->getTraceId() . PHP_EOL;
echo 'Root span started,      id: ' . $rootSpan->getContext()->getSpanId() . PHP_EOL;
$scope = $rootSpan->activate();

for ($i = 0; $i < 3; $i++) {
  $span = $tracer->spanBuilder('loop-' . $i)->startSpan();
  echo '- Child span started,   id: ' . $span->getContext()->getSpanId() . PHP_EOL;

  $span
    ->setAttribute('number', $i)
    ->setAttribute('foo', 'bar');

  $span->addEvent(
    'found_login' . $i, [
      'id' => $i,
      // cspell:disable-next-line
      'username' => 'otuser' . $i,
    ]
  );
  $span->addEvent(
    'generated_session', [
      'id' => md5((string) microtime(TRUE)),
    ]
  );

  $span->end();
  echo '- Child span ended,     id: ' . $span->getContext()->getSpanId() . PHP_EOL;
}
$rootSpan->end();
echo 'Root span ended,        id: ' . $rootSpan->getContext()->getSpanId() . PHP_EOL;

echo 'Trace finished, trace id: ' . $rootSpan->getContext()->getTraceId() . PHP_EOL;

$scope->detach();
echo PHP_EOL . 'OTLP tracer finished. Now find spans by displayed ids in Traces list.' . PHP_EOL;
$tracerProvider->shutdown();
