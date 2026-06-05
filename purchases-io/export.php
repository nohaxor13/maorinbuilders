<?php
// /MaorinBuilders/purchases-io/export.php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';
redirect_if_not_logged_in();
require_admin($pdo);

use PurchasesIO\ExcelWriter;

require __DIR__ . '/vendor/autoload.php';

// Filters (optional): date_from, date_to, supplier
$dateFrom = $_GET['date_from'] ?? null;
$dateTo   = $_GET['date_to'] ?? null;
$supplier = $_GET['supplier'] ?? null;

$where = ["user_id = :uid"];
$args  = [':uid' => (int)($_SESSION['user_id'] ?? 0)];

if ($dateFrom) { $where[] = "date >= :df"; $args[':df'] = $dateFrom; }
if ($dateTo)   { $where[] = "date <= :dt"; $args[':dt'] = $dateTo; }
if ($supplier) { $where[] = "supplier LIKE :sup"; $args[':sup'] = "%{$supplier}%"; }

$sql = "SELECT date, supplier, ref_page, tin, vat_nvat, address, category, description,
               project_name, reference, input_vat, vatable, non_vat, freight_handling, cash,
               account_title, debit, credit, remarks
        FROM purchase_entries
        WHERE " . implode(' AND ', $where) . "
        ORDER BY date ASC, supplier ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Write file to temp and stream to browser
$fname = 'purchase_journal_' . date('Ymd_His') . '.xlsx';
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $fname;

ExcelWriter::write($rows, $tmp);

// Output headers
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$fname.'"');
header('Content-Length: ' . filesize($tmp));
readfile($tmp);
@unlink($tmp);
exit;
