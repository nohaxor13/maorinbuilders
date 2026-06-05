<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../helpers.php';
redirect_if_not_logged_in();
require_admin($pdo);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Import Purchase Journal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link
  href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
  rel="stylesheet"
  integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
  crossorigin="anonymous"
/>

</head>
<body class="p-4">
  <h3 class="mb-3">Import Purchase Journal</h3>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <?php foreach ($errors as $e) echo '<div>'.htmlspecialchars($e).'</div>'; ?>
    </div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="border rounded p-3 bg-light">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
    <div class="mb-3">
      <label class="form-label fw-semibold">Excel file (.xlsx)</label>
      <input type="file" class="form-control" name="file" accept=".xlsx,.xls" required>
      <div class="form-text">Use your <code>reference.xlsx</code> layout or the provided sample.</div>
    </div>
    <div class="form-check mb-3">
      <input class="form-check-input" type="checkbox" value="1" id="dryrun" name="dry_run" checked>
      <label class="form-check-label" for="dryrun">Dry Run (do not insert, just preview)</label>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-primary">Upload & Validate</button>
      <a class="btn btn-outline-secondary" href="/MaorinBuilders/purchases-io/export.php">Export Current Journal</a>
    </div>
  </form>
</body>
</html>
