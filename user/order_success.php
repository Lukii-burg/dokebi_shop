<?php
require_once __DIR__ . '/../db/functions.php';
require_login();

if (!function_exists('column_exists')) {
    function column_exists(PDO $conn, string $table, string $column): bool {
        try {
            $stmt = $conn->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c LIMIT 1");
            $stmt->execute([':t' => $table, ':c' => $column]);
            return (bool) $stmt->fetchColumn();
        } catch (Exception $e) {
            return false;
        }
    }
}

function format_money(float $amount): string {
    return number_format($amount, 2) . ' MMK';
}

$userId       = current_user_id();
$flashMessage = $_SESSION['flash'] ?? '';
if ($flashMessage !== '') {
    unset($_SESSION['flash']);
}
$showSuccessModal = $flashMessage !== '';

$orderId        = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$orderCodeParam = trim($_GET['code'] ?? '');
$returnParam    = $_GET['return'] ?? ($_SESSION['latest_order_return'] ?? '../main/shop.php');

if ($orderId <= 0 && $orderCodeParam === '' && !empty($_SESSION['latest_order_id'])) {
    $orderId = (int) $_SESSION['latest_order_id'];
}
if ($orderCodeParam === '' && !empty($_SESSION['latest_order_code'])) {
    $orderCodeParam = $_SESSION['latest_order_code'];
}

if ($orderId <= 0 && $orderCodeParam === '') {
    $_SESSION['flash'] = 'No recent order to show.';
    header('Location: ' . url_for('user/account.php'));
    exit;
}

$hasNotes = column_exists($conn, 'orders', 'notes');
$fields   = "id, user_id, order_code, total_amount, payment_method, payment_status, order_status, created_at";
if ($hasNotes) {
    $fields .= ", notes";
}

$orderSql = "SELECT $fields FROM orders WHERE user_id = :uid";
$orderSql .= $orderId > 0 ? " AND id = :id" : " AND order_code = :code";
$orderSql .= " LIMIT 1";

$orderStmt = $conn->prepare($orderSql);
$orderStmt->bindValue(':uid', $userId, PDO::PARAM_INT);
if ($orderId > 0) {
    $orderStmt->bindValue(':id', $orderId, PDO::PARAM_INT);
} else {
    $orderStmt->bindValue(':code', $orderCodeParam, PDO::PARAM_STR);
}
$orderStmt->execute();
$order = $orderStmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    $_SESSION['flash'] = 'We could not find that order.';
    header('Location: ' . url_for('user/account.php'));
    exit;
}

$orderNotes   = $hasNotes && !empty($order['notes']) ? (json_decode($order['notes'], true) ?? []) : [];
$orderCode    = $order['order_code'];
$customerName = $_SESSION['user_name'] ?? 'Customer';
$orderDate    = $order['created_at'] ? date('M j, Y g:i A', strtotime($order['created_at'])) : date('M j, Y g:i A');
$paymentMethod = $order['payment_method'] ?: 'KBZPay';
$orderStatus   = ucfirst($order['order_status']);
$paymentStatus = ucfirst($order['payment_status']);

$_SESSION['latest_order_id']     = $order['id'];
$_SESSION['latest_order_code']   = $orderCode;
$_SESSION['latest_order_return'] = $returnParam;

