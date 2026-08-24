<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

mamon_admin_check();
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');

function render_regions(PDO $pdo): void {
    $sql = "WITH RECURSIVE tree AS (
                SELECT id, parent_id, name, province, region_type, cover_image, sort_order,
                       jsonb_array_length(gallery) AS gallery_count,
                       jsonb_array_length(attractions) AS attraction_count,
                       0 AS depth,
                       lpad(sort_order::text,8,'0')||'-'||name AS path
                FROM regions WHERE parent_id IS NULL
              UNION ALL
                SELECT r.id, r.parent_id, r.name, r.province, r.region_type, r.cover_image, r.sort_order,
                       jsonb_array_length(r.gallery), jsonb_array_length(r.attractions),
                       t.depth+1, t.path||'/'||lpad(r.sort_order::text,8,'0')||'-'||r.name
                FROM regions r JOIN tree t ON r.parent_id=t.id
            )
            SELECT * FROM tree ORDER BY path";

    $rows = $pdo->query($sql)->fetchAll();
    if (!$rows) {
        echo '<p>Henüz bölge oluşturulmadı.</p>';
        return;
    }

    $typeLabels = ['province' => 'İl', 'district' => 'İlçe', 'neighborhood' => 'Mahalle / Semt'];

    foreach ($rows as $row) {
        $indent = (int)$row['depth'] * 24;
        $type   = $typeLabels[$row['region_type']] ?? 'Bölge';

        echo '<div class="region-row" data-region-id="' . (int)$row['id']
            . '" data-region-depth="' . (int)$row['depth']
            . '" data-region-name="' . mamon_esc($row['name'])
            . '" style="margin-left:' . $indent . 'px">';

        if ($row['cover_image']) {
            echo '<img src="' . mamon_esc($row['cover_image']) . '" alt="">';
        }

        echo '<div><b>' . mamon_esc($row['name']) . '</b><small>'
            . mamon_esc($type) . ' · ' . mamon_esc($row['province'])
            . ' · ' . (int)$row['gallery_count'] . ' görsel · '
            . (int)$row['attraction_count'] . ' cazibe</small></div></div>';
    }
}

try {
    $pdo = mamon_db();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!mamon_csrf_verify()) {
            http_response_code(403);
            exit('CSRF doğrulaması başarısız.');
        }

        $name     = mamon_post_string('name');
        $province = mamon_post_string('province');
        $type     = mamon_post_string('regionType', 'district');
        $parent   = filter_var($_POST['parentId'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $order    = (int)($_POST['sortOrder'] ?? 0);

        if ($name === '' || $province === '' || !in_array($type, ['province','district','neighborhood'], true)) {
            http_response_code(422);
            exit('Alanları kontrol edin.');
        }

        // Unique slug
        $base      = mamon_slug($name);
        $candidate = $base;
        $number    = 2;
        $check     = $pdo->prepare('SELECT 1 FROM regions WHERE slug=?');
        while (true) {
            $check->execute([$candidate]);
            if (!$check->fetchColumn()) break;
            $candidate = $base . '-' . $number++;
        }

        $lines = fn(string $key) => array_values(
            array_filter(array_map('trim', preg_split('/\R/', mamon_post_string($key)) ?: []))
        );

        $stmt = $pdo->prepare(
            'INSERT INTO regions(name,province,slug,cover_image,parent_id,region_type,sort_order,
                content_title,description,gallery,video_url,attractions,
                seo_title,seo_description,seo_keywords,canonical_url,is_indexable)
             VALUES(?,?,?,?,?,?,?,?,?,?::jsonb,?,?::jsonb,?,?,?,?,?) RETURNING id'
        );
        $stmt->execute([
            $name, $province, $candidate,
            mamon_post_string('image') ?: null,
            $parent, $type, $order,
            mamon_post_string('contentTitle') ?: $name,
            mamon_post_string('description'),
            json_encode($lines('gallery'), JSON_UNESCAPED_SLASHES),
            mamon_post_string('videoUrl') ?: null,
            json_encode($lines('attractions'), JSON_UNESCAPED_UNICODE),
            mamon_post_string('seoTitle') ?: null,
            mamon_post_string('seoDescription') ?: null,
            mamon_post_string('seoKeywords') ?: null,
            mamon_post_string('canonicalUrl') ?: null,
            isset($_POST['isIndexable']) ? 1 : 0,
        ]);

        $newRegionId = $stmt->fetchColumn();
        header('X-Region-Id: ' . $newRegionId);
    }

    render_regions($pdo);
} catch (Throwable $e) {
    error_log('Admin regions: ' . $e->getMessage());
    http_response_code(500);
    echo 'Bölgeler yüklenemedi.';
}
