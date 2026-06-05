<?php
require "config.php";
require "helpers.php";

redirect_if_not_logged_in();
ensure_staff_profiles_table($pdo);
ensure_staff_activity_log_table($pdo);

$userId = (int)$_SESSION["user_id"];
$flashSuccess = '';
$flashError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    csrf_verify();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'change_password') {
      $password = (string)($_POST['password'] ?? '');
      if ($password === '') {
        throw new RuntimeException('Password is required.');
      }
      $st = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
      $st->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
      log_staff_activity($pdo, $userId, 'password_changed', 'Password changed from account dashboard.', $userId);
      $flashSuccess = 'Password changed.';
    }
  } catch (Throwable $e) {
    $flashError = $e->getMessage();
  }
}

$stmt = $pdo->prepare("SELECT COUNT(*) AS entry_count, COALESCE(SUM(cash),0) AS total_cash FROM purchase_entries WHERE user_id=?");
$stmt->execute([$userId]);
$row = $stmt->fetch() ?: ['entry_count' => 0, 'total_cash' => 0];
$count = (int)($row['entry_count'] ?? 0);
$total_cash = (float)($row['total_cash'] ?? 0);

$weekStmt = $pdo->prepare(
  "SELECT COUNT(*) FROM purchase_entries WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
);
$weekStmt->execute([$userId]);
$entriesThisWeek = (int)$weekStmt->fetchColumn();

$lastLoginStmt = $pdo->prepare(
  "SELECT created_at FROM staff_activity_log WHERE user_id = ? AND action = 'login' ORDER BY created_at DESC LIMIT 1"
);
$lastLoginStmt->execute([$userId]);
$lastLogin = (string)($lastLoginStmt->fetchColumn() ?: '');

$profileStmt = $pdo->prepare(
  "SELECT u.name, u.email, u.created_at, r.role, p.job_title, p.department, p.phone, p.address, p.bio
   FROM users u
   LEFT JOIN user_roles r ON r.user_id = u.id
   LEFT JOIN staff_profiles p ON p.user_id = u.id
   WHERE u.id = ? LIMIT 1"
);
$profileStmt->execute([$userId]);
$profile = $profileStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$role = current_user_role($pdo);
$recentEntries = $pdo->prepare(
  "SELECT id, date, supplier, ref_page, tin, vat_nvat, address, category, description, project_name, reference,
          input_vat, vatable, non_vat, total, freight_handling, cash, account_title, debit, credit, remarks, created_at
   FROM purchase_entries
   WHERE user_id = ?
   ORDER BY date DESC, id DESC
   LIMIT 5"
);
$recentEntries->execute([$userId]);
$recentEntries = $recentEntries->fetchAll(PDO::FETCH_ASSOC);

$recentActivity = $pdo->prepare(
  "SELECT created_at, action, details
   FROM staff_activity_log
   WHERE user_id = ?
   ORDER BY created_at DESC
   LIMIT 5"
);
$recentActivity->execute([$userId]);
$recentActivity = $recentActivity->fetchAll(PDO::FETCH_ASSOC);

$inquiryCounts = [
  'new' => 0,
  'contacted' => 0,
  'closed' => 0,
];
foreach (['new', 'contacted', 'closed'] as $statusKey) {
  $st = $pdo->prepare("SELECT COUNT(*) FROM website_inquiries WHERE status = ?");
  $st->execute([$statusKey]);
  $inquiryCounts[$statusKey] = (int)$st->fetchColumn();
}

$search = trim((string)($_GET['q'] ?? ''));
$recentEntriesQuery = "SELECT id, date, supplier, ref_page, tin, vat_nvat, address, category, description, project_name, reference,
          input_vat, vatable, non_vat, total, freight_handling, cash, account_title, debit, credit, remarks, created_at
   FROM purchase_entries
   WHERE user_id = ?";
