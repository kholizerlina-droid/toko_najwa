<?php
// admin/produk.php - Data produk, stok, dan deteksi kadaluarsa.
require_once __DIR__ . '/../includes/init_admin.php';

const DAFTAR_SATUAN = ['Pcs', 'Kg', 'Liter', 'Pouch', 'Bungkus', 'Botol', 'Kaleng', 'Renceng', 'Karung', 'Kotak', 'Galon'];

/* ---------- Proses form ---------- */
if (is_post()) {
    $aksi = post('aksi');
    $id   = (int) post('id');

    if ($aksi === 'tambah' || $aksi === 'ubah') {
        $barcode     = post('kode_barcode');
        $kategori_id = (int) post('kategori_id');
        $nama        = post('nama_produk');
        $satuan      = post('satuan', 'Pcs');
        $harga_beli  = (float) post('harga_beli');
        $harga_jual  = (float) post('harga_jual');
        $stok        = (int) post('stok');
        $min_stok    = (int) post('min_stok', '5');
        $kadaluarsa  = tanggal_atau_null(post('tgl_kadaluarsa'));

        $barcode_dipakai = ambil_nilai(
            'SELECT COUNT(*) FROM produk WHERE kode_barcode = ? AND id <> ?', 'si', [$barcode, $id]
        );

        if ($barcode === '' || $nama === '' || $kategori_id <= 0) {
            set_flash('danger', 'Barcode, kategori, dan nama produk wajib diisi.');
        } elseif ($barcode_dipakai > 0) {
            set_flash('danger', 'Kode barcode tersebut sudah dipakai produk lain.');
        } else {
            try {
                $foto_baru = simpan_gambar($_FILES['gambar'] ?? []);   // null jika tidak ada file
            } catch (Exception $e) {
                set_flash('danger', $e->getMessage());
                redirect('produk.php');
            }

            if ($aksi === 'tambah') {
                jalankan(
                    'INSERT INTO produk (kode_barcode, kategori_id, nama_produk, satuan, harga_beli, harga_jual, stok, min_stok, tgl_kadaluarsa, gambar)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    'sissddiiss',
                    [$barcode, $kategori_id, $nama, $satuan, $harga_beli, $harga_jual, $stok, $min_stok, $kadaluarsa, $foto_baru]
                );
                set_flash('success', 'Produk berhasil ditambahkan.');
            } else {
                $foto_lama = ambil_nilai('SELECT gambar FROM produk WHERE id = ?', 'i', [$id]);
                jalankan(
                    'UPDATE produk SET kode_barcode = ?, kategori_id = ?, nama_produk = ?, satuan = ?, harga_beli = ?,
                            harga_jual = ?, stok = ?, min_stok = ?, tgl_kadaluarsa = ?, gambar = ? WHERE id = ?',
                    'sissddiissi',
                    [$barcode, $kategori_id, $nama, $satuan, $harga_beli, $harga_jual, $stok, $min_stok, $kadaluarsa, $foto_baru ?? $foto_lama, $id]
                );
                if ($foto_baru) {
                    hapus_gambar($foto_lama);   // foto lama diganti
                }
                set_flash('success', 'Data produk berhasil diperbarui.');
            }
        }
    } elseif ($aksi === 'hapus') {
        if (ambil_nilai('SELECT COUNT(*) FROM transaksi_detail WHERE produk_id = ?', 'i', [$id]) > 0
            || ambil_nilai('SELECT COUNT(*) FROM restock WHERE produk_id = ?', 'i', [$id]) > 0) {
            set_flash('danger', 'Produk tidak bisa dihapus karena sudah punya riwayat penjualan / kulakan.');
        } else {
            hapus_gambar(ambil_nilai('SELECT gambar FROM produk WHERE id = ?', 'i', [$id]));
            jalankan('DELETE FROM produk WHERE id = ?', 'i', [$id]);
            set_flash('success', 'Produk berhasil dihapus.');
        }
    }
    redirect('produk.php');
}

/* ---------- Ambil data (dengan filter dari URL) ---------- */
$cari        = get('q');
$kategori_id = (int) get('kategori');
$filter_stok = get('filter_stok');   // menipis | habis
$filter_exp  = get('filter_exp');    // segera | kadaluarsa

