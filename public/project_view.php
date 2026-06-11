<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';
require __DIR__ . '/../helpers.php';

ensure_content_catalog_tables($pdo);
require_feature($pdo, 'public_site');

if (!function_exists('project_media_url')) {
  function project_media_url(?string $path, string $fallback = ''): string {
    $path = trim((string)$path);
    if ($path === '') {
      return $fallback;
    }
    return pub_url('/' . ltrim($path, '/'));
  }
}

$id = (string)($_GET['id'] ?? '');
$project = null;

if ($id !== '') {
  $st = $pdo->prepare("SELECT * FROM website_projects WHERE slug = ? OR CAST(id AS CHAR) = ? LIMIT 1");
  $st->execute([$id, $id]);
  $project = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

if (!$project) {
  $projects = require __DIR__ . '/data/projects.php';
  foreach ($projects as $p) {
    if (($p['id'] ?? '') === $id || ($p['slug'] ?? '') === $id) {
      $project = $p;
      break;
    }
  }
}

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

$updates = [];
try {
  $projectKey = (string)($project['slug'] ?? $project['id'] ?? $id);
  $st = $pdo->prepare("SELECT * FROM website_project_updates WHERE project_id=? ORDER BY created_at DESC LIMIT 30");
  $st->execute([$projectKey]);
  $updates = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $updates = [];
}

if (!$project) {
  $title = 'Project not found - Maorin Builders';
  require __DIR__ . '/templates/header.php';
  $back = htmlspecialchars(pub_url('/public/projects.php'), ENT_QUOTES, 'UTF-8');
  echo '<div class="container py-5"><div class="alert alert-warning">Project not found. <a href="' . $back . '">Back to projects</a>.</div></div>';
  require __DIR__ . '/templates/footer.php';
  exit;
}

$title = htmlspecialchars((string)$project['title'], ENT_QUOTES, 'UTF-8') . ' - Maorin Builders';
require __DIR__ . '/templates/header.php';

$materials = $project['materials'] ?? [];
if (is_string($materials) && $materials !== '') {
  $decoded = json_decode($materials, true);
  $materials = is_array($decoded) ? $decoded : array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $materials) ?: [])));
}
if (!is_array($materials)) {
  $materials = [];
}

$gallery = [];
if (!empty($project['id'])) {
  $stGallery = $pdo->prepare("SELECT path FROM website_project_media WHERE project_id = ? AND media_type = 'gallery' ORDER BY created_at DESC, id DESC");
  $stGallery->execute([(int)$project['id']]);
  $gallery = array_map(fn($r) => (string)$r['path'], $stGallery->fetchAll(PDO::FETCH_ASSOC) ?: []);
}
if (!$gallery) {
  $gallery = $project['gallery'] ?? [];
  if (is_string($gallery) && $gallery !== '') {
    $decoded = json_decode($gallery, true);
    $gallery = is_array($decoded) ? $decoded : array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $gallery) ?: [])));
  }
  if (!is_array($gallery)) {
    $gallery = [];
  }
}

$before = (string)($project['before_image'] ?? $project['before'] ?? '');
$after = (string)($project['after_image'] ?? $project['after'] ?? '');
$cover = (string)($project['cover'] ?? '');
$coverUrl = project_media_url($cover, pub_url('/assets/img/projects/residence.svg'));
$beforeUrl = project_media_url($before, pub_url('/assets/img/projects/p1_before.svg'));
$afterUrl = project_media_url($after, pub_url('/assets/img/projects/p1_after.svg'));
?>

