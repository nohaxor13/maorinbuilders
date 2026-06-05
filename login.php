<?php
require "config.php";
require "helpers.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (is_logged_in()) {
    $role = current_user_role($pdo);
    header("Location: " . (($role === 'admin') ? "admin.php" : "account_dashboard.php"));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = strtolower(trim($_POST["email"]));
    $pass  = $_POST["password"];

    $stmt = $pdo->prepare("SELECT id, name, password_hash, password FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $storedHash = (string)($user["password_hash"] ?? '');
    $storedPlain = (string)($user["password"] ?? '');
    $isValid = false;
    if ($user) {
        if ($storedHash !== '') {
            $isValid = password_verify($pass, $storedHash) || hash_equals($storedHash, $pass);
        } elseif ($storedPlain !== '') {
            $isValid = hash_equals($storedPlain, $pass);
        }
    }

    if ($user && $isValid) {
        $isAdmin = false;
        if (function_exists('maintenance_mode_is_enabled') && maintenance_mode_is_enabled($pdo)) {
            $roleStmt = $pdo->prepare("SELECT role FROM user_roles WHERE user_id = ? LIMIT 1");
            $roleStmt->execute([(int)$user["id"]]);
            $role = (string)$roleStmt->fetchColumn();
            $isAdmin = ($role === 'admin');
            if (!$isAdmin) {
                $error = "The site is currently under maintenance. Only admin accounts can log in.";
                $user = null;
                $isValid = false;
            }
        }

        if ($user && $isValid) {
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["name"]    = $user["name"];
            if ($isAdmin) {
                log_staff_activity($pdo, (int)$user["id"], 'login', 'Admin signed in.');
            } else {
                log_staff_activity($pdo, (int)$user["id"], 'login', 'Staff signed in.');
            }
            header("Location: " . ($isAdmin ? "admin.php" : "account_dashboard.php"));
            exit;
        }
    } else {
        $error = "Invalid credentials.";
    }
}

include "templates/header.php";
?>
<div class="card p-4">
  <h3>Account Dashboard</h3>
  <p class="text-muted mb-3">Staff accounts only. Sign in to access your account dashboard.</p>
  <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="post">
    <div class="mb-3">
      <label>Email</label>
      <input type="email" name="email" class="form-control" required>
    </div>
    <div class="mb-3">
      <label>Password</label>
      <input type="password" name="password" class="form-control" required>
    </div>
    <button class="btn btn-primary">Login</button>
    <a class="btn btn-outline-secondary ms-2" href="client/login.php">Client Portal</a>
  </form>
</div>
<?php include "templates/footer.php"; ?>
