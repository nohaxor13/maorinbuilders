<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/helpers.php';
redirect_if_not_logged_in();
require_permission($pdo, 'manage_company_content');

if (!function_exists('admin_about_company_file')) {
  function admin_about_company_file(): string {
    return __DIR__ . '/public/data/company.php';
  }
}

if (!function_exists('admin_about_default_company')) {
  function admin_about_default_company(): array {
    return [
      'name' => 'Maorin Builders',
      'tagline' => 'Construction • Renovation • Design & Build',
      'profile' => [
        'history' => [
          'title' => 'Our story',
          'text' => 'Maorin Builders is a Philippine-based construction team focused on delivering safe, durable builds with clear scope, reliable timelines, and transparent costing.',
        ],
        'mission' => 'Deliver quality construction work through disciplined planning, skilled execution, and honest communication.',
        'values' => [
          'Safety-first on every site',
          'Clear scope and documented changes',
          'Quality control checkpoints',
          'Respect for clients, neighbors, and timelines',
        ],
        'experience' => [
          'years' => '5+ years',
          'specialties' => [
            'Residential construction',
            'Commercial buildings & warehouses',
            'Renovation / remodeling',
            'Design & build packages',
          ],
        ],
        'licenses' => [
          ['label' => 'DTI/SEC Registration', 'value' => 'To be added'],
          ['label' => "Mayor's Permit", 'value' => 'To be added'],
          ['label' => 'PCAB License', 'value' => 'To be added'],
          ['label' => 'BIR Registration', 'value' => 'To be added'],
        ],
        'safety' => [
          'We follow jobsite safety standards including PPE compliance, housekeeping, hazard identification, and daily toolbox meetings as applicable.',
          'We keep documentation for work permits, safety briefings, and inspection checkpoints to reduce risk and ensure quality.',
        ],
      ],
      'contact' => [
        'phone' => '+63 9XX XXX XXXX',
        'email' => 'hello@maorinbuilders.com',
        'address' => 'Your office address here',
        'office_hours' => [
          'Mon-Sat: 8:00 AM - 6:00 PM',
          'Sun: By appointment',
        ],
        'whatsapp_number' => '639XXXXXXXXX',
        'messenger_username' => 'YourPageUsername',
        'map_embed_url' => 'https://www.google.com/maps?q=7.140785406644884,125.65552576134661&z=18&t=k&output=embed',
      ],
    ];
  }
}

if (!function_exists('admin_about_load_company')) {
  function admin_about_load_company(): array {
    $file = admin_about_company_file();
    if (is_file($file)) {
      $data = include $file;
      if (is_array($data)) {
        return $data;
      }
    }
    return admin_about_default_company();
  }
}

if (!function_exists('admin_about_normalize_lines')) {
  function admin_about_normalize_lines(string $text): array {
    $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
    if ($text === '') {
      return [];
    }
    return array_values(array_filter(array_map('trim', explode("\n", $text)), static fn($item) => $item !== ''));
  }
}

if (!function_exists('admin_about_join_lines')) {
  function admin_about_join_lines($value): string {
    if (!is_array($value)) {
      return '';
    }
    return implode("\n", array_values(array_filter(array_map(static fn($item) => trim((string)$item), $value), static fn($item) => $item !== '')));
  }
}

if (!function_exists('admin_about_export_php')) {
  function admin_about_export_php(array $data): string {
    $export = var_export($data, true);
    $export = preg_replace_callback(
      "/^([ ]*)'([^']*)' => /m",
      static function (array $matches): string {
        return $matches[1] . "'" . $matches[2] . "' => ";
      },
      $export
    );

    return "<?php\n// public/data/company.php\n// Centralized company profile data for the public website.\n// Update these placeholders with real details when ready.\n\nreturn " . $export . ";\n";
  }
}

$flashError = (string)($_SESSION['admin_flash_error'] ?? '');
$flashSuccess = (string)($_SESSION['admin_flash_success'] ?? '');
unset($_SESSION['admin_flash_error'], $_SESSION['admin_flash_success']);

