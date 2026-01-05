<?php
require_once __DIR__ . '/../db/functions.php';
require_login();

$userId    = current_user_id();
$productId = (int)($_POST['product_id'] ?? 0);
$rating    = (int)($_POST['rating'] ?? 0);
$comment   = trim($_POST['comment'] ?? '');
$redirect  = $_POST['redirect'] ?? '';

if ($redirect === '') {
    $redirect = url_for('main/shop.php');
} elseif (!preg_match('#^https?://#i', $redirect) && strpos($redirect, '/') !== 0 && strpos($redirect, '../') !== 0) {
    $redirect = url_for($redirect);
}

if ($productId <= 0 || $rating < 1 || $rating > 5) {
    $_SESSION['flash'] = 'Invalid review data.';
    header('Location: ' . $redirect);
    exit;
}

try {
    $prod = $conn->prepare("SELECT id FROM products WHERE id = :id AND status = 'active' LIMIT 1");
    $prod->execute([':id' => $productId]);
    if (!$prod->fetchColumn()) {
        $_SESSION['flash'] = 'Product not found.';
        header('Location: ' . $redirect);
        exit;
    }

    $comment = substr($comment, 0, 500);

    $check = $conn->prepare("SELECT id FROM reviews WHERE user_id = :uid AND product_id = :pid LIMIT 1");
    $check->execute([':uid' => $userId, ':pid' => $productId]);
    $existingId = $check->fetchColumn();

    if ($existingId) {
        $up = $conn->prepare("
            UPDATE reviews
            SET rating = :rating, comment = :comment, status = 'pending'
            WHERE id = :id
        ");
        $up->execute([
            ':rating'  => $rating,
            ':comment' => $comment,
            ':id'      => $existingId
        ]);
        $_SESSION['flash'] = 'Review updated and sent to admin.';
    } else {
        $ins = $conn->prepare("
            INSERT INTO reviews (user_id, product_id, rating, comment, status, created_at)
            VALUES (:uid, :pid, :rating, :comment, 'pending', NOW())
        ");
        $ins->execute([
            ':uid'     => $userId,
            ':pid'     => $productId,
            ':rating'  => $rating,
            ':comment' => $comment
        ]);
        $_SESSION['flash'] = 'Review submitted to admin.';
    }
} catch (Exception $e) {
    $_SESSION['flash'] = 'Could not save your review right now.';
}

header('Location: ' . $redirect);
exit;
