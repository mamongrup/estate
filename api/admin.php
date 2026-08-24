<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

mamon_admin_check();

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, private');

// Inject CSRF token as meta tag into admin.html
$html = file_get_contents(dirname(__DIR__) . '/admin.html');
$csrfToken = mamon_csrf_token();
$html = str_replace(
    '<meta charset="UTF-8">',
    '<meta charset="UTF-8"><meta name="csrf-token" content="' . $csrfToken . '">',
    $html
);
echo $html;
