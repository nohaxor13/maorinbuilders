<?php
declare(strict_types=1);

require "config.php";
require "helpers.php";
require "accounting_journal_common.php";

redirect_if_not_logged_in();
mb_ensure_accounting_journal_tables($pdo);

$type = mb_accounting_journal_type($_GET['type'] ?? $_POST['type'] ?? 'general');
$config = mb_accounting_journal_config($type);
$columns = mb_accounting_journal_columns($type);
$labels = mb_accounting_journal_field_labels();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$isEdit = $id > 0;
require_permission($pdo, $isEdit ? 'edit_journal' : 'create_journal');

$allFields = ['entry_date','particulars','ref_page','jv_no','client_name','supplier','tin','vat_nvat','goods_service','address','project_id','project_name','entry_type','sales_invoice_no','voucher_no','reference_no','description','debit','credit','sundry_account_title','sundry_debit','sundry_credit','remarks'];
$entry = array_fill_keys($allFields, '');
$entry['entry_date'] = date('Y-m-d');
$entry['debit'] = $entry['credit'] = $entry['sundry_debit'] = $entry['sundry_credit'] = '0.00';

if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM accounting_journal_entries WHERE id=? AND journal_type=?");
    $stmt->execute([$id, $type]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $_SESSION['_mb_error'] = 'Journal entry not found.';
        header('Location: ' . mb_journal_list_url($type));
        exit;
    }
    $entry = array_merge($entry, $row);
}

function aj_old(array $entry, string $key): string {
    return htmlspecialchars((string)($entry[$key] ?? ''), ENT_QUOTES, 'UTF-8');
}

