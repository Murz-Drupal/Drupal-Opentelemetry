<?php

namespace Drupal\opentelemetry\Plugin\opentelemetry\OpenTelemetryTrace;

use Drupal\Core\Database\Event\DatabaseEvent;

/**
 * The Database Statement span.
 *
 * @OpenTelemetryTrace(
 *   id = "database_statement",
 *   label = @Translation("Database Statement"),
 *   description = @Translation("Traces all database statements. Requires Core version 10.1 or higher."),
 * )
 */
class DatabaseStatementTrace extends OpentelemetryTraceBase {

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    return class_exists(DatabaseEvent::class);
  }

  /**
   * {@inheritdoc}
   */
  public function getUnavailableReason(): ?string {
    if ($this->isAvailable()) {
      return NULL;
    }
    return 'Missing StatementExecutionStartEvent in Core. The plugin requires
            Drupal Core version 10.1.0 or higher, or 10.1.0-dev later with
            the patch from the issue
            https://www.drupal.org/project/drupal/issues/3313355.';
  }

}
