<?php
$title = 'Contact — Maorin Builders';
require __DIR__ . '/../config.php';
require __DIR__ . '/../helpers.php';
require_feature($pdo, 'public_site');
require_feature($pdo, 'inquiries');
require __DIR__ . '/templates/header.php';

$company = require __DIR__ . '/data/company.php';
$c = $company['contact'] ?? [];

$ok = isset($_GET['sent']) && $_GET['sent'] === '1';
$err = (string)($_GET['err'] ?? '');
?>

<div class="container py-5">
  <div class="row g-4">
    <div class="col-lg-6">
      <h1 class="display-6 fw-bold mb-2">Request a quote</h1>
      <p class="mb-muted">Send your project details. We’ll respond with next steps, required documents, and a site visit schedule if needed.</p>

      <?php if ($ok): ?>
        <div class="alert alert-success">Thank you! Your inquiry has been sent. We’ll contact you soon.</div>
      <?php elseif ($err): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <form action="<?= htmlspecialchars(pub_url('/public/contact_submit.php'), ENT_QUOTES, 'UTF-8') ?>" method="post" class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Full name</label>
              <input name="name" class="form-control" required maxlength="120">
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone</label>
              <input name="phone" class="form-control" required maxlength="60">
            </div>
            <div class="col-12">
              <label class="form-label">Email (optional)</label>
              <input name="email" type="email" class="form-control" maxlength="120">
            </div>
            <div class="col-md-6">
              <label class="form-label">Project type</label>
              <select name="project_type" class="form-select" required>
                <option value="">Select…</option>
                <option>Residential</option>
                <option>Commercial</option>
                <option>Renovation</option>
                <option>Fit‑out</option>
                <option>Other</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Location</label>
              <input name="location" class="form-control" required maxlength="120" placeholder="City / Province">
            </div>
            <div class="col-12">
              <label class="form-label">Estimated budget (optional)</label>
              <select name="budget" class="form-select">
                <option value="">Select…</option>
                <option>Below ₱500k</option>
                <option>₱500k – ₱1M</option>
                <option>₱1M – ₱3M</option>
                <option>₱3M – ₱10M</option>
                <option>₱10M+</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Message</label>
              <textarea name="message" class="form-control" rows="5" required maxlength="2000" placeholder="Tell us the scope, size, and target start date."></textarea>
              <div class="form-text">Tip: include floor area (sqm) if you have it.</div>
            </div>
          </div>
          <button class="btn btn-primary btn-lg mt-3" type="submit">Send inquiry</button>
        </div>
      </form>
    </div>

    <div class="col-lg-6">
      <div class="card border-0 shadow-sm rounded-4 h-100">
        <div class="card-body p-4">
          <div class="h5 mb-2">Contact details</div>
          <div class="mb-muted">Use the details below to reach us directly.</div>
          <hr>
          <div><strong>Phone:</strong> <?= htmlspecialchars((string)($c['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
          <div><strong>Email:</strong> <?= htmlspecialchars((string)($c['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
          <div><strong>Office:</strong> <?= htmlspecialchars((string)($c['address'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>

          <?php if (!empty($c['office_hours']) && is_array($c['office_hours'])): ?>
            <div class="mt-3"><strong>Office hours:</strong></div>
            <ul class="mb-0">
              <?php foreach ($c['office_hours'] as $h): ?>
                <li><?= htmlspecialchars((string)$h, ENT_QUOTES, 'UTF-8') ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>

          <div class="d-flex flex-wrap gap-2 mt-3">
            <?php
              $wa = preg_replace('/\D+/', '', (string)($c['whatsapp_number'] ?? ''));
              $ms = trim((string)($c['messenger_username'] ?? ''));
            ?>
            <?php if ($wa): ?>
              <a class="btn btn-outline-success" href="https://wa.me/<?= htmlspecialchars($wa, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">WhatsApp</a>
            <?php endif; ?>
            <?php if ($ms): ?>
              <a class="btn btn-outline-primary" href="https://m.me/<?= htmlspecialchars($ms, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Messenger</a>
            <?php endif; ?>
          </div>
          <hr>
          <div class="ratio ratio-4x3 rounded-4 overflow-hidden">
            <iframe src="<?= htmlspecialchars((string)($c['map_embed_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/templates/footer.php'; ?>
