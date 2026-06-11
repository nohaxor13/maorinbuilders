<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/helpers.php';
redirect_if_not_logged_in();
require_permission($pdo, 'manage_client_portal');
ensure_client_portal_tables($pdo);
require_feature($pdo, 'client_portal');

// Optional role gate (uncomment if you want strict)
// require_role($pdo, ['admin','project_manager','accounting','staff']);

$projects = require __DIR__ . '/public/data/projects.php';

$uploadDir = __DIR__ . '/storage/uploads/project_files';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);

$flash = '';

$action = (string)($_POST['action'] ?? '');
if ($action === 'create_client') {
  $name  = trim((string)($_POST['name'] ?? ''));
  $email = trim((string)($_POST['email'] ?? ''));
  $phone = trim((string)($_POST['phone'] ?? ''));
  $pass  = (string)($_POST['password'] ?? '');
  if ($name && $email && $pass) {
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $st = $pdo->prepare("INSERT INTO website_clients (name,email,phone,password_hash) VALUES (?,?,?,?)");
    try {
      $st->execute([$name,$email,$phone ?: null,$hash]);
      $flash = 'Client created.';
    } catch (Throwable $e) {
      $flash = 'Error: ' . $e->getMessage();
    }
  } else $flash = 'Name, email, and password are required.';
}

if ($action === 'assign_project') {
  $client_id = (int)($_POST['client_id'] ?? 0);
  $project_id = trim((string)($_POST['project_id'] ?? ''));
  if ($client_id && $project_id) {
    $st = $pdo->prepare("INSERT IGNORE INTO website_client_projects (client_id, project_id) VALUES (?,?)");
    $st->execute([$client_id, $project_id]);
    $flash = 'Project assigned to client.';
  } else $flash = 'Select client and project.';
}

