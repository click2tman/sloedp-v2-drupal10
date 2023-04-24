<?php

declare(strict_types = 1);

namespace Drupal\Tests\migrate_spreadsheet\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\Tests\migrate\Kernel\MigrateTestBase;

/**
 * Tests the 'spreadsheet' migrate source plugin.
 *
 * @coversDefaultClass \Drupal\migrate_spreadsheet\Plugin\migrate\source\Spreadsheet
 * @group migrate_spreadsheet
 */
class MigrateSpreadsheetTest extends MigrateTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'migrate',
    'migrate_spreadsheet',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('entity_test');
  }

  /**
   * @covers ::initializeIterator
   * @dataProvider pluginTestProvider
   *
   * @param array $configuration
   *   The migrate plugin configuration.
   */
  public function testPlugin(array $configuration): void {
    $migration = $this->container->get('plugin.manager.migration')
      ->createStubMigration($configuration);
    $this->executeMigration($migration);

    $this->assertSame('Foo', EntityTest::load(2)->label());
    $this->assertSame('Bar', EntityTest::load(6)->label());
    $this->assertSame('Baz', EntityTest::load(34)->label());
    $this->assertSame('Qux', EntityTest::load(999)->label());
  }

  /**
   * Provides testing cases for ::testPlugin() test.
   *
   * @return \string[][]
   *   Test cases.
   */
  public function pluginTestProvider(): array {
    $configuration = [
      'source' => [
        'plugin' => 'spreadsheet',
        'worksheet' => 'Sheet1',
        'origin' => 'B3',
        'header_row' => 2,
        'columns' => [
          'ID',
          'Name',
        ],
        'keys' => [
          'ID' => [
            'type' => 'integer',
            'size' => 'big',
          ],
        ],
      ],
      'process' => [
        'id' => 'ID',
        'name' => 'Name',
      ],
      'destination' => [
        'plugin' => 'entity:entity_test',
      ],
    ];

    $files = [
      'Open Document Format/OASIS (.ods)' => 'test.ods',
      'Office Open XML (.xlsx) Excel 2007 and above' => 'test.xlsx',
      'BIFF 8 (.xls) Excel 97 and above' => 'test.xls',
      'BIFF 5 (.xls) Excel 95' => 'test_excel5.xls',
      'SpreadsheetML (.xml) Excel 2003' => 'test.xml',
      'SYLK' => 'test.slk',
      'CSV' => 'test.csv',
    ];

    return array_map(function (string $filename) use ($configuration): array {
      $configuration['source']['file'] = __DIR__ . "/../../fixtures/{$filename}";
      return [$configuration];
    }, $files);
  }

}
