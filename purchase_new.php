<?php
require "config.php";
require "helpers.php";
redirect_if_not_logged_in();
require_permission($pdo, 'create_journal');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Track used nonces in-session
$_SESSION['nonce_used'] = $_SESSION['nonce_used'] ?? [];

$success = false;
$already_saved = false;
$error_msg = '';
$errors = [];

/* ---------- helpers ---------- */

// Helper to keep values ("sticky" fields) after POST
function old($key, $default = "") {
    return isset($_POST[$key]) ? htmlspecialchars($_POST[$key]) : htmlspecialchars($default);
}

// Fetch suggestions for datalists (project names, categories, account titles)
$project_suggestions = [];
$category_suggestions = [];
$account_title_suggestions = [];

try {
    // Popular/distinct project names
    $stmt = $pdo->query("
        SELECT project_name, COUNT(*) c
        FROM purchase_entries
        WHERE project_name IS NOT NULL AND project_name <> ''
        GROUP BY project_name
        ORDER BY c DESC, project_name ASC
        LIMIT 5000
    ");
    $project_suggestions = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    // Optional: categories seen before
    $stmt = $pdo->query("
        SELECT category, COUNT(*) c
        FROM purchase_entries
        WHERE category IS NOT NULL AND category <> ''
        GROUP BY category
        ORDER BY c DESC, category ASC
        LIMIT 5000
    ");
    $category_suggestions = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    // Optional: account titles seen before
    $stmt = $pdo->query("
        SELECT account_title, COUNT(*) c
        FROM purchase_entries
        WHERE account_title IS NOT NULL AND account_title <> ''
        GROUP BY account_title
        ORDER BY c DESC, account_title ASC
        LIMIT 5000
    ");
    $account_title_suggestions = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
} catch (Throwable $e) {
    // Suggestions are optional; ignore failures
}

/* ---------- GET: prevent caching + issue a fresh nonce ---------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    $_SESSION['form_nonce'] = bin2hex(random_bytes(16));
    $form_nonce = $_SESSION['form_nonce'];
}

/* ---------- POST: idempotent, no-redirect ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nonce = $_POST['form_nonce'] ?? '';

    // Validate nonce against current form
    if (!isset($_SESSION['form_nonce']) || !hash_equals($_SESSION['form_nonce'], $nonce)) {
        $error_msg = 'This form was already submitted or expired. No changes were saved.';
        $_SESSION['form_nonce'] = bin2hex(random_bytes(16));
        $form_nonce = $_SESSION['form_nonce'];
    }
    // Same nonce reused (refresh/double-submit) => NO-OP
    elseif (!empty($_SESSION['nonce_used'][$nonce])) {
        $already_saved = true;
        $_SESSION['form_nonce'] = bin2hex(random_bytes(16));
        $form_nonce = $_SESSION['form_nonce'];
    }
    else {
        /* ----- server-side required validation ----- */
        // Ensure net has a value even if JS didn't propagate yet
        if (!isset($_POST['net']) || $_POST['net'] === '') {
            if (isset($_POST['net_view']) && $_POST['net_view'] !== '') {
                $_POST['net'] = preg_replace('/,/', '', $_POST['net_view']);
            }
        }

        $required = [
            'date'          => 'Date is required.',
            'supplier'      => 'Supplier is required.',
            'address'       => 'Address is required.',
            'tin'           => 'TIN is required.',
            'description'   => 'Description is required.',
            'project_name'  => 'Project Name is required.',
            'net'           => 'Net (helper) is required.',
        ];

        foreach ($required as $key => $msg) {
            if (!isset($_POST[$key]) || trim((string)$_POST[$key]) === '') {
                $errors[] = $msg;
            }
        }

        // Net must be numeric
        if (isset($_POST['net']) && $_POST['net'] !== '') {
            $netNum = (float)str_replace(',', '', (string)$_POST['net']);
            if (!is_numeric($_POST['net']) && !is_numeric($netNum)) {
                $errors[] = 'Net (helper) must be a number.';
            }
        }

        if ($errors) {
            // Do not insert; show errors, rotate a new nonce for continued edits
            $error_msg = implode(' ', $errors);
            $_SESSION['form_nonce'] = bin2hex(random_bytes(16));
            $form_nonce = $_SESSION['form_nonce'];
        } else {
            // First use of this nonce → perform the insert ONCE
            $calc = calc_purchase(
                $_POST["vatable"] ?? 0,
                $_POST["non_vat"] ?? 0,
                $_POST["net"] ?? 0,
                $_POST["vat_nvat"] ?? 'VAT'
            );

            $stmt = $pdo->prepare("
                INSERT INTO purchase_entries
                (user_id, date, supplier, ref_page, tin, vat_nvat, address, category, description,
                 project_name, reference, input_vat, vatable, non_vat, total, freight_handling, cash,
                 account_title, debit, credit, remarks)
                VALUES (?,?,?,?,?,?,?,?,?,
                        ?,?,       ?,?,?,?,?,?,
                        ?,?,?,?)
            ");

            $stmt->execute([
                $_SESSION["user_id"],
                $_POST["date"],
                $_POST["supplier"],
                $_POST["ref_page"] ?? null,                 // optional
                $_POST["tin"] ?? null,                      // required by validation
                $_POST["vat_nvat"] ?? 'VAT',
                $_POST["address"] ?? null,                  // required by validation
                $_POST["category"] ?? null,                 // optional
                $_POST["description"] ?? null,              // required by validation

                $_POST["project_name"] ?? null,             // required by validation
                $_POST["reference"] ?? null,                // optional

                $calc["input_vat"],
                $calc["vatable"],
                $calc["non_vat"],
                $calc["total"],
                $_POST["freight_handling"] !== "" ? $_POST["freight_handling"] : 0,
                $calc["cash"],

                $_POST["account_title"] ?? null,            // optional
                $_POST["debit"] !== "" ? $_POST["debit"] : 0,  // optional
                $_POST["credit"] !== "" ? $_POST["credit"] : 0, // optional
                $_POST["remarks"] ?? null
            ]);

            $success = true;
            $_SESSION['last_purchase_id'] = $pdo->lastInsertId();

            // Build a small summary for the success alert
            $saved_date     = $_POST["date"] ?? '';
            $saved_supplier = $_POST["supplier"] ?? '';
            $saved_net      = $_POST["net"] ?? ($_POST["net_view"] ?? '');
            // Normalize net display
            $saved_net_num  = (float)str_replace([',',' '], '', (string)$saved_net);
            $saved_net_disp = number_format($saved_net_num, 2);

            // Entry counter: use the inserted row id as the entry number
            $saved_entry_no = (int)$pdo->lastInsertId();


            // Mark nonce as used → refresh won't insert again
            $_SESSION['nonce_used'][$nonce] = true;

            // Rotate fresh nonce so user can tweak & save again
            $_SESSION['form_nonce'] = bin2hex(random_bytes(16));
            $form_nonce = $_SESSION['form_nonce'];
        }
    }
}

include "templates/header.php";
?>
<style>
.autocomplete-ghost-wrap { position:relative; }
.autocomplete-ghost {
  position:absolute; top:0; left:0; color:#bbb; pointer-events:none;
  font-family:inherit; font-size:inherit; height:100%; line-height:1.5;
  padding:0.375rem 0.75rem; width:100%; white-space:pre; z-index:1; overflow:hidden;
}
</style>

<div class="card p-4">
  <h3>New Purchase Entry</h3>

  <?php if ($success): ?>
    <div class="alert alert-success mt-2">✅ Saved <strong>Entry #<?= (int)($saved_entry_no ?? ($_SESSION['last_purchase_id'] ?? 0)) ?></strong> — <strong><?= htmlspecialchars($saved_date ?? "") ?></strong> — <strong><?= htmlspecialchars($saved_supplier ?? "") ?></strong> — Net: <strong>₱<?= htmlspecialchars($saved_net_disp ?? "") ?></strong></div>
  <?php elseif ($already_saved): ?>
    <div class="alert alert-info mt-2">ℹ️ That was a page refresh of your last submission. No new entry was created.</div>
  <?php elseif ($error_msg): ?>
    <div class="alert alert-warning mt-2"><?= htmlspecialchars($error_msg) ?></div>
  <?php endif; ?>

  <form id="purchaseForm" method="post" class="mt-3" autocomplete="off">
    <input type="hidden" name="form_nonce" value="<?= htmlspecialchars($form_nonce ?? ($_SESSION['form_nonce'] ?? '')) ?>">

    <div class="row g-3">

      <div class="col-md-3">
        <label class="form-label">Date</label>
        <input type="date" name="date" id="date" class="form-control" required value="<?= old('date', date('Y-m-d')) ?>">
        <div class="form-text">mm/dd/yyyy</div>
      </div>

      <div class="col-md-5">
        <label class="form-label">Supplier <span class="text-danger">*</span></label>
        <div class="autocomplete-ghost-wrap">
          <span id="supplier-ghost" class="autocomplete-ghost"></span>
          <input type="text" name="supplier" id="supplier" class="form-control" list="suppliers" required
                 value="<?= old('supplier') ?>" autocomplete="off" placeholder="Supplier name">
        </div>
        <datalist id="suppliers"></datalist>
      </div>

      <div class="col-md-4">
        <label class="form-label">VAT / NVAT</label>
        <select name="vat_nvat" id="vat_nvat" class="form-select">
          <?php $vv = $_POST['vat_nvat'] ?? 'VAT'; ?>
          <option value="VAT" <?= $vv==='VAT'?'selected':''; ?>>VAT</option>
          <option value="NonVAT" <?= $vv==='NonVAT'?'selected':''; ?>>NonVAT</option>
        </select>
      </div>

      <!-- Address BEFORE TIN (both required) -->
      <div class="col-md-6">
        <label class="form-label">Address <span class="text-danger">*</span></label>
        <div class="autocomplete-ghost-wrap">
          <span id="address-ghost" class="autocomplete-ghost"></span>
          <input type="text" name="address" id="address" class="form-control" required
                 value="<?= old('address') ?>" list="addresss" autocomplete="off" placeholder="Street / City / Province">
        </div>
        <datalist id="addresss"></datalist>
      </div>

      <div class="col-md-3">
        <label class="form-label">TIN <span class="text-danger">*</span></label>
        <input type="text" name="tin" id="tin" class="form-control" required
               value="<?= old('tin') ?>" placeholder="###-###-###-###"
               pattern="[\d\- ]{9,}" title="Numbers and dashes only">
      </div>

      <!-- Description (required) -->
      <div class="col-md-9">
        <label class="form-label">Description <span class="text-danger">*</span></label>
        <textarea name="description" class="form-control" rows="4" required
                  placeholder="What was purchased?" style="white-space: pre-wrap;"><?= old('description') ?></textarea>
      </div>

      <!-- Project Name: datalist dropdown but still editable -->
      <div class="col-md-6">
        <label class="form-label">Project Name <span class="text-danger">*</span></label>
        <input type="text" name="project_name" id="project" class="form-control"
               list="projects" required value="<?= old('project_name') ?>" autocomplete="off"
               placeholder="Select or type a new project">
        <datalist id="projects">
          <?php foreach ($project_suggestions as $pname): ?>
            <option value="<?= htmlspecialchars($pname) ?>"></option>
          <?php endforeach; ?>
        </datalist>
      </div>

      <!-- Net (helper) required -->
      <div class="col-md-3">
        <label class="form-label">Net (helper) <span class="text-danger">*</span></label>
        <input type="text" inputmode="decimal" id="net_view" name="net_view" class="form-control" required
               value="<?= isset($_POST['net']) && $_POST['net'] !== '' ? number_format((float)$_POST['net'], 2) : old('net') ?>"
               placeholder="0.00">
        <input type="hidden" name="net" id="net" value="<?= isset($_POST['net']) ? htmlspecialchars($_POST['net']) : '' ?>">
      </div>

      <!-- Auto-calculated -->
      <div class="col-md-3">
        <label class="form-label">VATable</label>
        <input type="text" id="vatable_view" class="form-control" readonly tabindex="-1"
               value="<?= isset($_POST['vatable']) ? number_format((float)$_POST['vatable'], 2) : number_format(0,2) ?>">
        <input type="hidden" name="vatable" id="vatable" value="<?= isset($_POST['vatable']) ? htmlspecialchars($_POST['vatable']) : '0' ?>">
      </div>

      <div class="col-md-3">
        <label class="form-label">NonVAT</label>
        <input type="text" id="non_vat_view" class="form-control" readonly tabindex="-1"
               value="<?= isset($_POST['non_vat']) ? number_format((float)$_POST['non_vat'], 2) : number_format(0,2) ?>">
        <input type="hidden" name="non_vat" id="non_vat" value="<?= isset($_POST['non_vat']) ? htmlspecialchars($_POST['non_vat']) : '0' ?>">
      </div>

      <div class="col-md-3">
        <label class="form-label">Input VAT</label>
        <input type="text" id="input_vat_view" class="form-control" readonly>
        <input type="hidden" name="input_vat" id="input_vat" value="">
      </div>

      <div class="col-md-3">
        <label class="form-label">Total</label>
        <input type="text" id="total_view" class="form-control" readonly>
        <input type="hidden" name="total" id="total" value="">
      </div>

      <div class="col-md-3">
        <label class="form-label">Freight & Handling</label>
        <input type="number" step="0.01" name="freight_handling" id="freight_handling" class="form-control"
               value="<?= old('freight_handling', '0') ?>">
      </div>

      <div class="col-md-3">
        <label class="form-label">Cash</label>
        <input type="text" id="cash_view" class="form-control" readonly>
        <input type="hidden" name="cash" id="cash" value="">
      </div>

      <!-- ===== Optional details (collapsed for a clean form) ===== -->
      <div class="col-12">
        <a class="btn btn-outline-secondary" data-bs-toggle="collapse" href="#optionalDetails" role="button" aria-expanded="false" aria-controls="optionalDetails">
          Optional details
        </a>
      </div>

      <div class="collapse" id="optionalDetails">
        <div class="row g-3 mt-1">
          <div class="col-md-3">
            <label class="form-label">Ref. Page</label>
            <input type="text" name="ref_page" class="form-control" value="<?= old('ref_page') ?>">
          </div>

          <div class="col-md-4">
            <label class="form-label">Category</label>
            <input type="text" name="category" class="form-control" list="categories" value="<?= old('category') ?>">
            <datalist id="categories">
              <?php foreach ($category_suggestions as $cat): ?>
                <option value="<?= htmlspecialchars($cat) ?>"></option>
              <?php endforeach; ?>
            </datalist>
          </div>

          <div class="col-md-5">
            <label class="form-label">Reference</label>
            <input type="text" name="reference" class="form-control" value="<?= old('reference') ?>">
          </div>

          <div class="col-md-6">
            <label class="form-label">Account Title</label>
            <input type="text" name="account_title" class="form-control" list="account_titles" value="<?= old('account_title') ?>">
            <datalist id="account_titles">
              <?php foreach ($account_title_suggestions as $at): ?>
                <option value="<?= htmlspecialchars($at) ?>"></option>
              <?php endforeach; ?>
            </datalist>
          </div>

          <div class="col-md-3">
            <label class="form-label">Debit</label>
            <input type="number" step="0.01" name="debit" class="form-control" value="<?= old('debit', '0') ?>">
          </div>

          <div class="col-md-3">
            <label class="form-label">Credit</label>
            <input type="number" step="0.01" name="credit" class="form-control" value="<?= old('credit', '0') ?>">
          </div>
        </div>
      </div>

      <div class="col-12 d-flex gap-2">
        <button class="btn btn-primary mt-2" type="submit">Save</button>
        <button type="button" id="clearFormBtn" class="btn btn-outline-secondary mt-2">Clear</button>
      </div>

    </div>
  </form>
</div>

<!-- One source of truth for JS -->
<script src="assets/script.js"></script>

<?php include "templates/footer.php"; ?>
