<?php
// Tymczasowy skrypt do czyszczenia cache na seohost.pl
// USUŃ PO UŻYCIU!

header('Content-Type: application/json');

$results = [];

// 1. Wyczyść OPcache
if (function_exists('opcache_reset')) {
    opcache_reset();
    $results['opcache'] = 'Wyczyszczony';
} else {
    $results['opcache'] = 'Niedostępny';
}

// 2. Wyczyść cache Laravel (jeśli możliwe)
try {
    if (file_exists(__DIR__ . '/../artisan')) {
        $output = [];
        $return = 0;
        exec('cd ' . __DIR__ . '/.. && php artisan cache:clear 2>&1', $output, $return);
        $results['laravel_cache'] = $return === 0 ? 'Wyczyszczony' : 'Błąd: ' . implode(' ', $output);
    } else {
        $results['laravel_cache'] = 'Artisan nie znaleziony';
    }
} catch (Exception $e) {
    $results['laravel_cache'] = 'Błąd: ' . $e->getMessage();
}

// 3. Informacje o PHP
$results['php_version'] = PHP_VERSION;
$results['opcache_enabled'] = function_exists('opcache_get_status') ? 'Tak' : 'Nie';
$results['timestamp'] = date('Y-m-d H:i:s');

echo json_encode($results, JSON_PRETTY_PRINT);
?>
