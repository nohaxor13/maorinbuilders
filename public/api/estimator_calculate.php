<?php
declare(strict_types=1);

require __DIR__ . '/../../config.php';
require __DIR__ . '/../../helpers.php';
require __DIR__ . '/../../includes/estimator_system.php';

require_feature($pdo, 'public_site');
mb_estimator_bootstrap($pdo);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Invalid request method.');
    }
    $raw = file_get_contents('php://input') ?: '';
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        throw new RuntimeException('Invalid estimator payload.');
    }
    $result = mb_estimator_calculate($pdo, $payload);
    mb_estimator_json(['ok' => true, 'result' => $result]);
} catch (Throwable $e) {
    mb_estimator_json(['ok' => false, 'message' => $e->getMessage()], 422);
}
