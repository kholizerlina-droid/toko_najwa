<?php
// index.php - Website publik Toko Najwa:
// beranda, tentang, katalog, testimoni, lokasi, keranjang, dan form pemesanan (ambil di toko).
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

/* ---------- Testimoni: simpan & tampilkan ---------- */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['csrf_testimoni'])) {
    $_SESSION['csrf_testimoni'] = bin2hex(random_bytes(16));
}

$berkas_testimoni = __DIR__ . '/data/testimoni.json';

function baca_testimoni(string $berkas): array
{
    if (!is_file($berkas)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($berkas), true);
    return is_array($data) ? $data : [];
}

$galat_testimoni = [];
$isian = ['nama' => '', 'asal' => '', 'bintang' => 5, 'isi' => ''];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['aksi'] ?? '') === 'testimoni') {
    $isian['nama']    = trim((string) ($_POST['nama'] ?? ''));
    $isian['asal']    = trim((string) ($_POST['asal'] ?? ''));
    $isian['bintang'] = (int) ($_POST['bintang'] ?? 5);
    $isian['isi']     = trim((string) ($_POST['isi'] ?? ''));

    if (!hash_equals($_SESSION['csrf_testimoni'], (string) ($_POST['token'] ?? ''))) {
        $galat_testimoni[] = 'Sesi formulir sudah kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    } elseif (trim((string) ($_POST['website'] ?? '')) !== '') {
        // Kolom jebakan untuk bot terisi: pura-pura berhasil.
        header('Location: index.php?testimoni=ok#testimoni');
        exit;
    } else {
        if ($isian['nama'] === '' || mb_strlen($isian['nama']) > 40) {
            $galat_testimoni[] = 'Nama wajib diisi (maksimal 40 huruf).';
        }
        if (mb_strlen($isian['asal']) > 40) {
            $galat_testimoni[] = 'Asal/alamat maksimal 40 huruf.';
        }
        if ($isian['bintang'] < 1 || $isian['bintang'] > 5) {
            $galat_testimoni[] = 'Pilih jumlah bintang 1 sampai 5.';
        }
        if (mb_strlen($isian['isi']) < 10 || mb_strlen($isian['isi']) > 300) {
            $galat_testimoni[] = 'Isi testimoni 10 sampai 300 huruf.';
        }
        if (isset($_SESSION['testimoni_terakhir']) && time() - $_SESSION['testimoni_terakhir'] < 60) {
            $galat_testimoni[] = 'Tunggu sebentar sebelum mengirim testimoni lagi.';
        }
    }

    if (!$galat_testimoni) {
        $folder = dirname($berkas_testimoni);
        if (!is_dir($folder)) {
            @mkdir($folder, 0755, true);
            @file_put_contents($folder . '/.htaccess', "Require all denied\n");
        }
        $fp = @fopen($berkas_testimoni, 'c+');
        if ($fp && flock($fp, LOCK_EX)) {
            $lama = json_decode((string) stream_get_contents($fp), true);
            $lama = is_array($lama) ? $lama : [];
            array_unshift($lama, [
                'nama'    => $isian['nama'],
                'asal'    => $isian['asal'],
                'bintang' => $isian['bintang'],
                'isi'     => $isian['isi'],
                'tanggal' => date('Y-m-d H:i'),
            ]);
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode(array_slice($lama, 0, 200), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
            $_SESSION['testimoni_terakhir'] = time();
            header('Location: index.php?testimoni=ok#testimoni');
            exit;
        }
        $galat_testimoni[] = 'Testimoni belum bisa disimpan. Pastikan folder data/ bisa ditulis oleh server.';
    }
}

$testimoni_terkirim = get('testimoni') === 'ok';

// Testimoni yang disembunyikan admin tidak ditampilkan
$testimoni = array_values(array_filter(
    baca_testimoni($berkas_testimoni),
    static fn($t) => empty($t['sembunyi'])
));
$testimoni = array_slice($testimoni, 0, 6);

/* Contoh testimoni: tampil hanya selama belum ada testimoni dari pelanggan */
if (!$testimoni) {
    $testimoni = [
        ['nama' => 'Bu Siti',   'asal' => 'Jaten RT 01', 'bintang' => 5, 'isi' => 'Harganya pas, barangnya selalu segar. Pesan lewat WhatsApp dari rumah, tinggal ambil di toko. Praktis sekali.'],
        ['nama' => 'Pak Joko',  'asal' => 'Jaten RT 03', 'bintang' => 5, 'isi' => 'Sembako lengkap, tidak perlu keliling ke pasar. Pesanan sudah disiapkan waktu saya datang.'],
        ['nama' => 'Mbak Rina', 'asal' => 'Jaten RT 02', 'bintang' => 4, 'isi' => 'Bayar bisa QRIS jadi tidak repot uang kecil. Pemiliknya ramah dan cepat membalas pesan.'],
    ];
}

/* Alamat untuk peta (tanpa API key) */
$peta_src = 'https://www.google.com/maps?q=' . urlencode($toko['alamat']) . '&output=embed';
$peta_url = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($toko['alamat']);

/* Foto beranda & tentang, dan logo (taruh berkasnya di assets/img/) */
$foto_beranda = 'assets/img/bt.jpeg';
$foto_tentang = 'assets/img/bt.jpeg';
$berkas_logo  = 'assets/img/logo.png';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($toko['nama_toko']) ?> - Belanja Sembako Warga</title>
    <meta name="description" content="Warung sembako <?= e($toko['nama_toko']) ?>: harga bersahabat, pesan lewat WhatsApp dan ambil di toko.">
    <link rel="stylesheet" href="<?= url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
    <script>document.documentElement.classList.add('js');</script>
    <style>
        /* ===== Dasar & warna ===== */
        :root {
            --emas: #f2b640;
            --emas-tua: #a86a00;
            --arang: #1b1b1b;
            --krem: #fbf7ef;
            --bs-primary: #d9941a;
            --bs-primary-rgb: 217, 148, 26;
            --bs-link-color: #a86a00;
            --bs-link-hover-color: #7d4f00;
        }
        html { scroll-behavior: smooth; }
        section[id], header[id] { scroll-margin-top: 72px; }
        body { background: #fff; }
        .text-primary { color: var(--emas-tua) !important; }
        .btn-primary {
            --bs-btn-bg: var(--emas); --bs-btn-border-color: var(--emas); --bs-btn-color: var(--arang);
            --bs-btn-hover-bg: #ffc857; --bs-btn-hover-border-color: #ffc857; --bs-btn-hover-color: #000;
            --bs-btn-active-bg: #e0a52f; --bs-btn-active-border-color: #e0a52f; --bs-btn-active-color: #000;
            --bs-btn-disabled-bg: var(--emas); --bs-btn-disabled-border-color: var(--emas); --bs-btn-disabled-color: var(--arang);
            --bs-btn-focus-shadow-rgb: 242, 182, 64;
            font-weight: 600;
        }
        .btn-outline-primary {
            --bs-btn-color: var(--emas-tua); --bs-btn-border-color: #d9941a;
            --bs-btn-hover-bg: var(--emas); --bs-btn-hover-border-color: var(--emas); --bs-btn-hover-color: #000;
            --bs-btn-active-bg: var(--emas); --bs-btn-active-border-color: var(--emas); --bs-btn-active-color: #000;
            --bs-btn-focus-shadow-rgb: 242, 182, 64;
        }
        .bg-krem { background: var(--krem) !important; }
        .bintang { color: #f2a900; letter-spacing: 2px; }

        /* Judul bagian */
        .kepala-bagian { margin-bottom: 2rem; }
        .kepala-bagian .atas {
            display: inline-block;
            color: var(--emas-tua);
            font-size: .8rem;
            font-weight: 700;
            letter-spacing: .14em;
            text-transform: uppercase;
        }
        .kepala-bagian h2 { font-weight: 800; margin: .25rem 0 0; }
        .kepala-bagian h2::after {
            content: "";
            display: block;
            width: 56px; height: 4px;
            margin-top: .7rem;
            border-radius: 4px;
            background: var(--emas);
        }
        .kepala-bagian.tengah { text-align: center; }
        .kepala-bagian.tengah h2::after { margin-inline: auto; }

        /* Muncul halus saat di-scroll */
        .js .muncul { opacity: 0; transform: translateY(24px); transition: opacity .6s ease, transform .6s ease; }
        .js .muncul.tampil { opacity: 1; transform: none; }
        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            .js .muncul { opacity: 1; transform: none; transition: none; }
            .hero .anim { animation: none !important; }
        }

        /* ===== Navbar ===== */
        .navbar {
            background: rgba(255,255,255,.92) !important;
            -webkit-backdrop-filter: blur(8px);
            backdrop-filter: blur(8px);
            box-shadow: 0 2px 14px rgba(0,0,0,.06);
        }
        .navbar-brand { font-size: 1.15rem; }
        .navbar .nav-link { font-weight: 500; position: relative; }
        @media (min-width: 992px) {
            .navbar .nav-link::after {
                content: ""; position: absolute; left: .5rem; right: .5rem; bottom: .2rem;
                height: 2px; background: var(--emas); transform: scaleX(0); transition: transform .25s;
            }
            .navbar .nav-link:hover::after { transform: scaleX(1); }
        }

        /* ===== Beranda ===== */
        .hero {
            position: relative;
            min-height: 640px;
            display: flex;
            align-items: center;
            color: #fff;
            background-color: var(--arang);
            background-size: cover;
            background-position: center;
            padding-bottom: 90px;
            overflow: hidden;
        }
        .hero::before {
            content: "";
            position: absolute; inset: 0;
            background: linear-gradient(100deg, rgba(10,10,10,.93) 0%, rgba(10,10,10,.75) 48%, rgba(10,10,10,.30) 100%);
        }
        .hero > .container { position: relative; z-index: 1; }
        .hero .label-hero {
            display: inline-block;
            padding: .35rem .9rem;
            border: 1px solid rgba(242,182,64,.6);
            border-radius: 50rem;
            color: var(--emas);
            font-size: .82rem;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .hero h1 { font-weight: 800; line-height: 1.1; text-shadow: 0 2px 12px rgba(0,0,0,.4); }
        .hero .judul-emas { color: var(--emas); }
        .hero .teks-hero { color: rgba(255,255,255,.85); max-width: 540px; }
        .hero .btn-emas { background: var(--emas); border-color: var(--emas); color: var(--arang); font-weight: 700; }
        .hero .btn-emas:hover { background: #ffc857; border-color: #ffc857; color: #000; }
        .hero .info-hero {
            display: flex; flex-wrap: wrap; gap: 14px 32px;
            margin-top: 2.25rem; padding-top: 1.25rem;
            border-top: 1px solid rgba(255,255,255,.22);
            font-size: .95rem;
        }
        .hero .info-hero i { color: var(--emas); margin-right: 8px; }
        @keyframes naik-halus {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: none; }
        }
        .hero .anim { animation: naik-halus .7s ease both; }
        .hero .anim.t2 { animation-delay: .15s; }
        .hero .anim.t3 { animation-delay: .3s; }

        /* ===== Kartu layanan (menumpuk di atas beranda) ===== */
        .layanan-wrap { margin-top: -70px; position: relative; z-index: 2; }
        .kartu-layanan {
            background: #fff;
            border: 0;
            border-radius: 1rem;
            box-shadow: 0 12px 32px rgba(0,0,0,.12);
            transition: transform .25s, box-shadow .25s;
        }
        .kartu-layanan:hover { transform: translateY(-4px); box-shadow: 0 16px 38px rgba(0,0,0,.16); }
        .ikon-bulat {
            width: 64px; height: 64px;
            display: grid; place-items: center;
            margin: 0 auto .75rem;
            border-radius: 50%;
            background: rgba(242,182,64,.2);
            color: var(--emas-tua);
            font-size: 1.7rem;
        }

        /* ===== Tentang ===== */
        .foto-tentang { position: relative; display: inline-block; max-width: 100%; }
        .foto-tentang::before {
            content: "";
            position: absolute; inset: 14px -14px -14px 14px;
            border: 3px solid var(--emas);
            border-radius: 1rem;
            z-index: 0;
        }
        .foto-tentang img { position: relative; z-index: 1; border-radius: 1rem; box-shadow: 0 10px 28px rgba(0,0,0,.15); }
        .poin-tentang { list-style: none; padding: 0; margin: 1.25rem 0 0; }
        .poin-tentang li { display: flex; gap: 12px; align-items: flex-start; margin-bottom: .8rem; }
        .poin-tentang .ikon {
            flex: 0 0 38px; height: 38px;
            display: grid; place-items: center;
            border-radius: 50%;
            background: rgba(242,182,64,.2);
            color: var(--emas-tua);
        }

        /* ===== Katalog ===== */
        .pil-kategori .btn { border-radius: 50rem; padding-inline: 1rem; }
        .kartu-produk {
            border: 1px solid rgba(0,0,0,.07);
            border-radius: 1rem;
            overflow: hidden;
            transition: transform .2s, box-shadow .2s;
        }
        .kartu-produk:hover { transform: translateY(-4px); box-shadow: 0 12px 28px rgba(0,0,0,.12); }
        .kartu-produk .harga { font-size: 1.1rem; font-weight: 800; color: var(--emas-tua); }
        .kartu-produk .tombol-tambah { border-radius: 50rem; font-weight: 600; }

        /* ===== Testimoni ===== */
        .kartu-testimoni {
            position: relative;
            border: 0;
            border-radius: 1rem;
            box-shadow: 0 6px 22px rgba(0,0,0,.08);
        }
        .kartu-testimoni::before {
            content: "\201C";
            position: absolute; top: -.1em; right: 1rem;
            font-family: Georgia, serif;
            font-size: 5.5rem; line-height: 1;
            color: rgba(242,182,64,.35);
        }
        .avatar {
            width: 40px; height: 40px;
            display: inline-grid; place-items: center;
            border-radius: 50%;
            background: var(--emas); color: var(--arang);
            font-weight: 700;
        }

        /* ===== Lokasi ===== */
        .baris-info { display: flex; gap: 14px; margin-bottom: 1rem; }
        .baris-info .ikon {
            flex: 0 0 42px; height: 42px;
            display: grid; place-items: center;
            border-radius: 50%;
            background: rgba(242,182,64,.2);
            color: var(--emas-tua);
        }
        .baris-info small { display: block; color: #6c757d; }

        /* ===== Footer ===== */
        .footer-gelap { background: var(--arang); color: rgba(255,255,255,.75); }
        .footer-gelap a { color: var(--emas); text-decoration: none; }
        .footer-gelap .garis { border-top: 1px solid rgba(255,255,255,.12); }

        /* ===== Panel keranjang & pemesanan ===== */
        #panelKeranjang { --bs-offcanvas-width: 420px; background: var(--krem); }
        #panelKeranjang .kepala-panel {
            background: linear-gradient(135deg, #f2b640 0%, #d9941a 100%);
            color: var(--arang);
            padding: 1.1rem 1.25rem;
        }
        #panelKeranjang .kepala-panel .ikon-kepala {
            width: 44px; height: 44px; display: grid; place-items: center;
            border-radius: 50%; background: rgba(255,255,255,.45); font-size: 1.4rem;
        }
        #panelKeranjang .area-gulir { flex: 1 1 auto; overflow-y: auto; padding: 1rem; }
        #panelKeranjang .blok {
            background: #fff; border-radius: 1rem; padding: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,.05); margin-bottom: .9rem;
        }
        #panelKeranjang .judul-blok {
            display: flex; align-items: center; gap: .6rem;
            font-weight: 700; margin-bottom: .8rem;
        }
        #panelKeranjang .nomor {
            width: 26px; height: 26px; display: grid; place-items: center;
            border-radius: 50%; background: var(--emas); color: var(--arang);
            font-size: .8rem; font-weight: 800;
        }
        #panelKeranjang #daftarKeranjang { max-height: 260px; overflow-y: auto; margin: 0; }
        #panelKeranjang #daftarKeranjang .list-group-item { background: transparent; padding-inline: 0; }
        #panelKeranjang #daftarKeranjang:empty::before {
            content: "Keranjang masih kosong. Yuk pilih sembako dulu 🛒";
            display: block; text-align: center; color: #6c757d; padding: 1.2rem .5rem; font-size: .92rem;
        }

        /* Info ambil di toko */
        #panelKeranjang .info-ambil {
            display: flex; gap: .75rem; align-items: center;
            background: rgba(242,182,64,.18); border: 1px dashed var(--emas);
            border-radius: .9rem; padding: .7rem .9rem; font-size: .9rem; margin-bottom: .9rem;
        }
        #panelKeranjang .info-ambil i { font-size: 1.5rem; color: var(--emas-tua); }

        /* Kolom isian berikon */
        #panelKeranjang .input-group-text {
            background: rgba(242,182,64,.2); border-color: #e5e5e5; color: var(--emas-tua);
        }
        #panelKeranjang .form-control { border-color: #e5e5e5; padding: .6rem .8rem; }
        #panelKeranjang .form-control:focus {
            border-color: var(--emas); box-shadow: 0 0 0 .2rem rgba(242,182,64,.25);
        }

        /* Pilihan bayar berbentuk kartu */
        #panelKeranjang .pilih-bayar { display: grid; grid-template-columns: 1fr 1fr; gap: .6rem; }
        #panelKeranjang .pilih-bayar .btn-check + label {
            display: flex; flex-direction: column; align-items: center; gap: .15rem;
            padding: .8rem .5rem; border: 2px solid #e5e5e5; border-radius: .9rem;
            background: #fff; color: #444; cursor: pointer; text-align: center;
            transition: border-color .2s, background .2s, transform .15s;
        }
        #panelKeranjang .pilih-bayar .btn-check + label i { font-size: 1.6rem; color: #888; }
        #panelKeranjang .pilih-bayar .btn-check + label small { color: #888; font-size: .72rem; }
        #panelKeranjang .pilih-bayar .btn-check + label:hover { transform: translateY(-2px); }
        #panelKeranjang .pilih-bayar .btn-check:checked + label {
            border-color: var(--emas); background: rgba(242,182,64,.18); font-weight: 700;
        }
        #panelKeranjang .pilih-bayar .btn-check:checked + label i { color: var(--emas-tua); }
        #panelKeranjang .pilih-bayar .btn-check:focus-visible + label { outline: 3px solid rgba(242,182,64,.5); }

        /* Footer tetap: total + tombol */
        #panelKeranjang .kaki-panel {
            background: #fff; padding: 1rem 1.25rem;
            box-shadow: 0 -6px 18px rgba(0,0,0,.08);
        }
        #panelKeranjang .total-baris { display: flex; justify-content: space-between; align-items: center; }
        #panelKeranjang #totalKeranjang { font-size: 1.5rem; font-weight: 800; color: var(--emas-tua); }
        #panelKeranjang #tombolPesan {
            border-radius: 50rem; padding: .8rem; font-weight: 700; font-size: 1.02rem;
            background: #25d366; border-color: #25d366;
            box-shadow: 0 6px 16px rgba(37,211,102,.35);
        }
        #panelKeranjang #tombolPesan:hover:not(:disabled) { background: #1ebe5b; border-color: #1ebe5b; }
        #panelKeranjang #tombolPesan:disabled { box-shadow: none; opacity: .55; }

        @media (max-width: 767.98px) {
            .hero { min-height: 560px; }
            .layanan-wrap { margin-top: -50px; }
            .foto-tentang::before { inset: 10px -8px -10px 8px; }
        }
    </style>
