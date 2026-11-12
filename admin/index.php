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

/* ---------- Tampilan ---------- */
$judul_halaman = 'Dashboard';
$menu_aktif    = 'dashboard';
include __DIR__ . '/../includes/layout/admin_top.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 mb-0">Dashboard</h1>
        <p class="text-body-secondary mb-0"><?= tanggal_indo(date('Y-m-d')) ?> &middot; Selamat datang, <?= e(user_login()['nama']) ?></p>
    </div>
    <a href="pos.php" class="btn btn-primary">Buka Kasir</a>
</div>

<!-- Ringkasan -->
<div class="row g-3 mb-4 ringkasan">
    <div class="col-sm-4">
        <div class="card h-100"><div class="card-body">
            <div class="text-body-secondary small">Omset Hari Ini</div>
            <div class="angka"><?= rupiah($hari_ini['omset']) ?></div>
            <small class="text-body-secondary"><?= $hari_ini['jumlah_nota'] ?> nota</small>
        </div></div>
    </div>
    <div class="col-sm-4">
        <div class="card h-100"><div class="card-body">
            <div class="text-body-secondary small">Laba Kotor Hari Ini</div>
            <div class="angka text-success"><?= rupiah($laba_hari_ini) ?></div>
            <small class="text-body-secondary">Selisih harga jual &amp; modal</small>
        </div></div>
    </div>
    <div class="col-sm-4">
        <div class="card h-100"><div class="card-body">
            <div class="text-body-secondary small">Kasbon Belum Lunas</div>
            <div class="angka text-danger"><?= rupiah($kasbon['total']) ?></div>
            <small class="text-body-secondary"><?= $kasbon['orang'] ?> warga</small>
        </div></div>
    </div>
</div>

<div class="row g-4">
    <!-- Kadaluarsa -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Mendekati Kadaluarsa</span>
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
                <span class="fw-semibold">Stok Menipis</span>
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
            <div class="card-header fw-semibold">Produk Terlaris</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($produk_terlaris as $no => $p): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-body-secondary me-2"><?= $no + 1 ?>.</span><?= e($p['nama_produk']) ?>
                            <small class="text-body-secondary d-block ms-4"><?= e($p['nama_kategori']) ?></small>
                        </div>
                        <span class="badge text-bg-secondary"><?= $p['terjual'] ?> terjual</span>
                    </li>
                <?php endforeach; ?>
                <?php if (!$produk_terlaris): ?>
                    <li class="list-group-item text-body-secondary text-center py-4">Belum ada penjualan.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

</div>

<?php include __DIR__ . '/../includes/layout/admin_bottom.php'; ?>
