<?php
// admin/laporan.php - Laporan Laba Rugi & Keuangan (Clean Retail Style)
$page_title = 'Laporan Laba Rugi';
require_once __DIR__ . '/header.php';

// Filter Periode
$tgl_mulai   = $_GET['tgl_mulai'] ?? date('Y-m-01');
$tgl_selesai = $_GET['tgl_selesai'] ?? date('Y-m-d');

// Penjualan & HPP
$q_sales = $koneksi->prepare("SELECT 
            COALESCE(SUM(td.subtotal), 0) AS total_penjualan,
            COALESCE(SUM(td.harga_beli * td.qty), 0) AS total_hpp,
            COALESCE(SUM(td.keuntungan), 0) AS laba_kotor,
            COUNT(DISTINCT t.id) AS total_transaksi
          FROM transaksi t
          JOIN transaksi_detail td ON t.id = td.transaksi_id
          WHERE DATE(t.tanggal) BETWEEN ? AND ?");
$q_sales->bind_param("ss", $tgl_mulai, $tgl_selesai);
$q_sales->execute();
$sales = $q_sales->get_result()->fetch_assoc();

$total_omset = (float)$sales['total_penjualan'];
$total_hpp   = (float)$sales['total_hpp'];
$laba_kotor  = (float)$sales['laba_kotor'];
$total_trx   = (int)$sales['total_transaksi'];

// Biaya Operasional
$q_biaya = $koneksi->prepare("SELECT COALESCE(SUM(jumlah), 0) AS total_biaya FROM pengeluaran WHERE tanggal BETWEEN ? AND ?");
$q_biaya->bind_param("ss", $tgl_mulai, $tgl_selesai);
$q_biaya->execute();
$total_biaya = (float)$q_biaya->get_result()->fetch_assoc()['total_biaya'];

$laba_bersih = $laba_kotor - $total_biaya;
$margin_persen = ($total_omset > 0) ? round(($laba_bersih / $total_omset) * 100, 1) : 0;
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="fw-bold text-dark mb-1">Laporan Laba Rugi Toko Najwa</h4>
        <p class="text-muted small mb-0">Perhitungan laba bersih otomatis untuk memisahkan modal kulakan dan profit pribadi.</p>
    </div>
    <a href="cetak_laporan.php?tgl_mulai=<?= $tgl_mulai; ?>&tgl_selesai=<?= $tgl_selesai; ?>" target="_blank" class="btn btn-pill-white shadow-sm">
        <i class="bi bi-printer me-1"></i> Cetak Format Dokumen
    </a>
</div>

<!-- Filter Periode -->
<div class="bento-card-clean mb-4 p-3">
    <form method="GET" action="laporan.php" class="row g-2 align-items-center">
        <div class="col-md-4">
            <label class="form-label small fw-semibold text-muted mb-1">Dari Tanggal</label>
            <input type="date" name="tgl_mulai" class="form-control form-control-sm rounded-pill px-3" value="<?= htmlspecialchars($tgl_mulai); ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-semibold text-muted mb-1">Sampai Tanggal</label>
            <input type="date" name="tgl_selesai" class="form-control form-control-sm rounded-pill px-3" value="<?= htmlspecialchars($tgl_selesai); ?>">
        </div>
        <div class="col-md-4 d-flex align-items-end gap-1 pt-4">
            <button type="submit" class="btn btn-sm btn-pill-green py-1 px-3 w-100"><i class="bi bi-funnel"></i> Terapkan</button>
            <a href="laporan.php?tgl_mulai=<?= date('Y-m-d'); ?>&tgl_selesai=<?= date('Y-m-d'); ?>" class="btn btn-sm btn-pill-white py-1 px-3 text-nowrap">Hari Ini</a>
            <a href="laporan.php?tgl_mulai=<?= date('Y-m-01'); ?>&tgl_selesai=<?= date('Y-m-d'); ?>" class="btn btn-sm btn-pill-white py-1 px-3 text-nowrap">Bulan Ini</a>
        </div>
    </form>
</div>

<!-- Rekapitulasi Laba Rugi Bersih -->
<div class="row g-4">
    <div class="col-lg-8">
        <div class="bento-card-clean p-4">
            <h6 class="fw-bold text-dark mb-3">Rekapitulasi Keuangan Periode: <?= tgl_indo($tgl_mulai); ?> s.d. <?= tgl_indo($tgl_selesai); ?></h6>
            
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <tbody>
                        <tr>
                            <td class="py-3">
                                <strong class="text-dark d-block">1. Total Penjualan Kasir (Omset)</strong>
                                <small class="text-muted"><?= $total_trx; ?> Transaksi Pelanggan Selesai</small>
                            </td>
                            <td class="text-end fw-bold text-dark fs-5 py-3"><?= rupiah($total_omset); ?></td>
                        </tr>
                        <tr>
                            <td class="py-3">
                                <strong class="text-dark d-block">2. Harga Pokok Penjualan (HPP)</strong>
                                <small class="text-muted">Total modal beli untuk barang-barang yang terjual</small>
                            </td>
                            <td class="text-end text-muted fs-5 py-3">(<?= rupiah($total_hpp); ?>)</td>
                        </tr>
                        <tr class="bg-light">
                            <td class="py-3 ps-3">
                                <strong class="text-primary">LABA KOTOR PENJUALAN (1 - 2)</strong>
                            </td>
                            <td class="text-end fw-bold text-primary fs-5 py-3 pe-3"><?= rupiah($laba_kotor); ?></td>
                        </tr>
                        <tr>
                            <td class="py-3">
                                <strong class="text-dark d-block">3. Pengeluaran Operasional Toko</strong>
                                <small class="text-muted">Listrik, kantong plastik kresek, bensin kulakan pasar</small>
                            </td>
                            <td class="text-end text-danger fs-5 py-3">(<?= rupiah($total_biaya); ?>)</td>
                        </tr>
                        <tr class="border-top border-2" style="background: #eaf5ee;">
                            <td class="py-4 ps-3">
                                <h5 class="fw-bold mb-0 text-success">LABA BERSIH RIIL USAHA</h5>
                                <small class="text-success opacity-75">Keuntungan bersih yang siap dinikmati pemilik</small>
                            </td>
                            <td class="text-end fw-bold text-success display-6 py-4 pe-3 fs-3">
                                <?= rupiah($laba_bersih); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="bento-card-clean p-4 justify-content-between">
            <div>
                <span class="small text-muted fw-semibold d-block mb-1">Net Margin Keuntungan:</span>
                <h2 class="fw-bold text-success mb-2"><?= $margin_persen; ?>%</h2>
                <p class="text-muted small">
                    Rata-rata margin laba bersih dari setiap sembako yang terjual di Toko Najwa pada periode yang dipilih.
                </p>
            </div>
            <div class="p-3 bg-light rounded-4 border mt-3">
                <small class="fw-bold text-dark d-block mb-1">Catatan Presentasi:</small>
                <small class="text-muted">
                    Laporan ini menjawab persoalan utama pada <strong>Bab I laporan SMK</strong>, yaitu sulitnya pemilik warung memantau laba harian secara tepat dan risiko modal usaha tergerus belanja kebutuhan pribadi.
                </small>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
