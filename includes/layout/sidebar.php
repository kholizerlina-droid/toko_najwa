<?php
// includes/layout/sidebar.php
// Isi sidebar admin (dipakai di sidebar desktop dan menu geser versi HP).
// Menambah menu baru: cukup tambah satu baris pada $daftar_menu.
// Format: [kode_menu, file, ikon, label, khusus_admin]

$daftar_menu = [
    'Utama' => [
        ['dashboard',   'index.php',       'bi-speedometer2',    'Dashboard',        false],
        ['pos',         'pos.php',         'bi-cart3',           'Kasir (POS)',      false],
        ['pesanan',     'pesanan.php',     'bi-bag-check',       'Pesanan Online',   false],
        ['pelanggan',   'pelanggan.php',   'bi-people',          'Data Pelanggan',   false],
    ],
    'Inventaris' => [
        ['produk',      'produk.php',      'bi-box-seam',        'Data Produk',      false],
        ['kategori',    'kategori.php',    'bi-tags',            'Kategori',         false],
        ['restock',     'restock.php',     'bi-truck',           'Kulakan / Restock', false],
    ],
    'Keuangan' => [
        ['transaksi',   'transaksi.php',   'bi-receipt',         'Riwayat Transaksi', false],
        ['piutang',     'piutang.php',     'bi-journal-bookmark', 'Buku Kasbon',     false],
        ['pengeluaran', 'pengeluaran.php', 'bi-wallet2',         'Pengeluaran',      false],
        ['laporan',     'laporan.php',     'bi-graph-up',        'Laporan Laba Rugi', false],
    ],
    'Sistem' => [
        ['pengaturan',  'pengaturan.php',  'bi-gear',            'Pengaturan',       true],
    ],
];

$toko = pengaturan_toko();
$user = user_login();

// Jumlah peringatan (kadaluarsa <= 30 hari atau stok menipis) untuk lencana di menu Produk
$jumlah_peringatan = (int) ambil_nilai(
    "SELECT COUNT(*) FROM produk
     WHERE stok <= min_stok
        OR (tgl_kadaluarsa IS NOT NULL AND tgl_kadaluarsa <= DATE_ADD(CURDATE(), INTERVAL 30 DAY))"
);
// Sidebar dipakai dua kali pada sebagian halaman; simpan hitungan agar query cukup sekali.
if (!isset($jumlah_pesanan_pending)) {
    $jumlah_pesanan_pending = (int) ambil_nilai(
        "SELECT COUNT(*) FROM pesanan_online
         WHERE transaksi_id IS NULL AND status IN ('baru', 'diproses')"
    );
}
?>
<!-- Kepala sidebar: nama toko di kiri, LOGO di pojok kanan -->
<div class="d-flex align-items-center p-3 border-bottom">
    <div class="me-auto">
        <div class="fw-bold lh-sm"><?= e($toko['nama_toko']) ?></div>
        <small class="text-body-secondary">Sistem Kasir &amp; Inventaris</small>
    </div>
    <?= logo(44, 'ms-2') ?>
</div>

<!-- Daftar menu -->
<nav class="flex-grow-1 overflow-y-auto p-3">
    <?php foreach ($daftar_menu as $judul_grup => $menu_grup): ?>
        <div class="text-body-secondary text-uppercase small fw-semibold mt-3 mb-1 px-2"><?= e($judul_grup) ?></div>
        <ul class="nav nav-pills flex-column">
            <?php foreach ($menu_grup as [$kode, $file, $ikon, $label, $khusus_admin]): ?>
                <?php if ($khusus_admin && !is_admin()) continue; ?>
                <li class="nav-item">
                    <a href="<?= url('admin/' . $file) ?>"
                       class="nav-link d-flex align-items-center <?= ($menu_aktif ?? '') === $kode ? 'active' : '' ?>">
                        <i class="bi <?= $ikon ?> me-2"></i>
                        <span class="me-auto"><?= e($label) ?></span>
                        <?php if ($kode === 'produk' && $jumlah_peringatan > 0): ?>
                            <span class="badge text-bg-danger rounded-pill"><?= $jumlah_peringatan ?></span>
                        <?php endif; ?>
                        <?php if ($kode === 'pesanan' && $jumlah_pesanan_pending > 0): ?>
                            <span class="badge text-bg-danger rounded-pill"><?= $jumlah_pesanan_pending ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endforeach; ?>
</nav>

<!-- Kaki sidebar: info user, lihat website, keluar -->
<div class="p-3 border-top">
    <div class="d-flex align-items-center mb-2">
        <i class="bi bi-person-circle fs-3 me-2"></i>
        <div class="lh-sm">
            <div class="fw-semibold small"><?= e($user['nama']) ?></div>
            <span class="badge text-bg-secondary text-uppercase"><?= e($user['role']) ?></span>
        </div>
    </div>
    <div class="d-grid gap-2">
        <a href="<?= url('index.php') ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-globe me-1"></i> Lihat Website
        </a>
        <a href="<?= url('logout.php') ?>" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-box-arrow-right me-1"></i> Keluar
        </a>
    </div>
</div>
