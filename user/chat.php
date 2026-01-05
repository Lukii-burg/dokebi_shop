<?php
require_once __DIR__ . '/../db/functions.php';
require_login();

$userId = current_user_id();
$sessionId = session_id();
$error = '';

// Fetch last messages
function fetch_chat($conn, $userId, $sessionId) {
    $stmt = $conn->prepare("
        SELECT role, message, created_at
        FROM ai_chat_logs
        WHERE (user_id = :uid OR session_id = :sid)
        ORDER BY id DESC
        LIMIT 30
    ");
    $stmt->execute([':uid'=>$userId, ':sid'=>$sessionId]);
    $rows = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    return $rows;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Clear chat history
    if (isset($_POST['clear_chat'])) {
        $del = $conn->prepare("DELETE FROM ai_chat_logs WHERE user_id = :uid OR session_id = :sid");
        $del->execute([':uid'=>$userId, ':sid'=>$sessionId]);
        if (isset($_POST['ajax'])) {
            json_response(['ok'=>true,'messages'=>[]]);
        }
        header('Location: chat.php');
        exit;
    }

    $msg = trim($_POST['message'] ?? '');
    if ($msg === '') {
        $error = 'Please enter a message.';
    } else {
        $ins = $conn->prepare("INSERT INTO ai_chat_logs (user_id, session_id, role, message) VALUES (:uid, :sid, 'user', :msg)");
        $ins->execute([':uid'=>$userId, ':sid'=>$sessionId, ':msg'=>$msg]);

        // Rule-based/FAQ reply (no external API)
        $reply = faq_reply($msg);

        $ins2 = $conn->prepare("INSERT INTO ai_chat_logs (user_id, session_id, role, message) VALUES (:uid, :sid, 'assistant', :msg)");
        $ins2->execute([':uid'=>$userId, ':sid'=>$sessionId, ':msg'=>$reply]);

        if (isset($_POST['ajax'])) {
            $messages = fetch_chat($conn, $userId, $sessionId);
            // add formatted timestamp for frontend convenience
            $messages = array_map(function($m) {
                $m['created_at_human'] = date('M d, H:i', strtotime($m['created_at']));
                return $m;
            }, $messages);
            json_response(['ok'=>true,'messages'=>$messages]);
        }

        header('Location: chat.php');
        exit;
    }
}

$messages = fetch_chat($conn, $userId, $sessionId);

// Simple rule-based FAQ reply (no external API call).
function faq_reply($prompt) {
    $text = strtolower($prompt);
    $rules = [
        // Orders / status
        ['keywords'=>['order','status','track','tracking','code'], 'reply'=>"I can help with orders. Please share your order code. You can also see updates in Account > Orders."],
        // Payments / methods
        ['keywords'=>['payment','pay','kpay','kbz','wave','wavepay','aya','visa','mastercard','mpu','proof','transaction','ref'], 'reply'=>"Payment help: tell me the method (KBZPay/Wave/Aya/Visa/MPU) and share your order code + payment reference if you have it."],
        // Refunds / cancel
        ['keywords'=>['refund','cancel'], 'reply'=>"Refunds/cancellations: share your order code and payment method. We'll review and update you quickly."],
        // Login / reset
        ['keywords'=>['login','password','reset','otp'], 'reply'=>"For password reset, use Recover on the sign-in page. If that fails, tell me your account email and I'll flag it for support."],
        // Delivery
        ['keywords'=>['delivery','how long','delay','delayed','processing'], 'reply'=>"Most digital items are delivered quickly after payment confirmation. If it's delayed, send your order code and we'll check."],
        // Redeem / invalid
        ['keywords'=>['redeem','invalid code','resend'], 'reply'=>"If a code is invalid or missing, share your order code and we’ll resend or help redeem it."],
        // Promo / price
        ['keywords'=>['promo','discount','cheap','best price','sale','coupon'], 'reply'=>"We run promos often. Tell me the product and payment method; I'll check current offers."],
        // Products (gaming/gift cards/premium)
        ['keywords'=>['mlbb','mobile legends','pubg','uc','free fire','genshin','valorant','robux','steam','psn','xbox','nintendo','razer','google play','itunes','gift card','netflix','spotify','disney','chatgpt'], 'reply'=>"Yes, we support those products. Tell me which item you want and the payment method, and I’ll guide you."],
        // Support generic
        ['keywords'=>['support','agent','help','contact','chat'], 'reply'=>"I'm here to help. Share your order code or describe the issue, and I'll route it."],
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Live Chat - Dokebi</title>
  <link rel="stylesheet" href="../maincss/style.css">
  <link rel="stylesheet" href="../maincss/dark-mode.css">
  <style>
    .chat-box {background:var(--panel); padding:16px; border-radius:12px; max-height:420px; overflow-y:auto; display:flex; flex-direction:column; gap:10px;}
    .chat-msg {padding:10px 12px; border-radius:10px; max-width:80%; background:var(--panel-2); color:var(--text);}
    .chat-user {align-self:flex-end; background:var(--accent-purple); color:white;}
    .chat-bot {align-self:flex-start; background:var(--panel-2); color:var(--text);}
  </style>
</head>
<body>
  <header class="site-header">
    <div class="container header-inner">
      <a class="back-icon" href="account.php" aria-label="Account">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </a>
      <a class="logo" href="../main/index.php"><img src="../logo/original.png" alt="Dokebi Family" class="logo-img"><span class="sitename">Dokebi Family</span></a>
      <div class="header-left-links">
        <a href="../main/index.php">Home</a>
        <a href="../main/shop.php">Shop</a>
        <div class="theme-toggle">
          <span>Dark / Light</span>
          <label class="toggle-switch">
            <input type="checkbox" id="themeToggle" aria-label="Toggle dark mode">
            <span class="toggle-slider"></span>
          </label>
        </div>
      </div>
    </div>
  </header>
  <main>
    <section class="container">
      <div class="form">
        <h2>Live Chat / Bot</h2>
        <p class="muted">We’ll auto-reply with quick guidance and a human can follow up.</p>
        <?php if($error): ?><div class="notice error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <div id="chatBox" class="chat-box">
          <?php if (!$messages): ?>
            <div class="muted">No messages yet. Say hello!</div>
          <?php else: ?>
            <?php foreach ($messages as $m): ?>
              <div class="chat-msg <?php echo $m['role']==='user'?'chat-user':'chat-bot'; ?>">
                <div class="muted tiny"><?php echo htmlspecialchars(date('M d, H:i', strtotime($m['created_at']))); ?></div>
                <div><?php echo nl2br(htmlspecialchars($m['message'])); ?></div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <form id="chatForm" method="post" style="margin-top:12px; display:flex; gap:10px; flex-wrap:wrap;">
          <input type="hidden" name="ajax" value="1">
          <input id="chatInput" type="text" name="message" placeholder="Type your message..." style="flex:1; min-width:220px;" required>
          <button class="btn primary" type="submit">Send</button>
          <button class="btn" type="button" id="clearChatBtn">Clear Chat</button>
        </form>
      </div>
    </section>
  </main>
  <script src="../js/theme-toggle.js"></script>
  <script>
    (function(){
      const form = document.getElementById('chatForm');
      const input = document.getElementById('chatInput');
      const box = document.getElementById('chatBox');
      const clearBtn = document.getElementById('clearChatBtn');

      function renderMessages(list){
        box.innerHTML = '';
        if (!list || list.length===0){
          box.innerHTML = '<div class="muted">No messages yet. Say hello!</div>';
          return;
        }
        list.forEach(m=>{
          const wrap = document.createElement('div');
          wrap.className = 'chat-msg ' + (m.role==='user'?'chat-user':'chat-bot');
          const ts = document.createElement('div');
          ts.className = 'muted tiny';
          ts.textContent = m.created_at_human || m.created_at || '';
          const body = document.createElement('div');
          body.innerHTML = (m.message || '').replace(/\\n/g,'<br>');
          wrap.appendChild(ts);
          wrap.appendChild(body);
          box.appendChild(wrap);
        });
        box.scrollTop = box.scrollHeight;
      }

      if (box) { box.scrollTop = box.scrollHeight; }

      form.addEventListener('submit', async (e)=>{
        e.preventDefault();
        const msg = input.value.trim();
        if (!msg) return;
        const fd = new FormData(form);
        try{
          const res = await fetch('chat.php', {method:'POST', body:fd});
          const data = await res.json();
          if (data && data.ok){
            renderMessages(data.messages);
            input.value = '';
            input.focus();
          }
        }catch(err){
          form.submit(); // fallback full reload
        }
      });

      clearBtn.addEventListener('click', async ()=>{
        const fd = new FormData();
        fd.append('ajax','1');
        fd.append('clear_chat','1');
        try{
          const res = await fetch('chat.php', {method:'POST', body:fd});
          const data = await res.json();
          if (data && data.ok){
            renderMessages([]);
          }
        }catch(err){
          window.location.reload();
        }
      });
    })();
  </script>
  <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
