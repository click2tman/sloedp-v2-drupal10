<?php

declare(strict_types = 1);

namespace Drupal\migrate_spreadsheet;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Provides a spreadsheet iterator.
 */
class SpreadsheetIterator implements SpreadsheetIteratorInterface {

  /**
   * The iterator configuration.
   *
   * @var array
   */
  protected $configuration = [];

  /**
   * The 'zero based' relative index of the current row.
   *
   * @var int
   */
  protected $relativeRow = 0;

  /**
   * The absolute row index of the current row.
   *
   * @var int
   */
  protected $absoluteRow;

  /**
   * Static cache for some of the computed values.
   *
   * @var array
   */
  protected $cache = [];

  /**
   * {@inheritdoc}
   */
  public function key(): array {
    if (empty($keys = $this->getKeys())) {
      // If no keys were passed, use the spreadsheet current row position.
      if (!$this->getRowIndexColumn()) {
        throw new \RuntimeException("Row index should act as key but no name has been provided. Use SpreadsheetIterator::setRowIndexColumn() to provide a name for this column.");
      }
      return [$this->getAbsoluteRowIndex()];
    }

    return array_values(array_map(
      function (string $col_letter) {
        $cell_reference = "$col_letter{$this->getAbsoluteRowIndex()}";
        if ($cell = $this->getWorksheet()->getCell($cell_reference, FALSE)) {
          return $cell->getValue();
        }
        $key = array_search($col_letter, $this->getKeys());
        throw new \RuntimeException("Key column '$key' contains a null value at $cell_reference.");
      },
      $keys
    ));
  }

  /**
   * {@inheritdoc}
   */
  public function valid(): bool {
    return ($this->relativeRow >= 0) && ($this->getAbsoluteRowIndex() <= $this->getRowsCount());
  }

  /**
   * {@inheritdoc}
   */
  public function rewind(): void {
    unset($this->absoluteRow);
    $this->relativeRow = 0;
  }

  /**
   * {@inheritdoc}
   */
  public function current(): array {
    $keys = $this->getKeys();
    $all_columns = $keys + $this->getColumns();

    if ($row_index_column = $this->getRowIndexColumn()) {
      // We set '@' here so that when it will be sorted, later, it will be the
      // first in the list. Ascii of '@' is lower than ascii of 'A'.
      $all_columns[$row_index_column] = '@';
    }
    elseif (empty($keys)) {
      throw new \InvalidArgumentException("Row index should act as key but no name has been provided. Pass a string in \$config['row_index_column'] key when setting the configuration in SpreadsheetIterator::setConfiguration(\$config), to provide a name for this column.");
    }

    // Arrange columns in their spreadsheet native order.
    asort($all_columns);

    return array_map(
      function (string $col_letter) {
        if ($col_letter === '@') {
          return $this->getAbsoluteRowIndex();
        }
        elseif ($cell = $this->getWorksheet()->getCell("$col_letter{$this->getAbsoluteRowIndex()}", FALSE)) {
          return $cell->getCalculatedValue();
        }
        // Fall back to NULL.
        return NULL;
      },
      $all_columns
    );
  }

