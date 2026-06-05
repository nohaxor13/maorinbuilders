<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/helpers.php';
redirect_if_not_logged_in();
require_permission($pdo, 'manage_company_content');

$pdo->exec(
  "CREATE TABLE IF NOT EXISTS website_project_updates (
     id INT AUTO_INCREMENT PRIMARY KEY,
     project_id VARCHAR(64) NOT NULL,
     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
     title VARCHAR(160) NOT NULL,
     note TEXT NULL,
     photo_path VARCHAR(255) NULL
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$projects = require __DIR__ . '/public/data/projects.php';

$uploadDir = __DIR__ . '/storage/uploads/projects';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);

if (($_POST['action'] ?? '') === 'create') {
  $project_id = trim((string)($_POST['project_id'] ?? ''));
  $title = trim((string)($_POST['title'] ?? ''));
  $note  = trim((string)($_POST['note'] ?? ''));

  $photo_path = null;
  if (!empty($_FILES['photo']['name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
    $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) $ext = 'jpg';
    $fname = $project_id . '_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)),0,8) . '.' . $ext;
    $dest = $uploadDir . '/' . $fname;
    if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
      $photo_path = 'storage/uploads/projects/' . $fname;
    }
  }

  if ($project_id !== '' && $title !== '') {
    $st = $pdo->prepare("INSERT INTO website_project_updates (project_id,title,note,photo_path) VALUES (?,?,?,?)");
    $st->execute([$project_id,$title,$note,$photo_path]);
  }
  header("Location: project_updates_admin.php?project_id=" . urlencode($project_id)); exit;
}

if (($_POST['action'] ?? '') === 'delete') {
  $id = (int)($_POST['id'] ?? 0);
  if ($id>0) {
    $st = $pdo->prepare("SELECT photo_path FROM website_project_updates WHERE id=?");
    $st->execute([$id]);
    $p = $st->fetchColumn();
    if ($p && is_file(__DIR__ . '/' . $p)) @unlink(__DIR__ . '/' . $p);

    $st = $pdo->prepare("DELETE FROM website_project_updates WHERE id=?");
    $st->execute([$id]);
  }
  $pid = trim((string)($_POST['project_id'] ?? ''));
  header("Location: project_updates_admin.php?project_id=" . urlencode($pid)); exit;
}

$filterProject = trim((string)($_GET['project_id'] ?? ($projects[0]['id'] ?? '')));

$st = $pdo->prepare("SELECT * FROM website_project_updates WHERE project_id=? ORDER BY created_at DESC");
$st->execute([$filterProject]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$title = "Project Updates";
include __DIR__ . '/templates/header.php';
?>
<div class="container py-4">
  <h1 class="h3 mb-3">Project Progress Updates</h1>

  <form method="get" class="row g-2 align-items-end mb-4">
    <div class="col-md-6">
      <label class="form-label">Project</label>
      <select class="form-select" name="project_id" onchange="this.form.submit()">
        <?php foreach ($projects as $p): ?>
          <option value="<?= htmlspecialchars((string)$p['id'], ENT_QUOTES, 'UTF-8') ?>" <?= ($filterProject===$p['id']?'selected':'') ?>>
            <?= htmlspecialchars((string)$p['title'], ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-6 text-md-end">
      <a class="btn btn-outline-secondary" target="_blank" href="public/project_view.php?id=<?= urlencode($filterProject) ?>">View public</a>
    </div>
  </form>

  <div class="card mb-4">
    <div class="card-header fw-semibold">Add update</div>
    <div class="card-body">
      <form method="post" enctype="multipart/form-data" class="row g-3">
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="project_id" value="<?= htmlspecialchars($filterProject, ENT_QUOTES, 'UTF-8') ?>">
        <div class="col-md-6">
          <label class="form-label">Title</label>
          <input class="form-control" name="title" placeholder="e.g. Slab pour completed" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Photo (optional)</label>
          <input class="form-control" type="file" name="photo" accept="image/*">
        </div>
        <div class="col-12">
          <label class="form-label">Note</label>
          <textarea class="form-control" name="note" rows="3"></textarea>
        </div>
        <div class="col-12">
          <button class="btn btn-primary" type="submit">Save update</button>
        </div>
      </form>
    </div>
  </div>

  <h2 class="h5">Latest updates</h2>
  <?php if (!$rows): ?>
    <div class="text-muted">No updates yet for this project.</div>
  <?php else: ?>
    <div class="list-group">
      <?php foreach ($rows as $u): ?>
        <div class="list-group-item">
          <div class="d-flex justify-content-between gap-3">
            <div>
              <div class="fw-semibold"><?= htmlspecialchars((string)$u['title'], ENT_QUOTES, 'UTF-8') ?></div>
              <div class="text-muted small"><?= htmlspecialchars(date('F j, Y g:i A', strtotime((string)$u['created_at'])), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <form method="post" onsubmit="return confirm('Delete this update?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <input type="hidden" name="project_id" value="<?= htmlspecialchars($filterProject, ENT_QUOTES, 'UTF-8') ?>">
              <button class="btn btn-sm btn-danger">Delete</button>
            </form>
          </div>
          <?php if (!empty($u['photo_path'])): ?>
            <div class="mt-3">
              <img src="<?= htmlspecialchars($u['photo_path'], ENT_QUOTES, 'UTF-8') ?>" class="img-fluid rounded" style="max-height:360px;object-fit:cover" alt="">
            </div>
          <?php endif; ?>
          <?php if (!empty($u['note'])): ?>
            <div class="mt-2"><?= nl2br(htmlspecialchars((string)$u['note'], ENT_QUOTES, 'UTF-8')) ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/templates/footer.php'; ?>
