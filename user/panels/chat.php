<?php
require_once __DIR__ . '/../../db/functions.php';
require_login();

$conn = db();
$userId = current_user_id();
$sessionId = session_id();

ensure_ai_chat_table($conn);

$stmt = $conn->prepare("
    SELECT role, message, created_at
    FROM ai_chat_logs
    WHERE user_id = ?
    ORDER BY id DESC
    LIMIT 20
");
$stmt->execute([$userId]);
$messages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
foreach ($messages as &$m) {
    $m['created_at_human'] = $m['created_at'] ? date('M d, H:i', strtotime($m['created_at'])) : '';
}
$messagesJson = htmlspecialchars(json_encode($messages, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
?>

<div class="chat-box">
  <div class="chat-header">
    <div>Live Chat Support</div>
    <div style="font-weight:500;font-size:13px;">Bot replies instantly. A human can follow up.</div>
  </div>

  <div id="accountChatBox" class="chat-body" data-messages="<?php echo $messagesJson; ?>">
    <?php if (!$messages): ?>
      <div class="muted">No messages yet. Say hello!</div>
    <?php else: ?>
      <?php foreach ($messages as $msg): ?>
        <div class="bubble <?php echo ($msg['role'] ?? '') === 'user' ? 'me' : 'support'; ?>">
          <?php echo nl2br(htmlspecialchars($msg['message'] ?? '')); ?>
          <small><?php echo htmlspecialchars($msg['created_at_human'] ?? ''); ?></small>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <form id="accountChatForm" class="chat-input" method="post">
    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
    <input type="text" id="accountChatInput" name="message" placeholder="Type your message..." required>
    <button class="btn-primary" type="submit" aria-label="Send message">Send</button>
    <button class="btn-ghost" type="button" id="accountChatClear">Clear</button>
  </form>
  <div id="accountChatError" class="notice error" style="display:none;margin:10px 14px 14px;"></div>
</div>
