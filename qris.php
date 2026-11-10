<?php
// qris.php - Halaman QRIS yang mudah dibuka dari tautan WhatsApp.
require_once __DIR__ . '/includes/init.php';

$skema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$asal = $skema . '://' . $host;
$url_gambar = $asal . gambar_qris();
$url_halaman = $asal . url('qris.php');
$toko = pengaturan_toko();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta property="og:type" content="website">
    <meta property="og:title" content="QRIS <?= e($toko['nama_toko']) ?>">
    <meta property="og:description" content="Buka halaman ini untuk melihat kode QRIS toko dengan jelas.">
    <meta property="og:image" content="<?= e($url_gambar) ?>">
    <meta property="og:url" content="<?= e($url_halaman) ?>">
    <title>QRIS - <?= e($toko['nama_toko']) ?></title>
    <link rel="stylesheet" href="<?= url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
    <style>
        body { min-height: 100vh; display: grid; place-items: center; background: #f5f7fa; }
        .qris-card { width: min(calc(100% - 2rem), 560px); }
        .qris-image { display: block; width: 100%; max-height: 70vh; object-fit: contain; image-rendering: auto; }
    </style>
</head>
<body>
    <main class="card qris-card shadow-sm">
        <div class="card-body text-center p-3 p-sm-4">
            <h1 class="h4 mb-1">QRIS <?= e($toko['nama_toko']) ?></h1>
            <p class="text-body-secondary">Buka gambar ini di perangkat lain atau simpan untuk dipindai saat pembayaran.</p>
            <a href="<?= e($url_gambar) ?>" target="_blank" rel="noopener" aria-label="Buka gambar QRIS ukuran asli">
                <img src="<?= e($url_gambar) ?>" alt="Kode QRIS <?= e($toko['nama_toko']) ?>" class="qris-image">
            </a>
            <a href="<?= e(url('index.php')) ?>" class="btn btn-outline-primary mt-3">Kembali ke katalog</a>
        </div>
    </main>
</body>
</html>
