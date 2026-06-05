<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
require __DIR__ . '/../helpers.php';
ensure_client_portal_tables($pdo);

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim((string)($_POST['email'] ?? ''));
  $pass  = (string)($_POST['password'] ?? '');
  $st = $pdo->prepare("SELECT id, password_hash FROM website_clients WHERE email=? LIMIT 1");
  $st->execute([$email]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  $stored = (string)($row['password_hash'] ?? '');
  $isValid = $row && ($stored !== '' ? (password_verify($pass, $stored) || hash_equals($stored, $pass)) : false);
  if ($isValid) {
    if (function_exists('maintenance_mode_is_enabled') && maintenance_mode_is_enabled($pdo)) {
      $err = 'The site is currently under maintenance. Client Portal login is temporarily unavailable.';
    } else {
    $_SESSION['client_id'] = (int)$row['id'];
    header('Location: index.php');
    exit;
    }
  }
  if ($err === '') {
    $err = 'Invalid email or password.';
  }
}

include __DIR__ . '/templates/header.php';
?>
<div class="row justify-content-center">
  <div class="col-md-6 col-lg-5">
    <div class="card shadow-sm border-0 rounded-4">
      <div class="card-body p-4">
        <h4 class="mb-1">Client Login</h4>
        <p class="muted mb-3">Client Portal accounts only. Staff should use the main staff login page.</p>
        <?php if ($err): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="on">
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input class="form-control" type="email" name="email" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input class="form-control" type="password" name="password" required>
          </div>
          <button class="btn btn-dark w-100">Login</button>
        </form>
        <div class="small muted mt-3">
          Need access? Contact Maorin Builders and request a Client Portal account.
        </div>
        <div class="small mt-2">
          <a href="../login.php">Staff login</a>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/templates/footer.php'; ?>
