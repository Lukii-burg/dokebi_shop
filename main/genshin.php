<?php
// Genshin Impact entry point: force the slug so the top-up page loads with the right fields and description.
if (empty($_GET['slug'])) {
    $_GET['slug'] = 'genshin-impact';
}
require __DIR__ . '/topup.php';
