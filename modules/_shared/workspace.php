<?php
if (!function_exists('mb_workspace_nav')) {
function mb_workspace_nav(PDO $pdo): array {
    return [
        ['label'=>'Dashboard','href'=>mb_base_url('account_dashboard.php'),'icon'=>'⌂','permission'=>'view_account_dashboard'],
        ['section'=>'Operations'],
        ['label'=>'Projects','href'=>mb_base_url('modules/projects/index.php'),'icon'=>'▦','permission'=>'view_projects'],
        ['label'=>'Estimates','href'=>mb_base_url('modules/estimates/index.php'),'icon'=>'◇','permission'=>'view_estimates'],
        ['label'=>'Proposals','href'=>mb_base_url('modules/proposals/index.php'),'icon'=>'▣','permission'=>'view_proposals'],
        ['label'=>'Plans','href'=>mb_base_url('modules/documents/plans.php'),'icon'=>'⌗','permission'=>'view_plans'],
        ['section'=>'Finance'],
        ['label'=>'Purchase Journal','href'=>mb_base_url('purchase_list.php'),'icon'=>'₱','permission'=>'view_journal'],
        ['label'=>'Expenses','href'=>mb_base_url('modules/finance/index.php'),'icon'=>'◉','permission'=>'view_finance'],
        ['label'=>'Invoices','href'=>mb_base_url('modules/finance/invoices.php'),'icon'=>'▤','permission'=>'view_finance'],
        ['section'=>'People & Assets'],
        ['label'=>'Employees','href'=>mb_base_url('modules/hr/index.php'),'icon'=>'☷','permission'=>'view_hr'],
        ['label'=>'Attendance','href'=>mb_base_url('modules/hr/attendance.php'),'icon'=>'◷','permission'=>'manage_attendance'],
        ['label'=>'Inventory','href'=>mb_base_url('modules/inventory/index.php'),'icon'=>'▥','permission'=>'view_inventory'],
        ['label'=>'Documents','href'=>mb_base_url('modules/documents/index.php'),'icon'=>'□','permission'=>'view_documents'],
        ['label'=>'Reports','href'=>mb_base_url('modules/reports/index.php'),'icon'=>'↗','permission'=>'view_reports'],
        ['section'=>'System'],
        ['label'=>'Admin','href'=>mb_base_url('admin.php'),'icon'=>'⚙','permission'=>'access_admin_panel'],
        ['label'=>'Logout','href'=>mb_base_url('logout.php'),'icon'=>'⇥','permission'=>null],
    ];
}}
function mb_workspace_header(PDO $pdo, string $title, string $active=''): void {
    ensure_maorin_workspace_tables($pdo);
    $name = $_SESSION['name'] ?? 'User';
    ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= htmlspecialchars($title) ?> · Maorin Builders</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="<?= htmlspecialchars(mb_base_url('assets/css/workspace.css')) ?>"></head><body class="mb-workspace-body"><div class="mb-shell"><aside class="mb-sidebar"><div class="mb-brand"><div class="mb-logo">MB</div><div class="mb-brand-text"><div class="mb-brand-title">Maorin Builders</div><div class="mb-brand-sub">Production workspace</div></div></div><nav class="mb-nav"><?php foreach(mb_workspace_nav($pdo) as $item): if(isset($item['section'])): ?><div class="mb-section-title"><?= htmlspecialchars($item['section']) ?></div><?php continue; endif; if(!empty($item['permission']) && !current_user_can($pdo,$item['permission'])) continue; ?><a class="mb-link <?= ($active===$item['label']?'active':'') ?>" href="<?= htmlspecialchars($item['href']) ?>"><span class="mb-link-icon"><?= htmlspecialchars($item['icon']) ?></span><span><?= htmlspecialchars($item['label']) ?></span></a><?php endforeach; ?></nav></aside><main class="mb-main"><header class="mb-topbar"><div class="d-flex align-items-center gap-3"><button class="mb-btn-icon" type="button" data-mb-toggle-sidebar>☰</button><div><div class="text-muted small">Workspace</div><h1 class="h4 m-0"><?= htmlspecialchars($title) ?></h1></div></div><div class="d-flex align-items-center gap-2"><span class="badge text-bg-light"><?= htmlspecialchars(ucfirst(str_replace('_',' ',current_user_role($pdo)))) ?></span><span class="text-muted small"><?= htmlspecialchars((string)$name) ?></span></div></header><section class="mb-content"><?php
}
function mb_workspace_footer(): void { ?></section></main></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script><script src="<?= htmlspecialchars(mb_base_url('assets/js/workspace.js')) ?>"></script></body></html><?php }
function mb_flash(): array { return [$_SESSION['_mb_success'] ?? '', $_SESSION['_mb_error'] ?? '']; unset($_SESSION['_mb_success'], $_SESSION['_mb_error']); }
function mb_set_success(string $msg): void { $_SESSION['_mb_success']=$msg; }
function mb_set_error(string $msg): void { $_SESSION['_mb_error']=$msg; }
function mb_redirect(string $url): void { header('Location: '.$url); exit; }
function mb_project_options(PDO $pdo): array { ensure_maorin_workspace_tables($pdo); return $pdo->query("SELECT id, name FROM mb_projects ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: []; }
?>