$recentEntriesParams = [$userId];
if ($search !== '') {
  $recentEntriesQuery .= " AND (supplier LIKE ? OR project_name LIKE ? OR category LIKE ? OR reference LIKE ? OR description LIKE ?)";
  $like = '%' . $search . '%';
  array_push($recentEntriesParams, $like, $like, $like, $like, $like);
}
$recentEntriesQuery .= " ORDER BY date DESC, id DESC LIMIT 5";
$recentEntries = $pdo->prepare($recentEntriesQuery);
$recentEntries->execute($recentEntriesParams);
$recentEntries = $recentEntries->fetchAll(PDO::FETCH_ASSOC);

$dailyCountsStmt = $pdo->prepare(
  "SELECT DATE(created_at) AS day, COUNT(*) AS total
   FROM purchase_entries
   WHERE user_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
   GROUP BY DATE(created_at)
   ORDER BY day ASC"
);
$dailyCountsStmt->execute([$userId]);
$dailyCountsRows = $dailyCountsStmt->fetchAll(PDO::FETCH_ASSOC);
$dailyMap = [];
for ($i = 6; $i >= 0; $i--) {
  $day = date('Y-m-d', strtotime("-{$i} day"));
  $dailyMap[$day] = 0;
}
foreach ($dailyCountsRows as $rowCount) {
  $dailyMap[(string)$rowCount['day']] = (int)$rowCount['total'];
}
$dailyMax = max(1, max($dailyMap));

