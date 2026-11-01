<?php
// admin/cetak_laporan.php - Cetak Dokumen Laporan Keuangan Toko Najwa
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

require_login();
$pengaturan = get_pengaturan($koneksi);

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

// Biaya
$q_biaya_kat = $koneksi->prepare("SELECT kategori_pengeluaran, SUM(jumlah) AS subtotal FROM pengeluaran WHERE tanggal BETWEEN ? AND ? GROUP BY kategori_pengeluaran");
$q_biaya_kat->bind_param("ss", $tgl_mulai, $tgl_selesai);
$q_biaya_kat->execute();
$biaya_kategori = $q_biaya_kat->get_result()->fetch_all(MYSQLI_ASSOC);

$total_biaya = 0;
foreach ($biaya_kategori as $bk) {
    $total_biaya += $bk['subtotal'];
}

$laba_bersih = $laba_kotor - $total_biaya;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Laporan Keuangan - <?= htmlspecialchars($pengaturan['nama_toko']); ?></title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            color: #000;
            background: #fff;
            padding: 30px;
        }
        .kop-surat {
            border-bottom: 3px double #000;
            padding-bottom: 12px;
            margin-bottom: 24px;
            text-align: center;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding: 0;
            }
        }
    </style>
</head>
<body>

<div class="container no-print mb-4 text-end">
    <button onclick="window.print()" class="btn btn-success fw-bold">
        Cetak Laporan (Print / Simpan PDF)
    </button>
    <a href="laporan.php" class="btn btn-outline-secondary">Kembali</a>
</div>

<div class="container" style="max-width: 800px;">
    <!-- KOP RESMI TOKO -->
    <div class="kop-surat">
        <h3 class="fw-bold mb-0 text-uppercase"><?= htmlspecialchars($pengaturan['nama_toko']); ?></h3>
        <p class="mb-0 fs-6"><?= htmlspecialchars($pengaturan['alamat']); ?></p>
        <p class="mb-0 fs-6">Telepon / WhatsApp: <?= htmlspecialchars($pengaturan['telepon']); ?> • Pemilik: <?= htmlspecialchars($pengaturan['pemilik']); ?></p>
    </div>

    <div class="text-center mb-4">
        <h5 class="fw-bold text-uppercase text-decoration-underline mb-1">LAPORAN LABA RUGI OPERASIONAL</h5>
        <span class="fs-6">Periode: <?= tgl_indo($tgl_mulai); ?> s.d. <?= tgl_indo($tgl_selesai); ?></span>
    </div>

    <!-- TABEL REKAPITULASI -->
    <table class="table table-bordered mb-4">
        <tbody>
            <tr class="table-light">
                <th colspan="2" class="fw-bold">1. PENDAPATAN OPERASIONAL</th>
            </tr>
            <tr>
                <td class="ps-4">Total Penjualan Sembako (<?= $total_trx; ?> Transaksi Kasir)</td>
                <td class="text-end fw-bold"><?= rupiah($total_omset); ?></td>
            </tr>
            <tr class="table-light">
                <th colspan="2" class="fw-bold">2. HARGA POKOK PENJUALAN (HPP)</th>
            </tr>
            <tr>
                <td class="ps-4">Modal Beli Barang Terjual</td>
                <td class="text-end">(<?= rupiah($total_hpp); ?>)</td>
            </tr>
            <tr class="table-secondary">
                <th class="ps-4">LABA KOTOR (1 - 2)</th>
                <th class="text-end"><?= rupiah($laba_kotor); ?></th>
            </tr>
            <tr class="table-light">
                <th colspan="2" class="fw-bold">3. BIAYA OPERASIONAL TOKO</th>
            </tr>
            <?php if (empty($biaya_kategori)): ?>
                <tr>
                    <td class="ps-4 text-muted">Tidak ada pengeluaran operasional tercatat</td>
                    <td class="text-end">Rp 0</td>
                </tr>
            <?php else: ?>
                <?php foreach ($biaya_kategori as $bk): ?>
                    <tr>
                        <td class="ps-4"><?= htmlspecialchars($bk['kategori_pengeluaran']); ?></td>
                        <td class="text-end">(<?= rupiah($bk['subtotal']); ?>)</td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            <tr class="table-secondary">
                <th class="ps-4">TOTAL BIAYA OPERASIONAL</th>
                <th class="text-end">(<?= rupiah($total_biaya); ?>)</th>
            </tr>
            <tr class="table-success border-2">
                <th class="fs-5 py-3">LABA BERSIH RIIL USAHA</th>
                <th class="fs-5 py-3 text-end fw-bold"><?= rupiah($laba_bersih); ?></th>
            </tr>
        </tbody>
    </table>

    <!-- TANDA TANGAN -->
    <div class="row mt-5 pt-4">
        <div class="col-6 text-center">
            <p class="mb-0">Penyusun Sistem / Laporan:</p>
            <p class="small text-muted mb-5">Tim Proyek Kolaborasi SMK N Tengaran</p>
            <br><br>
            <p class="fw-bold mb-0 text-decoration-underline">Kelompok 6 (RPL / PPLG)</p>
            <p class="small">Kholiz Erlina & Sifa Fuji Febriyana</p>
        </div>
        <div class="col-6 text-center">
            <p class="mb-0">Bonomerto, Suruh, <?= tgl_indo(date('Y-m-d')); ?></p>
            <p class="small text-muted mb-5">Pemilik Warung Sembako Toko Najwa</p>
            <br><br>
            <p class="fw-bold mb-0 text-decoration-underline"><?= htmlspecialchars($pengaturan['pemilik']); ?></p>
            <p class="small">Pelaku Usaha UMKM</p>
        </div>
    </div>
</div>

</body>
</html>
