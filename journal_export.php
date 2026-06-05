<?php
// PATH: /MaorinBuilders/journal_export.php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/helpers.php';
redirect_if_not_logged_in();
require_admin($pdo);

/* ---------- Autoload PhpSpreadsheet ---------- */
$autoloadCandidates = [
  __DIR__ . '/vendor/autoload.php',
  __DIR__ . '/../vendor/autoload.php',
  dirname(__DIR__) . '/vendor/autoload.php',
];
$autoloadOk = false;
foreach ($autoloadCandidates as $cand) {
  if (is_file($cand)) { require_once $cand; $autoloadOk = true; break; }
}
if (!$autoloadOk) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo "PhpSpreadsheet autoloader not found. Run: composer require phpoffice/phpspreadsheet";
  exit;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date as XLDate;

/* ---------- Read filters (PUBLIC scope) ---------- */
$search   = trim((string)($_GET['search'] ?? ''));
$sort     = (string)($_GET['sort'] ?? 'date_desc');

// Month-based export (preferred)
$month = isset($_GET['month']) ? (int)$_GET['month'] : 0; // 1-12
$year  = isset($_GET['year'])  ? (int)$_GET['year']  : 0; // 2000+

// Date-range export (fallback)
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo   = trim((string)($_GET['date_to'] ?? ''));

// If month/year provided, derive exact range (enforced)
if ($month >= 1 && $month <= 12 && $year >= 2000 && $year <= 2100) {
  $dateFrom = sprintf('%04d-%02d-01', $year, $month);
  $dateTo   = date('Y-m-t', strtotime($dateFrom)); // last day of that month
}

// Validate date strings (only for fallback / safety)
$reDate = '/^\d{4}-\d{2}-\d{2}$/';
if ($dateFrom !== '' && !preg_match($reDate, $dateFrom)) $dateFrom = '';
if ($dateTo   !== '' && !preg_match($reDate, $dateTo))   $dateTo   = '';

$sortMap = [
  'date_desc'     => 'date DESC, id DESC',
  'date_asc'      => 'date ASC, id ASC',
  'supplier_asc'  => 'supplier ASC, date DESC',
  'supplier_desc' => 'supplier DESC, date DESC',
  'total_desc'    => 'total DESC, date DESC',
  'total_asc'     => 'total ASC, date DESC',
];
$orderBy = $sortMap[$sort] ?? $sortMap['date_desc'];

/* ---------- Build WHERE (no user_id filter) ---------- */
$where  = '1=1';
$params = [];

if ($search !== '') {
  $where .= ' AND (supplier LIKE :s OR description LIKE :s OR remarks LIKE :s
                   OR category LIKE :s OR project_name LIKE :s OR reference LIKE :s
                   OR address LIKE :s OR tin LIKE :s OR vat_nvat LIKE :s
                   OR account_title LIKE :s)';
  $params[':s'] = '%'.$search.'%';
}

if ($dateFrom !== '' && $dateTo !== '') {
  $where .= ' AND date BETWEEN :df AND :dt';
  $params[':df'] = $dateFrom;
  $params[':dt'] = $dateTo;
} elseif ($dateFrom !== '') {
  $where .= ' AND date >= :df';
  $params[':df'] = $dateFrom;
} elseif ($dateTo !== '') {
  $where .= ' AND date <= :dt';
  $params[':dt'] = $dateTo;
}

/* ---------- Fetch data ---------- */
$sql = "SELECT id, date, supplier, ref_page, tin, vat_nvat, address, category, description,
               project_name, reference, input_vat, vatable, non_vat, total, freight_handling, cash,
               account_title, debit, credit, remarks
        FROM purchase_entries
        WHERE $where
        ORDER BY $orderBy";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------- Determine month/year naming ---------- */
$baseDate = time();
if ($dateFrom) $baseDate = strtotime($dateFrom);
elseif ($dateTo) $baseDate = strtotime($dateTo);
elseif (!empty($rows[0]['date'])) $baseDate = strtotime((string)$rows[0]['date']);

$monthName = date('F', $baseDate);
$yearName  = date('Y', $baseDate);