$sql    = 'SELECT p.*, k.nama_kategori FROM produk p JOIN kategori k ON k.id = p.kategori_id WHERE 1=1';
$tipe   = '';
$param  = [];

if ($cari !== '') {
    $sql .= ' AND (p.nama_produk LIKE ? OR p.kode_barcode LIKE ?)';
    $tipe .= 'ss';
    $param[] = "%$cari%";
    $param[] = "%$cari%";
}
if ($kategori_id > 0) {
    $sql .= ' AND p.kategori_id = ?';
    $tipe .= 'i';
    $param[] = $kategori_id;
}
if ($filter_stok === 'menipis') {
    $sql .= ' AND p.stok <= p.min_stok';
} elseif ($filter_stok === 'habis') {
    $sql .= ' AND p.stok = 0';
}
if ($filter_exp === 'segera') {
    $sql .= ' AND p.tgl_kadaluarsa <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)';
} elseif ($filter_exp === 'kadaluarsa') {
    $sql .= ' AND p.tgl_kadaluarsa < CURDATE()';
}

$daftar_produk   = ambil_semua($sql . ' ORDER BY p.nama_produk', $tipe, $param);
$daftar_kategori = ambil_semua('SELECT id, nama_kategori FROM kategori ORDER BY nama_kategori');

/* ---------- Tampilan ---------- */
$judul_halaman = 'Data Produk';
$menu_aktif    = 'produk';
include __DIR__ . '/../includes/layout/admin_top.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 mb-0">Data Produk &amp; Stok</h1>
        <p class="text-body-secondary mb-0">Pantau stok, harga, dan tanggal kadaluarsa barang.</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalProduk"
            data-aksi="tambah" data-judul="Tambah Produk">
        <i class="bi bi-plus-lg me-1"></i> Tambah Produk
    </button>
</div>

