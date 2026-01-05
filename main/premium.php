<?php
// Premium Accounts page: reuse topup.php with slug preset.
if (empty($_GET['slug'])) {
    $_GET['slug'] = 'premium-accounts';
}
require __DIR__ . '/topup.php';
