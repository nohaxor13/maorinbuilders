<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
redirect_if_not_logged_in();
ensure_maorin_workspace_tables($pdo);
mb_require_any_permission($pdo, ['view_projects','view_estimates','view_proposals','view_plans','view_finance','view_hr','view_inventory','view_documents','view_reports']);
$title = 'Workspace';
$extraStylesheets = ['assets/css/workspace.css','assets/css/proposal-letter.css'];
$pageContainerClass = 'container-fluid px-3 px-lg-4 px-xxl-5';
include __DIR__ . '/templates/header.php';
$role = current_user_role($pdo);
$name = $_SESSION['name'] ?? 'User';
?>
<div class="workspace-spa" id="mbWorkspaceSpa" data-default-module="overview">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
    <div>
      <div class="text-muted small text-uppercase fw-semibold">Maorin Builders workspace</div>
      <h3 class="mb-1">Operations Center</h3>
      <div class="text-muted">Projects, estimates, proposals, finance, HR, inventory, documents, plans, and reports in one SPA screen.</div>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <span class="badge text-bg-light border"><?= htmlspecialchars(ucfirst(str_replace('_',' ', $role)), ENT_QUOTES, 'UTF-8') ?></span>
      <span class="text-muted small"><?= htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
  </div>

  <div class="card border-0 shadow-sm workspace-shell-card">
    <div class="card-header bg-white border-0 pb-0">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <ul class="nav nav-pills workspace-tabs" id="workspaceTabs" role="tablist">
          <li class="nav-item"><button class="nav-link active" type="button" data-module="overview">Overview</button></li>
          <?php if (current_user_can($pdo, 'view_projects')): ?><li class="nav-item"><button class="nav-link" type="button" data-module="projects">Projects</button></li><?php endif; ?>
          <?php if (current_user_can($pdo, 'view_estimates')): ?><li class="nav-item"><button class="nav-link" type="button" data-module="estimates">Estimates</button></li><?php endif; ?>
          <?php if (current_user_can($pdo, 'view_proposals')): ?><li class="nav-item"><button class="nav-link" type="button" data-module="proposals">Proposals</button></li><?php endif; ?>
          <li class="nav-item dropdown">
            <button class="nav-link dropdown-toggle" data-bs-toggle="dropdown" type="button">More Workspace</button>
            <ul class="dropdown-menu shadow-sm">
              <?php if (current_user_can($pdo, 'view_plans') && feature_is_enabled($pdo, 'plans')): ?><li><button class="dropdown-item" type="button" data-module="plans">Plans</button></li><?php endif; ?>
              <?php if (current_user_can($pdo, 'view_finance')): ?><li><button class="dropdown-item" type="button" data-module="expenses">Expenses</button></li><li><button class="dropdown-item" type="button" data-module="invoices">Invoices</button></li><?php endif; ?>
              <?php if (current_user_can($pdo, 'view_hr')): ?><li><button class="dropdown-item" type="button" data-module="employees">Employees</button></li><li><button class="dropdown-item" type="button" data-module="attendance">Attendance</button></li><li><button class="dropdown-item" type="button" data-module="payroll">Payroll</button></li><?php if (current_user_can($pdo, 'manage_employees')): ?><li><hr class="dropdown-divider"></li><li><button class="dropdown-item" type="button" data-module="departments">Departments</button></li><li><button class="dropdown-item" type="button" data-module="job_titles">Job Titles & Rates</button></li><?php endif; ?><?php endif; ?>
              <?php if (current_user_can($pdo, 'view_inventory')): ?><li><button class="dropdown-item" type="button" data-module="inventory">Inventory</button></li><?php endif; ?>
              <?php if (current_user_can($pdo, 'view_documents')): ?><li><button class="dropdown-item" type="button" data-module="documents">Documents</button></li><?php endif; ?>
              <?php if (current_user_can($pdo, 'view_reports')): ?><li><hr class="dropdown-divider"></li><li><button class="dropdown-item" type="button" data-module="reports">Reports</button></li><?php endif; ?>
            </ul>
          </li>
        </ul>
        <div class="workspace-toolbar d-flex gap-2">
          <input class="form-control form-control-sm" id="workspaceSearch" placeholder="Search current module">
          <button class="btn btn-sm btn-outline-secondary" id="workspaceRefresh" type="button">Refresh</button>
        </div>
      </div>
    </div>
    <div class="card-body">
      <div id="workspaceNotice" class="d-none alert"></div>
      <div id="workspaceContent" class="workspace-content-area">
        <div class="text-center text-muted py-5">Loading workspace...</div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/proposals/partials/proposal_letter_modal.php'; ?>
<script>
window.MB_WORKSPACE = { api: 'modules/workspace_api.php', csrf: <?= json_encode(csrf_token()) ?> };
</script>
<script src="assets/js/proposal-letter.js"></script>
<script src="assets/js/workspace-spa.js"></script>
<?php include __DIR__ . '/templates/footer.php'; ?>
