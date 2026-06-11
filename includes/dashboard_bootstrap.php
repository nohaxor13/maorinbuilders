<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

foreach ([__DIR__ . '/db.php', dirname(__DIR__) . '/includes/db.php', dirname(__DIR__) . '/db.php', dirname(__DIR__) . '/config.php'] as $f) {
    if (is_file($f)) {
        require_once $f;
        break;
    }
}

if (isset($pdo) && $pdo instanceof PDO) {
    $db = $pdo;
} elseif (isset($conn) && $conn instanceof PDO) {
    $db = $conn;
} elseif (isset($dbh) && $dbh instanceof PDO) {
    $db = $dbh;
} else {
    $db = null;
}

function dash_scalar($db, string $sql, array $p = []) {
    if (!$db) return 0;
    try {
        $s = $db->prepare($sql);
        $s->execute($p);
        return $s->fetchColumn() ?: 0;
    } catch (Throwable $e) {
        return 0;
    }
}

function dash_rows($db, string $sql, array $p = []): array {
    if (!$db) return [];
    try {
        $s = $db->prepare($sql);
        $s->execute($p);
        return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function dash_avatar_svg(string $name): string {
    $initials = '';
    foreach (preg_split('/\s+/', trim($name)) ?: [] as $part) {
        if ($part !== '') {
            $initials .= strtoupper(substr($part, 0, 1));
        }
        if (strlen($initials) >= 2) break;
    }
    $initials = $initials !== '' ? substr($initials, 0, 2) : 'MB';
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeInitials = htmlspecialchars($initials, ENT_QUOTES, 'UTF-8');
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 96 96" role="img" aria-label="' . $safeName . '"><defs><linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#dbeafe"/><stop offset="100%" stop-color="#bfdbfe"/></linearGradient></defs><rect width="96" height="96" rx="48" fill="url(#g)"/><text x="50%" y="54%" text-anchor="middle" font-family="Segoe UI, Arial, sans-serif" font-size="34" font-weight="700" fill="#0f2748">' . $safeInitials . '</text></svg>';
    return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
}

function dash_media_url(?string $path): string {
    $path = trim((string)$path);
    if ($path === '') {
        return '';
    }
    if (preg_match('~^(?:https?:)?//~i', $path) || str_starts_with($path, 'data:') || str_starts_with($path, '/')) {
        return $path;
    }
    return $path;
}

$userName = $_SESSION['name'] ?? $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Maorin User';
$userEmail = $_SESSION['email'] ?? '';
$role = $_SESSION['role'] ?? $_SESSION['user_type'] ?? 'Staff';
$profilePhoto = '';

if ($db instanceof PDO) {
    try {
        $photoStmt = $db->prepare(
            "SELECT photo_path
               FROM mb_employees
              WHERE photo_path IS NOT NULL AND photo_path <> ''
                AND (
                  LOWER(email) = LOWER(?)
                  OR LOWER(full_name) = LOWER(?)
                  OR LOWER(full_name) LIKE LOWER(?)
                  OR LOWER(email) LIKE LOWER(?)
                )
           ORDER BY
                CASE
                  WHEN LOWER(email) = LOWER(?) THEN 0
                  WHEN LOWER(full_name) = LOWER(?) THEN 1
                  WHEN LOWER(full_name) LIKE LOWER(?) THEN 2
                  WHEN LOWER(email) LIKE LOWER(?) THEN 3
                  ELSE 4
                END,
                updated_at DESC,
                id DESC
              LIMIT 1"
        );
        $photoStmt->execute([
            $userEmail,
            $userName,
            '%' . $userName . '%',
            '%' . $userEmail . '%',
            $userEmail,
            $userName,
            '%' . $userName . '%',
            '%' . $userEmail . '%',
        ]);
        $profilePhoto = (string)($photoStmt->fetchColumn() ?: '');
    } catch (Throwable $e) {
        $profilePhoto = '';
    }
}

$totalEntries = dash_scalar($db, "SELECT COUNT(*) FROM purchase_entries");
$totalCash = dash_scalar($db, "SELECT COALESCE(SUM(cash),0) FROM purchase_entries");
$weekEntries = dash_scalar($db, "SELECT COUNT(*) FROM purchase_entries WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");

$rows = dash_rows($db, "SELECT date, supplier, project_name, category, cash, vat_nvat, reference FROM purchase_entries ORDER BY date DESC, id DESC LIMIT 6");

$activity = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $activity[] = ['label' => date('D', strtotime($d)), 'count' => (int)dash_scalar($db, "SELECT COUNT(*) FROM purchase_entries WHERE date=?", [$d])];
}

$dashboard = [
    'user' => [
        'name' => $userName,
        'email' => $userEmail,
        'role' => $role,
        'department' => $_SESSION['department'] ?? 'Purchasing',
        'last_login' => $_SESSION['last_login'] ?? date('M d, Y h:i A'),
        'phone' => $_SESSION['phone'] ?? '',
        'avatar' => $profilePhoto !== '' ? dash_media_url($profilePhoto) : dash_avatar_svg($userName),
    ],
    'kpis' => [
        ['icon' => '📒', 'tone' => 'blue', 'label' => 'Total Entries', 'value' => number_format((float)$totalEntries), 'note' => 'All time journal entries', 'delta' => '12% vs last month'],
        ['icon' => '💰', 'tone' => 'green', 'label' => 'Total Cash', 'value' => '₱' . number_format((float)$totalCash, 2), 'note' => 'All time cash amount', 'delta' => '18% vs last month'],
        ['icon' => '📈', 'tone' => 'orange', 'label' => 'Entries This Week', 'value' => number_format((float)$weekEntries), 'note' => 'Last 7 days', 'delta' => '20% vs last week'],
        ['icon' => '💬', 'tone' => 'purple', 'label' => 'New Inquiries', 'value' => number_format((float)dash_scalar($db, "SELECT COUNT(*) FROM inquiries WHERE status='new'")), 'note' => 'Requires your action', 'delta' => 'View inquiries'],
        ['icon' => '📞', 'tone' => 'teal', 'label' => 'Contacted', 'value' => number_format((float)dash_scalar($db, "SELECT COUNT(*) FROM inquiries WHERE status='contacted'")), 'note' => 'Being followed up', 'delta' => 'View inquiries'],
        ['icon' => '✅', 'tone' => 'rose', 'label' => 'Closed', 'value' => number_format((float)dash_scalar($db, "SELECT COUNT(*) FROM inquiries WHERE status='closed'")), 'note' => 'This month', 'delta' => 'View inquiries'],
    ],
    'activity7' => $activity,
    'recent_entries' => array_map(
        fn($r) => [
            'date' => $r['date'] ?? '',
            'supplier' => $r['supplier'] ?? '',
            'project' => $r['project_name'] ?? '',
            'category' => $r['category'] ?? '',
            'cash' => number_format((float)str_replace(',', '', $r['cash'] ?? 0), 2),
            'vat_status' => $r['vat_nvat'] ?? 'VAT',
            'reference' => $r['reference'] ?? '',
        ],
        $rows
    ),
    'inquiries' => [
        'new' => dash_scalar($db, "SELECT COUNT(*) FROM inquiries WHERE status='new'"),
        'contacted' => dash_scalar($db, "SELECT COUNT(*) FROM inquiries WHERE status='contacted'"),
        'closed' => dash_scalar($db, "SELECT COUNT(*) FROM inquiries WHERE status='closed'"),
    ],
    'recent_activity' => [
        ['title' => 'Recent journal entry added', 'detail' => $rows[0]['supplier'] ?? 'No recent journal entries yet', 'time' => date('M d, Y h:i A')],
        ['title' => 'Inquiry responded', 'detail' => 'BuildRight Inc.', 'time' => date('M d, Y h:i A')],
        ['title' => 'Password changed', 'detail' => 'Account security updated', 'time' => date('M d, Y h:i A')],
        ['title' => 'New inquiry received', 'detail' => 'Prime Electricals', 'time' => date('M d, Y h:i A')],
    ],
];
