<?php
// admin/laporan.php - Laporan laba rugi per periode.
require_once __DIR__ . '/../includes/init_admin.php';
require_once __DIR__ . '/../includes/laporan.php';

$tgl_mulai   = tanggal_dari_url('tgl_mulai', '');
$tgl_selesai = tanggal_dari_url('tgl_selesai', '');
$lap         = hitung_laporan($tgl_mulai, $tgl_selesai);

$judul_halaman = 'Laporan Laba Rugi';
$menu_aktif    = 'laporan';
include __DIR__ . '/../includes/layout/admin_top.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 mb-0">Laporan Laba Rugi</h1>
        <p class="text-body-secondary mb-0">Memisahkan modal barang dari keuntungan bersih pemilik.</p>
    </div>
    <a href="cetak_laporan.php?<?= e(http_build_query(array_filter(['tgl_mulai' => $tgl_mulai, 'tgl_selesai' => $tgl_selesai], static fn($tanggal) => $tanggal !== ''))) ?>" target="_blank" class="btn btn-outline-secondary">
        <i class="bi bi-printer me-1"></i> Cetak Laporan
    </a>
</div>

<form method="get" class="card card-body mb-4">
    <div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label small mb-1">Dari tanggal</label>
            <input type="date" name="tgl_mulai" class="form-control" value="<?= e($tgl_mulai) ?>"></div>
        <div class="col-md-3"><label class="form-label small mb-1">Sampai tanggal</label>
            <input type="date" name="tgl_selesai" class="form-control" value="<?= e($tgl_selesai) ?>"></div>
        <div class="col-md-6 d-flex flex-wrap gap-2">
            <button class="btn btn-primary" type="submit">Terapkan</button>
            <a class="btn btn-outline-secondary" href="laporan.php">Semua riwayat</a>
            <a class="btn btn-outline-secondary" href="?tgl_mulai=<?= date('Y-m-d') ?>&tgl_selesai=<?= date('Y-m-d') ?>">Hari ini</a>
            <a class="btn btn-outline-secondary" href="?tgl_mulai=<?= date('Y-m-01') ?>&tgl_selesai=<?= date('Y-m-d') ?>">Bulan ini</a>
        </div>
    </div>
</form>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header fw-semibold">
                Periode <?= $tgl_mulai === '' && $tgl_selesai === '' ? 'Semua Riwayat' : ($tgl_mulai !== '' ? tanggal_indo($tgl_mulai) : 'Awal') . ' s.d. ' . ($tgl_selesai !== '' ? tanggal_indo($tgl_selesai) : 'Sekarang') ?>
            </div>
            <table class="table mb-0 align-middle">
                <tbody>
                    <tr>
                        <td><strong>1. Total Penjualan (Omset)</strong><small class="d-block text-body-secondary"><?= $lap['jumlah_nota'] ?> nota</small></td>
                        <td class="text-end h5 mb-0"><?= rupiah($lap['omset']) ?></td>
                    </tr>
                    <tr>
                        <td><strong>2. Harga Pokok Penjualan (HPP)</strong><small class="d-block text-body-secondary">Modal beli barang yang terjual</small></td>
                        <td class="text-end h5 mb-0 text-body-secondary">(<?= rupiah($lap['hpp']) ?>)</td>
                    </tr>
                    <tr class="table-light">
                        <td><strong>Laba Kotor (1 &minus; 2)</strong></td>
                        <td class="text-end h5 mb-0 text-primary"><?= rupiah($lap['laba_kotor']) ?></td>
                    </tr>
                    <tr>
                        <td><strong>3. Pengeluaran Operasional</strong><small class="d-block text-body-secondary">Listrik, bensin kulakan, kantong plastik, dll.</small></td>
                        <td class="text-end h5 mb-0 text-danger">(<?= rupiah($lap['biaya']) ?>)</td>
                    </tr>
                    <tr class="table-success">
                        <td class="py-3"><strong class="fs-5">LABA BERSIH</strong><small class="d-block">Laba kotor &minus; pengeluaran</small></td>
                        <td class="text-end h4 mb-0 py-3"><?= rupiah($lap['laba_bersih']) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-body-secondary small">Margin laba bersih</div>
                <div class="display-5 fw-bold <?= $lap['margin'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= $lap['margin'] ?>%</div>
                <p class="text-body-secondary mb-0">
                    Persentase laba bersih dari setiap rupiah penjualan pada periode yang dipilih.
                </p>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/layout/admin_bottom.php'; ?>
