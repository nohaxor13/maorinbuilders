<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/includes/dashboard_bootstrap.php';

redirect_if_not_logged_in();
if (isset($pdo) && $pdo instanceof PDO) {
    ensure_maorin_workspace_tables($pdo);
    if (!current_user_can($pdo, 'view_account_dashboard')) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

$title = 'Account Dashboard';
$extraStylesheets = ['assets/css/account-dashboard.css?v=20260610-root'];
$pageContainerClass = 'container-fluid px-0';
include __DIR__ . '/templates/header.php';

$heroName = $dashboard['user']['name'];
$heroAvatar = $dashboard['user']['avatar'];
$heroMeta = [
    ['label' => $dashboard['user']['role'], 'class' => 'tag-blue'],
    ['label' => 'Department: ' . $dashboard['user']['department'], 'class' => 'tag-gray'],
    ['label' => 'Last login: ' . $dashboard['user']['last_login'], 'class' => 'tag-gray'],
];
?>

<div class="account-dashboard-shell">
  <div class="dashboard-grid">
    <main class="main-col">
      <section class="hero-card dash-card">
        <div class="hero-left">
          <img class="avatar-lg" src="<?= e($heroAvatar) ?>" alt="Profile">
          <div class="hero-copy">
            <div class="eyebrow">Welcome back</div>
            <h1>Welcome back, <?= e($heroName) ?>! <span aria-hidden="true">👋</span></h1>
            <p>Stay productive and keep your purchase journal up to date.</p>
            <div class="hero-tags">
              <?php foreach ($heroMeta as $meta): ?>
                <span class="<?= e($meta['class']) ?>"><?= e($meta['label']) ?></span>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </section>

      <section class="kpi-grid">
        <?php
        $kpiClasses = ['blue','orange','purple','teal','rose'];
        $visibleKpis = array_values(array_filter($dashboard['kpis'], fn($kpi) => ($kpi['label'] ?? '') !== 'Total Cash'));
        foreach ($visibleKpis as $i => $kpi):
          $tone = $kpi['tone'] ?? $kpiClasses[$i] ?? 'blue';
        ?>
          <article class="kpi-card dash-card">
            <div class="kpi-icon tone-<?= e($tone) ?>"><?= e($kpi['icon']) ?></div>
            <div class="kpi-body">
              <div class="kpi-label"><?= e($kpi['label']) ?></div>
              <div class="kpi-value"><?= e($kpi['value']) ?></div>
              <div class="kpi-note"><?= e($kpi['note']) ?></div>
              <div class="kpi-delta"><?= e($kpi['delta'] ?? '') ?></div>
            </div>
          </article>
        <?php endforeach; ?>
      </section>

      <section class="lower-grid">
        <section class="dash-card chart-card">
          <div class="section-head">
            <h2>7-Day Journal Activity</h2>
            <select aria-label="Activity range">
              <option>Last 7 Days</option>
            </select>
          </div>
          <div class="chart-wrap">
            <div class="y-axis">
              <span>40</span><span>30</span><span>20</span><span>10</span><span>0</span>
            </div>
            <div class="bar-chart">
              <?php foreach ($dashboard['activity7'] as $bar): ?>
                <?php $h = max(8, (int)$bar['count'] * 9); ?>
                <div class="bar-item">
                  <b style="height:<?= $h ?>px"><span><?= (int)$bar['count'] ?></span></b>
                  <small><?= e($bar['label']) ?></small>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </section>

        <aside class="quick-card dash-card">
          <h2>Quick Actions</h2>
          <a class="action-btn primary" href="purchase_new.php">New Entry</a>
          <a class="action-btn gold" href="purchase_list.php">Open Journal</a>
          <a class="action-btn" href="inquiries.php">Inquiries</a>
          <a class="action-btn" href="workspace.php">Reports</a>
        </aside>
      </section>

      <section class="recent-card dash-card">
        <div class="section-head">
          <h2>Recent Purchase Journal Entries</h2>
          <form method="get" class="search-row">
            <input name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Search supplier, project...">
            <button type="submit">Search</button>
            <a href="purchase_list.php">View All</a>
          </form>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Date</th>
                <th>Supplier</th>
                <th>Project</th>
                <th>Category</th>
                <th>Cash Amount</th>
                <th>VAT Status</th>
                <th>OR #</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($dashboard['recent_entries'] as $row): ?>
                <?php $vatClass = strtolower(str_replace([' ', '_'], '-', (string)$row['vat_status'])); ?>
                <tr>
                  <td><?= e($row['date']) ?></td>
                  <td><?= e($row['supplier']) ?></td>
                  <td><?= e($row['project']) ?></td>
                  <td><?= e($row['category']) ?></td>
                  <td class="money">₱<?= e($row['cash']) ?></td>
                  <td><span class="vat-chip <?= e($vatClass) ?>"><?= e($row['vat_status']) ?></span></td>
                  <td><?= e($row['reference']) ?></td>
                  <td><a class="icon-btn" href="purchase_list.php?q=<?= urlencode($row['reference']) ?>">View</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <div class="view-all-row"><a href="purchase_list.php">View all entries →</a></div>
        </div>
      </section>
    </main>

    <aside class="side-col">
      <section class="dash-card security-card">
        <div class="section-title">Account Security</div>
        <p>Keep your account secure with a strong password. We recommend using a strong password.</p>
        <a class="profile-btn" href="change_password.php">Change Password</a>
      </section>

      <section class="dash-card inquiry-card">
        <div class="section-title">Inquiry Status</div>
        <div class="inquiry-grid">
          <div class="mini-box purple"><span>New</span><b><?= e($dashboard['inquiries']['new']) ?></b></div>
          <div class="mini-box teal"><span>Contacted</span><b><?= e($dashboard['inquiries']['contacted']) ?></b></div>
          <div class="mini-box rose"><span>Closed</span><b><?= e($dashboard['inquiries']['closed']) ?></b></div>
        </div>
        <a class="text-link" href="inquiries.php">View inquiries →</a>
      </section>

      <section class="dash-card activity-card">
        <div class="section-title">Recent Activity</div>
        <ul class="activity-list">
          <?php foreach ($dashboard['recent_activity'] as $a): ?>
            <li>
              <span class="activity-dot"></span>
              <div>
                <b><?= e($a['title']) ?></b>
                <span><?= e($a['detail']) ?></span>
                <small><?= e($a['time']) ?></small>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    </aside>
  </div>
</div>

<?php include __DIR__ . '/templates/footer.php'; ?>