<!-- Filter -->
<form method="get" class="card card-body mb-3">
    <div class="row g-2">
        <div class="col-md-4">
            <input type="search" name="q" class="form-control" placeholder="Cari nama / barcode" value="<?= e($cari) ?>">
        </div>
        <div class="col-md-3">
            <select name="kategori" class="form-select">
                <option value="0">Semua kategori</option>
                <?php foreach ($daftar_kategori as $k): ?>
                    <option value="<?= $k['id'] ?>" <?= $kategori_id === (int) $k['id'] ? 'selected' : '' ?>><?= e($k['nama_kategori']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="filter_stok" class="form-select">
                <option value="">Semua stok</option>
                <option value="menipis" <?= $filter_stok === 'menipis' ? 'selected' : '' ?>>Stok menipis</option>
                <option value="habis" <?= $filter_stok === 'habis' ? 'selected' : '' ?>>Stok habis</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="filter_exp" class="form-select">
                <option value="">Semua tanggal</option>
                <option value="segera" <?= $filter_exp === 'segera' ? 'selected' : '' ?>>&le; 30 hari lagi</option>
                <option value="kadaluarsa" <?= $filter_exp === 'kadaluarsa' ? 'selected' : '' ?>>Sudah kadaluarsa</option>
            </select>
        </div>
        <div class="col-md-1 d-grid">
            <button type="submit" class="btn btn-outline-primary" aria-label="Terapkan filter"><i class="bi bi-funnel"></i></button>
        </div>
    </div>
</form>

<!-- Tabel -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Produk</th><th>Kategori</th>
                    <th class="text-end">Harga Beli</th><th class="text-end">Harga Jual</th>
                    <th class="text-center">Stok</th><th>Kadaluarsa</th><th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($daftar_produk as $p): $exp = status_kadaluarsa($p['tgl_kadaluarsa']); ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <img src="<?= gambar_produk($p['gambar']) ?>" alt="" class="foto-produk-sm me-2">
                                <div>
                                    <div class="fw-semibold"><?= e($p['nama_produk']) ?></div>
                                    <small class="text-body-secondary font-monospace"><?= e($p['kode_barcode']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td><?= e($p['nama_kategori']) ?></td>
                        <td class="text-end"><?= rupiah($p['harga_beli']) ?></td>
                        <td class="text-end fw-semibold"><?= rupiah($p['harga_jual']) ?></td>
                        <td class="text-center">
                            <span class="badge <?= $p['stok'] <= $p['min_stok'] ? 'text-bg-danger' : 'text-bg-success' ?>">
                                <?= $p['stok'] ?> <?= e($p['satuan']) ?>
                            </span>
                        </td>
                        <td><span class="badge <?= $exp['badge'] ?>"><?= e($exp['label']) ?></span></td>
                        <td class="text-end text-nowrap">
                            <a href="restock.php?produk_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-success" title="Kulakan"><i class="bi bi-truck"></i></a>
                            <button type="button" class="btn btn-sm btn-outline-secondary" title="Ubah"
                                    data-bs-toggle="modal" data-bs-target="#modalProduk"
                                    data-aksi="ubah" data-judul="Ubah Produk" data-id="<?= $p['id'] ?>"
                                    data-kode_barcode="<?= e($p['kode_barcode']) ?>"
                                    data-kategori_id="<?= $p['kategori_id'] ?>"
                                    data-nama_produk="<?= e($p['nama_produk']) ?>"
                                    data-satuan="<?= e($p['satuan']) ?>"
                                    data-harga_beli="<?= (int) $p['harga_beli'] ?>"
                                    data-harga_jual="<?= (int) $p['harga_jual'] ?>"
                                    data-stok="<?= $p['stok'] ?>"
                                    data-min_stok="<?= $p['min_stok'] ?>"
                                    data-tgl_kadaluarsa="<?= e($p['tgl_kadaluarsa']) ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="post" class="d-inline" data-konfirmasi="Hapus produk &quot;<?= e($p['nama_produk']) ?>&quot;?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="aksi" value="hapus">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$daftar_produk): ?>
                    <tr><td colspan="7" class="text-center text-body-secondary py-5">Tidak ada produk yang cocok.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer text-body-secondary small"><?= count($daftar_produk) ?> produk ditampilkan</div>
</div>

<!-- Modal tambah / ubah produk -->
<div class="modal fade" id="modalProduk" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="post" enctype="multipart/form-data" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="tambah">
            <input type="hidden" name="id">
            <div class="modal-header">
                <h2 class="modal-title h5">Tambah Produk</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="pBarcode">Kode Barcode</label>
                        <input type="text" class="form-control" id="pBarcode" name="kode_barcode" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="pKategori">Kategori</label>
                        <select class="form-select" id="pKategori" name="kategori_id" required>
                            <option value="">Pilih kategori</option>
                            <?php foreach ($daftar_kategori as $k): ?>
                                <option value="<?= $k['id'] ?>"><?= e($k['nama_kategori']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="pNama">Nama Produk</label>
                        <input type="text" class="form-control" id="pNama" name="nama_produk" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="pSatuan">Satuan</label>
                        <select class="form-select" id="pSatuan" name="satuan">
                            <?php foreach (DAFTAR_SATUAN as $s): ?>
                                <option><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="pBeli">Harga Beli (modal)</label>
                        <div class="input-group"><span class="input-group-text">Rp</span>
                            <input type="number" class="form-control" id="pBeli" name="harga_beli" min="0" required></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="pJual">Harga Jual</label>
                        <div class="input-group"><span class="input-group-text">Rp</span>
                            <input type="number" class="form-control" id="pJual" name="harga_jual" min="0" required></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="pStok">Stok</label>
                        <input type="number" class="form-control" id="pStok" name="stok" min="0" value="0" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="pMin">Batas Stok Menipis</label>
                        <input type="number" class="form-control" id="pMin" name="min_stok" min="0" value="5" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="pExp">Tanggal Kadaluarsa</label>
                        <input type="date" class="form-control" id="pExp" name="tgl_kadaluarsa">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="pFoto">Foto Produk</label>
                        <input type="file" class="form-control" id="pFoto" name="gambar" accept="image/jpeg,image/png,image/webp">
                        <div class="form-text">JPG, PNG, atau WEBP, maksimal 2 MB. Saat mengubah produk, kosongkan jika tidak ingin mengganti foto.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/layout/admin_bottom.php'; ?>
