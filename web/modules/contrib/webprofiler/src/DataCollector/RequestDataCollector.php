<?php

declare(strict_types=1);

namespace Drupal\webprofiler\DataCollector;

use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\RequestDataCollector as BaseRequestDataCollector;

/**
 * Collects HTTP requests data.
 *
 * @phpstan-ignore-next-line
 */
class RequestDataCollector extends BaseRequestDataCollector implements HasPanelInterface {

  use DataCollectorTrait;
  use PanelTrait;

  public const SERVICE_ID = 'service_id';

  public const CALLABLE = 'callable';

  /**
   * The Controller resolver service.
   *
   * @var \Symfony\Component\HttpKernel\Controller\ControllerResolverInterface
   */
  private ControllerResolverInterface $controllerResolver;

  /**
   * The list of access checks applied to this request.
   *
   * @var array
   */
  private array $accessChecks;

  /**
   * RequestDataCollector constructor.
   *
   * @param \Symfony\Component\HttpKernel\Controller\ControllerResolverInterface $controllerResolver
   *   The Controller resolver service.
   */
  public function __construct(ControllerResolverInterface $controllerResolver) {
    parent::__construct();

    $this->controllerResolver = $controllerResolver;
    $this->accessChecks = [];
  }

  /**
   * {@inheritdoc}
   */
  public function collect(Request $request, Response $response, \Throwable $exception = NULL) {
    parent::collect($request, $response);

    $this->data['big_pipe'] = $response->headers->get('X-Drupal-BigPipe-Placeholder');

    if ($controller = $this->controllerResolver->getController($request)) {
      $this->data['controller'] = $this->getMethodData(
        $controller[0], $controller[1]
      ) ?? 'no controller';
      $this->data['access_checks'] = $this->accessChecks;
    }

    unset($this->data['request_attributes']['_route_params']);
  }

  /**
   * {@inheritdoc}
   */
  public function getPanel(): array {
    return array_merge(
      $this->renderBigPipe($this->data['big_pipe']),
      $this->renderTable(
        $this->getRequestQuery()->all(), 'GET parameters'),
      $this->renderTable(
        $this->getRequestRequest()->all(), 'POST parameters'),
      $this->renderTable(
        $this->getRequestAttributes()->all(), 'Request attributes'),
      $this->renderAccessChecks(
        $this->getAccessChecks()->all(), 'Access check'),
      $this->renderTable(
        $this->getRequestCookies()->all(), 'Cookies'),
      $this->renderTable(
        $this->getSessionMetadata(), 'Session Metadata'),
      $this->renderTable(
        $this->getSessionAttributes(), 'Session Attributes'),
      $this->renderTable(
        $this->getRequestHeaders()->all(), 'Request headers'),
      $this->renderContent(
        $this->getContent(), 'Raw content'),
      $this->renderTable(
        $this->getRequestServer()->all(), 'Server Parameters'),
      $this->renderTable(
        $this->getResponseHeaders()->all(), 'Response headers')
    );
  }

  /**
   * Save an access check.
   *
   * @param string $service_id
   *   The service id of the service implementing the access check.
   * @param array $callable
   *   The callable that implement the access check.
   */
  public function addAccessCheck(
    string $service_id,
    array $callable
  ) {
    $this->accessChecks[] = [
      self::SERVICE_ID => $service_id,
      self::CALLABLE => $this->getMethodData($callable[0], $callable[1]),
    ];
  }

  /**
   * Return the list of access checks as ParameterBag.
   *
   * @return \Symfony\Component\HttpFoundation\ParameterBag
   *   The list of access checks.
   */
  public function getAccessChecks(): ParameterBag {
    return isset($this->data['access_checks']) ? new ParameterBag($this->data['access_checks']->getValue()) : new ParameterBag();
  }

  /**
   * Return the render array with BigPipe data.
   *
   * @param string|null $big_pipe
   *   The BigPipe placeholder.
   *
   * @return array
   *   The render array with BigPipe data.
   */
  private function renderBigPipe(?string $big_pipe): array {
    if ($big_pipe == NULL) {
      return [];
    }

    $parts = explode('&', substr($big_pipe, strlen('callback=')));
    $data = urldecode($parts[0]);

    return [
      '#type' => 'inline_template',
      '#template' => '<h3>BigPipe placeholder</h3>{{ data|raw }}',
      '#context' => [
        'data' => $data,
      ],
    ];
  }

  /**
   * Render the content of a POST request.
   *
   * @param string $content
   *   The content of a POST request.
   * @param string $label
   *   The section's label.
   *
   * @return array
   *   The render array of the content.
   */
  private function renderContent(string $content, string $label): array {
    return [
      $label => [
        '#type' => 'inline_template',
        '#template' => '<h3>{{ title }}</h3> {% if data %}{{ data|raw }}{% else %}<em>{{ "No data"|t }}</em>{% endif %}',
        '#context' => [
          'title' => $label,
          'data' => $content,
        ],
      ],
    ];
  }

  /**
   * Render the list of access checks.
   *
   * @param array $accessChecks
   *   The list of access checks.
   * @param string $label
   *   The section label.
   *
   * @return array
   *   The render array of the list of access checks.
   */
  private function renderAccessChecks(array $accessChecks, $label): array {
    if (count($accessChecks) == 0) {
      return [];
    }

    $rows = [];
    /** @var \Symfony\Component\VarDumper\Cloner\Data $el */
    foreach ($accessChecks as $el) {
      $service_id = $el->getValue()[RequestDataCollector::SERVICE_ID];
      $callable = $el->getValue()[RequestDataCollector::CALLABLE];

      $rows[] = [
        [
          'data' => $service_id->getValue(),
          'class' => 'webprofiler__key',
        ],
        [
          'data' => [
            '#type' => 'inline_template',
            '#template' => '{{ data|raw }}',
            '#context' => [
              'data' => $this->dumpData($callable),
            ],
          ],
          'class' => 'webprofiler__value',
        ],
      ];
    }

    return [
      $label => [
        '#theme' => 'webprofiler_dashboard_section',
        '#title' => $label,
        '#data' => [
          '#type' => 'table',
          '#header' => [$this->t('Name'), $this->t('Value')],
          '#rows' => $rows,
          '#attributes' => [
            'class' => [
              'webprofiler__table',
            ],
          ],
        ],
      ],
    ];
  }

}
