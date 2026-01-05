<?php
require_once __DIR__ . '/mailer.php';

/**
 * Send customer receipt email.
 */
function sendCustomerReceipt(array $order, array $user): array {
    $to = $user['email'] ?? '';
    if (!$to) {
        return ['sent' => false, 'error' => 'No customer email'];
    }
    $subject = 'Your order ' . htmlspecialchars($order['order_code'] ?? '');
    $items = $order['items'] ?? [];
    $orderDate = $order['order_date'] ?? date('Y-m-d H:i');
    $status = ucfirst($order['status'] ?? 'pending');
    $pay = $order['payment_method'] ?? 'Pending';
    $total = number_format((float)($order['total_amount'] ?? 0), 2) . ' MMK';

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $logoUrl = $scheme . '://' . $host . '/logo/original.png';

    $itemsHtml = '';
    if ($items) {
        $itemsHtml .= '<table style="width:100%;border-collapse:collapse;margin-top:12px;font-size:14px;">';
        $itemsHtml .= '<tr style="background:#f1f5f9;color:#0f172a;"><th align="left" style="padding:8px;">Item</th><th align="center" style="padding:8px;">Qty</th><th align="right" style="padding:8px;">Price</th><th align="right" style="padding:8px;">Subtotal</th></tr>';
        foreach ($items as $it) {
            $itemsHtml .= '<tr style="border-bottom:1px solid #e2e8f0;">';
            $itemsHtml .= '<td style="padding:6px 8px;color:#0f172a;font-weight:600;">' . htmlspecialchars($it['name'] ?? '') . '</td>';
            $itemsHtml .= '<td style="padding:6px 8px;text-align:center;color:#475569;">' . (int)($it['quantity'] ?? 1) . '</td>';
            $itemsHtml .= '<td style="padding:6px 8px;text-align:right;color:#475569;">' . number_format((float)($it['unit'] ?? 0),2) . ' MMK</td>';
            $itemsHtml .= '<td style="padding:6px 8px;text-align:right;color:#0f172a;font-weight:600;">' . number_format((float)($it['subtotal'] ?? 0),2) . ' MMK</td>';
            $itemsHtml .= '</tr>';
        }
        $itemsHtml .= '<tr><td colspan="3" style="padding:8px 8px 4px;text-align:right;color:#475569;font-weight:600;">Total</td><td style="padding:8px 8px 4px;text-align:right;color:#0f172a;font-weight:700;">' . $total . '</td></tr>';
        $itemsHtml .= '</table>';
    }

    $html = '<div style="font-family:Arial,Helvetica,sans-serif;background:#f8fafc;padding:18px;">';
    $html .= '<div style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:14px;padding:22px;border:1px solid #e2e8f0;">';
    $html .= '<div style="text-align:center;margin-bottom:16px;">
                <img src="' . htmlspecialchars($logoUrl) . '" alt="Dokebi Family" style="height:44px;margin-bottom:10px;">
                <div style="width:64px;height:64px;margin:0 auto 10px;border-radius:50%;background:#22c55e1a;border:1px solid #22c55e66;display:flex;align-items:center;justify-content:center;font-size:30px;color:#22c55e;">✔</div>
                <h2 style="margin:4px 0 6px;font-size:22px;color:#0f172a;">Payment Complete</h2>
                <div style="color:#475569;font-size:14px;">We’ve received your payment. A confirmation was sent to ' . htmlspecialchars($to) . '</div>
              </div>';
    $html .= '<div style="display:grid;gap:6px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;margin-bottom:14px;font-size:14px;color:#0f172a;">';
    $html .= '<div style="display:flex;justify-content:space-between;"><span style="color:#475569;">Order Ref</span><strong>' . htmlspecialchars($order['order_code'] ?? '') . '</strong></div>';
    $html .= '<div style="display:flex;justify-content:space-between;"><span style="color:#475569;">Order Date</span><span>' . htmlspecialchars($orderDate) . '</span></div>';
    $html .= '<div style="display:flex;justify-content:space-between;"><span style="color:#475569;">Payment Type</span><span>' . htmlspecialchars($pay) . '</span></div>';
    $html .= '<div style="display:flex;justify-content:space-between;"><span style="color:#475569;">Status</span><span>' . htmlspecialchars($status) . '</span></div>';
    $html .= '<div style="display:flex;justify-content:space-between;font-weight:700;"><span>Total</span><span>' . $total . '</span></div>';
    $html .= '</div>';
    $html .= $itemsHtml;
    $html .= '<p style="color:#475569;font-size:13px;margin-top:16px;">If you have questions, reply to this email. Thank you for shopping with Dokebi Family.</p>';
    $html .= '<div style="margin-top:12px;font-size:12px;color:#94a3b8;text-align:center;">Dokebi Family</div>';
    $html .= '</div></div>';
    return send_app_mail($to, $subject, $html);
}

/**
 * Send admin notification email.
 */