/* ---------- Build spreadsheet (template-based) ---------- */
$templatePath = __DIR__ . '/templates/PURCHASE_JOURNAL_TEMPLATE.xlsx';

if (is_file($templatePath)) {
  $spreadsheet = IOFactory::load($templatePath);
  $ws = $spreadsheet->getSheet(0);
} else {
  // Fallback (still exports data)
  $spreadsheet = new Spreadsheet();
  $spreadsheet->getDefaultStyle()->getFont()->setName('Times New Roman')->setSize(10);
  $ws = $spreadsheet->getActiveSheet();
  $ws->setCellValue('B6','Date')->setCellValue('C6','Supplier')->setCellValue('D6','Ref. Page')
     ->setCellValue('F6','TIN')->setCellValue('G6','VAT/ NVAT')->setCellValue('H6','Address')
     ->setCellValue('I6','Category')->setCellValue('J6','Description')->setCellValue('L6','Project Name')
     ->setCellValue('M6','Reference')->setCellValue('P6','Input VAT')->setCellValue('Q6','VATable')
     ->setCellValue('R6','NonVAT')->setCellValue('S6','Total')->setCellValue('T6','Freight & Handling')
     ->setCellValue('V6','Accounts Payable')->setCellValue('W6','Cash')->setCellValue('X6','Account Title')
     ->setCellValue('Y6','Debit')->setCellValue('Z6','Credit')->setCellValue('AA6','Remarks');
}

$ws->setTitle($monthName);

/* ---------- Locate TOTAL row (template has "TOTAL" in column N) ---------- */
$dataStartRow = 10;
$totalRow = null;

$maxScan = max(250, (int)$ws->getHighestRow());
for ($r = 1; $r <= $maxScan; $r++) {
  $v = (string)$ws->getCell('N' . $r)->getValue();
  if (trim($v) === 'TOTAL') { $totalRow = $r; break; }
}
if ($totalRow === null) {
  $totalRow = $dataStartRow + max(1, count($rows));
  $ws->setCellValue('N' . $totalRow, 'TOTAL');
}

/* ---------- Ensure TOTAL is directly after data block ---------- */
$existingDataRows = max(0, ($totalRow - $dataStartRow));
$needDataRows = count($rows);

if ($needDataRows > $existingDataRows) {
  $ws->insertNewRowBefore($totalRow, $needDataRows - $existingDataRows);
  $totalRow += ($needDataRows - $existingDataRows);
} elseif ($needDataRows < $existingDataRows) {
  $removeFrom  = $dataStartRow + $needDataRows;
  $removeCount = $existingDataRows - $needDataRows;
  if ($removeCount > 0) {
    $ws->removeRow($removeFrom, $removeCount);
    $totalRow -= $removeCount;
  }
}

/* ---------- Clear data area (keeps formatting) ---------- */
/* ✅ Clear with NULL (not TYPE_STRING) to avoid Excel repair popup */
$displayRows = max(1, $needDataRows);
for ($r = $dataStartRow; $r <= ($dataStartRow + $displayRows - 1); $r++) {
  foreach (range('B','Z') as $col) {
    $ws->setCellValue($col.$r, null);
  }
  $ws->setCellValue('AA'.$r, null);
}

/* ---------- Write rows (DB → template columns) ---------- */
$rowNum = $dataStartRow;

