<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/accounting_journal_common.php';

redirect_if_not_logged_in();
require_permission($pdo, 'export_journal');
mb_ensure_accounting_journal_tables($pdo);

$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    echo 'PhpSpreadsheet autoloader not found.';
    exit;
}
require_once $autoload;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date as XLDate;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$type = mb_accounting_journal_type($_GET['type'] ?? 'general');
$config = mb_accounting_journal_config($type);
$columns = mb_accounting_journal_columns($type);
$labels = mb_accounting_journal_field_labels();

$search = trim((string)($_GET['search'] ?? ''));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));

$where = 'journal_type = :type';
$params = [':type' => $type];
if ($search !== '') {
    $where .= " AND (particulars LIKE :s OR client_name LIKE :s OR supplier LIKE :s OR tin LIKE :s OR address LIKE :s OR project_name LIKE :s OR description LIKE :s OR reference_no LIKE :s OR remarks LIKE :s)";
    $params[':s'] = '%' . $search . '%';
}
if ($dateFrom !== '') {
    $where .= ' AND entry_date >= :df';
    $params[':df'] = $dateFrom;
}
if ($dateTo !== '') {
    $where .= ' AND entry_date <= :dt';
    $params[':dt'] = $dateTo;
}

$stmt = $pdo->prepare("SELECT * FROM accounting_journal_entries WHERE $where ORDER BY entry_date ASC, id ASC");
foreach ($params as $key => $value) $stmt->bindValue($key, $value);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle(substr($config['label'], 0, 31));
$sheet->setCellValue('A1', 'MARINO T. ROJAS');
$sheet->setCellValue('A2', 'Block 9 Lot 1 Vinta St. Emiville Subd., Sasa, Davao City');
$sheet->setCellValue('A3', 'TIN: 123-983-333-000');
$sheet->setCellValue('A4', strtoupper($config['label']));

$headerRow = 6;
$col = 1;
foreach ($columns as $field) {
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . $headerRow, $labels[$field] ?? $field);
}
$sheet->getStyle('A' . $headerRow . ':' . Coordinate::stringFromColumnIndex(count($columns)) . $headerRow)->getFont()->setBold(true);
$sheet->freezePane('A7');

$rowNum = 7;
foreach ($rows as $row) {
    $col = 1;
    foreach ($columns as $field) {
        $value = $row[$field] ?? '';
        $cell = Coordinate::stringFromColumnIndex($col) . $rowNum;
        if ($field === 'entry_date' && $value !== '') {
            $dt = \DateTime::createFromFormat('Y-m-d', (string)$value) ?: new \DateTime((string)$value);
            $sheet->setCellValue($cell, XLDate::PHPToExcel($dt));
            $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('mm/dd/yyyy');
        } elseif (in_array($field, ['debit','credit','sundry_debit','sundry_credit'], true)) {
            $sheet->setCellValue($cell, (float)$value);
            $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.00');
        } else {
            $sheet->setCellValue($cell, (string)$value);
        }
        $col++;
    }
    $rowNum++;
}

foreach (range(1, count($columns)) as $i) {
    $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
}

$filename = strtolower(str_replace(' ', '_', $config['label'])) . '_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->setPreCalculateFormulas(false);
$writer->save('php://output');
