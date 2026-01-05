<?php
// Valorant Points page: preset slug then reuse topup.php flow.
if (empty($_GET['slug'])) {
    $_GET['slug'] = 'valorant-points';
}
require __DIR__ . '/topup.php';
