<?php

declare(strict_types=1);

namespace Drupal\migrate_spreadsheet;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Provides an interface for spreadsheet iterators.
 */
interface SpreadsheetIteratorInterface extends \Iterator {

  /**
   * Sets the iterator configuration.
   *
   * The caller should assure sane values.
   *
   * @param array $configuration
   *   An associative array with the next keys:
   *   - worksheet (\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet): The
   *     worksheet object.
   *   - columns (string[]): An indexed array of columns.
   *   - keys (string[]): A list of columns that are giving the primary key.
   *   - header_row (int): The index of the first row from where the table
   *     starts. It's the value of the spreadsheet row that contains the table
   *     header. If the table row is the first this should be 1. A value of 3
   *     would mean that the table header is on the third row.
   *   - row_index_column (string): The row index column name. The 'row index
   *     column' is a pseudo-column, that not exist on the worksheet, containing
   *     the current index/position/delta of each row. The caller can pass a
   *     name to be used for that column. If a name was passed, that column will
   *     be also outputted along with the row, in ::current(). The same name can
   *     be passed also in 'keys' list. In that case the row index will be or
   *     will be part of the primary key.
   *
   * @return $this
   */
  public function setConfiguration(array $configuration): self;

  /**
   * Gets the iterator configuration.
   *
   * @return array
   *   The iterator configuration as an associative array.
   */
  public function getConfiguration(): array;

  /**
   * Gets the worksheet.
   *
   * @return \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
   *   The PHP Office worksheet object.
   *
   * @throws \InvalidArgumentException
   *   If an empty or invalid 'worksheet' has been passed.
   */
  public function getWorksheet(): Worksheet;

  /**
   * Retrieves the top-left origin of data area.
   *
   * @return string
   *   The top-left cell of data area (such as A2 or B5).
   */
  public function getOrigin(): string;

  /**
   * Gets the list of columns.
   *
   * @return string[]
   *   The list of columns.
   *
   * @throws \InvalidArgumentException
   *   If a column passed in 'columns' does not exist in the header.
   *
   * @see \Drupal\migrate_spreadsheet\SpreadsheetIteratorInterface::setColumns()
   */
  public function getColumns(): array;

  /**
   * Gets the list of columns that are composing the primary key.
   *
   * @return string[]
   *   A list of column names.
   *
   * @throws \InvalidArgumentException
   *   If a key passed in 'keys' does not exist in the header.
   */
  public function getKeys(): array;

  /**
   * Gets the header row index.
   *
   * @return int|null
   *   The header row index.
   */
  public function getHeaderRow(): ?int;

  /**
   * Gets the name of the row index column.
   *
   * @return string|null
   *   A name to be given to the column returning the numeric index of the row.
   */
  public function getRowIndexColumn(): ?string;

  /**
   * Retrieves a full list of headers.
   *
   * @return string[]
   *   An associative array having the header name as key and header column
   *   index as value. If there is no header row defined, the key is the same as
   *   the value. The column index has a letter representation (A, B, C, ...).
   *
   * @throws \LogicException
   *   If a header cell is duplicated.
   */
  public function getHeaders(): array;

  /**
   * Gets the total number of rows in the worksheet.
   *
   * @return int
   *   The total number of rows in the worksheet.
   */
  public function getRowsCount(): int;

  /**
   * Gets the total number of columns in the worksheet.
   *
   * @return int
   *   The total number of columns in the worksheet.
   */
  public function getColumnsCount(): int;

  /**
   * Clears the iterator internal, static cache.
   */
  public function clearCache(): void;

}
