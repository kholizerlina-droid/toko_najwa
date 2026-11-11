<?php
// admin/pesanan.php - Daftar pesanan yang dikirim dari katalog WhatsApp.
require_once __DIR__ . '/../includes/init_admin.php';

if (is_post() && post('aksi') === 'status') {
    $id = (int) post('id');
    $status = post('status');
    if (in_array($status, ['baru', 'diproses', 'dibatalkan'], true)) {
        jalankan('UPDATE pesanan_online SET status = ? WHERE id = ? AND transaksi_id IS NULL', 'si', [$status, $id]);
        set_flash('success', 'Status pesanan diperbarui.');
    }
    redirect('pesanan.php');
}

$pesanan = ambil_semua(
    'SELECT po.*, t.no_faktur FROM pesanan_online po
     LEFT JOIN transaksi t ON t.id = po.transaksi_id
     ORDER BY po.created_at DESC LIMIT 200'
);
$label_status = ['baru' => 'Baru', 'diproses' => 'Diproses', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'];
$status_manual = ['baru' => 'Baru', 'diproses' => 'Diproses', 'dibatalkan' => 'Dibatalkan'];
$judul_halaman = 'Pesanan Online';
$menu_aktif = 'pesanan';
include __DIR__ . '/../includes/layout/admin_top.php';
?>

<div class="mb-4">
    <h1 class="h3 mb-0">Pesanan Online</h1>
    <p class="text-body-secondary mb-0">Pesanan dari katalog yang dikirim ke WhatsApp. Pesanan baru belum mengurangi stok dan belum menjadi transaksi.</p>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Kode / Waktu</th><th>Pemesan</th><th>Layanan</th><th>Barang</th><th>Pembayaran</th><th class="text-end">Total</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($pesanan as $p): $items = json_decode($p['item_json'], true) ?: []; ?>
                <tr>
                    <td><span class="font-monospace small"><?= e($p['kode_pesanan']) ?></span><small class="d-block text-body-secondary"><?= tanggal_indo($p['created_at'], true) ?></small></td>
                    <td><?= e($p['nama_pemesan']) ?><?php if ($p['catatan']): ?><small class="d-block text-body-secondary"><?= e($p['catatan']) ?></small><?php endif; ?></td>
                    <td><?= e($p['layanan']) ?></td>
                    <td><ul class="mb-0 ps-3"><?php foreach ($items as $i): ?><li><?= e($i['nama']) ?> × <?= (int) $i['qty'] ?> (<?= rupiah($i['subtotal']) ?>)</li><?php endforeach; ?></ul></td>
                    <td><?= e($p['metode_bayar']) ?></td>
                    <td class="text-end fw-semibold"><?= rupiah($p['total']) ?></td>
                    <td>
                        <span class="badge text-bg-<?= $p['status'] === 'baru' ? 'warning' : ($p['status'] === 'selesai' ? 'success' : 'secondary') ?>"><?= e($label_status[$p['status']] ?? $p['status']) ?></span>
                        <?php if ($p['no_faktur']): ?><small class="d-block"><a href="transaksi.php?q=<?= urlencode($p['no_faktur']) ?>">Faktur <?= e($p['no_faktur']) ?></a></small><?php endif; ?>
                    </td>
                    <td class="text-nowrap">
                        <?php if (!$p['transaksi_id'] && $p['status'] !== 'dibatalkan'): ?>
                            <a href="pos.php?pesanan=<?= (int) $p['id'] ?>" class="btn btn-sm btn-primary">Proses di Kasir</a>
                        <?php elseif (!$p['transaksi_id']): ?>
                            <span class="text-body-secondary small">Dibatalkan</span>
                        <?php endif; ?>
                        <?php if (!$p['transaksi_id'] && $p['status'] !== 'selesai'): ?>
                            <form method="post" class="d-flex gap-1 mt-1">
                                <?= csrf_field() ?><input type="hidden" name="aksi" value="status"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <select name="status" class="form-select form-select-sm" aria-label="Status pesanan">
                                    <?php foreach ($status_manual as $key => $label): ?><option value="<?= e($key) ?>" <?= $p['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                                </select><button class="btn btn-sm btn-outline-primary" type="submit">Simpan</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$pesanan): ?><tr><td colspan="8" class="text-center text-body-secondary py-5">Belum ada pesanan dari katalog.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/layout/admin_bottom.php'; ?>
