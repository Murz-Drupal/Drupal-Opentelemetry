<?php

declare(strict_types=1);

namespace Drupal\opentelemetry_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\opentelemetry\OpentelemetryService;
use Drupal\opentelemetry\OpentelemetryServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for the opentelemetry_test test pages.
 */
class OpenTelemetryTestController extends ControllerBase {

  /**
   * The OpenTelemetry service.
   *
   * @var \Drupal\opentelemetry\OpentelemetryServiceInterface
   */
  protected OpentelemetryServiceInterface $opentelemetry;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $db;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    $instance = parent::create($container);
    $instance->opentelemetry = $container->get('opentelemetry');
    $instance->db = $container->get('database');
    return $instance;
  }

  /**
   * Generates test database queries.
   *
   * @return array
   *   The render array.
   */
  public function databaseStatementTraceTest(): array {
    $query1 = $this->db->query('SELECT * FROM {users} u587319 WHERE uid = :uid', [':uid' => 42]);
    $query1->fetchAll();
    $query2 = $this->db->select('users', 'u425153')->fields('u425153', ['uid'])->condition('uid', 142);
    $query2->execute();
    $output = ['#markup' => self::class . '::' . __FUNCTION__];
    $output['#cache']['max-age'] = 0;
    return $output;
  }

  /**
   * Generates the failing database query.
   *
   * @return array
   *   The render array.
   */
  public function databaseStatementTraceExecutionFailureTest(): array {
    $query1 = $this->db->query('FOO BAR');
    $query1->execute();
    $output = ['#markup' => self::class . '::' . __FUNCTION__];
    $output['#cache']['max-age'] = 0;
    return $output;
  }

  /**
   * Sets the test configuration.
   */
  public static function setTestConfiguration() {
    $config = \Drupal::configFactory()->getEditable(OpentelemetryService::SETTINGS_KEY);
    $endpoint = getenv('DRUPAL_OPENTELEMETRY_ENDPOINT') ?? 'http://localhost:4318';
    if ($endpoint) {
      $config->set(OpentelemetryService::SETTING_ENDPOINT, $endpoint);
    }
    $config->save();
  }

}
