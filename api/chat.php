<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Yalnızca POST isteği desteklenir.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$apiKey = mamon_config('DEEPSEEK_API_KEY');
if ($apiKey === '') {
    http_response_code(503);
    echo json_encode(['error' => 'DeepSeek API anahtarı henüz yapılandırılmadı.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Load AI settings from DB
$aiSettings = ['temperature' => 0.35, 'systemPrompt' => ''];
try {
    $pdo   = mamon_db();
    $value = $pdo->query("SELECT value::text FROM site_settings WHERE key='deepseek'")->fetchColumn();
    if ($value) $aiSettings = array_merge($aiSettings, json_decode($value, true) ?: []);
} catch (Throwable $e) {
    error_log('DeepSeek settings load: ' . $e->getMessage());
}

$input            = json_decode((string)file_get_contents('php://input'), true);
$messages         = is_array($input['messages'] ?? null) ? array_slice($input['messages'], -12) : [];
$listings         = is_array($input['listings'] ?? null) ? array_slice($input['listings'], 0, 30) : [];
$requestedLanguage = preg_replace('/[^a-z-]/i', '', (string)($input['language'] ?? 'tr'));

$languageNames = ['tr' => 'Türkçe', 'en' => 'English', 'de' => 'Deutsch', 'ru' => 'Русский', 'ar' => 'العربية', 'fr' => 'Français'];
$language      = array_key_exists($requestedLanguage, $languageNames) ? $requestedLanguage : 'tr';
$languageName  = $languageNames[$language];

if (!$messages || mb_strlen((string)($messages[count($messages) - 1]['content'] ?? '')) > 1000) {
    http_response_code(422);
    echo json_encode(['error' => 'Geçerli bir mesaj gönderin.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$systemPrompt = trim((string)$aiSettings['systemPrompt']);
$system      = $systemPrompt . "\nSen Mamon Estate'in yardımcı yapay zekâ danışmanısın. "
    . "Varsayılan yanıt dilin {$languageName} ({$language}) olmalıdır. "
    . "Kullanıcı son mesajında açıkça başka bir dil kullanırsa o dilde cevap ver. Dil karıştırma. "
    . "Özel isimleri ve para birimlerini aynen koru. Kısa, sıcak ve profesyonel yanıt ver. "
    . "Yalnızca verilen portföyü öner; bilgi uydurma. Kesin hukuki veya finansal tavsiye verme. "
    . "Portföy JSON: " . json_encode($listings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$payload = json_encode([
    'model'  => mamon_config('DEEPSEEK_MODEL', 'deepseek-chat'),
    'messages' => array_merge([['role' => 'system', 'content' => $system]], $messages),
    'temperature' => max(0, min(2, (float)$aiSettings['temperature'])),
    'max_tokens'  => 500,
    'stream'      => false,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$ch = curl_init('https://api.deepseek.com/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 35,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ],
    CURLOPT_POSTFIELDS => $payload,
]);
$response = curl_exec($ch);
$status   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error    = curl_error($ch);
curl_close($ch);

if ($response === false || $status < 200 || $status >= 300) {
    error_log('DeepSeek API error: ' . $status . ' ' . $error);
    http_response_code(502);
    echo json_encode(['error' => 'Yapay zekâ servisine şu anda ulaşılamıyor.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$decoded = json_decode($response, true);
$reply   = trim((string)($decoded['choices'][0]['message']['content'] ?? ''));

if ($reply === '') {
    http_response_code(502);
    echo json_encode(['error' => 'Yapay zekâ boş yanıt döndürdü.'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['reply' => $reply], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
