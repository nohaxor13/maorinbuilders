<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
require __DIR__ . '/../helpers.php';
ensure_client_portal_tables($pdo);
require_feature($pdo, 'client_portal');
redirect_client_if_not_logged_in();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); die('Missing id'); }

$st = $pdo->prepare("SELECT project_id, stored_name, display_name, mime, size_bytes FROM website_project_files WHERE id=? LIMIT 1");
$st->execute([$id]);
$f = $st->fetch(PDO::FETCH_ASSOC);
if (!$f) { http_response_code(404); die('File not found'); }

$project_id = (string)$f['project_id'];
$client_id = (int)$_SESSION['client_id'];
if (!client_can_access_project($pdo, $client_id, $project_id)) {
  http_response_code(403); die('Forbidden');
}

$path = __DIR__ . '/../storage/uploads/project_files/' . basename((string)$f['stored_name']);
if (!is_file($path)) { http_response_code(404); die('Missing file on server'); }

$mime = $f['mime'] ?: 'application/octet-stream';
$disp = $f['display_name'] ?: 'download';
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($path));
header('Content-Disposition: attachment; filename="' . str_replace('"','', $disp) . '"');
readfile($path);
exit;
