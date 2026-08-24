<?php
declare(strict_types=1);
require __DIR__ . '/../api/config.php';

$lock = fopen('/tmp/mamonestate-exchange-rates.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Kur güncellemesi zaten çalışıyor.\n");
    exit(0);
}

try {
    $pdo = mamon_db();

    $ch = curl_init('https://open.er-api.com/v6/latest/TRY');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_USERAGENT      => 'MamonEstate/1.0',
    ]);
    $raw    = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode((string)$raw, true);
    if ($status !== 200 || ($data['result'] ?? '') !== 'success' || ($data['base_code'] ?? '') !== 'TRY') {
        throw new RuntimeException('Kur sağlayıcısından geçerli veri alınamadı.');
    }

    $currencies = ['TRY', 'EUR', 'USD', 'GBP', 'RUB', 'AED'];
    $rates      = ['TRY' => 1.0];

    foreach (array_slice($currencies, 1) as $currency) {
        $rate = (float)($data['rates'][$currency] ?? 0);
        if ($rate <= 0) throw new RuntimeException($currency . ' kuru eksik.');
        $rates[$currency] = $rate;
    }

    // Sanity check: no more than 20% change
    $existing = [];
    foreach ($pdo->query('SELECT currency,rate FROM exchange_rates')->fetchAll() as $row) {
        $existing[$row['currency']] = (float)$row['rate'];
    }

    foreach ($rates as $currency => $rate) {
        if (isset($existing[$currency]) && $existing[$currency] > 0) {
            $change = abs($rate - $existing[$currency]) / $existing[$currency];
            if ($change > 0.20) {
                throw new RuntimeException($currency . ' kurunda olağan dışı %' . round($change * 100, 1) . ' değişim.');
            }
        }
    }

    $providerTime = !empty($data['time_last_update_unix'])
        ? date('c', (int)$data['time_last_update_unix'])
        : null;

    $pdo->beginTransaction();

    $history = $pdo->prepare(
        'INSERT INTO exchange_rate_history(currency,rate,provider,provider_updated_at) VALUES(?,?,?,?::timestamptz)'
    );
    $upsert = $pdo->prepare(
        'INSERT INTO exchange_rates(currency,rate,updated_at) VALUES(?,?,now()) ON CONFLICT(currency) DO UPDATE SET rate=excluded.rate,updated_at=now()'
    );

    foreach ($rates as $currency => $rate) {
        $history->execute([$currency, $rate, 'ExchangeRate-API', $providerTime]);
        $upsert->execute([$currency, $rate]);
    }

    $pdo->commit();

    // Write rates to frontend JS file
    $asset = mamon_config('STATIC_ROOT', '.')
        . '/assets/js/rates-live.js';
    $tmp = $asset . '.tmp';
    $js  = "window.MAMON_RATES=" . json_encode($rates, JSON_UNESCAPED_SLASHES) . ";\n";

    if (file_put_contents($tmp, $js, LOCK_EX) === false || !rename($tmp, $asset)) {
        throw new RuntimeException('Ön yüz kur dosyası yazılamadı.');
    }

    echo date('c') . ' ' . count($rates) . ' kur başarıyla güncellendi.\n';
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, date('c') . ' ' . $e->getMessage() . "\n");
    exit(1);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
