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
    <?php if (!empty($extraStylesheets) && is_array($extraStylesheets)): ?>
      <?php foreach ($extraStylesheets as $href): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars((string)$href, ENT_QUOTES, 'UTF-8') ?>">
      <?php endforeach; ?>
    <?php endif; ?>
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
              <?php if (isset($pdo) && function_exists('current_user_can') && current_user_can($pdo, 'create_journal')): ?>
                <a class="nav-link nav-pill<?= basename($_SERVER['SCRIPT_NAME'] ?? '') === 'purchase_new.php' ? ' active' : '' ?>" href="purchase_new.php">New Entry</a>
              <?php endif; ?>
              <?php if (isset($pdo) && function_exists('current_user_can') && current_user_can($pdo, 'view_journal')): ?>
                <a class="nav-link nav-pill<?= basename($_SERVER['SCRIPT_NAME'] ?? '') === 'purchase_list.php' ? ' active' : '' ?>" href="purchase_list.php">Journal</a>
              <?php endif; ?>
              <?php if (isset($pdo) && function_exists('current_user_can') && current_user_can($pdo, 'view_account_dashboard')): ?>
                <a class="nav-link nav-pill<?= basename($_SERVER['SCRIPT_NAME'] ?? '') === 'account_dashboard.php' ? ' active' : '' ?>" href="account_dashboard.php">Account Dashboard</a>
              <?php endif; ?>
              <?php if (isset($pdo) && function_exists('current_user_can') && (current_user_can($pdo, 'view_projects') || current_user_can($pdo, 'view_finance') || current_user_can($pdo, 'view_hr') || current_user_can($pdo, 'view_inventory') || current_user_can($pdo, 'view_documents') || current_user_can($pdo, 'view_reports'))): ?>
                <div class="nav-item dropdown">
                  <a class="nav-link nav-pill dropdown-toggle<?= basename($_SERVER['SCRIPT_NAME'] ?? '') === 'workspace.php' ? ' active' : '' ?>" href="workspace.php" role="button" data-bs-toggle="dropdown" aria-expanded="false">Workspace</a>
                  <ul class="dropdown-menu dropdown-menu-dark shadow">
                    <li><a class="dropdown-item" href="workspace.php#overview">Overview</a></li>
                    <?php if (current_user_can($pdo, 'view_projects')): ?><li><a class="dropdown-item" href="workspace.php#projects">Projects</a></li><?php endif; ?>
                    <?php if (current_user_can($pdo, 'view_estimates')): ?><li><a class="dropdown-item" href="workspace.php#estimates">Estimates</a></li><?php endif; ?>
                    <?php if (current_user_can($pdo, 'view_proposals')): ?><li><a class="dropdown-item" href="workspace.php#proposals">Proposals</a></li><?php endif; ?>
                    <?php if (current_user_can($pdo, 'view_plans')): ?><li><a class="dropdown-item" href="workspace.php#plans">Plans</a></li><?php endif; ?>
                    <?php if (current_user_can($pdo, 'view_finance')): ?><li><hr class="dropdown-divider"></li><li><a class="dropdown-item" href="workspace.php#expenses">Expenses</a></li><li><a class="dropdown-item" href="workspace.php#invoices">Invoices</a></li><?php endif; ?>
                    <?php if (current_user_can($pdo, 'view_hr')): ?><li><a class="dropdown-item" href="workspace.php#employees">Employees</a></li><li><a class="dropdown-item" href="workspace.php#attendance">Attendance</a></li><?php endif; ?>
                    <?php if (current_user_can($pdo, 'view_inventory')): ?><li><a class="dropdown-item" href="workspace.php#inventory">Inventory</a></li><?php endif; ?>
                    <?php if (current_user_can($pdo, 'view_documents')): ?><li><a class="dropdown-item" href="workspace.php#documents">Documents</a></li><?php endif; ?>
                    <?php if (current_user_can($pdo, 'view_reports')): ?><li><a class="dropdown-item" href="workspace.php#reports">Reports</a></li><?php endif; ?>
                  </ul>
                </div>
              <?php endif; ?>
              <span class="nav-divider d-none d-lg-block"></span>
              <div class="nav-section-label d-lg-none">Management</div>
              <?php if (isset($pdo) && function_exists('current_user_can') && current_user_can($pdo, 'access_admin_panel')): ?>
                <a class="nav-link nav-pill<?= basename($_SERVER['SCRIPT_NAME'] ?? '') === 'admin.php' ? ' active' : '' ?>" href="admin.php">Admin</a>
              <?php endif; ?>
              <?php if (isset($pdo) && function_exists('current_user_can') && current_user_can($pdo, 'view_inquiries')): ?>
                <a class="nav-link nav-pill<?= basename($_SERVER['SCRIPT_NAME'] ?? '') === 'inquiries.php' ? ' active' : '' ?>" href="inquiries.php">Inquiries</a>
              <?php endif; ?>
              <a class="nav-link nav-pill text-warning" href="logout.php">Logout</a>
            <?php else: ?>
              <a class="nav-link nav-pill<?= basename($_SERVER['SCRIPT_NAME'] ?? '') === 'login.php' ? ' active' : '' ?>" href="login.php">Account Dashboard</a>
              <!--li class="nav-item"><a class="nav-link" href="register.php">Register</a></li -->
            <?php endif; ?>
          </div>
        </div>
      </div>
    </nav>

    <?php $pageContainerClass = isset($pageContainerClass) && is_string($pageContainerClass) && $pageContainerClass !== '' ? $pageContainerClass : 'container'; ?>
    <div class="<?= htmlspecialchars($pageContainerClass, ENT_QUOTES, 'UTF-8') ?> py-3">
