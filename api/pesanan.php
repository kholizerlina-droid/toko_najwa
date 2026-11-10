<?php
// api/pesanan.php - Simpan pesanan katalog sebelum membuka WhatsApp.
require_once __DIR__ . '/../includes/init.php';

header('Content-Type: application/json; charset=utf-8');

function balas_json(int $status, array $isi): never
{
    http_response_code($status);
    echo json_encode($isi, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!is_post()) {
    balas_json(405, ['ok' => false, 'pesan' => 'Metode permintaan tidak valid.']);
}

$input = json_decode(file_get_contents('php://input'), true);
$nama = trim((string) ($input['nama'] ?? ''));
$layanan = trim((string) ($input['layanan'] ?? ''));
$catatan = trim((string) ($input['catatan'] ?? ''));
$metode = trim((string) ($input['metode_bayar'] ?? ''));
$items = $input['items'] ?? null;

if ($nama === '' || mb_strlen($nama) > 100 || $layanan !== 'Ambil di Toko'
    || !in_array($metode, ['Tunai (bayar saat ambil)', 'QRIS'], true)
    || !is_array($items) || !$items || count($items) > 100) {
    balas_json(422, ['ok' => false, 'pesan' => 'Data pesanan belum lengkap atau tidak valid.']);
}

try {
    $db = db();
    $baris = [];
    $total = 0;
    foreach ($items as $item) {
        $id = filter_var($item['id'] ?? null, FILTER_VALIDATE_INT);
        $qty = filter_var($item['qty'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || !$qty || $qty < 1 || $qty > 999) {
            balas_json(422, ['ok' => false, 'pesan' => 'Isi keranjang tidak valid.']);
        }
        $produk = ambil_satu('SELECT id, nama_produk, satuan, harga_jual, stok FROM produk WHERE id = ?', 'i', [$id]);
        if (!$produk || $produk['stok'] < $qty) {
            balas_json(409, ['ok' => false, 'pesan' => 'Stok salah satu barang tidak mencukupi. Muat ulang katalog lalu coba lagi.']);
        }
        $subtotal = (int) $produk['harga_jual'] * $qty;
        $total += $subtotal;
        $baris[] = [
            'id' => (int) $produk['id'], 'nama' => $produk['nama_produk'],
            'satuan' => $produk['satuan'], 'harga' => (int) $produk['harga_jual'],
            'qty' => $qty, 'subtotal' => $subtotal,
        ];
    }

    $kode = 'ORD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    jalankan(
        'INSERT INTO pesanan_online (kode_pesanan, nama_pemesan, layanan, catatan, metode_bayar, total, item_json) VALUES (?, ?, ?, ?, ?, ?, ?)',
        'sssssis', [$kode, $nama, $layanan, $catatan !== '' ? $catatan : null, $metode, $total, json_encode($baris, JSON_UNESCAPED_UNICODE)]
    );
    balas_json(201, ['ok' => true, 'kode' => $kode, 'total' => $total, 'items' => $baris]);
} catch (Throwable $e) {
    error_log('Gagal menyimpan pesanan katalog: ' . $e->getMessage());
    balas_json(500, ['ok' => false, 'pesan' => 'Pesanan belum tersimpan. Silakan coba lagi.']);
}
