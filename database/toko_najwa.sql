-- =====================================================================
-- DATABASE TOKO NAJWA  (db_tokonajwa)
-- Warung Sembako Toko Najwa - SMK Negeri Tengaran, PPLG/RPL Kelas XI
--
-- Cara pakai : import file ini lewat phpMyAdmin / HeidiSQL,
--              atau buka  http://localhost/toko_najwa/database/install.php
-- PERINGATAN : file ini MENGHAPUS lalu membuat ulang semua tabel,
--              jadi semua data lama akan hilang.
--
-- Daftar tabel:
--   users, kategori, produk, pelanggan, transaksi, transaksi_detail,
--   piutang, piutang_pembayaran, pesanan_online, restock, pengeluaran, pengaturan
-- =====================================================================

CREATE DATABASE IF NOT EXISTS db_tokonajwa DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE db_tokonajwa;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS piutang_pembayaran, piutang, transaksi_detail, transaksi, pesanan_online,
                     restock, pengeluaran, produk, pelanggan, kategori, pengaturan, users;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- STRUKTUR TABEL
-- ---------------------------------------------------------------------

-- Akun yang boleh masuk ke halaman admin
CREATE TABLE users (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    username     VARCHAR(50)  NOT NULL UNIQUE,
    password     VARCHAR(255) NOT NULL,                 -- disimpan dalam bentuk hash
    nama_lengkap VARCHAR(100) NOT NULL,
    role         ENUM('admin', 'kasir') NOT NULL DEFAULT 'kasir',
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pengelompokan barang (Sembako Utama, Bumbu, Minuman, dst.)
CREATE TABLE kategori (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nama_kategori VARCHAR(100) NOT NULL,
    deskripsi     TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Data barang dagangan
CREATE TABLE produk (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    kode_barcode   VARCHAR(50)  NOT NULL UNIQUE,
    kategori_id    INT NOT NULL,
    nama_produk    VARCHAR(150) NOT NULL,
    satuan         VARCHAR(30)  NOT NULL DEFAULT 'Pcs',
    harga_beli     DECIMAL(12,0) NOT NULL DEFAULT 0,    -- modal
    harga_jual     DECIMAL(12,0) NOT NULL DEFAULT 0,
    stok           INT NOT NULL DEFAULT 0,
    min_stok       INT NOT NULL DEFAULT 5,              -- batas peringatan stok menipis
    tgl_kadaluarsa DATE NULL,
    gambar         VARCHAR(255) NULL,                   -- nama file foto di assets/img/produk/
    FOREIGN KEY (kategori_id) REFERENCES kategori(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Data pelanggan terdaftar untuk transaksi dan kasbon
CREATE TABLE pelanggan (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    nama_pelanggan VARCHAR(100) NOT NULL UNIQUE,
    no_telepon     VARCHAR(30) NULL,
    alamat         TEXT NULL,
    batas_utang    DECIMAL(12,0) NOT NULL DEFAULT 0,
    catatan        TEXT NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Satu baris = satu nota/struk penjualan
CREATE TABLE transaksi (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    no_faktur         VARCHAR(50) NOT NULL UNIQUE,      -- contoh: TRX-20261103-0001
    tanggal           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    user_id           INT NULL,                         -- kasir yang melayani
    pelanggan_id      INT NULL,
    nama_pelanggan    VARCHAR(100) NOT NULL DEFAULT 'Warga / Umum',
    total_harga       DECIMAL(12,0) NOT NULL DEFAULT 0,
    total_bayar       DECIMAL(12,0) NOT NULL DEFAULT 0,
    kembalian         DECIMAL(12,0) NOT NULL DEFAULT 0,
    metode_pembayaran ENUM('tunai', 'qris', 'utang') NOT NULL DEFAULT 'tunai',
    status_pembayaran ENUM('lunas', 'belum_lunas') NOT NULL DEFAULT 'lunas',
    catatan           TEXT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (pelanggan_id) REFERENCES pelanggan(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rincian barang pada setiap nota (harga beli/jual disalin agar laporan tidak berubah
-- walaupun harga produk diubah di kemudian hari)
CREATE TABLE transaksi_detail (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    transaksi_id INT NOT NULL,
    produk_id    INT NOT NULL,
    harga_beli   DECIMAL(12,0) NOT NULL DEFAULT 0,
    harga_jual   DECIMAL(12,0) NOT NULL DEFAULT 0,
    qty          INT NOT NULL DEFAULT 1,
    subtotal     DECIMAL(12,0) NOT NULL DEFAULT 0,
    keuntungan   DECIMAL(12,0) NOT NULL DEFAULT 0,
    FOREIGN KEY (transaksi_id) REFERENCES transaksi(id) ON DELETE CASCADE,
    FOREIGN KEY (produk_id)    REFERENCES produk(id)    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Buku kasbon: utang warga
CREATE TABLE piutang (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    transaksi_id    INT NULL,                           -- terisi jika kasbon berasal dari kasir
    pelanggan_id    INT NULL,
    nama_pelanggan  VARCHAR(100) NOT NULL,
    no_telepon      VARCHAR(30) NULL,
    total_utang     DECIMAL(12,0) NOT NULL DEFAULT 0,
    sisa_utang      DECIMAL(12,0) NOT NULL DEFAULT 0,
    tgl_utang       DATE NOT NULL,
    tgl_jatuh_tempo DATE NULL,
    status          ENUM('belum_lunas', 'lunas') NOT NULL DEFAULT 'belum_lunas',
    catatan         TEXT NULL,
    FOREIGN KEY (transaksi_id) REFERENCES transaksi(id) ON DELETE SET NULL,
    FOREIGN KEY (pelanggan_id) REFERENCES pelanggan(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Riwayat cicilan / pelunasan kasbon
CREATE TABLE piutang_pembayaran (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    piutang_id   INT NOT NULL,
    tgl_bayar    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    jumlah_bayar DECIMAL(12,0) NOT NULL,
    metode       VARCHAR(50) NOT NULL DEFAULT 'tunai',
    catatan      VARCHAR(255) NULL,
    FOREIGN KEY (piutang_id) REFERENCES piutang(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pesanan dari katalog yang dikirim ke WhatsApp (belum menjadi transaksi kasir)
CREATE TABLE pesanan_online (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    kode_pesanan   VARCHAR(32) NOT NULL UNIQUE,
    nama_pemesan   VARCHAR(100) NOT NULL,
    layanan        VARCHAR(50) NOT NULL DEFAULT 'Ambil di Toko',
    catatan        TEXT NULL,
    metode_bayar   VARCHAR(100) NOT NULL,
    total          DECIMAL(12,0) NOT NULL DEFAULT 0,
    item_json      JSON NOT NULL,
    status         ENUM('baru', 'diproses', 'selesai', 'dibatalkan') NOT NULL DEFAULT 'baru',
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Riwayat kulakan (barang masuk dari grosir)
CREATE TABLE restock (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    produk_id           INT NOT NULL,
    tanggal             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    qty_masuk           INT NOT NULL,
    harga_beli_satuan   DECIMAL(12,0) NOT NULL DEFAULT 0,
    supplier            VARCHAR(150) NULL,
    tgl_kadaluarsa_baru DATE NULL,
    catatan             TEXT NULL,
    FOREIGN KEY (produk_id) REFERENCES produk(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Biaya operasional (listrik, bensin kulakan, kantong plastik, dst.)
CREATE TABLE pengeluaran (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    tanggal              DATE NOT NULL,
    kategori_pengeluaran VARCHAR(100) NOT NULL,
    jumlah               DECIMAL(12,0) NOT NULL,
    keterangan           TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Profil toko (hanya 1 baris)
CREATE TABLE pengaturan (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    nama_toko         VARCHAR(100) NOT NULL,
    pemilik           VARCHAR(100) NOT NULL,
    telepon           VARCHAR(30)  NOT NULL,
    alamat            TEXT NOT NULL,
    teks_footer_struk TEXT NULL,
    qris_image        VARCHAR(255) NULL                 -- nama file QRIS di assets/img/qris/
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- DATA AWAL
-- ---------------------------------------------------------------------

-- Akun demo. Password keduanya: admin123  (WAJIB diganti saat dipakai sungguhan)
INSERT INTO users (username, password, nama_lengkap, role) VALUES
('admin', '$2y$12$BHaIiQhnIONV2NXcwfM27uISiOTOFezmZ/CiJaqsUTqovyT/wN5y.', 'Ibu Dhiyah (Pemilik)', 'admin'),
('kasir', '$2y$12$BHaIiQhnIONV2NXcwfM27uISiOTOFezmZ/CiJaqsUTqovyT/wN5y.', 'Kasir Toko Najwa',    'kasir');

INSERT INTO pengaturan (nama_toko, pemilik, telepon, alamat, teks_footer_struk) VALUES
('Warung Sembako Toko Najwa', 'Ibu Dhiyah', '085712345678',
 'Jaten, Bonomerto Rt.02 Rw.04 Kec. Suruh, Kab. Semarang',
 'Terima kasih telah berbelanja di Toko Najwa!\nBarang yang sudah dibeli tidak dapat ditukar kecuali ada perjanjian.');

INSERT INTO kategori (nama_kategori, deskripsi) VALUES
('Sembako Utama',           'Beras, minyak goreng, gula pasir, tepung, telur'),
('Bumbu & Dapur',           'Garam, penyedap, merica, kecap, saus'),
('Minuman & Seduhan',       'Kopi sachet, teh, susu kental manis, air mineral'),
('Makanan Ringan & Mie',    'Mie instan, biskuit, kerupuk, camilan harian'),
('Kebutuhan Rumah Tangga',  'Sabun cuci piring, deterjen, sabun mandi, pasta gigi'),
('Rokok & Lainnya',         'Rokok bungkus, korek api, obat-obatan warung');

-- Produk contoh. Kolom terakhir = kadaluarsa dalam ... hari dari hari ini
-- (supaya peringatan kadaluarsa di dashboard selalu bisa didemokan).
INSERT INTO produk (kode_barcode, kategori_id, nama_produk, satuan, harga_beli, harga_jual, stok, min_stok, tgl_kadaluarsa) VALUES
-- Sembako Utama
('8991001001', 1, 'Minyak Goreng Sedap 1 Liter',            'Pouch',   16000, 18500, 24,  5, DATE_ADD(CURDATE(), INTERVAL 200 DAY)),
('8991001002', 1, 'Minyak Goreng Filma 2 Liter',            'Pouch',   33500, 37000, 15,  4, DATE_ADD(CURDATE(), INTERVAL 220 DAY)),
('8991001003', 1, 'Minyak Goreng Fortune 1 Liter',          'Pouch',   15800, 18000, 20,  5, DATE_ADD(CURDATE(), INTERVAL 180 DAY)),
('8991001004', 1, 'Minyak Goreng Minyakita 1 Liter',        'Botol',   14500, 16000, 36, 10, DATE_ADD(CURDATE(), INTERVAL 250 DAY)),
('8991001005', 1, 'Beras C4 Super 5 Kg',                    'Karung',  64000, 72000, 18,  5, DATE_ADD(CURDATE(), INTERVAL 300 DAY)),
('8991001006', 1, 'Beras Raja Lele 1 Kg (Eceran)',          'Kg',      13000, 14500, 45, 10, DATE_ADD(CURDATE(), INTERVAL 320 DAY)),
('8991001007', 1, 'Gula Pasir Gulaku Kuning 1 Kg',          'Kg',      15500, 17500, 28,  5, DATE_ADD(CURDATE(), INTERVAL 280 DAY)),
('8991001008', 1, 'Gula Pasir Curah 1 Kg',                  'Kg',      14500, 16500, 30,  8, DATE_ADD(CURDATE(), INTERVAL 150 DAY)),
('8991001009', 1, 'Telur Ayam Ras Segar 1 Kg',              'Kg',      25000, 28000, 25,  5, DATE_ADD(CURDATE(), INTERVAL 12 DAY)),
('8991001010', 1, 'Tepung Terigu Segitiga Biru 1 Kg',       'Kg',      11000, 13000, 19,  5, DATE_ADD(CURDATE(), INTERVAL 140 DAY)),
('8991001011', 1, 'Tepung Beras Rose Brand 500gr',          'Bungkus',  7500,  9000, 16,  4, DATE_ADD(CURDATE(), INTERVAL 120 DAY)),
-- Bumbu & Dapur
('8992001001', 2, 'Royco Bumbu Kaldu Ayam 8gr (1 Renceng)', 'Renceng',  4500,  6000, 14,  3, DATE_ADD(CURDATE(), INTERVAL 260 DAY)),
('8992001002', 2, 'Masako Rasa Sapi 8gr (1 Renceng)',       'Renceng',  4500,  6000, 12,  3, DATE_ADD(CURDATE(), INTERVAL 270 DAY)),
('8992001003', 2, 'Ladaku Merica Bubuk Sachet',             'Renceng',  9000, 11000, 10,  2, DATE_ADD(CURDATE(), INTERVAL 290 DAY)),
('8992001004', 2, 'Kecap Bango Manis Refill 550ml',         'Pouch',   21000, 24000,  8,  2, DATE_ADD(CURDATE(), INTERVAL 160 DAY)),
('8992001005', 2, 'Garam Beryodium Cap Daun 250gr',         'Bungkus',  2500,  3500, 22,  5, DATE_ADD(CURDATE(), INTERVAL 400 DAY)),
('8992001006', 2, 'Saus Sambal ABC Botol 275ml',            'Botol',   13000, 15500,  6,  2, DATE_ADD(CURDATE(), INTERVAL 7 DAY)),
-- Minuman & Seduhan
('8993001001', 3, 'Kopi Kapal Api Special Mix (1 Renceng)', 'Renceng', 13500, 16000, 25,  4, DATE_ADD(CURDATE(), INTERVAL 330 DAY)),
('8993001002', 3, 'Torabika Cappuccino (1 Renceng)',        'Renceng', 18000, 21000, 15,  3, DATE_ADD(CURDATE(), INTERVAL 290 DAY)),
('8993001003', 3, 'Teh Tong Tji Melati Celup 25 Bag',       'Kotak',    9000, 11500, 14,  3, DATE_ADD(CURDATE(), INTERVAL 360 DAY)),
('8993001004', 3, 'Susu Frisian Flag Cokelat Kaleng 370gr','Kaleng',  11500, 13500, 18,  4, DATE_ADD(CURDATE(), INTERVAL 240 DAY)),
('8993001005', 3, 'Air Mineral Le Minerale 600ml',          'Botol',    3000,  4000, 48, 12, DATE_ADD(CURDATE(), INTERVAL 450 DAY)),
('8993001006', 3, 'Air Mineral Galon Aqua (Isi Ulang)',     'Galon',   19000, 22000,  3,  5, DATE_ADD(CURDATE(), INTERVAL 90 DAY)),
-- Makanan Ringan & Mie
('8994001001', 4, 'Indomie Goreng Spesial 85gr',            'Bungkus',  2800,  3500, 80, 20, DATE_ADD(CURDATE(), INTERVAL 210 DAY)),
('8994001002', 4, 'Indomie Kuah Ayam Bawang 69gr',          'Bungkus',  2700,  3300, 60, 15, DATE_ADD(CURDATE(), INTERVAL 200 DAY)),
('8994001003', 4, 'Mie Sedaap Goreng 90gr',                 'Bungkus',  2800,  3500, 55, 15, DATE_ADD(CURDATE(), INTERVAL 220 DAY)),
('8994001004', 4, 'Biskuit Roma Kelapa 300gr',              'Bungkus',  9500, 11500,  2,  5, DATE_ADD(CURDATE(), INTERVAL 170 DAY)),
-- Kebutuhan Rumah Tangga
('8995001001', 5, 'Sunlight Jeruk Nipis 650ml Refill',      'Pouch',   12500, 15000, 12,  3, DATE_ADD(CURDATE(), INTERVAL 380 DAY)),
('8995001002', 5, 'Deterjen Daia Putih 800gr',              'Bungkus',  16000, 18500,  9,  3, DATE_ADD(CURDATE(), INTERVAL 350 DAY)),
('8995001003', 5, 'Sabun Mandi Lifebuoy Merah 85gr',        'Pcs',      3500,  4500, 30,  6, DATE_ADD(CURDATE(), INTERVAL 500 DAY)),
('8995001004', 5, 'Pasta Gigi Pepsodent 120gr',             'Pcs',      8500, 10500, 14,  4, DATE_ADD(CURDATE(), INTERVAL 450 DAY)),
-- Rokok & Lainnya
('8996001001', 6, 'Rokok Sampoerna Mild 16',                'Bungkus', 31500, 34000, 20,  5, DATE_ADD(CURDATE(), INTERVAL 400 DAY)),
('8996001002', 6, 'Rokok Gudang Garam Surya 12',            'Bungkus', 22500, 25000, 25,  5, DATE_ADD(CURDATE(), INTERVAL 400 DAY)),
('8996001003', 6, 'Rokok Djarum Super 12',                  'Bungkus', 22000, 24500, 18,  5, DATE_ADD(CURDATE(), INTERVAL 400 DAY));

-- Kasbon warga (contoh dari buku catatan manual Bu Dhiyah)
INSERT INTO piutang (nama_pelanggan, no_telepon, total_utang, sisa_utang, tgl_utang, tgl_jatuh_tempo, status, catatan) VALUES
('Pak Budi (RT 02)', '081233445566', 85000, 85000, DATE_SUB(CURDATE(), INTERVAL 9 DAY),  DATE_ADD(CURDATE(), INTERVAL 5 DAY),  'belum_lunas', 'Beras 5kg + Minyak 1L'),
('Bu Sari (Jaten)',  '085711223344', 45000, 20000, DATE_SUB(CURDATE(), INTERVAL 14 DAY), DATE_SUB(CURDATE(), INTERVAL 4 DAY),  'belum_lunas', 'Telur & bumbu dapur (sudah cicil 25rb)'),
('Mbah Joko',        '089877665544', 32000,     0, DATE_SUB(CURDATE(), INTERVAL 20 DAY), DATE_SUB(CURDATE(), INTERVAL 10 DAY), 'lunas',       'Kopi & rokok Surya');

INSERT INTO piutang_pembayaran (piutang_id, tgl_bayar, jumlah_bayar, metode, catatan) VALUES
(2, DATE_SUB(NOW(), INTERVAL 7 DAY),  25000, 'tunai', 'Cicilan pertama'),
(3, DATE_SUB(NOW(), INTERVAL 12 DAY), 32000, 'tunai', 'Pelunasan');

INSERT INTO pengeluaran (tanggal, kategori_pengeluaran, jumlah, keterangan) VALUES
(DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'Operasional Kulakan',   25000, 'Bensin motor kulakan ke Pasar Suruh'),
(DATE_SUB(CURDATE(), INTERVAL 4 DAY), 'Perlengkapan Warung',   18000, 'Kantong plastik kresek aneka ukuran'),
(DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'Listrik & Penerangan',  60000, 'Token listrik warung');

-- Tiga nota penjualan contoh agar dashboard & laporan langsung terisi
INSERT INTO transaksi (no_faktur, tanggal, user_id, nama_pelanggan, total_harga, total_bayar, kembalian, metode_pembayaran, status_pembayaran) VALUES
(CONCAT('TRX-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '%Y%m%d'), '-0001'), DATE_SUB(NOW(), INTERVAL 2 DAY), 1, 'Ibu Sri Rahayu', 52500, 60000, 7500, 'tunai', 'lunas'),
(CONCAT('TRX-', DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '%Y%m%d'), '-0001'), DATE_SUB(NOW(), INTERVAL 1 DAY), 1, 'Mas Doni',       48000, 48000,    0, 'qris',  'lunas'),
(CONCAT('TRX-', DATE_FORMAT(CURDATE(), '%Y%m%d'), '-0001'),                            NOW(),                          2, 'Tetangga Sebelah', 32000, 50000, 18000, 'tunai', 'lunas');

-- Rincian: harga diambil otomatis dari tabel produk
INSERT INTO transaksi_detail (transaksi_id, produk_id, harga_beli, harga_jual, qty, subtotal, keuntungan)
SELECT t.id, p.id, p.harga_beli, p.harga_jual, d.qty, p.harga_jual * d.qty, (p.harga_jual - p.harga_beli) * d.qty
FROM (
    SELECT 1 AS trx, '8991001001' AS barcode, 1 AS qty UNION ALL   -- Minyak Sedap
    SELECT 1,        '8991001007',            1        UNION ALL   -- Gula Gulaku
    SELECT 1,        '8994001001',            3        UNION ALL   -- Indomie goreng
    SELECT 1,        '8992001001',            1        UNION ALL   -- Royco
    SELECT 2,        '8996001002',            1        UNION ALL   -- Rokok Surya
    SELECT 2,        '8993001001',            1        UNION ALL   -- Kopi Kapal Api
    SELECT 2,        '8994001001',            2        UNION ALL   -- Indomie goreng
    SELECT 3,        '8991001009',            1        UNION ALL   -- Telur
    SELECT 3,        '8993001005',            1                    -- Air mineral
) AS d
JOIN produk p ON p.kode_barcode = d.barcode
JOIN transaksi t ON t.id = d.trx;

-- Total nota dihitung ulang dari rincian supaya selalu cocok
UPDATE transaksi t
SET t.total_harga = (SELECT SUM(subtotal) FROM transaksi_detail WHERE transaksi_id = t.id);
