<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

mamon_admin_check();
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');

function upload_file(array $file, array $allowed, int $max, string $prefix): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($file['error'] ?? 1) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > $max) {
        throw new RuntimeException('Dosya yüklenemedi veya boyutu fazla.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Desteklenmeyen dosya türü: ' . $mime);
    }
    $dir = dirname(__DIR__) . '/uploads/listings';
    if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
        throw new RuntimeException('Yükleme klasörü oluşturulamadı.');
    }
    $name = $prefix . '-' . bin2hex(random_bytes(10)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('Dosya kaydedilemedi.');
    }
    return '/uploads/listings/' . $name;
}

try {
    $pdo = mamon_db();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!mamon_csrf_verify()) {
            http_response_code(403);
            exit('CSRF doğrulaması başarısız.');
        }

        $title  = mamon_post_string('title');
        $region = mamon_post_string('region');
        if ($title === '' || $region === '') {
            http_response_code(422);
            exit('Başlık ve bölge zorunlu.');
        }

        $slug = mamon_slug(mamon_post_string('slug') ?: $title) . '-' . substr(bin2hex(random_bytes(3)), 0, 6);

        $contract  = mamon_post_string('contractType', 'dated');
        $start     = mamon_post_string('startDate') ?: null;
        $end       = mamon_post_string('endDate') ?: null;
        $duration  = filter_var($_POST['contractDurationMonths'] ?? null, FILTER_VALIDATE_INT) ?: null;
        if ($duration || $start || $end) $contract = 'dated';

        $currency = mamon_post_string('priceCurrency', 'TRY');
        if (!in_array($currency, ['TRY','EUR','USD','GBP','RUB','AED'], true)) {
            http_response_code(422);
            exit('Para birimi geçersiz.');
        }

        $priceAmount = (float)($_POST['price'] ?? 0);
        if ($priceAmount < 0) {
            http_response_code(422);
            exit('Fiyat geçersiz.');
        }

        $rateQuery = $pdo->prepare('SELECT rate FROM exchange_rates WHERE currency=?');
        $rateQuery->execute([$currency]);
        $entryRate = (float)($rateQuery->fetchColumn() ?: ($currency === 'TRY' ? 1 : 0));
        if ($entryRate <= 0) {
            http_response_code(422);
            exit('Seçilen para biriminin güncel kuru bulunamadı.');
        }
        $priceTry = round($priceAmount / $entryRate, 2);

        // Gallery uploads
        $gallery = [];
        $files = $_FILES['images'] ?? null;
        if ($files && is_array($files['name'] ?? null)) {
            foreach (array_slice(array_keys($files['name']), 0, 20) as $i) {
                $uploaded = upload_file(
                    ['tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]],
                    ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
                    12 * 1024 * 1024,
                    'image'
                );
                if ($uploaded) $gallery[] = $uploaded;
            }
        }

        // Video
        $video = mamon_post_string('videoUrl');
        if (isset($_FILES['videoFile'])) {
            $video = upload_file(
                $_FILES['videoFile'],
                ['video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'video/webm' => 'webm'],
                100 * 1024 * 1024,
                'video'
            ) ?: $video;
        }

        // Details JSON
        $details = [];
        foreach (['buildingAge','floorCount','floor','heating','kitchen','parking','occupancy','deedStatus','dues','energyCertificate','propertyNumber'] as $k) {
            $details[$k] = mamon_post_string($k);
        }
        foreach (['furnished','creditEligible','exchangeAllowed'] as $k) {
            $details[$k] = isset($_POST[$k]);
        }
        foreach (['facades','interior','exterior','surroundings','transport'] as $k) {
            $details[$k] = array_values($_POST[$k] ?? []);
        }

        $sql = "INSERT INTO listings(region_id,title_tr,description_tr,property_type,sale_type,price_try,original_price,price_currency,entry_exchange_rate,rooms,bathrooms,gross_area,cover_image,status,contract_type,contract_start,contract_end,contract_duration_months,net_area,open_area,province,district,neighborhood,details,gallery,video_url,seo_title_tr,seo_description_tr,seo_keywords_tr,slug,canonical_url)
                SELECT r.id,?,?,?,?,?::numeric,?::numeric,?,?::numeric,?,nullif(?,'')::smallint,nullif(?,'')::numeric,nullif(?,''),'published',?::contract_kind,?::date,?::date,nullif(?,'')::smallint,nullif(?,'')::numeric,nullif(?,'')::numeric,nullif(?,''),nullif(?,''),nullif(?,''),?::jsonb,?::jsonb,nullif(?,''),nullif(?,''),nullif(?,''),nullif(?,''),?,nullif(?,'')
                FROM regions r WHERE r.name=? RETURNING id";

        $s = $pdo->prepare($sql);
        $s->execute([
            $title, mamon_post_string('description'), mamon_post_string('type', 'Villa'), mamon_post_string('status', 'Satılık'),
            (string)$priceTry, (string)$priceAmount, $currency, (string)$entryRate,
            mamon_post_string('rooms'), mamon_post_string('bath'), mamon_post_string('area'),
            mamon_post_string('image'), $contract, $start, $end, $duration,
            mamon_post_string('netArea'), mamon_post_string('openArea'),
            mamon_post_string('province'), mamon_post_string('district'), mamon_post_string('neighborhood'),
            json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($gallery, JSON_UNESCAPED_SLASHES),
            $video,
            mamon_post_string('seoTitle'), mamon_post_string('seoDescription'), mamon_post_string('seoKeywords'),
            $slug, mamon_post_string('canonicalUrl'),
            $region,
        ]);

        $id = $s->fetchColumn();
        if (!$id) {
            http_response_code(422);
            exit('Bölge bulunamadı.');
        }
        header('X-Listing-Id: ' . $id);
    }

    // Render listing list
    $rows = $pdo->query(
        "SELECT l.id,l.title_tr,r.name AS region,l.status::text
         FROM listings l JOIN regions r ON r.id=l.region_id
         ORDER BY l.created_at DESC LIMIT 50"
    )->fetchAll();

    foreach ($rows as $row) {
        echo '<div class="mini-listing"><div><b>' . mamon_esc($row['title_tr']) . '</b><small>'
            . mamon_esc($row['region']) . ' · ' . mamon_esc($row['status']) . '</small></div><strong>MV-'
            . (int)$row['id'] . '</strong></div>';
    }
} catch (Throwable $e) {
    error_log('Admin listings: ' . $e->getMessage());
    http_response_code(500);
    echo 'İlanlar yüklenemedi.';
}
