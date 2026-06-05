<?php
$company = require __DIR__ . '/data/company.php';
$p = $company['profile'] ?? [];
$title = 'About — ' . ($company['name'] ?? 'Maorin Builders');
require __DIR__ . '/templates/header.php';
?>

<div class="container py-5">
  <div class="row g-4 align-items-start">
    <div class="col-lg-7">
      <h1 class="display-6 fw-bold mb-2">About <?= htmlspecialchars($company['name'] ?? 'Maorin Builders', ENT_QUOTES, 'UTF-8') ?></h1>
      <p class="lead mb-3"><?= htmlspecialchars($p['history']['text'] ?? 'We build safe, durable projects with reliable timelines and transparent costing.', ENT_QUOTES, 'UTF-8') ?></p>

      <div class="row g-3">
        <div class="col-12">
          <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
              <div class="h5 mb-2"><?= htmlspecialchars($p['history']['title'] ?? 'Our story', ENT_QUOTES, 'UTF-8') ?></div>
              <div class="mb-muted"><?= htmlspecialchars($p['history']['text'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
            </div>
          </div>
        </div>

        <div class="col-12">
          <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
              <div class="h5 mb-2">Mission</div>
              <div class="mb-muted"><?= htmlspecialchars($p['mission'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
            </div>
          </div>
        </div>

        <div class="col-12">
          <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
              <div class="h5 mb-2">Values</div>
              <ul class="mb-0">
                <?php foreach (($p['values'] ?? []) as $v): ?>
                  <li><?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        </div>

        <div class="col-12">
          <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
              <div class="h5 mb-2">Safety standards & compliance</div>
              <ul class="mb-0">
                <?php foreach (($p['safety'] ?? []) as $s): ?>
                  <li><?= htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-body p-4">
          <div class="h5 mb-3">Company profile</div>
          <div class="d-flex justify-content-between"><span class="mb-muted">Years of experience</span><span><?= htmlspecialchars($p['experience']['years'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span></div>
          <hr>
          <div class="mb-muted mb-2">Specialties</div>
          <ul class="mb-0">
            <?php foreach (($p['experience']['specialties'] ?? []) as $sp): ?>
              <li><?= htmlspecialchars((string)$sp, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>

      <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
          <div class="h5 mb-3">Licenses, registrations, permits</div>
          <div class="small mb-muted mb-2">(Replace placeholders with your real document numbers.)</div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <tbody>
                <?php foreach (($p['licenses'] ?? []) as $row): ?>
                  <tr>
                    <td class="mb-muted" style="width:55%">
                      <?= htmlspecialchars((string)($row['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <td class="text-end fw-semibold">
                      <?= htmlspecialchars((string)($row['value'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <hr>
          <a class="btn btn-primary w-100" href="<?= htmlspecialchars(pub_url('/public/contact.php'), ENT_QUOTES, 'UTF-8') ?>">Request a Quote</a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/templates/footer.php'; ?>
