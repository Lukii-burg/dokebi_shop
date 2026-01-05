<?php
// inc/functions.php - shared helpers + DB bootstrap
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/connection.php';

// Expose a PDO instance as $pdo for legacy code that referenced it.
$pdo = $conn;

function db(): PDO {
    // Convenience accessor to the shared PDO connection.
    global $conn;
    return $conn;
}

// Derive the web-visible base path (works whether the project is in the web root or a subfolder).
if (!defined('APP_BASE_PATH')) {
    $docRoot     = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $projectRoot = str_replace('\\', '/', dirname(__DIR__));
    $base        = '';
    if ($docRoot !== '' && strpos($projectRoot, $docRoot) === 0) {
        $base = substr($projectRoot, strlen($docRoot));
    }
    $base        = $base === '' ? '' : '/' . ltrim($base, '/');
    define('APP_BASE_PATH', $base);
}

function url_for(string $path = ''): string {
    $prefix = APP_BASE_PATH;
    $prefix = $prefix === '/' ? '' : $prefix; // normalize root installs
    return ($prefix === '' ? '' : rtrim($prefix, '/')) . '/' . ltrim($path, '/');
}

// Role constants
define('ROLE_USER', 'user');
define('ROLE_ADMIN', 'admin');

function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: ' . url_for('auth/login.php'));
        exit;
    }
}

function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function current_user_role() {
    return $_SESSION['user_role'] ?? null;
}

function is_admin(): bool {
    return current_user_role() === ROLE_ADMIN;
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        $_SESSION['flash'] = 'Admin access required.';
        header('Location: ' . url_for('auth/login.php'));
        exit;
    }
}

function get_user_role($user_id) {
    global $pdo;
    $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

function set_user_session($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = htmlspecialchars($user['name'] ?? ($user['username'] ?? ''), ENT_QUOTES, 'UTF-8');
    $_SESSION['user_role'] = $user['role'] ?? ROLE_USER;
    $_SESSION['user_email'] = $user['email'] ?? '';
    // Legacy keys used by admin views
    $_SESSION['role'] = $_SESSION['user_role'];
    $_SESSION['username'] = $user['username'] ?? ($_SESSION['user_name'] ?? '');
    $_SESSION['user_image'] = $user['profile_image'] ?? 'default_user.png';
}

function current_user(): ?array {
    $id = current_user_id();
    if (!$id) {
        return null;
    }
    try {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Exception $e) {
        return null;
    }
}

function password_is_hashed(string $value): bool {
    $info = password_get_info($value);
    return ($info['algo'] ?? 0) !== 0;
}

function password_matches(string $input, string $stored): bool {
    if ($stored === '') return false;
    if (password_is_hashed($stored)) {
        return password_verify($input, $stored);
    }
    return hash_equals($stored, $input);
}

function hash_password_value(string $plain): string {
    return password_hash($plain, PASSWORD_DEFAULT);
}

function rehash_password_if_needed(PDO $conn, int $userId, string $inputPassword, string $stored): void {
    if (!password_is_hashed($stored) || password_needs_rehash($stored, PASSWORD_DEFAULT)) {
        try {
            $stmt = $conn->prepare("UPDATE users SET password = :pwd WHERE id = :id");
            $stmt->execute([':pwd' => hash_password_value($inputPassword), ':id' => $userId]);
        } catch (Exception $e) {
            // Do not block login/profile flows if rehash fails.
        }
    }
}

// Generate a random 6-digit OTP
function generate_otp($length = 6) {
    return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

// Store OTP and expiration for a user by email
function store_user_otp($email, $otp, $expires_in_minutes = 10) {
    global $pdo;
    $expires = date('Y-m-d H:i:s', time() + $expires_in_minutes * 60);
    $stmt = $pdo->prepare('UPDATE users SET otp_code = ?, otp_expires = ? WHERE email = ?');
    return $stmt->execute([$otp, $expires, $email]);
}

function welcome_promo_defaults(): array {
    return ['code' => 'WELCOME50', 'discount' => 50];
}

function ensure_welcome_promo_table(PDO $conn): bool {
    static $checked = false;
    static $ok = false;
    if ($checked) {
        return $ok;
    }
    $checked = true;
    try {
        $conn->exec("
            CREATE TABLE IF NOT EXISTS user_promotions (
                user_id INT(10) UNSIGNED NOT NULL PRIMARY KEY,
                promo_code VARCHAR(50) NOT NULL,
                discount_percent TINYINT NOT NULL DEFAULT 50,
                redeemed_at DATETIME DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        $ok = true;
    } catch (Exception $e) {
        $ok = false;
    }
    return $ok;
}

function get_welcome_promo(PDO $conn, int $userId): array {
    $defaults = welcome_promo_defaults();
    $fallbackUsed = !empty($_SESSION['welcome_promo_redeemed']);

    if (!ensure_welcome_promo_table($conn)) {
        return ['code' => $defaults['code'], 'discount' => $defaults['discount'], 'used' => $fallbackUsed, 'source' => 'session'];
    }

    try {
        $stmt = $conn->prepare("SELECT promo_code, discount_percent, redeemed_at FROM user_promotions WHERE user_id = :uid LIMIT 1");
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $ins = $conn->prepare("INSERT IGNORE INTO user_promotions (user_id, promo_code, discount_percent) VALUES (:uid, :code, :disc)");
            $ins->execute([':uid' => $userId, ':code' => $defaults['code'], ':disc' => $defaults['discount']]);
            $row = ['promo_code' => $defaults['code'], 'discount_percent' => $defaults['discount'], 'redeemed_at' => null];
        }

        return [
            'code'     => $row['promo_code'] ?: $defaults['code'],
            'discount' => (int)($row['discount_percent'] ?: $defaults['discount']),
            'used'     => !empty($row['redeemed_at']),
            'source'   => 'db'
        ];
    } catch (Exception $e) {
        return ['code' => $defaults['code'], 'discount' => $defaults['discount'], 'used' => $fallbackUsed, 'source' => 'session'];
    }
}

function mark_welcome_promo_used(PDO $conn, int $userId): void {
    $_SESSION['welcome_promo_redeemed'] = true;
    if (!ensure_welcome_promo_table($conn)) {
        return;
    }
    try {
        $stmt = $conn->prepare("UPDATE user_promotions SET redeemed_at = NOW() WHERE user_id = :uid");
        $stmt->execute([':uid' => $userId]);
    } catch (Exception $e) {
        // swallow errors so checkout flow is not blocked
    }
}

/**
 * Track recent viewed category slugs in session (max 8).
 */
function add_recent_view(string $slug): void {
    if ($slug === '') return;
    $arr = $_SESSION['recent_views'] ?? [];
    $arr = array_values(array_filter($arr, fn($s) => $s !== $slug));
    array_unshift($arr, $slug);
    $arr = array_slice($arr, 0, 8);
    $_SESSION['recent_views'] = $arr;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_validate(?string $token): bool {
    return isset($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function ensure_ai_chat_table(PDO $conn): void {
    static $done = false;
    if ($done) {
        return;
    }
    $conn->exec("
        CREATE TABLE IF NOT EXISTS ai_chat_logs (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_id VARCHAR(191) NOT NULL,
            role ENUM('user','assistant') NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id_id (user_id, id),
            INDEX idx_session_id_id (session_id, id)
        )
    ");
    $done = true;
}
