<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../helpers.php';
redirect_if_not_logged_in();
mb_require_any_permission($pdo, ['view_estimates','manage_estimates']);
header('Location: ../../workspace.php#estimates');
exit;
