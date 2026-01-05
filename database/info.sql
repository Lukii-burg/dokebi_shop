INSERT INTO users (name, username, email, password, role)
VALUES ('Test User', 'test1', 'test@dokebi.com', '1234', 'user');

INSERT INTO orders (user_id, order_code, total_amount, payment_method, payment_status, order_status)
VALUES (1, 'ORDER12345', 15000, 'KBZPay', 'paid', 'completed');

INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, subtotal)
VALUES
(1, 1, 'MLBB 86 Diamonds', 1, 1500, 1500),
(1, 2, 'PUBG 60 UC', 1, 1200, 1200);

INSERT INTO payments (order_id, amount, method, status, transaction_ref, paid_at)
VALUES (1, 15000, 'KBZPay', 'success', 'TXN123456', NOW());