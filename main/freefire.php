<?php
// Free Fire Diamonds entry point: force the Free Fire slug so users land on the correct selling page.
if (empty($_GET['slug'])) {
    $_GET['slug'] = 'freefire-diamonds';
}
require __DIR__ . '/topup.php';