$title = 'Account Dashboard';
include "templates/header.php";
?>
<div class="row g-4">
  <div class="col-lg-8">
    <div class="card p-4 h-100">
      <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start mb-3">
        <div>
          <div class="text-muted small text-uppercase fw-semibold">Account dashboard</div>
          <h3 class="mb-1">Welcome, <?= htmlspecialchars((string)($profile['name'] ?? $_SESSION['name'] ?? 'User'), ENT_QUOTES, 'UTF-8') ?></h3>
          <div class="text-muted">Your profile, activity, and journal usage in one place.</div>
        </div>
        <span class="badge text-bg-primary align-self-start"><?= htmlspecialchars(ucfirst($role), ENT_QUOTES, 'UTF-8') ?></span>
      </div>

      <?php if ($flashSuccess): ?>
        <div class="alert alert-success"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>
      <?php if ($flashError): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <div class="card border-0 bg-light h-100">
            <div class="card-body">
              <div class="text-muted small">Your entries</div>
              <div class="display-6 fw-semibold"><?= (int)$count ?></div>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card border-0 bg-light h-100">
            <div class="card-body">
              <div class="text-muted small">Total cash</div>
              <div class="display-6 fw-semibold"><?= number_format((float)$total_cash, 2) ?></div>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card border-0 bg-light h-100">
            <div class="card-body">
              <div class="text-muted small">Entries this week</div>
              <div class="display-6 fw-semibold"><?= (int)$entriesThisWeek ?></div>
            </div>
          </div>
        </div>
      </div>

      <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div class="fw-semibold">7-day activity</div>
          <div class="text-muted small">Entries created per day</div>
        </div>
        <div class="d-flex align-items-end gap-2" style="height:96px;">
          <?php foreach ($dailyMap as $day => $value): ?>
            <?php $h = (int)max(12, round(($value / $dailyMax) * 88)); ?>
            <div class="flex-fill text-center">
              <div class="bg-primary-subtle rounded-top mx-auto" style="height: <?= $h ?>px; width: 100%; max-width: 42px;"></div>
              <div class="small text-muted mt-1"><?= htmlspecialchars(date('D', strtotime($day)), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-primary" href="purchase_new.php">New Entry</a>
        <a class="btn btn-outline-primary" href="purchase_list.php">Open Journal</a>
        <a class="btn btn-outline-secondary" href="inquiries.php">Inquiries</a>
        <?php if ($role === 'admin'): ?>
          <a class="btn btn-outline-dark" href="admin.php">Admin Tools</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card p-4 h-100">
      <div class="card-header bg-white px-0 pt-0 fw-semibold">My profile</div>
      <div class="small text-muted mb-3">
        <div><span class="fw-semibold">Email:</span> <?= htmlspecialchars((string)($profile['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
        <div><span class="fw-semibold">Joined:</span> <?= htmlspecialchars((string)($profile['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
        <div><span class="fw-semibold">Last login:</span> <?= htmlspecialchars($lastLogin ?: 'Not available', ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      <div class="alert alert-info py-2 small mb-4">Profile changes are managed by an administrator.</div>

      <form method="post" class="row g-2 mb-4">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" value="change_password">
        <div class="col-12">
          <label class="form-label small mb-1">Change password</label>
          <input type="password" name="password" class="form-control form-control-sm" placeholder="New password" required>
        </div>
        <div class="col-12">
          <button class="btn btn-outline-secondary btn-sm">Update password</button>
        </div>
      </form>

      <div class="card-header bg-white px-0 pt-0 fw-semibold">Quick shortcuts</div>
      <div class="row g-2 mb-4">
        <div class="col-4">
          <div class="p-3 bg-light rounded-3 text-center">
            <div class="small text-muted">New</div>
            <div class="h4 mb-0"><?= (int)$inquiryCounts['new'] ?></div>
          </div>
        </div>
        <div class="col-4">
          <div class="p-3 bg-light rounded-3 text-center">
            <div class="small text-muted">Contacted</div>
            <div class="h4 mb-0"><?= (int)$inquiryCounts['contacted'] ?></div>
          </div>
        </div>
        <div class="col-4">
          <div class="p-3 bg-light rounded-3 text-center">
            <div class="small text-muted">Closed</div>
            <div class="h4 mb-0"><?= (int)$inquiryCounts['closed'] ?></div>
          </div>
        </div>
      </div>

      <div class="card-header bg-white px-0 pt-0 fw-semibold d-flex justify-content-between align-items-center">
        <span>Recent entries</span>
        <form method="get" class="d-flex gap-2">
          <input type="search" name="q" class="form-control form-control-sm" placeholder="Search entries..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
          <button class="btn btn-sm btn-outline-primary">Search</button>
        </form>
      </div>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr><th>Date</th><th>Supplier</th><th class="text-end">Cash</th></tr>
          </thead>
          <tbody>
            <?php if (!$recentEntries): ?>
              <tr><td colspan="3" class="text-center text-muted py-3">No entries yet.</td></tr>
            <?php else: ?>
              <?php foreach ($recentEntries as $entry): ?>
                <tr role="button" tabindex="0" data-bs-toggle="modal" data-bs-target="#entryModal<?= (int)$entry['id'] ?>">
                  <td><?= htmlspecialchars((string)$entry['date'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars((string)$entry['supplier'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="text-end"><?= number_format((float)$entry['cash'], 2) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="row g-4 mt-1">
  <div class="col-lg-6">
    <div class="card p-4 h-100">
      <div class="card-header bg-white px-0 pt-0 fw-semibold">Recent activity</div>
      <div class="list-group list-group-flush">
        <?php if (!$recentActivity): ?>
          <div class="text-center text-muted py-3">No activity yet.</div>
        <?php else: ?>
          <?php foreach ($recentActivity as $act): ?>
            <div class="list-group-item px-0 d-flex justify-content-between align-items-start">
              <div>
                <div class="fw-semibold"><?= htmlspecialchars((string)$act['action'], ENT_QUOTES, 'UTF-8') ?></div>
                <div class="text-muted small"><?= htmlspecialchars((string)($act['details'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
              </div>
              <div class="text-muted small ms-3 text-nowrap"><?= htmlspecialchars((string)$act['created_at'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php foreach ($recentEntries as $entry): ?>
  <div class="modal fade" id="entryModal<?= (int)$entry['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h5 class="modal-title mb-0"><?= htmlspecialchars((string)$entry['supplier'], ENT_QUOTES, 'UTF-8') ?></h5>
            <div class="text-muted small">Journal entry details</div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4"><div class="text-muted small">Date</div><div class="fw-semibold"><?= htmlspecialchars((string)$entry['date'], ENT_QUOTES, 'UTF-8') ?></div></div>
            <div class="col-md-4"><div class="text-muted small">Supplier</div><div class="fw-semibold"><?= htmlspecialchars((string)$entry['supplier'], ENT_QUOTES, 'UTF-8') ?></div></div>
            <div class="col-md-4"><div class="text-muted small">Project</div><div class="fw-semibold"><?= htmlspecialchars((string)($entry['project_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div></div>

            <div class="col-md-4"><div class="text-muted small">Ref page</div><div class="fw-semibold"><?= htmlspecialchars((string)($entry['ref_page'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div></div>
            <div class="col-md-4"><div class="text-muted small">TIN</div><div class="fw-semibold"><?= htmlspecialchars((string)($entry['tin'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div></div>
            <div class="col-md-4"><div class="text-muted small">VAT / Non-VAT</div><div class="fw-semibold"><?= htmlspecialchars((string)($entry['vat_nvat'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div></div>

            <div class="col-md-6"><div class="text-muted small">Address</div><div class="fw-semibold"><?= htmlspecialchars((string)($entry['address'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div></div>
            <div class="col-md-6"><div class="text-muted small">Category</div><div class="fw-semibold"><?= htmlspecialchars((string)($entry['category'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div></div>

            <div class="col-12"><div class="text-muted small">Description</div><div class="fw-semibold"><?= nl2br(htmlspecialchars((string)($entry['description'] ?? '-'), ENT_QUOTES, 'UTF-8')) ?></div></div>
            <div class="col-12"><div class="text-muted small">Remarks</div><div class="fw-semibold"><?= nl2br(htmlspecialchars((string)($entry['remarks'] ?? '-'), ENT_QUOTES, 'UTF-8')) ?></div></div>

            <div class="col-md-4"><div class="text-muted small">Input VAT</div><div class="fw-semibold"><?= number_format((float)($entry['input_vat'] ?? 0), 2) ?></div></div>
            <div class="col-md-4"><div class="text-muted small">Vatable</div><div class="fw-semibold"><?= number_format((float)($entry['vatable'] ?? 0), 2) ?></div></div>
            <div class="col-md-4"><div class="text-muted small">Non-VAT</div><div class="fw-semibold"><?= number_format((float)($entry['non_vat'] ?? 0), 2) ?></div></div>

            <div class="col-md-4"><div class="text-muted small">Total</div><div class="fw-semibold"><?= number_format((float)($entry['total'] ?? 0), 2) ?></div></div>
            <div class="col-md-4"><div class="text-muted small">Freight / Handling</div><div class="fw-semibold"><?= number_format((float)($entry['freight_handling'] ?? 0), 2) ?></div></div>
            <div class="col-md-4"><div class="text-muted small">Cash</div><div class="fw-semibold"><?= number_format((float)($entry['cash'] ?? 0), 2) ?></div></div>

            <div class="col-md-4"><div class="text-muted small">Account title</div><div class="fw-semibold"><?= htmlspecialchars((string)($entry['account_title'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div></div>
            <div class="col-md-4"><div class="text-muted small">Debit</div><div class="fw-semibold"><?= number_format((float)($entry['debit'] ?? 0), 2) ?></div></div>
            <div class="col-md-4"><div class="text-muted small">Credit</div><div class="fw-semibold"><?= number_format((float)($entry['credit'] ?? 0), 2) ?></div></div>

            <div class="col-12"><div class="text-muted small">Reference</div><div class="fw-semibold"><?= htmlspecialchars((string)($entry['reference'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div></div>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php endforeach; ?>
<?php include "templates/footer.php"; ?>