</head>
<body data-wa="<?= e(nomor_wa($toko['telepon'])) ?>" data-qris-url="<?= e(url('qris.php')) ?>">

<!-- Navigasi -->
<nav class="navbar navbar-expand-lg sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center fw-bold" href="<?= url('index.php') ?>">
            <img src="<?= url($berkas_logo) ?>" alt="Logo <?= e($toko['nama_toko']) ?>"
                 height="46" class="me-2" style="object-fit:contain">
            <?= e($toko['nama_toko']) ?>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuUtama" aria-label="Buka menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="menuUtama">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                <li class="nav-item"><a class="nav-link" href="#beranda">Beranda</a></li>
                <li class="nav-item"><a class="nav-link" href="#tentang">Tentang</a></li>
                <li class="nav-item"><a class="nav-link" href="#katalog">Produk</a></li>
                <li class="nav-item"><a class="nav-link" href="#testimoni">Testimoni</a></li>
                <li class="nav-item"><a class="nav-link" href="#lokasi">Lokasi</a></li>
                <li class="nav-item ms-lg-2">
                    <button type="button" class="btn btn-primary position-relative rounded-pill px-3" data-bs-toggle="offcanvas" data-bs-target="#panelKeranjang">
                        <i class="bi bi-cart3 me-1"></i> Keranjang
                        <span id="lencanaKeranjang" class="badge text-bg-dark ms-1">0</span>
                    </button>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Beranda -->
