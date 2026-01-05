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

$currentPassword = trim($_POST['current_password'] ?? '');
$newPassword     = trim($_POST['new_password'] ?? '');
$confirmPassword = trim($_POST['confirm_password'] ?? '');

if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
    json_response(['ok' => false, 'error' => 'All password fields are required.'], 422);
}
if ($newPassword !== $confirmPassword) {
    json_response(['ok' => false, 'error' => 'New passwords do not match.'], 422);
}
if (strlen($newPassword) < 6) {
    json_response(['ok' => false, 'error' => 'New password must be at least 6 characters.'], 422);
}

try {
    $stmt = $conn->prepare("SELECT id, password FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        json_response(['ok' => false, 'error' => 'User not found'], 404);
    }

    if (!password_matches($currentPassword, $user['password'] ?? '')) {
        json_response(['ok' => false, 'error' => 'Current password is incorrect.'], 422);
    }

    $newHash = hash_password_value($newPassword);
    $upd = $conn->prepare("UPDATE users SET password = :pwd WHERE id = :id");
    $upd->execute([':pwd' => $newHash, ':id' => $userId]);

    json_response(['ok' => true, 'message' => 'Password updated successfully.']);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Server error: ' . $e->getMessage()], 500);
}
