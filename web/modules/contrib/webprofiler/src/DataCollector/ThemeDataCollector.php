<?php

declare(strict_types=1);

namespace Drupal\webprofiler\DataCollector;

use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\webprofiler\Theme\ThemeNegotiatorWrapper;
use Twig\Markup;
use Twig\Profiler\Dumper\HtmlDumper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Twig\Profiler\Profile;

/**
 * Collects theme data.
 */
class ThemeDataCollector extends DataCollector implements HasPanelInterface, LateDataCollectorInterface {

  use StringTranslationTrait, DataCollectorTrait, PanelTrait;

  /**
   * Used to store twig computed data between method calls.
   *
   * @var array|null
   */
  private ?array $computed = NULL;

  /**
   * The twig profile.
   *
   * @var \Twig\Profiler\Profile|null
   */
  private ?Profile $profile = NULL;

  /**
   * ThemeDataCollector constructor.
   *
   * @param \Drupal\Core\Theme\ThemeManagerInterface $themeManager
   *   The theme manager.
   * @param \Drupal\Core\Theme\ThemeNegotiatorInterface $themeNegotiator
   *   The theme negotiator.
   * @param \Twig\Profiler\Profile $profile
   *   The twig profile.
   */
  public function __construct(
    private readonly ThemeManagerInterface $themeManager,
    private readonly ThemeNegotiatorInterface $themeNegotiator,
    Profile $profile
  ) {
    $this->profile = $profile;
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return 'theme';
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
  public function collect(Request $request, Response $response, \Throwable $exception = NULL) {
    $activeTheme = $this->themeManager->getActiveTheme();

    $this->data['activeTheme'] = [
      'name' => $activeTheme->getName(),
      'path' => $activeTheme->getPath(),
      'engine' => $activeTheme->getEngine(),
      'owner' => $activeTheme->getOwner(),
      'baseThemes' => $activeTheme->getBaseThemeExtensions(),
      'extension' => $activeTheme->getExtension(),
      'styleSheetsRemove' => $activeTheme->getLibrariesOverride(),
      'libraries' => $activeTheme->getLibraries(),
      'regions' => $activeTheme->getRegions(),
    ];

    if ($this->themeNegotiator instanceof ThemeNegotiatorWrapper) {
      $this->data['negotiator'] = [
        'class' => $this->getMethodData($this->themeNegotiator->getNegotiator(), 'determineActiveTheme'),
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function lateCollect(): void {
    $this->data['twig'] = serialize($this->profile);
  }

  /**
   * Return the active theme.
   *
   * @return array
   *   The active theme.
   */
  public function getActiveTheme(): array {
    return $this->data['activeTheme'];
  }

  /**
   * Return the theme negotiator.
   *
   * @return array
   *   The theme negotiator.
   */
  public function getThemeNegotiator(): array {
    return $this->data['negotiator'];
  }

  /**
   * Return the time spent by the twig rendering process, in seconds.
   *
   * @return float
   *   The time spent by the twig rendering process, in seconds.
   */
  public function getTime(): float {
    return $this->getProfile()->getDuration() * 1000;
  }

  /**
   * Return the number of twig templates rendered.
   *
   * @return int
   *   The number of twig templates rendered.
   */
  public function getTemplateCount(): int {
    return $this->getComputedData('template_count');
  }

  /**
   * Return the number of twig blocks rendered.
   *
   * @return int
   *   The number of twig blocks rendered.
   */
  public function getBlockCount(): int {
    return $this->getComputedData('block_count');
  }

  /**
   * Return the number of twig macros rendered.
   *
   * @return int
   *   The number of twig macros rendered.
   */
  public function getMacroCount(): int {
    return $this->getComputedData('macro_count');
  }

  /**
   * {@inheritdoc}
   */
  public function getPanel(): array {
    return [
      [
        '#type' => 'inline_template',
        '#template' => '{{ data|raw }}',
        '#context' => [
          'data' => $this->dumpData($this->cloneVar($this->data['activeTheme'])),
        ],
      ],
      [
        '#theme' => 'webprofiler_dashboard_section',
        '#title' => $this->t('Rendering Call Graph'),
        '#data' => [
          '#type' => 'inline_template',
          '#template' => '<div id="twig-dump">{{ data|raw }}</div>',
          '#context' => [
            'data' => (string) $this->getHtmlCallGraph(),
          ],
        ],
      ],
    ];
  }

  /**
   * Render the twig call graph.
   *
   * @return \Twig\Markup
   *   The twig call graph.
   */
  private function getHtmlCallGraph(): Markup {
    $dumper = new HtmlDumper();

    return new Markup($dumper->dump($this->getProfile()), 'UTF-8');
  }

  /**
   * Return the twig profile, deserialized from data, if needed.
   *
   * @return \Twig\Profiler\Profile
   *   The twig profile, deserialized from data, if needed.
   */
  private function getProfile(): Profile {
    return $this->profile ??= unserialize($this->data['twig'], ['allowed_classes' => ['\Twig\Profiler\Profile', Profile::class]]);
  }

  /**
   * Return a specific computed data.
   *
   * @param string $index
   *   The index of the data to return.
   *
   * @return mixed
   *   The computed data.
   */
  private function getComputedData(string $index): mixed {
    $this->computed ??= $this->computeData($this->getProfile());

    return $this->computed[$index];
  }

  /**
   * Compute the data from the twig profile.
   *
   * @param \Twig\Profiler\Profile $profile
   *   The twig profile.
   *
   * @return array
   *   The computed data.
   */
  private function computeData(Profile $profile): array {
    $data = [
      'template_count' => 0,
      'block_count' => 0,
      'macro_count' => 0,
    ];

    $templates = [];
    foreach ($profile as $p) {
      $d = $this->computeData($p);

      $data['template_count'] += ($p->isTemplate() ? 1 : 0) + $d['template_count'];
      $data['block_count'] += ($p->isBlock() ? 1 : 0) + $d['block_count'];
      $data['macro_count'] += ($p->isMacro() ? 1 : 0) + $d['macro_count'];

      if ($p->isTemplate()) {
        if (!isset($templates[$p->getTemplate()])) {
          $templates[$p->getTemplate()] = 1;
        }
        else {
          $templates[$p->getTemplate()]++;
        }
      }

      foreach ($d['templates'] as $template => $count) {
        if (!isset($templates[$template])) {
          $templates[$template] = $count;
        }
        else {
          $templates[$template] += $count;
        }
      }
    }
    $data['templates'] = $templates;

    return $data;
  }

}
