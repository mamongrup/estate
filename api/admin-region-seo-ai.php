<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

mamon_admin_check();
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$apiKey     = mamon_config('DEEPSEEK_API_KEY');
$name       = mamon_post_string('name');
$province   = mamon_post_string('province');
$description = mb_substr(mamon_post_string('description'), 0, 1500);

if ($name === '' || $apiKey === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Bölge adı veya DeepSeek anahtarı eksik.']);
    exit;
}

$prompt = "Türkiye'de {$province} ilindeki {$name} bölgesinin emlak sayfası için Türkçe SEO içeriği hazırla. Açıklama bağlamı: {$description}. seoTitle en fazla 60 karakter, seoDescription 150-160 karakter, keywords virgülle ayrılmış 6 gerçek arama ifadesi olmalı. Yanıltıcı bilgi ve garanti kullanma. Yalnızca şu JSON nesnesini döndür: {\"seoTitle\":\"\",\"seoDescription\":\"\",\"keywords\":\"\"}.";

$payload = json_encode([
    'model'  => mamon_config('DEEPSEEK_MODEL', 'deepseek-chat'),
    'messages' => [['role' => 'user', 'content' => $prompt]],
    'temperature' => 0.25,
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
$response = curl_exec($ch);
$status   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($status < 200 || $status >= 300 || $response === false) {
    http_response_code(502);
    echo json_encode(['error' => 'DeepSeek yanıt vermedi.']);
    exit;
}

$content = (string)(json_decode($response, true)['choices'][0]['message']['content'] ?? '');
$seo     = json_decode($content, true);
if (!is_array($seo)) {
    http_response_code(502);
    echo json_encode(['error' => 'SEO içeriği çözümlenemedi.']);
    exit;
}

$seo['seoTitle']       = mb_substr(trim((string)($seo['seoTitle'] ?? '')), 0, 70);
$seo['seoDescription'] = mb_substr(trim((string)($seo['seoDescription'] ?? '')), 0, 170);
$seo['keywords']       = mb_substr(trim((string)($seo['keywords'] ?? '')), 0, 500);

echo json_encode($seo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
