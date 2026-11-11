<?php
// includes/layout/admin_top.php
// Bagian ATAS semua halaman admin (kepala HTML + sidebar).
// Sebelum di-include, halaman mengisi dua variabel:
//   $judul_halaman : judul pada tab browser & judul halaman
//   $menu_aktif    : kode menu yang ditandai aktif di sidebar (lihat sidebar.php)

$toko = pengaturan_toko();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($judul_halaman ?? 'Admin') ?> - <?= e($toko['nama_toko']) ?></title>
    <link rel="stylesheet" href="<?= url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>?v=20261111-admin1">
</head>
<body class="admin-panel bg-body-tertiary">

<!-- Bilah atas khusus HP: tombol buka menu + logo -->
<nav class="navbar bg-body border-bottom sticky-top d-lg-none">
    <div class="container-fluid flex-nowrap">
        <button class="btn btn-outline-secondary" type="button" data-bs-toggle="offcanvas" data-bs-target="#menuHp" aria-label="Buka menu">
            <i class="bi bi-list"></i>
        </button>
        <span class="navbar-brand mb-0 mx-3 me-auto fw-bold text-truncate"><?= e($toko['nama_toko']) ?></span>
        <?= logo(36, 'flex-shrink-0') ?>
    </div>
</nav>

<div class="d-flex">
    <!-- Sidebar desktop -->
    <aside class="sidebar d-none d-lg-flex flex-column flex-shrink-0 sticky-top vh-100 bg-body border-end">
        <?php include __DIR__ . '/sidebar.php'; ?>
    </aside>

    <!-- Sidebar HP (menu geser dari kiri) -->
    <div class="offcanvas offcanvas-start sidebar" tabindex="-1" id="menuHp">
        <button type="button" class="btn-close position-absolute top-0 end-0 m-2" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
        <div class="d-flex flex-column h-100">
            <?php include __DIR__ . '/sidebar.php'; ?>
        </div>
    </div>

    <!-- Isi halaman -->
    <main class="konten flex-grow-1 p-3 p-lg-4">
        <?php tampil_flash(); ?>
