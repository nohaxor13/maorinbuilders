<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/helpers.php';
redirect_if_not_logged_in();
require_admin($pdo);

set_time_limit(0);
@ini_set('memory_limit', '512M');

function db_import_split_sql(string $sql): array {
    $statements = [];
    $buffer = '';
    $inSingle = false;
    $inDouble = false;
    $inBacktick = false;
    $len = strlen($sql);

    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $next = $i + 1 < $len ? $sql[$i + 1] : '';

        if (!$inDouble && !$inBacktick && $ch === "'" && ($i === 0 || $sql[$i - 1] !== '\\')) {
            $inSingle = !$inSingle;
            $buffer .= $ch;
            continue;
        }
        if (!$inSingle && !$inBacktick && $ch === '"' && ($i === 0 || $sql[$i - 1] !== '\\')) {
            $inDouble = !$inDouble;
            $buffer .= $ch;
            continue;
        }
        if (!$inSingle && !$inDouble && $ch === '`') {
            $inBacktick = !$inBacktick;
            $buffer .= $ch;
            continue;
        }

        if (!$inSingle && !$inDouble && !$inBacktick) {
            if ($ch === '-' && $next === '-') {
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
                continue;
            }
            if ($ch === '#') {
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
                continue;
            }
        }

        if ($ch === ';' && !$inSingle && !$inDouble && !$inBacktick) {
            $stmt = trim($buffer);
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $ch;
    }

    $tail = trim($buffer);
    if ($tail !== '') {
        $statements[] = $tail;
    }

    return $statements;
}

function db_import_is_patch_safe_error(Throwable $e): bool {
    $message = strtolower($e->getMessage());
    $safeFragments = [
        'already exists',
        'duplicate column name',
        'duplicate key name',
        'duplicate entry',
        'multiple primary key defined',
        'duplicate foreign key constraint name',
    ];
    foreach ($safeFragments as $fragment) {
        if (str_contains($message, $fragment)) {
            return true;
        }
    }
    return false;
}

function db_import_prepare_statement(string $statement, bool $patchMode): ?string {
    $trimmed = trim($statement);
    if ($trimmed === '') {
        return null;
    }

    if (!$patchMode) {
        return $trimmed;
    }

    if (preg_match('/^(DROP\s+TABLE|DROP\s+VIEW|DROP\s+DATABASE)\b/i', $trimmed)) {
        return null;
    }
    if (preg_match('/^CREATE\s+TABLE\b/i', $trimmed) && !preg_match('/^CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\b/i', $trimmed)) {
        return preg_replace('/^CREATE\s+TABLE\b/i', 'CREATE TABLE IF NOT EXISTS', $trimmed, 1) ?: $trimmed;
    }
    if (preg_match('/^INSERT\s+INTO\b/i', $trimmed)) {
        return preg_replace('/^INSERT\s+INTO\b/i', 'INSERT IGNORE INTO', $trimmed, 1) ?: $trimmed;
    }

    return $trimmed;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $mode = (string)($_POST['import_mode'] ?? 'patch');
    $patchMode = $mode !== 'restore';
    if (empty($_FILES['sql_file']['tmp_name']) || !is_uploaded_file($_FILES['sql_file']['tmp_name'])) {
        $error = 'Please choose a valid .sql file.';
    } else {
        $tmpPath = (string)$_FILES['sql_file']['tmp_name'];
        $name = (string)($_FILES['sql_file']['name'] ?? 'backup.sql');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if ($ext !== 'sql') {
            $error = 'Only .sql files are accepted.';
        } else {
            $sql = (string)file_get_contents($tmpPath);
            if (trim($sql) === '') {
                $error = 'The uploaded file is empty.';
            } else {
                try {
                    $statements = db_import_split_sql($sql);
                    $executed = 0;
                    $skipped = 0;
                    foreach ($statements as $statement) {
                        $trimmed = ltrim($statement);
                        if ($trimmed === '') {
                            continue;
                        }
                        $prepared = db_import_prepare_statement($trimmed, $patchMode);
                        if ($prepared === null) {
                            $skipped++;
                            continue;
                        }
                        if (stripos($prepared, 'SET FOREIGN_KEY_CHECKS=') === 0) {
                            $pdo->exec($prepared);
                            continue;
                        }
                        if (stripos($prepared, 'SET NAMES') === 0) {
                            $pdo->exec($prepared);
                            continue;
                        }
                        try {
                            $pdo->exec($prepared);
                            $executed++;
                        } catch (Throwable $statementError) {
                            if ($patchMode && db_import_is_patch_safe_error($statementError)) {
                                $skipped++;
                                continue;
                            }
                            throw $statementError;
                        }
                    }
                    $success = $patchMode
                        ? 'Database patch completed. Executed ' . number_format($executed) . ' SQL statements and skipped ' . number_format($skipped) . ' existing/conflicting statements.'
                        : 'Database import completed successfully. Executed ' . number_format($executed) . ' SQL statements.';
                } catch (Throwable $e) {
                    $error = 'Import failed: ' . $e->getMessage();
                }
            }
        }
    }
}

$title = 'Database Import';
include __DIR__ . '/templates/header.php';
?>
<div class="card p-4">
  <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
    <div>
      <h3 class="mb-1">Database Import</h3>
      <div class="text-muted">Restore a `.sql` backup file from the admin area.</div>
    </div>
    <a class="btn btn-outline-secondary" href="admin.php">Back to Admin</a>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <div class="alert alert-warning">
    Patch mode is safest for existing databases. It skips destructive drop statements and ignores rows or schema that already exist.
  </div>

  <form method="post" enctype="multipart/form-data" class="row g-3">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <div class="col-md-4">
      <label class="form-label">Import mode</label>
      <select name="import_mode" class="form-select">
        <option value="patch" selected>Patch existing database</option>
        <option value="restore">Full restore</option>
      </select>
    </div>
    <div class="col-12">
      <label class="form-label">SQL file</label>
      <input type="file" name="sql_file" class="form-control" accept=".sql" required>
    </div>
    <div class="col-12">
      <button class="btn btn-danger">Import Database</button>
    </div>
  </form>
</div>
<?php include __DIR__ . '/templates/footer.php'; ?>
