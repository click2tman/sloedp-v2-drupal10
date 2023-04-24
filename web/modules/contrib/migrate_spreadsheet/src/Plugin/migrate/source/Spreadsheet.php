<?php

declare(strict_types = 1);

namespace Drupal\migrate_spreadsheet\Plugin\migrate\source;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate_spreadsheet\SpreadsheetIterator;
use Drupal\migrate_spreadsheet\SpreadsheetIteratorInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a source plugin that migrate from spreadsheet files.
 *
 * This source plugin uses the PhpOffice/PhpSpreadsheet library to read
 * spreadsheet files.
 *
 * @MigrateSource(
 *   id = "spreadsheet",
 * )
 */
class Spreadsheet extends SourcePluginBase implements ConfigurableInterface, DependentPluginInterface, ContainerFactoryPluginInterface {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The migrate spreadsheet iterator.
   *
   * @var \Drupal\migrate_spreadsheet\SpreadsheetIteratorInterface
   */
  protected $spreadsheetIterator;

  /**
   * Flag to determine if the iterator has been initialized.
   *
   * @var bool
   */
  protected $iteratorIsInitialized = FALSE;

  /**
   * Constructs a spreadsheet migration source plugin object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The current migration.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\migrate_spreadsheet\SpreadsheetIteratorInterface $spreadsheet_iterator
   *   The migrate spreadsheet iterator.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, FileSystemInterface $file_system, SpreadsheetIteratorInterface $spreadsheet_iterator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
    $this->setConfiguration($configuration);
    $this->fileSystem = $file_system;
    $this->spreadsheetIterator = $spreadsheet_iterator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('file_system'),
      new SpreadsheetIterator()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'file' => NULL,
      'worksheet' => NULL,
      'origin' => 'A2',
      'header_row' => NULL,
      'columns' => [],
      'keys' => [],
      'row_index_column' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = NestedArray::mergeDeep(
      $this->defaultConfiguration(),
      $configuration
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function __toString(): string {
    return $this->configuration['file'] . ':' . $this->configuration['worksheet'];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds(): array {
    $config = $this->getConfiguration();

    if (empty($config['keys'])) {
      if (empty($config['row_index_column'])) {
        throw new \RuntimeException("Row index should act as key but no name has been provided. Set 'row_index_column' in source config to provide a name for this column.");
      }
      // If no keys are defined, we'll use the 'zero based' index of the
      // spreadsheet current row.
      return [$config['row_index_column'] => ['type' => 'integer']];
    }

    return $config['keys'];
  }

  /**
   * {@inheritdoc}
   */
  public function fields(): array {
    // No column headers provided in config, read worksheet for header row.
    if (!$columns = $this->getConfiguration()['columns']) {
      $this->initializeIterator();
      $columns = array_keys($this->spreadsheetIterator->getHeaders());
    }
    // Add $row_index_column if it's been configured.
    if ($row_index_column = $this->getConfiguration()['row_index_column']) {
      $columns[] = $row_index_column;
    }
    return array_combine($columns, $columns);
  }

  /**
   * {@inheritdoc}
   */
  public function initializeIterator(): SpreadsheetIteratorInterface {
    if (!$this->iteratorIsInitialized) {
      $configuration = $this->getConfiguration();
      $configuration['worksheet'] = $this->loadWorksheet();
      $configuration['keys'] = array_keys($configuration['keys']);

      // The 'file' and 'plugin' items are not part of iterator configuration.
      unset($configuration['file'], $configuration['plugin']);
      $this->spreadsheetIterator->setConfiguration($configuration);

      // Flag that the iterator has been initialized.
      $this->iteratorIsInitialized = TRUE;
    }

    return $this->spreadsheetIterator;
  }

  /**
   * Loads the worksheet.
   *
   * @return \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
   *   The source worksheet.
   *
   * @throws \Drupal\migrate\MigrateException
   *   When it's impossible to load the file or the worksheet does not exist.
   */
  protected function loadWorksheet(): Worksheet {
    $config = $this->getConfiguration();

    // Check that the file exists.
    if (!file_exists($config['file'])) {
      throw new MigrateException("File with path '{$config['file']}' doesn't exist.");
    }

    // Check that a non-empty worksheet has been passed.
    if (empty($config['worksheet'])) {
      throw new MigrateException('No worksheet was passed.');
    }

    // Load the workbook.
    try {
      $file_path = $this->fileSystem->realpath($config['file']);

      // Identify the type of the input file.
      $type = IOFactory::identify($file_path);

      // Create a new Reader of the file type.
      $reader = IOFactory::createReader($type);

      // @see https://github.com/PHPOffice/PhpSpreadsheet/pull/2623
      if ($reader instanceof Csv) {
        $reader->setTestAutoDetect(FALSE);
      }

      // Advise the Reader that we only want to load cell data.
      $reader->setReadDataOnly(TRUE);

      // Advise the Reader of which worksheet we want to load.
      $reader->setLoadSheetsOnly($config['worksheet']);

      $workbook = $reader->load($file_path);

      return $workbook->getSheet(0);
    }
    catch (\Exception $e) {
      $class = get_class($e);
      throw new MigrateException("Got '$class', message '{$e->getMessage()}'.");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    return [];
  }

}
