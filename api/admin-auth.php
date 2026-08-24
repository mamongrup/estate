<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

function admin_redirect(string $path): never {
    header('Location: ' . $path, true, 303);
    exit;
}

$action = $_GET['action'] ?? '';

// Logout
if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    admin_redirect('/admin-giris');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    admin_redirect('/admin-giris');
}

// Rate limiting: max 5 attempts per 5 minutes
$now      = time();
$attempts = $_SESSION['admin_attempts'] ?? [];
$attempts = array_values(array_filter($attempts, fn($t) => $t > $now - 300));

if (count($attempts) >= 5) {
    admin_redirect('/admin-giris?hata=bekleyin');
}

$username = mamon_post_string('username');
$password = mamon_post_string('password');

$expectedUser     = mamon_config('ADMIN_USER');
$expectedPassword = mamon_config('ADMIN_PASSWORD');

if ($expectedUser === '' || $expectedPassword === ''
    || !hash_equals($expectedUser, $username)
    || !hash_equals($expectedPassword, $password)
) {
    $_SESSION['admin_attempts'] = [...$attempts, $now];
    admin_redirect('/admin-giris?hata=bilgiler');
}

// Success
session_regenerate_id(true);
$_SESSION['admin_authenticated'] = true;
$_SESSION['admin_login_at']      = $now;
$_SESSION['admin_attempts']      = [];
admin_redirect('/admin');
