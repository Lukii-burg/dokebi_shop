<?php
require_once __DIR__ . '/../db/functions.php';
require_once __DIR__ . '/../includes/order_notifications.php';

function table_exists(PDO $conn, string $table): bool {
    try {
        $stmt = $conn->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1");
        $stmt->execute([':t' => $table]);
        return (bool)$stmt->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

function column_exists(PDO $conn, string $table, string $column): bool {
    try {
        $stmt = $conn->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c LIMIT 1");
        $stmt->execute([':t' => $table, ':c' => $column]);
        return (bool)$stmt->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

if (!is_logged_in()) {
    $_SESSION['flash'] = 'Please sign in to buy products.';
    header('Location: ../auth/login.php');
    exit;
}

$userId = current_user_id();
$productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$qty            = isset($_POST['qty']) ? max(1, (int)$_POST['qty']) : 1;
$paymentMethod  = trim($_POST['payment_method'] ?? 'KBZPay');
$userGameId     = trim($_POST['user_game_id'] ?? '');
$userZoneId     = trim($_POST['user_zone_id'] ?? '');
$userAccount    = trim($_POST['user_account'] ?? '');
$userPhone      = trim($_POST['user_phone'] ?? '');
$deliveryEmail  = trim($_POST['delivery_email'] ?? '');
$accountOption  = $_POST['account_option'] ?? 'new';
$existingEmail  = trim($_POST['existing_email'] ?? '');
$existingUsername = trim($_POST['existing_username'] ?? '');
$existingPhone  = trim($_POST['existing_phone'] ?? '');
$promoInput     = trim($_POST['promo_code'] ?? '');
$redirect       = $_POST['redirect'] ?? '../main/shop.php';
$welcomePromo   = get_welcome_promo($conn, $userId);
$promoApplied   = false;
$promoSavings   = 0;
$promoNote      = '';
$orderHasNotes  = column_exists($conn, 'orders', 'notes');

if ($productId <= 0) {
    $_SESSION['flash'] = 'Invalid product.';
    header('Location: ../main/shop.php');
    exit;
}

try {
    $conn->beginTransaction();
    $redeemCode = null;
    $generatedAccount = null;

    // Lock the product row to avoid overselling
    $prodStmt = $conn->prepare("
        SELECT p.*, c.slug AS category_slug
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE p.id = :id AND p.status = 'active'
        FOR UPDATE
    ");
    $prodStmt->execute([':id' => $productId]);
    $product = $prodStmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        $conn->rollBack();
        $_SESSION['flash'] = 'Product unavailable.';
        header('Location: ../main/shop.php');
        exit;
    }

    if ($product['stock'] < $qty) {
        $conn->rollBack();
        $_SESSION['flash'] = 'Not enough stock available.';
        $slugParam = $product['category_slug'] ? '?category=' . urlencode($product['category_slug']) : '';
        header('Location: ../main/shop.php' . $slugParam);
        exit;
    }

    $categorySlug = $product['category_slug'] ?? '';
    $giftCardSlugs = ['gift-cards', 'steam-wallet', 'google-play-gift-cards', 'app-store-gift-cards'];
    $premiumSlugs  = ['premium-accounts', 'spotify-premium', 'youtube-premium', 'netflix-premium', 'telegram-premium', 'chatgpt-premium'];
    $telcoSlugs    = ['mm-topup', 'mpt-topup', 'u9-topup', 'atom-topup', 'mytel-topup'];
    $serviceNames  = [
        'spotify-premium'  => 'Spotify Premium',
        'youtube-premium'  => 'YouTube Premium',
        'netflix-premium'  => 'Netflix Premium',
        'telegram-premium' => 'Telegram Premium',
        'chatgpt-premium'  => 'ChatGPT Premium',
        'premium-accounts' => 'Premium Account'
    ];
    $serviceName = $serviceNames[$categorySlug] ?? ($product['product_name'] ?? 'Service');
    $accountOption = $accountOption === 'existing' ? 'existing' : 'new';
    $isGiftCard = in_array($categorySlug, $giftCardSlugs, true);
    $isPremiumService = in_array($categorySlug, $premiumSlugs, true);
    $isMyanmarTelco = in_array($categorySlug, $telcoSlugs, true);
    $userEmail = $_SESSION['user_email'] ?? '';

    // Input validation per product family
    $redirectTarget = $redirect ?: '../main/shop.php';
    if ($isMyanmarTelco && $userPhone === '') {
        $conn->rollBack();
        $_SESSION['flash'] = 'Please provide the phone number for this top-up.';
        header('Location: ' . $redirectTarget);
        exit;
    }

    if ($isGiftCard) {
        if ($deliveryEmail === '') {
            $deliveryEmail = $userEmail;
        }
        if ($deliveryEmail === '') {
            $conn->rollBack();
            $_SESSION['flash'] = 'Add your Gmail address so we can send the redeem code.';
            header('Location: ' . $redirectTarget);
            exit;
        }
        if (stripos($deliveryEmail, '@gmail.com') === false) {
            $conn->rollBack();
            $_SESSION['flash'] = 'Gift cards require a Gmail account for delivery.';
            header('Location: ' . $redirectTarget);
            exit;
        }
    }

    if ($isPremiumService) {
        if ($accountOption === 'existing') {
            if ($categorySlug === 'telegram-premium') {
                if ($existingPhone === '' || $existingEmail === '' || $existingUsername === '') {
                    $conn->rollBack();
                    $_SESSION['flash'] = 'Enter phone, email, and username to extend your Telegram Premium account.';
                    header('Location: ' . $redirectTarget);
                    exit;
                }
            } else {
                if ($existingEmail === '') {
                    $conn->rollBack();
                    $_SESSION['flash'] = 'Enter the email of the account you want to extend.';
                    header('Location: ' . $redirectTarget);
                    exit;
                }
            }
            if ($deliveryEmail === '' && $existingEmail !== '') {
                $deliveryEmail = $existingEmail;
            }
        } else {
            if ($deliveryEmail === '') {
                $deliveryEmail = $userEmail;
            }
            if ($deliveryEmail === '') {
                $conn->rollBack();
                $_SESSION['flash'] = 'Add an email so we can deliver your new account credentials.';
                header('Location: ' . $redirectTarget);
                exit;
            }
        }
    }

    $originalTotal = $product['price'] * $qty;
    $total = $originalTotal;
    if ($promoInput !== '' && !$welcomePromo['used'] && strcasecmp($promoInput, $welcomePromo['code']) === 0) {
        $promoSavings = round($total * ($welcomePromo['discount'] / 100), 2);
        $total = max(0, $total - $promoSavings);
        $promoApplied = true;
    } elseif ($promoInput !== '') {
        $promoNote = 'Promo code is invalid or already redeemed.';
    }
    $orderCode = 'ORD-' . strtoupper(bin2hex(random_bytes(4)));
    $orderCreatedAt = date('Y-m-d H:i');

    $orderNotesData = [
        'user_game_id' => $userGameId,
        'user_zone_id' => $userZoneId,
        'user_account' => $userAccount,
        'user_phone'   => $userPhone,
        'account_option' => $isPremiumService ? $accountOption : null,
        'delivery_email' => $deliveryEmail,
        'existing_email' => $existingEmail,
        'existing_username' => $existingUsername,
        'existing_phone' => $existingPhone,
        'service_action' => $isPremiumService ? ($accountOption === 'new' ? 'new_account' : 'extend_account') : null,
        'promo_code'   => $promoApplied ? $welcomePromo['code'] : null,
        'promo_discount_percent' => $promoApplied ? $welcomePromo['discount'] : null,
        'promo_savings' => $promoApplied ? $promoSavings : 0,
        'original_total' => $originalTotal
    ];
    $orderNotes = json_encode($orderNotesData);

    if ($orderHasNotes) {
        $orderStmt = $conn->prepare("
            INSERT INTO orders (user_id, order_code, total_amount, payment_method, payment_status, order_status, notes, created_at)
            VALUES (:uid, :code, :total, :method, 'pending', 'pending', :notes, NOW())
        ");
        $orderStmt->execute([
            ':uid'   => $userId,
            ':code'  => $orderCode,
            ':total' => $total,
            ':method'=> $paymentMethod !== '' ? $paymentMethod : 'KBZPay',
            ':notes' => $orderNotes
        ]);
    } else {
        $orderStmt = $conn->prepare("
            INSERT INTO orders (user_id, order_code, total_amount, payment_method, payment_status, order_status, created_at)
            VALUES (:uid, :code, :total, :method, 'pending', 'pending', NOW())
        ");
        $orderStmt->execute([
            ':uid'   => $userId,
            ':code'  => $orderCode,
            ':total' => $total,
            ':method'=> $paymentMethod !== '' ? $paymentMethod : 'KBZPay'
        ]);
    }

    $orderId = $conn->lastInsertId();

    // Insert order item
    $itemStmt = $conn->prepare("
        INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, subtotal)
        VALUES (:oid, :pid, :name, :qty, :price, :subtotal)
    ");
    $itemStmt->execute([
        ':oid'      => $orderId,
        ':pid'      => $product['id'],
        ':name'     => $product['product_name'],
        ':qty'      => $qty,
        ':price'    => $product['price'],
        ':subtotal' => $total
    ]);

    // Create payment row so admins can see pending payments
    $payStmt = $conn->prepare("
        INSERT INTO payments (order_id, amount, method, status, transaction_ref, paid_at)
        VALUES (:oid, :amount, :method, 'pending', NULL, NULL)
    ");
    $payStmt->execute([
        ':oid'    => $orderId,
        ':amount' => $total,
        ':method' => $paymentMethod !== '' ? $paymentMethod : 'KBZPay'
    ]);

    // Decrease stock
    $stockStmt = $conn->prepare("UPDATE products SET stock = stock - :qty WHERE id = :id");
    $stockStmt->execute([':qty' => $qty, ':id' => $product['id']]);

    // Gift card delivery code
    if ($isGiftCard) {
        if (!table_exists($conn, 'giftcard_codes')) {
            $redeemCode = 'GC-' . strtoupper(bin2hex(random_bytes(4)));
        } else {
            $tries = 0;
            while ($tries < 5) {
                $candidate = 'GC-' . strtoupper(bin2hex(random_bytes(4)));
                try {
                    $ins = $conn->prepare("
                        INSERT INTO giftcard_codes (category_id, product_id, code, is_used, used_by, order_id, delivered_to, used_at)
                        VALUES (:cid, :pid, :code, 1, :uid, :oid, :email, NOW())
                    ");
                    $ins->execute([
                        ':cid'   => $product['category_id'],
                        ':pid'   => $product['id'],
                        ':code'  => $candidate,
                        ':uid'   => $userId,
                        ':oid'   => $orderId,
                        ':email' => $deliveryEmail
                    ]);
                    $redeemCode = $candidate;
                    break;
                } catch (Exception $e) {
                    $tries++;
                }
            }
            if ($redeemCode === null) {
                $redeemCode = 'GC-' . strtoupper(bin2hex(random_bytes(4)));
            }
        }
        $orderNotesData['giftcard_code'] = $redeemCode;
    }

    // Premium new account inventory
    if ($isPremiumService && $accountOption === 'new') {
        if (!table_exists($conn, 'premium_accounts_pool')) {
            $generatedAccount = [
                'username' => strtolower(preg_replace('/\s+/', '', substr($serviceName, 0, 8))) . random_int(1000, 9999),
                'password' => 'P@' . strtoupper(bin2hex(random_bytes(3)))
            ];
        } else {
            $attempts = 0;
            while ($attempts < 6) {
                $candidateUser = strtolower(preg_replace('/\s+/', '', substr($serviceName, 0, 8))) . random_int(1000, 9999);
                $candidatePass = 'P@' . strtoupper(bin2hex(random_bytes(3)));
                try {
                    $insAcc = $conn->prepare("
                        INSERT INTO premium_accounts_pool (category_id, product_id, service, username, password, is_assigned, assigned_to, order_id, assigned_at)
                        VALUES (:cid, :pid, :svc, :usr, :pwd, 1, :uid, :oid, NOW())
                    ");
                    $insAcc->execute([
                        ':cid' => $product['category_id'],
                        ':pid' => $product['id'],
                        ':svc' => $serviceName,
                        ':usr' => $candidateUser,
                        ':pwd' => $candidatePass,
                        ':uid' => $userId,
                        ':oid' => $orderId
                    ]);
                    $generatedAccount = [
                        'username' => $candidateUser,
                        'password' => $candidatePass
                    ];
                    break;
                } catch (Exception $e) {
                    $attempts++;
                }
            }
            if ($generatedAccount === null) {
                $generatedAccount = [
                    'username' => strtolower(preg_replace('/\s+/', '', substr($serviceName, 0, 8))) . random_int(1000, 9999),
                    'password' => 'P@' . strtoupper(bin2hex(random_bytes(3)))
                ];
            }
        }
        $orderNotesData['generated_account'] = $generatedAccount;
    }

    if ($orderHasNotes && ($redeemCode !== null || $generatedAccount !== null)) {
        $updateStmt = $conn->prepare("UPDATE orders SET notes = :notes WHERE id = :id");
        $updateStmt->execute([':notes' => json_encode($orderNotesData), ':id' => $orderId]);
    }

    $conn->commit();

    if ($promoApplied) {
        mark_welcome_promo_used($conn, $userId);
    }

    // Clear cart for this user to avoid duplicates after direct purchase.
    try {
        $clear = $conn->prepare("DELETE ci FROM cart_items ci JOIN carts c ON ci.cart_id = c.id WHERE c.user_id = :uid");
        $clear->execute([':uid' => $userId]);
    } catch (Exception $e) {}

    // Email notifications (non-blocking)
    $orderItems = [[
        'name'     => $product['product_name'],
        'quantity' => $qty,
        'unit'     => $product['price'],
        'subtotal' => $total,
    ]];

    $orderData = [
        'order_code'     => $orderCode,
        'total_amount'   => $total,
        'payment_method' => $paymentMethod,
        'order_date'     => $orderCreatedAt,
        'status'         => 'pending',
        'items'          => $orderItems,
    ];
    $userInfo = [
        'id'    => $userId,
        'name'  => $_SESSION['user_name'] ?? 'Customer',
        'email' => $_SESSION['user_email'] ?? ''
    ];
    $digitalFlashParts = [];

    if ($isGiftCard && $redeemCode !== null) {
        $digitalFlashParts[] = 'Redeem code: ' . $redeemCode . ' (sent to ' . $deliveryEmail . ').';
        sendDigitalDelivery([
            'type'         => 'giftcard',
            'code'         => $redeemCode,
            'product_name' => $product['product_name'] ?? 'Gift Card',
            'service'      => $serviceName,
            'email'        => $deliveryEmail,
            'order_code'   => $orderCode
        ], $userInfo);
    }

    if ($isPremiumService) {
        if ($accountOption === 'new' && $generatedAccount) {
            $digitalFlashParts[] = 'New ' . $serviceName . ' account: ' . $generatedAccount['username'] . ' / ' . $generatedAccount['password'] . '.';
            sendDigitalDelivery([
                'type'       => 'premium_new',
                'service'    => $serviceName,
                'email'      => $deliveryEmail ?: $userEmail,
                'order_code' => $orderCode,
                'account'    => $generatedAccount
            ], $userInfo);
        } elseif ($accountOption === 'existing') {
            $extendMsg = 'Successfully Extend ' . $serviceName . ' account subscription.';
            $digitalFlashParts[] = $extendMsg;
            sendDigitalDelivery([
                'type'           => 'premium_extend',
                'service'        => $serviceName,
                'email'          => $deliveryEmail ?: $userEmail,
                'order_code'     => $orderCode,
                'target_email'   => $existingEmail,
                'target_phone'   => $existingPhone,
                'target_username'=> $existingUsername
            ], $userInfo);
        }
    }
    sendOrderConfirmation($orderData, $userInfo);

    $flashMsgLines = [];
    $flashMsgLines[] = 'Order created: ' . htmlspecialchars($orderCode) . '.';
    $flashMsgLines[] = 'Product: ' . htmlspecialchars($product['product_name']) . ' x ' . $qty;
    $flashMsgLines[] = 'Total: ' . number_format($total,2) . ' MMK via ' . htmlspecialchars($paymentMethod);
    $flashMsgLines[] = 'Status: pending';
    if ($promoApplied) {
        $flashMsgLines[] = 'Promo applied for ' . (int)$welcomePromo['discount'] . '% off.';
    } elseif ($promoNote) {
        $flashMsgLines[] = $promoNote;
    }
    if ($digitalFlashParts) {
        $flashMsgLines = array_merge($flashMsgLines, $digitalFlashParts);
    }
    $_SESSION['flash'] = implode("\n", array_filter($flashMsgLines));
    $_SESSION['latest_order_id'] = $orderId;
    $_SESSION['latest_order_code'] = $orderCode;
    $_SESSION['latest_order_return'] = $redirectTarget;

    $successUrl = url_for('user/order_success.php');
    $query = ['order_id' => $orderId, 'code' => $orderCode];
    if ($redirectTarget) {
        $query['return'] = $redirectTarget;
    }
    header('Location: ' . $successUrl . '?' . http_build_query($query));
    exit;
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $_SESSION['flash'] = 'Unable to place order right now.';
    header('Location: ../main/shop.php');
    exit;
}
