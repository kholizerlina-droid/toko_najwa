<?php
// admin/cetak_struk.php - Cetak Struk Nota Kasir Standar Thermal / Kertas
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

require_login();
$pengaturan = get_pengaturan($koneksi);

$faktur = trim($_GET['faktur'] ?? '');
if (empty($faktur)) {
    die("Nomor faktur tidak disertakan.");
}

// Ambil data transaksi
$stmt = $koneksi->prepare("SELECT t.*, u.nama_lengkap AS nama_kasir 
                          FROM transaksi t 
                          LEFT JOIN users u ON t.user_id = u.id 
                          WHERE t.no_faktur = ?");
$stmt->bind_param("s", $faktur);
$stmt->execute();
$trx = $stmt->get_result()->fetch_assoc();

if (!$trx) {
    die("Transaksi tidak ditemukan.");
}

// Ambil rincian barang
$stmt_d = $koneksi->prepare("SELECT td.*, p.nama_produk, p.satuan 
                            FROM transaksi_detail td 
                            JOIN produk p ON td.produk_id = p.id 
                            WHERE td.transaksi_id = ?");
$stmt_d->bind_param("i", $trx['id']);
$stmt_d->execute();
$items = $stmt_d->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Belanja - <?= htmlspecialchars($trx['no_faktur']); ?></title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/custom.css">
    <style>
        body {
            background-color: #f1f5f9;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: #fff;
                padding: 0;
            }
            .thermal-paper {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                width: 100% !important;
                max-width: 80mm;
            }
        }
    </style>
</head>
<body class="py-4">

<div class="container text-center mb-3 no-print">
    <div class="d-flex justify-content-center gap-2">
        <button onclick="window.print()" class="btn btn-success fw-bold px-4">
            <i class="bi bi-printer me-1"></i> Cetak Struk (Print)
        </button>
        <a href="pos.php" class="btn btn-outline-secondary">
            <i class="bi bi-cart3 me-1"></i> Kasir Baru
        </a>
        <a href="transaksi.php" class="btn btn-outline-secondary">
            <i class="bi bi-receipt me-1"></i> Riwayat
        </a>
    </div>
</div>

<div class="thermal-paper shadow-sm rounded">
    <!-- Header Toko -->
    <div class="text-center mb-2 pb-2 border-bottom border-dark border-1">
        <h5 class="fw-bold mb-1 text-uppercase" style="letter-spacing: 0.5px;"><?= htmlspecialchars($pengaturan['nama_toko']); ?></h5>
        <div style="font-size: 11px;"><?= htmlspecialchars($pengaturan['alamat']); ?></div>
        <div style="font-size: 11px;">WA: <?= htmlspecialchars($pengaturan['telepon']); ?> (<?= htmlspecialchars($pengaturan['pemilik']); ?>)</div>
    </div>

    <!-- Metadata Faktur -->
    <div class="mb-2 pb-1 border-bottom border-dark border-dashed" style="font-size: 11px;">
        <div class="d-flex justify-content-between">
            <span>No: <?= htmlspecialchars($trx['no_faktur']); ?></span>
            <span><?= date('d/m/Y H:i', strtotime($trx['tanggal'])); ?></span>
        </div>
        <div class="d-flex justify-content-between">
            <span>Kasir: <?= htmlspecialchars($trx['nama_kasir'] ?? 'Petugas'); ?></span>
            <span>Plg: <?= htmlspecialchars($trx['nama_pelanggan']); ?></span>
        </div>
        <div class="d-flex justify-content-between">
            <span>Metode: <strong class="text-uppercase"><?= htmlspecialchars($trx['metode_pembayaran']); ?></strong></span>
            <span>Status: <?= ($trx['status_pembayaran'] === 'lunas') ? 'LUNAS' : 'KASBON / UTANG'; ?></span>
        </div>
    </div>

    <!-- Rincian Barang -->
    <table class="w-100 mb-2" style="font-size: 11px; border-collapse: collapse;">
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td colspan="2" class="fw-bold pt-1"><?= htmlspecialchars($item['nama_produk']); ?></td>
                </tr>
                <tr class="border-bottom border-secondary border-dashed">
                    <td class="pb-1 text-muted">
                        <?= $item['qty']; ?> <?= htmlspecialchars($item['satuan']); ?> x <?= rupiah($item['harga_jual']); ?>
                    </td>
                    <td class="pb-1 text-end fw-bold">
                        <?= rupiah($item['subtotal']); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Ringkasan Angka -->
    <div class="pt-1 border-top border-dark border-1 mb-3" style="font-size: 12px;">
        <div class="d-flex justify-content-between fw-bold mb-1">
            <span>TOTAL BELANJA</span>
            <span><?= rupiah($trx['total_harga']); ?></span>
        </div>
        <div class="d-flex justify-content-between mb-1">
            <span>Total Bayar</span>
            <span><?= rupiah($trx['total_bayar']); ?></span>
        </div>
        <div class="d-flex justify-content-between fw-bold">
            <span>Kembalian</span>
            <span><?= rupiah($trx['kembalian']); ?></span>
        </div>
    </div>

    <!-- Footer Struk -->
    <div class="text-center pt-2 border-top border-dark border-dashed" style="font-size: 10px;">
        <p class="mb-1"><?= nl2br(htmlspecialchars($pengaturan['teks_footer_struk'])); ?></p>
        <span class="text-muted">*** Simpan Struk ini Sebagai Bukti Pembayaran ***</span>
    </div>
</div>

<script>
    // Auto-focus print dialog on window load if requested
    <?php if (isset($_GET['autoprint'])): ?>
        window.addEventListener('load', function() {
            window.print();
        });
    <?php endif; ?>
</script>

</body>
</html>
