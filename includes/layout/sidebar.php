<?php
// includes/layout/sidebar.php
// Isi sidebar admin (dipakai di sidebar desktop dan menu geser versi HP).
// Menambah menu baru: cukup tambah satu baris pada $daftar_menu.
// Format: [kode_menu, file, ikon, label, khusus_admin]

$daftar_menu = [
    ['dashboard',   'index.php',       'bi-speedometer2',     'Dashboard',         false],
    ['pos',         'pos.php',         'bi-cart3',            'Kasir (POS)',       false],
    ['produk',      'produk.php',      'bi-box-seam',         'Data Produk',       false],
    ['kategori',    'kategori.php',    'bi-tags',             'Kategori',          false],
    ['restock',     'restock.php',     'bi-truck',            'Kulakan / Restock', false],
    ['pelanggan',   'pelanggan.php',   'bi-people',           'Data Pelanggan',    false],
    ['piutang',     'piutang.php',     'bi-journal-bookmark', 'Buku Kasbon',       false],
    ['pengeluaran', 'pengeluaran.php', 'bi-wallet2',          'Pengeluaran',       false],
    ['laporan',     'laporan.php',     'bi-graph-up',         'Laporan Laba Rugi', false],
    ['pengaturan',  'pengaturan.php',  'bi-gear',             'Pengaturan',        true],
];

$toko = pengaturan_toko();
$user = user_login();

// Jumlah peringatan (kadaluarsa <= 30 hari atau stok menipis) untuk lencana di menu Produk
$jumlah_peringatan = (int) ambil_nilai(
    "SELECT COUNT(*) FROM produk
     WHERE stok <= min_stok
        OR (tgl_kadaluarsa IS NOT NULL AND tgl_kadaluarsa <= DATE_ADD(CURDATE(), INTERVAL 30 DAY))"
);
?>
<!-- Kepala sidebar: nama toko + logo kecil -->
<div class="d-flex align-items-center p-3 border-bottom">
    <div class="fw-bold lh-sm me-auto"><?= e($toko['nama_toko']) ?></div>
    <?= logo(32, 'ms-2') ?>
</div>

<!-- Daftar menu -->
<nav class="flex-grow-1 overflow-y-auto py-2">
    <ul class="nav nav-pills flex-column menu-admin">
        <?php foreach ($daftar_menu as [$kode, $file, $ikon, $label, $khusus_admin]): ?>
            <?php if ($khusus_admin && !is_admin()) continue; ?>
            <li class="nav-item">
                <a href="<?= url('admin/' . $file) ?>"
                   class="nav-link d-flex align-items-center <?= ($menu_aktif ?? '') === $kode ? 'active' : '' ?>">
                    <i class="bi <?= $ikon ?> me-2"></i>
                    <span class="me-auto"><?= e($label) ?></span>
                    <?php if ($kode === 'produk' && $jumlah_peringatan > 0): ?>
                        <span class="badge text-bg-danger"><?= $jumlah_peringatan ?></span>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>

<!-- Kaki sidebar: nama user, lihat website, keluar -->
<div class="p-3 border-top small">
    <div class="mb-2"><?= e($user['nama']) ?> <span class="text-body-secondary">(<?= e($user['role']) ?>)</span></div>
    <a href="<?= url('index.php') ?>" target="_blank" class="d-block mb-1">Lihat Website</a>
    <a href="<?= url('logout.php') ?>" class="d-block text-danger">Keluar</a>
</div>