function aj_field(array $entry, array $labels, string $key, string $class = 'col-md-4'): void {
    $label = $labels[$key] ?? ucwords(str_replace('_', ' ', $key));
    $required = in_array($key, ['entry_date','particulars','client_name','supplier','description'], true);
    $numeric = in_array($key, ['debit','credit','sundry_debit','sundry_credit'], true);
    $textarea = in_array($key, ['particulars','description','remarks'], true);
    ?>
    <div class="<?= htmlspecialchars($class) ?>">
      <label class="form-label"><?= htmlspecialchars($label) ?><?= $required ? ' <span class="text-danger">*</span>' : '' ?></label>
      <?php if ($textarea): ?>
        <textarea name="<?= htmlspecialchars($key) ?>" class="form-control" rows="3" <?= $required ? 'required' : '' ?>><?= aj_old($entry, $key) ?></textarea>
      <?php elseif ($key === 'entry_date'): ?>
        <input type="date" name="<?= htmlspecialchars($key) ?>" class="form-control" required value="<?= aj_old($entry, $key) ?>">
      <?php elseif ($key === 'vat_nvat'): ?>
        <?php $vv = (string)($entry[$key] ?? ''); ?>
        <select name="<?= htmlspecialchars($key) ?>" class="form-select">
          <option value="">Select</option>
          <option value="VAT" <?= $vv==='VAT'?'selected':'' ?>>VAT</option>
          <option value="NVAT" <?= $vv==='NVAT'?'selected':'' ?>>NVAT</option>
          <option value="NonVAT" <?= $vv==='NonVAT'?'selected':'' ?>>NonVAT</option>
        </select>
      <?php else: ?>
        <input type="<?= $numeric ? 'number' : 'text' ?>" <?= $numeric ? 'step="0.01"' : '' ?> name="<?= htmlspecialchars($key) ?>" class="form-control" value="<?= aj_old($entry, $key) ?>" <?= $required ? 'required' : '' ?>>
      <?php endif; ?>
    </div>
    <?php
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrf_verify();
        foreach ($allFields as $field) {
            if (in_array($field, ['debit','credit','sundry_debit','sundry_credit'], true)) {
                $entry[$field] = mb_decimal_input($_POST[$field] ?? 0);
            } else {
                $entry[$field] = trim((string)($_POST[$field] ?? ''));
            }
        }

        if ($entry['entry_date'] === '') $errors[] = 'Date is required.';
        if ($type === 'general' && $entry['particulars'] === '') $errors[] = 'Particulars is required.';
        if ($type === 'sales' && $entry['client_name'] === '') $errors[] = 'Client Name is required.';
        if ($type === 'cash_disbursements' && $entry['supplier'] === '') $errors[] = 'Supplier is required.';
        if (($entry['debit'] + $entry['credit'] + $entry['sundry_debit'] + $entry['sundry_credit']) <= 0) $errors[] = 'Enter at least one debit or credit amount.';

        $journalNo = $type === 'general' ? $entry['jv_no'] : ($type === 'sales' ? $entry['sales_invoice_no'] : $entry['voucher_no']);
        if ($journalNo === '') $journalNo = mb_next_accounting_journal_no($pdo, $type);
        if ($type === 'general') $entry['jv_no'] = $journalNo;
        if ($type === 'sales') $entry['sales_invoice_no'] = $journalNo;
        if ($type === 'cash_disbursements') $entry['voucher_no'] = $journalNo;

        $partyName = $entry['client_name'] ?: $entry['supplier'];
        $invoiceNo = $entry['sales_invoice_no'] ?: $entry['voucher_no'];
        $accountTitle = $entry['sundry_account_title'] ?: ($entry['particulars'] ?: ($entry['description'] ?: 'Journal Entry'));
        $description = $entry['description'] ?: $entry['particulars'];

        if (!$errors) {
            $sqlFields = [
                'journal_type','user_id','entry_date','journal_no','particulars','ref_page','jv_no','client_name','supplier','party_name',
                'invoice_no','sales_invoice_no','voucher_no','reference_no','tin','vat_nvat','goods_service','address','project_id','entry_type',
                'account_title','description','project_name','payment_method','debit','credit','cash_in','cash_out','sundry_account_title','sundry_debit','sundry_credit','remarks'
            ];
            $values = [
                $type, (int)($_SESSION['user_id'] ?? 0), $entry['entry_date'], $journalNo, $entry['particulars'], $entry['ref_page'], $entry['jv_no'],
                $entry['client_name'], $entry['supplier'], $partyName, $invoiceNo, $entry['sales_invoice_no'], $entry['voucher_no'], $entry['reference_no'],
                $entry['tin'], $entry['vat_nvat'], $entry['goods_service'], $entry['address'], $entry['project_id'], $entry['entry_type'],
                $accountTitle, $description, $entry['project_name'], '', $entry['debit'], $entry['credit'], 0, 0, $entry['sundry_account_title'],
                $entry['sundry_debit'], $entry['sundry_credit'], $entry['remarks']
            ];

            if ($isEdit) {
                $assignments = array_map(fn($field) => "$field=?", array_slice($sqlFields, 2));
                $stmt = $pdo->prepare("UPDATE accounting_journal_entries SET " . implode(',', $assignments) . " WHERE id=? AND journal_type=?");
                $stmt->execute(array_merge(array_slice($values, 2), [$id, $type]));
                $_SESSION['_mb_success'] = 'Journal entry updated.';
            } else {
                $placeholders = implode(',', array_fill(0, count($sqlFields), '?'));
                $stmt = $pdo->prepare("INSERT INTO accounting_journal_entries (" . implode(',', $sqlFields) . ") VALUES ($placeholders)");
                $stmt->execute($values);
                $_SESSION['_mb_success'] = 'Journal entry saved.';
            }
            header('Location: ' . mb_journal_list_url($type));
            exit;
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

$pageContainerClass = 'container';
include "templates/header.php";
?>
<style>
.aj-form-title h3{margin:0}
</style>

<div class="card p-4">
  <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap aj-form-title">
    <div>
      <h3><?= htmlspecialchars($isEdit ? 'Edit ' . $config['short'] . ' Entry' : $config['entry_label']) ?></h3>
      <div class="text-muted small mt-1"><?= htmlspecialchars($config['label']) ?></div>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars(mb_journal_list_url($type)) ?>">Back to Journal</a>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-warning mt-3"><?= htmlspecialchars(implode(' ', $errors)) ?></div>
  <?php endif; ?>

  <form method="post" class="mt-3" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
    <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <div class="row g-3">
      <?php
      foreach ($columns as $field) {
          $class = in_array($field, ['particulars','description','remarks'], true) ? 'col-12' : 'col-md-3';
          if (in_array($field, ['client_name','supplier','address','project_name','sundry_account_title'], true)) $class = 'col-md-6';
          aj_field($entry, $labels, $field, $class);
      }
      ?>
      <div class="col-12 d-flex gap-2">
        <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Update Entry' : 'Save Entry' ?></button>
        <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(mb_journal_list_url($type)) ?>">Cancel</a>
      </div>
    </div>
  </form>
</div>

<?php include "templates/footer.php"; ?>
