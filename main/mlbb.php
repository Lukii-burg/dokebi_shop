<?php
// Mobile Legends Diamonds entry point: force the MLBB slug so users land on the right selling page.
if (empty($_GET['slug'])) {
    $_GET['slug'] = 'mlbb-diamonds';
}
require __DIR__ . '/topup.php';
