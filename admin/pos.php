<?php
// admin/pos.php - Kasir (Point of Sale).
// Keranjang disusun di browser (assets/js/pos.js), lalu dikirim sebagai JSON saat "Bayar".
require_once __DIR__ . '/../includes/init_admin.php';

/* ---------- Proses pembayaran ---------- */
$nota_selesai = null;   // nomor faktur jika berhasil
$pesan_error  = '';

if (is_post() && post('aksi') === 'bayar') {
    $keranjang   = json_decode(post('keranjang', '[]'), true) ?: [];
    $pelanggan   = post('nama_pelanggan') ?: 'Warga / Umum';
    $metode      = post('metode_pembayaran', 'tunai');
    $uang_bayar  = (float) post('uang_bayar');
    $telepon     = post('telepon_pelanggan');
    $jatuh_tempo = post('jatuh_tempo') ?: date('Y-m-d', strtotime('+14 days'));
    $catatan     = post('catatan');

    $db = db();
    $db->begin_transaction();
    try {
        if (!in_array($metode, ['tunai', 'qris', 'utang'], true)) {
            throw new Exception('Metode pembayaran tidak dikenal.');
        }

        // 1. Cek tiap barang ke database (jangan percaya harga dari browser)
        $baris = [];
        $total = 0;
        foreach ($keranjang as $item) {
            $qty = (int) ($item['qty'] ?? 0);
            if ($qty <= 0) continue;

            $produk = ambil_satu(
                'SELECT id, nama_produk, harga_beli, harga_jual, stok FROM produk WHERE id = ? FOR UPDATE',
                'i', [(int) ($item['id'] ?? 0)]
            );
            if (!$produk) throw new Exception('Ada produk yang tidak ditemukan.');
            if ($produk['stok'] < $qty) {
                throw new Exception("Stok {$produk['nama_produk']} tidak cukup (sisa {$produk['stok']}).");
            }

            $subtotal = $produk['harga_jual'] * $qty;
            $total   += $subtotal;
            $baris[]  = $produk + ['qty' => $qty, 'subtotal' => $subtotal];
        }
        if (!$baris) throw new Exception('Keranjang masih kosong.');

        // 2. Hitung kembalian & status sesuai metode bayar
        $status    = 'lunas';
        $kembalian = 0;
        if ($metode === 'tunai') {
            if ($uang_bayar < $total) throw new Exception('Uang tunai kurang dari total belanja.');
            $kembalian = $uang_bayar - $total;
        } elseif ($metode === 'qris') {
            $uang_bayar = $total;
        } else { // utang / kasbon
            $status     = 'belum_lunas';
            $uang_bayar = 0;
            if ($pelanggan === 'Warga / Umum') throw new Exception('Untuk kasbon, isi nama warga dengan jelas.');
        }

        // 3. Buat nomor faktur: TRX-TanggalHariIni-0001, 0002, dst.
        $awalan     = 'TRX-' . date('Ymd') . '-';
        $terakhir   = ambil_nilai('SELECT MAX(no_faktur) FROM transaksi WHERE no_faktur LIKE ?', 's', [$awalan . '%']);
        $urutan     = $terakhir ? ((int) substr($terakhir, -4)) + 1 : 1;
        $no_faktur  = $awalan . str_pad($urutan, 4, '0', STR_PAD_LEFT);

        // 4. Simpan nota, rincian, dan kurangi stok
        jalankan(
            'INSERT INTO transaksi (no_faktur, user_id, nama_pelanggan, total_harga, total_bayar, kembalian, metode_pembayaran, status_pembayaran, catatan)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            'sisdddsss',
            [$no_faktur, user_login()['id'], $pelanggan, $total, $uang_bayar, $kembalian, $metode, $status, $catatan]
        );
        $transaksi_id = $db->insert_id;

        foreach ($baris as $b) {
            $laba = ($b['harga_jual'] - $b['harga_beli']) * $b['qty'];
            jalankan(
                'INSERT INTO transaksi_detail (transaksi_id, produk_id, harga_beli, harga_jual, qty, subtotal, keuntungan)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                'iiddidd',
                [$transaksi_id, $b['id'], $b['harga_beli'], $b['harga_jual'], $b['qty'], $b['subtotal'], $laba]
            );
            jalankan('UPDATE produk SET stok = stok - ? WHERE id = ?', 'ii', [$b['qty'], $b['id']]);
        }

        // 5. Jika kasbon, catat juga di buku kasbon
        if ($metode === 'utang') {
            jalankan(
                "INSERT INTO piutang (transaksi_id, nama_pelanggan, no_telepon, total_utang, sisa_utang, tgl_utang, tgl_jatuh_tempo, status, catatan)
                 VALUES (?, ?, ?, ?, ?, CURDATE(), ?, 'belum_lunas', ?)",
                'issddss',
                [$transaksi_id, $pelanggan, $telepon, $total, $total, $jatuh_tempo, $catatan ?: "Nota $no_faktur"]
            );
        }

        $db->commit();
        $nota_selesai = $no_faktur;
    } catch (Exception $e) {
        $db->rollback();
        $pesan_error = $e->getMessage();
    }
}

/* ---------- Ambil data untuk tampilan ---------- */
$daftar_produk = ambil_semua(
    'SELECT p.id, p.kode_barcode, p.kategori_id, p.nama_produk, p.satuan, p.harga_jual, p.stok, p.gambar
     FROM produk p JOIN kategori k ON k.id = p.kategori_id
     WHERE p.stok > 0 ORDER BY p.nama_produk'
);
foreach ($daftar_produk as &$p) {
    $p['foto']       = gambar_produk($p['gambar']);
    unset($p['gambar']);
    $p['harga_jual'] = (int) $p['harga_jual'];
}
unset($p);
$daftar_kategori = ambil_semua('SELECT id, nama_kategori FROM kategori ORDER BY id');

