<?php
$title = 'Projects — Maorin Builders';
require __DIR__ . '/templates/header.php';
ensure_content_catalog_tables($pdo);
$projects = $pdo->query("SELECT slug AS id, title, location, year, type, status, cover, summary FROM website_projects ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
if (!$projects) {
  $projects = require __DIR__ . '/data/projects.php';
}

if (!function_exists('project_media_url')) {
  function project_media_url(?string $path, string $fallback = ''): string {
    $path = trim((string)$path);
    if ($path === '') {
      return $fallback;
    }
    return pub_url('/' . ltrim($path, '/'));
  }
}

$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? 'all'));

if ($status && $status !== 'all') {
  $projects = array_values(array_filter($projects, function($p) use ($status){
    return strcasecmp((string)($p['status'] ?? ''), $status) === 0;
  }));
}
if ($q !== '') {
  $projects = array_values(array_filter($projects, function($p) use ($q){
    $hay = strtolower(($p['title'] ?? '').' '.($p['location'] ?? '').' '.($p['type'] ?? '').' '.($p['year'] ?? '').' '.($p['status'] ?? ''));
    return str_contains($hay, strtolower($q));
  }));
}
?>

<div class="container py-5">
  <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
    <div>
      <h1 class="display-6 fw-bold mb-1">Projects</h1>
      <div class="mb-muted">A showcase of our recent work. Replace placeholders with your real photos anytime.</div>
    </div>
    <form class="d-flex flex-wrap gap-2" method="get" action="<?= htmlspecialchars(pub_url('/public/projects.php'), ENT_QUOTES, 'UTF-8') ?>">
      <select class="form-select" name="status" style="max-width:220px">
        <option value="all" <?= ($status==='all'?'selected':'') ?>>All</option>
        <option value="Completed" <?= ($status==='Completed'?'selected':'') ?>>Completed</option>
        <option value="Ongoing" <?= ($status==='Ongoing'?'selected':'') ?>>Ongoing</option>
      </select>
      <input class="form-control" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search (type, location, year)" style="min-width:240px">
      <button class="btn btn-outline-primary" type="submit">Filter</button>
    </form>
  </div>

  <div class="row g-4">
    <?php if (!$projects): ?>
      <div class="col-12">
        <div class="alert alert-warning">No projects match your search.</div>
      </div>
    <?php endif; ?>

    <?php foreach ($projects as $p): ?>
      <div class="col-md-4">
        <a class="text-decoration-none" href="<?= htmlspecialchars(pub_url('/public/project_view.php'), ENT_QUOTES, 'UTF-8') ?>?id=<?= urlencode($p['id']) ?>">
          <div class="card border-0 shadow-sm rounded-4 h-100">
            <?php $coverUrl = project_media_url((string)($p['cover'] ?? ''), pub_url('/assets/img/projects/residence.svg')); ?>
            <img class="mb-portfolio-img" src="<?= htmlspecialchars($coverUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8') ?>" onerror="this.onerror=null;this.src='<?= htmlspecialchars(pub_url('/assets/img/projects/residence.svg'), ENT_QUOTES, 'UTF-8') ?>';">
            <div class="card-body p-4">
              <div class="small mb-muted">
                <?= htmlspecialchars((string)($p['type'] ?? '')) ?> • <?= htmlspecialchars((string)($p['location'] ?? '')) ?> • <?= htmlspecialchars((string)($p['year'] ?? '')) ?>
                <?php if (!empty($p['status'])): ?> • <span class="fw-semibold"><?= htmlspecialchars((string)$p['status']) ?></span><?php endif; ?>
              </div>
              <div class="h5 mb-2 text-dark"><?= htmlspecialchars($p['title']) ?></div>
              <div class="mb-muted"><?= htmlspecialchars($p['summary']) ?></div>
            </div>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="mt-5 p-4 rounded-4 mb-cta">
    <div class="row align-items-center g-3">
      <div class="col-lg-8">
        <div class="h4 fw-bold mb-1">Want to see progress updates?</div>
        <div class="mb-muted">We can provide photo updates and milestone reports for active projects (client portal ready).</div>
      </div>
      <div class="col-lg-4 text-lg-end">
        <a class="btn btn-primary btn-lg" href="<?= htmlspecialchars(pub_url('/public/contact.php'), ENT_QUOTES, 'UTF-8') ?>">Talk to us</a>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/templates/footer.php'; ?>
