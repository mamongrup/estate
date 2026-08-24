<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

mamon_admin_check();

try {
    $pdo = mamon_db();
    $counts = $pdo->query("SELECT
        (SELECT count(*) FROM listings) AS total_listings,
        (SELECT count(*) FROM listings WHERE status='published') AS active_listings,
        (SELECT count(*) FROM regions) AS total_regions,
        (SELECT count(*) FROM listing_translations) AS translation_count
    ")->fetch();

    $total = (int)$counts['total_listings'];
    $translationTarget = $total * 5;
    $translationPercent = $translationTarget > 0
        ? min(100, (int)round(((int)$counts['translation_count'] / $translationTarget) * 100))
        : 0;

    $distribution = $pdo->query("SELECT property_type AS type, count(*) AS count
        FROM listings GROUP BY property_type ORDER BY property_type")->fetchAll();

    $alerts = $pdo->query("SELECT id,title_tr AS title,contract_end
        FROM listings
        WHERE contract_end IS NOT NULL AND contract_end >= CURRENT_DATE
        ORDER BY contract_end ASC LIMIT 4")->fetchAll();

    mamon_json([
        'totalListings' => $total,
        'activeListings' => (int)$counts['active_listings'],
        'totalRegions' => (int)$counts['total_regions'],
        'translationPercent' => $translationPercent,
        'distribution' => $distribution,
        'contractAlerts' => $alerts,
    ]);
} catch (Throwable $error) {
    error_log('Admin stats: ' . $error->getMessage());
    mamon_json(['error' => 'İstatistikler yüklenemedi.'], 500);
}
