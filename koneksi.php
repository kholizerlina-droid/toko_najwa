<?php
// koneksi.php - Koneksi Database MySQL Toko Najwa
$host = '127.0.0.1';
$user = 'root';
$pass = '';
$db   = 'db_tokonajwa';

$koneksi = new mysqli($host, $user, $pass, $db);

if ($koneksi->connect_error) {
    die("Koneksi Database Gagal: " . $koneksi->connect_error);
}

// Set karakter utf8mb4
$koneksi->set_charset("utf8mb4");

// Pengaturan zona waktu WIB
date_default_timezone_set('Asia/Jakarta');
