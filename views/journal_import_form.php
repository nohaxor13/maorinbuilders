<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';
redirect_if_not_logged_in();
require_admin($pdo);
?>
<!doctype html>
<html><body class="p-4">
  <h3>Import Journal</h3>
  <form method="post" enctype="multipart/form-data">
    <div>
      <label>Excel file (.xlsx):</label>
      <input type="file" name="file" accept=".xlsx" required>
    </div>
    <div class="mt-2 form-check">
      <input type="checkbox" class="form-check-input" name="dry_run" value="1" id="dryrun" checked>
      <label for="dryrun" class="form-check-label">Dry run (preview only)</label>
    </div>
    <button class="btn btn-primary mt-3" type="submit">Import</button>
  </form>
</body></html>
