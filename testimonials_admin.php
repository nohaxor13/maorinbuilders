<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/helpers.php';
redirect_if_not_logged_in();
require_permission($pdo, 'manage_company_content');
require_feature($pdo, 'company_content');

$pdo->exec(
  "CREATE TABLE IF NOT EXISTS website_testimonials (
     id INT AUTO_INCREMENT PRIMARY KEY,
     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
     client_name VARCHAR(160) NOT NULL,
     project_type VARCHAR(80) NULL,
     rating TINYINT NOT NULL DEFAULT 5,
     message TEXT NOT NULL,
     project_ref VARCHAR(160) NULL,
     is_approved TINYINT(1) NOT NULL DEFAULT 0
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$act = $_POST['action'] ?? '';
$id  = (int)($_POST['id'] ?? 0);

if ($act === 'approve' && $id>0) {
  $st = $pdo->prepare("UPDATE website_testimonials SET is_approved=1 WHERE id=?");
  $st->execute([$id]);
  header("Location: testimonials_admin.php"); exit;
}
if ($act === 'delete' && $id>0) {
  $st = $pdo->prepare("DELETE FROM website_testimonials WHERE id=?");
  $st->execute([$id]);
  header("Location: testimonials_admin.php"); exit;
}
if ($act === 'create') {
  $name = trim((string)($_POST['client_name'] ?? ''));
  $ptype = trim((string)($_POST['project_type'] ?? ''));
  $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
  $msg = trim((string)($_POST['message'] ?? ''));
  $pref = trim((string)($_POST['project_ref'] ?? ''));
  if ($name !== '' && $msg !== '') {
    $st = $pdo->prepare("INSERT INTO website_testimonials (client_name, project_type, rating, message, project_ref, is_approved) VALUES (?,?,?,?,?,0)");
    $st->execute([$name,$ptype,$rating,$msg,$pref]);
  }
  header("Location: testimonials_admin.php"); exit;
}

$pending = $pdo->query("SELECT * FROM website_testimonials WHERE is_approved=0 ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$approved = $pdo->query("SELECT * FROM website_testimonials WHERE is_approved=1 ORDER BY created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

$title = "Testimonials Admin";
include __DIR__ . '/templates/header.php';
?>
<div class="container py-4">
  <h1 class="h3 mb-3">Testimonials</h1>

  <div class="card mb-4">
    <div class="card-header fw-semibold">Add new (pending approval)</div>
    <div class="card-body">
      <form method="post" class="row g-3">
        <input type="hidden" name="action" value="create">
        <div class="col-md-4">
          <label class="form-label">Client name</label>
          <input class="form-control" name="client_name" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Project type</label>
          <input class="form-control" name="project_type" placeholder="Residential / Commercial / Renovation...">
        </div>
        <div class="col-md-2">
          <label class="form-label">Rating</label>
          <select class="form-select" name="rating">
            <option>5</option><option>4</option><option>3</option><option>2</option><option>1</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Project ref</label>
          <input class="form-control" name="project_ref" placeholder="optional">
        </div>
        <div class="col-12">
          <label class="form-label">Message</label>
          <textarea class="form-control" name="message" rows="3" required></textarea>
        </div>
        <div class="col-12">
          <button class="btn btn-primary" type="submit">Save</button>
          <a class="btn btn-outline-secondary" href="public/testimonials.php" target="_blank">View public page</a>
        </div>
      </form>
    </div>
  </div>

  <h2 class="h5">Pending</h2>
  <?php if (!$pending): ?>
    <div class="text-muted mb-4">No pending testimonials.</div>
  <?php else: ?>
    <div class="table-responsive mb-5">
      <table class="table table-striped align-middle">
        <thead><tr><th>Date</th><th>Client</th><th>Rating</th><th>Message</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($pending as $t): ?>
          <tr>
            <td class="text-muted small"><?= htmlspecialchars((string)$t['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)$t['client_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= (int)$t['rating'] ?>/5</td>
            <td style="max-width:520px"><?= htmlspecialchars(mb_strimwidth((string)$t['message'],0,140,'…'), ENT_QUOTES, 'UTF-8') ?></td>
            <td class="text-end">
              <form method="post" class="d-inline">
                <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                <button class="btn btn-sm btn-success" name="action" value="approve">Approve</button>
              </form>
              <form method="post" class="d-inline" onsubmit="return confirm('Delete this testimonial?')">
                <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                <button class="btn btn-sm btn-danger" name="action" value="delete">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <h2 class="h5">Approved (latest 50)</h2>
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle">
      <thead><tr><th>Date</th><th>Client</th><th>Rating</th><th>Project</th><th>Message</th></tr></thead>
      <tbody>
      <?php foreach ($approved as $t): ?>
        <tr>
          <td class="text-muted small"><?= htmlspecialchars((string)$t['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string)$t['client_name'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= (int)$t['rating'] ?>/5</td>
          <td><?= htmlspecialchars((string)($t['project_ref'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
          <td style="max-width:620px"><?= htmlspecialchars(mb_strimwidth((string)$t['message'],0,160,'…'), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/templates/footer.php'; ?>
