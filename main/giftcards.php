<?php
// Gift Cards page: preset slug then reuse topup.php flow.
if (empty($_GET['slug'])) {
    $_GET['slug'] = 'gift-cards';
}
require __DIR__ . '/topup.php';
