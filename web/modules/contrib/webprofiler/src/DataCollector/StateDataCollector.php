<?php

declare(strict_types=1);

namespace Drupal\webprofiler\DataCollector;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * Collects states data.
 */
class StateDataCollector extends DataCollector implements HasPanelInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function collect(Request $request, Response $response, \Throwable $exception = NULL) {
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'state';
  }

  /**
   * Reset the collected data.
   */
  public function reset() {
    $this->data = [];
  }

  /**
   * Add a state to the collected data.
   *
   * @param $key
   *   The state key.
   */
  public function addState($key) {
    $this->data['state_get'][$key] = isset($this->data['state_get'][$key]) ? $this->data['state_get'][$key] + 1 : 1;
  }

  /**
   * Twig callback to show all requested state keys.
   *
   * @return int
   */
  public function getStateKeysCount(): int {
    return count($this->data['state_get']);
  }

  /**
   * {@inheritdoc}
   */
  public function getPanel(): array {
    $data = $this->data['state_get'];

    array_walk(
      $data,
      function (&$key, $data) {
        $key = [
          $data,
          $key,
        ];
      }
    );

    return [
      '#theme' => 'webprofiler_dashboard_section',
      '#data' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Name'),
          $this->t('Path'),
        ],
        '#rows' => $data,
        '#attributes' => [
          'class' => [
            'webprofiler__table',
          ],
        ],
        '#sticky' => TRUE,
      ],
    ];
  }

}
