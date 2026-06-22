<?php
// public/templates/header.php
require __DIR__ . '/../../config.php';
require __DIR__ . '/../../helpers.php';
// Base path helper so the site works whether installed at domain root or a subfolder.
$__script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

/**
 * Compute the installation root reliably.
 * We want links like:
 *   /maorinbuilders/public/services/architectural.php  -> root = /maorinbuilders
 *   /maorinbuilders/public/contact.php                -> root = /maorinbuilders
 */
$__root = '';
$__pos = strpos($__script, '/public/');
if ($__pos !== false) {
  $__root = rtrim(substr($__script, 0, $__pos), '/');
} else {
  // Fallback: one level up from the current script
  $__root = rtrim(dirname($__script), '/');
  if ($__root === '.' || $__root === '/') $__root = '';
}

function pub_url(string $path): string {
  global $__root;
  if ($path === '' || $path[0] !== '/') $path = '/'.ltrim($path,'/');
  return $__root . $path;
}

/**
 * Active link helper (cosmetic only)
 */
function pub_is_active(string $href): bool {
  $__script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
  $cur = rtrim(explode('?', $__script, 2)[0], '/');
  $target = rtrim($href, '/');
  return $cur === $target;
}

$__logoPath = pub_url('/assets/img/company-logo.png');
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title ?? 'Maorin Builders', ENT_QUOTES, 'UTF-8') ?></title>

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous">

  <!-- Public site styles -->
  <link rel="stylesheet" href="<?= htmlspecialchars(pub_url('/assets/css/public.css'), ENT_QUOTES, 'UTF-8') ?>">

  <!-- Small header-only tweaks (keeps your CSS file intact) -->
<style>
  /* Modern header look */
  .mainnav{ box-shadow: 0 6px 18px rgba(2,6,23,.08); }

  /* Brand area */
  .mainnav .navbar-brand{
    display:flex;
    align-items:center;
    gap:12px;
    padding: 8px 0;
  }

  /* ✅ NO CROPPING WRAPPER */
  .mainnav .brand-logo-wrap{
    display:flex;
    align-items:center;
    overflow: visible;      /* ✅ important */
    height: auto;           /* ✅ important */
  }

  /* ✅ BIGGER LOGO, no cropping */
  .mainnav .brand-logo-inline{
    height: 56px;           /* ✅ increase this (48–68 recommended) */
    width: auto;
    max-width: 520px;       /* keeps it from taking whole row */
    object-fit: contain;
    transform: none;        /* ✅ remove scaling trick */
  }

  /* Nav alignment */
  .mainnav .navbar-collapse{ justify-content:flex-end; }
  .mainnav .navbar-nav{ margin-left:auto !important; gap:6px; }

  /* Links */
  .mainnav .nav-link{
    padding: 8px 12px !important;
    border-radius: 999px;
    font-weight: 700;
    letter-spacing: .01em;
  }
  .mainnav .nav-link.active,
  .mainnav .nav-link[aria-current="page"]{
    background: rgba(228,206,25,.18);
    box-shadow: inset 0 0 0 1px rgba(228,206,25,.40);
  }

  /* Mobile */
  @media (max-width: 576px){
    .mainnav .brand-logo-inline{
      height: 46px;         /* still big on mobile */
      max-width: 100%;
    }
    .mainnav .nav-link{ width:100%; border-radius: 12px; }
    .mainnav .navbar-nav{ padding-top:10px; }
  }
</style>

</head>
<body>

<!-- =========================================================
     MAIN NAVIGATION (logo + links in one professional row)
     ========================================================= -->
<nav class="navbar navbar-expand-lg navbar-dark mainnav">
  <div class="container">

    <!-- Logo brand (professional placement) -->
    <a class="navbar-brand" href="<?= htmlspecialchars(pub_url('/public/index.php'), ENT_QUOTES, 'UTF-8') ?>" aria-label="Go to homepage">
      <span class="brand-logo-wrap">
        <img
          src="<?= htmlspecialchars($__logoPath, ENT_QUOTES, 'UTF-8') ?>"
          alt="Maorin Builders & Supply"
          class="brand-logo-inline"
          loading="eager"
          decoding="async"
        >
      </span>
      <span class="visually-hidden">Maorin Builders</span>
    </a>

    <button class="navbar-toggler" type="button"
            data-bs-toggle="collapse"
            data-bs-target="#pubNav"
            aria-controls="pubNav"
            aria-expanded="false"
            aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="pubNav">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0">

        <?php if (!function_exists('feature_is_enabled') || feature_is_enabled($pdo, 'public_site')): ?>
        <li class="nav-item">
          <?php $href = pub_url('/public/about.php'); ?>
          <a class="nav-link<?= pub_is_active($href) ? ' active' : '' ?>" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>">About</a>
        </li>
        <?php if (function_exists('feature_is_enabled') && feature_is_enabled($pdo, 'public_services_grid')): ?>
        <li class="nav-item">
          <?php $href = pub_url('/public/services.php'); ?>
          <a class="nav-link<?= pub_is_active($href) ? ' active' : '' ?>" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>">Services</a>
        </li>
        <?php endif; ?>
        <?php if (function_exists('feature_is_enabled') && feature_is_enabled($pdo, 'public_projects_grid')): ?>
        <li class="nav-item">
          <?php $href = pub_url('/public/projects.php'); ?>
          <a class="nav-link<?= pub_is_active($href) ? ' active' : '' ?>" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>">Projects</a>
        </li>
        <?php endif; ?>
        <li class="nav-item">
          <?php $href = pub_url('/public/estimator.php'); ?>
          <a class="nav-link<?= pub_is_active($href) ? ' active' : '' ?>" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>">Estimator</a>
        </li>
        <?php if (function_exists('feature_is_enabled') && feature_is_enabled($pdo, 'public_testimonials')): ?>
        <li class="nav-item">
          <?php $href = pub_url('/public/testimonials.php'); ?>
          <a class="nav-link<?= pub_is_active($href) ? ' active' : '' ?>" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>">Testimonials</a>
        </li>
        <?php endif; ?>
        <?php if (!function_exists('feature_is_enabled') || feature_is_enabled($pdo, 'client_portal')): ?>
        <li class="nav-item">
          <?php $href = pub_url('/client/login.php'); ?>
          <a class="nav-link<?= pub_is_active($href) ? ' active' : '' ?>" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>">Client Portal</a>
        </li>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (!function_exists('feature_is_enabled') || feature_is_enabled($pdo, 'public_contact_methods')): ?>
        <li class="nav-item">
          <?php $href = pub_url('/public/contact.php'); ?>
          <a class="nav-link<?= pub_is_active($href) ? ' active' : '' ?>" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>">Contact</a>
        </li>
        <?php endif; ?>

        <li class="nav-item">
          <?php $href = pub_url('/login.php'); ?>
          <a class="nav-link<?= pub_is_active($href) ? ' active' : '' ?>" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>">Account Dashboard</a>
        </li>

      </ul>
    </div>
  </div>
</nav>
