<?php
// PATH: /MaorinBuilders/journal_import.php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

$LOG_PATH = __DIR__ . '/logs/journal_import.log';
function log_err(string $msg): void {
  global $LOG_PATH;
  if (!is_dir(dirname($LOG_PATH))) @mkdir(dirname($LOG_PATH), 0775, true);
  @file_put_contents($LOG_PATH, '['.date('Y-m-d H:i:s')."] $msg\n", FILE_APPEND);
}
function respond_json($statusCode, array $payload) {
  http_response_code($statusCode);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload);
  exit;
}
$DEBUG = isset($_GET['debug']) && $_GET['debug'] === '1';

require "config.php";
require "helpers.php";
redirect_if_not_logged_in();
require_admin($pdo);

// ----- autoload -----
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
  respond_json(500, ['ok'=>false,'message'=>'PhpSpreadsheet autoloader not found. Install phpoffice/phpspreadsheet.']);
}

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlsDate;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  ?>
  <!doctype html><html lang="en"><head><meta charset="utf-8"><title>Import Journal</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"></head>
  <body class="p-4">
    <h3>Import Journal</h3>
    <form method="post" enctype="multipart/form-data" class="mt-3">
      <div class="mb-3">
        <label class="form-label">Excel file (.xlsx)</label>
        <input type="file" name="file" accept=".xlsx" class="form-control" required>
      </div>
      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="dry_run" value="1" id="dryrun" checked>
        <label class="form-check-label" for="dryrun">Dry run (preview only)</label>
      </div>
      <button class="btn btn-primary" type="submit">Import</button>
      <a class="btn btn-outline-secondary" href="?debug=1">Enable Debug</a>
    </form>
  </body></html>
  <?php
  exit;
}