<header class="hero" id="beranda" style="background-image: url('<?= url($foto_beranda) ?>');">
    <div class="container py-5">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <span class="label-hero mb-3 anim">Warung Sembako Dusun Jaten</span>
                <h1 class="display-3 mt-3 anim t2">
                    Belanja Sembako<br>
                    <span class="judul-emas">Tanpa Antre</span>
                </h1>
                <p class="teks-hero lead my-4 anim t2">
                    Pilih dari katalog, kirim pesanan lewat WhatsApp, lalu ambil di toko.
                    Pesanan sudah siap saat Anda datang.
                </p>
                <div class="anim t3">
                    <a href="#katalog" class="btn btn-emas btn-lg rounded-pill px-4 me-2 mb-2">
                        <i class="bi bi-basket3 me-1"></i> Mulai Belanja
                    </a>
                    <a href="https://wa.me/<?= e(nomor_wa($toko['telepon'])) ?>" target="_blank" rel="noopener" class="btn btn-outline-light btn-lg rounded-pill px-4 mb-2">
                        <i class="bi bi-whatsapp me-1"></i> Hubungi Kami
                    </a>
                </div>
                <div class="info-hero anim t3">
                    <span><i class="bi bi-clock"></i>Buka 06.00 &ndash; 21.00 WIB</span>
                    <span><i class="bi bi-geo-alt"></i>Dusun Jaten</span>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Cara pesan & pembayaran -->
