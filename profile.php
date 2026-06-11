<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/includes/dashboard_bootstrap.php';

redirect_if_not_logged_in();
if (!empty($pdo) && $pdo instanceof PDO) {
    ensure_maorin_workspace_tables($pdo);
    ensure_staff_profiles_table($pdo);
}

$title = 'My Profile';
$extraStylesheets = ['assets/css/account-dashboard.css?v=20260610-root'];
$pageContainerClass = 'container-fluid px-0';
include __DIR__ . '/templates/header.php';

$name = $_SESSION['name'] ?? 'User';
$email = $_SESSION['email'] ?? '';
$phone = $_SESSION['phone'] ?? '';
$role = $_SESSION['role'] ?? 'Staff';
$department = $_SESSION['department'] ?? 'Purchasing';
$photo = $dashboard['user']['avatar'] ?? dash_avatar_svg($name);
?>

<div class="account-dashboard-shell">
  <div class="dashboard-grid" style="grid-template-columns:1fr;max-width:1200px;padding:0 18px;">
    <main class="main-col">
      <section class="hero-card dash-card">
        <div class="hero-left">
          <img class="avatar-lg" src="<?= e($photo) ?>" alt="Profile">
          <div class="hero-copy">
            <div class="eyebrow">Profile</div>
            <h1><?= e($name) ?></h1>
            <p><?= e($email) ?></p>
            <div class="hero-tags">
              <span class="tag-blue"><?= e($role) ?></span>
              <span class="tag-gray">Department: <?= e($department) ?></span>
              <span class="tag-gray"><?= e($phone) ?></span>
            </div>
          </div>
        </div>
      </section>
    </main>
  </div>
</div>

<?php include __DIR__ . '/templates/footer.php'; ?>
