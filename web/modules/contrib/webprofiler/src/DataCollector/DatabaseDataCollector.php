<?php

declare(strict_types=1);

namespace Drupal\webprofiler\DataCollector;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * Collects database data.
 */
class DatabaseDataCollector extends DataCollector implements HasPanelInterface {

  /**
   * DatabaseDataCollector constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(
    private readonly Connection $database
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function collect(Request $request, Response $response, \Throwable $exception = NULL) {
    $connections = [];
    foreach (Database::getAllConnectionInfo() as $key => $info) {
      try {
        $database = Database::getConnection('default', $key);

        if ($database->getLogger()) {
          $connections[$key] = $database->getLogger()->get('webprofiler');
        }
      }
      catch (\Exception $e) {
        // There was some error during database connection, maybe a stale
        // configuration in settings.php or wrong values used for a migration.
      }
    }

    $this->data['connections'] = array_keys($connections);

    $data = [];
    foreach ($connections as $key => $queries) {
      foreach ($queries as $query) {
        // Remove caller args.
        unset($query['caller']['args']);

        // Remove query args element if empty.
        if (isset($query['args']) && empty($query['args'])) {
          unset($query['args']);
        }

        // Save time in milliseconds.
        $query['time'] = $query['time'] * 1000;
        $query['database'] = $key;

        $query['query'] = str_replace('"', '', $query['query']);

        $data[] = $query;
      }
    }

    $this->data['queries'] = $data;

    $options = $this->database->getConnectionOptions();

    // Remove password for security.
    unset($options['password']);

    $this->data['database'] = $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return 'database';
  }

  /**
   * Reset the collected data.
   */
  public function reset() {
    $this->data = [];
  }

  /**
   * {@inheritdoc}
   */
  public function getPanel(): array {
    // Panel is implemented in the template.
    return [];
  }

  /**
   * Return the database info.
   *
   * @return array
   *   The database info.
   */
  public function getDatabase(): array {
    return $this->data['database'];
  }

  /**
   * Return the number of execute queries.
   *
   * @return int
   *   The number of execute queries.
   */
  public function getQueryCount(): int {
    return count($this->data['queries']);
  }

  /**
   * Return a list of execute queries.
   *
   * Queries are sorted by the value of query_sort config option.
   *
   * @return array
   *   A list of execute queries.
   */
  public function getQueries(): array {
    // When a profile is loaded from storage this object is deserialized and
    // no constructor is called, so we cannot use dependency injection.
    // phpcs:disable
    $query_sort = \Drupal::configFactory()
      ->get('webprofiler.settings')
      ->get('query_sort') ?: '';
    // phpcs:enable

    $queries = $this->data['queries'];
    if ('duration' === $query_sort) {
      usort($queries, function (array $a, array $b): int {
        return $a['time'] <=> $b['time'];
      });
    }

    return $queries;
  }

  /**
   * Returns the total execution time.
   *
   * @return float
   *   The total execution time.
   */
  public function getTime(): float {
    $time = 0;

    foreach ($this->data['queries'] as $query) {
      $time += $query['time'];
    }

    return $time;
  }

  /**
   * Returns the configured query highlight threshold.
   *
   * @return int
   *   The configured query highlight threshold.
   */
  public function getQueryHighlightThreshold(): int {
    // When a profile is loaded from storage this object is deserialized and
    // no constructor is called, so we cannot use dependency injection.
    // phpcs:disable
    return \Drupal::config('webprofiler.settings')->get('query_highlight');
    // php:enable
  }

}
