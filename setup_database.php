<?php
// setup_database.php - Inisialisasi Tabel dan Data Awal Toko Najwa
require_once __DIR__ . '/koneksi.php';

$queries = [
    // 1. Users
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        nama_lengkap VARCHAR(100) NOT NULL,
        role ENUM('admin', 'kasir') NOT NULL DEFAULT 'admin',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // 2. Kategori Produk
    "CREATE TABLE IF NOT EXISTS kategori (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama_kategori VARCHAR(100) NOT NULL,
        deskripsi TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // 3. Produk
    "CREATE TABLE IF NOT EXISTS produk (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kode_barcode VARCHAR(50) NOT NULL UNIQUE,
        kategori_id INT NOT NULL,
        nama_produk VARCHAR(150) NOT NULL,
        satuan VARCHAR(30) NOT NULL DEFAULT 'Pcs',
        harga_beli DECIMAL(12,2) NOT NULL DEFAULT 0,
        harga_jual DECIMAL(12,2) NOT NULL DEFAULT 0,
        stok INT NOT NULL DEFAULT 0,
        min_stok INT NOT NULL DEFAULT 5,
        tgl_kadaluarsa DATE NULL,
        gambar VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (kategori_id) REFERENCES kategori(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // 4. Transaksi Penjualan
    "CREATE TABLE IF NOT EXISTS transaksi (
        id INT AUTO_INCREMENT PRIMARY KEY,
        no_faktur VARCHAR(50) NOT NULL UNIQUE,
        tanggal DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        user_id INT NULL,
        nama_pelanggan VARCHAR(100) NOT NULL DEFAULT 'Warga / Umum',
        total_harga DECIMAL(12,2) NOT NULL DEFAULT 0,
        total_bayar DECIMAL(12,2) NOT NULL DEFAULT 0,
        kembalian DECIMAL(12,2) NOT NULL DEFAULT 0,
        metode_pembayaran ENUM('tunai', 'qris', 'utang') NOT NULL DEFAULT 'tunai',
        status_pembayaran ENUM('lunas', 'belum_lunas') NOT NULL DEFAULT 'lunas',
        catatan TEXT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // 5. Transaksi Detail
    "CREATE TABLE IF NOT EXISTS transaksi_detail (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaksi_id INT NOT NULL,
        produk_id INT NOT NULL,
        harga_beli DECIMAL(12,2) NOT NULL DEFAULT 0,
        harga_jual DECIMAL(12,2) NOT NULL DEFAULT 0,
        qty INT NOT NULL DEFAULT 1,
        subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
        keuntungan DECIMAL(12,2) NOT NULL DEFAULT 0,
        FOREIGN KEY (transaksi_id) REFERENCES transaksi(id) ON DELETE CASCADE,
        FOREIGN KEY (produk_id) REFERENCES produk(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // 6. Piutang / Catatan Utang Kasbon Pelanggan
    "CREATE TABLE IF NOT EXISTS piutang (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaksi_id INT NULL,
        nama_pelanggan VARCHAR(100) NOT NULL,
        no_telepon VARCHAR(30) NULL,
        total_utang DECIMAL(12,2) NOT NULL DEFAULT 0,
        sisa_utang DECIMAL(12,2) NOT NULL DEFAULT 0,
        tgl_utang DATE NOT NULL,
        tgl_jatuh_tempo DATE NULL,
        status ENUM('belum_lunas', 'lunas') NOT NULL DEFAULT 'belum_lunas',
        catatan TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (transaksi_id) REFERENCES transaksi(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // 7. Riwayat Pembayaran Piutang
    "CREATE TABLE IF NOT EXISTS piutang_pembayaran (
        id INT AUTO_INCREMENT PRIMARY KEY,
        piutang_id INT NOT NULL,
        tgl_bayar DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        jumlah_bayar DECIMAL(12,2) NOT NULL,
        metode VARCHAR(50) NOT NULL DEFAULT 'tunai',
        catatan VARCHAR(255) NULL,
        FOREIGN KEY (piutang_id) REFERENCES piutang(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // 8. Restock / Kulakan Barang Masuk
    "CREATE TABLE IF NOT EXISTS restock (
        id INT AUTO_INCREMENT PRIMARY KEY,
        produk_id INT NOT NULL,
        tanggal DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        qty_masuk INT NOT NULL,
        harga_beli_satuan DECIMAL(12,2) NOT NULL DEFAULT 0,
        supplier VARCHAR(150) NULL,
        tgl_kadaluarsa_baru DATE NULL,
        catatan TEXT NULL,
        FOREIGN KEY (produk_id) REFERENCES produk(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // 9. Pengeluaran / Arus Kas Keluar
    "CREATE TABLE IF NOT EXISTS pengeluaran (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tanggal DATE NOT NULL,
        kategori_pengeluaran VARCHAR(100) NOT NULL,
        jumlah DECIMAL(12,2) NOT NULL,
        keterangan TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // 10. Pengaturan Toko
    "CREATE TABLE IF NOT EXISTS pengaturan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama_toko VARCHAR(100) NOT NULL DEFAULT 'Warung Sembako Toko Najwa',
        pemilik VARCHAR(100) NOT NULL DEFAULT 'Ibu Dhiyah',
        telepon VARCHAR(30) NOT NULL DEFAULT '081234567890',
        alamat TEXT NOT NULL,
        teks_footer_struk TEXT NULL,
        qris_image VARCHAR(255) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

foreach ($queries as $sql) {
    if (!$koneksi->query($sql)) {
        die("Error creating table: " . $koneksi->error . " in SQL: " . $sql);
    }
}

echo "Semua tabel berhasil dibuat!\n";

// Inisialisasi Data Default jika kosong
// 1. User admin default (admin / admin123)
$res = $koneksi->query("SELECT COUNT(*) FROM users");
if ($res->fetch_row()[0] == 0) {
    $hashed = password_hash('admin123', PASSWORD_BCRYPT);
    $koneksi->query("INSERT INTO users (username, password, nama_lengkap, role) VALUES 
        ('admin', '$hashed', 'Ibu Dhiyah (Pemilik)', 'admin'),
        ('kasir', '$hashed', 'Kasir Toko Najwa', 'kasir')");
    echo "Default users created.\n";
}

// 2. Pengaturan Default
$res = $koneksi->query("SELECT COUNT(*) FROM pengaturan");
if ($res->fetch_row()[0] == 0) {
    $alamat = "Jaten, Bonomerto Rt.02 Rw.04 Kec. Suruh, Kab. Semarang";
    $pesan = "Terima kasih telah berbelanja di Toko Najwa!\nBarang yang sudah dibeli tidak dapat ditukar kecuali perjanjian.";
    $stmt = $koneksi->prepare("INSERT INTO pengaturan (nama_toko, pemilik, telepon, alamat, teks_footer_struk) VALUES (?, ?, ?, ?, ?)");
    $toko = "Warung Sembako Toko Najwa";
    $pemilik = "Ibu Dhiyah";
    $telp = "085712345678";
    $stmt->bind_param("sssss", $toko, $pemilik, $telp, $alamat, $pesan);
    $stmt->execute();
    echo "Default settings created.\n";
}

// 3. Kategori Default
$res = $koneksi->query("SELECT COUNT(*) FROM kategori");
if ($res->fetch_row()[0] == 0) {
    $kategoris = [
        ['Sembako Utama', 'Beras, Minyak Goreng, Gula Pasir, Tepung Terigu/Beras, Telur'],
        ['Bumbu & Dapur', 'Garam, Bumbu Masak, Penyedap Rasa, Bawang, Kecap, Saus'],
        ['Minuman & Seduhan', 'Kopi Sachet, Teh Celup/Tubruk, Susu Kental Manis, Air Mineral'],
        ['Makanan Ringan & Mie', 'Mie Instan, Biskuit, Kerupuk, Camilan Harian'],
        ['Kebutuhan Rumah Tangga', 'Sabun Cuci Piring, Deterjen, Sabun Mandi, Shampo, Pasta Gigi'],
        ['Rokok & Lainnya', 'Rokok Ecer/Bungkus, Korek Api, Obat-obatan Warung']
    ];
    $stmt = $koneksi->prepare("INSERT INTO kategori (nama_kategori, deskripsi) VALUES (?, ?)");
    foreach ($kategoris as $k) {
        $stmt->bind_param("ss", $k[0], $k[1]);
        $stmt->execute();
    }
    echo "Default categories created.\n";
}

// 4. Sample Produk berdasarkan Laporan Observasi Bu Dhiyah
$res = $koneksi->query("SELECT COUNT(*) FROM produk");
if ($res->fetch_row()[0] == 0) {
    $produk_samples = [
        // [kode, kat_id, nama, satuan, h_beli, h_jual, stok, min_stok, kadaluarsa]
        ['8991001001', 1, 'Minyak Goreng Sedap 1 Liter', 'Pouch', 16000, 18500, 24, 5, '2027-05-20'],
        ['8991001002', 1, 'Minyak Goreng Filma 2 Liter', 'Pouch', 33500, 37000, 15, 4, '2027-06-15'],
        ['8991001003', 1, 'Minyak Goreng Fortune 1 Liter', 'Pouch', 15800, 18000, 20, 5, '2027-04-10'],
        ['8991001004', 1, 'Minyak Goreng Minyakita 1 Liter', 'Botol', 14500, 16000, 36, 10, '2027-08-01'],
        ['8991001005', 1, 'Beras C4 Super 5 Kg', 'Karung', 64000, 72000, 18, 5, '2027-10-01'],
        ['8991001006', 1, 'Beras Raja Lele 1 Kg (Eceran)', 'Kg', 13000, 14500, 45, 10, '2027-11-01'],
        ['8991001007', 1, 'Gula Pasir Gulaku Kuning 1 Kg', 'Kg', 15500, 17500, 28, 5, '2027-09-12'],
        ['8991001008', 1, 'Gula Pasir Curah 1 Kg', 'Kg', 14500, 16500, 30, 8, '2027-03-30'],
        ['8991001009', 1, 'Telur Ayam Ras Segar 1 Kg', 'Kg', 25000, 28000, 25, 5, '2026-11-15'], // Kadaluarsa dekat (alert!)
        ['8991001010', 1, 'Tepung Terigu Segitiga Biru 1 Kg', 'Kg', 11000, 13000, 19, 5, '2027-03-15'],
        ['8991001011', 1, 'Tepung Beras Rose Brand 500gr', 'Bungkus', 7500, 9000, 16, 4, '2027-02-28'],
        
        // Bumbu & Dapur
        ['8992001001', 2, 'Royco Bumbu Kaldu Ayam 8gr (1 Renceng)', 'Renceng', 4500, 6000, 14, 3, '2027-07-20'],
        ['8992001002', 2, 'Masako Rasa Sapi 8gr (1 Renceng)', 'Renceng', 4500, 6000, 12, 3, '2027-08-10'],
        ['8992001003', 2, 'Ladaku Merica Bubuk Sachet', 'Renceng', 9000, 11000, 10, 2, '2027-09-05'],
        ['8992001004', 2, 'Kecap Bango Manis Refill 550ml', 'Pouch', 21000, 24000, 8, 2, '2027-04-18'],
        ['8992001005', 2, 'Garam Beryodium Cap Daun 250gr', 'Bungkus', 2500, 3500, 22, 5, '2028-01-01'],
        ['8992001006', 2, 'Saus Sambal ABC Botol 275ml', 'Botol', 13000, 15500, 6, 2, '2026-11-10'], // Kadaluarsa sangat dekat

        // Minuman & Seduhan
        ['8993001001', 3, 'Kopi Kapal Api Special Mix Sachet (1 Renceng)', 'Renceng', 13500, 16000, 25, 4, '2027-10-15'],
        ['8993001002', 3, 'Torabika Cappuccino (1 Renceng)', 'Renceng', 18000, 21000, 15, 3, '2027-08-25'],
        ['8993001003', 3, 'Teh Tong Tji Melati Celup 25 Bag', 'Kotak', 9000, 11500, 14, 3, '2027-12-01'],
        ['8993001004', 3, 'Susu Frisian Flag Cokelat Kaleng 370gr', 'Kaleng', 11500, 13500, 18, 4, '2027-07-14'],
        ['8993001005', 3, 'Air Mineral Le Minerale 600ml', 'Botol', 3000, 4000, 48, 12, '2028-02-10'],
        ['8993001006', 3, 'Air Mineral Galon Aqua (Isi Ulang Resmi)', 'Galon', 19000, 22000, 3, 5, '2027-01-01'], // Stok menipis (alert!)

        // Makanan Ringan & Mie
        ['8994001001', 4, 'Indomie Goreng Spesial 85gr', 'Bungkus', 2800, 3500, 80, 20, '2027-06-01'],
        ['8994001002', 4, 'Indomie Kuah Ayam Bawang 69gr', 'Bungkus', 2700, 3300, 60, 15, '2027-05-15'],
        ['8994001003', 4, 'Mie Sedaap Goreng 90gr', 'Bungkus', 2800, 3500, 55, 15, '2027-06-20'],
        ['8994001004', 4, 'Biskuit Roma Kelapa 300gr', 'Bungkus', 9500, 11500, 2, 5, '2027-04-05'], // Stok menipis

        // Kebutuhan Rumah Tangga
        ['8995001001', 5, 'Sunlight Jeruk Nipis 650ml Refill', 'Pouch', 12500, 15000, 12, 3, '2027-11-20'],
        ['8995001002', 5, 'Deterjen Daia Putih 800gr', 'Bungkus', 16000, 18500, 9, 3, '2027-10-30'],
        ['8995001003', 5, 'Sabun Mandi Lifebuoy Merah 85gr', 'Pcs', 3500, 4500, 30, 6, '2028-03-01'],
        ['8995001004', 5, 'Pasta Gigi Pepsodent 120gr', 'Pcs', 8500, 10500, 14, 4, '2028-01-15'],

        // Rokok & Lainnya
        ['8996001001', 6, 'Rokok Sampoerna Mild 16', 'Bungkus', 31500, 34000, 20, 5, '2027-12-31'],
        ['8996001002', 6, 'Rokok Gudang Garam Surya 12', 'Bungkus', 22500, 25000, 25, 5, '2027-12-31'],
        ['8996001003', 6, 'Rokok Djarum Super 12', 'Bungkus', 22000, 24500, 18, 5, '2027-12-31']
    ];

    $stmt = $koneksi->prepare("INSERT INTO produk (kode_barcode, kategori_id, nama_produk, satuan, harga_beli, harga_jual, stok, min_stok, tgl_kadaluarsa) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($produk_samples as $p) {
        $stmt->bind_param("sisdddiis", $p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6], $p[7], $p[8]);
        $stmt->execute();
    }
    echo "Sample products created.\n";
}

// 5. Sample Piutang (Catatan Hutang Warga seperti di Lampiran 4)
$res = $koneksi->query("SELECT COUNT(*) FROM piutang");
if ($res->fetch_row()[0] == 0) {
    $piutangs = [
        ['Pak Budi (RT 02)', '081233445566', 85000, 85000, '2026-10-25', '2026-11-05', 'belum_lunas', 'Beras 5kg + Minyak 1L'],
        ['Bu Sari (Jaten)', '085711223344', 45000, 20000, '2026-10-20', '2026-10-30', 'belum_lunas', 'Sembako telur & bumbu dapur (sudah cicil 25rb)'],
        ['Mbah Joko', '089877665544', 32000, 0, '2026-10-15', '2026-10-25', 'lunas', 'Kopi & Rokok Surya (Lunas)']
    ];
    $stmt = $koneksi->prepare("INSERT INTO piutang (nama_pelanggan, no_telepon, total_utang, sisa_utang, tgl_utang, tgl_jatuh_tempo, status, catatan) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($piutangs as $pi) {
        $stmt->bind_param("ssddssss", $pi[0], $pi[1], $pi[2], $pi[3], $pi[4], $pi[5], $pi[6], $pi[7]);
        $stmt->execute();
    }
    echo "Sample piutang created.\n";
}

// 6. Sample Pengeluaran Operasional
$res = $koneksi->query("SELECT COUNT(*) FROM pengeluaran");
if ($res->fetch_row()[0] == 0) {
    $pengeluaran_samples = [
        ['2026-10-26', 'Operasional Kulakan', 25000, 'Bensin motor kulakan ke Pasar Suruh'],
        ['2026-10-28', 'Perlengkapan Warung', 18000, 'Beli kantong plastik kresek aneka ukuran'],
        ['2026-10-31', 'Listrik & Penerangan', 60000, 'Beli token listrik warung bulan Oktober']
    ];
    $stmt = $koneksi->prepare("INSERT INTO pengeluaran (tanggal, kategori_pengeluaran, jumlah, keterangan) VALUES (?, ?, ?, ?)");
    foreach ($pengeluaran_samples as $pg) {
        $stmt->bind_param("ssds", $pg[0], $pg[1], $pg[2], $pg[3]);
        $stmt->execute();
    }
    echo "Sample pengeluaran created.\n";
}

// 7. Sample Transaksi agar Dashboard & Laporan langsung berpenghuni data representatif
$res = $koneksi->query("SELECT COUNT(*) FROM transaksi");
if ($res->fetch_row()[0] == 0) {
    // Faktur 1
    $koneksi->query("INSERT INTO transaksi (no_faktur, tanggal, user_id, nama_pelanggan, total_harga, total_bayar, kembalian, metode_pembayaran, status_pembayaran) 
        VALUES ('TRX-20261030-001', '2026-10-30 08:30:00', 1, 'Ibu Sri Rahayu', 55500, 60000, 4500, 'tunai', 'lunas')");
    $trx1_id = $koneksi->insert_id;
    // Detail: Minyak Sedap 1L (18500), Beras C4 (72000 - eceran/bagian), Gula 1kg (17500), Indomie (3500)
    $koneksi->query("INSERT INTO transaksi_detail (transaksi_id, produk_id, harga_beli, harga_jual, qty, subtotal, keuntungan) VALUES 
        ($trx1_id, 1, 16000, 18500, 1, 18500, 2500),
        ($trx1_id, 7, 15500, 17500, 1, 17500, 2000),
        ($trx1_id, 24, 2800, 3500, 3, 10500, 2100),
        ($trx1_id, 12, 4500, 6000, 1, 6000, 1500)");

    // Faktur 2 (QRIS)
    $koneksi->query("INSERT INTO transaksi (no_faktur, tanggal, user_id, nama_pelanggan, total_harga, total_bayar, kembalian, metode_pembayaran, status_pembayaran) 
        VALUES ('TRX-20261031-002', '2026-10-31 10:15:00', 1, 'Mas Doni', 48500, 48500, 0, 'qris', 'lunas')");
    $trx2_id = $koneksi->insert_id;
    $koneksi->query("INSERT INTO transaksi_detail (transaksi_id, produk_id, harga_beli, harga_jual, qty, subtotal, keuntungan) VALUES 
        ($trx2_id, 32, 22500, 25000, 1, 25000, 2500),
        ($trx2_id, 18, 13500, 16000, 1, 16000, 2500),
        ($trx2_id, 24, 2800, 3500, 2, 7000, 1400)");

    // Faktur 3 (Hari ini)
    $koneksi->query("INSERT INTO transaksi (no_faktur, tanggal, user_id, nama_pelanggan, total_harga, total_bayar, kembalian, metode_pembayaran, status_pembayaran) 
        VALUES ('TRX-20261101-003', NOW(), 1, 'Tetangga Sebelah', 32000, 50000, 18000, 'tunai', 'lunas')");
    $trx3_id = $koneksi->insert_id;
    $koneksi->query("INSERT INTO transaksi_detail (transaksi_id, produk_id, harga_beli, harga_jual, qty, subtotal, keuntungan) VALUES 
        ($trx3_id, 9, 25000, 28000, 1, 28000, 3000),
        ($trx3_id, 22, 3000, 4000, 1, 4000, 1000)");

    echo "Sample transactions created.\n";
}

echo "Database initialization completed successfully!\n";
