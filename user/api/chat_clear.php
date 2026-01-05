<?php
require_once __DIR__ . '/../../db/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

if (!csrf_validate($_POST['csrf'] ?? null)) {
    json_response(['ok' => false, 'error' => 'Invalid CSRF token'], 403);
}

$conn = db();
$userId = current_user_id();
$sessionId = session_id();

try {
    ensure_ai_chat_table($conn);
    $del = $conn->prepare("DELETE FROM ai_chat_logs WHERE user_id = :uid OR session_id = :sid");
    $del->execute([':uid' => $userId, ':sid' => $sessionId]);
    json_response(['ok' => true, 'messages' => []]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Could not clear chat: ' . $e->getMessage()], 500);
}
