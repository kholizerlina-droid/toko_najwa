<?php
// admin/cetak_struk.php - Struk belanja ukuran kertas thermal.
require_once __DIR__ . '/../includes/init.php';
wajib_login();

$toko = pengaturan_toko();
$trx  = ambil_satu(
    'SELECT t.*, u.nama_lengkap AS kasir FROM transaksi t LEFT JOIN users u ON u.id = t.user_id WHERE t.no_faktur = ?',
    's', [get('faktur')]
);
if (!$trx) {
    exit('Transaksi tidak ditemukan.');
}

$items = ambil_semua(
    'SELECT d.*, p.nama_produk, p.satuan FROM transaksi_detail d JOIN produk p ON p.id = d.produk_id WHERE d.transaksi_id = ?',
    'i', [$trx['id']]
);
$label_metode = ['tunai' => 'Tunai', 'qris' => 'QRIS', 'utang' => 'Kasbon (Belum Lunas)'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk <?= e($trx['no_faktur']) ?></title>
    <link rel="stylesheet" href="<?= url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
</head>
<body class="bg-body-tertiary">
<div class="container py-3">
    <div class="struk mx-auto bg-white border shadow-sm p-3">

        <div class="text-center mb-2">
            <?= logo(48) ?>
            <div class="fw-bold"><?= e(strtoupper($toko['nama_toko'])) ?></div>
            <div><?= e($toko['alamat']) ?></div>
            <div>Telp. <?= e($toko['telepon']) ?></div>
        </div>
        <hr class="my-2 border-dark border-1 opacity-100" style="border-style: dashed !important;">

        <div>No. Faktur : <?= e($trx['no_faktur']) ?></div>
        <div>Tanggal    : <?= date('d/m/Y H:i', strtotime($trx['tanggal'])) ?></div>
        <div>Kasir      : <?= e($trx['kasir'] ?? '-') ?></div>
        <div>Pelanggan  : <?= e($trx['nama_pelanggan']) ?></div>
        <hr class="my-2 border-dark border-1 opacity-100" style="border-style: dashed !important;">

        <?php foreach ($items as $i): ?>
            <div class="fw-bold"><?= e($i['nama_produk']) ?></div>
            <div class="d-flex justify-content-between">
                <span><?= $i['qty'] ?> <?= e($i['satuan']) ?> x <?= number_format($i['harga_jual'], 0, ',', '.') ?></span>
                <span><?= number_format($i['subtotal'], 0, ',', '.') ?></span>
            </div>
        <?php endforeach; ?>
        <hr class="my-2 border-dark border-1 opacity-100" style="border-style: dashed !important;">

        <div class="d-flex justify-content-between fw-bold"><span>TOTAL</span><span><?= rupiah($trx['total_harga']) ?></span></div>
        <div class="d-flex justify-content-between"><span>Pembayaran</span><span><?= $label_metode[$trx['metode_pembayaran']] ?></span></div>
        <?php if ($trx['metode_pembayaran'] === 'tunai'): ?>
            <div class="d-flex justify-content-between"><span>Tunai</span><span><?= rupiah($trx['total_bayar']) ?></span></div>
            <div class="d-flex justify-content-between"><span>Kembali</span><span><?= rupiah($trx['kembalian']) ?></span></div>
        <?php endif; ?>
        <hr class="my-2 border-dark border-1 opacity-100" style="border-style: dashed !important;">

        <div class="text-center"><?= nl2br(e($toko['teks_footer_struk'])) ?></div>
    </div>

    <div class="d-print-none text-center mt-3">
        <button type="button" class="btn btn-primary" onclick="window.print()">Cetak</button>
        <button type="button" class="btn btn-outline-secondary" onclick="window.close()">Tutup</button>
    </div>
</div>
</body>
</html>
