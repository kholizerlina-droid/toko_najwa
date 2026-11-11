<?php
// admin/index.php - Dashboard: ringkasan hari ini, peringatan, produk terlaris.
require_once __DIR__ . '/../includes/init_admin.php';

/* ---------- Ambil data ---------- */
$hari_ini = ambil_satu(
    "SELECT COALESCE(SUM(total_harga), 0) AS omset, COUNT(*) AS jumlah_nota
     FROM transaksi WHERE DATE(tanggal) = CURDATE()"
);
$laba_hari_ini = ambil_nilai(
    "SELECT COALESCE(SUM(td.keuntungan), 0)
     FROM transaksi_detail td JOIN transaksi t ON t.id = td.transaksi_id
     WHERE DATE(t.tanggal) = CURDATE()"
);
$kasbon = ambil_satu(
    "SELECT COALESCE(SUM(sisa_utang), 0) AS total, COUNT(*) AS orang
     FROM piutang WHERE status = 'belum_lunas'"
);

$produk_kadaluarsa = ambil_semua(
    "SELECT nama_produk, stok, satuan, tgl_kadaluarsa FROM produk
     WHERE tgl_kadaluarsa IS NOT NULL AND tgl_kadaluarsa <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     ORDER BY tgl_kadaluarsa ASC LIMIT 5"
);
$produk_menipis = ambil_semua(
    "SELECT nama_produk, stok, min_stok, satuan FROM produk
     WHERE stok <= min_stok ORDER BY stok ASC LIMIT 5"
);
$produk_terlaris = ambil_semua(
    "SELECT p.nama_produk, k.nama_kategori, SUM(td.qty) AS terjual
     FROM transaksi_detail td
     JOIN produk p ON p.id = td.produk_id
     JOIN kategori k ON k.id = p.kategori_id
     GROUP BY p.id ORDER BY terjual DESC LIMIT 5"
);
$transaksi_terakhir = ambil_semua(
    "SELECT no_faktur, nama_pelanggan, total_harga, tanggal
     FROM transaksi ORDER BY tanggal DESC LIMIT 5"
);
$jumlah_pesanan_pending = (int) ambil_nilai(
    "SELECT COUNT(*) FROM pesanan_online
     WHERE transaksi_id IS NULL AND status IN ('baru', 'diproses')"
);
$pesanan_pending = ambil_semua(
    "SELECT id, kode_pesanan, nama_pemesan, total, created_at
     FROM pesanan_online
     WHERE transaksi_id IS NULL AND status IN ('baru', 'diproses')
     ORDER BY created_at DESC LIMIT 5"
);

/* ---------- Tampilan ---------- */
$judul_halaman = 'Dashboard';
$menu_aktif    = 'dashboard';
$skrip_tambahan = ['dashboard.js'];
include __DIR__ . '/../includes/layout/admin_top.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 mb-0">Dashboard</h1>
        <p class="text-body-secondary mb-0"><?= tanggal_indo(date('Y-m-d')) ?> &middot; Selamat datang, <?= e(user_login()['nama']) ?></p>
    </div>
    <a href="pos.php" class="btn btn-primary"><i class="bi bi-cart3 me-1"></i> Buka Kasir</a>
</div>

<!-- Pop-up otomatis untuk pesanan online yang belum diproses kasir -->
<?php if ($jumlah_pesanan_pending > 0): ?>
<div class="modal fade" id="modalPesananMenunggu" tabindex="-1" aria-labelledby="judulPesananMenunggu" aria-hidden="true" data-auto-show="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary-subtle">
                <div>
                    <h2 class="modal-title fs-5" id="judulPesananMenunggu"><i class="bi bi-bell me-2"></i>Pesanan online baru</h2>
                    <small class="text-body-secondary"><?= $jumlah_pesanan_pending ?> pesanan menunggu diproses</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body p-0">
                <ul class="list-group list-group-flush">
                    <?php foreach ($pesanan_pending as $p): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center gap-3 py-3">
                            <div class="min-w-0">
                                <div class="fw-semibold text-truncate"><?= e($p['nama_pemesan']) ?></div>
                                <small class="font-monospace text-body-secondary"><?= e($p['kode_pesanan']) ?></small>
                                <small class="d-block text-body-secondary"><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?> &middot; <?= rupiah($p['total']) ?></small>
                            </div>
                            <a href="pos.php?pesanan=<?= (int) $p['id'] ?>" class="btn btn-sm btn-primary flex-shrink-0"><i class="bi bi-cart-check me-1"></i>Proses</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($jumlah_pesanan_pending > count($pesanan_pending)): ?>
                    <div class="px-3 py-2 small text-body-secondary">Menampilkan 5 pesanan terbaru. Buka halaman Pesanan Online untuk melihat semuanya.</div>
                <?php endif; ?>
            </div>
            <div class="modal-footer justify-content-between">
                <a href="pesanan.php" class="btn btn-link text-decoration-none px-0">Lihat semua pesanan</a>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Nanti</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Ringkasan -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-body-secondary small">Omset Hari Ini</div>
            <div class="h4 mb-0"><?= rupiah($hari_ini['omset']) ?></div>
            <small class="text-body-secondary"><?= $hari_ini['jumlah_nota'] ?> nota</small>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-body-secondary small">Laba Kotor Hari Ini</div>
            <div class="h4 mb-0 text-success"><?= rupiah($laba_hari_ini) ?></div>
            <small class="text-body-secondary">Selisih harga jual &amp; modal</small>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-body-secondary small">Kasbon Belum Lunas</div>
            <div class="h4 mb-0 text-danger"><?= rupiah($kasbon['total']) ?></div>
            <small class="text-body-secondary"><?= $kasbon['orang'] ?> warga</small>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-body-secondary small">Perlu Perhatian</div>
            <div class="h4 mb-0"><?= count($produk_kadaluarsa) ?> <small class="fs-6 text-body-secondary">kadaluarsa</small>
                / <?= count($produk_menipis) ?> <small class="fs-6 text-body-secondary">stok</small></div>
            <small class="text-body-secondary">Kadaluarsa &le; 30 hari &amp; stok menipis</small>
        </div></div>
    </div>
