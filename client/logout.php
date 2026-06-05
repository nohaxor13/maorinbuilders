<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
require __DIR__ . '/../helpers.php';
unset($_SESSION['client_id']);
header('Location: login.php');
exit;
