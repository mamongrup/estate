<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

mamon_admin_check();
header('Cache-Control: no-store');

function read_setting(PDO $pdo, string $key, array $fallback): array {
    $s = $pdo->prepare('SELECT value::text FROM site_settings WHERE key=?');
    $s->execute([$key]);
    $v = $s->fetchColumn();
    return $v ? array_merge($fallback, json_decode($v, true) ?: []) : $fallback;
}

function save_setting(PDO $pdo, string $key, array $value): void {
    $s = $pdo->prepare(
        'INSERT INTO site_settings(key,value) VALUES(?,?::jsonb) ON CONFLICT(key) DO UPDATE SET value=excluded.value,updated_at=now()'
    );
    $s->execute([$key, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
}

function update_env_config(array $changes): void {
    $configFile = MAMON_CONFIG_FILE;
    $lines = is_readable($configFile) ? file($configFile, FILE_IGNORE_NEW_LINES) : [];
    $done = [];

    foreach ($lines as &$line) {
        if (!str_contains($line, '=') || str_starts_with(trim($line), '#')) continue;
        [$key] = explode('=', $line, 2);
        $key = trim($key);
        if (array_key_exists($key, $changes)) {
            $line = $key . '=' . $changes[$key];
            $done[$key] = true;
        }
    }
    unset($line);

    foreach ($changes as $key => $value) {
        if (!isset($done[$key])) {
            $lines[] = $key . '=' . $value;
        }
    }

    $tmp = $configFile . '.tmp';
    if (file_put_contents($tmp, implode(PHP_EOL, $lines) . PHP_EOL, LOCK_EX) === false
        || !rename($tmp, $configFile)) {
        throw new RuntimeException('Yapılandırma yazılamadı.');
    }
    chmod($configFile, 0600);
}

try {
    $pdo = mamon_db();

    $siteDefaults = [
        'siteName' => 'Mamon Estate', 'phone' => '0533 057 79 13',
        'email' => 'info@mamonestate.com', 'whatsapp' => '0533 057 79 13',
        'address' => 'Antalya, Türkiye', 'defaultLanguage' => 'tr', 'defaultCurrency' => 'TRY',
    ];
    $seoDefaults = [
        'title' => 'Akdeniz & Ege Satılık Villa ve Daire | Mamon Estate',
        'description' => 'Akdeniz ve Ege seçkin gayrimenkul portföyü.',
        'keywords' => 'akdeniz emlak, ege villa', 'canonical' => 'https://mamonestate.com/',
        'socialImage' => '', 'indexing' => '1', 'sitemap' => '1',
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        mamon_json([
            'site'      => read_setting($pdo, 'site', $siteDefaults),
            'seo'       => read_setting($pdo, 'seo', $seoDefaults),
            'deepseek'  => array_merge(
                read_setting($pdo, 'deepseek', ['temperature' => '0.7', 'systemPrompt' => 'Mamon Estate çok dilli gayrimenkul danışmanısın.']),
                [
                    'model'             => mamon_config('DEEPSEEK_MODEL', 'deepseek-chat'),
                    'apiKeyConfigured'  => !empty(mamon_config('DEEPSEEK_API_KEY')),
                ]
            ),
        ]);
    }

    // POST
    if (!mamon_csrf_verify()) {
        mamon_json(['error' => 'CSRF doğrulaması başarısız.'], 403);
    }

    $type = mamon_post_string('type');
    $data = $_POST;
    unset($data['type']);

    if ($type === 'site') {
        save_setting($pdo, 'site', $data);
    } elseif ($type === 'seo') {
        if (!isset($data['indexing'])) $data['indexing'] = '0';
        if (!isset($data['sitemap']))  $data['sitemap']  = '0';
        save_setting($pdo, 'seo', $data);
    } elseif ($type === 'deepseek') {
        $key   = mamon_post_string('apiKey');
        $model = in_array($data['model'] ?? '', ['deepseek-chat', 'deepseek-reasoner'], true)
            ? $data['model'] : 'deepseek-chat';

        $changes = ['DEEPSEEK_MODEL' => $model];
        if ($key !== '') {
            $changes['DEEPSEEK_API_KEY'] = str_replace(["\r", "\n"], '', $key);
        }
        update_env_config($changes);

        unset($data['apiKey'], $data['model']);
        save_setting($pdo, 'deepseek', $data);
    } else {
        throw new RuntimeException('Geçersiz grup.');
    }

    mamon_json(['ok' => true]);
} catch (Throwable $e) {
    error_log('Admin settings: ' . $e->getMessage());
    http_response_code(500);
    mamon_json(['error' => 'Ayarlar kaydedilemedi.']);
}
