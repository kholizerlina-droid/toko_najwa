# Web App Warung Sembako "Toko Najwa"

Aplikasi web **PHP native + MySQL** dengan tampilan **Bootstrap 5 murni** untuk Warung Sembako
**Toko Najwa** (Ibu Dhiyah, Dusun Jaten, Desa Bonomerto, Kec. Suruh, Kab. Semarang).
Dibuat sebagai proyek kolaborasi *Analisis SWOT dan Pengembangan UMKM Berbasis Digital* —
SMK Negeri Tengaran, XI PPLG/RPL, Kelompok 6 (Kholiz Erlina & Sifa Fuji Febriyana), TP 2026/2027.

Fitur menjawab masalah prioritas pada laporan SWOT (pencatatan manual, barang kadaluarsa,
tidak tahu produk terlaris, jangkauan pemasaran terbatas):

| Masalah di laporan | Fitur |
|---|---|
| Catatan transaksi di buku tulis | Kasir (POS) + riwayat transaksi + cetak struk |
| Selisih kas & laba tidak jelas | Laporan laba rugi otomatis + pengeluaran |
| Barang kadaluarsa tidak terpantau | Peringatan kadaluarsa ≤ 30 hari & stok menipis |
| Tidak tahu produk laris | Dashboard "Produk Terlaris" |
| Buku utang warga terselip | Buku kasbon + cicilan + pengingat WhatsApp |
| Jangkauan pemasaran terbatas | Website katalog + pesan-antar via WhatsApp |

## Cara menjalankan (Laragon)

1. Letakkan folder ini di `laragon/www/toko_najwa`, lalu nyalakan **Apache** dan **MySQL**.
2. Impor database, pilih salah satu:
   - buka `http://localhost/toko_najwa/database/install.php` lalu klik tombol, **atau**
   - impor file `database/toko_najwa.sql` lewat phpMyAdmin / HeidiSQL.
3. Buka `http://localhost/toko_najwa/` (website publik).
4. Halaman petugas: `http://localhost/toko_najwa/login.php` (tidak ditautkan di website publik).

Jika user/password MySQL berbeda, ubah di `config/config.php`.

**Akun demo** (password keduanya `admin123`, segera ganti lewat menu Pengaturan):

| Peran | Username |
|---|---|
| Pemilik | `admin` |
| Kasir | `kasir` |

> Ikon memakai Bootstrap Icons dari CDN, jadi perlu internet agar ikon tampil.

## Struktur folder

```
toko_najwa/
├── index.php              Website publik (profil, katalog, keranjang → WhatsApp)
├── login.php, logout.php
├── config/
│   ├── config.php         Pengaturan database, zona waktu, info proyek  ← edit di sini
│   └── database.php       Fungsi db() untuk koneksi MySQL
├── includes/
│   ├── init.php           Dipanggil paling atas oleh semua halaman
│   ├── init_admin.php     Sama, tapi wajib login + cek CSRF (halaman admin)
│   ├── helpers.php        Fungsi bantu: query, format rupiah/tanggal, flash, CSRF
│   ├── auth.php           Login, logout, pembatasan akses
│   ├── laporan.php        Perhitungan laba rugi (dipakai layar & cetak)
│   └── layout/            Kerangka halaman admin: admin_top, admin_bottom, sidebar
├── admin/                 Satu file = satu halaman
│   ├── index.php          Dashboard
│   ├── pos.php            Kasir
│   ├── produk.php, kategori.php, restock.php
│   ├── transaksi.php, piutang.php, pengeluaran.php, laporan.php
│   ├── pengaturan.php     (khusus admin)
│   ├── cetak_struk.php, cetak_laporan.php
│   └── ajax/              Potongan HTML untuk modal (rincian nota, riwayat cicilan)
├── assets/
│   ├── css/app.css        CSS tambahan yang sangat kecil
│   ├── js/                Satu file JS per halaman (pos.js, katalog.js, ...) + admin.js
│   ├── img/               QRIS, gambar produk kosong, produk/ (foto unggahan), logo.png (taruh di sini)
│   └── vendor/bootstrap/  Bootstrap 5.3 (CSS + JS)
└── database/
    ├── toko_najwa.sql     Struktur tabel + data contoh
    └── install.php        Impor database lewat browser
```

## Pola kode (supaya mudah dikelola)

Setiap halaman admin disusun dengan urutan yang sama:

```php
<?php
require_once __DIR__ . '/../includes/init_admin.php';   // 1. login + keamanan

if (is_post()) { ... redirect(...); }                    // 2. proses form (simpan/hapus)

$data = ambil_semua('SELECT ...');                       // 3. ambil data

$judul_halaman = '...'; $menu_aktif = '...';             // 4. tampilan
include __DIR__ . '/../includes/layout/admin_top.php';
?>
<!-- HTML Bootstrap -->
<?php include __DIR__ . '/../includes/layout/admin_bottom.php'; ?>
```

- **Query database**: `ambil_semua()`, `ambil_satu()`, `ambil_nilai()`, `jalankan()` — semuanya
  prepared statement (aman dari SQL injection). Contoh: `ambil_semua('SELECT * FROM produk WHERE id = ?', 'i', [$id])`.
- **Form POST** wajib memuat `<?= csrf_field() ?>`; pemeriksaannya otomatis di `init_admin.php`.
- **Menambah menu sidebar**: tambah satu baris di `includes/layout/sidebar.php`.
- **Menambah halaman admin**: salin halaman yang paling mirip, ubah query & HTML-nya.
- **Modal tambah/ubah**: tombol cukup diberi `data-aksi`, `data-id`, `data-nama_kolom`, … dan
  `assets/js/admin.js` mengisi form otomatis. Contoh ada di `produk.php`.
- **Konfirmasi hapus**: beri atribut `data-konfirmasi="Pesan?"` pada `<form>`.

## Catatan keamanan

- Password disimpan sebagai hash (`password_hash`), semua input melewati prepared statement,
  semua output di-escape dengan `e()`, setiap form dilindungi token CSRF.
- Harga di kasir dibaca ulang dari database saat bayar (tidak mempercayai data dari browser).
- Hapus `database/install.php` setelah dipakai di server sungguhan, dan ganti password akun demo.

## Logo dan foto produk

- **Logo**: simpan file di `assets/img/logo.png` (boleh juga `logo.svg`, `logo.jpg`, `logo.webp`). Selama belum ada,
  tampil kotak putus-putus bertuliskan "LOGO". Begitu file dipasang, logo muncul otomatis di sidebar admin
  (pojok kanan atas), bilah atas HP, halaman login, website publik, struk, dan laporan cetak. Ukuran persegi
  (mis. 512×512 px) paling pas. Fungsi pengaturnya: `logo()` di `includes/helpers.php`.
- **Foto produk**: unggah lewat menu **Data Produk → Tambah / Ubah → Foto Produk** (JPG/PNG/WEBP, maks. 2 MB).
  File tersimpan di `assets/img/produk/` dan nama file di kolom `produk.gambar`. Produk tanpa foto memakai
  `assets/img/produk-kosong.svg`. Foto paling bagus berbentuk persegi.
- **Warna biru pastel**: diatur di bagian atas `assets/css/app.css` (variabel `--pastel-biru`, `--biru-teks`, `--biru-tua`).
