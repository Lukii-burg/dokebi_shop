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

$name    = trim($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$phone   = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');

if ($name === '' || $email === '') {
    json_response(['ok' => false, 'error' => 'Name and email are required.'], 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['ok' => false, 'error' => 'Invalid email address.'], 422);
}

try {
    $stmt = $conn->prepare("SELECT id, profile_image FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u) {
        json_response(['ok' => false, 'error' => 'User not found'], 404);
    }

    $profileImg = $u['profile_image'] ?: 'default_user.png';

    if (!empty($_FILES['profile_image']['name'])) {
        $file = $_FILES['profile_image'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            json_response(['ok' => false, 'error' => 'Image upload failed.'], 422);
        }
        if ($file['size'] > 2 * 1024 * 1024) {
            json_response(['ok' => false, 'error' => 'Max image size is 2MB.'], 422);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

        if (!isset($allowed[$mime])) {
            json_response(['ok' => false, 'error' => 'Only JPG, PNG, GIF, WEBP allowed.'], 422);
        }

        $uploadDir = __DIR__ . '/../../uploads/users/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $newName = 'user_' . $userId . '_' . time() . '.' . $allowed[$mime];
        $target  = $uploadDir . $newName;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            json_response(['ok' => false, 'error' => 'Could not save image.'], 500);
        }

        $profileImg = $newName;
    }

    $upd = $conn->prepare("
        UPDATE users
        SET name = :name, email = :email, phone = :phone, address = :address, profile_image = :img
        WHERE id = :id
    ");
    $upd->execute([
        ':name' => $name,
        ':email' => $email,
        ':phone' => $phone,
        ':address' => $address,
        ':img' => $profileImg,
        ':id' => $userId
    ]);

    $_SESSION['user_name']  = $name;
    $_SESSION['user_image'] = $profileImg;

    json_response([
        'ok' => true,
        'message' => 'Profile updated successfully.',
        'user' => [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'profile_image' => $profileImg,
            'profile_image_url' => '../uploads/users/' . $profileImg
        ]
    ]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Server error: ' . $e->getMessage()], 500);
}
