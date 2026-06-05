<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/helpers.php';
redirect_if_not_logged_in();
require_permission($pdo, 'view_inquiries');

// Ensure table exists (so page doesn't crash on fresh installs)
$pdo->exec(
  "CREATE TABLE IF NOT EXISTS website_inquiries (
     id INT AUTO_INCREMENT PRIMARY KEY,
     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
     name VARCHAR(160) NOT NULL,
     phone VARCHAR(64) NULL,
     email VARCHAR(160) NULL,
     project_type VARCHAR(80) NULL,
     location VARCHAR(160) NULL,
     budget VARCHAR(80) NULL,
     message TEXT NOT NULL,
     status VARCHAR(32) NOT NULL DEFAULT 'new'
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$status = trim((string)($_GET['status'] ?? ''));
$where = '';
$bind = [];
if ($status !== '') {
  $where = 'WHERE status = ?';
  $bind[] = $status;
}

$st = $pdo->prepare("SELECT * FROM website_inquiries $where ORDER BY id DESC LIMIT 500");
$st->execute($bind);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/templates/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
  <div>
    <h3 class="mb-1">Website Inquiries</h3>
    <div class="text-muted">Leads submitted from <code>/public/contact.php</code></div>
  </div>
  <div class="btn-group">
    <a class="btn btn-outline-secondary btn-sm <?= $status===''?'active':'' ?>" href="inquiries.php">All</a>
    <a class="btn btn-outline-secondary btn-sm <?= $status==='new'?'active':'' ?>" href="inquiries.php?status=new">New</a>
    <a class="btn btn-outline-secondary btn-sm <?= $status==='contacted'?'active':'' ?>" href="inquiries.php?status=contacted">Contacted</a>
    <a class="btn btn-outline-secondary btn-sm <?= $status==='closed'?'active':'' ?>" href="inquiries.php?status=closed">Closed</a>
  </div>
</div>

<?php if (!$rows): ?>
  <div class="alert alert-info">No inquiries yet.</div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table table-striped align-middle">
      <thead>
        <tr>
          <th>Date</th>
          <th>Name</th>
          <th>Contact</th>
          <th>Project</th>
          <th>Location</th>
          <th>Status</th>
          <th style="width:120px"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= htmlspecialchars((string)$r['created_at']) ?></td>
            <td class="fw-semibold"><?= htmlspecialchars((string)$r['name']) ?></td>
            <td>
              <div><?= htmlspecialchars((string)($r['phone'] ?? '')) ?></div>
              <div class="text-muted small"><?= htmlspecialchars((string)($r['email'] ?? '')) ?></div>
            </td>
            <td>
              <div><?= htmlspecialchars((string)($r['project_type'] ?? '')) ?></div>
              <div class="text-muted small"><?= htmlspecialchars((string)($r['budget'] ?? '')) ?></div>
            </td>
            <td><?= htmlspecialchars((string)($r['location'] ?? '')) ?></td>
            <td><span class="badge bg-<?= ($r['status']==='new'?'primary':($r['status']==='contacted'?'warning':'secondary')) ?>"><?= htmlspecialchars((string)$r['status']) ?></span></td>
            <td>
              <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#msg<?= (int)$r['id'] ?>">View</button>
            </td>
          </tr>
          <tr class="collapse" id="msg<?= (int)$r['id'] ?>">
            <td colspan="7">
              <div class="p-3 bg-light rounded">
                <div class="text-muted small mb-2">Message</div>
                <div style="white-space:pre-wrap"><?= htmlspecialchars((string)$r['message']) ?></div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/templates/footer.php'; ?>
