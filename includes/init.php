<?php
// includes/init.php
// Dipanggil paling atas oleh SEMUA halaman: memuat konfigurasi, helper, dan memulai session.

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
