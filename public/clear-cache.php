<?php
// Tymczasowy skrypt do czyszczenia cache na seohost.pl
// USUŃ PO UŻYCIU!

// Prosty skrypt bez skomplikowanych operacji
echo "<h2>Status Cache na seohost.pl</h2>";

// 1. Sprawdź OPcache
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "<p style='color: green;'>✅ OPcache został wyczyszczony!</p>";
} else {
    echo "<p style='color: orange;'>⚠️ OPcache nie jest dostępny</p>";
}

// 2. Informacje o PHP
echo "<p><strong>Wersja PHP:</strong> " . PHP_VERSION . "</p>";
echo "<p><strong>OPcache włączony:</strong> " . (function_exists('opcache_get_status') ? 'Tak' : 'Nie') . "</p>";
echo "<p><strong>Czas:</strong> " . date('Y-m-d H:i:s') . "</p>";

echo "<hr>";
echo "<p style='color: red;'><strong>USUŃ TEN PLIK: public/clear-cache.php</strong></p>";
?>
