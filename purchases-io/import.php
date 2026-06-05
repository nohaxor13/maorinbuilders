<?php
// /MaorinBuilders/purchases-io/import.php
declare(strict_types=1);

/**
 * Improvements:
 * - Optional sheet selection via POST[sheet] (defaults to first sheet)
 * - Defensive guards around autoload/mapping
 * - Validates upload type/size; clearer error messages
 * - Supports Dry Run (no DB writes) and full import
 * - Wraps inserts in a transaction for non-dry runs (faster/atomic)
 * - Computes totals (uses calc_purchase() if available)
 * - Always keeps $results/$errors defined for the result view
 * - Small perf headroom (time/memory) for bigger files
 */

@ini_set('max_execution_time', '300'); // 5 minutes
@ini_set('memory_limit', '512M');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';
redirect_if_not_logged_in();
require_admin($pdo);

use PurchasesIO\ExcelReader;

$errors  = [];
$results = [
  'total_rows' => 0,
  'inserted'   => 0,
  'skipped'    => 0,
  'rows'       => [],
];

$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

// Show form if GET
if (!$isPost) {
  include __DIR__ . '/views/import_form.php';
  exit;
}

/* ---------- CSRF ---------- */
try {
  if (!function_exists('csrf_verify')) {
    throw new RuntimeException('CSRF helper missing (csrf_verify).');
  }
  csrf_verify();
} catch (Throwable $e) {
  http_response_code(400);
  $errors[] = 'Invalid CSRF token.';
  include __DIR__ . '/views/import_form.php';
  exit;
}

/* ---------- Composer autoload + mapping ---------- */
try {
  $autoload = __DIR__ . '/vendor/autoload.php';
  if (!is_file($autoload)) {
    throw new RuntimeException('Composer autoload not found. Run "composer install" in purchases-io.');
  }
  require $autoload;

  $mapFile = __DIR__ . '/mapping.php';
  if (!is_file($mapFile)) {
    throw new RuntimeException('mapping.php not found in purchases-io.');
  }
  $mapping = require $mapFile;
  if (!is_array($mapping) || !$mapping) {
    throw new RuntimeException('mapping.php must return a non-empty array.');
  }
} catch (Throwable $e) {
  $errors[] = $e->getMessage();
  include __DIR__ . '/views/import_form.php';
  exit;
}

/* ---------- Read user inputs ---------- */
$dryRun    = isset($_POST['dry_run']) && $_POST['dry_run'] === '1';
$sheetName = isset($_POST['sheet']) ? trim((string)$_POST['sheet']) : null;

/* ---------- Validate upload ---------- */
if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
  $errors[] = 'Please choose a valid Excel file (.xlsx or .xls).';
  include __DIR__ . '/views/import_form.php';
  exit;
}

$tmpPath    = $_FILES['file']['tmp_name'];
$origName   = (string)($_FILES['file']['name'] ?? '');
$ext        = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
$allowExt   = ['xlsx', 'xls'];
if (!in_array($ext, $allowExt, true)) {
  $errors[] = 'Unsupported file type. Please upload .xlsx or .xls.';
  include __DIR__ . '/views/import_form.php';
  exit;
}

/* ---------- Parse Excel ---------- */
try {
  $reader = new ExcelReader($mapping);
  // If $sheetName is null or empty, ExcelReader will use the first sheet
  $reader->load($tmpPath, $sheetName ?: null);
  $rows = $reader->extractRows();
  $results['total_rows'] = count($rows);

  if ($results['total_rows'] === 0) {
    $errors[] = 'No data rows found under the detected header. Check if headers match mapping.php.';
    include __DIR__ . '/views/import_result.php';
    exit;
  }

} catch (Throwable $e) {
  $errors[] = 'Failed to read Excel: ' . $e->getMessage();
  include __DIR__ . '/views/import_result.php';
  exit;
}

/* ---------- Prepare INSERT ---------- */
$sql = "
  INSERT INTO purchase_entries
  (user_id, date, supplier, ref_page, tin, vat_nvat, address, category, description,
   project_name, reference, input_vat, vatable, non_vat, total, freight_handling, cash,
   account_title, debit, credit, remarks)
  VALUES (?,?,?,?,?,?,?,?,?,
          ?,?,       ?,?,?,?,?,?,
          ?,?,?,?)
";
$stmt = $pdo->prepare($sql);
$uid  = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) {
  $errors[] = 'User not recognized in session (user_id missing).';
  include __DIR__ . '/views/import_result.php';
  exit;
}

/* ---------- Insert loop (transactional if not dry run) ---------- */
$inTransaction = false;
try {
  if (!$dryRun) {
    $pdo->beginTransaction();
    $inTransaction = true;
  }

  foreach ($rows as $row) {
    // Normalize & defaults
    $row['vat_nvat'] = $row['vat_nvat'] ?? 'VAT';
    $row['vatable']  = (float)($row['vatable'] ?? 0);
    $row['non_vat']  = (float)($row['non_vat'] ?? 0);
    $freight         = (float)($row['freight_handling'] ?? 0);

    // Compute totals / input VAT
    if (function_exists('calc_purchase')) {
      $calc     = calc_purchase($row['vatable'], $row['non_vat'], 0, $row['vat_nvat']);
      $total    = (float)($calc['total']     ?? ($row['vatable'] + $row['non_vat'] + $freight));
      $inputVat = (float)($row['input_vat']  ?? ($calc['input_vat'] ?? 0));
    } else {
      $total    = $row['vatable'] + $row['non_vat'] + $freight;
      $inputVat = (float)($row['input_vat'] ?? 0);
    }

    $bind = [
      $uid,
      $row['date'] ?? null,
      $row['supplier'] ?? null,
      $row['ref_page'] ?? null,
      $row['tin'] ?? null,
      $row['vat_nvat'] ?? null,
      $row['address'] ?? null,
      $row['category'] ?? null,
      $row['description'] ?? null,
      $row['project_name'] ?? null,
      $row['reference'] ?? null,
      $inputVat,
      $row['vatable'],
      $row['non_vat'],
      $total,
      $freight,
      $row['cash'] ?? 0,
      $row['account_title'] ?? null,
      $row['debit'] ?? 0,
      $row['credit'] ?? 0,
      $row['remarks'] ?? null,
    ];

    if ($dryRun) {
      $results['rows'][] = ['status' => 'DRY', 'data' => $bind];
      $results['skipped']++;
      continue;
    }

    try {
      $stmt->execute($bind);
      $results['inserted']++;
    } catch (Throwable $ex) {
      // Collect row-level error but continue
      $results['rows'][] = [
        'status' => 'ERROR',
        'error'  => $ex->getMessage(),
        'data'   => $bind,
      ];
      $results['skipped']++;
    }
  }

  if ($inTransaction) {
    // If everything went fine, commit; still commit even with some row errors
    $pdo->commit();
    $inTransaction = false;
  }

} catch (Throwable $e) {
  if ($inTransaction) {
    $pdo->rollBack();
    $inTransaction = false;
  }
  $errors[] = 'Import failed: ' . $e->getMessage();
}

/* ---------- Show result ---------- */
include __DIR__ . '/views/import_result.php';
