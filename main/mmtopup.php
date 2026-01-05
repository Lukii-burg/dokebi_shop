<?php
// Myanmar Top Up page: preset slug to reuse topup.php form.
if (empty($_GET['slug'])) {
    $_GET['slug'] = 'mm-topup';
}
require __DIR__ . '/topup.php';