<section class="layanan-wrap pb-5" id="layanan">
    <div class="container">
        <div class="row g-3 text-center">
            <div class="col-md-4 muncul">
                <div class="card kartu-layanan h-100"><div class="card-body p-4">
                    <div class="ikon-bulat"><i class="bi bi-whatsapp"></i></div>
                    <h2 class="h5">Pesan via WhatsApp</h2>
                    <p class="text-body-secondary mb-0">Isi keranjang, isi form pemesanan, dan daftar belanjaan langsung terkirim ke <?= e($toko['pemilik']) ?>.</p>
                </div></div>
            </div>
            <div class="col-md-4 muncul">
                <div class="card kartu-layanan h-100"><div class="card-body p-4">
                    <div class="ikon-bulat"><i class="bi bi-shop"></i></div>
                    <h2 class="h5">Ambil di Toko</h2>
                    <p class="text-body-secondary mb-0">Pesanan disiapkan lebih dulu. Datang, bayar, dan bawa pulang.</p>
                </div></div>
            </div>
            <div class="col-md-4 muncul">
                <div class="card kartu-layanan h-100"><div class="card-body p-4">
                    <div class="ikon-bulat"><i class="bi bi-qr-code"></i></div>
                    <h2 class="h5">Tunai &amp; QRIS</h2>
                    <p class="text-body-secondary mb-2">Bayar tunai atau scan QRIS dari bank dan dompet digital.</p>
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalQris">Lihat QRIS</button>
                </div></div>
            </div>
        </div>
    </div>
