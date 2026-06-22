<?php
// =====================================================
// Maorin Builders Public Home
// Works if this file is placed in:
//   /public/index.php
//   /index.php  (accidentally placed at root)
// =====================================================

require __DIR__ . '/../config.php';
require __DIR__ . '/../helpers.php';
require_feature($pdo, 'public_site');

$title = 'Maorin Builders - Construction • Renovation • Design & Build';

$here = __DIR__;
$inPublic = is_dir($here . '/templates') && is_file($here . '/templates/header.php');
$tplDir  = $inPublic ? ($here . '/templates') : ($here . '/public/templates');
$dataDir = $inPublic ? ($here . '/data') : ($here . '/public/data');

if (!is_file($tplDir . '/header.php')) {
  http_response_code(500);
  die("Missing template: " . htmlspecialchars($tplDir . "/header.php"));
}
if (!is_file($tplDir . '/footer.php')) {
  http_response_code(500);
  die("Missing template: " . htmlspecialchars($tplDir . "/footer.php"));
}
if (!is_file($dataDir . '/projects.php')) {
  http_response_code(500);
  die("Missing data: " . htmlspecialchars($dataDir . "/projects.php"));
}

require $tplDir . '/header.php';

if (!function_exists('project_media_url')) {
  function project_media_url(?string $path, string $fallback = ''): string {
    $path = trim((string)$path);
    if ($path === '') {
      return $fallback;
    }
    return pub_url('/' . ltrim($path, '/'));
  }
}

ensure_content_catalog_tables($pdo);
$projects = [];
try {
  $st = $pdo->query("SELECT slug AS id, title, location, year, type, status, cover, summary FROM website_projects ORDER BY id DESC");
  $projects = $st ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
} catch (Throwable $e) {
  $projects = [];
}

if (!$projects) {
  $projects = require $dataDir . '/projects.php';
  if (!is_array($projects)) {
    $projects = [];
  }
}
?>

<header class="mb-hero py-5">
  <div class="container py-4">
    <div class="row align-items-center g-4">
      <div class="col-lg-7">
        <div class="d-inline-flex align-items-center gap-2 px-3 py-2 rounded-pill mb-badge">
          <span class="small fw-semibold">Based in the Philippines</span>
          <span class="small opacity-75">• Residential • Commercial</span>
        </div>
        <h1 class="display-5 fw-bold mb-3">Build with confidence.</h1>
        <p class="lead mb-4">Maorin Builders delivers quality construction, renovations, and design-build solutions with clear communication, reliable timelines, and safe work standards.</p>
        <div class="d-flex flex-wrap gap-2">
          <a href="<?= htmlspecialchars(pub_url('/public/contact.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-light btn-lg">Request a Quote</a>
          <a href="<?= htmlspecialchars(pub_url('/public/projects.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-light btn-lg">View Projects</a>
        </div>
        <div class="mt-4 small opacity-75">Fast response • Transparent costing • On-site supervision</div>
      </div>
      <div class="col-lg-5">
        <div class="p-4 rounded-4" style="background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18)">
          <div class="h5 fw-semibold">What we can do for you</div>
          <ul class="mb-0">
            <li>House & building construction</li>
            <li>Renovation & remodeling</li>
            <li>Commercial fit-outs</li>
            <li>Project management</li>
            <li>Permits & documentation support</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</header>

<section class="py-5">
  <div class="container">
    <div class="row g-4">
      <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
          <div class="card-body p-4">
            <div class="mb-card-icon mb-3">
              <span class="fw-bold">✓</span>
            </div>
            <div class="h5 mb-2">Quality workmanship</div>
            <div class="mb-muted">We focus on durable materials, clean finishing, and proper engineering practice.</div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
          <div class="card-body p-4">
            <div class="mb-card-icon mb-3"><span class="fw-bold">⏱</span></div>
            <div class="h5 mb-2">Reliable timelines</div>
            <div class="mb-muted">Clear milestones, progress updates, and realistic scheduling.</div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
          <div class="card-body p-4">
            <div class="mb-card-icon mb-3"><span class="fw-bold">₱</span></div>
            <div class="h5 mb-2">Transparent costing</div>
            <div class="mb-muted">Upfront scope + itemized quotation, so expectations stay aligned.</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="py-5">
  <div class="container">
    <div class="d-flex align-items-end justify-content-between gap-3 mb-3">
      <div>
        <div class="text-uppercase small fw-semibold mb-1 mb-section-title">Recent work</div>
        <h2 class="h3 fw-bold mb-0">Projects & portfolio</h2>
      </div>
      <a class="btn btn-outline-primary" href="<?= htmlspecialchars(pub_url('/public/projects.php'), ENT_QUOTES, 'UTF-8') ?>">See all</a>
    </div>

    <div class="row g-4">
      <?php foreach (array_slice($projects, 0, 3) as $p): ?>
        <?php
          $pid = (string)($p['id'] ?? '');
          $cover = (string)($p['cover'] ?? '');
          $ptitle = (string)($p['title'] ?? 'Project');
          $ptype = (string)($p['type'] ?? '');
          $ploc = (string)($p['location'] ?? '');
          $pyear = (string)($p['year'] ?? '');
          $psum = (string)($p['summary'] ?? '');
          $coverUrl = project_media_url($cover, pub_url('/assets/img/projects/residence.svg'));
        ?>
        <div class="col-md-4">
          <a class="text-decoration-none" href="<?= htmlspecialchars(pub_url('/public/project_view.php'), ENT_QUOTES, 'UTF-8') ?>?id=<?= urlencode($pid) ?>">
            <div class="card border-0 shadow-sm rounded-4 h-100">
              <img class="mb-portfolio-img" src="<?= htmlspecialchars($coverUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($ptitle, ENT_QUOTES, 'UTF-8') ?>" onerror="this.onerror=null;this.src='<?= htmlspecialchars(pub_url('/assets/img/projects/residence.svg'), ENT_QUOTES, 'UTF-8') ?>';">
              <div class="card-body p-4">
                <div class="small mb-muted">
                  <?= htmlspecialchars($ptype, ENT_QUOTES, 'UTF-8') ?> • <?= htmlspecialchars($ploc, ENT_QUOTES, 'UTF-8') ?> • <?= htmlspecialchars($pyear, ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="h5 mb-2 text-dark"><?= htmlspecialchars($ptitle, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="mb-muted"><?= htmlspecialchars($psum, ENT_QUOTES, 'UTF-8') ?></div>
              </div>
            </div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="mt-4 p-4 rounded-4 mb-cta">
      <div class="row align-items-center g-3">
        <div class="col-lg-8">
          <div class="h4 fw-bold mb-1">Have a project in mind?</div>
          <div class="mb-muted">Tell us your scope and location - we’ll get back with next steps and a quotation schedule.</div>
        </div>
        <div class="col-lg-4 text-lg-end">
          <a class="btn btn-primary btn-lg" href="<?= htmlspecialchars(pub_url('/public/contact.php'), ENT_QUOTES, 'UTF-8') ?>">Get a Quote</a>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require $tplDir . '/footer.php'; ?>
