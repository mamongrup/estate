<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

mamon_admin_check();
header('Content-Type: application/json; charset=UTF-8');

try {
    $id      = mamon_get_int('listingId');
    $apiKey  = mamon_config('DEEPSEEK_API_KEY');
    if (!$id || $apiKey === '') throw new RuntimeException('Eksik ayar');

    $pdo  = mamon_db();
    $stmt = $pdo->prepare('SELECT title_tr,description_tr,seo_title_tr,seo_description_tr,seo_keywords_tr,slug FROM listings WHERE id=?');
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    if (!$item) throw new RuntimeException('İlan yok');

    $prompt = 'Bu Türkçe gayrimenkul ilanını en,de,ru,ar,fr dillerine doğru ve doğal çevir. Bilgi ekleme. Her dil için title, description, seoTitle (70 karakter), seoDescription (170 karakter), seoKeywords ve ASCII slug üret. Yalnızca {"translations":{"en":{"title":"","description":"","seoTitle":"","seoDescription":"","seoKeywords":"","slug":""}}} biçiminde 5 dili içeren JSON döndür. Veri: ' . json_encode($item, JSON_UNESCAPED_UNICODE);

    $payload = json_encode([
        'model'  => mamon_config('DEEPSEEK_MODEL', 'deepseek-chat'),
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'temperature' => 0.15,
        'max_tokens'  => 7000,
        'response_format' => ['type' => 'json_object'],
        'stream' => false,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://api.deepseek.com/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 90,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => $payload,
    ]);
    $r = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $translations = json_decode((string)(json_decode((string)$r, true)['choices'][0]['message']['content'] ?? ''), true)['translations'] ?? [];
    if ($status < 200 || $status >= 300) throw new RuntimeException('AI hatası');

    $pdo->beginTransaction();
    $q = $pdo->prepare(
        'INSERT INTO listing_translations(listing_id,language,title,description,seo_title,seo_description,seo_keywords,slug,ai_model)
         VALUES(?,?,?,?,?,?,?,?,?) ON CONFLICT(listing_id,language) DO UPDATE SET
         title=excluded.title,description=excluded.description,seo_title=excluded.seo_title,
         seo_description=excluded.seo_description,seo_keywords=excluded.seo_keywords,
         slug=excluded.slug,ai_model=excluded.ai_model'
    );

    $n = 0;
    foreach (['en','de','ru','ar','fr'] as $lang) {
        $t = $translations[$lang] ?? null;
        if (!is_array($t)) continue;
        $q->execute([
            $id, $lang,
            mb_substr((string)($t['title'] ?? ''), 0, 220),
            (string)($t['description'] ?? ''),
            mb_substr((string)($t['seoTitle'] ?? ''), 0, 220),
            mb_substr((string)($t['seoDescription'] ?? ''), 0, 320),
            mb_substr((string)($t['seoKeywords'] ?? ''), 0, 1000),
            mb_substr((string)($t['slug'] ?? ''), 0, 240),
            mamon_config('DEEPSEEK_MODEL', 'deepseek-chat'),
        ]);
        $n++;
    }
    $pdo->commit();

    if ($n !== 5) throw new RuntimeException('Eksik dil');
    echo json_encode(['ok' => true, 'translated' => $n]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Listing translate: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'İlan çevirileri oluşturulamadı']);
}
