# Web App Warung Sembako "Toko Najwa" (Bento Grid Edition)

Aplikasi web berbasis **PHP Murni (Native)** dan database **MySQL (`db_tokonajwa`)** yang disederhanakan khusus untuk **presentasi proyek kolaborasi sekolah** (SMK Negeri Tengaran - Jurusan PPLG Kelas XI Tahun 2026/2027) di Warung Sembako **Toko Najwa** milik **Ibu Dhiyah** (Dusun Jaten, Desa Bonomerto, Kec. Suruh, Kab. Semarang).

---

## 🍱 Konsep Tata Letak: Bento Grid Layout
Aplikasi mengadopsi format **Bento Grid** modern dengan kotak-kotak informasi modular yang asimetris, visual, dan berfokus pada kemudahan presentasi:
1. **Hero Bento Utama:** Menampilkan profil Toko Najwa, keunggulan sembako murah, dan tombol belanja.
2. **Bento Antar Rumah:** Fitur pesan antar warga Jaten via WhatsApp.
3. **Bento Pembayaran:** Dukungan Tunai & QRIS resmi Toko Najwa.
4. **Bento Jam Buka & Lokasi:** 06.00 - 21.00 WIB di Bonomerto, Suruh.
5. **Bento Proyek Siswa:** Kelompok 6 PPLG (Kholiz Erlina & Sifa Fuji Febriyana).
6. **Bento Dashboard Kasir:** Metrik ringkas omset, estimasi laba, kasbon, peringatan kadaluarsa (SWOT), stok menipis, dan produk terlaris (*Fast-Moving*).

---

## 🔒 Privasi Akses Login Petugas / Kasir
Sesuai arahan, halaman publik pembeli (`index.php`) **tidak menampilkan tombol login** agar tampilan murni untuk warga/pembeli.
Hanya admin/presenter yang mengetahui alamat login:
👉 **URL Login Petugas:** `http://localhost/toko_najwa/login.php`

**Akun Demo Presentasi:**
- **Admin / Ibu Dhiyah:** Username `admin` | Password `admin123`
- **Kasir:** Username `kasir` | Password `admin123`
*(Tersedia tombol 1-klik di halaman login untuk langsung mengisi akun tanpa mengetik manual saat demo).*

---

## 🎯 5 Modul Inti Presentasi Sekolah
1. **Website Profil & Katalog Online (`index.php`):** Katalog sembako interaktif + Keranjang belanja pesan-antar WhatsApp.
2. **Sistem Kasir POS (`admin/pos.php`):** Scan barcode / klik sembako, hitung uang pas/pecahan, cetak nota struk thermal (`admin/cetak_struk.php`).
3. **Inventaris & Deteksi Kadaluarsa (`admin/produk.php`):** Peringatan otomatis produk expired (merah) & mendekati expired < 30 hari (kuning) sesuai temuan SWOT.
4. **Buku Kasbon Warga (`admin/piutang.php`):** Digitalisasi buku utang harian, catat cicilan lunas, dan tombol kirim pengingat WhatsApp.
5. **Laporan Laba Rugi Otomatis (`admin/laporan.php`):** Pemisahan modal beli vs laba bersih + cetak format dokumen laporan resmi.