</section>

<!-- Tentang -->
<section class="py-5" id="tentang">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-md-5 text-center muncul">
                <div class="foto-tentang">
                    <img src="<?= url($foto_tentang) ?>" alt="Tentang <?= e($toko['nama_toko']) ?>"
                         class="img-fluid" style="max-height: 340px; object-fit: cover;">
                </div>
            </div>
            <div class="col-md-7 muncul">
                <div class="kepala-bagian">
                    <span class="atas">Tentang Kami</span>
                    <h2><?= e($toko['nama_toko']) ?></h2>
                </div>
                <p class="text-body-secondary">
                    <?= e($toko['nama_toko']) ?> adalah warung sembako milik <?= e($toko['pemilik']) ?> yang melayani warga
                    Dusun Jaten dan sekitarnya: beras, minyak, gula, telur, bumbu dapur, dan kebutuhan rumah tangga.
                </p>
                <ul class="poin-tentang">
                    <li>
                        <span class="ikon"><i class="bi bi-tags"></i></span>
                        <div><strong>Harga bersahabat</strong><br><span class="text-body-secondary">Kebutuhan pokok dengan harga yang terjangkau.</span></div>
                    </li>
                    <li>
                        <span class="ikon"><i class="bi bi-bag-check"></i></span>
                        <div><strong>Pesanan siap saat datang</strong><br><span class="text-body-secondary">Kirim lewat WhatsApp, tinggal ambil di toko.</span></div>
                    </li>
                    <li>
                        <span class="ikon"><i class="bi bi-journal-check"></i></span>
                        <div><strong>Tercatat rapi</strong><br><span class="text-body-secondary">Kasir, stok, dan kasbon tersimpan di sistem, bukan lagi di buku tulis.</span></div>
                    </li>
                </ul>
                <p class="text-body-secondary small mt-3 mb-0">
                    Website ini bagian dari proyek kolaborasi <?= e(INFO_PROYEK['jurusan']) ?> <?= e(INFO_PROYEK['sekolah']) ?>:
                    analisis SWOT dan pengembangan UMKM berbasis digital.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Produk / Katalog -->