if ($action === 'set_status') {
  $project_id = trim((string)($_POST['project_id'] ?? ''));
  $status_label = trim((string)($_POST['status_label'] ?? 'Ongoing'));
  $progress = max(0, min(100, (int)($_POST['progress_percent'] ?? 0)));
  $start = trim((string)($_POST['start_date'] ?? ''));
  $target = trim((string)($_POST['target_end_date'] ?? ''));
  $note = trim((string)($_POST['note'] ?? ''));
  if ($project_id) {
    $st = $pdo->prepare("INSERT INTO website_project_status (project_id,status_label,progress_percent,start_date,target_end_date,note)
      VALUES (?,?,?,?,?,?)
      ON DUPLICATE KEY UPDATE
        status_label=VALUES(status_label),
        progress_percent=VALUES(progress_percent),
        start_date=VALUES(start_date),
        target_end_date=VALUES(target_end_date),
        note=VALUES(note)");
    $st->execute([
      $project_id,
      $status_label ?: 'Ongoing',
      $progress,
      $start ?: null,
      $target ?: null,
      $note ?: null
    ]);
    $flash = 'Project status updated.';
  } else $flash = 'Project is required.';
}

if ($action === 'add_payment') {
  $project_id = trim((string)($_POST['project_id'] ?? ''));
  $due = trim((string)($_POST['due_date'] ?? ''));
  $label = trim((string)($_POST['label'] ?? ''));
  $amount = (float)($_POST['amount'] ?? 0);
  $status = (string)($_POST['status'] ?? 'pending');
  $paid_at = trim((string)($_POST['paid_at'] ?? ''));
  $note = trim((string)($_POST['note'] ?? ''));
  if ($project_id && $label) {
    $st = $pdo->prepare("INSERT INTO website_project_payments (project_id,due_date,label,amount,status,paid_at,note) VALUES (?,?,?,?,?,?,?)");
    $st->execute([$project_id, $due ?: null, $label, $amount, ($status==='paid'?'paid':'pending'), $paid_at ?: null, $note ?: null]);
    $flash = 'Payment item added.';
  } else $flash = 'Project and milestone label are required.';
}

if ($action === 'upload_file') {
  $project_id = trim((string)($_POST['project_id'] ?? ''));
  $kind = trim((string)($_POST['kind'] ?? 'Document'));
  $display = trim((string)($_POST['display_name'] ?? ''));
  if ($project_id && $display && !empty($_FILES['file']['name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
    $orig = (string)$_FILES['file']['name'];
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $stored = $project_id . '_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)),0,8) . ($ext?'.'.$ext:'');
    $dest = $uploadDir . '/' . $stored;
    if (move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
      $mime = function_exists('mime_content_type') ? (mime_content_type($dest) ?: null) : null;
      $size = filesize($dest) ?: null;
      $st = $pdo->prepare("INSERT INTO website_project_files (project_id, kind, display_name, stored_name, mime, size_bytes) VALUES (?,?,?,?,?,?)");
      $st->execute([$project_id, $kind ?: 'Document', $display, $stored, $mime, $size]);
      $flash = 'File uploaded.';
    } else $flash = 'Upload failed.';
  } else $flash = 'Project, display name and a file are required.';
}

// Load clients
$clients = $pdo->query("SELECT id,name,email,phone,created_at FROM website_clients ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Client Portal Admin • Maorin Builders</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container">
    <a class="navbar-brand" href="index.php">Maorin Builders</a>
    <div class="ms-auto d-flex gap-2">
      <a class="btn btn-sm btn-outline-light" href="inquiries.php">Inquiries</a>
      <a class="btn btn-sm btn-outline-light" href="logout.php">Logout</a>
    </div>
  </div>
</nav>
<div class="container py-4">
  <h3 class="mb-3">Client Portal Admin</h3>

  <?php if ($flash): ?>
    <div class="alert alert-info"><?= htmlspecialchars($flash) ?></div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body">
          <h5>Create Client</h5>
          <form method="post" class="vstack gap-2">
            <input type="hidden" name="action" value="create_client">
            <input class="form-control" name="name" placeholder="Client name" required>
            <input class="form-control" name="email" placeholder="Email" type="email" required>
            <input class="form-control" name="phone" placeholder="Phone (optional)">
            <input class="form-control" name="password" placeholder="Temp password" required>
            <button class="btn btn-dark">Create</button>
          </form>
          <div class="small text-muted mt-2">Give the email+password to your client for login at <code>/client/login.php</code>.</div>
        </div>
      </div>

      <div class="card border-0 shadow-sm rounded-4 mt-3">
        <div class="card-body">
          <h5>Assign Project Access</h5>
          <form method="post" class="vstack gap-2">
            <input type="hidden" name="action" value="assign_project">
            <select class="form-select" name="client_id" required>
              <option value="">Select client…</option>
              <?php foreach ($clients as $c): ?>
                <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name'].' • '.$c['email']) ?></option>
              <?php endforeach; ?>
            </select>
            <select class="form-select" name="project_id" required>
              <option value="">Select project…</option>
              <?php foreach ($projects as $p): ?>
                <option value="<?= htmlspecialchars($p['id']) ?>"><?= htmlspecialchars($p['title'].' ('.$p['id'].')') ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-dark">Assign</button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body">
          <h5>Project Status</h5>
          <form method="post" class="row g-2 align-items-end">
            <input type="hidden" name="action" value="set_status">
            <div class="col-md-4">
              <label class="form-label">Project</label>
              <select class="form-select" name="project_id" required>
                <option value="">Select…</option>
                <?php foreach ($projects as $p): ?>
                  <option value="<?= htmlspecialchars($p['id']) ?>"><?= htmlspecialchars($p['title'].' ('.$p['id'].')') ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Status</label>
              <select class="form-select" name="status_label">
                <option>Ongoing</option>
                <option>Completed</option>
                <option>On Hold</option>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label">Progress %</label>
              <input class="form-control" type="number" name="progress_percent" min="0" max="100" value="0">
            </div>
            <div class="col-md-3">
              <label class="form-label">Start date</label>
              <input class="form-control" type="date" name="start_date">
            </div>
            <div class="col-md-3">
              <label class="form-label">Target end</label>
              <input class="form-control" type="date" name="target_end_date">
            </div>
            <div class="col-12">
              <label class="form-label">Note</label>
              <textarea class="form-control" name="note" rows="2" placeholder="Optional status note visible to client"></textarea>
            </div>
            <div class="col-12">
              <button class="btn btn-dark">Save Status</button>
            </div>
          </form>
        </div>
      </div>

      <div class="card border-0 shadow-sm rounded-4 mt-3">
        <div class="card-body">
          <h5>Upload Project File</h5>
          <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
            <input type="hidden" name="action" value="upload_file">
            <div class="col-md-4">
              <label class="form-label">Project</label>
              <select class="form-select" name="project_id" required>
                <option value="">Select…</option>
                <?php foreach ($projects as $p): ?>
                  <option value="<?= htmlspecialchars($p['id']) ?>"><?= htmlspecialchars($p['title'].' ('.$p['id'].')') ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Kind</label>
              <select class="form-select" name="kind">
                <option>Contract</option>
                <option>Plan</option>
                <option>Invoice</option>
                <option>Receipt</option>
                <option>Document</option>
              </select>
            </div>
            <div class="col-md-5">
              <label class="form-label">Display name</label>
              <input class="form-control" name="display_name" placeholder="e.g. Contract (Signed)" required>
            </div>
            <div class="col-md-8">
              <input class="form-control" type="file" name="file" required>
            </div>
            <div class="col-md-4">
              <button class="btn btn-dark w-100">Upload</button>
            </div>
          </form>
          <div class="small text-muted mt-2">Files are securely served via <code>/client/download.php</code> only for assigned clients.</div>
        </div>
      </div>

      <div class="card border-0 shadow-sm rounded-4 mt-3">
        <div class="card-body">
          <h5>Add Payment Schedule Item</h5>
          <form method="post" class="row g-2 align-items-end">
            <input type="hidden" name="action" value="add_payment">
            <div class="col-md-4">
              <label class="form-label">Project</label>
              <select class="form-select" name="project_id" required>
                <option value="">Select…</option>
                <?php foreach ($projects as $p): ?>
                  <option value="<?= htmlspecialchars($p['id']) ?>"><?= htmlspecialchars($p['title'].' ('.$p['id'].')') ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Due date</label>
              <input class="form-control" type="date" name="due_date">
            </div>
            <div class="col-md-5">
              <label class="form-label">Milestone label</label>
              <input class="form-control" name="label" placeholder="e.g. 30% Downpayment" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">Amount</label>
              <input class="form-control" type="number" step="0.01" name="amount" value="0">
            </div>
            <div class="col-md-3">
              <label class="form-label">Status</label>
              <select class="form-select" name="status">
                <option value="pending">pending</option>
                <option value="paid">paid</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Paid at</label>
              <input class="form-control" type="date" name="paid_at">
            </div>
            <div class="col-md-3">
              <label class="form-label">Note</label>
              <input class="form-control" name="note" placeholder="optional">
            </div>
            <div class="col-12">
              <button class="btn btn-dark">Add</button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </div>

  <hr class="my-4">

  <h5 class="mb-2">Clients</h5>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Created</th></tr></thead>
      <tbody>
      <?php foreach ($clients as $c): ?>
        <tr>
          <td><?= (int)$c['id'] ?></td>
          <td><?= htmlspecialchars($c['name']) ?></td>
          <td><?= htmlspecialchars($c['email']) ?></td>
          <td><?= htmlspecialchars((string)$c['phone']) ?></td>
          <td><?= htmlspecialchars(substr((string)$c['created_at'],0,10)) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
