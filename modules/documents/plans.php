<?php
require_once __DIR__ . '/../../config.php';
redirect_if_not_logged_in();
header('Location: ' . mb_base_url('workspace.php#plans'));
exit;
