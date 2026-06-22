<?php
declare(strict_types=1);

require __DIR__ . '/../../config.php';
require __DIR__ . '/../../helpers.php';
require __DIR__ . '/../../includes/estimator_system.php';

require_feature($pdo, 'public_site');
mb_estimator_bootstrap($pdo);
mb_estimator_json([
    'ok' => true,
    'csrf_token' => csrf_token(),
    'config' => mb_estimator_get_public_config($pdo),
]);
