<?php
// admin/pengeluaran.php - Biaya operasional toko (listrik, bensin, kantong plastik, dll.).
require_once __DIR__ . '/../includes/init_admin.php';

const KATEGORI_PENGELUARAN = [
    'Operasional Kulakan', 'Perlengkapan Warung', 'Listrik & Penerangan',
    'Gaji / Upah', 'Transportasi', 'Lain-lain',
];

/* ---------- Proses form ---------- */
if (is_post()) {
    $aksi = post('aksi');

    if ($aksi === 'tambah') {
        $jumlah = (float) post('jumlah');
        if ($jumlah <= 0) {
            set_flash('danger', 'Jumlah pengeluaran harus lebih dari 0.');
        } else {
            jalankan(
                'INSERT INTO pengeluaran (tanggal, kategori_pengeluaran, jumlah, keterangan) VALUES (?, ?, ?, ?)',
                'ssds', [post('tanggal', date('Y-m-d')), post('kategori_pengeluaran', 'Lain-lain'), $jumlah, post('keterangan')]
            );
            set_flash('success', 'Pengeluaran berhasil dicatat.');
        }
    } elseif ($aksi === 'hapus') {
        jalankan('DELETE FROM pengeluaran WHERE id = ?', 'i', [(int) post('id')]);
        set_flash('success', 'Catatan pengeluaran dihapus.');
    }
    redirect('pengeluaran.php?bulan=' . urlencode(get('bulan', date('Y-m'))));
}

/* ---------- Ambil data ---------- */
$bulan = preg_match('/^\d{4}-\d{2}$/', get('bulan')) ? get('bulan') : date('Y-m');

$daftar_pengeluaran = ambil_semua(
    "SELECT * FROM pengeluaran WHERE DATE_FORMAT(tanggal, '%Y-%m') = ? ORDER BY tanggal DESC, id DESC",
    's', [$bulan]
);
$total = array_sum(array_column($daftar_pengeluaran, 'jumlah'));

/* ---------- Tampilan ---------- */
$judul_halaman = 'Pengeluaran';
$menu_aktif    = 'pengeluaran';
include __DIR__ . '/../includes/layout/admin_top.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 mb-0">Pengeluaran Operasional</h1>
        <p class="text-body-secondary mb-0">Biaya di luar modal barang; mengurangi laba bersih di laporan.</p>
    </div>
    <form method="get" class="d-flex gap-2">
        <input type="month" name="bulan" class="form-control" value="<?= e($bulan) ?>">
        <button class="btn btn-outline-primary" type="submit">Tampilkan</button>
    </form>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <form method="post" class="card">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="tambah">
            <div class="card-header fw-semibold"><i class="bi bi-plus-circle me-1"></i> Catat Pengeluaran</div>
            <div class="card-body">
                <div class="mb-3"><label class="form-label" for="tgl">Tanggal</label>
                    <input type="date" name="tanggal" id="tgl" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                <div class="mb-3"><label class="form-label" for="kat">Kategori</label>
                    <select name="kategori_pengeluaran" id="kat" class="form-select">
                        <?php foreach (KATEGORI_PENGELUARAN as $k): ?><option><?= e($k) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="mb-3"><label class="form-label" for="jml">Jumlah</label>
                    <div class="input-group"><span class="input-group-text">Rp</span>
                        <input type="number" name="jumlah" id="jml" class="form-control" min="1" required></div></div>
                <div><label class="form-label" for="ket">Keterangan</label>
                    <input type="text" name="keterangan" id="ket" class="form-control"></div>
            </div>
            <div class="card-footer"><button type="submit" class="btn btn-primary w-100">Simpan</button></div>
        </form>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <span class="fw-semibold">Bulan <?= e(date('m/Y', strtotime($bulan . '-01'))) ?></span>
                <span>Total: <strong class="text-danger"><?= rupiah($total) ?></strong></span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Tanggal</th><th>Kategori</th><th>Keterangan</th><th class="text-end">Jumlah</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($daftar_pengeluaran as $p): ?>
                            <tr>
                                <td class="text-nowrap"><?= tanggal_indo($p['tanggal']) ?></td>
                                <td><span class="badge text-bg-secondary"><?= e($p['kategori_pengeluaran']) ?></span></td>
                                <td><?= e($p['keterangan']) ?></td>
                                <td class="text-end fw-semibold"><?= rupiah($p['jumlah']) ?></td>
                                <td class="text-end">
                                    <form method="post" data-konfirmasi="Hapus catatan pengeluaran ini?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="aksi" value="hapus">
                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$daftar_pengeluaran): ?>
                            <tr><td colspan="5" class="text-center text-body-secondary py-5">Belum ada pengeluaran bulan ini.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/layout/admin_bottom.php'; ?>