<section class="bg-krem border-top border-bottom py-5" id="katalog">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
            <div class="kepala-bagian mb-0">
                <span class="atas">Produk</span>
                <h2>Katalog Sembako</h2>
            </div>
            <form method="get" action="#katalog" class="d-flex gap-2">
                <?php if ($kategori_id > 0): ?><input type="hidden" name="kategori" value="<?= $kategori_id ?>"><?php endif; ?>
                <input type="search" name="q" class="form-control rounded-pill px-3" placeholder="Cari sembako..." value="<?= e($cari) ?>">
                <button class="btn btn-primary rounded-circle" type="submit" aria-label="Cari" style="width:42px;height:42px"><i class="bi bi-search"></i></button>
            </form>
        </div>

        <div class="pil-kategori d-flex flex-wrap gap-2 mb-4">
            <a href="?q=<?= urlencode($cari) ?>#katalog" class="btn btn-sm <?= $kategori_id === 0 ? 'btn-primary' : 'btn-outline-primary' ?>">Semua</a>
            <?php foreach ($daftar_kategori as $k): ?>
                <a href="?kategori=<?= $k['id'] ?>&q=<?= urlencode($cari) ?>#katalog"
                   class="btn btn-sm <?= $kategori_id === (int) $k['id'] ? 'btn-primary' : 'btn-outline-primary' ?>"><?= e($k['nama_kategori']) ?></a>
            <?php endforeach; ?>
        </div>

        <div class="row g-3">
            <?php foreach ($daftar_produk as $p): $foto = gambar_produk($p['gambar']); ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="card kartu-produk h-100">
                        <div class="card-body text-center d-flex flex-column">
                            <img src="<?= $foto ?>" alt="<?= e($p['nama_produk']) ?>" class="foto-produk mx-auto mb-2">
                            <h3 class="h6 mb-1"><?= e($p['nama_produk']) ?></h3>
                            <small class="text-body-secondary mb-2"><?= e($p['nama_kategori']) ?> &middot; per <?= e($p['satuan']) ?></small>
                            <div class="harga mt-auto mb-2"><?= rupiah($p['harga_jual']) ?></div>
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

