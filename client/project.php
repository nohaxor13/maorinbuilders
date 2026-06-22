<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
require __DIR__ . '/../helpers.php';
ensure_client_portal_tables($pdo);
require_feature($pdo, 'client_portal');
redirect_client_if_not_logged_in();

$project_id = trim((string)($_GET['project_id'] ?? ''));
if ($project_id === '') { http_response_code(400); die('Missing project_id'); }

$client_id = (int)$_SESSION['client_id'];
if (!client_can_access_project($pdo, $client_id, $project_id)) {
  http_response_code(403);
  die('Forbidden');
}

$projectsData = require __DIR__ . '/../public/data/projects.php';
$project = null;
foreach ($projectsData as $p) {
  if (($p['id'] ?? '') === $project_id) { $project = $p; break; }
}
if (!$project) { http_response_code(404); die('Project not found'); }

$st = $pdo->prepare("SELECT * FROM website_project_status WHERE project_id=? LIMIT 1");
$st->execute([$project_id]);
$status = $st->fetch(PDO::FETCH_ASSOC) ?: [
  'status_label'=>($project['status'] ?? 'Ongoing'),
  'progress_percent'=>0,
  'start_date'=>null,
  'target_end_date'=>null,
  'note'=>null
];

$st = $pdo->prepare("SELECT id, created_at, title, note, photo_path FROM website_project_updates WHERE project_id=? ORDER BY created_at DESC");
$st->execute([$project_id]);
$updates = $st->fetchAll(PDO::FETCH_ASSOC);

$st = $pdo->prepare("SELECT id, kind, display_name, uploaded_at FROM website_project_files WHERE project_id=? ORDER BY uploaded_at DESC");
$st->execute([$project_id]);
$files = $st->fetchAll(PDO::FETCH_ASSOC);

$st = $pdo->prepare("SELECT id, due_date, label, amount, status, paid_at, note FROM website_project_payments WHERE project_id=? ORDER BY COALESCE(due_date,'9999-12-31') ASC, id ASC");
$st->execute([$project_id]);
$payments = $st->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/templates/header.php';
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div>
    <h3 class="mb-1"><?= htmlspecialchars($project['title'] ?? 'Project') ?></h3>
    <div class="muted"><?= htmlspecialchars($project['location'] ?? '') ?> • <?= htmlspecialchars($project['type'] ?? '') ?></div>
  </div>
  <div class="text-end">
    <span class="badge text-bg-secondary fs-6"><?= htmlspecialchars((string)$status['status_label']) ?></span>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="kpi">
      <div class="muted small">Progress</div>
      <div class="d-flex justify-content-between align-items-center">
        <div class="fs-3 fw-semibold"><?= (int)$status['progress_percent'] ?>%</div>
        <div class="small muted">Updated: <?= htmlspecialchars((string)($status['last_update'] ?? '')) ?></div>
      </div>
      <div class="progress mt-2">
        <div class="progress-bar" style="width: <?= (int)$status['progress_percent'] ?>%"></div>
      </div>
      <div class="mt-3 small">
        <div><span class="muted">Start:</span> <?= htmlspecialchars((string)($status['start_date'] ?? '—')) ?></div>
        <div><span class="muted">Target:</span> <?= htmlspecialchars((string)($status['target_end_date'] ?? '—')) ?></div>
      </div>
      <?php if (!empty($status['note'])): ?>
        <hr>
        <div class="small"><?= nl2br(htmlspecialchars((string)$status['note'])) ?></div>
      <?php endif; ?>
    </div>

    <?php if (feature_is_enabled($pdo, 'client_files')): ?>
    <div class="kpi mt-3">
      <div class="muted small mb-2">Project Files</div>
      <?php if (!$files): ?>
        <div class="muted">No files uploaded yet.</div>
      <?php else: ?>
        <div class="d-flex flex-column gap-2">
          <?php foreach ($files as $f): ?>
            <div class="d-flex justify-content-between align-items-center gap-2">
              <div>
                <div class="fw-semibold"><?= htmlspecialchars($f['display_name']) ?></div>
                <div class="small muted"><?= htmlspecialchars($f['kind']) ?> • <?= htmlspecialchars(substr((string)$f['uploaded_at'],0,10)) ?></div>
              </div>
              <a class="btn btn-sm btn-outline-dark" href="download.php?id=<?= (int)$f['id'] ?>">Download</a>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="col-lg-8">
    <div class="kpi">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0">Progress Updates</h5>
        <a class="btn btn-sm btn-outline-dark" href="../public/project_view.php?id=<?= urlencode($project_id) ?>" target="_blank">View public page</a>
      </div>

      <?php if (!$updates): ?>
        <div class="muted">No updates yet.</div>
      <?php else: ?>
        <div class="vstack gap-3">
          <?php foreach ($updates as $u): ?>
            <div class="border rounded-4 p-3 bg-white">
              <div class="d-flex justify-content-between">
                <div class="fw-semibold"><?= htmlspecialchars($u['title']) ?></div>
                <div class="small muted"><?= htmlspecialchars(substr((string)$u['created_at'],0,16)) ?></div>
              </div>
              <?php if (!empty($u['note'])): ?>
                <div class="mt-2 small"><?= nl2br(htmlspecialchars((string)$u['note'])) ?></div>
              <?php endif; ?>
              <?php if (!empty($u['photo_path'])): ?>
                <div class="mt-3">
                  <img class="prog-photo" src="../<?= htmlspecialchars($u['photo_path']) ?>" alt="Progress photo">
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <?php if (feature_is_enabled($pdo, 'client_payments')): ?>
    <div class="kpi mt-3">
      <h5 class="mb-2">Payment Schedule</h5>
      <?php if (!$payments): ?>
        <div class="muted">No payment schedule uploaded yet.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th>Due</th>
                <th>Milestone</th>
                <th class="text-end">Amount</th>
                <th>Status</th>
                <th>Paid</th>
                <th>Note</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($payments as $pay): ?>
                <tr>
                  <td><?= htmlspecialchars((string)($pay['due_date'] ?? '')) ?></td>
                  <td><?= htmlspecialchars($pay['label']) ?></td>
                  <td class="text-end"><?= number_format((float)$pay['amount'], 2) ?></td>
                  <td>
                    <?php if ($pay['status']==='paid'): ?>
                      <span class="badge text-bg-success">Paid</span>
                    <?php else: ?>
                      <span class="badge text-bg-warning">Pending</span>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars((string)($pay['paid_at'] ?? '')) ?></td>
                  <td class="small muted"><?= htmlspecialchars((string)($pay['note'] ?? '')) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/templates/footer.php'; ?>