/* ---------- Tampilan ---------- */
$judul_halaman  = 'Kasir (POS)';
$menu_aktif     = 'pos';
$skrip_tambahan = ['pos.js'];
include __DIR__ . '/../includes/layout/admin_top.php';
?>

<?php if ($pesan_error): ?>
    <div class="alert alert-danger"><?= e($pesan_error) ?></div>
<?php endif; ?>

<?php if ($nota_selesai): ?>
    <div class="alert alert-success d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><i class="bi bi-check-circle me-1"></i> Transaksi <strong class="font-monospace"><?= e($nota_selesai) ?></strong> berhasil disimpan.</span>
        <a href="cetak_struk.php?faktur=<?= urlencode($nota_selesai) ?>" target="_blank" class="btn btn-success btn-sm">
            <i class="bi bi-printer me-1"></i> Cetak Struk
        </a>
    </div>
<?php endif; ?>

<h1 class="h3 mb-3">Kasir</h1>

<div class="row g-4">
    <!-- Kiri: pilih produk -->
    <div class="col-lg-8">
        <div class="row g-2 mb-3">
            <div class="col-md-7">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" id="cariProduk" class="form-control" placeholder="Cari nama sembako...">
                </div>
            </div>
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
                    <input type="text" id="scanBarcode" class="form-control" placeholder="Scan barcode lalu Enter">
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mb-3" id="filterKategori">
            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill active" data-kategori="semua">Semua</button>
            <?php foreach ($daftar_kategori as $k): ?>
                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" data-kategori="<?= $k['id'] ?>"><?= e($k['nama_kategori']) ?></button>
            <?php endforeach; ?>
        </div>

        <div class="row g-3 area-gulir" id="daftarProduk"></div>
    </div>

    <!-- Kanan: keranjang & pembayaran -->
    <div class="col-lg-4">
        <form method="post" id="formBayar" class="card sticky-lg-top" style="top: 1rem;">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="bayar">
            <input type="hidden" name="keranjang" id="inputKeranjang">

            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-basket me-1"></i> Keranjang</span>
                <button type="button" class="btn btn-sm btn-outline-danger" id="tombolKosongkan">Kosongkan</button>
            </div>

            <ul class="list-group list-group-flush area-gulir" style="max-height: 260px;" id="daftarKeranjang"></ul>

            <div class="card-body border-top">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="fw-semibold">Total</span>
                    <span class="h4 mb-0 text-primary" id="teksTotal">Rp 0</span>
                </div>

                <input type="text" name="nama_pelanggan" id="inputPelanggan" class="form-control mb-3"
                       value="Warga / Umum" placeholder="Nama pelanggan" required>

                <div class="btn-group w-100 mb-3" role="group" aria-label="Metode pembayaran">
                    <input type="radio" class="btn-check" name="metode_pembayaran" id="bayarTunai" value="tunai" checked>
                    <label class="btn btn-outline-secondary" for="bayarTunai">Tunai</label>
                    <input type="radio" class="btn-check" name="metode_pembayaran" id="bayarQris" value="qris">
                    <label class="btn btn-outline-secondary" for="bayarQris">QRIS</label>
                    <input type="radio" class="btn-check" name="metode_pembayaran" id="bayarUtang" value="utang">
                    <label class="btn btn-outline-secondary" for="bayarUtang">Kasbon</label>
                </div>

                <!-- Panel tunai -->
                <div id="panelTunai">
                    <div class="btn-group w-100 mb-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="tombolUangPas">Uang Pas</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-uang="20000">20rb</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-uang="50000">50rb</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-uang="100000">100rb</button>
                    </div>
                    <div class="input-group mb-2">
                        <span class="input-group-text">Rp</span>
                        <input type="number" name="uang_bayar" id="inputUang" class="form-control" min="0" placeholder="Uang diterima">
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-body-secondary">Kembalian</span>
                        <strong id="teksKembalian">Rp 0</strong>
                    </div>
                </div>

                <!-- Panel QRIS -->
                <div id="panelQris" class="text-center d-none">
                    <img src="<?= gambar_qris() ?>" alt="QRIS Toko Najwa" class="img-fluid" style="max-height: 180px;">
                    <div class="small text-body-secondary">Pelanggan scan QRIS, lalu klik Bayar.</div>
                </div>

                <!-- Panel kasbon -->
                <div id="panelUtang" class="d-none">
                    <input type="text" name="telepon_pelanggan" class="form-control mb-2" placeholder="Nomor WhatsApp (opsional)">
                    <label class="form-label small mb-1" for="jatuhTempo">Jatuh tempo</label>
                    <input type="date" name="jatuh_tempo" id="jatuhTempo" class="form-control" value="<?= date('Y-m-d', strtotime('+14 days')) ?>">
                </div>
            </div>

            <div class="card-footer">
                <button type="submit" class="btn btn-primary w-100" id="tombolBayar" disabled>
                    <i class="bi bi-check2-circle me-1"></i> Bayar &amp; Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Data produk untuk JavaScript kasir -->
<script type="application/json" id="dataProduk"><?= json_encode($daftar_produk, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<?php include __DIR__ . '/../includes/layout/admin_bottom.php'; ?>
