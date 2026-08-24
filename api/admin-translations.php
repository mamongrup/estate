<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

mamon_admin_check();

try {
    $rows = mamon_db()->query(
        "SELECT l.id, l.title_tr AS title,
                COALESCE(bool_or(t.language='en'), false) AS en,
                COALESCE(bool_or(t.language='de'), false) AS de,
                COALESCE(bool_or(t.language='ru'), false) AS ru,
                COALESCE(bool_or(t.language='ar'), false) AS ar,
                COALESCE(bool_or(t.language='fr'), false) AS fr
         FROM listings l
         LEFT JOIN listing_translations t ON t.listing_id=l.id
         GROUP BY l.id, l.title_tr
         ORDER BY l.updated_at DESC, l.id DESC"
    )->fetchAll();
    foreach ($rows as &$row) {
        foreach (['en','de','ru','ar','fr'] as $language) {
            $row[$language] = filter_var($row[$language], FILTER_VALIDATE_BOOL);
        }
    }
    unset($row);
    mamon_json(['listings' => $rows]);
} catch (Throwable $error) {
    error_log('Admin translations: ' . $error->getMessage());
    mamon_json(['error' => 'Çeviri durumu yüklenemedi.'], 500);
}