<!-- Testimoni -->
<section class="py-5" id="testimoni">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
            <div class="kepala-bagian mb-0">
                <span class="atas">Testimoni</span>
                <h2>Kata Pelanggan</h2>
            </div>
            <button type="button" class="btn btn-outline-primary rounded-pill px-3" data-bs-toggle="collapse" data-bs-target="#formTestimoni"
                    aria-expanded="<?= $galat_testimoni ? 'true' : 'false' ?>" aria-controls="formTestimoni">
                <i class="bi bi-chat-heart me-1"></i> Tulis Testimoni
            </button>
        </div>

        <?php if ($testimoni_terkirim): ?>
            <div class="alert alert-success alert-dismissible" role="alert">
                Terima kasih! Testimoni Anda sudah tampil di bawah.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
            </div>
        <?php endif; ?>

        <div class="collapse <?= $galat_testimoni ? 'show' : '' ?> mb-4" id="formTestimoni">
            <form method="post" action="index.php#testimoni" class="card card-body border-0 shadow-sm rounded-4" novalidate>
                <input type="hidden" name="aksi" value="testimoni">
                <input type="hidden" name="token" value="<?= e($_SESSION['csrf_testimoni']) ?>">
                <div style="position:absolute;left:-9999px" aria-hidden="true">
                    <label>Jangan diisi <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                </div>

                <?php if ($galat_testimoni): ?>
                    <div class="alert alert-danger py-2" role="alert">
                        <?php foreach ($galat_testimoni as $g): ?><div><?= e($g) ?></div><?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="tNama" class="form-label">Nama</label>
                        <input type="text" id="tNama" name="nama" class="form-control" maxlength="40" required value="<?= e($isian['nama']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="tAsal" class="form-label">Asal / alamat <span class="text-body-secondary">(boleh dikosongkan)</span></label>
                        <input type="text" id="tAsal" name="asal" class="form-control" maxlength="40" placeholder="Contoh: Jaten RT 02" value="<?= e($isian['asal']) ?>">
                    </div>
                    <div class="col-12">
                        <label for="tBintang" class="form-label">Penilaian</label>
                        <select id="tBintang" name="bintang" class="form-select">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <option value="<?= $i ?>" <?= $isian['bintang'] === $i ? 'selected' : '' ?>><?= str_repeat('★', $i) ?> (<?= $i ?> dari 5)</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label for="tIsi" class="form-label">Testimoni</label>
                        <textarea id="tIsi" name="isi" class="form-control" rows="3" minlength="10" maxlength="300" required
                                  placeholder="Ceritakan pengalaman belanja Anda..."><?= e($isian['isi']) ?></textarea>
                        <div class="form-text">10 sampai 300 huruf.</div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary rounded-pill px-4"><i class="bi bi-send me-1"></i> Kirim Testimoni</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="row g-4">
            <?php foreach ($testimoni as $t): ?>
                <div class="col-md-4 muncul">
                    <figure class="card kartu-testimoni h-100 mb-0">
                        <div class="card-body d-flex flex-column p-4">
                            <div class="bintang mb-2" aria-label="<?= (int) $t['bintang'] ?> dari 5 bintang">
                                <?= str_repeat('★', (int) $t['bintang']) . str_repeat('☆', 5 - (int) $t['bintang']) ?>
                            </div>
                            <blockquote class="mb-3 flex-grow-1">&ldquo;<?= e($t['isi']) ?>&rdquo;</blockquote>
                            <figcaption class="d-flex align-items-center gap-2">
                                <span class="avatar"><?= e(mb_strtoupper(mb_substr($t['nama'], 0, 1))) ?></span>
                                <span class="small">
                                    <span class="fw-semibold d-block"><?= e($t['nama']) ?></span>
                                    <?php if (!empty($t['asal'])): ?><span class="text-body-secondary"><?= e($t['asal']) ?></span><?php endif; ?>
                                </span>
                            </figcaption>
                        </div>
                    </figure>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Lokasi -->
<section class="bg-krem border-top py-5" id="lokasi">
    <div class="container">
        <div class="row g-4 align-items-center">
            <div class="col-md-5 muncul">
                <div class="kepala-bagian">
                    <span class="atas">Kunjungi Kami</span>
                    <h2>Lokasi &amp; Jam Buka</h2>
                </div>
                <div class="baris-info">
                    <span class="ikon"><i class="bi bi-geo-alt"></i></span>
                    <div><small>Alamat</small><?= e($toko['alamat']) ?></div>
                </div>
                <div class="baris-info">
                    <span class="ikon"><i class="bi bi-person"></i></span>
                    <div><small>Pemilik</small><?= e($toko['pemilik']) ?></div>
                </div>
                <div class="baris-info">
                    <span class="ikon"><i class="bi bi-whatsapp"></i></span>
                    <div><small>WhatsApp</small><?= e($toko['telepon']) ?></div>
                </div>
                <div class="baris-info">
                    <span class="ikon"><i class="bi bi-clock"></i></span>
                    <div><small>Jam buka</small>Setiap hari, 06.00 &ndash; 21.00 WIB</div>
                </div>
                <a href="<?= e($peta_url) ?>" target="_blank" rel="noopener" class="btn btn-primary rounded-pill px-4 mt-2">
                    <i class="bi bi-signpost-2 me-1"></i> Buka di Google Maps
                </a>
            </div>
            <div class="col-md-7 muncul">
                <div class="ratio ratio-16x9 rounded-4 overflow-hidden shadow">
                    <iframe src="<?= e($peta_src) ?>" title="Peta lokasi <?= e($toko['nama_toko']) ?>" loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
                </div>
            </div>
        </div>
    </div>
</section>

<footer class="footer-gelap pt-5 pb-4">
    <div class="container">
        <div class="row g-4 pb-4">
            <div class="col-md-5">
                <div class="d-flex align-items-center mb-2 text-white fw-bold fs-5">
                    <img src="<?= url($berkas_logo) ?>" alt="Logo" height="40" class="me-2" style="object-fit:contain">
                    <?= e($toko['nama_toko']) ?>
                </div>
                <p class="small mb-0">Belanja sembako harian dengan mudah: pesan lewat WhatsApp, ambil di toko.</p>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-white fw-semibold mb-2">Menu</div>
                <div class="small d-flex flex-column gap-1">
                    <a href="#tentang">Tentang</a>
                    <a href="#katalog">Produk</a>
                    <a href="#testimoni">Testimoni</a>
                    <a href="#lokasi">Lokasi</a>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="text-white fw-semibold mb-2">Hubungi</div>
                <div class="small d-flex flex-column gap-1">
                    <span><i class="bi bi-whatsapp me-1"></i><?= e($toko['telepon']) ?></span>
                    <span><i class="bi bi-clock me-1"></i>06.00 &ndash; 21.00 WIB</span>
                </div>
            </div>
        </div>
        <div class="garis pt-3 text-center small">
            &copy; <?= date('Y') ?> <?= e($toko['nama_toko']) ?> &middot;
            <?= e(INFO_PROYEK['kelompok']) ?> (<?= e(implode(' & ', INFO_PROYEK['anggota'])) ?>) &middot;
            <?= e(INFO_PROYEK['sekolah']) ?> TP <?= e(INFO_PROYEK['tahun']) ?>
        </div>
    </div>
