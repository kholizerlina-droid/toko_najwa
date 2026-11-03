<?php
// admin/restock.php - Catat kulakan (barang masuk). Stok produk otomatis bertambah.
require_once __DIR__ . '/../includes/init_admin.php';

/* ---------- Proses form ---------- */
if (is_post()) {
    $produk_id  = (int) post('produk_id');
    $qty        = (int) post('qty_masuk');
    $harga_beli = (float) post('harga_beli_satuan');
    $supplier   = post('supplier') ?: 'Pasar Grosir Suruh';
    $kadaluarsa = tanggal_atau_null(post('tgl_kadaluarsa_baru'));
    $catatan    = post('catatan');

    if ($produk_id <= 0 || $qty <= 0 || $harga_beli <= 0) {
        set_flash('danger', 'Produk, jumlah masuk, dan harga beli wajib diisi dengan benar.');
    } else {
        $db = db();
        $db->begin_transaction();
        try {
            jalankan(
                'INSERT INTO restock (produk_id, qty_masuk, harga_beli_satuan, supplier, tgl_kadaluarsa_baru, catatan)
                 VALUES (?, ?, ?, ?, ?, ?)',
                'iidsss', [$produk_id, $qty, $harga_beli, $supplier, $kadaluarsa, $catatan]
            );

            // Stok bertambah, harga beli ikut diperbarui; tanggal kadaluarsa hanya diganti jika diisi
            jalankan(
                'UPDATE produk SET stok = stok + ?, harga_beli = ?, tgl_kadaluarsa = COALESCE(?, tgl_kadaluarsa) WHERE id = ?',
                'idsi', [$qty, $harga_beli, $kadaluarsa, $produk_id]
            );

            if (isset($_POST['catat_pengeluaran'])) {
                jalankan(
                    "INSERT INTO pengeluaran (tanggal, kategori_pengeluaran, jumlah, keterangan)
                     VALUES (CURDATE(), 'Operasional Kulakan', ?, ?)",
                    'ds', [$qty * $harga_beli, "Kulakan dari $supplier ($qty item)"]
                );
            }

            $db->commit();
            set_flash('success', 'Kulakan tersimpan dan stok produk sudah bertambah.');
        } catch (Exception $e) {
            $db->rollback();
            set_flash('danger', 'Gagal menyimpan kulakan: ' . $e->getMessage());
        }
    }
    redirect('restock.php');
}

/* ---------- Ambil data ---------- */
$produk_terpilih = (int) get('produk_id');
$daftar_produk   = ambil_semua('SELECT id, nama_produk, satuan, stok, harga_beli, tgl_kadaluarsa FROM produk ORDER BY nama_produk');
$riwayat         = ambil_semua(
    'SELECT r.*, p.nama_produk, p.satuan FROM restock r JOIN produk p ON p.id = r.produk_id
     ORDER BY r.tanggal DESC LIMIT 25'
);
$total_bulan_ini = ambil_nilai(
    'SELECT COALESCE(SUM(qty_masuk * harga_beli_satuan), 0) FROM restock
     WHERE YEAR(tanggal) = YEAR(CURDATE()) AND MONTH(tanggal) = MONTH(CURDATE())'
);

/* ---------- Tampilan ---------- */
$judul_halaman  = 'Kulakan / Restock';
$menu_aktif     = 'restock';
$skrip_tambahan = ['restock.js'];
include __DIR__ . '/../includes/layout/admin_top.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 mb-0">Kulakan / Restock</h1>
        <p class="text-body-secondary mb-0">Catat barang masuk dari grosir; stok bertambah otomatis.</p>
    </div>
    <div class="text-end">
        <div class="text-body-secondary small">Modal kulakan bulan ini</div>
        <div class="h5 mb-0"><?= rupiah($total_bulan_ini) ?></div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <form method="post" class="card">
            <?= csrf_field() ?>
            <div class="card-header fw-semibold"><i class="bi bi-cart-plus me-1"></i> Form Kulakan</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="produkId">Produk</label>
                    <select class="form-select" name="produk_id" id="produkId" required>
                        <option value="">Pilih produk</option>
                        <?php foreach ($daftar_produk as $p): ?>
                            <option value="<?= $p['id'] ?>"
                                    data-harga="<?= (int) $p['harga_beli'] ?>"
                                    data-satuan="<?= e($p['satuan']) ?>"
                                    <?= $produk_terpilih === (int) $p['id'] ? 'selected' : '' ?>>
                                <?= e($p['nama_produk']) ?> (stok <?= $p['stok'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label" for="qtyMasuk">Jumlah masuk</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="qty_masuk" id="qtyMasuk" min="1" required>
                            <span class="input-group-text" id="teksSatuan">Pcs</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="hargaBeli">Harga beli satuan</label>
                        <input type="number" class="form-control" name="harga_beli_satuan" id="hargaBeli" min="1" required>
                    </div>
                </div>
                <div class="alert alert-secondary d-flex justify-content-between py-2">
                    <span>Total biaya</span><strong id="totalKulakan">Rp 0</strong>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="supplier">Supplier / sumber</label>
                    <input type="text" class="form-control" name="supplier" id="supplier" value="Pasar Grosir Suruh">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="kadaluarsaBaru">Tanggal kadaluarsa batch ini</label>
                    <input type="date" class="form-control" name="tgl_kadaluarsa_baru" id="kadaluarsaBaru">
                    <div class="form-text">Kosongkan jika tidak ingin mengubah tanggal kadaluarsa produk.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="catatan">Catatan</label>
                    <input type="text" class="form-control" name="catatan" id="catatan">
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="catat_pengeluaran" id="catatPengeluaran" checked>
                    <label class="form-check-label" for="catatPengeluaran">Catat juga sebagai pengeluaran</label>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary w-100">Simpan &amp; Tambah Stok</button>
            </div>
        </form>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header fw-semibold"><i class="bi bi-clock-history me-1"></i> 25 Kulakan Terakhir</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Tanggal</th><th>Produk</th><th class="text-center">Masuk</th><th class="text-end">Harga</th><th class="text-end">Total</th><th>Supplier</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($riwayat as $r): ?>
                            <tr>
                                <td class="text-nowrap"><?= tanggal_indo($r['tanggal'], true) ?></td>
                                <td><?= e($r['nama_produk']) ?></td>
                                <td class="text-center"><span class="badge text-bg-success">+<?= $r['qty_masuk'] ?> <?= e($r['satuan']) ?></span></td>
                                <td class="text-end"><?= rupiah($r['harga_beli_satuan']) ?></td>
                                <td class="text-end fw-semibold"><?= rupiah($r['qty_masuk'] * $r['harga_beli_satuan']) ?></td>
                                <td><?= e($r['supplier']) ?><?php if ($r['catatan']): ?><small class="d-block text-body-secondary"><?= e($r['catatan']) ?></small><?php endif; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$riwayat): ?>
                            <tr><td colspan="6" class="text-center text-body-secondary py-4">Belum ada riwayat kulakan.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/layout/admin_bottom.php'; ?>