  /**
   * {@inheritdoc}
   */
  public function next(): void {
    unset($this->absoluteRow);
    $this->relativeRow++;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): SpreadsheetIteratorInterface {
    // Unset cached values.
    $this->clearCache();
    $this->configuration = $configuration;
    return $this;
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
  public function getWorksheet(): Worksheet {
    if (!isset($this->cache['worksheet'])) {
      if (empty($this->configuration['worksheet']) || !$this->configuration['worksheet'] instanceof Worksheet) {
        throw new \InvalidArgumentException("No valid 'worksheet' configuration.");
      }
      $this->cache['worksheet'] = $this->configuration['worksheet'];
    }
    return $this->cache['worksheet'];
  }

  /**
   * {@inheritdoc}
   */
  public function getOrigin(): string {
    if (!isset($this->cache['origin'])) {
      $config = $this->getConfiguration();
      if (empty($config['origin'])) {
        // Defaulting to a table where the first row contains the header and
        // data starts on the second row, column A.
        return 'A2';
      }
      if ($coordinates = Coordinate::coordinateFromString($config['origin'])) {
        $row_count = $this->getRowsCount();
        $column_count = $this->getColumnsCount();
        if (($coordinates[1] > $row_count) || ((Coordinate::columnIndexFromString($coordinates[0])) > $column_count)) {
          $max = Coordinate::stringFromColumnIndex($column_count) . $row_count;
          throw new \InvalidArgumentException("Origin '{$config['origin']}' is out of bounds. Max value is '$max'.");
        }
      }
      $this->cache['origin'] = strtoupper($config['origin']);
    }
    return $this->cache['origin'];
  }

  /**
   * {@inheritdoc}
   */
  public function getColumns(): array {
    if (!isset($this->cache['columns'])) {
      $headers = $this->getHeaders();
      if (empty($this->configuration['columns'])) {
        // If no columns were passed, all columns will be used.
        $this->cache['columns'] = $headers;
      }
      else {
        $this->cache['columns'] = [];
        foreach ($this->configuration['columns'] as $column) {
          $column = trim($column);
          if (!isset($headers[$column])) {
            throw new \InvalidArgumentException("Column '$column' doesn't exist in the table header.");
          }
          $this->cache['columns'][$column] = $headers[$column];
        }
      }
    }
    return $this->cache['columns'];
  }

  /**
   * {@inheritdoc}
   */
  public function getKeys(): array {
    if (!isset($this->cache['keys'])) {
      $this->cache['keys'] = [];
      if (!empty($this->configuration['keys'])) {
        $headers = $this->getHeaders();
        foreach ($this->configuration['keys'] as $key) {
          if ($key != $this->getRowIndexColumn() && !isset($headers[$key])) {
            throw new \InvalidArgumentException("Key '$key' doesn't exist in the table header.");
          }
          $this->cache['keys'][$key] = $headers[$key];
        }
      }
    }
    return $this->cache['keys'];
  }

  /**
   * {@inheritdoc}
   */
  public function getHeaderRow(): ?int {
    if (!isset($this->cache['header_row'])) {
      $header_row = $this->configuration['header_row'] ?? NULL;
      if ($header_row !== NULL && (!is_numeric($this->configuration['header_row']) || ($this->configuration['header_row'] < 1))) {
        throw new \InvalidArgumentException("Wrong header_row value '{$this->configuration['header_row']}'.");
      }
      $this->cache['header_row'] = $header_row;
    }
    return $this->cache['header_row'];
  }

  /**
   * {@inheritdoc}
   */
  public function getRowIndexColumn(): ?string {
    $column = $this->configuration['row_index_column'] ?? NULL;
    if ($column) {
      $headers = $this->getHeaders();
      if (isset($headers[$column])) {
        throw new \InvalidArgumentException("The header column '$column' cannot be used as 'row_index_column'. Chose a value for 'row_index_column' that doesn't exist in header cells.");
      }
    }
    return $column;
  }

  /**
   * {@inheritdoc}
   */
  public function getHeaders(): array {
    if (!isset($this->cache['headers'])) {
      // Get the first column index (one based, A is 1).
      $first_col_index = Coordinate::columnIndexFromString(Coordinate::coordinateFromString($this->getOrigin())[0]);
      for ($col_index = $first_col_index; $col_index <= $this->getColumnsCount(); ++$col_index) {
        $col_letter = Coordinate::stringFromColumnIndex($col_index);
        if ($header_row = $this->getHeaderRow()) {
          $value = '';
          if ($cell = $this->getWorksheet()->getCell("$col_letter$header_row", FALSE)) {
            $value = trim((string) $cell->getValue());
            if (isset($this->cache['headers'][$value])) {
              throw new \LogicException("Table header '$value' is duplicated.");
            }
          }
        }
        else {
          $value = $col_letter;
        }
        if (!empty($value)) {
          // Only non-empty cells can act as header.
          $this->cache['headers'][$value] = $col_letter;
        }
      }
    }
    return $this->cache['headers'];
  }

  /**
   * {@inheritdoc}
   */
  public function getRowsCount(): int {
    if (!isset($this->cache['rows_count'])) {
      $this->cache['rows_count'] = $this->getWorksheet()->getHighestDataRow();
    }
    return $this->cache['rows_count'];
  }

  /**
   * {@inheritdoc}
   */
  public function getColumnsCount(): int {
    if (!isset($this->cache['columns_count'])) {
      $this->cache['columns_count'] = Coordinate::columnIndexFromString($this->getWorksheet()->getHighestDataColumn());
    }
    return $this->cache['columns_count'];
  }

  /**
   * {@inheritdoc}
   */
  public function clearCache(): void {
    $this->cache = [];
  }

  /**
   * Gets the absolute row index.
   *
   * @return int
   *   The absolute row index.
   *
   * @throws \PhpOffice\PhpSpreadsheet\Exception
   *   On wrong coordinate string.
   */
  protected function getAbsoluteRowIndex(): int {
    if (!isset($this->absoluteRow)) {
      $row = (int) Coordinate::coordinateFromString($this->getOrigin())[1];
      $this->absoluteRow = $row + $this->relativeRow;
    }
    return $this->absoluteRow;
  }

}
