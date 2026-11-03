<?php
// index.php - Website publik Toko Najwa: profil warung, katalog sembako, dan pesan-antar via WhatsApp.
require_once __DIR__ . '/includes/init.php';

$toko = pengaturan_toko();

/* ---------- Ambil data katalog (dengan filter) ---------- */
$cari        = get('q');
$kategori_id = (int) get('kategori');

$sql   = 'SELECT p.id, p.nama_produk, p.satuan, p.harga_jual, p.gambar, k.nama_kategori
          FROM produk p JOIN kategori k ON k.id = p.kategori_id WHERE p.stok > 0';
$tipe  = '';
$param = [];
if ($cari !== '') {
    $sql .= ' AND p.nama_produk LIKE ?';
    $tipe .= 's';
    $param[] = "%$cari%";
}
if ($kategori_id > 0) {
    $sql .= ' AND p.kategori_id = ?';
    $tipe .= 'i';
    $param[] = $kategori_id;
}

$daftar_produk   = ambil_semua($sql . ' ORDER BY p.nama_produk', $tipe, $param);
$daftar_kategori = ambil_semua('SELECT id, nama_kategori FROM kategori ORDER BY id');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($toko['nama_toko']) ?> - Belanja Sembako Warga</title>
    <meta name="description" content="Warung sembako <?= e($toko['nama_toko']) ?>: harga bersahabat, pesan-antar gratis lewat WhatsApp.">
    <link rel="stylesheet" href="<?= url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
</head>
<body data-wa="<?= e(nomor_wa($toko['telepon'])) ?>">

<!-- Navigasi -->
<nav class="navbar navbar-expand-lg bg-body border-bottom sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center fw-bold" href="<?= url('index.php') ?>">
            <?= logo(40, 'me-2') ?>
            <?= e($toko['nama_toko']) ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuUtama" aria-label="Buka menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="menuUtama">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                <li class="nav-item"><a class="nav-link" href="#layanan">Layanan</a></li>
                <li class="nav-item"><a class="nav-link" href="#katalog">Katalog</a></li>
                <li class="nav-item"><a class="nav-link" href="#lokasi">Lokasi</a></li>
                <li class="nav-item">
                    <button type="button" class="btn btn-primary position-relative" data-bs-toggle="offcanvas" data-bs-target="#panelKeranjang">
                        <i class="bi bi-cart3 me-1"></i> Keranjang
                        <span id="lencanaKeranjang" class="badge text-bg-danger ms-1">0</span>
                    </button>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Hero -->
<header class="bg-body-tertiary border-bottom">
    <div class="container py-5">
        <div class="row align-items-center g-4">
            <div class="col-md-7">
                <span class="badge text-bg-success mb-2">Buka setiap hari 06.00 &ndash; 21.00 WIB</span>
                <h1 class="display-5 fw-bold">Kebutuhan pokok harian, dekat dan terjangkau.</h1>
                <p class="lead text-body-secondary">
                    <?= e($toko['nama_toko']) ?> melayani warga Dusun Jaten dan sekitarnya dengan beras, minyak, gula,
                    telur, bumbu dapur, dan kebutuhan rumah tangga. Pesan lewat website, kami antar gratis.
                </p>
                <a href="#katalog" class="btn btn-primary btn-lg me-2">Mulai Belanja</a>
                <a href="https://wa.me/<?= e(nomor_wa($toko['telepon'])) ?>" target="_blank" class="btn btn-outline-success btn-lg">
                    <i class="bi bi-whatsapp me-1"></i> Hubungi Kami
                </a>
            </div>
            <div class="col-md-5 text-center">
                <?= logo(220) ?>
            </div>
        </div>
    </div>
</header>

