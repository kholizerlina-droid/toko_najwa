<?php
// admin/transaksi.php - Riwayat nota penjualan: lihat rincian, cetak ulang, batalkan.
require_once __DIR__ . '/../includes/init_admin.php';

/* ---------- Batalkan transaksi (stok dikembalikan) ---------- */
if (is_post() && post('aksi') === 'batalkan') {
    $id = (int) post('id');
    $db = db();
    $db->begin_transaction();
    try {
        // Kunci utang terkait agar kasir tidak mencatat cicilan bersamaan dengan pembatalan.
        $piutang_terkait = ambil_semua('SELECT id FROM piutang WHERE transaksi_id = ? FOR UPDATE', 'i', [$id]);
        $jumlah_pembayaran_kasbon = 0;
        foreach ($piutang_terkait as $utang_terkait) {
            $jumlah_pembayaran_kasbon += (int) ambil_nilai(
                'SELECT COUNT(*) FROM piutang_pembayaran WHERE piutang_id = ?',
                'i', [$utang_terkait['id']]
            );
        }
        if ($jumlah_pembayaran_kasbon > 0) {
            throw new Exception('Transaksi ini sudah memiliki pembayaran kasbon. Riwayat keuangan dipertahankan dan pembatalan ditolak.');
        }

        jalankan(
            'UPDATE produk p JOIN transaksi_detail d ON d.produk_id = p.id
             SET p.stok = p.stok + d.qty WHERE d.transaksi_id = ?',
            'i', [$id]
        );
        jalankan("UPDATE pesanan_online SET status = 'baru', transaksi_id = NULL WHERE transaksi_id = ?", 'i', [$id]);
        jalankan('DELETE FROM piutang WHERE transaksi_id = ?', 'i', [$id]);
        jalankan('DELETE FROM transaksi WHERE id = ?', 'i', [$id]);   // rincian ikut terhapus (CASCADE)
        $db->commit();
        set_flash('success', 'Transaksi dibatalkan dan stok barang dikembalikan.');
    } catch (Exception $e) {
        $db->rollback();
        set_flash('danger', 'Gagal membatalkan transaksi: ' . $e->getMessage());
    }
    redirect('transaksi.php');
}

/* ---------- Ambil data (dengan filter) ---------- */
$tgl_mulai   = get('tgl_mulai');
$tgl_selesai = get('tgl_selesai');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_mulai)) $tgl_mulai = '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_selesai)) $tgl_selesai = '';
$metode      = get('metode');
$cari        = get('q');

$sql   = "SELECT t.*, u.nama_lengkap AS kasir,
                 (SELECT COALESCE(SUM(keuntungan), 0) FROM transaksi_detail WHERE transaksi_id = t.id) AS laba
          FROM transaksi t LEFT JOIN users u ON u.id = t.user_id
          WHERE 1 = 1";
$tipe  = '';
$param = [];

if ($tgl_mulai !== '') {
    $sql .= ' AND DATE(t.tanggal) >= ?';
    $tipe .= 's';
    $param[] = $tgl_mulai;
}
if ($tgl_selesai !== '') {
    $sql .= ' AND DATE(t.tanggal) <= ?';
    $tipe .= 's';
    $param[] = $tgl_selesai;
}

if ($metode !== '') {
    $sql .= ' AND t.metode_pembayaran = ?';
    $tipe .= 's';
    $param[] = $metode;
}
if ($cari !== '') {
    $sql .= ' AND (t.no_faktur LIKE ? OR t.nama_pelanggan LIKE ?)';
    $tipe .= 'ss';
    $param[] = "%$cari%";
    $param[] = "%$cari%";
}

$daftar_transaksi = ambil_semua($sql . ' ORDER BY t.tanggal DESC', $tipe, $param);
$total_omset = array_sum(array_column($daftar_transaksi, 'total_harga'));
$total_laba  = array_sum(array_column($daftar_transaksi, 'laba'));

$label_metode = ['tunai' => 'Tunai', 'qris' => 'QRIS', 'utang' => 'Kasbon'];

/* ---------- Tampilan ---------- */
$judul_halaman  = 'Riwayat Transaksi';
$menu_aktif     = 'transaksi';
$skrip_tambahan = ['transaksi.js'];
include __DIR__ . '/../includes/layout/admin_top.php';
?>