$company = admin_about_load_company();
$profile = $company['profile'] ?? [];
$history = $profile['history'] ?? [];
$experience = $profile['experience'] ?? [];
$contact = $company['contact'] ?? [];
$licenses = $profile['licenses'] ?? [];
$values = $profile['values'] ?? [];
$safety = $profile['safety'] ?? [];
$specialties = $experience['specialties'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    csrf_verify();
    $action = (string)($_POST['action'] ?? '');
    if ($action !== 'save_about') {
      throw new RuntimeException('Unsupported action.');
    }

    $name = trim((string)($_POST['name'] ?? ''));
    $tagline = trim((string)($_POST['tagline'] ?? ''));
    $historyTitle = trim((string)($_POST['history_title'] ?? ''));
    $historyText = trim((string)($_POST['history_text'] ?? ''));
    $mission = trim((string)($_POST['mission'] ?? ''));
    $years = trim((string)($_POST['experience_years'] ?? ''));

    $valuesText = (string)($_POST['values_text'] ?? '');
    $safetyText = (string)($_POST['safety_text'] ?? '');
    $specialtiesText = (string)($_POST['specialties_text'] ?? '');
    $licenseLabels = $_POST['license_label'] ?? [];
    $licenseValues = $_POST['license_value'] ?? [];

    $licensesOut = [];
    $licenseCount = max(count((array)$licenseLabels), count((array)$licenseValues));
    for ($i = 0; $i < $licenseCount; $i++) {
      $label = trim((string)($licenseLabels[$i] ?? ''));
      $value = trim((string)($licenseValues[$i] ?? ''));
      if ($label === '' && $value === '') {
        continue;
      }
      $licensesOut[] = ['label' => $label, 'value' => $value];
    }

    $company['name'] = $name !== '' ? $name : ($company['name'] ?? 'Maorin Builders');
    $company['tagline'] = $tagline !== '' ? $tagline : ($company['tagline'] ?? '');
    $company['profile'] = [
      'history' => [
        'title' => $historyTitle !== '' ? $historyTitle : 'Our story',
        'text' => $historyText,
      ],
      'mission' => $mission,
      'values' => admin_about_normalize_lines($valuesText),
      'experience' => [
        'years' => $years,
        'specialties' => admin_about_normalize_lines($specialtiesText),
      ],
      'licenses' => $licensesOut,
      'safety' => admin_about_normalize_lines($safetyText),
    ];

    if (!isset($company['contact']) || !is_array($company['contact'])) {
      $company['contact'] = admin_about_default_company()['contact'];
    }

    $php = admin_about_export_php($company);
    $file = admin_about_company_file();
    $tmp = $file . '.tmp';
    if (file_put_contents($tmp, $php, LOCK_EX) === false) {
      throw new RuntimeException('Unable to write company data file.');
    }
    if (!@rename($tmp, $file)) {
      @unlink($tmp);
      throw new RuntimeException('Unable to replace company data file.');
    }

    $_SESSION['admin_flash_success'] = 'About page updated.';
    header('Location: admin_about.php');
    exit;
  } catch (Throwable $e) {
    $flashError = $e->getMessage();
  }
}