try {
  $uid = $_SESSION['user_id'] ?? 0;
  if (!$uid) throw new Exception("Unauthorized.");

  if (empty($_FILES['file']) || !empty($_FILES['file']['error'])) {
    $err = $_FILES['file']['error'] ?? 0;
    throw new Exception($err ? "Upload error code {$err}." : "No file uploaded.");
  }
  $tmp = $_FILES['file']['tmp_name'];
  if (!is_uploaded_file($tmp)) throw new Exception("Upload failed.");

  $dryRun = isset($_POST['dry_run']) && $_POST['dry_run'] === '1';

  // ---- load workbook ----
  try {
    $spreadsheet = IOFactory::load($tmp);
  } catch (Throwable $e) {
    throw new Exception("Failed to read Excel. Ensure PHP 'zip' extension is enabled. Inner: ".$e->getMessage());
  }
  $sheet = $spreadsheet->getActiveSheet();

  // ---- helpers (A1 addressing) ----
  $highestCol = $sheet->getHighestColumn();
  $highestIdx = Coordinate::columnIndexFromString($highestCol);

  $cellValue = function(int $col, int $row) use ($sheet): mixed {
    $addr = Coordinate::stringFromColumnIndex($col) . $row;
    return $sheet->getCell($addr)->getValue();
  };
  $cellCalc  = function(int $col, int $row) use ($sheet): mixed {
    $addr = Coordinate::stringFromColumnIndex($col) . $row;
    return $sheet->getCell($addr)->getCalculatedValue();
  };
  $toNumber = function($v): float {
    if (is_numeric($v)) return (float)$v;
    $s = trim((string)$v);
    $neg = ($s !== '' && $s[0] === '(' && substr($s, -1) === ')');
    if ($neg) $s = trim($s, '()');
    $s = str_replace([',',' '], '', $s);
    return is_numeric($s) ? ($neg ? -(float)$s : (float)$s) : 0.0;
  };

  // ---- detect header top row (looks for DATE + SUPPLIER in same row) ----
  $headerTopRow = null;
  for ($r = 1; $r <= 80; $r++) {
    $vals = [];
    for ($c = 1; $c <= $highestIdx; $c++) {
      $vals[] = strtoupper(trim((string)$cellValue($c, $r)));
    }
    $hasDate = in_array('DATE', $vals, true);
    $hasSupplier = false;
    foreach ($vals as $v) { if (strpos($v, 'SUPPLIER') !== false) { $hasSupplier = true; break; } }
    if ($hasDate && $hasSupplier) { $headerTopRow = $r; break; }
  }
  if (!$headerTopRow) throw new Exception("Could not locate header row; please use the reference layout.");

  $headerBottomRow = $headerTopRow + 1;
  $firstDataRow    = $headerTopRow + 2;

  // ---- build two-line header keys ----
  $colKeys = [];
  for ($c = 1; $c <= $highestIdx; $c++) {
    $top = trim((string)$cellValue($c, $headerTopRow));
    $sub = trim((string)$cellValue($c, $headerBottomRow));
    $key = $top && $sub ? ($top.' / '.$sub) : ($top ?: $sub);
    $key = preg_replace('/\s+/', ' ', $key);
    $colKeys[$c] = $key;
  }

  // ---- tolerant header normalization + mapping (with fuzzy resolver) ----
  $norm = function(string $s): string {
    $s = trim($s);
    $s = preg_replace('/\s+/', ' ', $s);
    $s = str_ireplace(['.', '–', '—'], '', $s);     // remove dots & long dashes
    $s = str_replace([' / ', '/', '-'], ' ', $s);   // unify separators to space
    $s = preg_replace('/\s+/', ' ', $s);
    return strtolower($s);
  };

  $mapNorm = [
    // basics
    'date' => 'date',
    'supplier' => 'supplier',
    'supplier ' => 'supplier',
    'ref page' => 'ref_page',
    'voucher no' => null,
    'tin' => 'tin',
    'vat nvat' => 'vat_nvat',
    'address' => 'address',
    'category' => 'category',
    'description' => 'description',
    'project id' => null,
    'project name' => 'project_name',
    'reference' => 'reference',
    'payment terms' => null,
    'remarks' => 'remarks',
    'remarks ' => 'remarks',

    // grouped/sundry
    'd e b i t input vat' => 'input_vat',
    'd e b i t direct materials' => 'vatable',
    'd e b i t direct materials 1' => 'non_vat',
    'd e b i t direct materials 2' => 'total',
    'd e b i t freight & handling' => 'freight_handling',
    'd e b i t cash' => 'cash',

    's u n d r y account title' => 'account_title',
    's u n d r y debit' => 'debit',
    's u n d r y credit' => 'credit',

    // single-row straightforward
    'input vat' => 'input_vat',
    'vatable'   => 'vatable',
    'vat able'  => 'vatable',
    'nonvat'    => 'non_vat',
    'non vat'   => 'non_vat',
    'total'     => 'total',
    'freight & handling' => 'freight_handling',
    'freight and handling' => 'freight_handling',
    'cash'      => 'cash',
    'account title' => 'account_title',
    'debit' => 'debit',
    'credit'=> 'credit',

    // single-row header used in your file
    'direct materials'   => 'vatable',
    'direct materials 1' => 'non_vat',
    'direct materials 2' => 'total',
  ];

  $fuzzyResolve = function(string $k) use ($mapNorm) : ?string {
    if (isset($mapNorm[$k])) return $mapNorm[$k];

    // generic contains checks
    if (strpos($k, 'input') !== false && strpos($k, 'vat') !== false) return 'input_vat';

    // VATable variants
    if (strpos($k, 'vatable') !== false) return 'vatable';
    if (strpos($k, 'vat able') !== false) return 'vatable';
    if (preg_match('/\bv\s*a\s*t\s*able\b/', $k)) return 'vatable';

    // Non-VAT variants
    if (strpos($k, 'non vat') !== false) return 'non_vat';
    if (strpos($k, 'nonvat') !== false)  return 'non_vat';
    if (preg_match('/\bnon\s*v\s*a\s*t\b/', $k)) return 'non_vat';

    // Total but not grand/subtotal headers
    if ($k === 'total') return 'total';
    if (preg_match('/(^|\s)total($|\s)/', $k) && stripos($k, 'grand') === false && stripos($k, 'sub') === false) return 'total';

    // Cash / Freight
    if (strpos($k, 'cash') !== false) return 'cash';
    if (strpos($k, 'freight') !== false) return 'freight_handling';

    return null;
  };

  $colToField = [];
  $debugHeaders = [];
  for ($c = 1; $c <= $highestIdx; $c++) {
    $rawKey = $colKeys[$c] ?? '';
    $kNorm  = $norm($rawKey);
    $field  = $fuzzyResolve($kNorm);
    $colToField[$c] = $field; // may be null
    $debugHeaders[] = ['col'=>$c,'raw'=>$rawKey,'norm'=>$kNorm,'field'=>$field];
  }

  // --- Heuristic: "Direct Materials" spans 3 columns with blank neighbors ---
  for ($c = 1; $c <= $highestIdx; $c++) {
    $kNorm = $norm((string)($colKeys[$c] ?? ''));
    if ($kNorm === 'direct materials') {
      // c = VATable
      if (!$colToField[$c]) $colToField[$c] = 'vatable';

      // c+1 = NonVAT (if exists & unmapped or blank header)
      if ($c + 1 <= $highestIdx) {
        $n1Norm = $norm((string)($colKeys[$c+1] ?? ''));
        if (!$colToField[$c+1] && ($n1Norm === '' || $n1Norm === 'direct materials 1')) {
          $colToField[$c+1] = 'non_vat';
        }
      }
      // c+2 = Total (if exists & unmapped or blank header)
      if ($c + 2 <= $highestIdx) {
        $n2Norm = $norm((string)($colKeys[$c+2] ?? ''));
        if (!$colToField[$c+2] && ($n2Norm === '' || $n2Norm === 'direct materials 2')) {
          $colToField[$c+2] = 'total';
        }
      }
    }
  }
  // refresh debug headers to reflect the heuristic
  $debugHeaders = [];
  for ($c = 1; $c <= $highestIdx; $c++) {
    $debugHeaders[] = ['col'=>$c,'raw'=>$colKeys[$c] ?? '', 'norm'=>$norm((string)($colKeys[$c] ?? '')), 'field'=>$colToField[$c] ?? null];
  }

  // ---- prepare insert ----
  $ins = $pdo->prepare("
    INSERT INTO purchase_entries
      (user_id, date, supplier, ref_page, tin, vat_nvat, address, category, description,
       project_name, reference, input_vat, vatable, non_vat, total, freight_handling, cash,
       account_title, debit, credit, remarks)
    VALUES
      (:uid,:date,:supplier,:ref_page,:tin,:vat_nvat,:address,:category,:description,
       :project_name,:reference,:input_vat,:vatable,:non_vat,:total,:freight_handling,:cash,
       :account_title,:debit,:credit,:remarks)
  ");

  $inserted = 0; $skipped = 0; $preview = []; $emptyRun = 0;
  $lastDate = null; // carry-forward

  for ($r = $firstDataRow, $last = $sheet->getHighestRow(); $r <= $last; $r++) {
    // stop after several blank lines
    $dateB_raw = trim((string)$cellValue(2, $r));
    $suppC_raw = trim((string)$cellValue(3, $r));
    if ($dateB_raw === '' && $suppC_raw === '') {
      if (++$emptyRun >= 8) break;
      continue;
    } else { $emptyRun = 0; }

    $rec = [
      'date'=>null,'supplier'=>null,'ref_page'=>null,'tin'=>null,'vat_nvat'=>null,'address'=>null,
      'category'=>null,'description'=>null,'project_name'=>null,'reference'=>null,
      'input_vat'=>0,'vatable'=>0,'non_vat'=>0,'total'=>0,'freight_handling'=>0,'cash'=>0,
      'account_title'=>null,'debit'=>0,'credit'=>0,'remarks'=>null
    ];

    for ($c = 1; $c <= $highestIdx; $c++) {
      $field = $colToField[$c] ?? null;
      if ($field === null) continue;

      $val = $cellCalc($c, $r);

      if (in_array($field, ['input_vat','vatable','non_vat','total','freight_handling','cash','debit','credit'], true)) {
        $rec[$field] = $toNumber($val);
      } elseif ($field === 'date') {
        if (is_numeric($val)) {
          $rec['date'] = XlsDate::excelToDateTimeObject($val)->format('Y-m-d');
        } else {
          $ts = strtotime((string)$val);
          $rec['date'] = $ts ? date('Y-m-d', $ts) : null;
        }
      } else {
        $rec[$field] = trim((string)$val);
      }
    }

    // carry-forward empty date
    if (!$rec['date']) {
      if ($dateB_raw !== '') {
        $ts = strtotime($dateB_raw);
        if ($ts) $rec['date'] = date('Y-m-d', $ts);
      }
      if (!$rec['date'] && $lastDate) $rec['date'] = $lastDate;
    }
    if ($rec['date']) $lastDate = $rec['date'];

    // skip total/subtotal summary rows
    $joined = strtoupper(trim(($rec['supplier'] ?? '').' '.($rec['category'] ?? '').' '.($rec['description'] ?? '').' '.($rec['account_title'] ?? '')));
    $isTotalRow = (bool)preg_match('/\b(TOTAL|GRAND\s+TOTAL|SUBTOTAL)\b/', $joined);
    if ($isTotalRow) {
      if ($dryRun) $preview[] = $rec + ['_skip_reason' => 'total/subtotal row'];
      else $skipped++;
      continue;
    }

    // require a date (after carry-forward)
    if (!$rec['date']) {
      if ($dryRun) $preview[] = $rec + ['_skip_reason' => 'missing date after carry-forward'];
      else $skipped++;
      continue;
    }

    // optional: skip visually empty lines
    $hasAnyAmount = ($rec['input_vat'] + $rec['vatable'] + $rec['non_vat'] + $rec['total'] + $rec['freight_handling'] + $rec['cash'] + $rec['debit'] + $rec['credit']) != 0;
    if (!$hasAnyAmount && trim((string)$rec['description']) === '' && trim((string)$rec['supplier']) === '') {
      if ($dryRun) $preview[] = $rec + ['_skip_reason' => 'empty line'];
      else $skipped++;
      continue;
    }

    if ($dryRun) {
      $preview[] = $rec;
    } else {
      $ins->execute([
        ':uid'=>$uid, ':date'=>$rec['date'], ':supplier'=>$rec['supplier'], ':ref_page'=>$rec['ref_page'],
        ':tin'=>$rec['tin'], ':vat_nvat'=>$rec['vat_nvat'], ':address'=>$rec['address'], ':category'=>$rec['category'],
        ':description'=>$rec['description'], ':project_name'=>$rec['project_name'], ':reference'=>$rec['reference'],
        ':input_vat'=>$rec['input_vat'], ':vatable'=>$rec['vatable'], ':non_vat'=>$rec['non_vat'], ':total'=>$rec['total'],
        ':freight_handling'=>$rec['freight_handling'], ':cash'=>$rec['cash'],
        ':account_title'=>$rec['account_title'], ':debit'=>$rec['debit'], ':credit'=>$rec['credit'], ':remarks'=>$rec['remarks'],
      ]);
      $inserted++;
    }
  }

  respond_json(200, [
    'ok'=>true,
    'dry_run'=>$dryRun,
    'inserted'=>$inserted,
    'skipped'=>$skipped,
    'rows'=>$dryRun?$preview:null,
    'headers'=>($dryRun || $DEBUG) ? $debugHeaders : null
  ]);

} catch (Throwable $e) {
  log_err($e->getMessage()."\n".$e->getTraceAsString());
  respond_json(500, ['ok'=>false,'message'=>$e->getMessage(),'trace'=>$DEBUG?$e->getTraceAsString():null]);
}
