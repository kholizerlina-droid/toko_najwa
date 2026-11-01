-- ========================================================
-- DATABASE STRUCTURE & INITIAL DATA: db_tokonajwa
-- Proyek Pengembangan UMKM Warung Sembako Toko Najwa
-- SMK Negeri Tengaran - Jurusan PPLG / RPL
-- ========================================================

CREATE DATABASE IF NOT EXISTS `db_tokonajwa` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `db_tokonajwa`;

-- --------------------------------------------------------
-- 1. Tabel users
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `role` enum('admin','kasir') NOT NULL DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 2. Tabel kategori
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `kategori` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_kategori` varchar(100) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 3. Tabel produk
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `produk` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kode_barcode` varchar(50) NOT NULL UNIQUE,
  `kategori_id` int(11) NOT NULL,
  `nama_produk` varchar(150) NOT NULL,
  `satuan` varchar(30) NOT NULL DEFAULT 'Pcs',
  `harga_beli` decimal(12,2) NOT NULL DEFAULT 0.00,
  `harga_jual` decimal(12,2) NOT NULL DEFAULT 0.00,
  `stok` int(11) NOT NULL DEFAULT 0,
  `min_stok` int(11) NOT NULL DEFAULT 5,
  `tgl_kadaluarsa` date DEFAULT NULL,
  `gambar` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `kategori_id` (`kategori_id`),
  CONSTRAINT `fk_produk_kategori` FOREIGN KEY (`kategori_id`) REFERENCES `kategori` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 4. Tabel transaksi
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `transaksi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `no_faktur` varchar(50) NOT NULL UNIQUE,
  `tanggal` datetime NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  `nama_pelanggan` varchar(100) NOT NULL DEFAULT 'Warga / Umum',
  `total_harga` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_bayar` decimal(12,2) NOT NULL DEFAULT 0.00,
  `kembalian` decimal(12,2) NOT NULL DEFAULT 0.00,
  `metode_pembayaran` enum('tunai','qris','utang') NOT NULL DEFAULT 'tunai',
  `status_pembayaran` enum('lunas','belum_lunas') NOT NULL DEFAULT 'lunas',
  `catatan` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_transaksi_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 5. Tabel transaksi_detail
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `transaksi_detail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaksi_id` int(11) NOT NULL,
  `produk_id` int(11) NOT NULL,
  `harga_beli` decimal(12,2) NOT NULL DEFAULT 0.00,
  `harga_jual` decimal(12,2) NOT NULL DEFAULT 0.00,
  `qty` int(11) NOT NULL DEFAULT 1,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `keuntungan` decimal(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `transaksi_id` (`transaksi_id`),
  KEY `produk_id` (`produk_id`),
  CONSTRAINT `fk_detail_transaksi` FOREIGN KEY (`transaksi_id`) REFERENCES `transaksi` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_detail_produk` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 6. Tabel piutang (Kasbon Warga)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `piutang` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaksi_id` int(11) DEFAULT NULL,
  `nama_pelanggan` varchar(100) NOT NULL,
  `no_telepon` varchar(30) DEFAULT NULL,
  `total_utang` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sisa_utang` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tgl_utang` date NOT NULL,
  `tgl_jatuh_tempo` date DEFAULT NULL,
  `status` enum('belum_lunas','lunas') NOT NULL DEFAULT 'belum_lunas',
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `transaksi_id` (`transaksi_id`),
  CONSTRAINT `fk_piutang_transaksi` FOREIGN KEY (`transaksi_id`) REFERENCES `transaksi` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 7. Tabel piutang_pembayaran (Cicilan)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `piutang_pembayaran` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `piutang_id` int(11) NOT NULL,
  `tgl_bayar` datetime NOT NULL DEFAULT current_timestamp(),
  `jumlah_bayar` decimal(12,2) NOT NULL,
  `metode` varchar(50) NOT NULL DEFAULT 'tunai',
  `catatan` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `piutang_id` (`piutang_id`),
  CONSTRAINT `fk_pembayaran_piutang` FOREIGN KEY (`piutang_id`) REFERENCES `piutang` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 8. Tabel restock (Kulakan Masuk)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `restock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `produk_id` int(11) NOT NULL,
  `tanggal` datetime NOT NULL DEFAULT current_timestamp(),
  `qty_masuk` int(11) NOT NULL,
  `harga_beli_satuan` decimal(12,2) NOT NULL DEFAULT 0.00,
  `supplier` varchar(150) DEFAULT NULL,
  `tgl_kadaluarsa_baru` date DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `produk_id` (`produk_id`),
  CONSTRAINT `fk_restock_produk` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 9. Tabel pengeluaran (Biaya Operasional)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pengeluaran` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `kategori_pengeluaran` varchar(100) NOT NULL,
  `jumlah` decimal(12,2) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 10. Tabel pengaturan
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pengaturan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_toko` varchar(100) NOT NULL DEFAULT 'Warung Sembako Toko Najwa',
  `pemilik` varchar(100) NOT NULL DEFAULT 'Ibu Dhiyah',
  `telepon` varchar(30) NOT NULL DEFAULT '085712345678',
  `alamat` text NOT NULL,
  `teks_footer_struk` text DEFAULT NULL,
  `qris_image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
