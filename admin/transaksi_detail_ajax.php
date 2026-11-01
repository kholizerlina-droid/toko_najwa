<?php
// admin/transaksi_detail_ajax.php
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die("ID tidak valid");

$stmt_t = $koneksi->prepare("SELECT * FROM transaksi WHERE id = ?");
$stmt_t->bind_param("i", $id);
$stmt_t->execute();
$trx = $stmt_t->get_result()->fetch_assoc();

if (!$trx) die("Data tidak ditemukan");

$stmt_d = $koneksi->prepare("SELECT td.*, p.nama_produk, p.satuan, k.nama_kategori 
                            FROM transaksi_detail td 
                            JOIN produk p ON td.produk_id = p.id 
                            JOIN kategori k ON p.kategori_id = k.id 
                            WHERE td.transaksi_id = ?");
$stmt_d->bind_param("i", $id);
$stmt_d->execute();
$items = $stmt_d->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="row g-2 mb-3 small bg-light p-3 rounded-3 border">
    <div class="col-6">
        <div>No Faktur: <strong><?= htmlspecialchars($trx['no_faktur']); ?></strong></div>
        <div>Waktu: <strong><?= tgl_indo($trx['tanggal'], true); ?></strong></div>
    </div>
    <div class="col-6 text-end">
        <div>Pelanggan: <strong><?= htmlspecialchars($trx['nama_pelanggan']); ?></strong></div>
        <div>Metode: <strong class="text-uppercase"><?= htmlspecialchars($trx['metode_pembayaran']); ?></strong></div>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
        <thead class="table-light small">
            <tr>
                <th>Produk</th>
                <th>Kategori</th>
                <th class="text-end">Harga Beli</th>
                <th class="text-end">Harga Jual</th>
                <th class="text-center">Qty</th>
                <th class="text-end">Subtotal</th>
                <th class="text-end">Keuntungan</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $total_laba = 0;
            foreach ($items as $item): 
                $total_laba += $item['keuntungan'];
            ?>
                <tr>
                    <td><strong><?= htmlspecialchars($item['nama_produk']); ?></strong></td>
                    <td><span class="badge bg-light text-secondary border"><?= htmlspecialchars($item['nama_kategori']); ?></span></td>
                    <td class="text-end text-muted small"><?= rupiah($item['harga_beli']); ?></td>
                    <td class="text-end"><?= rupiah($item['harga_jual']); ?></td>
                    <td class="text-center fw-bold"><?= $item['qty']; ?> <?= htmlspecialchars($item['satuan']); ?></td>
                    <td class="text-end fw-bold text-dark"><?= rupiah($item['subtotal']); ?></td>
                    <td class="text-end fw-bold text-success">+<?= rupiah($item['keuntungan']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="table-light">
            <tr>
                <th colspan="5" class="text-end">TOTAL:</th>
                <th class="text-end fs-6 text-dark"><?= rupiah($trx['total_harga']); ?></th>
                <th class="text-end fs-6 text-success">+<?= rupiah($total_laba); ?></th>
            </tr>
        </tfoot>
    </table>
</div>

<div class="text-end mt-3">
    <a href="cetak_struk.php?faktur=<?= urlencode($trx['no_faktur']); ?>" target="_blank" class="btn btn-sm btn-success">
        <i class="bi bi-printer me-1"></i> Cetak Struk Nota
    </a>
</div>