</footer>

<!-- Keranjang & form pemesanan (panel geser dari kanan) -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="panelKeranjang">
    <div class="kepala-panel d-flex align-items-center gap-3">
        <span class="ikon-kepala"><i class="bi bi-basket3-fill"></i></span>
        <div class="flex-grow-1">
            <h2 class="offcanvas-title h5 mb-0 fw-bold">Keranjang &amp; Pemesanan</h2>
            <small>Kirim pesanan, ambil di toko, tanpa antre</small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
    </div>

    <div class="offcanvas-body p-0 d-flex flex-column">
        <div class="area-gulir">

            <!-- 1. Belanjaan -->
            <div class="blok">
                <div class="judul-blok"><span class="nomor">1</span> Belanjaan Anda</div>
                <ul class="list-group list-group-flush" id="daftarKeranjang"></ul>
                <div class="text-end mt-1">
                    <button type="button" class="btn btn-link btn-sm text-danger text-decoration-none p-0" id="tombolKosongkan">
                        <i class="bi bi-trash3 me-1"></i>Kosongkan keranjang
                    </button>
                </div>
            </div>

            <!-- Pengambilan: hanya di toko. Radio disembunyikan agar katalog.js tetap membaca nilainya. -->
            <input type="radio" name="layanan" id="layananAmbil" value="Ambil di Toko" checked hidden>
            <div class="info-ambil">
                <i class="bi bi-shop-window"></i>
                <div><strong>Ambil di toko</strong><br>Pesanan kami siapkan, Anda tinggal datang.</div>
            </div>

            <!-- 2. Data pemesan -->
            <div class="blok">
                <div class="judul-blok"><span class="nomor">2</span> Data Pemesan</div>

                <label for="namaPemesan" class="form-label small text-body-secondary mb-1">Nama pemesan</label>
                <div class="input-group mb-3">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" id="namaPemesan" class="form-control" placeholder="Nama Anda">
                </div>

                <label for="alamatPemesan" class="form-label small text-body-secondary mb-1">Waktu ambil / catatan</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-clock-history"></i></span>
                    <input type="text" id="alamatPemesan" class="form-control" placeholder="Contoh: diambil jam 16.00, telur yang besar">
                </div>
            </div>

            <!-- 3. Pembayaran -->
            <div class="blok mb-0">
                <div class="judul-blok"><span class="nomor">3</span> Cara Bayar</div>
                <div class="pilih-bayar">
                    <input type="radio" class="btn-check" name="bayar" id="bayarTunai" value="Tunai (bayar saat ambil)" checked>
                    <label for="bayarTunai">
                        <i class="bi bi-cash-coin"></i>
                        <span>Tunai</span>
                        <small>Bayar saat ambil</small>
                    </label>

                    <input type="radio" class="btn-check" name="bayar" id="bayarQris" value="QRIS">
                    <label for="bayarQris">
                        <i class="bi bi-qr-code-scan"></i>
                        <span>QRIS</span>
                        <small>Bank &amp; dompet digital</small>
                    </label>
                </div>
            </div>
        </div>

        <!-- Footer tetap -->
        <div class="kaki-panel">
            <div class="total-baris mb-2">
                <span class="text-body-secondary">Total belanja</span>
                <span id="totalKeranjang">Rp 0</span>
            </div>
            <button type="button" class="btn btn-success w-100" id="tombolPesan" disabled>
                <i class="bi bi-whatsapp me-2"></i>Kirim Pesanan via WhatsApp
            </button>
            <div class="text-center small text-body-secondary mt-2">
                <i class="bi bi-shield-check me-1"></i>Pesanan dikirim langsung ke pemilik toko
            </div>
        </div>
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
<script src="<?= url('assets/js/katalog.js') ?>?v=20261110-4"></script>
<script>
/* Munculkan elemen .muncul saat masuk layar */
(function () {
    var el = document.querySelectorAll('.muncul');
    if (!('IntersectionObserver' in window)) {
        el.forEach(function (e) { e.classList.add('tampil'); });
        return;
    }
    var io = new IntersectionObserver(function (items) {
        items.forEach(function (i) {
            if (i.isIntersecting) { i.target.classList.add('tampil'); io.unobserve(i.target); }
        });
    }, { threshold: 0.12 });
    el.forEach(function (e) { io.observe(e); });
})();
</script>
</body>
</html>
