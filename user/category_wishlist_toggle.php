<?php
require_once __DIR__ . '/../db/functions.php';
require_login();

$userId   = current_user_id();
$catId    = (int)($_POST['category_id'] ?? 0);
$action   = $_POST['action'] ?? 'add';
$redirect = $_POST['redirect'] ?? '';
$wantsJson = isset($_POST['ajax']) || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
$catName = '';
$msg = '';

if ($redirect === '') {
    $redirect = $_SERVER['HTTP_REFERER'] ?? '../main/shop.php';
}

// Ensure table exists (idempotent).
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS category_wishlists (
            user_id INT NOT NULL,
            category_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(user_id, category_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
        )
    ");
} catch (Exception $e) {
    // continue even if table creation fails
}

if ($catId > 0) {
    // Fetch category name for clearer messaging.
    try {
        $c = $conn->prepare("SELECT category_name FROM categories WHERE id = :id LIMIT 1");
        $c->execute([':id' => $catId]);
        $catName = (string)$c->fetchColumn();
    } catch (Exception $e) {
        $catName = '';
    }

    if ($action === 'remove') {
        $stmt = $conn->prepare('DELETE FROM category_wishlists WHERE user_id = :uid AND category_id = :cid');
        $stmt->execute([':uid' => $userId, ':cid' => $catId]);
        $msg = $catName ? "Removed {$catName} from favorites." : 'Removed from favorites.';
        if (!$wantsJson) $_SESSION['flash'] = $msg;
    } else {
        $stmt = $conn->prepare('INSERT IGNORE INTO category_wishlists (user_id, category_id) VALUES (:uid, :cid)');
        $stmt->execute([':uid' => $userId, ':cid' => $catId]);
        $msg = $catName ? "Saved {$catName} to favorites." : 'Saved to favorites.';
        if (!$wantsJson) $_SESSION['flash'] = $msg;
    }
}

if ($wantsJson) {
    json_response([
        'status' => 'ok',
        'action' => $action,
        'category_id' => $catId,
        'message' => $msg ?? '',
    ]);
} else {
    if (!preg_match('#^https?://#', $redirect) && strpos($redirect, '/') !== 0 && strpos($redirect, '../') !== 0) {
        $redirect = '../main/' . ltrim($redirect, '/');
    }
    header('Location: ' . $redirect);
    exit;
}
