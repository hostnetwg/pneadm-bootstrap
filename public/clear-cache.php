<?php
// Plik do czyszczenia cache na produkcji
// Uruchom przez: https://adm.pnedu.pl/clear-cache.php

echo "<h2>Czyszczenie cache Laravel</h2>";

try {
    // Wyczyść cache routes
    $output = shell_exec('php artisan route:clear 2>&1');
    echo "<p><strong>Route cache:</strong> " . ($output ? $output : 'Wyczyszczony') . "</p>";
    
    // Wyczyść cache config
    $output = shell_exec('php artisan config:clear 2>&1');
    echo "<p><strong>Config cache:</strong> " . ($output ? $output : 'Wyczyszczony') . "</p>";
    
    // Wyczyść cache aplikacji
    $output = shell_exec('php artisan cache:clear 2>&1');
    echo "<p><strong>Application cache:</strong> " . ($output ? $output : 'Wyczyszczony') . "</p>";
    
    // Wyczyść cache widoków
    $output = shell_exec('php artisan view:clear 2>&1');
    echo "<p><strong>View cache:</strong> " . ($output ? $output : 'Wyczyszczony') . "</p>";
    
    echo "<p><strong style='color: green;'>✅ Cache został wyczyszczony!</strong></p>";
    echo "<p><a href='/api/publigo/webhook-test'>Przetestuj webhook</a></p>";
    
} catch (Exception $e) {
    echo "<p><strong style='color: red;'>❌ Błąd:</strong> " . $e->getMessage() . "</p>";
}

// Usuń ten plik po użyciu
echo "<p><small>Pamiętaj, aby usunąć ten plik po użyciu!</small></p>";
?>
