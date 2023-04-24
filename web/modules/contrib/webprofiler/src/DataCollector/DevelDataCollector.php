<?php

declare(strict_types=1);

namespace Drupal\webprofiler\DataCollector;

use Drupal\Core\Link;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Render\Markup;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * Provides a toolbar item for Devel menu links.
 */
class DevelDataCollector extends DataCollector {

  /**
   * DevelDataCollector constructor.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match.
   */
  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function collect(Request $request, Response $response, \Throwable $exception = NULL) {
    $original_route = $this->routeMatch->getRouteName();
    if ($original_route != NULL) {
      $original_route_parameters = $this->routeMatch
        ->getRawParameters()
        ->all();
      $this->data['destination'] = Url::fromRoute($original_route, $original_route_parameters)
        ->toString();
    }
  }

  /**
   * Return the list of Devel links.
   *
   * @return array
   *   The list of Devel links.
   */
  public function getLinks(): array {
    return $this->develMenuLinks($this->data['destination']);
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'devel';
  }

  /**
   * Reset the collected data.
   */
  public function reset() {
    $this->data = [];
  }

  /**
   * Return the list of Devel links for a given route.
   *
   * @param string $destination
   *   The route to use as a destination.
   *
   * @return array
   *   Array containing Devel Menu links
   */
  protected function develMenuLinks(string $destination): array {
    // When a profile is loaded from storage this object is deserialized and
    // no constructor is called, so we cannot use dependency injection.
    // phpcs:disable
    /** @var \Drupal\Core\Menu\MenuLinkTreeInterface $menuLinkTreeService */
    $menuLinkTreeService = \Drupal::service('menu.link_tree');
    /** @var \Drupal\Core\Render\Renderer $rendererService */
    $rendererService = \Drupal::service('renderer');
    // phpcs:enable

    $parameters = new MenuTreeParameters();
    $parameters->setMaxDepth(1)->onlyEnabledLinks();
    $tree = $menuLinkTreeService->load('devel', $parameters);

    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $menuLinkTreeService->transform($tree, $manipulators);

    $links = [];
    foreach ($tree as $item) {
      /** @var \Drupal\devel\Plugin\Menu\DestinationMenuLink $item_link */
      $item_link = $item->link;

      // Get the link url and replace the destination parameter with the
      // original route.
      $url = $item_link->getUrlObject();
      $url->setOption('query', ['destination' => $destination]);

      // Build and render the link.
      $link = Link::fromTextAndUrl($item_link->getTitle(), $url);
      $renderable = $link->toRenderable();
      $rendered = $rendererService->renderPlain($renderable);

      $links[] = Markup::create($rendered);
    }

    return $links;
  }

}
