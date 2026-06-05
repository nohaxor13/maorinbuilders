<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/helpers.php';
redirect_if_not_logged_in();
require_permission($pdo, 'run_database_tools');

set_time_limit(0);
@ini_set('memory_limit', '512M');

function db_quote_ident(string $name): string {
    return '`' . str_replace('`', '``', $name) . '`';
}

function db_sql_literal(PDO $pdo, mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    if (is_int($value) || is_float($value)) {
        return (string)$value;
    }
    if (is_resource($value)) {
        $value = stream_get_contents($value);
    }

    $value = (string)$value;
    $quoted = $pdo->quote($value);
    if ($quoted !== false) {
        return $quoted;
    }

    return '0x' . bin2hex($value);
}

function db_normalize_sql_collations(string $sql): string
{
    $sql = str_replace(
        [
            'utf8mb4_uca1400_ai_ci',
            'utf8mb4_0900_ai_ci',
        ],
        'utf8mb4_unicode_ci',
        $sql
    );

    $sql = preg_replace(
        '/DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_[a-z0-9_]+/i',
        'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        $sql
    ) ?? $sql;

    $sql = preg_replace(
        '/CHARACTER SET utf8mb4 COLLATE utf8mb4_[a-z0-9_]+/i',
        'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
        $sql
    ) ?? $sql;

    $sql = preg_replace(
        '/COLLATE=utf8mb4_[a-z0-9_]+/i',
        'COLLATE=utf8mb4_unicode_ci',
        $sql
    ) ?? $sql;

    $sql = preg_replace(
        '/COLLATE utf8mb4_[a-z0-9_]+/i',
        'COLLATE utf8mb4_unicode_ci',
        $sql
    ) ?? $sql;

    return $sql;
}

$databaseName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ($databaseName === '') {
    http_response_code(500);
    exit('Unable to determine active database.');
}

$tableStmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
$tables = [];
while ($row = $tableStmt->fetch(PDO::FETCH_NUM)) {
    if (!empty($row[0])) {
        $tables[] = (string)$row[0];
    }
}

$downloadName = $databaseName . '_backup_' . date('Ymd_His') . '.sql';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('X-Content-Type-Options: nosniff');

echo "-- Maorin Builders database backup\n";
echo "-- Database: {$databaseName}\n";
echo "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
echo "SET NAMES utf8mb4;\n";
echo "SET collation_connection = 'utf8mb4_unicode_ci';\n";
echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

foreach ($tables as $table) {
    $quotedTable = db_quote_ident($table);

    echo "-- --------------------------------------------------------\n";
    echo "-- Table structure for table {$quotedTable}\n";
    echo "-- --------------------------------------------------------\n\n";
    echo "DROP TABLE IF EXISTS {$quotedTable};\n";

    $createRow = $pdo->query("SHOW CREATE TABLE {$quotedTable}")->fetch(PDO::FETCH_ASSOC);
    if (!$createRow || !isset($createRow['Create Table'])) {
        continue;
    }
    $createSql = db_normalize_sql_collations((string)$createRow['Create Table']);
    echo $createSql . ";\n\n";

    $columns = [];
    $colStmt = $pdo->query("SHOW COLUMNS FROM {$quotedTable}");
    while ($col = $colStmt->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($col['Field'])) {
            $columns[] = (string)$col['Field'];
        }
    }

    if (!$columns) {
        continue;
    }

    echo "-- Dumping data for table {$quotedTable}\n";

    $dataStmt = $pdo->query("SELECT * FROM {$quotedTable}");
    $batch = [];
    $batchSize = 100;

    while ($row = $dataStmt->fetch(PDO::FETCH_ASSOC)) {
        $values = [];
        foreach ($columns as $column) {
            $values[] = db_sql_literal($pdo, $row[$column] ?? null);
        }
        $batch[] = '(' . implode(', ', $values) . ')';

        if (count($batch) >= $batchSize) {
            echo "INSERT INTO {$quotedTable} (" . implode(', ', array_map('db_quote_ident', $columns)) . ") VALUES\n";
            echo implode(",\n", $batch) . ";\n\n";
            $batch = [];
        }
    }

    if ($batch) {
        echo "INSERT INTO {$quotedTable} (" . implode(', ', array_map('db_quote_ident', $columns)) . ") VALUES\n";
        echo implode(",\n", $batch) . ";\n\n";
    }
}

echo "SET FOREIGN_KEY_CHECKS=1;\n";
