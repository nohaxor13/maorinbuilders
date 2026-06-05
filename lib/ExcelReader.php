<?php
namespace PurchasesIO;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExcelReader {
  private array $mapping; // normalized header (lower) → db column
  private ?Worksheet $sheet = null;

  public function __construct(array $mapping) {
    // Normalize keys to lower
    $norm = [];
    foreach ($mapping as $k => $v) { $norm[strtolower(trim($k))] = $v; }
    $this->mapping = $norm;
  }

  public function load(string $filepath, ?string $sheetName = null): void {
    $reader = IOFactory::createReaderForFile($filepath);
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($filepath);
    $this->sheet = $sheetName ? $spreadsheet->getSheetByName($sheetName)
                              : $spreadsheet->getSheet(0);
  }

  /** Find header row by scanning top N rows for any known header tokens. */
  private function findHeaderRow(int $scanRows = 40): array {
    $ws = $this->sheet;
    if (!$ws) throw new \RuntimeException("Sheet not loaded");
    $highestCol = $ws->getHighestColumn();
    $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);

    for ($r = 1; $r <= $scanRows; $r++) {
      $rowVals = [];
      for ($c = 1; $c <= $highestColIndex; $c++) {
        $val = (string)($ws->getCellByColumnAndRow($c, $r)->getValue());
        $rowVals[$c] = strtolower(trim(preg_replace('/\s+/', ' ', $val)));
      }
      // Score row by how many mapping keys it contains
      $score = 0;
      foreach ($rowVals as $v) {
        if ($v && isset($this->mapping[$v])) $score++;
      }
      if ($score >= 4) { // likely header
        return [$r, $rowVals];
      }
    }
    throw new \RuntimeException("Header row not found (top {$scanRows} rows).");
  }

  public function extractRows(): array {
    [$headerRow, $normalizedHeaders] = $this->findHeaderRow();
    $ws = $this->sheet;
    $highestRow = $ws->getHighestRow();
    $highestCol = $ws->getHighestColumn();
    $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);

    // Build column index → db column
    $colMap = [];
    for ($c = 1; $c <= $highestColIndex; $c++) {
      $raw = (string)$ws->getCellByColumnAndRow($c, $headerRow)->getValue();
      $norm = strtolower(trim(preg_replace('/\s+/', ' ', $raw)));
      if (isset($this->mapping[$norm])) {
        $colMap[$c] = $this->mapping[$norm];
      }
    }

    $rows = [];
    for ($r = $headerRow + 1; $r <= $highestRow; $r++) {
      $row = [];
      $nonEmpty = false;

      foreach ($colMap as $c => $dbCol) {
        $cell = $ws->getCellByColumnAndRow($c, $r);
        $v = $cell->getValue();

        // Convert Excel dates to PHP Y-m-d
        if ($dbCol === 'date' && $v !== null && $v !== '') {
          if (is_numeric($v)) {
            $ts = ExcelDate::excelToTimestamp($v);
            $v = date('Y-m-d', $ts);
          } else {
            $t = strtotime($v);
            $v = $t ? date('Y-m-d', $t) : null;
          }
        }

        // Trim strings; convert empties to null
        if (is_string($v)) $v = trim($v);
        if ($v === '' || $v === null) $v = null;

        // Numeric normalization
        if (in_array($dbCol, ['input_vat','vatable','non_vat','freight_handling','cash','debit','credit'], true)) {
          if ($v !== null) $v = (float)str_replace([',',' '], '', (string)$v);
        }

        $row[$dbCol] = $v;
        if ($v !== null && $v !== '') $nonEmpty = true;
      }

      if ($nonEmpty) $rows[] = $row;
    }

    return $rows;
  }
}