<div class="container py-5">
  <a class="text-decoration-none" href="<?= htmlspecialchars(pub_url('/public/projects.php'), ENT_QUOTES, 'UTF-8') ?>">&larr; Back to projects</a>
  <div class="row g-4 align-items-start mt-1">
    <div class="col-lg-7">
      <?php if ($before && $after): ?>
        <div class="mb-beforeafter shadow-sm" data-beforeafter>
          <img class="ba-img ba-after" src="<?= htmlspecialchars($afterUrl, ENT_QUOTES, 'UTF-8') ?>" alt="After">
          <div class="ba-before-wrap" data-ba-before>
            <img class="ba-img ba-before" src="<?= htmlspecialchars($beforeUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Before">
          </div>
          <input class="ba-range" type="range" min="0" max="100" value="50" aria-label="Before/After slider" data-ba-range>
          <div class="ba-line" data-ba-line></div>
          <button type="button" class="ba-label ba-label-before" data-full-image="<?= htmlspecialchars($beforeUrl, ENT_QUOTES, 'UTF-8') ?>" data-full-title="Before">Before</button>
          <button type="button" class="ba-label ba-label-after" data-full-image="<?= htmlspecialchars($afterUrl, ENT_QUOTES, 'UTF-8') ?>" data-full-title="After">After</button>
        </div>
        <div class="small mb-muted mt-2">Drag the slider to compare before vs after.</div>
      <?php else: ?>
        <img class="mb-portfolio-img shadow-sm" src="<?= htmlspecialchars($coverUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string)$project['title'], ENT_QUOTES, 'UTF-8') ?>" onerror="this.onerror=null;this.src='<?= htmlspecialchars(pub_url('/assets/img/projects/residence.svg'), ENT_QUOTES, 'UTF-8') ?>';">
      <?php endif; ?>
    </div>
    <div class="col-lg-5">
      <h1 class="h2 fw-bold mb-2"><?= htmlspecialchars((string)$project['title'], ENT_QUOTES, 'UTF-8') ?></h1>
      <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <span class="badge text-bg-light border"><?= htmlspecialchars((string)($project['type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
        <span class="badge text-bg-light border"><?= htmlspecialchars((string)($project['location'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
        <span class="badge text-bg-light border"><?= htmlspecialchars((string)($project['year'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
        <?php if (!empty($project['status'])): ?>
          <span class="badge <?= ($project['status'] === 'Ongoing') ? 'text-bg-warning' : 'text-bg-success' ?>"><?= htmlspecialchars((string)$project['status'], ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
      </div>
      <p class="mb-4"><?= htmlspecialchars((string)($project['summary'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>

      <?php if (!empty($materials)): ?>
        <div class="card border-0 shadow-sm rounded-4 mb-3">
          <div class="card-body p-4">
            <div class="h5 mb-2">Materials used</div>
            <ul class="mb-0">
              <?php foreach ($materials as $m): ?>
                <li><?= htmlspecialchars((string)$m, ENT_QUOTES, 'UTF-8') ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($updates)): ?>
        <div class="card border-0 shadow-sm rounded-4 mb-3">
          <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div class="h5 mb-0">Progress Updates</div>
              <div class="small text-muted">Latest <?= (int)count($updates) ?></div>
            </div>
            <div class="vstack gap-3">
              <?php foreach ($updates as $u): ?>
                <div class="p-3 rounded-3" style="background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08);">
                  <div class="d-flex justify-content-between gap-3">
                    <div class="fw-semibold"><?= htmlspecialchars((string)$u['title'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="small text-muted"><?= htmlspecialchars(date('M j, Y', strtotime((string)$u['created_at'])), ENT_QUOTES, 'UTF-8') ?></div>
                  </div>
                  <?php if (!empty($u['photo_path'])): ?>
                    <?php $updatePhoto = project_media_url((string)$u['photo_path'], pub_url('/assets/img/projects/residence.svg')); ?>
                    <img src="<?= htmlspecialchars($updatePhoto, ENT_QUOTES, 'UTF-8') ?>" class="img-fluid rounded mt-2" style="max-height:420px;object-fit:cover" alt="" onerror="this.onerror=null;this.src='<?= htmlspecialchars(pub_url('/assets/img/projects/residence.svg'), ENT_QUOTES, 'UTF-8') ?>';">
                  <?php endif; ?>
                  <?php if (!empty($u['note'])): ?>
                    <div class="mt-2"><?= nl2br(htmlspecialchars((string)$u['note'], ENT_QUOTES, 'UTF-8')) ?></div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($gallery)): ?>
        <div class="card border-0 shadow-sm rounded-4 mb-3">
          <div class="card-body p-4">
            <div class="h5 mb-3">Gallery</div>
            <div class="row g-3">
              <?php foreach ($gallery as $img): ?>
                <div class="col-6">
                  <?php $imgUrl = project_media_url((string)$img, pub_url('/assets/img/projects/residence.svg')); ?>
                  <a href="<?= htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                    <img class="mb-portfolio-img" src="<?= htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Gallery image" onerror="this.onerror=null;this.src='<?= htmlspecialchars(pub_url('/assets/img/projects/residence.svg'), ENT_QUOTES, 'UTF-8') ?>';">
                  </a>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
          <div class="h5 mb-2">Want something similar?</div>
          <div class="mb-muted mb-3">Send your location, scope, and target schedule. We'll reply with next steps.</div>
          <a class="btn btn-primary w-100" href="<?= htmlspecialchars(pub_url('/public/contact.php'), ENT_QUOTES, 'UTF-8') ?>">Request a Quote</a>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="projectImageModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header">
        <h5 class="modal-title" id="projectImageModalTitle">Image</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <img id="projectImageModalImg" src="" alt="" class="w-100" style="max-height:80vh; object-fit:contain; background:#0b1220;">
      </div>
    </div>
  </div>
</div>

<script>
(() => {
  const modalEl = document.getElementById('projectImageModal');
  const imgEl = document.getElementById('projectImageModalImg');
  const titleEl = document.getElementById('projectImageModalTitle');
  if (!modalEl || !imgEl || !titleEl || typeof bootstrap === 'undefined') return;

  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

  document.querySelectorAll('[data-full-image]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const src = btn.getAttribute('data-full-image') || '';
      if (!src) return;
      imgEl.src = src;
      imgEl.alt = btn.getAttribute('data-full-title') || 'Project image';
      titleEl.textContent = btn.getAttribute('data-full-title') || 'Image';
      modal.show();
    });
  });

  modalEl.addEventListener('hidden.bs.modal', () => {
    imgEl.src = '';
  });
})();
</script>

<?php require __DIR__ . '/templates/footer.php'; ?>
