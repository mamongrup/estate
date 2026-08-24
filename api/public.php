<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

/**
 * Public API for Mamon Estate frontend
 * Endpoints:
 *   GET /api/public/listings           — published listings (with pagination)
 *   GET /api/public/listings?id=N      — single listing detail
 *   GET /api/public/listings?search=q  — full-text search (pg_trgm)
 *   GET /api/public/regions            — all regions
 *   POST /api/public/contact           — contact form
 */

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: public, max-age=60');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? 'listings';

try {
    $pdo = mamon_db();

    /* ── Listings ────────────────────────────── */
    if ($action === 'listings') {

        // Single listing
        $id = mamon_get_int('id');
        if ($id) {
            $stmt = $pdo->prepare(
                "SELECT l.id, l.title_tr AS title, l.description_tr AS description,
                        l.property_type AS type, l.sale_type AS status,
                        l.original_price AS price, l.price_currency AS currency,
                        l.rooms, l.bathrooms AS bath, l.gross_area AS area,
                        l.net_area AS netArea, l.open_area AS openArea,
                        l.cover_image AS image, l.gallery, l.video_url AS videoUrl,
                        l.province, l.district, l.neighborhood,
                        l.details, l.featured,
                        l.seo_title_tr AS seoTitle, l.seo_description_tr AS seoDescription,
                        l.seo_keywords_tr AS seoKeywords, l.slug, l.canonical_url AS canonicalUrl,
                        l.contract_type AS contractType,
                        l.contract_start AS startDate, l.contract_end AS endDate,
                        l.contract_duration_months AS contractDurationMonths,
                        r.name AS region
                 FROM listings l JOIN regions r ON r.id = l.region_id
                 WHERE l.id = ? AND l.status = 'published'
                   AND (l.contract_end IS NULL OR l.contract_end >= CURRENT_DATE)"
            );
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) mamon_json(['error' => 'İlan bulunamadı.'], 404);

            $details = json_decode((string)($row['details'] ?? '{}'), true) ?: [];
            $gallery = json_decode((string)($row['gallery'] ?? '[]'), true) ?: [];

            mamon_json(['listing' => array_merge([
                'id'          => (int)$row['id'],
                'title'       => $row['title'],
                'description' => $row['description'],
                'type'        => $row['type'],
                'status'      => $row['status'],
                'price'       => $row['price'],
                'currency'    => $row['currency'],
                'rooms'       => $row['rooms'],
                'bath'        => $row['bath'],
                'area'        => $row['area'],
                'netArea'     => $row['netArea'],
                'openArea'    => $row['openArea'],
                'image'       => $row['image'],
                'gallery'     => $gallery,
                'videoUrl'    => $row['videoUrl'],
                'province'    => $row['province'],
                'district'    => $row['district'],
                'neighborhood'=> $row['neighborhood'],
                'featured'    => (bool)$row['featured'],
                'seoTitle'    => $row['seoTitle'],
                'seoDescription' => $row['seoDescription'],
                'seoKeywords' => $row['seoKeywords'],
                'slug'        => $row['slug'],
                'canonicalUrl'=> $row['canonicalUrl'],
                'contractType'=> $row['contractType'],
                'startDate'   => $row['startDate'],
                'endDate'     => $row['endDate'],
                'contractDurationMonths' => $row['contractDurationMonths'],
                'region'      => $row['region'],
            ], $details)]);
        }

        // Search
        $search = trim((string)($_GET['search'] ?? ''));
        $page   = max(1, mamon_get_int('page', 1));
        $limit  = min(50, max(1, mamon_get_int('limit', 12)));
        $offset = ($page - 1) * $limit;

        // Filter params
        $typeFilter   = mamon_post_string('type') ?: ($_GET['type'] ?? '');
        $statusFilter = mamon_post_string('status') ?: ($_GET['status'] ?? '');
        $regionFilter = mamon_post_string('region') ?: ($_GET['region'] ?? '');

        $conditions = [
            "l.status = 'published'",
            "(l.contract_end IS NULL OR l.contract_end >= CURRENT_DATE)",
        ];
        $params = [];

        if ($search !== '') {
            // pg_trgm search
            $conditions[] = "(l.title_tr % ? OR l.title_tr ILIKE '%' || ? || '%')";
            $params[] = $search;
            $params[] = $search;
        }

        if ($typeFilter !== '') {
            $conditions[] = "l.property_type = ?";
            $params[] = $typeFilter;
        }

        if ($statusFilter !== '') {
            $conditions[] = "l.sale_type = ?";
            $params[] = $statusFilter;
        }

        if ($regionFilter !== '') {
            $conditions[] = "r.name = ?";
            $params[] = $regionFilter;
        }

        $where = implode(' AND ', $conditions);

        // Count total
        $countSql = "SELECT count(*)::int FROM listings l JOIN regions r ON r.id=l.region_id WHERE {$where}";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Fetch listings
        $sql = "SELECT l.id, l.title_tr AS title, l.property_type AS type, l.sale_type AS status,
                       l.original_price AS price, l.price_currency AS currency,
                       l.rooms, l.bathrooms AS bath, l.gross_area AS area,
                       l.cover_image AS image, l.featured, r.name AS region
                FROM listings l JOIN regions r ON r.id=l.region_id
                WHERE {$where}
                ORDER BY l.featured DESC, l.created_at DESC
                LIMIT {$limit} OFFSET {$offset}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $listings = $stmt->fetchAll();

        mamon_json([
            'listings' => $listings,
            'pagination' => [
                'total'       => $total,
                'page'        => $page,
                'limit'       => $limit,
                'totalPages'  => (int)ceil($total / $limit),
            ],
        ]);
    }

    /* ── Regions ─────────────────────────────── */
    if ($action === 'regions') {
        $stmt = $pdo->query(
            "SELECT id, name, province, cover_image AS image, slug,
                    jsonb_array_length(attractions) AS attraction_count
             FROM regions ORDER BY sort_order, name"
        );
        $regions = $stmt->fetchAll();

        // Count listings per region
        $counts = $pdo->query(
            "SELECT r.name, count(*)::int AS cnt
             FROM listings l JOIN regions r ON r.id=l.region_id
             WHERE l.status='published' AND (l.contract_end IS NULL OR l.contract_end >= CURRENT_DATE)
             GROUP BY r.name"
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        foreach ($regions as &$r) {
            $r['listingCount'] = $counts[$r['name']] ?? 0;
        }
        unset($r);

        mamon_json(['regions' => $regions]);
    }

    /* ── Exchange rates ──────────────────────── */
    if ($action === 'rates') {
        $rows = $pdo->query("SELECT currency, rate FROM exchange_rates")->fetchAll(PDO::FETCH_KEY_PAIR);
        mamon_json(['rates' => $rows]);
    }

    /* ── Contact form ────────────────────────── */
    if ($action === 'contact' && $method === 'POST') {
        if (!mamon_csrf_verify()) mamon_json(['error' => 'CSRF hatası.'], 403);

        $name    = mamon_post_string('name');
        $phone   = mamon_post_string('phone');
        $email   = mamon_post_string('email');
        $message = mamon_post_string('message');

        if ($name === '' || ($phone === '' && $email === '') || $message === '') {
            mamon_json(['error' => 'Ad, iletişim bilgisi ve mesaj zorunludur.'], 422);
        }

        // Store contact request
        $stmt = $pdo->prepare(
            "INSERT INTO contact_requests(name,phone,email,message,created_at) VALUES(?,?,?,?,now())"
        );
        $stmt->execute([$name, $phone, $email, $message]);

        // Notify admin via email
        $adminEmail = mamon_config('CONTACT_EMAIL', mamon_config('email', 'info@mamonestate.com'));
        $body = "Yeni iletişim talebi:\n\nAd: {$name}\nTelefon: {$phone}\nE-posta: {$email}\n\nMesaj:\n{$message}";
        @mail($adminEmail, 'Mamon Estate — Yeni İletişim Talebi', $body,
            "From: noreply@mamonestate.com\r\nContent-Type: text/plain; charset=UTF-8");

        mamon_json(['ok' => true]);
    }

    mamon_json(['error' => 'Geçersiz istek.'], 400);
} catch (Throwable $e) {
    error_log('Public API error: ' . $e->getMessage());
    http_response_code(500);
    mamon_json(['error' => 'Sunucu hatası.'], 500);
}
