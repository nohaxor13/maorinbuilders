<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/accounting_journal_common.php';

redirect_if_not_logged_in();
require_permission($pdo, 'import_journal');
mb_ensure_accounting_journal_tables($pdo);

$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    echo 'PhpSpreadsheet autoloader not found.';
    exit;
}
require_once $autoload;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as XLDate;

$type = mb_accounting_journal_type($_GET['type'] ?? $_POST['type'] ?? 'general');
$config = mb_accounting_journal_config($type);
$columns = mb_accounting_journal_columns($type);
$labels = mb_accounting_journal_field_labels();
$errors = [];
$summary = null;

function aj_import_norm(string $value): string {
    $value = strtolower(trim($value));
    $value = str_replace(['.', '/', '-', '_'], ' ', $value);
    return preg_replace('/\s+/', ' ', $value);
}

function aj_import_date($value): string {
    if ($value instanceof DateTimeInterface) return $value->format('Y-m-d');
    if (is_numeric($value) && (float)$value > 20000) {
        return XLDate::excelToDateTimeObject((float)$value)->format('Y-m-d');
    }
    $text = trim((string)$value);
    if ($text === '') return '';
    $time = strtotime($text);
    return $time ? date('Y-m-d', $time) : '';
}

function aj_import_number($value): float {
    $text = trim((string)$value);
    $negative = str_starts_with($text, '(') && str_ends_with($text, ')');
    $text = trim($text, '()');
    $text = str_replace([',', ' '], '', $text);
    $num = is_numeric($text) ? (float)$text : 0.0;
    return $negative ? -$num : $num;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrf_verify();
        if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
            throw new RuntimeException('Choose an XLSX file to import.');
        }

        $spreadsheet = IOFactory::load($_FILES['file']['tmp_name']);
        $sheet = $spreadsheet->getSheetByName($config['label']) ?: $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();
        $highestCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        $aliases = [];
        foreach ($labels as $field => $label) $aliases[aj_import_norm($label)] = $field;
        $aliases += [
            'date' => 'entry_date',
            'particulars' => 'particulars',
            'ref page' => 'ref_page',
            'jv no' => 'jv_no',
            'client name' => 'client_name',
            'supplier' => 'supplier',
            'tin' => 'tin',
            'vat nvat' => 'vat_nvat',
            'vat n vat' => 'vat_nvat',
            'goods service' => 'goods_service',
            'address' => 'address',
            'project id' => 'project_id',
            'project name' => 'project_name',
            'type' => 'entry_type',
            'sales invoice no' => 'sales_invoice_no',
            'voucher no' => 'voucher_no',
            'reference' => 'reference_no',
            'description' => 'description',
            'debit' => 'debit',
            'credit' => 'credit',
            'sundry account title' => 'sundry_account_title',
            'account title' => 'sundry_account_title',
            'sundry debit' => 'sundry_debit',
            'sundry credit' => 'sundry_credit',
            'remarks' => 'remarks',
        ];

        $headerRow = null;
        $colToField = [];
        for ($r = 1; $r <= min(30, $highestRow); $r++) {
            $candidate = [];
            for ($c = 1; $c <= $highestCol; $c++) {
                $value = trim((string)$sheet->getCell(Coordinate::stringFromColumnIndex($c) . $r)->getCalculatedValue());
                $field = $aliases[aj_import_norm($value)] ?? null;
                if ($field) $candidate[$c] = $field;
            }
            if (in_array('entry_date', $candidate, true) && count($candidate) >= 3) {
                $headerRow = $r;
                $colToField = $candidate;
                break;
            }
        }
        if (!$headerRow) throw new RuntimeException('Could not find a header row. Please use the exported format or the reference journal layout.');

        $inserted = 0;
        $skipped = 0;
        $pdo->beginTransaction();
        $sqlFields = [
            'journal_type','user_id','entry_date','journal_no','particulars','ref_page','jv_no','client_name','supplier','party_name',
            'invoice_no','sales_invoice_no','voucher_no','reference_no','tin','vat_nvat','goods_service','address','project_id','entry_type',
            'account_title','description','project_name','payment_method','debit','credit','cash_in','cash_out','sundry_account_title','sundry_debit','sundry_credit','remarks'
        ];
        $stmt = $pdo->prepare("INSERT INTO accounting_journal_entries (" . implode(',', $sqlFields) . ") VALUES (" . implode(',', array_fill(0, count($sqlFields), '?')) . ")");

        for ($r = $headerRow + 1; $r <= $highestRow; $r++) {
            $row = array_fill_keys($columns, '');
            foreach ($colToField as $c => $field) {
                if (!in_array($field, $columns, true)) continue;
                $value = $sheet->getCell(Coordinate::stringFromColumnIndex($c) . $r)->getCalculatedValue();
                if ($field === 'entry_date') $value = aj_import_date($value);
                if (in_array($field, ['debit','credit','sundry_debit','sundry_credit'], true)) $value = aj_import_number($value);
                $row[$field] = $value;
            }
            if (empty($row['entry_date'])) {
                $skipped++;
                continue;
            }
            $hasText = trim(implode('', array_map('strval', $row))) !== '';
            $hasAmount = ((float)($row['debit'] ?? 0) + (float)($row['credit'] ?? 0) + (float)($row['sundry_debit'] ?? 0) + (float)($row['sundry_credit'] ?? 0)) != 0.0;
            if (!$hasText || !$hasAmount) {
                $skipped++;
                continue;
            }

            $journalNo = $type === 'general' ? ($row['jv_no'] ?? '') : ($type === 'sales' ? ($row['sales_invoice_no'] ?? '') : ($row['voucher_no'] ?? ''));
            if ($journalNo === '') $journalNo = mb_next_accounting_journal_no($pdo, $type);
            $partyName = (string)($row['client_name'] ?? $row['supplier'] ?? '');
            $accountTitle = (string)($row['sundry_account_title'] ?? $row['particulars'] ?? $row['description'] ?? 'Journal Entry');
            $description = (string)($row['description'] ?? $row['particulars'] ?? '');
            $stmt->execute([
                $type, (int)($_SESSION['user_id'] ?? 0), $row['entry_date'], $journalNo, $row['particulars'] ?? '', $row['ref_page'] ?? '', $row['jv_no'] ?? '',
                $row['client_name'] ?? '', $row['supplier'] ?? '', $partyName, $journalNo, $row['sales_invoice_no'] ?? '', $row['voucher_no'] ?? '', $row['reference_no'] ?? '',
                $row['tin'] ?? '', $row['vat_nvat'] ?? '', $row['goods_service'] ?? '', $row['address'] ?? '', $row['project_id'] ?? '', $row['entry_type'] ?? '',
                $accountTitle, $description, $row['project_name'] ?? '', '', $row['debit'] ?? 0, $row['credit'] ?? 0, 0, 0, $row['sundry_account_title'] ?? '',
                $row['sundry_debit'] ?? 0, $row['sundry_credit'] ?? 0, $row['remarks'] ?? ''
            ]);
            $inserted++;
        }
        $pdo->commit();
        $summary = ['inserted' => $inserted, 'skipped' => $skipped];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errors[] = $e->getMessage();
    }
}

$pageContainerClass = 'container';
include "templates/header.php";
?>
<div class="card p-4">
  <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
    <div>
      <h3 class="mb-1">Import <?= htmlspecialchars($config['label']) ?></h3>
      <p class="text-muted mb-0">Upload an XLSX exported from this journal or the matching reference sheet.</p>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars(mb_journal_list_url($type)) ?>">Back to Journal</a>
  </div>

  <?php if ($summary): ?>
    <div class="alert alert-success mt-3">Imported <?= (int)$summary['inserted'] ?> entries. Skipped <?= (int)$summary['skipped'] ?> blank or invalid rows.</div>
  <?php endif; ?>
  <?php if ($errors): ?>
    <div class="alert alert-warning mt-3"><?= htmlspecialchars(implode(' ', $errors)) ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="mt-3">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
    <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
    <div class="mb-3">
      <label class="form-label">Excel file (.xlsx)</label>
      <input type="file" name="file" accept=".xlsx" class="form-control" required>
    </div>
    <button class="btn btn-primary" type="submit">Import XLSX</button>
  </form>
</div>
<?php include "templates/footer.php"; ?>
