<?php
// api/chat-save.php — saves chat messages to the database
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']); exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$role    = $input['role']    ?? '';
$message = trim($input['message'] ?? '');

if (!in_array($role, ['user','assistant']) || !$message) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']); exit;
}

$db     = getDB();
$userId = currentUserId();

// Keep last 200 messages per user (prune old ones)
$stmt = $db->prepare("SELECT COUNT(*) FROM chat_history WHERE user_id=?");
$stmt->execute([$userId]);
$count = (int)$stmt->fetchColumn();

if ($count >= 200) {
    $db->prepare("
        DELETE FROM chat_history WHERE user_id=?
        ORDER BY created_at ASC
        LIMIT ?
    ")->execute([$userId, $count - 190]);
}

$db->prepare("INSERT INTO chat_history (user_id, role, message) VALUES (?,?,?)")
   ->execute([$userId, $role, $message]);

echo json_encode(['success' => true]);