</div>

<div class="row g-4">
    <!-- Kadaluarsa -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-calendar-x text-danger me-1"></i> Mendekati Kadaluarsa</span>
                <a href="produk.php?filter_exp=segera" class="small">Lihat semua</a>
            </div>
            <ul class="list-group list-group-flush">
                <?php foreach ($produk_kadaluarsa as $p): $st = status_kadaluarsa($p['tgl_kadaluarsa']); ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <div><?= e($p['nama_produk']) ?></div>
                            <small class="text-body-secondary">Stok <?= $p['stok'] ?> <?= e($p['satuan']) ?></small>
                        </div>
                        <span class="badge <?= $st['badge'] ?>"><?= e($st['label']) ?></span>
                    </li>
                <?php endforeach; ?>
                <?php if (!$produk_kadaluarsa): ?>
                    <li class="list-group-item text-body-secondary text-center py-4">Semua barang aman dari kadaluarsa.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <!-- Stok menipis -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-exclamation-triangle text-warning me-1"></i> Stok Menipis</span>
                <a href="produk.php?filter_stok=menipis" class="small">Lihat semua</a>
            </div>
            <ul class="list-group list-group-flush">
                <?php foreach ($produk_menipis as $p): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <div><?= e($p['nama_produk']) ?></div>
                            <small class="text-body-secondary">Batas minimal <?= $p['min_stok'] ?></small>
                        </div>
                        <span class="badge text-bg-danger">Sisa <?= $p['stok'] ?> <?= e($p['satuan']) ?></span>
                    </li>
                <?php endforeach; ?>
                <?php if (!$produk_menipis): ?>
                    <li class="list-group-item text-body-secondary text-center py-4">Semua stok masih mencukupi.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <!-- Terlaris -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold"><i class="bi bi-fire text-danger me-1"></i> Produk Terlaris</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($produk_terlaris as $no => $p): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-body-secondary me-2"><?= $no + 1 ?>.</span><?= e($p['nama_produk']) ?>
                            <small class="text-body-secondary d-block ms-4"><?= e($p['nama_kategori']) ?></small>
                        </div>
                        <span class="badge text-bg-success"><?= $p['terjual'] ?> terjual</span>
                    </li>
                <?php endforeach; ?>
                <?php if (!$produk_terlaris): ?>
                    <li class="list-group-item text-body-secondary text-center py-4">Belum ada penjualan.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <!-- Transaksi terakhir -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-receipt me-1"></i> Transaksi Terakhir</span>
                <a href="transaksi.php" class="small">Lihat semua</a>
            </div>
            <ul class="list-group list-group-flush">
                <?php foreach ($transaksi_terakhir as $t): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <div class="font-monospace small"><?= e($t['no_faktur']) ?></div>
                            <small class="text-body-secondary"><?= e($t['nama_pelanggan']) ?> &middot; <?= tanggal_indo($t['tanggal'], true) ?></small>
                        </div>
                        <div class="text-end">
                            <div class="fw-semibold"><?= rupiah($t['total_harga']) ?></div>
                            <a href="cetak_struk.php?faktur=<?= urlencode($t['no_faktur']) ?>" target="_blank" class="small">Struk</a>
                        </div>
                    </li>
                <?php endforeach; ?>
                <?php if (!$transaksi_terakhir): ?>
                    <li class="list-group-item text-body-secondary text-center py-4">Belum ada transaksi.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/layout/admin_bottom.php'; ?>
