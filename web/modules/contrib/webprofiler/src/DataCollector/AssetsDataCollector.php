<?php

declare(strict_types=1);

namespace Drupal\webprofiler\DataCollector;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * Collects assets data.
 */
class AssetsDataCollector extends DataCollector implements HasPanelInterface {

  use StringTranslationTrait, DataCollectorTrait, PanelTrait;

  /**
   * AssetDataCollector constructor.
   *
   * @param string $root
   *   The app root.
   */
  public function __construct(private readonly string $root) {
    $this->data['js'] = [];
    $this->data['css'] = [];
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return 'assets';
  }

  /**
   * {@inheritdoc}
   */
  public function collect(Request $request, Response $response, \Throwable $exception = NULL) {
    $this->data['assets']['installation_path'] = $this->root . '/';
  }

  /**
   * Reset the collected data.
   */
  public function reset() {
    $this->data = [];
  }

  /**
   * Add a javascript asset to collected data.
   *
   * @param array $jsAsset
   *   A javascript asset.
   */
  public function addJsAsset(array $jsAsset) {
    $this->data['js'] = NestedArray::mergeDeepArray([
      $jsAsset,
      $this->data['js'],
    ]);
  }

  /**
   * Add a css asset to collected data.
   *
   * @param array $cssAsset
   *   A css asset.
   */
  public function addCssAsset(array $cssAsset) {
    $this->data['css'] = NestedArray::mergeDeepArray([
      $cssAsset,
      $this->data['css'],
    ]);
  }

  /**
   * Return the number of css files used in page.
   *
   * @return int
   *   The number of css files used in page.
   */
  public function getCssCount(): int {
    return count($this->data['css']);
  }

  /**
   * Return the number of javascript files used in page.
   *
   * @return int
   *   The number of javascript files used in page.
   */
  public function getJsCount(): int {
    return count($this->data['js']) - 1;
  }

  /**
   * {@inheritdoc}
   */
  public function getPanel(): array {
    return [
      '#theme' => 'webprofiler_dashboard_tabs',
      '#tabs' => [
        [
          'label' => $this->t('CSS'),
          'content' => $this->renderCss($this->data['css']),
        ],
        [
          'label' => $this->t('Settings'),
          'content' => $this->renderSettings($this->data['js'] ?? ['drupalSettings']),
        ],
        [
          'label' => $this->t('JS'),
          'content' => $this->renderJs($this->data['js']),
        ],
      ],
    ];
  }

  /**
   * Render a list of CSS files.
   *
   * @param array $data
   *   A list of CSS files.
   *
   * @return array
   *   The render array of the list of CSS files.
   */
  private function renderCss(array $data): array {
    return [
      '#theme' => 'webprofiler_dashboard_section',
      '#data' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Asset'),
          $this->t('Version'),
          $this->t('Type'),
          $this->t('Media'),
        ],
        '#rows' => array_map(function ($asset) {
          return [
            $asset['data'],
            $asset['version'],
            $asset['type'],
            $asset['media'],
          ];
        }, $data),
        '#attributes' => [
          'class' => [
            'webprofiler__table',
          ],
        ],
        '#sticky' => TRUE,
      ],
    ];
  }

  /**
   * Render the DrupalSettings array.
   *
   * @param array $settings
   *   The DrupalSettings array.
   *
   * @return array
   *   The render array of the DrupalSettings array.
   */
  private function renderSettings(array $settings): array {
    return [
      '#type' => 'inline_template',
      '#template' => '{{ data|raw }}',
      '#context' => [
        'data' => array_key_exists('drupalSettings', $settings) ? $this->dumpData($this->cloneVar($settings['drupalSettings'])) : 'n/a',
      ],
    ];
  }

  /**
   * Render a list of javascript files.
   *
   * @param array $data
   *   A list of javascript files.
   *
   * @return array
   *   The render array of the list of javascript files.
   */
  private function renderJs(array $data): array {
    return [
      '#theme' => 'webprofiler_dashboard_section',
      '#data' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Asset'),
          $this->t('Version'),
          $this->t('Type'),
          $this->t('Scope'),
        ],
        '#rows' => array_map(function ($asset) {
          return [
            $asset['data'],
            $asset['version'],
            $asset['type'],
            $asset['scope'],
          ];
        }, array_filter($data, function ($asset) {
          return $asset['type'] !== 'setting';
        })),
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