function sendAdminNotification(array $order, array $user): array {
    $cfg = file_exists(__DIR__ . '/mail_config.php') ? include __DIR__ . '/mail_config.php' : [];
    $admin = $cfg['admin_email'] ?? '';
    if (!$admin) return ['sent' => false, 'error' => 'No admin email'];

    $subject = 'New order ' . htmlspecialchars($order['order_code'] ?? '');
    $html = '<p>New order created.</p>';
    $html .= '<p><strong>Order:</strong> ' . htmlspecialchars($order['order_code'] ?? '') . '<br>';
    $html .= '<strong>User:</strong> ' . htmlspecialchars($user['name'] ?? ('ID ' . ($user['id'] ?? ''))) . '<br>';
    $html .= '<strong>Total:</strong> ' . number_format((float)($order['total_amount'] ?? 0), 2) . ' MMK<br>';
    $html .= '<strong>Payment:</strong> ' . htmlspecialchars($order['payment_method'] ?? 'Pending') . '</p>';
    if (!empty($order['items'])) {
        $html .= '<ul>';
        foreach ($order['items'] as $it) {
            $html .= '<li>' . htmlspecialchars($it['name'] ?? '') . ' x ' . (int)($it['quantity'] ?? 1) . ' (' . number_format((float)($it['subtotal'] ?? 0),2) . ' MMK)</li>';
        }
        $html .= '</ul>';
    }
    return send_app_mail($admin, $subject, $html);
}

/**
 * Send gift card codes or premium account credentials.
 */
function sendDigitalDelivery(array $payload, array $user): void {
    $to = $payload['email'] ?? ($user['email'] ?? '');
    $type = $payload['type'] ?? '';
    if (!$to || $type === '') {
        return;
    }
    $service   = $payload['service'] ?? 'Service';
    $orderCode = $payload['order_code'] ?? '';
    $subject   = '';
    $html      = '';

    if ($type === 'giftcard') {
        $code = $payload['code'] ?? '';
        if ($code === '') return;
        $productName = $payload['product_name'] ?? $service;
        $subject = 'Your redeem code for ' . htmlspecialchars($productName);
        $html   .= '<p>Hi ' . htmlspecialchars($user['name'] ?? 'there') . ',</p>';
        $html   .= '<p>Here is your redeem code for <strong>' . htmlspecialchars($productName) . '</strong>:</p>';
        $html   .= '<p style="font-size:18px;"><strong>' . htmlspecialchars($code) . '</strong></p>';
    } elseif ($type === 'premium_new') {
        $account = $payload['account'] ?? [];
        if (empty($account['username']) || empty($account['password'])) return;
        $subject = 'Your new ' . htmlspecialchars($service) . ' account';
        $html   .= '<p>Hi ' . htmlspecialchars($user['name'] ?? 'there') . ',</p>';
        $html   .= '<p>We created a new <strong>' . htmlspecialchars($service) . '</strong> account for you.</p>';
        $html   .= '<p>Username: <strong>' . htmlspecialchars($account['username']) . '</strong><br>';
        $html   .= 'Password: <strong>' . htmlspecialchars($account['password']) . '</strong></p>';
    } elseif ($type === 'premium_extend') {
        $subject = 'Extending your ' . htmlspecialchars($service) . ' account';
        $html   .= '<p>Hi ' . htmlspecialchars($user['name'] ?? 'there') . ',</p>';
        $html   .= '<p>We received your request to extend your <strong>' . htmlspecialchars($service) . '</strong> subscription.</p>';
        if (!empty($payload['target_email']) || !empty($payload['target_phone']) || !empty($payload['target_username'])) {
            $html .= '<p>Account details:<br>';
            if (!empty($payload['target_email'])) {
                $html .= 'Email: <strong>' . htmlspecialchars($payload['target_email']) . '</strong><br>';
            }
            if (!empty($payload['target_phone'])) {
                $html .= 'Phone: <strong>' . htmlspecialchars($payload['target_phone']) . '</strong><br>';
            }
            if (!empty($payload['target_username'])) {
                $html .= 'Username: <strong>' . htmlspecialchars($payload['target_username']) . '</strong><br>';
            }
            $html .= '</p>';
        }
        $html   .= '<p>We will notify you once the subscription is updated.</p>';
    } else {
        return;
    }

    if ($orderCode !== '') {
        $html .= '<p>Order: ' . htmlspecialchars($orderCode) . '</p>';
    }

    send_app_mail($to, $subject, $html);

    $cfg = file_exists(__DIR__ . '/mail_config.php') ? include __DIR__ . '/mail_config.php' : [];
    $admin = $cfg['admin_email'] ?? '';
    if ($admin) {
        send_app_mail($admin, $subject . ' (admin copy)', $html);
    }
}

/**
 * Combined hook for order creation.
 */
function sendOrderConfirmation(array $order, array $user): void {
    // Fire and forget; ignore errors to not block checkout.
    sendCustomerReceipt($order, $user);
    sendAdminNotification($order, $user);
}
