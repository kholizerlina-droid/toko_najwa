<?php
// admin/cetak_laporan.php - Laporan laba rugi siap cetak (kertas A4).
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/laporan.php';
wajib_login();

$toko        = pengaturan_toko();
$tgl_mulai   = tanggal_dari_url('tgl_mulai', date('Y-m-01'));
$tgl_selesai = tanggal_dari_url('tgl_selesai', date('Y-m-d'));
$lap         = hitung_laporan($tgl_mulai, $tgl_selesai);

$pengeluaran = ambil_semua(
    'SELECT * FROM pengeluaran WHERE tanggal BETWEEN ? AND ? ORDER BY tanggal',
    'ss', [$tgl_mulai, $tgl_selesai]
);
$terlaris = ambil_semua(
    "SELECT p.nama_produk, SUM(d.qty) AS terjual, SUM(d.subtotal) AS omset, SUM(d.keuntungan) AS laba
     FROM transaksi_detail d
     JOIN transaksi t ON t.id = d.transaksi_id
     JOIN produk p ON p.id = d.produk_id
     WHERE DATE(t.tanggal) BETWEEN ? AND ?
     GROUP BY p.id ORDER BY terjual DESC LIMIT 10",
    'ss', [$tgl_mulai, $tgl_selesai]
);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Laba Rugi <?= e($tgl_mulai) ?> - <?= e($tgl_selesai) ?></title>
    <link rel="stylesheet" href="<?= url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
</head>
<body class="bg-body-tertiary">
<div class="container bg-white my-3 p-4 p-md-5 border" style="max-width: 800px;">

    <div class="d-print-none d-flex justify-content-between mb-4">
        <a href="laporan.php?tgl_mulai=<?= e($tgl_mulai) ?>&tgl_selesai=<?= e($tgl_selesai) ?>" class="btn btn-outline-secondary">&larr; Kembali</a>
        <button type="button" class="btn btn-primary" onclick="window.print()">Cetak</button>
    </div>

    <div class="text-center border-bottom border-2 pb-3 mb-4">
        <?= logo(64) ?>
        <h1 class="h4 mt-2 mb-0"><?= e(strtoupper($toko['nama_toko'])) ?></h1>
        <div class="small"><?= e($toko['alamat']) ?> &middot; Telp. <?= e($toko['telepon']) ?></div>
    </div>

    <h2 class="h5 text-center">LAPORAN LABA RUGI</h2>
    <p class="text-center mb-4">Periode <?= tanggal_indo($tgl_mulai) ?> s.d. <?= tanggal_indo($tgl_selesai) ?></p>

    <table class="table table-bordered">
        <tbody>
            <tr><td>Total Penjualan (<?= $lap['jumlah_nota'] ?> nota)</td><td class="text-end"><?= rupiah($lap['omset']) ?></td></tr>
            <tr><td>Harga Pokok Penjualan (HPP)</td><td class="text-end">(<?= rupiah($lap['hpp']) ?>)</td></tr>
            <tr class="table-light fw-bold"><td>Laba Kotor</td><td class="text-end"><?= rupiah($lap['laba_kotor']) ?></td></tr>
            <tr><td>Pengeluaran Operasional</td><td class="text-end">(<?= rupiah($lap['biaya']) ?>)</td></tr>
            <tr class="table-secondary fw-bold"><td>LABA BERSIH</td><td class="text-end"><?= rupiah($lap['laba_bersih']) ?></td></tr>
        </tbody>
    </table>

    <?php if ($terlaris): ?>
        <h3 class="h6 mt-4">Produk Terlaris</h3>
        <table class="table table-sm table-bordered">
            <thead class="table-light"><tr><th>Produk</th><th class="text-end">Terjual</th><th class="text-end">Omset</th><th class="text-end">Laba</th></tr></thead>
            <tbody>
                <?php foreach ($terlaris as $t): ?>
                    <tr><td><?= e($t['nama_produk']) ?></td><td class="text-end"><?= $t['terjual'] ?></td>
                        <td class="text-end"><?= rupiah($t['omset']) ?></td><td class="text-end"><?= rupiah($t['laba']) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if ($pengeluaran): ?>
        <h3 class="h6 mt-4">Rincian Pengeluaran</h3>
        <table class="table table-sm table-bordered">
            <thead class="table-light"><tr><th>Tanggal</th><th>Kategori</th><th>Keterangan</th><th class="text-end">Jumlah</th></tr></thead>
            <tbody>
                <?php foreach ($pengeluaran as $p): ?>
                    <tr><td><?= tanggal_indo($p['tanggal']) ?></td><td><?= e($p['kategori_pengeluaran']) ?></td>
                        <td><?= e($p['keterangan']) ?></td><td class="text-end"><?= rupiah($p['jumlah']) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="row mt-5">
        <div class="col-6 offset-6 text-center">
            <div><?= tanggal_indo(date('Y-m-d')) ?></div>
            <div>Pemilik,</div>
            <div style="height: 70px;"></div>
            <strong><u><?= e($toko['pemilik']) ?></u></strong>
        </div>
    </div>
</div>
</body>
</html>
