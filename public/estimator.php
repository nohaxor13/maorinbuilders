<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
require __DIR__ . '/../helpers.php';
require_feature($pdo, 'public_site');
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
        </div>
      </form>
    </div>
  </div>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>
