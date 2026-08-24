<?php
declare(strict_types=1);

/* ──────────────────────────────────────────────
 *  Mamon Estate — Centralized PHP Configuration
 *  Include this file at the top of every PHP endpoint:
 *    require __DIR__ . '/config.php';
 * ────────────────────────────────────────────── */

/* ── Session hardening (idempotent) ─────────── */
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'httponly'  => true,
        'secure'   => true,
        'samesite' => 'Lax',
        'path'     => '/',
    ]);
    session_start();
}

/* ── Config file reader ─────────────────────── */
const MAMON_CONFIG_FILE = '/var/www/vhosts/mamonestate.com/mamonestate-config.env';

function mamon_config(string $key, string $default = ''): string
{
    // 1. Environment variable (Docker / systemd)
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }

    // 2. Plesk config file
    if (is_readable(MAMON_CONFIG_FILE)) {
        foreach (file(MAMON_CONFIG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            if (trim($k) === $key) {
                return trim($v, " \t\n\r\0\x0B\"'");
            }
        }
    }

    return $default;
}

/* ── Database connection (singleton) ─────────── */
function mamon_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $url = mamon_config('DATABASE_URL');
    if ($url === '') {
        throw new RuntimeException('DATABASE_URL is not configured.');
    }

    $parts = parse_url($url);
    if (!$parts || ($parts['scheme'] ?? '') !== 'postgresql') {
        throw new RuntimeException('DATABASE_URL must be a postgresql:// URL.');
    }

    $dsn = sprintf(
        'pgsql:host=%s;port=%d;dbname=%s',
        $parts['host'],
        $parts['port'] ?? 5432,
        ltrim($parts['path'] ?? '', '/')
    );

    $pdo = new PDO(
        $dsn,
        urldecode($parts['user'] ?? ''),
        urldecode($parts['pass'] ?? ''),
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );

    return $pdo;
}

/* ── HTML escaping ───────────────────────────── */
function mamon_esc(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ── Slug generator ──────────────────────────── */
function mamon_slug(string $text): string
{
    // Transliterate Turkish chars first
    $text = strtr($text, [
        'ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u',
        'Ç' => 'C', 'Ğ' => 'G', 'İ' => 'I', 'Ö' => 'O', 'Ş' => 'S', 'Ü' => 'U',
    ]);
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower($text)) ?: $text;
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim((string)$text, '-');
}

/* ── CSRF protection ─────────────────────────── */
function mamon_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function mamon_csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . mamon_esc(mamon_csrf_token()) . '">';
}

function mamon_csrf_verify(): bool
{
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($token === '' || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/* ── JSON response helper ────────────────────── */
function mamon_json(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, private');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ── Admin auth check ────────────────────────── */
function mamon_admin_check(): void
{
    if (($_SESSION['admin_authenticated'] ?? false) !== true) {
        http_response_code(401);
        header('Content-Type: text/html; charset=UTF-8');
        exit('Yetkisiz');
    }
}

/* ── Input helpers ───────────────────────────── */
function mamon_post_string(string $key, string $default = ''): string
{
    return trim((string)($_POST[$key] ?? $default));
}

function mamon_get_int(string $key, int $default = 0): int
{
    return filter_var($_GET[$key] ?? $_POST[$key] ?? null, FILTER_VALIDATE_INT) ?: $default;
}
