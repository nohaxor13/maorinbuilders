<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

redirect_if_not_logged_in();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = (string)($_POST['current_password'] ?? '');
    $new = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if ($new === '' || $confirm === '') {
        $error = 'Please fill in all password fields.';
    } elseif ($new !== $confirm) {
        $error = 'The new passwords do not match.';
    } else {
        $st = $pdo->prepare("SELECT password, password_hash FROM users WHERE id = ? LIMIT 1");
        $st->execute([(int)$_SESSION['user_id']]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $stored = (string)($row['password_hash'] ?: $row['password'] ?: '');
        $ok = $stored !== '' && (password_verify($current, $stored) || hash_equals($stored, $current));

        if (!$ok) {
            $error = 'Current password is incorrect.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, (int)$_SESSION['user_id']]);
            $success = 'Password updated successfully.';
        }
    }
}

$title = 'Change Password';
$extraStylesheets = ['assets/css/account-dashboard.css?v=20260610-root'];
$pageContainerClass = 'container-fluid px-0';
include __DIR__ . '/templates/header.php';
?>

<div class="account-dashboard-shell">
  <div class="dashboard-grid" style="grid-template-columns:1fr;max-width:900px;padding:0 18px;">
    <main class="main-col">
      <section class="dash-card" style="padding:18px;">
        <div class="section-title">Change Password</div>
        <?php if ($error): ?><div class="mb-3" style="color:#b91c1c;"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="mb-3" style="color:#166534;"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <form method="post" class="d-grid gap-3" style="max-width:520px;">
          <label>Current Password<input class="form-control" type="password" name="current_password" required></label>
          <label>New Password<input class="form-control" type="password" name="new_password" required></label>
          <label>Confirm Password<input class="form-control" type="password" name="confirm_password" required></label>
          <button class="profile-btn" type="submit">Update Password</button>
        </form>
      </section>
    </main>
  </div>
</div>

<?php include __DIR__ . '/templates/footer.php'; ?>
