<?php
// database/install.php
// Cara mudah mengimpor database tanpa phpMyAdmin: buka halaman ini lewat browser lalu klik tombol.
// PERINGATAN: semua tabel dibuat ulang, data lama hilang. Hapus file ini saat sudah dipakai sungguhan.

require_once __DIR__ . '/../config/config.php';

$pesan = '';
$sukses = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $koneksi = new mysqli(DB_HOST, DB_USER, DB_PASS);
        $koneksi->set_charset('utf8mb4');
        $koneksi->multi_query(file_get_contents(__DIR__ . '/toko_najwa.sql'));
        while ($koneksi->more_results() && $koneksi->next_result()) {
            // habiskan semua hasil query satu per satu
        }
        $sukses = true;
        $pesan = 'Database berhasil dibuat dan diisi data contoh.';
    } catch (mysqli_sql_exception $e) {
        $pesan = 'Gagal: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instalasi Database - Toko Najwa</title>
    <link rel="stylesheet" href="../assets/vendor/bootstrap/css/bootstrap.min.css">
</head>
<body class="bg-body-tertiary">
<div class="container py-5" style="max-width: 560px;">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h1 class="h4 mb-3">Instalasi Database Toko Najwa</h1>

            <?php if ($pesan): ?>
                <div class="alert alert-<?= $sukses ? 'success' : 'danger' ?>"><?= htmlspecialchars($pesan) ?></div>
            <?php endif; ?>

            <?php if ($sukses): ?>
                <a href="../login.php" class="btn btn-primary">Lanjut ke halaman login</a>
            <?php else: ?>
                <p class="text-body-secondary">
                    Tombol di bawah membuat database <code><?= DB_NAME ?></code> beserta semua tabel dan data contoh.
                    <strong>Jika database sudah ada, seluruh isinya akan dihapus.</strong>
                </p>
                <form method="post" onsubmit="return confirm('Yakin? Semua data lama akan dihapus.');">
                    <button type="submit" class="btn btn-danger">Buat Ulang Database</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
