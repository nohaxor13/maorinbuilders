<?php
declare(strict_types=1);
$title = "Testimonials";

require __DIR__ . '/../config.php';
require __DIR__ . '/../helpers.php';

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

$st = $pdo->query("SELECT * FROM website_testimonials WHERE is_approved=1 ORDER BY created_at DESC LIMIT 50");
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$company = require __DIR__ . '/data/company.php';
include __DIR__ . '/templates/header.php';
?>
<main class="container py-5">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
    <div>
      <h1 class="mb-1">Client Testimonials</h1>
      <p class="text-muted mb-0">Real feedback from projects we’ve completed.</p>
    </div>
    <a class="btn btn-outline-light" href="<?= htmlspecialchars(pub_url('/public/contact.php'), ENT_QUOTES, 'UTF-8') ?>">Start your project</a>
  </div>

  <?php if (!$rows): ?>
    <div class="alert alert-secondary">No testimonials published yet. Check back soon.</div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($rows as $t): ?>
        <div class="col-md-6 col-lg-4">
          <div class="card h-100 shadow-sm">
            <div class="card-body">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="fw-semibold"><?= htmlspecialchars((string)$t['client_name'], ENT_QUOTES, 'UTF-8') ?></div>
                <div class="small text-warning">
                  <?php for($i=0;$i<(int)$t['rating'];$i++) echo '★'; ?>
                  <?php for($i=(int)$t['rating'];$i<5;$i++) echo '☆'; ?>
                </div>
              </div>
              <div class="text-muted small mb-2">
                <?= htmlspecialchars((string)($t['project_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                <?php if (!empty($t['project_ref'])): ?>
                  · <?= htmlspecialchars((string)$t['project_ref'], ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
              </div>
              <div><?= nl2br(htmlspecialchars((string)$t['message'], ENT_QUOTES, 'UTF-8')) ?></div>
            </div>
            <div class="card-footer small text-muted">
              <?= htmlspecialchars(date('F j, Y', strtotime((string)$t['created_at'])), ENT_QUOTES, 'UTF-8') ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>
