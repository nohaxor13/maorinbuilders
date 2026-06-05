<?php
declare(strict_types=1);
$title = "Cost Estimator";
$company = require __DIR__ . '/data/company.php';
include __DIR__ . '/templates/header.php';
?>
<main class="container py-5">
  <div class="row g-4 align-items-start">
    <div class="col-lg-6">
      <h1 class="mb-2">Construction Cost Estimator</h1>
      <p class="text-muted mb-4">Get a quick, ballpark range. Final pricing depends on site conditions, design, finishes, and scope.</p>

      <form id="estForm" class="card card-body shadow-sm">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Project type</label>
            <select class="form-select" name="project_type" required>
              <option value="Residential Construction">Residential construction</option>
              <option value="Commercial Buildings">Commercial buildings</option>
              <option value="Renovation / Remodeling">Renovation / remodeling</option>
              <option value="Design & Build">Design & build package</option>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label">Finish level</label>
            <select class="form-select" name="finish_level" required>
              <option value="Basic">Basic</option>
              <option value="Standard" selected>Standard</option>
              <option value="Premium">Premium</option>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label">Floor area (sqm)</label>
            <input class="form-control" type="number" min="10" step="1" name="area_sqm" placeholder="e.g. 120" required>
          </div>

          <div class="col-md-6">
            <label class="form-label">Number of floors</label>
            <select class="form-select" name="floors" required>
              <option value="1" selected>1</option>
              <option value="2">2</option>
              <option value="3">3+</option>
            </select>
          </div>

          <div class="col-12">
            <label class="form-label">Location / City</label>
            <input class="form-control" name="location" placeholder="e.g. Quezon City">
          </div>

          <div class="col-md-6">
            <label class="form-label">Name</label>
            <input class="form-control" name="name" placeholder="Your name" required>
          </div>

          <div class="col-md-6">
            <label class="form-label">Contact (Phone / Email)</label>
            <input class="form-control" name="contact" placeholder="09xx... or email" required>
          </div>

          <div class="col-12">
            <label class="form-label">Notes (optional)</label>
            <textarea class="form-control" name="notes" rows="3" placeholder="Any details you already know (lot size, preferred style, timeline, etc.)"></textarea>
          </div>
        </div>

        <div class="d-flex gap-2 mt-4">
          <button class="btn btn-primary" type="submit">Estimate</button>
          <a class="btn btn-outline-light" href="<?= htmlspecialchars(pub_url('/public/contact.php'), ENT_QUOTES, 'UTF-8') ?>">Contact us</a>
        </div>

        <div class="small text-muted mt-3">
          By submitting, you agree we may contact you about your inquiry.
        </div>
      </form>
    </div>

    <div class="col-lg-6">
      <div id="estResult" class="card card-body shadow-sm d-none">
        <h2 class="h4 mb-3">Estimated Range</h2>
        <div class="d-flex flex-wrap gap-3 align-items-center">
          <div>
            <div class="text-muted small">Estimated cost</div>
            <div class="display-6 fw-semibold" id="estCost">—</div>
          </div>
          <div>
            <div class="text-muted small">Estimated timeline</div>
            <div class="h5 mb-0" id="estTime">—</div>
          </div>
        </div>

        <hr class="my-4">

        <div class="row g-3">
          <div class="col-12">
            <div class="text-muted small">What’s included (typical)</div>
            <ul class="mb-0" id="estIncluded"></ul>
          </div>
          <div class="col-12">
            <div class="text-muted small">Reference No.</div>
            <div class="fw-semibold" id="estRef">—</div>
          </div>
        </div>

        <div class="alert alert-info mt-4 mb-0">
          This is a ballpark estimate. For an accurate quotation, we’ll schedule a site visit and review plans.
        </div>
      </div>

      <div class="card card-body shadow-sm mt-4">
        <h2 class="h5 mb-2">Need a formal quotation?</h2>
        <p class="text-muted mb-3">Send us your floor plan / sketches and we’ll prepare a detailed breakdown.</p>
        <a class="btn btn-outline-light" href="<?= htmlspecialchars(pub_url('/public/contact.php'), ENT_QUOTES, 'UTF-8') ?>">Request quotation</a>
      </div>
    </div>
  </div>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>