$itemStmt = $conn->prepare("
    SELECT oi.product_name, oi.quantity, oi.unit_price, oi.subtotal, p.product_image
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = :oid
");
$itemStmt->execute([':oid' => $order['id']]);
$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

$paymentStmt = $conn->prepare("
    SELECT amount, method, status, transaction_ref, paid_at
    FROM payments
    WHERE order_id = :oid
    ORDER BY id DESC
    LIMIT 1
");
$paymentStmt->execute([':oid' => $order['id']]);
$payment = $paymentStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$subtotal = 0;
foreach ($items as $it) {
    $subtotal += (float)$it['unit_price'] * (int)$it['quantity'];
}
$originalTotal = $orderNotes['original_total'] ?? $subtotal;
if ($originalTotal <= 0) {
    $originalTotal = $order['total_amount'];
}
$discount     = max(0, $originalTotal - $order['total_amount']);
$taxes        = isset($orderNotes['tax_amount']) ? (float)$orderNotes['tax_amount'] : 0;
$shippingFee  = isset($orderNotes['shipping_fee']) ? (float)$orderNotes['shipping_fee'] : 0;
$contactEmail = $_SESSION['user_email'] ?? '';
$deliveryEmail = $orderNotes['delivery_email'] ?? $contactEmail;
$contactPhone  = $orderNotes['user_phone'] ?? ($orderNotes['existing_phone'] ?? '');
$gameId        = $orderNotes['user_game_id'] ?? '';
$zoneId        = $orderNotes['user_zone_id'] ?? '';
$userAccount   = $orderNotes['user_account'] ?? ($orderNotes['existing_email'] ?? '');
$giftcardCode  = $orderNotes['giftcard_code'] ?? '';
$generatedAccount = $orderNotes['generated_account'] ?? null;
$promoCode     = $orderNotes['promo_code'] ?? '';
$serviceAction = $orderNotes['service_action'] ?? '';

$continueUrl = trim($returnParam) !== '' ? $returnParam : '../main/shop.php';
$continueLabel = (strpos($continueUrl, 'account.php') !== false) ? 'Back to account' : 'Continue shopping';
$ordersLink = url_for('user/account.php') . '?panel=orders&highlight=' . urlencode($orderCode) . '#order-' . rawurlencode($orderCode);

$digitalRows = [];
if ($deliveryEmail) {
    $digitalRows[] = ['label' => 'Delivery email', 'value' => $deliveryEmail];
}
if ($contactPhone) {
    $digitalRows[] = ['label' => 'Phone / Contact', 'value' => $contactPhone];
}
if ($gameId) {
    $label = 'Game ID';
    if ($zoneId) {
        $label .= ' / Zone';
    }
    $value = $zoneId ? $gameId . ' (Zone ' . $zoneId . ')' : $gameId;
    $digitalRows[] = ['label' => $label, 'value' => $value];
}
if ($userAccount) {
    $digitalRows[] = ['label' => 'Account', 'value' => $userAccount];
}
if ($giftcardCode) {
    $digitalRows[] = ['label' => 'Gift card code', 'value' => $giftcardCode];
}
if (is_array($generatedAccount) && !empty($generatedAccount['username'])) {
    $creds = $generatedAccount['username'];
    if (!empty($generatedAccount['password'])) {
        $creds .= ' / ' . $generatedAccount['password'];
    }
    $digitalRows[] = ['label' => 'Credentials', 'value' => $creds];
}
if ($promoCode) {
    $digitalRows[] = ['label' => 'Promo', 'value' => strtoupper($promoCode)];
}

$statusSteps = [
    ['label' => 'Order placed', 'active' => true],
    ['label' => 'Processing', 'active' => in_array($order['order_status'], ['processing', 'completed', 'refunded', 'cancelled'], true)],
    ['label' => 'Completed', 'active' => in_array($order['order_status'], ['completed'], true)],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order Confirmed - Dokebi Family</title>
  <link rel="stylesheet" href="../maincss/style.css">
  <link rel="stylesheet" href="../maincss/dark-mode.css">
  <style>
    body.order-success-page { background: var(--bg); }
    .confirm-shell { max-width: 1200px; margin: 1.5rem auto 2.5rem; padding: 0 1rem; }
    .hero-card {
      background: var(--surface);
      border: 1px solid var(--glass);
      border-radius: 22px;
      padding: 1.5rem;
      display: grid;
      grid-template-columns: 1.05fr 0.95fr;
      gap: 1.5rem;
      align-items: center;
      position: relative;
      overflow: hidden;
      box-shadow: 0 24px 60px rgba(15, 23, 42, 0.12);
    }
    .hero-card::before, .hero-card::after {
      content: '';
      position: absolute;
      border-radius: 50%;
      background: radial-gradient(circle at 30% 30%, rgba(37,99,235,0.12), rgba(37,99,235,0.02));
      width: 260px;
      height: 260px;
      z-index: 0;
    }
    .hero-card::before { top: -40px; right: -80px; }
    .hero-card::after { bottom: -60px; left: -40px; }
    .hero-copy { position: relative; z-index: 1; display: flex; flex-direction: column; gap: 0.75rem; }
    .eyebrow { letter-spacing: 0.06em; font-weight: 700; color: var(--muted); text-transform: uppercase; font-size: 0.85rem; }
    .hero-title { font-size: 1.75rem; margin: 0; color: var(--text); }
    .status-pills { display: flex; gap: 0.5rem; flex-wrap: wrap; }
    .pill-outline { border: 1px solid var(--glass); padding: 0.35rem 0.6rem; border-radius: 999px; font-weight: 600; color: var(--text); background: rgba(255,255,255,0.6); }
    .hero-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
    .hero-visual {
      position: relative;
      z-index: 1;
      background: linear-gradient(135deg, rgba(37,99,235,0.12), rgba(6,182,212,0.1));
      border-radius: 18px;
      padding: 1rem;
      min-height: 260px;
      display: grid;
      place-items: center;
      box-shadow: inset 0 1px 0 rgba(255,255,255,0.35);
    }
    .visual-card {
      background: var(--surface);
      border: 1px solid var(--glass);
      border-radius: 16px;
      padding: 1.1rem;
      width: 100%;
      max-width: 360px;
      text-align: center;
      box-shadow: 0 20px 40px rgba(15,23,42,0.08);
    }
    .visual-check {
      width: 52px;
      height: 52px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--accent), var(--accent-2));
      display: grid;
      place-items: center;
      color: #fff;
      margin: 0 auto 0.75rem;
      box-shadow: 0 10px 28px rgba(37,99,235,0.35);
    }
    .visual-head { font-weight: 800; margin: 0; color: var(--text); }
    .visual-sub { margin: 0.25rem 0 0.75rem; color: var(--muted); }
    .visual-done { display: none; }
    .confirm-grid {
      margin-top: 1.25rem;
      display: grid;
      grid-template-columns: 1.25fr 0.9fr;
      gap: 1rem;
      align-items: start;
    }
    .card {
      background: var(--surface);
      border: 1px solid var(--glass);
      border-radius: 16px;
      padding: 1rem 1.1rem;
      box-shadow: 0 10px 28px rgba(15,23,42,0.08);
    }
    .card-title { font-weight: 700; margin: 0 0 0.35rem; }
    .card-sub { color: var(--muted); margin: 0 0 0.5rem; }
    .status-steps { display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 0.35rem; }
    .status-step { display: flex; align-items: center; gap: 0.4rem; color: var(--muted); font-weight: 600; }
    .status-dot { width: 18px; height: 18px; border-radius: 50%; border: 2px solid var(--glass); display: inline-flex; align-items: center; justify-content: center; font-size: 12px; }
    .status-step.active { color: var(--text); }
    .status-step.active .status-dot { border-color: var(--accent); background: rgba(37,99,235,0.12); color: var(--accent); }
    .info-pairs { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.4rem 0.8rem; margin-top: 0.5rem; }
    .info-line { display: flex; flex-direction: column; gap: 0.1rem; }
    .info-label { font-size: 0.85rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.04em; }
    .info-value { font-weight: 700; color: var(--text); }
    .digital-list { margin: 0.35rem 0 0; padding: 0; list-style: none; display: grid; gap: 0.4rem; }
    .digital-list li { background: rgba(37,99,235,0.06); border: 1px dashed var(--glass); padding: 0.55rem 0.7rem; border-radius: 10px; display: flex; justify-content: space-between; gap: 0.5rem; flex-wrap: wrap; }
    .summary-card { background: var(--surface); border: 1px solid var(--glass); border-radius: 16px; padding: 1rem; box-shadow: 0 12px 32px rgba(15,23,42,0.1); color: var(--text); }
    .summary-head { display: flex; justify-content: space-between; align-items: baseline; gap: 0.5rem; }
    .summary-items { margin: 0.75rem 0; display: grid; gap: 0.65rem; }
    .summary-item { display: flex; gap: 0.75rem; align-items: center; }
    .summary-thumb { width: 64px; height: 64px; border-radius: 12px; object-fit: cover; border: 1px solid var(--glass); background: var(--surface); }
    .summary-meta { flex: 1; }
    .summary-meta .title { font-weight: 700; }
    .summary-meta .muted { margin-top: 2px; }
    .summary-price { text-align: right; font-weight: 700; }
    .summary-rows { border-top: 1px solid var(--glass); margin-top: 0.5rem; padding-top: 0.75rem; display: grid; gap: 0.45rem; }
    .summary-row { display: flex; justify-content: space-between; align-items: center; }
    .summary-row.total { font-size: 1.1rem; font-weight: 800; color: var(--text); }
    .summary-actions { margin-top: 0.75rem; display: flex; gap: 0.5rem; flex-wrap: wrap; }
    @media (max-width: 960px) {
      .hero-card { grid-template-columns: 1fr; }
      .confirm-grid { grid-template-columns: 1fr; }
    }
    .order-success-page .site-footer-wide {
      margin-top: 0;
      background: linear-gradient(180deg, #0b1224, #0f172a);
      color: #e2e8f0;
      border-top: 1px solid rgba(255,255,255,0.08);
    }
    .order-success-page .site-footer-wide .footer-bottom {
      border-top: 1px solid rgba(255,255,255,0.08);
    }
    [data-theme="light"] .order-success-page .site-footer-wide {
      background: linear-gradient(180deg, #f4f6fb, #e2e8f0);
      color: #0f1724;
      border-top: 1px solid var(--glass);
    }
    [data-theme="light"] .order-success-page .site-footer-wide .footer-bottom {
      border-top: 1px solid var(--glass);
    }
    [data-theme="dark"] .hero-card,
    [data-theme="dark"] .card,
    [data-theme="dark"] .summary-card {
      background: #0b1224;
      border-color: rgba(255,255,255,0.07);
      box-shadow: 0 22px 48px rgba(0,0,0,0.65);
      color: var(--text);
    }
    [data-theme="dark"] .hero-visual {
      background: linear-gradient(135deg, rgba(6,182,212,0.22), rgba(167,139,250,0.18));
      box-shadow: inset 0 1px 0 rgba(255,255,255,0.08);
    }
    [data-theme="dark"] .visual-card {
      box-shadow: 0 20px 44px rgba(0,0,0,0.5);
    }
    [data-theme="dark"] .summary-thumb {
      background: #111827;
      border-color: rgba(255,255,255,0.08);
    }
    [data-theme="dark"] .digital-list li {
      background: rgba(6,182,212,0.08);
      border-color: rgba(255,255,255,0.08);
    }
  </style>
</head>
<body class="order-success-page">
  <header class="site-header">
    <div class="container header-inner">
      <a class="back-icon" href="<?php echo url_for('main/shop.php'); ?>" aria-label="Back to shop">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </a>
      <a class="logo" href="<?php echo url_for('main/index.php'); ?>"><img src="../logo/original.png" alt="Dokebi Family" class="logo-img"><span class="sitename">Dokebi Family</span></a>
      <div class="header-left-links">
        <a href="<?php echo url_for('main/shop.php'); ?>">Shop</a>
        <a href="<?php echo url_for('user/account.php'); ?>">Orders</a>
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
    <section class="confirm-shell">
      <div class="hero-card">
        <div class="hero-copy">
          <div class="eyebrow">Order <?php echo htmlspecialchars($orderCode); ?></div>
          <h1 class="hero-title">Thank you <?php echo htmlspecialchars($customerName); ?>!</h1>
          <p class="muted">Your order is confirmed. You will get a confirmation email shortly with your order number and delivery details.</p>
          <div class="status-pills">
            <span class="pill-outline">Order: <?php echo htmlspecialchars($orderStatus); ?></span>
            <span class="pill-outline">Payment: <?php echo htmlspecialchars($paymentStatus); ?></span>
            <span class="pill-outline">Method: <?php echo htmlspecialchars($paymentMethod); ?></span>
          </div>
          <div class="hero-actions">
            <a class="btn primary" href="<?php echo htmlspecialchars($ordersLink); ?>">Go to my orders</a>
            <button class="btn" type="button" onclick="window.print()">Download receipt</button>
            <a class="btn" href="<?php echo htmlspecialchars($continueUrl); ?>"><?php echo htmlspecialchars($continueLabel); ?></a>
          </div>
          <div class="status-steps">
            <?php foreach ($statusSteps as $idx => $step): ?>
              <div class="status-step <?php echo $step['active'] ? 'active' : ''; ?>">
                <span class="status-dot"><?php echo $step['active'] ? '✓' : $idx + 1; ?></span>
                <span><?php echo htmlspecialchars($step['label']); ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="hero-visual" aria-hidden="true">
          <div class="visual-card">
            <div class="visual-check">
              <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 6L9 17l-5-5" />
              </svg>
            </div>
            <p class="visual-head">Your Order Is Confirmed!</p>
            <p class="visual-sub">Thanks for your order.</p>
          </div>
        </div>
      </div>

      <div class="confirm-grid">
        <div class="left-stack" style="display:flex;flex-direction:column;gap:0.9rem;">
          <div class="card">
            <div class="card-title">Order updates</div>
            <p class="card-sub">We will email updates to <?php echo htmlspecialchars($deliveryEmail ?: $contactEmail); ?>. Need to change details? Visit your account or reach our live chat.</p>
            <div class="info-pairs">
              <div class="info-line">
                <span class="info-label">Order date</span>
                <span class="info-value"><?php echo htmlspecialchars($orderDate); ?></span>
              </div>
              <div class="info-line">
                <span class="info-label">Order code</span>
                <span class="info-value"><?php echo htmlspecialchars($orderCode); ?></span>
              </div>
              <div class="info-line">
                <span class="info-label">Payment</span>
                <span class="info-value"><?php echo htmlspecialchars($paymentMethod); ?> · <?php echo htmlspecialchars($paymentStatus); ?></span>
              </div>
              <div class="info-line">
                <span class="info-label">Status</span>
                <span class="info-value"><?php echo htmlspecialchars($orderStatus); ?></span>
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card-title">Customer & delivery</div>
            <p class="card-sub">Here is where we'll send confirmations and digital deliveries.</p>
            <div class="info-pairs">
              <div class="info-line">
                <span class="info-label">Customer</span>
                <span class="info-value"><?php echo htmlspecialchars($customerName); ?></span>
              </div>
              <div class="info-line">
                <span class="info-label">Email</span>
                <span class="info-value"><?php echo htmlspecialchars($deliveryEmail ?: $contactEmail); ?></span>
              </div>
              <?php if ($contactPhone): ?>
              <div class="info-line">
                <span class="info-label">Phone</span>
                <span class="info-value"><?php echo htmlspecialchars($contactPhone); ?></span>
              </div>
              <?php endif; ?>
              <?php if ($userAccount): ?>
              <div class="info-line">
                <span class="info-label">Account reference</span>
                <span class="info-value"><?php echo htmlspecialchars($userAccount); ?></span>
              </div>
              <?php endif; ?>
            </div>
          </div>

          <?php if (!empty($digitalRows)): ?>
            <div class="card">
              <div class="card-title">Digital details</div>
              <p class="card-sub">Keep this handy to redeem or receive your items.</p>
              <ul class="digital-list">
                <?php foreach ($digitalRows as $row): ?>
                  <li>
                    <span class="info-label" style="margin:0;"><?php echo htmlspecialchars($row['label']); ?></span>
                    <span class="info-value" style="margin:0;"><?php echo htmlspecialchars($row['value']); ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
        </div>

        <aside class="summary-card">
          <div class="summary-head">
            <div>
              <div class="card-title" style="margin:0;">Order summary</div>
              <div class="muted tiny">Placed <?php echo htmlspecialchars($orderDate); ?></div>
            </div>
            <div class="muted tiny">Code: <?php echo htmlspecialchars($orderCode); ?></div>
          </div>
          <div class="summary-items">
            <?php if (!$items): ?>
              <div class="muted">Items will appear once added.</div>
            <?php else: ?>
              <?php foreach ($items as $it):
                $imgFile = $it['product_image'] ?: 'default_product.png';
                $imgPath = "../uploads/products/" . $imgFile;
                $lineTotal = (float)$it['unit_price'] * (int)$it['quantity'];
              ?>
                <div class="summary-item">
                  <img class="summary-thumb" src="<?php echo htmlspecialchars($imgPath); ?>" alt="<?php echo htmlspecialchars($it['product_name']); ?>">
                  <div class="summary-meta">
                    <div class="title"><?php echo htmlspecialchars($it['product_name']); ?></div>
                    <div class="muted tiny"><?php echo (int)$it['quantity']; ?> × <?php echo format_money((float)$it['unit_price']); ?></div>
                  </div>
                  <div class="summary-price"><?php echo format_money($lineTotal); ?></div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <div class="summary-rows">
            <div class="summary-row">
              <span>Subtotal</span>
              <span><?php echo format_money($originalTotal); ?></span>
            </div>
            <?php if ($discount > 0): ?>
            <div class="summary-row">
              <span>Discount<?php echo $promoCode ? ' (' . htmlspecialchars(strtoupper($promoCode)) . ')' : ''; ?></span>
              <span>-<?php echo format_money($discount); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($shippingFee > 0): ?>
            <div class="summary-row">
              <span>Shipping</span>
              <span><?php echo format_money($shippingFee); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($taxes > 0): ?>
            <div class="summary-row">
              <span>Taxes / Fees</span>
              <span><?php echo format_money($taxes); ?></span>
            </div>
            <?php endif; ?>
            <div class="summary-row total">
              <span>Total</span>
              <span><?php echo format_money($order['total_amount']); ?></span>
            </div>
          </div>
          <div class="summary-actions">
            <a class="btn primary" href="<?php echo htmlspecialchars($ordersLink); ?>">Go to order history</a>
            <button class="btn" type="button" onclick="window.print()">Print</button>
            <a class="btn" href="<?php echo htmlspecialchars($continueUrl); ?>"><?php echo htmlspecialchars($continueLabel); ?></a>
          </div>
        </aside>
      </div>
    </section>
  </main>
  <script src="../js/theme-toggle.js"></script>
  <script src="../js/app.js"></script>
  <script>
    (function() {
      const shouldShow = <?php echo $showSuccessModal ? 'true' : 'false'; ?>;
      if (!shouldShow || !window.showFlashModal) return;
      const msg = <?php echo json_encode($flashMessage ?: 'Order ' . $orderCode . ' created successfully.'); ?>;
      window.showFlashModal(msg, {
        title: 'Order placed',
        subtitle: 'Your order is confirmed. We are processing it now.',
        primaryText: 'Go to order history',
        primaryLink: '<?php echo htmlspecialchars($ordersLink, ENT_QUOTES); ?>',
        secondaryText: '<?php echo htmlspecialchars($continueLabel, ENT_QUOTES); ?>',
        secondaryLink: '<?php echo htmlspecialchars($continueUrl, ENT_QUOTES); ?>'
      });
    })();
  </script>
  <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
