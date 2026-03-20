<?php
/**
 * Konfiguracja phpMyAdmin dla importu dużych plików SQL
 * Montowana do kontenera jako config.user.inc.php
 */

// Bez limitu czasu wykonania (0 = unlimited) - import może trwać dowolnie długo
$cfg['ExecTimeLimit'] = 0;

// Zwiększony limit pamięci dla dużych importów
$cfg['MemoryLimit'] = '1024M';
