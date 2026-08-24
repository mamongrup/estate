<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

mamon_admin_check();
header('Content-Type: application/json; charset=UTF-8');

$apiKey = mamon_config('DEEPSEEK_API_KEY');
$title  = mamon_post_string('title');
$description = mb_substr(mamon_post_string('description'), 0, 1800);
$region = mamon_post_string('region');

if ($title === '' || $apiKey === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Başlık veya API anahtarı eksik']);
    exit;
}

$prompt = "{$region} bölgesindeki {$title} adlı gayrimenkul ilanı için Türkçe SEO üret. İçerik: {$description}. Yalnızca {\"seoTitle\":\"en fazla 60 karakter\",\"seoDescription\":\"150-160 karakter\",\"seoKeywords\":\"virgülle ayrılmış 6 ifade\",\"slug\":\"turkce-ascii-url\"} JSON nesnesi döndür. Bilgi uydurma.";

$payload = json_encode([
    'model'  => mamon_config('DEEPSEEK_MODEL', 'deepseek-chat'),
    'messages' => [['role' => 'user', 'content' => $prompt]],
    'temperature' => 0.2,
    'response_format' => ['type' => 'json_object'],
    'stream' => false,
], JSON_UNESCAPED_UNICODE);

$ch = curl_init('https://api.deepseek.com/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 35,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ],
    CURLOPT_POSTFIELDS => $payload,
]);
$r = curl_exec($ch);
$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode((string)(json_decode((string)$r, true)['choices'][0]['message']['content'] ?? ''), true);

if ($status < 200 || $status >= 300 || !is_array($data)) {
    http_response_code(502);
    echo json_encode(['error' => 'SEO üretilemedi']);
    exit;
}

$data['seoTitle']       = mb_substr((string)($data['seoTitle'] ?? ''), 0, 70);
$data['seoDescription'] = mb_substr((string)($data['seoDescription'] ?? ''), 0, 170);

echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
