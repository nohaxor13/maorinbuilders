<?php
declare(strict_types=1);
require __DIR__ . '/../../config.php';
require __DIR__ . '/../../helpers.php';
ensure_client_portal_tables($pdo);

$clientName = '';
if (is_client_logged_in()) {
  $st = $pdo->prepare("SELECT name FROM website_clients WHERE id=? LIMIT 1");
  $st->execute([(int)$_SESSION['client_id']]);
  $clientName = (string)($st->fetchColumn() ?: '');
}
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Client Portal • Maorin Builders</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/app.css">
  <style>
    .kpi{border:1px solid rgba(0,0,0,.08); border-radius:14px; padding:14px; background:#fff}
    .muted{color:#6b7280}
    .file-pill{border:1px solid rgba(0,0,0,.1); border-radius:999px; padding:6px 10px; display:inline-flex; gap:8px; align-items:center}
    .prog-photo{max-width:100%; border-radius:12px; border:1px solid rgba(0,0,0,.08)}
  </style>
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container">
    <a class="navbar-brand" href="../public/index.php">Maorin Builders • Client Portal</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#cpNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="cpNav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="index.php">My Projects</a></li>
      </ul>
      <div class="d-flex gap-2 align-items-center">
        <?php if (is_client_logged_in()): ?>
          <span class="text-white-50 small">Hi, <?= htmlspecialchars($clientName) ?></span>
          <a class="btn btn-sm btn-outline-light" href="logout.php">Logout</a>
        <?php else: ?>
          <a class="btn btn-sm btn-outline-light" href="login.php">Login</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>
<main class="container py-4">
