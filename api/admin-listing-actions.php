<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

mamon_admin_check();
header('Cache-Control: no-store');

try {
    $pdo    = mamon_db();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // DELETE — delete a listing
    if ($method === 'DELETE') {
        $id   = mamon_get_int('id');
        if (!$id) mamon_json(['error' => 'Geçersiz ilan numarası.'], 422);

        $stmt = $pdo->prepare('DELETE FROM listings WHERE id=? RETURNING id');
        $stmt->execute([$id]);
        if (!$stmt->fetchColumn()) mamon_json(['error' => 'İlan bulunamadı.'], 404);
        mamon_json(['ok' => true]);
    }

    // GET with ?id= — single listing detail
    if ($method === 'GET' && isset($_GET['id'])) {
        $id = mamon_get_int('id');
        if (!$id) mamon_json(['error' => 'Geçersiz ilan numarası.'], 422);

        $stmt = $pdo->prepare(
            "SELECT l.*, r.name AS region FROM listings l JOIN regions r ON r.id=l.region_id WHERE l.id=?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) mamon_json(['error' => 'İlan bulunamadı.'], 404);

        $details = json_decode((string)($row['details'] ?? '{}'), true) ?: [];
        mamon_json(['listing' => array_merge([
            'id' => (int)$row['id'], 'title' => $row['title_tr'], 'description' => $row['description_tr'],
            'region' => $row['region'], 'type' => $row['property_type'], 'status' => $row['sale_type'],
            'price' => $row['original_price'], 'priceCurrency' => $row['price_currency'],
            'rooms' => $row['rooms'], 'bath' => $row['bathrooms'], 'area' => $row['gross_area'],
            'netArea' => $row['net_area'], 'openArea' => $row['open_area'],
            'image' => $row['cover_image'], 'province' => $row['province'],
            'district' => $row['district'], 'neighborhood' => $row['neighborhood'],
            'videoUrl' => $row['video_url'],
            'seoTitle' => $row['seo_title_tr'], 'seoDescription' => $row['seo_description_tr'],
            'seoKeywords' => $row['seo_keywords_tr'], 'slug' => $row['slug'],
            'canonicalUrl' => $row['canonical_url'],
            'contractType' => $row['contract_type'], 'startDate' => $row['contract_start'],
            'endDate' => $row['contract_end'], 'contractDurationMonths' => $row['contract_duration_months'],
        ], $details)]);
    }

    // GET all listings
    if ($method === 'GET') {
        $rows = $pdo->query(
            "SELECT l.id,l.title_tr AS title,r.name AS region,l.property_type AS type,
                    l.sale_type AS sale_status,l.original_price AS price,l.price_currency AS currency,
                    l.cover_image AS image,l.contract_type::text,l.contract_end,l.contract_duration_months
             FROM listings l JOIN regions r ON r.id=l.region_id ORDER BY l.created_at DESC"
        )->fetchAll();
        mamon_json(['listings' => $rows]);
    }

    // POST — update listing
    if ($method === 'POST') {
        if (!mamon_csrf_verify()) mamon_json(['error' => 'CSRF hatası.'], 403);

        $id     = mamon_get_int('id');
        $title  = mamon_post_string('title');
        $region = mamon_post_string('region');
        if (!$id || $title === '' || $region === '') {
            mamon_json(['error' => 'Başlık ve bölge zorunlu.'], 422);
        }

        $currency = mamon_post_string('priceCurrency', 'TRY');
        if (!in_array($currency, ['TRY','EUR','USD','GBP','RUB','AED'], true)) {
            mamon_json(['error' => 'Para birimi geçersiz.'], 422);
        }

        $price = (float)($_POST['price'] ?? 0);
        $rateStmt = $pdo->prepare('SELECT rate FROM exchange_rates WHERE currency=?');
        $rateStmt->execute([$currency]);
        $rate = (float)($rateStmt->fetchColumn() ?: ($currency === 'TRY' ? 1 : 0));
        if ($price < 0 || $rate <= 0) {
            mamon_json(['error' => 'Fiyat veya kur bilgisi geçersiz.'], 422);
        }

        $contract = mamon_post_string('contractType', 'unlimited');
        $start    = mamon_post_string('startDate') ?: null;
        $end      = mamon_post_string('endDate') ?: null;
        $duration = filter_var($_POST['contractDurationMonths'] ?? null, FILTER_VALIDATE_INT) ?: null;
        if ($duration || $start || $end) $contract = 'dated';

        $details = [];
        foreach (['buildingAge','floorCount','floor','heating','kitchen','parking','occupancy',
                  'deedStatus','dues','energyCertificate','propertyNumber','contactName','contactMethod'] as $k) {
            $details[$k] = mamon_post_string($k);
        }
        foreach (['furnished','creditEligible','exchangeAllowed'] as $k) {
            $details[$k] = isset($_POST[$k]);
        }
        foreach (['facades','interior','exterior','surroundings','transport','views','housingTypes','accessibility'] as $k) {
            $details[$k] = array_values($_POST[$k] ?? []);
        }

        $sql = "UPDATE listings l SET region_id=r.id,title_tr=?,description_tr=?,property_type=?,sale_type=?,
                price_try=?::numeric,original_price=?::numeric,price_currency=?,entry_exchange_rate=?::numeric,
                rooms=?,bathrooms=nullif(?,'')::smallint,gross_area=nullif(?,'')::numeric,
                cover_image=nullif(?,''),contract_type=?::contract_kind,contract_start=?::date,
                contract_end=?::date,contract_duration_months=nullif(?,'')::smallint,
                net_area=nullif(?,'')::numeric,open_area=nullif(?,'')::numeric,
                province=nullif(?,''),district=nullif(?,''),neighborhood=nullif(?,''),
                details=?::jsonb,video_url=nullif(?,''),
                seo_title_tr=nullif(?,''),seo_description_tr=nullif(?,''),seo_keywords_tr=nullif(?,''),
                slug=nullif(?,''),canonical_url=nullif(?,''),updated_at=now()
                FROM regions r WHERE l.id=? AND r.name=? RETURNING l.id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $title, mamon_post_string('description'), mamon_post_string('type', 'Villa'),
            mamon_post_string('status', 'Satılık'),
            (string)round($price / $rate, 2), (string)$price, $currency, (string)$rate,
            mamon_post_string('rooms'), mamon_post_string('bath'), mamon_post_string('area'),
            mamon_post_string('image'), $contract, $start, $end, $duration,
            mamon_post_string('netArea'), mamon_post_string('openArea'),
            mamon_post_string('province'), mamon_post_string('district'), mamon_post_string('neighborhood'),
            json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            mamon_post_string('videoUrl'),
            mamon_post_string('seoTitle'), mamon_post_string('seoDescription'), mamon_post_string('seoKeywords'),
            mamon_post_string('slug'), mamon_post_string('canonicalUrl'),
            $id, $region,
        ]);

        if (!$stmt->fetchColumn()) mamon_json(['error' => 'İlan veya bölge bulunamadı.'], 404);
        mamon_json(['ok' => true, 'id' => $id]);
    }

    mamon_json(['error' => 'Desteklenmeyen işlem.'], 405);
} catch (Throwable $e) {
    error_log('Admin listing action: ' . $e->getMessage());
    http_response_code(500);
    mamon_json(['error' => 'İlan işlemi tamamlanamadı.'], 500);
}