foreach ($rows as $r) {
  // Date (B) as true Excel date
  $d = (string)($r['date'] ?? '');
  if ($d !== '') {
    $dt = \DateTime::createFromFormat('Y-m-d', $d) ?: new \DateTime($d);
    $ws->setCellValue('B'.$rowNum, XLDate::PHPToExcel($dt));
    $ws->getStyle('B'.$rowNum)->getNumberFormat()->setFormatCode('[$-809]dd\\ mmmm\\ yyyy;@');
  }

  $ws->setCellValue('C'.$rowNum, (string)($r['supplier'] ?? ''));
  $ws->setCellValue('D'.$rowNum, (string)($r['ref_page'] ?? ''));
  $ws->setCellValue('F'.$rowNum, (string)($r['tin'] ?? ''));
  $ws->setCellValue('G'.$rowNum, (string)($r['vat_nvat'] ?? ''));
  $ws->setCellValue('H'.$rowNum, (string)($r['address'] ?? ''));
  $ws->setCellValue('I'.$rowNum, (string)($r['category'] ?? ''));
  $ws->setCellValue('J'.$rowNum, (string)($r['description'] ?? ''));

  $ws->setCellValue('L'.$rowNum, (string)($r['project_name'] ?? ''));
  $ws->setCellValue('M'.$rowNum, (string)($r['reference'] ?? ''));

  $inputVat = (float)($r['input_vat'] ?? 0);
  $vatable  = (float)($r['vatable'] ?? 0);
  $nonVat   = (float)($r['non_vat'] ?? 0);
  $total    = (float)($r['total'] ?? 0);
  $freight  = (float)($r['freight_handling'] ?? 0);
  $cash     = (float)($r['cash'] ?? 0);

  $ws->setCellValue('P'.$rowNum, $inputVat);
  $ws->setCellValue('Q'.$rowNum, $vatable);
  $ws->setCellValue('R'.$rowNum, $nonVat);
  $ws->setCellValue('S'.$rowNum, $vatable + $nonVat);
  $ws->setCellValue('T'.$rowNum, $freight);

  if ($total <= 0) $total = ($inputVat + $vatable + $nonVat);
  $ap = max(0, $total - $cash);
  $ws->setCellValue('V'.$rowNum, $ap);
  $ws->setCellValue('W'.$rowNum, $cash);

  $ws->setCellValue('X'.$rowNum, (string)($r['account_title'] ?? ''));
  $ws->setCellValue('Y'.$rowNum, (float)($r['debit'] ?? 0));
  $ws->setCellValue('Z'.$rowNum, (float)($r['credit'] ?? 0));
  $ws->setCellValue('AA'.$rowNum,(string)($r['remarks'] ?? ''));

  $rowNum++;
}

/* ---------- Wrap text + auto row height for text columns ---------- */
/* ✅ Ensures exported rows behave like the template (wrap text) */
$lastDataRow = ($totalRow > $dataStartRow) ? ($totalRow - 1) : $dataStartRow;

// Columns that should wrap (edit if your template differs)
// H=Address, I=Category, J=Description, L=Project Name, X=Account Title, AA=Remarks
$wrapCols = ['H','I','J','L','X','AA'];

foreach ($wrapCols as $col) {
  $ws->getStyle("{$col}{$dataStartRow}:{$col}{$lastDataRow}")
     ->getAlignment()
     ->setWrapText(true);
}

// Auto-fit row height so the wrapped text is visible
for ($r = $dataStartRow; $r <= $lastDataRow; $r++) {
  $ws->getRowDimension($r)->setRowHeight(-1);
}

/* ---------- Update TOTAL formulas ---------- */
/* ✅ Remove U (often merged / invalid in template) */
$sumCols = ['P','Q','R','S','T','V','W','Y','Z'];

foreach ($sumCols as $col) {
  $ws->setCellValue($col.$totalRow, "=SUM({$col}{$dataStartRow}:{$col}{$lastDataRow})");
}

/* ---------- Output ---------- */
/* ✅ Includes YEAR in filename */
$downloadName = "PURCHASE JOURNAL {$monthName} {$yearName}.xlsx";

try {
  @ini_set('zlib.output_compression', '0');
  if (function_exists('apache_setenv')) { @apache_setenv('no-gzip', '1'); }

  while (ob_get_level() > 0) { ob_end_clean(); }
  if (ob_get_length()) { @ob_end_clean(); }

  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment; filename="' . $downloadName . '"');
  header('Content-Transfer-Encoding: binary');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
  header('Expires: 0');

  $writer = new Xlsx($spreadsheet);
  $writer->save('php://output');
  exit;

} catch (Throwable $e) {
  while (ob_get_level() > 0) { ob_end_clean(); }
  http_response_code(500);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
  exit;
}
