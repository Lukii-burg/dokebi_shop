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
$message = trim($_POST['message'] ?? '');

if ($message === '') {
    json_response(['ok' => false, 'error' => 'Empty message'], 422);
}
if (mb_strlen($message) > 1000) {
    json_response(['ok' => false, 'error' => 'Message too long'], 422);
}

// Simple rate limit (session-based)
$now = time();
$_SESSION['chat_rl'] = $_SESSION['chat_rl'] ?? [];
$_SESSION['chat_rl'] = array_values(array_filter($_SESSION['chat_rl'], fn($t) => ($now - $t) < 30));
if (count($_SESSION['chat_rl']) >= 8) {
    json_response(['ok' => false, 'error' => 'Too many messages. Slow down.'], 429);
}
$_SESSION['chat_rl'][] = $now;

function faq_reply(string $prompt): string {
    $text = strtolower($prompt);
    $rules = [
        ['keywords'=>['order','status','track','tracking','code'], 'reply'=>"I can help with orders. Please share your order code. You can also see updates in Account > Orders."],
        ['keywords'=>['payment','pay','kpay','kbz','wave','wavepay','aya','visa','mastercard','mpu','proof','transaction','ref'], 'reply'=>"Payment help: tell me the method (KBZPay/Wave/Aya/Visa/MPU) and share your order code + payment reference if you have it."],
        ['keywords'=>['refund','cancel'], 'reply'=>"Refunds/cancellations: share your order code and payment method. We'll review and update you quickly."],
        ['keywords'=>['login','password','reset','otp'], 'reply'=>"For password reset, use Recover on the sign-in page. If that fails, tell me your account email and I'll flag it for support."],
        ['keywords'=>['delivery','how long','delay','delayed','processing'], 'reply'=>"Most digital items are delivered quickly after payment confirmation. If it's delayed, send your order code and we'll check."],
        ['keywords'=>['redeem','invalid code','resend'], 'reply'=>"If a code is invalid or missing, share your order code and we'll resend or help redeem it."],
        ['keywords'=>['promo','discount','cheap','best price','sale','coupon'], 'reply'=>"We run promos often. Tell me the product and payment method; I'll check current offers."],
        ['keywords'=>['mlbb','mobile legends','pubg','uc','free fire','genshin','valorant','robux','steam','psn','xbox','nintendo','razer','google play','itunes','gift card','netflix','spotify','disney','chatgpt'], 'reply'=>"Yes, we support those products. Tell me which item you want and the payment method, and I'll guide you."],
        ['keywords'=>['support','agent','help','contact','chat'], 'reply'=>"I'm here to help. Share your order code or describe the issue, and I'll route it."]
    ];
    foreach ($rules as $r) {
        foreach ($r['keywords'] as $k) {
            if (strpos($text, $k) !== false) {
                return $r['reply'];
            }
        }
    }
    return "Thanks for reaching out! Share your order code (or describe the issue) and we'll help right away.";
}

try {
    ensure_ai_chat_table($conn);

    // Save user message
    $ins = $conn->prepare("INSERT INTO ai_chat_logs(user_id, session_id, role, message) VALUES(?,?, 'user', ?)");
    $ins->execute([$userId, $sessionId, $message]);

    $assistantText = faq_reply($message);

    // Save assistant message
    $ins2 = $conn->prepare("INSERT INTO ai_chat_logs(user_id, session_id, role, message) VALUES(?,?, 'assistant', ?)");
    $ins2->execute([$userId, $sessionId, $assistantText]);

    // Return last 30 messages
    $msgStmt = $conn->prepare("
        SELECT role, message, created_at
        FROM ai_chat_logs
        WHERE user_id = ?
        ORDER BY id DESC
        LIMIT 30
    ");
    $msgStmt->execute([$userId]);
    $rows = array_reverse($msgStmt->fetchAll(PDO::FETCH_ASSOC));
    foreach ($rows as &$r) {
        $r['created_at_human'] = $r['created_at'] ? date('M d, H:i', strtotime($r['created_at'])) : '';
    }

    json_response(['ok' => true, 'messages' => $rows]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Server error: ' . $e->getMessage()], 500);
}