<div class="mb-4">
    <h1 class="h3 mb-0">Riwayat Transaksi</h1>
    <p class="text-body-secondary mb-0">Daftar seluruh nota penjualan kasir. Kosongkan tanggal untuk melihat semua riwayat.</p>
</div>

<form method="get" class="card card-body mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-2"><label class="form-label small mb-1">Dari</label>
            <input type="date" name="tgl_mulai" class="form-control" value="<?= e($tgl_mulai) ?>"></div>
        <div class="col-md-2"><label class="form-label small mb-1">Sampai</label>
            <input type="date" name="tgl_selesai" class="form-control" value="<?= e($tgl_selesai) ?>"></div>
        <div class="col-md-2"><label class="form-label small mb-1">Pembayaran</label>
            <select name="metode" class="form-select">
                <option value="">Semua</option>
                <?php foreach ($label_metode as $kode => $label): ?>
                    <option value="<?= $kode ?>" <?= $metode === $kode ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="col-md-4"><label class="form-label small mb-1">Cari faktur / pelanggan</label>
            <input type="search" name="q" class="form-control" value="<?= e($cari) ?>"></div>
        <div class="col-md-2 d-grid gap-1">
            <button class="btn btn-primary" type="submit">Terapkan</button>
            <a href="transaksi.php" class="btn btn-sm btn-outline-secondary">Semua riwayat</a>
        </div>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card card-body"><div class="text-body-secondary small">Jumlah nota</div><div class="h4 mb-0"><?= count($daftar_transaksi) ?></div></div></div>
    <div class="col-md-4"><div class="card card-body"><div class="text-body-secondary small">Total omset</div><div class="h4 mb-0"><?= rupiah($total_omset) ?></div></div></div>
    <div class="col-md-4"><div class="card card-body"><div class="text-body-secondary small">Total laba kotor</div><div class="h4 mb-0 text-success"><?= rupiah($total_laba) ?></div></div></div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Faktur</th><th>Waktu</th><th>Pelanggan</th><th>Bayar</th><th class="text-end">Total</th><th class="text-end">Laba</th><th class="text-end">Aksi</th></tr>
            </thead>
            <tbody>
                <?php foreach ($daftar_transaksi as $t): ?>
                    <tr>
                        <td class="font-monospace small"><?= e($t['no_faktur']) ?></td>
                        <td class="text-nowrap"><?= tanggal_indo($t['tanggal'], true) ?></td>
                        <td><?= e($t['nama_pelanggan']) ?><small class="d-block text-body-secondary">Kasir: <?= e($t['kasir'] ?? '-') ?></small></td>
                        <td>
                            <span class="badge <?= $t['metode_pembayaran'] === 'utang' ? 'text-bg-warning' : 'text-bg-secondary' ?>">
                                <?= $label_metode[$t['metode_pembayaran']] ?>
                            </span>
                        </td>
                        <td class="text-end fw-semibold"><?= rupiah($t['total_harga']) ?></td>
                        <td class="text-end text-success"><?= rupiah($t['laba']) ?></td>
                        <td class="text-end text-nowrap">
                            <button type="button" class="btn btn-sm btn-outline-primary" data-detail="<?= $t['id'] ?>" title="Rincian"><i class="bi bi-eye"></i></button>
                            <a href="cetak_struk.php?faktur=<?= urlencode($t['no_faktur']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Cetak struk"><i class="bi bi-printer"></i></a>
                            <form method="post" class="d-inline" data-konfirmasi="Batalkan transaksi <?= e($t['no_faktur']) ?>? Stok barang akan dikembalikan.">
                                <?= csrf_field() ?>
                                <input type="hidden" name="aksi" value="batalkan">
                                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Batalkan"><i class="bi bi-x-circle"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$daftar_transaksi): ?>
                    <tr><td colspan="7" class="text-center text-body-secondary py-5">Tidak ada transaksi pada periode ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal rincian (isinya diambil dari ajax/transaksi_detail.php) -->
<div class="modal fade" id="modalDetail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5">Rincian Transaksi</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body" id="isiDetail"></div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/layout/admin_bottom.php'; ?>
