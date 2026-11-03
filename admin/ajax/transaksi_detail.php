<?php
// admin/ajax/transaksi_detail.php
// Mengembalikan potongan HTML rincian satu nota (dipanggil oleh assets/js/transaksi.js).
require_once __DIR__ . '/../../includes/init.php';
wajib_login();

$id  = (int) get('id');
$trx = ambil_satu('SELECT * FROM transaksi WHERE id = ?', 'i', [$id]);

if (!$trx) {
    exit('<div class="alert alert-warning mb-0">Transaksi tidak ditemukan.</div>');
}

$items = ambil_semua(
    'SELECT d.*, p.nama_produk, p.satuan
     FROM transaksi_detail d JOIN produk p ON p.id = d.produk_id
     WHERE d.transaksi_id = ?',
    'i', [$id]
);
$total_laba = array_sum(array_column($items, 'keuntungan'));
?>
<div class="row mb-3 small">
    <div class="col-6">
        <div>Faktur: <strong class="font-monospace"><?= e($trx['no_faktur']) ?></strong></div>
        <div>Waktu: <strong><?= tanggal_indo($trx['tanggal'], true) ?></strong></div>
    </div>
    <div class="col-6 text-end">
        <div>Pelanggan: <strong><?= e($trx['nama_pelanggan']) ?></strong></div>
        <div>Pembayaran: <strong class="text-uppercase"><?= e($trx['metode_pembayaran']) ?></strong></div>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
            <tr><th>Produk</th><th class="text-end">Harga Beli</th><th class="text-end">Harga Jual</th><th class="text-center">Qty</th><th class="text-end">Subtotal</th><th class="text-end">Laba</th></tr>
        </thead>
        <tbody>
            <?php foreach ($items as $i): ?>
                <tr>
                    <td><?= e($i['nama_produk']) ?></td>
                    <td class="text-end text-body-secondary"><?= rupiah($i['harga_beli']) ?></td>
                    <td class="text-end"><?= rupiah($i['harga_jual']) ?></td>
                    <td class="text-center"><?= $i['qty'] ?> <?= e($i['satuan']) ?></td>
                    <td class="text-end fw-semibold"><?= rupiah($i['subtotal']) ?></td>
                    <td class="text-end text-success"><?= rupiah($i['keuntungan']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="table-light">
            <tr>
                <th colspan="4" class="text-end">Total</th>
                <th class="text-end"><?= rupiah($trx['total_harga']) ?></th>
                <th class="text-end text-success"><?= rupiah($total_laba) ?></th>
            </tr>
        </tfoot>
    </table>
</div>
