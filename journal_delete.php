<?php
// PATH: /maorinbuilders/journal_delete.php
declare(strict_types=1);

require "config.php";
require "helpers.php";
header('Content-Type: application/json; charset=utf-8');

try {
  redirect_if_not_logged_in();
  $uid = (int)($_SESSION['user_id'] ?? 0);
  if (!$uid) throw new Exception('Unauthorized');

  // Accept JSON body: { "id": 123 }
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  if (!is_array($data)) throw new Exception('Invalid payload');
  $id = (int)($data['id'] ?? 0);
  if ($id <= 0) throw new Exception('Invalid id');

  // Ensure the row exists and belongs to the current user
  $stmt = $pdo->prepare("SELECT id, user_id FROM purchase_entries WHERE id = :id");
  $stmt->execute([':id' => $id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) throw new Exception('Entry not found');

  if ((int)$row['user_id'] !== $uid) {
    // Extra safety: only the owner can delete
    throw new Exception('You can only delete your own entries.');
  }

  // Perform hard delete
  $del = $pdo->prepare("DELETE FROM purchase_entries WHERE id = :id AND user_id = :uid");
  $del->execute([':id' => $id, ':uid' => $uid]);

  echo json_encode(['ok' => true, 'message' => 'Deleted']);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
