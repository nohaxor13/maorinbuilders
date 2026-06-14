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
    csrf_verify();
    $settings = mb_estimator_settings($pdo);
    $payload = json_decode((string)($_POST['payload_json'] ?? ''), true);
    if (!is_array($payload)) {
        throw new RuntimeException('Missing estimator payload.');
    }
    $result = mb_estimator_calculate($pdo, $payload);
    $drawing = [];
    if (!empty($payload['drawing']) && is_array($payload['drawing'])) {
        $drawing = mb_estimator_validate_drawing((array)$payload['drawing'], $settings);
    }
    $upload = null;
    if (!empty($settings['enable_file_upload']) && !empty($_FILES['attachment'])) {
        $upload = mb_estimator_save_upload($_FILES['attachment'], $settings);
    }
    $leadId = mb_estimator_save_submission($pdo, $payload, $result, $drawing, $upload);
    mb_estimator_json([
        'ok' => true,
        'lead_id' => $leadId,
        'message' => 'Your preliminary estimate has been submitted to Maorin Builders.',
        'result' => $result,
    ]);
} catch (Throwable $e) {
    mb_estimator_json(['ok' => false, 'message' => $e->getMessage()], 422);
}
