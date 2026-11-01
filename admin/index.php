<?php
// admin/index.php - Dashboard Manajemen Toko Najwa (Clean Retail Style)
$page_title = 'Dashboard Manajemen';
require_once __DIR__ . '/header.php';

$today = date('Y-m-d');

// 1. Omset & Laba Hari Ini
$q_omset = $koneksi->query("SELECT COALESCE(SUM(total_harga), 0) AS total, COUNT(id) AS jml_trx FROM transaksi WHERE DATE(tanggal) = '$today'");
$row_omset = $q_omset->fetch_assoc();
$omset_today = $row_omset['total'];
$trx_today = $row_omset['jml_trx'];

$q_laba = $koneksi->query("SELECT COALESCE(SUM(td.keuntungan), 0) AS total_laba 
                          FROM transaksi_detail td 
                          JOIN transaksi t ON td.transaksi_id = t.id 
                          WHERE DATE(t.tanggal) = '$today'");
$laba_today = $q_laba->fetch_assoc()['total_laba'];

// 2. Piutang / Kasbon Belum Lunas
$q_piutang = $koneksi->query("SELECT COALESCE(SUM(sisa_utang), 0) AS total_piutang, COUNT(id) AS jml_debitur FROM piutang WHERE status = 'belum_lunas'");
$row_piutang = $q_piutang->fetch_assoc();
$total_piutang = $row_piutang['total_piutang'];
$jml_debitur = $row_piutang['jml_debitur'];

// 3. Alert Kadaluarsa (Fokus SWOT)
$q_exp_list = $koneksi->query("SELECT p.*, k.nama_kategori, DATEDIFF(p.tgl_kadaluarsa, CURDATE()) AS sisa_hari 
                              FROM produk p 
                              JOIN kategori k ON p.kategori_id = k.id 
                              WHERE p.tgl_kadaluarsa IS NOT NULL AND p.tgl_kadaluarsa <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) 
                              ORDER BY p.tgl_kadaluarsa ASC LIMIT 4");
$exp_items = $q_exp_list->fetch_all(MYSQLI_ASSOC);

// 4. Alert Stok Menipis
$q_low_stock = $koneksi->query("SELECT p.*, k.nama_kategori 
                               FROM produk p 
                               JOIN kategori k ON p.kategori_id = k.id 
                               WHERE p.stok <= p.min_stok 
                               ORDER BY p.stok ASC LIMIT 4");
$low_stock_items = $q_low_stock->fetch_all(MYSQLI_ASSOC);

// 5. Fast-Moving (Top 4 Terlaris)
$q_fast = $koneksi->query("SELECT p.nama_produk, k.nama_kategori, COALESCE(SUM(td.qty), 0) AS total_terjual 
                          FROM produk p 
                          LEFT JOIN transaksi_detail td ON p.id = td.produk_id 
                          JOIN kategori k ON p.kategori_id = k.id 
                          GROUP BY p.id 
                          HAVING total_terjual > 0 
                          ORDER BY total_terjual DESC LIMIT 4");
$fast_moving = $q_fast->fetch_all(MYSQLI_ASSOC);

// 6. Transaksi Terakhir
$q_recent_trx = $koneksi->query("SELECT t.*, u.nama_lengkap AS kasir 
                                FROM transaksi t 
                                LEFT JOIN users u ON t.user_id = u.id 
                                ORDER BY t.tanggal DESC LIMIT 4");
$recent_trx = $q_recent_trx->fetch_all(MYSQLI_ASSOC);
?>

<div class="mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h4 class="fw-bold text-dark mb-1">Dashboard Operasional</h4>
            <p class="text-muted small mb-0">Ringkasan transaksi kasir, keuangan, dan status inventaris Warung Sembako Toko Najwa.</p>
        </div>
        <a href="pos.php" class="btn btn-pill-green shadow-sm">
            <i class="bi bi-cart3"></i>
            <span>Buka Kasir POS</span>
        </a>
    </div>
</div>

<!-- 4 Metrik Utama Bersih -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="bento-card-clean">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="small text-muted fw-semibold">Omset Hari Ini</span>
                <span class="stat-metric-badge bg-success bg-opacity-10 text-success"><?= $trx_today; ?> Nota</span>
            </div>
            <h3 class="fw-bold text-dark mb-0"><?= rupiah($omset_today); ?></h3>
            <small class="text-muted" style="font-size: 0.72rem;">Penjualan harian toko</small>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="bento-card-clean">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="small text-muted fw-semibold">Laba Kotor Hari Ini</span>
                <span class="stat-metric-badge bg-primary bg-opacity-10 text-primary">Margin</span>
            </div>
            <h3 class="fw-bold text-success mb-0"><?= rupiah($laba_today); ?></h3>
            <small class="text-muted" style="font-size: 0.72rem;">Keuntungan dari barang terjual</small>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="bento-card-clean">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="small text-muted fw-semibold">Kasbon Belum Lunas</span>
                <span class="stat-metric-badge bg-danger bg-opacity-10 text-danger"><?= $jml_debitur; ?> Orang</span>
            </div>
            <h3 class="fw-bold text-danger mb-0"><?= rupiah($total_piutang); ?></h3>
            <small class="text-muted" style="font-size: 0.72rem;">Piutang warga langganan</small>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="bento-card-clean">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="small text-muted fw-semibold">Perlu Tindakan (SWOT)</span>
                <span class="stat-metric-badge bg-warning bg-opacity-25 text-dark"><?= count($exp_items) + count($low_stock_items); ?> Alert</span>
            </div>
            <h3 class="fw-bold text-dark mb-0"><?= count($exp_items); ?> <small class="fs-6 text-muted">Exp</small> / <?= count($low_stock_items); ?> <small class="fs-6 text-muted">Stok</small></h3>
            <small class="text-muted" style="font-size: 0.72rem;">Deteksi kadaluarsa & stok menipis</small>
        </div>
    </div>
</div>

<!-- Row Peringatan SWOT & Fast-Moving -->
<div class="row g-4 mb-4">
    <!-- Peringatan Kadaluarsa (Hasil Analisis SWOT) -->
    <div class="col-lg-6">
        <div class="bento-card-clean">
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="bento-icon bg-danger bg-opacity-10 text-danger" style="width: 38px; height: 38px; font-size: 1.1rem; border-radius: 12px;">
                        <i class="bi bi-calendar-x"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-0">Deteksi Kadaluarsa (SWOT Alert)</h6>
                        <small class="text-muted" style="font-size: 0.75rem;">Barang mendekati tanggal expired</small>
                    </div>
                </div>
                <a href="produk.php?filter_exp=warning" class="small text-decoration-none text-danger fw-semibold">Lihat Semua</a>
            </div>

            <?php if (empty($exp_items)): ?>
                <div class="py-4 text-center text-muted small">
                    <i class="bi bi-shield-check text-success fs-2 d-block mb-1"></i>
                    Seluruh barang terpantau aman.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                            <?php foreach ($exp_items as $ei): 
                                $st = cek_status_kadaluarsa($ei['tgl_kadaluarsa']);
                            ?>
                                <tr>
                                    <td>
                                        <strong class="d-block text-dark small"><?= htmlspecialchars($ei['nama_produk']); ?></strong>
                                        <span class="text-muted" style="font-size: 0.72rem;">Stok: <?= $ei['stok']; ?> <?= $ei['satuan']; ?></span>
                                    </td>
                                    <td class="text-end">
                                        <span class="badge bg-<?= $st['badge']; ?> rounded-pill small"><?= $st['label']; ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Peringatan Stok Menipis -->
    <div class="col-lg-6">
        <div class="bento-card-clean">
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="bento-icon bg-warning bg-opacity-15 text-warning" style="width: 38px; height: 38px; font-size: 1.1rem; border-radius: 12px;">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-0">Stok Menipis / Perlu Kulakan</h6>
                        <small class="text-muted" style="font-size: 0.75rem;">Stok di bawah batas minimal</small>
                    </div>
                </div>
                <a href="produk.php?filter_stok=low" class="small text-decoration-none text-dark fw-semibold">Lihat Semua</a>
            </div>

            <?php if (empty($low_stock_items)): ?>
                <div class="py-4 text-center text-muted small">
                    <i class="bi bi-check-circle text-success fs-2 d-block mb-1"></i>
                    Semua stok sembako mencukupi.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                            <?php foreach ($low_stock_items as $lsi): ?>
                                <tr>
                                    <td>
                                        <strong class="d-block text-dark small"><?= htmlspecialchars($lsi['nama_produk']); ?></strong>
                                        <span class="text-muted" style="font-size: 0.72rem;">Batas min: <?= $lsi['min_stok']; ?> <?= $lsi['satuan']; ?></span>
                                    </td>
                                    <td class="text-end">
                                        <span class="badge bg-danger rounded-pill">Sisa <?= $lsi['stok']; ?> <?= $lsi['satuan']; ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Row Produk Terlaris & Transaksi Terakhir -->
<div class="row g-4">
    <!-- Fast-Moving Products -->
    <div class="col-lg-6">
        <div class="bento-card-clean">
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="bento-icon bg-success bg-opacity-10 text-success" style="width: 38px; height: 38px; font-size: 1.1rem; border-radius: 12px;">
                        <i class="bi bi-fire"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-0">Produk Terlaris (Fast-Moving)</h6>
                        <small class="text-muted" style="font-size: 0.75rem;">Sering dibeli warga secara konsisten</small>
                    </div>
                </div>
                <span class="badge bg-light text-secondary border rounded-pill">Top 4</span>
            </div>

            <?php if (empty($fast_moving)): ?>
                <div class="py-4 text-center text-muted small">Belum ada transaksi.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                            <?php foreach ($fast_moving as $idx => $fm): ?>
                                <tr>
                                    <td style="width: 30px;">
                                        <span class="badge bg-light text-dark border rounded-circle" style="width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;"><?= $idx + 1; ?></span>
                                    </td>
                                    <td>
                                        <strong class="d-block text-dark small"><?= htmlspecialchars($fm['nama_produk']); ?></strong>
                                        <span class="text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($fm['nama_kategori']); ?></span>
                                    </td>
                                    <td class="text-end">
                                        <strong class="text-success small"><?= $fm['total_terjual']; ?> Terjual</strong>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Transaksi Terakhir -->
    <div class="col-lg-6">
        <div class="bento-card-clean">
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="bento-icon bg-light text-dark border" style="width: 38px; height: 38px; font-size: 1.1rem; border-radius: 12px;">
                        <i class="bi bi-receipt"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-0">Transaksi Kasir Terakhir</h6>
                        <small class="text-muted" style="font-size: 0.75rem;">Riwayat struk penjualan</small>
                    </div>
                </div>
                <a href="pos.php" class="small text-decoration-none text-success fw-semibold">+ Transaksi Baru</a>
            </div>

            <?php if (empty($recent_trx)): ?>
                <div class="py-4 text-center text-muted small">Belum ada transaksi.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                            <?php foreach ($recent_trx as $rt): ?>
                                <tr>
                                    <td>
                                        <strong class="d-block text-dark small"><?= htmlspecialchars($rt['no_faktur']); ?></strong>
                                        <span class="text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($rt['nama_pelanggan']); ?></span>
                                    </td>
                                    <td class="text-end">
                                        <strong class="text-success small"><?= rupiah($rt['total_harga']); ?></strong>
                                    </td>
                                    <td class="text-end">
                                        <a href="cetak_struk.php?faktur=<?= urlencode($rt['no_faktur']); ?>" target="_blank" class="btn btn-sm btn-light border rounded-pill px-3 py-1" style="font-size: 0.75rem;">
                                            <i class="bi bi-printer me-1"></i> Struk
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
