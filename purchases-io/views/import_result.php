<?php
if (session_status() === PHP_SESSION_NONE) session_start();

/* ---------- Safe defaults & guard ---------- */
if (!isset($results) && !isset($errors)) {
  // Likely accessed directly; send user back to the import UI.
  header('Location: ./import.php');
  exit;
}

$results = $results ?? [
  'total_rows' => 0,
  'inserted'   => 0,
  'skipped'    => 0,
  'rows'       => [],
];
$errors  = $errors  ?? [];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Import Result</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
    integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
    crossorigin="anonymous"
  />
  <style>
    .table-fixed { table-layout: fixed; }
    .small-cell { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px; }
  </style>
</head>
<body class="p-4">
  <h3 class="mb-3">Import Result</h3>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <?php foreach ($errors as $e) echo '<div>'.htmlspecialchars($e).'</div>'; ?>
    </div>
    <a class="btn btn-secondary" href="./import.php">Back</a>
  <?php else: ?>
    <div class="alert alert-info">
      Total Rows Found: <strong><?php echo (int)($results['total_rows'] ?? 0); ?></strong><br>
      Inserted: <strong><?php echo (int)($results['inserted'] ?? 0); ?></strong><br>
      Skipped/DRY: <strong><?php echo (int)($results['skipped'] ?? 0); ?></strong>
    </div>

    <?php if (!empty($results['rows'])): ?>
      <div class="table-responsive">
        <table class="table table-sm table-bordered table-fixed">
          <thead class="table-light">
            <tr>
              <th style="width: 80px;">Status</th>
              <th>Data (bound values)</th>
              <th>Error</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($results['rows'] as $r): ?>
            <tr>
              <td>
                <span class="badge bg-<?php echo (($r['status'] ?? '') === 'ERROR') ? 'danger' : 'secondary'; ?>">
                  <?php echo htmlspecialchars($r['status'] ?? ''); ?>
                </span>
              </td>
              <td class="small-cell">
                <code><?php echo htmlspecialchars(json_encode($r['data'] ?? [], JSON_UNESCAPED_UNICODE)); ?></code>
              </td>
              <td class="small-cell"><?php echo htmlspecialchars($r['error'] ?? ''); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['dry_run']) && $_POST['dry_run'] === '1'): ?>
      <form method="post" enctype="multipart/form-data" class="mt-3">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
        <input type="hidden" name="dry_run" value="0">
        <div class="alert alert-warning mb-2">
          Dry Run complete. To commit inserts, upload the same file again and uncheck Dry Run.
        </div>
        <div class="mb-2">
          <input type="file" class="form-control" name="file" accept=".xlsx,.xls" required>
        </div>
        <button class="btn btn-success">Import Now</button>
        <a class="btn btn-secondary" href="./import.php">Back</a>
      </form>
    <?php else: ?>
      <a class="btn btn-primary" href="./import.php">New Import</a>
    <?php endif; ?>
  <?php endif; ?>
</body>
</html>