<!-- Layanan -->
<section class="container py-5" id="layanan">
    <div class="row g-3 text-center">
        <div class="col-md-4">
            <div class="card h-100"><div class="card-body">
                <i class="bi bi-truck fs-1 text-primary"></i>
                <h2 class="h5 mt-2">Antar Gratis</h2>
                <p class="text-body-secondary mb-0">Pesan-antar tanpa ongkir untuk warga Dusun Jaten dan sekitarnya.</p>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card h-100"><div class="card-body">
                <i class="bi bi-qr-code fs-1 text-primary"></i>
                <h2 class="h5 mt-2">Tunai &amp; QRIS</h2>
                <p class="text-body-secondary mb-2">Bayar tunai atau scan QRIS dari bank dan dompet digital.</p>
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalQris">Lihat QRIS</button>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card h-100"><div class="card-body">
                <i class="bi bi-whatsapp fs-1 text-primary"></i>
                <h2 class="h5 mt-2">Pesan via WhatsApp</h2>
                <p class="text-body-secondary mb-0">Isi keranjang, klik pesan, dan daftar belanjaan langsung terkirim ke <?= e($toko['pemilik']) ?>.</p>
            </div></div>
        </div>
    </div>
</section>

<!-- Katalog -->
<section class="bg-body-tertiary border-top border-bottom py-5" id="katalog">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h2 class="h3 mb-0">Katalog Sembako</h2>
            <form method="get" action="#katalog" class="d-flex gap-2">
                <?php if ($kategori_id > 0): ?><input type="hidden" name="kategori" value="<?= $kategori_id ?>"><?php endif; ?>
                <input type="search" name="q" class="form-control" placeholder="Cari sembako..." value="<?= e($cari) ?>">
                <button class="btn btn-outline-primary" type="submit" aria-label="Cari"><i class="bi bi-search"></i></button>
            </form>
        </div>

        <div class="d-flex flex-wrap gap-2 mb-4">
            <a href="?q=<?= urlencode($cari) ?>#katalog" class="btn btn-sm <?= $kategori_id === 0 ? 'btn-primary' : 'btn-outline-primary' ?>">Semua</a>
            <?php foreach ($daftar_kategori as $k): ?>
                <a href="?kategori=<?= $k['id'] ?>&q=<?= urlencode($cari) ?>#katalog"
                   class="btn btn-sm <?= $kategori_id === (int) $k['id'] ? 'btn-primary' : 'btn-outline-primary' ?>"><?= e($k['nama_kategori']) ?></a>
            <?php endforeach; ?>
        </div>

        <div class="row g-3">
            <?php foreach ($daftar_produk as $p): $foto = gambar_produk($p['gambar']); ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="card h-100">
                        <div class="card-body text-center d-flex flex-column">
                            <img src="<?= $foto ?>" alt="<?= e($p['nama_produk']) ?>" class="foto-produk mx-auto mb-2">
                            <h3 class="h6"><?= e($p['nama_produk']) ?></h3>
                            <small class="text-body-secondary mb-2"><?= e($p['nama_kategori']) ?> &middot; per <?= e($p['satuan']) ?></small>
                            <div class="fw-bold text-primary mt-auto mb-2"><?= rupiah($p['harga_jual']) ?></div>
                            <button type="button" class="btn btn-sm btn-primary tombol-tambah"
                                    data-id="<?= $p['id'] ?>" data-nama="<?= e($p['nama_produk']) ?>"
                                    data-harga="<?= (int) $p['harga_jual'] ?>" data-satuan="<?= e($p['satuan']) ?>"
                                    data-foto="<?= e($foto) ?>">
                                <i class="bi bi-plus-lg"></i> Tambah
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$daftar_produk): ?>
                <div class="col-12 text-center text-body-secondary py-5">
                    <i class="bi bi-basket3 fs-1 d-block"></i>
                    Sembako yang dicari tidak ditemukan. <a href="index.php#katalog">Tampilkan semua</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Lokasi -->
