<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$base = rtrim(mamon_config('SITE_URL', 'https://mamonestate.com'), '/');
$languages = ['tr', 'en', 'de', 'ru', 'ar', 'fr'];

// Static pages
$static = [
    '' => '1.0',
    'satilik' => '0.9',
    'kiralik' => '0.9',
    'bolgeler' => '0.8',
    'hakkimizda' => '0.6',
    'iletisim' => '0.6',
];

echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . PHP_EOL;

// Static pages
foreach ($static as $path => $priority) {
    foreach ($languages as $lang) {
        $url = $base . '/' . $lang . '/' . ($path ? $path . '/' : '');
        echo '<url><loc>' . htmlspecialchars($url, ENT_XML1) . '</loc>';
        echo '<lastmod>' . date('Y-m-d') . '</lastmod>';
        echo '<changefreq>' . ($path ? 'weekly' : 'daily') . '</changefreq>';
        echo '<priority>' . $priority . '</priority>';
        foreach ($languages as $alt) {
            $href = $base . '/' . $alt . '/' . ($path ? $path . '/' : '');
            echo '<xhtml:link rel="alternate" hreflang="' . $alt . '" href="' . htmlspecialchars($href, ENT_XML1) . '" />';
        }
        echo '</url>' . PHP_EOL;
    }
}

// Dynamic listings from database
try {
    $pdo = mamon_db();
    $listings = $pdo->query(
        "SELECT l.id, l.slug, l.updated_at, r.slug AS region_slug
         FROM listings l JOIN regions r ON r.id = l.region_id
         WHERE l.status = 'published'
           AND (l.contract_end IS NULL OR l.contract_end >= CURRENT_DATE)
         ORDER BY l.updated_at DESC"
    )->fetchAll();

    foreach ($listings as $listing) {
        $slug = $listing['slug'] ?: $listing['id'];
        $lastmod = date('Y-m-d', strtotime($listing['updated_at']));

        foreach ($languages as $lang) {
            $url = $base . '/' . $lang . '/ilan/' . $slug;
            echo '<url><loc>' . htmlspecialchars($url, ENT_XML1) . '</loc>';
            echo '<lastmod>' . $lastmod . '</lastmod>';
            echo '<changefreq>weekly</changefreq>';
            echo '<priority>0.7</priority>';
            foreach ($languages as $alt) {
                $href = $base . '/' . $alt . '/ilan/' . $slug;
                echo '<xhtml:link rel="alternate" hreflang="' . $alt . '" href="' . htmlspecialchars($href, ENT_XML1) . '" />';
            }
            echo '</url>' . PHP_EOL;
        }
    }

    // Dynamic regions
    $regions = $pdo->query(
        "SELECT slug, updated_at FROM regions ORDER BY name"
    )->fetchAll();

    foreach ($regions as $region) {
        $lastmod = date('Y-m-d', strtotime($region['updated_at'] ?? 'now'));

        foreach ($languages as $lang) {
            $url = $base . '/' . $lang . '/bolgeler/' . $region['slug'];
            echo '<url><loc>' . htmlspecialchars($url, ENT_XML1) . '</loc>';
            echo '<lastmod>' . $lastmod . '</lastmod>';
            echo '<changefreq>weekly</changefreq>';
            echo '<priority>0.6</priority>';
            foreach ($languages as $alt) {
                $href = $base . '/' . $alt . '/bolgeler/' . $region['slug'];
                echo '<xhtml:link rel="alternate" hreflang="' . $alt . '" href="' . htmlspecialchars($href, ENT_XML1) . '" />';
            }
            echo '</url>' . PHP_EOL;
        }
    }
} catch (Throwable $e) {
    error_log('Sitemap error: ' . $e->getMessage());
}

echo '</urlset>' . PHP_EOL;
