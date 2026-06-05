<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
require __DIR__ . '/../helpers.php';
ensure_client_portal_tables($pdo);
redirect_client_if_not_logged_in();

$projectsData = require __DIR__ . '/../public/data/projects.php';

$st = $pdo->prepare("SELECT project_id FROM website_client_projects WHERE client_id=? ORDER BY created_at DESC");
$st->execute([(int)$_SESSION['client_id']]);
$projectIds = $st->fetchAll(PDO::FETCH_COLUMN);

$my = [];
foreach ($projectIds as $pid) {
  foreach ($projectsData as $p) {
    if (($p['id'] ?? '') === $pid) { $my[] = $p; break; }
  }
}

include __DIR__ . '/templates/header.php';
?>
<h3 class="mb-3">My Projects</h3>

<?php if (!$my): ?>
  <div class="alert alert-info">No projects assigned to your account yet.</div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($my as $p): 
      $pid = (string)$p['id'];
      $st2 = $pdo->prepare("SELECT status_label, progress_percent, start_date, target_end_date FROM website_project_status WHERE project_id=? LIMIT 1");
      $st2->execute([$pid]);
      $ps = $st2->fetch(PDO::FETCH_ASSOC) ?: ['status_label'=>$p['status'] ?? 'Ongoing','progress_percent'=>0,'start_date'=>null,'target_end_date'=>null];
      $img = $p['image'] ?? '';
      ?>
      <div class="col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
          <?php if ($img): ?>
            <img src="../<?= htmlspecialchars($img) ?>" alt="" style="height:180px;object-fit:cover;width:100%">
          <?php endif; ?>
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <h5 class="mb-1"><?= htmlspecialchars($p['title'] ?? 'Project') ?></h5>
                <div class="muted small"><?= htmlspecialchars($p['location'] ?? '') ?></div>
              </div>
              <span class="badge text-bg-secondary"><?= htmlspecialchars((string)$ps['status_label']) ?></span>
            </div>
            <div class="mt-3">
              <div class="d-flex justify-content-between small muted">
                <span>Progress</span><span><?= (int)$ps['progress_percent'] ?>%</span>
              </div>
              <div class="progress" role="progressbar" aria-valuenow="<?= (int)$ps['progress_percent'] ?>" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar" style="width: <?= (int)$ps['progress_percent'] ?>%"></div>
              </div>
            </div>
            <div class="mt-3 d-flex gap-2">
              <a class="btn btn-dark btn-sm" href="project.php?project_id=<?= urlencode($pid) ?>">Open</a>
              <a class="btn btn-outline-dark btn-sm" href="../public/project_view.php?id=<?= urlencode($pid) ?>" target="_blank">Public Page</a>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/templates/footer.php'; ?>
