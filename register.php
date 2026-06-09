<?php
require "config.php";
require "helpers.php"; // <-- needed so header.php can call is_logged_in()

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name  = trim($_POST["name"]);
    $email = strtolower(trim($_POST["email"]));
    $pass  = password_hash($_POST["password"], PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO users (name,email,password_hash) VALUES (?,?,?)");
    try {
        $stmt->execute([$name, $email, $pass]);
        $userId = (int)$pdo->lastInsertId();
        ensure_table_user_roles($pdo);
        $roleStmt = $pdo->prepare("INSERT INTO user_roles (user_id, role) VALUES (?, 'staff')");
        $roleStmt->execute([$userId]);
        header("Location: login.php");
        exit;
    } catch (PDOException $e) {
        $error = "Email already exists. Please use another.";
    }
}

include "templates/header.php";
?>
<div class="card p-4">
  <h3>Create Staff Account</h3>
  <p class="text-muted">This form creates a staff login in the main `users` table.</p>
  <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="post" class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Name</label>
      <input type="text" name="name" class="form-control" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Email</label>
      <input type="text" name="email" class="form-control" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Password</label>
      <input type="password" name="password" class="form-control" required>
    </div>
    <div class="col-12">
      <button class="btn btn-success">Register</button>
      <a href="login.php" class="btn btn-secondary">Back to Login</a>
    </div>
  </form>
</div>
<?php include "templates/footer.php"; ?>
