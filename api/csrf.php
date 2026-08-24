<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

// Returns a fresh CSRF token for public forms
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

mamon_json(['token' => mamon_csrf_token()]);
