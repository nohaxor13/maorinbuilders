<?php

$host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';

$isLocalhost = in_array($host, ['localhost', '127.0.0.1', '::1'], true)
    || str_contains($host, '.local');

if ($isLocalhost) {
    // Local XAMPP database
    $dsn = "mysql:host=localhost;dbname=maorin_builders;charset=utf8mb4";
    $user = "marcatech";
    $pass = "marcatech";
} else {
    // Hostinger production database
    $dsn = "mysql:host=localhost;dbname=u934498006_maorinbuilders;charset=utf8mb4";
    $user = "u934498006_maorinbuilders";
    $pass = "Maorinbuilders23";
}

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/helpers.php';

ensure_site_settings_table($pdo);

if (!function_exists('maintenance_mode_request_is_exempt')) {
    function maintenance_mode_request_is_exempt(): bool {
        $path = str_replace('\\', '/', parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $target = $path !== '' ? $path : $script;

        $exempt = [
            '/config.php',
            '/helpers.php',
            '/maintenance.php',
            '/login.php',
            '/logout.php',
            '/admin.php',
            '/client/login.php',
            '/client/logout.php',
        ];

        foreach ($exempt as $item) {
            if ($target === $item || str_ends_with($target, $item)) {
                return true;
            }
        }

        return false;
    }
}

if (maintenance_mode_is_enabled($pdo) && !maintenance_mode_request_is_exempt()) {
    $isAdmin = function_exists('current_user_is_admin') && !empty($_SESSION['user_id']) && current_user_is_admin($pdo);
    if (!$isAdmin) {
        $message = maintenance_mode_message($pdo);
        $retryAfter = (int)site_setting_get($pdo, 'maintenance_retry_after', '3600');
        http_response_code(503);
        header('Retry-After: ' . max(60, $retryAfter));
        $title = 'Maintenance Mode';
        include __DIR__ . '/maintenance.php';
        exit;
    }
}
?>
