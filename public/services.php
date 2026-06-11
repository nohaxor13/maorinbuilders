<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../helpers.php';
ensure_content_catalog_tables($pdo);
$title = 'Services — Maorin Builders';
require_feature($pdo, 'public_site');
require __DIR__ . '/templates/header.php';

$services = $pdo->query("SELECT slug, name, desc_text AS desc, href, range_text AS range, timeline_text AS timeline FROM website_services ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
if (!$services) {
  $services = [
  [
    'name'=>'Residential construction',
    'desc'=>'New builds, extensions, and structural works for houses and townhomes.',
    'href'=>'/public/services/residential.php',
    'range'=>'₱25k–₱45k / sqm (typical)',
    'timeline'=>'12–24 weeks (typical)'
  ],
  [
    'name'=>'Commercial buildings',
    'desc'=>'Warehouses, small commercial buildings, and light industrial projects.',
    'href'=>'/public/services/commercial.php',
    'range'=>'₱28k–₱55k / sqm (typical)',
    'timeline'=>'10–28 weeks (typical)'
  ],
  [
    'name'=>'Renovation / remodeling',
    'desc'=>'Upgrades, room conversions, waterproofing, and repair works with proper planning.',
    'href'=>'/public/services/renovation.php',
    'range'=>'₱200k–₱3M+ (typical)',
    'timeline'=>'2–10 weeks (typical)'
  ],
  [
    'name'=>'Architectural & engineering works',
    'desc'=>'Plans, structural design, bill of materials, and permitting support (project dependent).',
    'href'=>'/public/services/architectural.php',
    'range'=>'By scope',
    'timeline'=>'1–6 weeks (typical)'
  ],
  [
    'name'=>'Project management',
    'desc'=>'Scheduling, subcontractor coordination, progress reporting, and QA/QC checkpoints.',
    'href'=>'/public/services/project_management.php',
    'range'=>'By project size',
    'timeline'=>'Ongoing'
  ],
  [
    'name'=>'Design & build packages',
    'desc'=>'Concept, planning, costing, and construction under one coordinated team.',
    'href'=>'/public/services/design_build.php',
    'range'=>'Package-based',
    'timeline'=>'Varies'
  ],
  ];
}
?>

<div class="container py-5">
  <div class="row g-4 align-items-end">
    <div class="col-lg-8">
      <h1 class="display-6 fw-bold mb-2">Services</h1>
      <p class="lead mb-0">Choose a service below — we’ll schedule a site visit and provide an itemized quote.</p>
    </div>
    <div class="col-lg-4 text-lg-end">
      <a class="btn btn-primary btn-lg" href="<?= htmlspecialchars(pub_url('/public/contact.php'), ENT_QUOTES, 'UTF-8') ?>">Request a Quote</a>
    </div>
  </div>

  <div class="row g-4 mt-1">
    <?php foreach($services as $s): ?>
      <div class="col-md-6 col-lg-4">
        <a class="text-decoration-none" href="<?= htmlspecialchars(pub_url($s['href']), ENT_QUOTES, 'UTF-8') ?>">
          <div class="card border-0 shadow-sm rounded-4 h-100 mb-service-card">
            <div class="card-body p-4">
              <div class="h5 mb-2 text-dark"><?= htmlspecialchars($s['name']) ?></div>
              <div class="mb-muted mb-3"><?= htmlspecialchars($s['desc']) ?></div>
              <div class="d-flex flex-wrap gap-2">
                <span class="badge text-bg-light border">Timeline: <?= htmlspecialchars($s['timeline']) ?></span>
                <span class="badge text-bg-light border">Est. range: <?= htmlspecialchars($s['range']) ?></span>
              </div>
              <div class="mt-3 fw-semibold" style="color:var(--mb-brand)">View details →</div>
            </div>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="mt-4 p-4 rounded-4 mb-cta">
    <div class="row g-3 align-items-center">
      <div class="col-lg-8">
        <div class="h4 fw-bold mb-1">Not sure where to start?</div>
        <div class="mb-muted">Send us your location + scope. We’ll recommend the right approach and next steps.</div>
      </div>
      <div class="col-lg-4 text-lg-end">
        <a class="btn btn-outline-primary btn-lg" href="<?= htmlspecialchars(pub_url('/public/contact.php'), ENT_QUOTES, 'UTF-8') ?>">Talk to us</a>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/templates/footer.php'; ?>
