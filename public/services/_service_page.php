<?php
// public/services/_service_page.php
// Shared renderer for individual service pages.

if (!isset($service) || !is_array($service)) {
  http_response_code(500);
  die('Service page misconfigured.');
}

$title = ($service['name'] ?? 'Service') . ' — Maorin Builders';
require __DIR__ . '/../templates/header.php';

$inc = $service['included'] ?? [];
$timeline = $service['timeline'] ?? [];
$range = $service['estimate_range'] ?? '';
?>

<div class="container py-5">
  <a class="text-decoration-none" href="<?= htmlspecialchars(pub_url('/public/services.php'), ENT_QUOTES, 'UTF-8') ?>">&larr; Back to services</a>

  <div class="row g-4 align-items-start mt-2">
    <div class="col-lg-8">
      <div class="mb-service-hero p-4 p-lg-5 rounded-4 shadow-sm">
        <div class="text-uppercase small fw-semibold mb-2" style="letter-spacing:.02em">Service</div>
        <h1 class="display-6 fw-bold mb-2"><?= htmlspecialchars($service['name'] ?? 'Service', ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="lead mb-0"><?= htmlspecialchars($service['desc'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
        <div class="d-flex flex-wrap gap-2 mt-3">
          <?php if ($range): ?>
            <span class="badge text-bg-light border">Estimated cost range: <?= htmlspecialchars($range, ENT_QUOTES, 'UTF-8') ?></span>
          <?php endif; ?>
          <?php if (!empty($service['timeline_note'])): ?>
            <span class="badge text-bg-light border">Timeline: <?= htmlspecialchars($service['timeline_note'], ENT_QUOTES, 'UTF-8') ?></span>
          <?php endif; ?>
        </div>
      </div>

      <div class="row g-4 mt-1">
        <div class="col-12">
          <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
              <div class="h5 mb-2">What’s included</div>
              <ul class="mb-0">
                <?php foreach ($inc as $it): ?>
                  <li><?= htmlspecialchars((string)$it, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        </div>

        <div class="col-12">
          <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
              <div class="h5 mb-2">Timeline (typical)</div>
              <?php if ($timeline): ?>
                <ol class="mb-0">
                  <?php foreach ($timeline as $step): ?>
                    <li><?= htmlspecialchars((string)$step, ENT_QUOTES, 'UTF-8') ?></li>
                  <?php endforeach; ?>
                </ol>
              <?php else: ?>
                <div class="mb-muted">Timeline depends on scope. We’ll give you a schedule after a site visit and scope confirmation.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <?php if (!empty($service['notes'])): ?>
          <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4">
              <div class="card-body p-4">
                <div class="h5 mb-2">Notes</div>
                <div class="mb-muted"><?= htmlspecialchars((string)$service['notes'], ENT_QUOTES, 'UTF-8') ?></div>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card border-0 shadow-sm rounded-4 sticky-top" style="top:16px">
        <div class="card-body p-4">
          <div class="h5 mb-2">Request a quote</div>
          <div class="mb-muted mb-3">Tell us your location, scope, and target schedule. We’ll reply with the next steps and a site visit plan.</div>
          <a class="btn btn-primary w-100" href="<?= htmlspecialchars(pub_url('/public/contact.php'), ENT_QUOTES, 'UTF-8') ?>">Contact us</a>
          <hr>
          <div class="small mb-muted">Tip</div>
          <div class="small">Include floor area (sqm), finish level (basic/standard/premium), and desired start date for a faster estimate.</div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
