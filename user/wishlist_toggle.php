<?php
require_once __DIR__ . '/../db/functions.php';
require_login();

$userId    = current_user_id();
$productId = (int)($_POST['product_id'] ?? 0);
$action    = $_POST['action'] ?? 'add';
$redirect  = $_POST['redirect'] ?? '';
$wantsJson = isset($_POST['ajax']) || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

if ($redirect === '') {
    $redirect = $_SERVER['HTTP_REFERER'] ?? '../main/shop.php';
}

if ($productId > 0) {
    if ($action === 'remove') {
        $stmt = $conn->prepare('DELETE FROM wishlists WHERE user_id = :uid AND product_id = :pid');
        $stmt->execute([':uid' => $userId, ':pid' => $productId]);
        if (!$wantsJson) $_SESSION['flash'] = 'Removed from wishlist.';
    } else {
        $stmt = $conn->prepare('INSERT IGNORE INTO wishlists (user_id, product_id) VALUES (:uid, :pid)');
        $stmt->execute([':uid' => $userId, ':pid' => $productId]);
        if (!$wantsJson) $_SESSION['flash'] = 'Saved to wishlist.';
    }
}

if ($wantsJson) {
    json_response([
        'status' => 'ok',
        'action' => $action,
        'product_id' => $productId,
    ]);
} else {
    // Normalize redirect for relative paths
    if (!preg_match('#^https?://#', $redirect) && strpos($redirect, '/') !== 0 && strpos($redirect, '../') !== 0) {
        // Default to main directory when a bare filename is provided
        $redirect = '../main/' . ltrim($redirect, '/');
    }
    header('Location: ' . $redirect);
    exit;
}
