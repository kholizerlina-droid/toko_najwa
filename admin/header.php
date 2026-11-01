<?php
// admin/header.php - Layout Shell Terpadu (Design Reference: Image 2)
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

require_login();
$user = current_user();
$pengaturan = get_pengaturan($koneksi);

// Hitung Peringatan Kadaluarsa (< 30 hari atau lewat)
$res_exp = $koneksi->query("SELECT COUNT(*) FROM produk WHERE tgl_kadaluarsa IS NOT NULL AND tgl_kadaluarsa <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
$count_exp = $res_exp ? (int)$res_exp->fetch_row()[0] : 0;

// Hitung Peringatan Stok Menipis (stok <= min_stok)
$res_stok = $koneksi->query("SELECT COUNT(*) FROM produk WHERE stok <= min_stok");
$count_stok = $res_stok ? (int)$res_stok->fetch_row()[0] : 0;

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Kasir'; ?> - <?= htmlspecialchars($pengaturan['nama_toko']); ?></title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/custom.css">
</head>
<body>

<div class="app-layout-wrapper">
    <!-- Slim Left App Sidebar Rail (Like Image 2) -->
    <aside class="app-sidebar-rail">
        <a href="index.php" title="<?= htmlspecialchars($pengaturan['nama_toko']); ?>">
            <img src="../assets/img/logo.svg" alt="Logo" class="app-rail-logo">
        </a>

        <ul class="rail-menu-list">
            <li>
                <a href="index.php" class="rail-item-link <?= ($current_page === 'index.php') ? 'active' : ''; ?>" title="Dashboard">
                    <i class="bi bi-house-door"></i>
                </a>
            </li>
            <li>
                <a href="pos.php" class="rail-item-link <?= ($current_page === 'pos.php') ? 'active' : ''; ?>" title="Kasir Penjualan (POS)">
                    <i class="bi bi-grid-fill"></i>
                </a>
            </li>
            <li>
                <a href="produk.php" class="rail-item-link <?= in_array($current_page, ['produk.php', 'kategori.php', 'restock.php']) ? 'active' : ''; ?>" title="Data Stok & Kadaluarsa">
                    <i class="bi bi-box-seam"></i>
                    <?php if ($count_exp > 0 || $count_stok > 0): ?>
                        <span class="rail-badge-dot"></span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a href="piutang.php" class="rail-item-link <?= ($current_page === 'piutang.php') ? 'active' : ''; ?>" title="Buku Kasbon Warga">
                    <i class="bi bi-journal-bookmark"></i>
                </a>
            </li>
            <li>
                <a href="laporan.php" class="rail-item-link <?= ($current_page === 'laporan.php') ? 'active' : ''; ?>" title="Laporan Laba Rugi">
                    <i class="bi bi-graph-up"></i>
                </a>
            </li>
        </ul>

        <div class="mt-auto d-none d-md-flex flex-column gap-2 align-items-center">
            <a href="../index.php" target="_blank" class="rail-item-link" title="Buka Halaman Pembeli">
                <i class="bi bi-globe"></i>
            </a>
            <a href="../logout.php" class="rail-item-link text-danger" title="Keluar">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </aside>

    <!-- Main Canvas Area -->
    <main class="app-main-canvas">
        <!-- Canvas Topbar -->
        <div class="canvas-header">
            <div>
                <small class="text-muted d-block" style="font-size: 0.82rem; font-weight: 500;">
                    <i class="bi bi-calendar3 me-1"></i> <?= tgl_indo(date('Y-m-d')); ?>
                </small>
                <h3 class="fw-bold mb-0 text-dark" style="letter-spacing: -0.5px;"><?= htmlspecialchars($pengaturan['nama_toko']); ?></h3>
            </div>

            <div class="d-flex align-items-center gap-2">
                <div class="d-flex align-items-center gap-2 p-1 pe-3 bg-white rounded-pill border shadow-sm">
                    <div class="bento-icon bg-success text-white" style="width: 34px; height: 34px; font-size: 1rem; border-radius: 50%;">
                        <i class="bi bi-person-fill"></i>
                    </div>
                    <div>
                        <strong class="d-block text-dark lh-1 small"><?= htmlspecialchars($user['nama']); ?></strong>
                        <span class="text-muted" style="font-size: 0.7rem;"><?= strtoupper($user['role']); ?></span>
                    </div>
                </div>

                <a href="../logout.php" class="btn btn-outline-danger btn-sm rounded-pill px-3 d-md-none">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
        </div>

        <?php show_flash(); ?>
