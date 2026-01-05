<?php
session_start();
require_once "../db/connection.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$reviewId = (int)($_POST['review_id'] ?? 0);
$status   = $_POST['status'] ?? '';
$redirect = $_POST['redirect'] ?? 'reviews_manage.php';

$allowed = ['pending','approved','rejected'];
if ($reviewId > 0 && in_array($status, $allowed, true)) {
    $stmt = $conn->prepare("UPDATE reviews SET status = :status WHERE id = :id");
    $stmt->execute([':status' => $status, ':id' => $reviewId]);
    $_SESSION['flash'] = 'Review updated.';
} else {
    $_SESSION['flash'] = 'Invalid review update.';
}

if (!preg_match('#^https?://#i', $redirect) && strpos($redirect, '/') !== 0) {
    $redirect = 'reviews_manage.php';
}

header("Location: $redirect");
exit;
