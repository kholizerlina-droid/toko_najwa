-- Data awal contoh untuk toko sembako.
-- File ini hanya menambah kategori dan produk; tidak menghapus atau mengubah akun users.
-- Kode TNJ-xxxx adalah kode internal toko, bukan barcode resmi pada kemasan.
-- Harga beli/jual dan stok awal adalah contoh; sesuaikan harga dengan nota kulakan dan stok fisik.
-- Pilih database toko_najwa di phpMyAdmin sebelum mengimpor file ini.

START TRANSACTION;

INSERT INTO kategori (nama_kategori, deskripsi)
SELECT 'Makanan Instan', 'Mi dan makanan praktis'
WHERE NOT EXISTS (SELECT 1 FROM kategori WHERE nama_kategori = 'Makanan Instan');

INSERT INTO kategori (nama_kategori, deskripsi)
SELECT 'Sembako', 'Beras, minyak, gula, telur, dan bahan pokok'
WHERE NOT EXISTS (SELECT 1 FROM kategori WHERE nama_kategori = 'Sembako');

INSERT INTO kategori (nama_kategori, deskripsi)
SELECT 'Bahan Dapur', 'Tepung dan bumbu dapur'
WHERE NOT EXISTS (SELECT 1 FROM kategori WHERE nama_kategori = 'Bahan Dapur');

INSERT INTO kategori (nama_kategori, deskripsi)
SELECT 'Minuman', 'Air mineral, teh, dan kopi'
WHERE NOT EXISTS (SELECT 1 FROM kategori WHERE nama_kategori = 'Minuman');

INSERT INTO produk
    (kode_barcode, kategori_id, nama_produk, satuan, harga_beli, harga_jual, stok, min_stok, tgl_kadaluarsa, gambar)
VALUES
    ('TNJ-0001', (SELECT id FROM kategori WHERE nama_kategori = 'Makanan Instan' ORDER BY id LIMIT 1), 'Indomie Mi Goreng 85 g', 'Bungkus', 2800, 3500, 0, 5, NULL, 'https://allmartshop.com/cdn/shop/files/IndomieInstantNoodlesMiGorengOriginal85g.jpg?v=1736089268&width=1445'),
    ('TNJ-0002', (SELECT id FROM kategori WHERE nama_kategori = 'Makanan Instan' ORDER BY id LIMIT 1), 'Mie Sedaap Goreng 90 g', 'Bungkus', 2700, 3500, 0, 5, NULL, 'https://down-id.img.susercontent.com/file/a0bff763bdeb6601ad9ff9fdf96db86b'),
    ('TNJ-0003', (SELECT id FROM kategori WHERE nama_kategori = 'Sembako' ORDER BY id LIMIT 1), 'Beras Sania Premium 5 kg', 'Karung', 74000, 79000, 0, 2, NULL, 'https://image.astronauts.cloud/product-images/2025/1/SaniaBerasPremium5KG_f8b8677f-8f97-4399-8a3c-2a7c03c08d6a_900x900.jpg'),
    ('TNJ-0004', (SELECT id FROM kategori WHERE nama_kategori = 'Sembako' ORDER BY id LIMIT 1), 'Minyak Goreng Bimoli 1 L', 'Botol', 20000, 22000, 0, 5, NULL, 'https://marilenminimart.com/cdn/shop/products/bimoli_bimoli-minyak-goreng--1-l-_full02.jpg?v=1627706843'),
    ('TNJ-0005', (SELECT id FROM kategori WHERE nama_kategori = 'Sembako' ORDER BY id LIMIT 1), 'Gula Pasir Gulaku 1 kg', 'Bungkus', 19000, 22000, 0, 5, NULL, 'https://www.static-src.com/wcsstore/Indraprastha/images/catalog/full/98/MTA-10790682/gulaku_gulaku_premium_full00.jpg'),
    ('TNJ-0006', (SELECT id FROM kategori WHERE nama_kategori = 'Sembako' ORDER BY id LIMIT 1), 'Telur Ayam Ras', 'Kg', 25000, 28000, 0, 5, NULL, 'https://down-id.img.susercontent.com/file/id-11134207-7rbkb-m6w5ygg2b8rub5'),
    ('TNJ-0007', (SELECT id FROM kategori WHERE nama_kategori = 'Bahan Dapur' ORDER BY id LIMIT 1), 'Tepung Terigu Segitiga Biru 1 kg', 'Bungkus', 12000, 14000, 0, 5, NULL, 'https://i.gojekapi.com/darkroom/gomart-public-production/v2/images/public/images/554774dd-a1ca-4d0c-a866-11dd7bbcaa7f_Segitiga-Biru-Tepung-Terigu-1-kg.jpg'),
    ('TNJ-0008', (SELECT id FROM kategori WHERE nama_kategori = 'Minuman' ORDER BY id LIMIT 1), 'Kopi Kapal Api Special Mix 10 x 23 g', 'Renceng', 17000, 20000, 0, 3, NULL, 'https://storage.googleapis.com/download/storage/v1/b/xooply-static-production/o/dev%2Fkamix%20png.png-9khLx1.png?alt=media&generation=1719137764964791'),
    ('TNJ-0009', (SELECT id FROM kategori WHERE nama_kategori = 'Minuman' ORDER BY id LIMIT 1), 'Teh Celup Sariwangi Isi 25', 'Kotak', 8500, 10500, 0, 3, NULL, 'https://down-id.img.susercontent.com/file/id-11134207-7qul3-lj4chmr4na0a52'),
    ('TNJ-0010', (SELECT id FROM kategori WHERE nama_kategori = 'Minuman' ORDER BY id LIMIT 1), 'Aqua Air Mineral 600 ml', 'Botol', 2500, 3500, 0, 5, NULL, 'https://c.alfagift.id/product/1/1_A12460003260_20260518134546715_base.png'),
    ('TNJ-0011', (SELECT id FROM kategori WHERE nama_kategori = 'Bahan Dapur' ORDER BY id LIMIT 1), 'Kecap Manis Bango Refill 220 ml', 'Pouch', 10500, 13500, 0, 3, NULL, 'https://www.static-src.com/wcsstore/Indraprastha/images/catalog/full/86/MTA-13172351/bango_bango_refill_kecap_manis_220_ml_full01_r50vm69y.jpg')
ON DUPLICATE KEY UPDATE
    kategori_id = VALUES(kategori_id),
    nama_produk = VALUES(nama_produk),
    satuan = VALUES(satuan),
    harga_beli = VALUES(harga_beli),
    harga_jual = VALUES(harga_jual),
    stok = VALUES(stok),
    min_stok = VALUES(min_stok),
    tgl_kadaluarsa = VALUES(tgl_kadaluarsa),
    gambar = VALUES(gambar);

COMMIT;
