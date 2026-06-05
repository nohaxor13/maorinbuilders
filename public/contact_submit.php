<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';

// Basic anti-spam: require POST and a minimum time could be added later
// Base path for redirects (works on subfolder installs)
$__script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$__root = rtrim(dirname(dirname($__script)), '/');
if ($__root === '.' || $__root === '/') $__root = '';
$__contact = $__root . '/public/contact.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  header('Location: ' . $__contact);
  exit;
}

function f(string $k): string {
  return trim((string)($_POST[$k] ?? ''));
}

$name = f('name');
$phone = f('phone');
$email = f('email');
$project_type = f('project_type');
$location = f('location');
$budget = f('budget');
$message = f('message');

// Minimal validation
if ($name === '' || ($phone === '' && $email === '') || $message === '') {
  header('Location: ' . $__contact . '?err=Please+fill+in+your+name%2C+message%2C+and+either+phone+or+email.');
  exit;
}

// Create table if missing (first-run friendly)
$pdo->exec(
  "CREATE TABLE IF NOT EXISTS website_inquiries (
     id INT AUTO_INCREMENT PRIMARY KEY,
     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
     name VARCHAR(160) NOT NULL,
     phone VARCHAR(64) NULL,
     email VARCHAR(160) NULL,
     project_type VARCHAR(80) NULL,
     location VARCHAR(160) NULL,
     budget VARCHAR(80) NULL,
     message TEXT NOT NULL,
     status VARCHAR(32) NOT NULL DEFAULT 'new'
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$st = $pdo->prepare(
  "INSERT INTO website_inquiries (name,phone,email,project_type,location,budget,message)
   VALUES (?,?,?,?,?,?,?)"
);
$st->execute([$name, $phone ?: null, $email ?: null, $project_type ?: null, $location ?: null, $budget ?: null, $message]);

header('Location: ' . $__contact . '?sent=1');
exit;
