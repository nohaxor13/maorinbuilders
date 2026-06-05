<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';
require __DIR__ . '/../helpers.php';

header('Content-Type: application/json; charset=utf-8');

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
     status VARCHAR(32) NOT NULL DEFAULT 'new',
     project_id VARCHAR(64) NULL
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$project_type = trim((string)($_POST['project_type'] ?? ''));
$finish_level = trim((string)($_POST['finish_level'] ?? 'Standard'));
$area_sqm = (float)($_POST['area_sqm'] ?? 0);
$floors = trim((string)($_POST['floors'] ?? '1'));
$location = trim((string)($_POST['location'] ?? ''));
$name = trim((string)($_POST['name'] ?? ''));
$contact = trim((string)($_POST['contact'] ?? ''));
$notes = trim((string)($_POST['notes'] ?? ''));

if ($project_type === '' || $name === '' || $contact === '' || $area_sqm <= 0) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'error'=>'Missing required fields.']);
  exit;
}

// Split contact into phone/email if possible (simple heuristic)
$phone = null; $email = null;
if (strpos($contact, '@') !== false) $email = $contact;
else $phone = $contact;

// Base costs per sqm (PHP). Adjust anytime.
$base = [
  'Residential Construction' => 28000,
  'Commercial Buildings' => 32000,
  'Renovation / Remodeling' => 18000,
  'Design & Build' => 34000,
];

$multFinish = [
  'Basic' => 0.85,
  'Standard' => 1.00,
  'Premium' => 1.25,
];

$k = $base[$project_type] ?? 28000;
$m = $multFinish[$finish_level] ?? 1.0;

// Floors modifier (small complexity bump)
$floorMult = 1.0;
if ($floors === '2') $floorMult = 1.07;
if ($floors === '3') $floorMult = 1.12;

$cost = $k * $m * $floorMult * $area_sqm;

// Range +-10%
$min = $cost * 0.90;
$max = $cost * 1.10;

// Timeline rough estimate (weeks) by type + area
$weeksBase = [
  'Residential Construction' => 16,
  'Commercial Buildings' => 20,
  'Renovation / Remodeling' => 8,
  'Design & Build' => 22,
];
$w = ($weeksBase[$project_type] ?? 16) + (int)round($area_sqm / 40);
if ($finish_level === 'Premium') $w += 2;
if ($floors === '2') $w += 2;
if ($floors === '3') $w += 4;

$timeline = $w . "–" . ($w+4) . " weeks";

// Included per type
$included = [
  'Residential Construction' => [
    'Site mobilization & layout',
    'Structural works (foundation, columns, beams, slab)',
    'Masonry, plastering & waterproofing (as applicable)',
    'Basic electrical & plumbing rough-ins',
    'Finishing (per selected level)',
    'Punchlist & turnover'
  ],
  'Commercial Buildings' => [
    'Project planning & scheduling',
    'Structural works & site safety controls',
    'MEP coordination (electrical/plumbing/fire as applicable)',
    'Finishing (per selected level)',
    'Testing, punchlist & turnover'
  ],
  'Renovation / Remodeling' => [
    'Demolition / removal (as needed)',
    'Structural reinforcement (as needed)',
    'MEP adjustments',
    'Finishing (per selected level)',
    'Cleanup & turnover'
  ],
  'Design & Build' => [
    'Concept + schematic planning',
    'Detailed design coordination',
    'Permitting assistance (as applicable)',
    'Construction execution',
    'Punchlist & turnover'
  ],
];

$ref = 'MB-' . date('Ymd') . '-' . substr(bin2hex(random_bytes(4)), 0, 8);

$messageLines = [];
$messageLines[] = "Estimator Reference: {$ref}";
$messageLines[] = "Finish: {$finish_level}";
$messageLines[] = "Area: {$area_sqm} sqm";
$messageLines[] = "Floors: {$floors}";
if ($notes !== '') $messageLines[] = "Notes: {$notes}";
$message = implode("\n", $messageLines);

$budget = "₱" . number_format($min, 0) . " – ₱" . number_format($max, 0);

$st = $pdo->prepare("INSERT INTO website_inquiries (name, phone, email, project_type, location, budget, message, status) VALUES (?,?,?,?,?,?,?,'new')");
$st->execute([$name, $phone, $email, $project_type, $location, $budget, $message]);

echo json_encode([
  'ok'=>true,
  'ref'=>$ref,
  'min'=>$min,
  'max'=>$max,
  'timeline'=>$timeline,
  'included'=>$included[$project_type] ?? $included['Residential Construction'],
]);
