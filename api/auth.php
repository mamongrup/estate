<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

function auth_redirect(string $path): never {
    header('Location: ' . $path, true, 303);
    exit;
}

$action = $_GET['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    auth_redirect('/uye-girisi');
}

// CSRF check for auth forms
if (!mamon_csrf_verify()) {
    auth_redirect('/uye-ol?durum=csrf-hatasi');
}

try {
    $pdo = mamon_db();

    if ($action === 'register') {
        $name     = mamon_post_string('name');
        $email    = mb_strtolower(mamon_post_string('email'));
        $password = mamon_post_string('password');
        $phone    = mamon_post_string('phone');

        if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            auth_redirect('/uye-ol?durum=gecersiz');
        }

        $pdo->prepare('INSERT INTO users(name,email,phone,password_hash) VALUES(?,?,?,?)')
            ->execute([$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT)]);

        auth_redirect('/uye-girisi?durum=kayit-basarili');
    }

    if ($action === 'login') {
        $email    = mb_strtolower(mamon_post_string('email'));
        $password = mamon_post_string('password');

        $stmt = $pdo->prepare("SELECT id,name,password_hash,role::text FROM users WHERE lower(email)=? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            auth_redirect('/uye-girisi?durum=hata');
        }

        session_regenerate_id(true);
        $_SESSION = [
            'user_id'   => $user['id'],
            'user_name' => $user['name'],
            'user_role' => $user['role'],
        ];
        auth_redirect('/?giris=basarili');
    }

    if ($action === 'forgot-password') {
        $email = mb_strtolower(mamon_post_string('email'));

        $stmt = $pdo->prepare('SELECT id FROM users WHERE lower(email)=? LIMIT 1');
        $stmt->execute([$email]);
        $userId = $stmt->fetchColumn();

        if ($userId) {
            $token  = bin2hex(random_bytes(32));
            $hash   = hash('sha256', $token);
            $siteUrl = rtrim(mamon_config('SITE_URL', 'https://mamonestate.com'), '/');

            $pdo->prepare("INSERT INTO password_reset_tokens(user_id,token_hash,expires_at) VALUES(?,?,now()+interval '1 hour')")
                ->execute([$userId, $hash]);

            $resetLink = $siteUrl . '/sifre-yenile?token=' . $token;
            @mail(
                $email,
                'Mamon Estate şifre sıfırlama',
                "Merhaba,\n\nŞifrenizi sıfırlamak için aşağıdaki bağlantıyı kullanın:\n{$resetLink}\n\nBu bağlantı 1 saat geçerlidir.\n\nSaygılarımızla,\nMamon Estate",
                "From: info@mamonestate.com\r\nContent-Type: text/plain; charset=UTF-8"
            );
        }
        auth_redirect('/sifremi-unuttum?durum=gonderildi');
    }

    if ($action === 'reset') {
        $token    = mamon_post_string('token');
        $password = mamon_post_string('password');

        if (strlen($token) !== 64 || strlen($password) < 8) {
            auth_redirect('/sifre-yenile?durum=gecersiz');
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "SELECT id,user_id FROM password_reset_tokens WHERE token_hash=? AND used_at IS NULL AND expires_at>now() FOR UPDATE"
        );
        $stmt->execute([hash('sha256', $token)]);
        $row = $stmt->fetch();

        if (!$row) {
            $pdo->rollBack();
            auth_redirect('/sifre-yenile?durum=gecersiz');
        }

        $pdo->prepare('UPDATE users SET password_hash=?,updated_at=now() WHERE id=?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $row['user_id']]);

        $pdo->prepare('UPDATE password_reset_tokens SET used_at=now() WHERE id=?')
            ->execute([$row['id']]);

        $pdo->commit();
        auth_redirect('/uye-girisi?durum=sifre-yenilendi');
    }

    auth_redirect('/uye-girisi');
} catch (Throwable $e) {
    error_log('Auth error: ' . $e->getMessage());
    auth_redirect('/uye-girisi?durum=sistem-hatasi');
}