$company = admin_about_load_company();
$profile = $company['profile'] ?? [];
$history = $profile['history'] ?? [];
$experience = $profile['experience'] ?? [];
$licenses = $profile['licenses'] ?? [];
$values = $profile['values'] ?? [];
$safety = $profile['safety'] ?? [];
$specialties = $experience['specialties'] ?? [];
$title = 'Edit About Content';
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> - Maorin Builders</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
  <style>
    body { background: #eef2f7; color: #0f172a; }
    .admin-shell { background: #f6f8fb; border: 1px solid rgba(15,23,42,.08); border-radius: 24px; }
    .admin-hero { background: linear-gradient(135deg, #fff, #eef4ff); border: 1px solid rgba(15,23,42,.08); border-radius: 20px; }
    .admin-card { border: 1px solid rgba(15,23,42,.08); border-radius: 18px; box-shadow: 0 8px 24px rgba(2,6,23,.06); }
    .field-hint { color: #6b7280; font-size: .875rem; }
    textarea.form-control { min-height: 140px; }
  </style>
</head>
<body>

<div class="container py-4">
  <div class="admin-shell p-3 p-lg-4">
    <div class="admin-hero p-4 p-lg-5 mb-4">
      <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
        <div>
          <div class="text-uppercase small text-primary fw-semibold mb-2">Admin Workspace</div>
          <h1 class="display-6 fw-bold mb-2">Edit About Page</h1>
          <div class="text-secondary">Update the public About page content from one canonical source file.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <a class="btn btn-outline-secondary" href="admin.php">Back to dashboard</a>
          <a class="btn btn-outline-dark" href="public/about.php" target="_blank" rel="noopener">View public About</a>
        </div>
      </div>
    </div>

    <?php if ($flashSuccess): ?>
      <div class="alert alert-success"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post" class="vstack gap-4">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="action" value="save_about">

      <div class="card admin-card">
        <div class="card-body p-4">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Company name / headline</label>
              <input type="text" name="name" class="form-control" value="<?= htmlspecialchars((string)($company['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
              <div class="field-hint mt-1">This updates the public About heading.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Tagline</label>
              <input type="text" name="tagline" class="form-control" value="<?= htmlspecialchars((string)($company['tagline'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
              <div class="field-hint mt-1">Shown in the shared company data for future public use.</div>
            </div>
            <div class="col-12">
              <label class="form-label">Story title</label>
              <input type="text" name="history_title" class="form-control" value="<?= htmlspecialchars((string)($history['title'] ?? 'Our story'), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-12">
              <label class="form-label">Story text</label>
              <textarea name="history_text" class="form-control"><?= htmlspecialchars((string)($history['text'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Mission</label>
              <textarea name="mission" class="form-control"><?= htmlspecialchars((string)($profile['mission'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
          </div>
        </div>
      </div>

      <div class="card admin-card">
        <div class="card-body p-4">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Years of experience</label>
              <input type="text" name="experience_years" class="form-control" value="<?= htmlspecialchars((string)($experience['years'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-12">
              <label class="form-label">Specialties</label>
              <textarea name="specialties_text" class="form-control"><?= htmlspecialchars(admin_about_join_lines($specialties), ENT_QUOTES, 'UTF-8') ?></textarea>
              <div class="field-hint mt-1">One specialty per line.</div>
            </div>
            <div class="col-12">
              <label class="form-label">Values</label>
              <textarea name="values_text" class="form-control"><?= htmlspecialchars(admin_about_join_lines($values), ENT_QUOTES, 'UTF-8') ?></textarea>
              <div class="field-hint mt-1">One value per line.</div>
            </div>
            <div class="col-12">
              <label class="form-label">Safety standards & compliance</label>
              <textarea name="safety_text" class="form-control"><?= htmlspecialchars(admin_about_join_lines($safety), ENT_QUOTES, 'UTF-8') ?></textarea>
              <div class="field-hint mt-1">One safety item per line.</div>
            </div>
          </div>
        </div>
      </div>

      <div class="card admin-card">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <div class="h5 mb-1">Licenses, registrations, permits</div>
              <div class="field-hint">Edit the label and value for each row. Blank rows are ignored.</div>
            </div>
          </div>
          <div class="vstack gap-3">
            <?php
              $licenseRows = is_array($licenses) && $licenses ? array_values($licenses) : [];
              if (!$licenseRows) {
                $licenseRows = admin_about_default_company()['profile']['licenses'];
              }
              foreach ($licenseRows as $idx => $row):
            ?>
              <div class="row g-2 align-items-end">
                <div class="col-md-5">
                  <label class="form-label">Label</label>
                  <input type="text" name="license_label[]" class="form-control" value="<?= htmlspecialchars((string)($row['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-7">
                  <label class="form-label">Value</label>
                  <input type="text" name="license_value[]" class="form-control" value="<?= htmlspecialchars((string)($row['value'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
              </div>
            <?php endforeach; ?>
            <div class="row g-2 align-items-end">
              <div class="col-md-5">
                <input type="text" name="license_label[]" class="form-control" placeholder="Add another label">
              </div>
              <div class="col-md-7">
                <input type="text" name="license_value[]" class="form-control" placeholder="Add another value">
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="d-flex flex-wrap gap-2 justify-content-end">
        <a class="btn btn-outline-secondary" href="admin.php">Cancel</a>
        <button type="submit" class="btn btn-primary px-4">Save About Page</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
