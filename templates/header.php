<?php
// templates/header.php (fixed: adds Bootstrap includes + mobile toggler)
if (!function_exists('is_logged_in')) {
  require_once __DIR__ . '/../helpers.php';
}
?><!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Maorin Builders</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <!-- App CSS -->
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
      .app-navbar {
        box-shadow: 0 10px 28px rgba(15, 23, 42, 0.18);
        border-bottom: 1px solid rgba(255,255,255,0.06);
      }
      .app-navbar .navbar-brand {
        font-weight: 700;
        letter-spacing: .01em;
      }
      .nav-pill {
        border-radius: 999px;
        padding: .55rem .9rem;
        transition: background-color .15s ease, color .15s ease, transform .15s ease;
      }
      .nav-pill:hover {
        background: rgba(255,255,255,.08);
        transform: translateY(-1px);
      }
      .nav-pill.active {
        background: rgba(13,110,253,.22);
        color: #fff !important;
      }
      .nav-section-label {
        font-size: .72rem;
        letter-spacing: .12em;
        text-transform: uppercase;
        color: rgba(255,255,255,.5);
        padding: 0 .75rem .25rem;
      }
      @media (min-width: 992px) {
        .nav-divider {
          width: 1px;
          height: 28px;
          background: rgba(255,255,255,.12);
          margin: 0 .25rem;
          align-self: center;
        }
      }
    </style>
  </head>
  <body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top app-navbar">
      <div class="container-fluid">
        <a class="navbar-brand" href="index.php">Maorin Builders</a>

        <!-- Mobile toggler -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Collapsible content -->
        <div class="collapse navbar-collapse" id="mainNav">
          <div class="navbar-nav me-auto mb-2 mb-lg-0 align-items-lg-center gap-lg-1">
            <?php if (is_logged_in()): ?>
              <div class="nav-section-label d-lg-none">Workspace</div>
              <a class="nav-link nav-pill<?= basename($_SERVER['SCRIPT_NAME'] ?? '') === 'purchase_new.php' ? ' active' : '' ?>" href="purchase_new.php">New Entry</a>
              <a class="nav-link nav-pill<?= basename($_SERVER['SCRIPT_NAME'] ?? '') === 'purchase_list.php' ? ' active' : '' ?>" href="purchase_list.php">Journal</a>
              <a class="nav-link nav-pill<?= basename($_SERVER['SCRIPT_NAME'] ?? '') === 'account_dashboard.php' ? ' active' : '' ?>" href="account_dashboard.php">Account Dashboard</a>
              <span class="nav-divider d-none d-lg-block"></span>
              <div class="nav-section-label d-lg-none">Management</div>
              <?php if (isset($pdo) && function_exists('current_user_is_admin') && current_user_is_admin($pdo)): ?>
                <a class="nav-link nav-pill<?= basename($_SERVER['SCRIPT_NAME'] ?? '') === 'admin.php' ? ' active' : '' ?>" href="admin.php">Admin</a>
              <?php endif; ?>
              <a class="nav-link nav-pill<?= basename($_SERVER['SCRIPT_NAME'] ?? '') === 'inquiries.php' ? ' active' : '' ?>" href="inquiries.php">Inquiries</a>
              <a class="nav-link nav-pill text-warning" href="logout.php">Logout</a>
            <?php else: ?>
              <a class="nav-link nav-pill<?= basename($_SERVER['SCRIPT_NAME'] ?? '') === 'login.php' ? ' active' : '' ?>" href="login.php">Account Dashboard</a>
              <!--li class="nav-item"><a class="nav-link" href="register.php">Register</a></li -->
            <?php endif; ?>
          </div>
        </div>
      </div>
    </nav>

    <div class="container py-3">
