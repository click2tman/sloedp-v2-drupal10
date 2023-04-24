<?php

declare(strict_types=1);

namespace Drupal\webprofiler\DataCollector;

use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * Collects routing data.
 */
class RoutingDataCollector extends DataCollector implements HasPanelInterface {

  use StringTranslationTrait, PanelTrait;

  /**
   * Constructs a new RoutingDataCollector.
   *
   * @param \Drupal\Core\Routing\RouteProviderInterface $routeProvider
   *   The route provider.
   */
  public function __construct(private readonly RouteProviderInterface $routeProvider) {
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return 'routing';
  }

  /**
   * {@inheritdoc}
   */
  public function collect(Request $request, Response $response, \Throwable $exception = NULL) {
    $this->data['routing'] = [];
    foreach ($this->routeProvider->getAllRoutes() as $route_name => $route) {
      $this->data['routing'][] = [
        'name' => $route_name,
        'path' => $route->getPath(),
        ];
    }
  }

  /**
   * Reset the collected data.
   */
  public function reset() {
    $this->data = [];
  }

  /**
   * Return the number of routes.
   *
   * @return int
   */
  public function getRoutesCount(): int {
    return count($this->routing());
  }

  /**
   * Twig callback for displaying the routes.
   */
  public function routing() {
    return $this->data['routing'];
  }

  /**
   * {@inheritdoc}
   */
  public function getPanel(): array {
    $data = $this->data['routing'];

    return [
      '#theme' => 'webprofiler_dashboard_section',
      '#data' => [
      '#type' => 'table',
      '#header' => [
        $this->t('Name'),
        $this->t('Path'),
      ],
      '#rows' => array_map(
          function ($data) {
              return [
              $data['name'],
              $data['path'],
              ];
          }, $data
      ),
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
