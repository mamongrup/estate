<?php
declare(strict_types=1);
ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['httponly'=>true,'secure'=>true,'samesite'=>'Strict','path'=>'/']);
session_start();
header('Cache-Control: no-store');
if (($_SESSION['admin_authenticated'] ?? false) !== true) {
    http_response_code(401);
    exit('Yetkisiz');
}

function config_value(string $key): string {
    $file = '/var/www/vhosts/mamonestate.com/mamonestate-config.env';
    foreach (is_readable($file) ? file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [] as $line) {
        if (str_contains($line, '=') && !str_starts_with(trim($line), '#')) {
            [$name, $value] = explode('=', $line, 2);
            if (trim($name) === $key) return trim($value, " \t\n\r\0\x0B\"'");
        }
    }
    return '';
}

function database(): PDO {
    $url = parse_url(config_value('DATABASE_URL'));
    if (!$url) throw new RuntimeException('Veritabanı ayarı eksik.');
    return new PDO(
        sprintf('pgsql:host=%s;port=%d;dbname=%s', $url['host'], $url['port'] ?? 5432, ltrim($url['path'], '/')),
        urldecode($url['user'] ?? ''),
        urldecode($url['pass'] ?? ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}

function json_response(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function request_id(): int {
    $id = filter_var($_GET['id'] ?? $_POST['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id) json_response(['error'=>'Geçersiz ilan numarası.'], 422);
    return (int)$id;
}

try {
    $pdo = database();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'DELETE') {
        $id = request_id();
        $stmt = $pdo->prepare('DELETE FROM listings WHERE id = ? RETURNING id');
        $stmt->execute([$id]);
        if (!$stmt->fetchColumn()) json_response(['error'=>'İlan bulunamadı.'], 404);
        json_response(['ok'=>true]);
    }

    if ($method === 'GET' && isset($_GET['id'])) {
        $id = request_id();
        $stmt = $pdo->prepare("SELECT l.*, r.name AS region FROM listings l JOIN regions r ON r.id=l.region_id WHERE l.id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) json_response(['error'=>'İlan bulunamadı.'], 404);
        $details = json_decode((string)($row['details'] ?? '{}'), true) ?: [];
        json_response(['listing'=>array_merge([
            'id'=>(int)$row['id'], 'title'=>$row['title_tr'], 'description'=>$row['description_tr'],
            'region'=>$row['region'], 'type'=>$row['property_type'], 'status'=>$row['sale_type'],
            'price'=>$row['original_price'], 'priceCurrency'=>$row['price_currency'], 'rooms'=>$row['rooms'],
            'bath'=>$row['bathrooms'], 'area'=>$row['gross_area'], 'netArea'=>$row['net_area'],
            'openArea'=>$row['open_area'], 'image'=>$row['cover_image'], 'province'=>$row['province'],
            'district'=>$row['district'], 'neighborhood'=>$row['neighborhood'], 'videoUrl'=>$row['video_url'],
            'seoTitle'=>$row['seo_title_tr'], 'seoDescription'=>$row['seo_description_tr'],
            'seoKeywords'=>$row['seo_keywords_tr'], 'slug'=>$row['slug'], 'canonicalUrl'=>$row['canonical_url'],
            'contractType'=>$row['contract_type'], 'startDate'=>$row['contract_start'], 'endDate'=>$row['contract_end'],
            'contractDurationMonths'=>$row['contract_duration_months']
        ], $details)]);
    }

    if ($method === 'GET') {
        $rows = $pdo->query("SELECT l.id,l.title_tr AS title,r.name AS region,l.property_type AS type,l.sale_type AS sale_status,l.original_price AS price,l.price_currency AS currency,l.cover_image AS image,l.contract_type::text,l.contract_end,l.contract_duration_months FROM listings l JOIN regions r ON r.id=l.region_id ORDER BY l.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
        json_response(['listings'=>$rows]);
    }

    if ($method === 'POST') {
        $id = request_id();
        $title = trim((string)($_POST['title'] ?? ''));
        $region = trim((string)($_POST['region'] ?? ''));
        if ($title === '' || $region === '') json_response(['error'=>'Başlık ve bölge zorunlu.'], 422);
        $currency = (string)($_POST['priceCurrency'] ?? 'TRY');
        if (!in_array($currency, ['TRY','EUR','USD','GBP','RUB','AED'], true)) json_response(['error'=>'Para birimi geçersiz.'], 422);
        $price = (float)($_POST['price'] ?? 0);
        $rateStmt = $pdo->prepare('SELECT rate FROM exchange_rates WHERE currency=?');
        $rateStmt->execute([$currency]);
        $rate = (float)($rateStmt->fetchColumn() ?: ($currency === 'TRY' ? 1 : 0));
        if ($price < 0 || $rate <= 0) json_response(['error'=>'Fiyat veya kur bilgisi geçersiz.'], 422);
        $contract = (string)($_POST['contractType'] ?? 'unlimited');
        $start = trim((string)($_POST['startDate'] ?? '')) ?: null;
        $end = trim((string)($_POST['endDate'] ?? '')) ?: null;
        $duration = filter_var($_POST['contractDurationMonths'] ?? null, FILTER_VALIDATE_INT) ?: null;
        if ($duration || $start || $end) $contract = 'dated';
        $details = [];
        foreach (['buildingAge','floorCount','floor','heating','kitchen','parking','occupancy','deedStatus','dues','energyCertificate','propertyNumber','contactName','contactMethod'] as $key) $details[$key] = (string)($_POST[$key] ?? '');
        foreach (['furnished','creditEligible','exchangeAllowed'] as $key) $details[$key] = isset($_POST[$key]);
        foreach (['facades','interior','exterior','surroundings','transport','views','housingTypes','accessibility'] as $key) $details[$key] = array_values($_POST[$key] ?? []);
        $sql = "UPDATE listings l SET region_id=r.id,title_tr=?,description_tr=?,property_type=?,sale_type=?,price_try=?::numeric,original_price=?::numeric,price_currency=?,entry_exchange_rate=?::numeric,rooms=?,bathrooms=nullif(?,'')::smallint,gross_area=nullif(?,'')::numeric,cover_image=nullif(?,''),contract_type=?::contract_kind,contract_start=?::date,contract_end=?::date,contract_duration_months=nullif(?,'')::smallint,net_area=nullif(?,'')::numeric,open_area=nullif(?,'')::numeric,province=nullif(?,''),district=nullif(?,''),neighborhood=nullif(?,''),details=?::jsonb,video_url=nullif(?,''),seo_title_tr=nullif(?,''),seo_description_tr=nullif(?,''),seo_keywords_tr=nullif(?,''),slug=nullif(?,''),canonical_url=nullif(?,''),updated_at=now() FROM regions r WHERE l.id=? AND r.name=? RETURNING l.id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title,trim((string)($_POST['description']??'')),(string)($_POST['type']??'Villa'),(string)($_POST['status']??'Satılık'),(string)round($price/$rate,2),(string)$price,$currency,(string)$rate,(string)($_POST['rooms']??''),(string)($_POST['bath']??''),(string)($_POST['area']??''),(string)($_POST['image']??''),$contract,$start,$end,$duration,(string)($_POST['netArea']??''),(string)($_POST['openArea']??''),(string)($_POST['province']??''),(string)($_POST['district']??''),(string)($_POST['neighborhood']??''),json_encode($details,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),(string)($_POST['videoUrl']??''),(string)($_POST['seoTitle']??''),(string)($_POST['seoDescription']??''),(string)($_POST['seoKeywords']??''),(string)($_POST['slug']??''),(string)($_POST['canonicalUrl']??''),$id,$region]);
        if (!$stmt->fetchColumn()) json_response(['error'=>'İlan veya bölge bulunamadı.'], 404);
        json_response(['ok'=>true,'id'=>$id]);
    }

    json_response(['error'=>'Desteklenmeyen işlem.'], 405);
} catch (Throwable $error) {
    error_log('Admin listing action: '.$error->getMessage());
    json_response(['error'=>'İlan işlemi tamamlanamadı.'], 500);
}