<section class="container py-5" id="lokasi">
    <div class="row g-4">
        <div class="col-md-6">
            <h2 class="h3">Lokasi &amp; Jam Buka</h2>
            <ul class="list-unstyled mb-0">
                <li class="mb-2"><i class="bi bi-geo-alt text-primary me-2"></i><?= e($toko['alamat']) ?></li>
                <li class="mb-2"><i class="bi bi-person text-primary me-2"></i>Pemilik: <?= e($toko['pemilik']) ?></li>
                <li class="mb-2"><i class="bi bi-whatsapp text-primary me-2"></i><?= e($toko['telepon']) ?></li>
                <li><i class="bi bi-clock text-primary me-2"></i>Setiap hari, 06.00 &ndash; 21.00 WIB</li>
            </ul>
        </div>
        <div class="col-md-6">
            <h2 class="h3">Tentang Website Ini</h2>
            <p class="text-body-secondary mb-0">
                Proyek kolaborasi <?= e(INFO_PROYEK['jurusan']) ?> <?= e(INFO_PROYEK['sekolah']) ?>:
                analisis SWOT dan pengembangan UMKM berbasis digital. Pencatatan kasir, stok, dan
                kasbon warung kini tersimpan rapi di sistem, bukan lagi di buku tulis.
            </p>
        </div>
    </div>
</section>

<footer class="border-top py-4 text-center text-body-secondary small">
    &copy; <?= date('Y') ?> <?= e($toko['nama_toko']) ?> &middot;
    <?= e(INFO_PROYEK['kelompok']) ?> (<?= e(implode(' & ', INFO_PROYEK['anggota'])) ?>) &middot;
    <?= e(INFO_PROYEK['sekolah']) ?> TP <?= e(INFO_PROYEK['tahun']) ?>
</footer>

<!-- Keranjang belanja (panel geser dari kanan) -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="panelKeranjang">
    <div class="offcanvas-header border-bottom">
        <h2 class="offcanvas-title h5">Keranjang Belanja</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column">
        <ul class="list-group list-group-flush flex-grow-1 overflow-y-auto mb-3" id="daftarKeranjang"></ul>

        <div class="d-flex justify-content-between mb-3">
            <span class="fw-semibold">Total</span>
            <span class="h5 mb-0 text-primary" id="totalKeranjang">Rp 0</span>
        </div>

        <div class="btn-group w-100 mb-3" role="group" aria-label="Cara terima">
            <input type="radio" class="btn-check" name="layanan" id="layananAntar" value="Antar ke Rumah" checked>
            <label class="btn btn-outline-secondary" for="layananAntar">Antar ke Rumah</label>
            <input type="radio" class="btn-check" name="layanan" id="layananAmbil" value="Ambil di Toko">
            <label class="btn btn-outline-secondary" for="layananAmbil">Ambil di Toko</label>
        </div>
        <div class="btn-group w-100 mb-3" role="group" aria-label="Cara bayar">
            <input type="radio" class="btn-check" name="bayar" id="bayarTunai" value="Tunai (bayar saat terima)" checked>
            <label class="btn btn-outline-secondary" for="bayarTunai">Tunai</label>
            <input type="radio" class="btn-check" name="bayar" id="bayarQris" value="QRIS">
            <label class="btn btn-outline-secondary" for="bayarQris">QRIS</label>
        </div>

        <input type="text" id="namaPemesan" class="form-control mb-2" placeholder="Nama Anda">
        <input type="text" id="alamatPemesan" class="form-control mb-3" placeholder="Alamat (contoh: Jaten RT 02)">

        <button type="button" class="btn btn-success w-100" id="tombolPesan" disabled>
            <i class="bi bi-whatsapp me-1"></i> Pesan via WhatsApp
        </button>
        <button type="button" class="btn btn-link text-danger w-100 mt-1" id="tombolKosongkan">Kosongkan keranjang</button>
    </div>
</div>

<!-- Modal QRIS -->
<div class="modal fade" id="modalQris" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content text-center">
            <div class="modal-header">
                <h2 class="modal-title h5">QRIS <?= e($toko['nama_toko']) ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <img src="<?= gambar_qris() ?>" alt="QRIS" class="img-fluid" style="max-height: 280px;">
                <p class="text-body-secondary small mt-2 mb-0">Mendukung bank dan dompet digital yang terhubung QRIS.</p>
            </div>
        </div>
    </div>
</div>

<script src="<?= url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= url('assets/js/katalog.js') ?>"></script>
</body>
</html>
