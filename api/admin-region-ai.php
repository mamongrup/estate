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

$apiKey   = mamon_config('DEEPSEEK_API_KEY');
$name     = mamon_post_string('name');
$province = mamon_post_string('province');

if ($name === '' || $apiKey === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Bölge adı veya DeepSeek anahtarı eksik.']);
    exit;
}

$prompt = "{$province} ilindeki {$name} bölgesi için emlak sitesi ziyaretçilerine uygun 6 gerçek cazibe noktası yaz. Her maddede yer adı ve en fazla 12 kelimelik kısa açıklama olsun. Yalnızca JSON dizi döndür, markdown kullanma. Bilgi uydurma.";

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
$decoded = json_decode($content, true);
$items   = $decoded['attractions'] ?? $decoded['items'] ?? $decoded;

if (!is_array($items)) {
    http_response_code(502);
    echo json_encode(['error' => 'Cazibe noktaları çözümlenemedi.']);
    exit;
}

$lines = [];
foreach (array_slice($items, 0, 8) as $item) {
    if (is_string($item)) {
        $lines[] = $item;
    } elseif (is_array($item)) {
        $lines[] = trim((string)($item['name'] ?? $item['title'] ?? ''))
            . ' — ' . trim((string)($item['description'] ?? ''));
    }
}

echo json_encode(['attractions' => $lines], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
