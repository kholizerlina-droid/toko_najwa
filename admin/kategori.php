<?php
// admin/kategori.php - Kelola kategori produk (tambah, ubah, hapus).
require_once __DIR__ . '/../includes/init_admin.php';

/* ---------- Proses form ---------- */
if (is_post()) {
    $aksi = post('aksi');
    $id   = (int) post('id');
    $nama = post('nama_kategori');
    $desk = post('deskripsi');

    if ($aksi === 'tambah' || $aksi === 'ubah') {
        if ($nama === '') {
            set_flash('danger', 'Nama kategori wajib diisi.');
        } elseif ($aksi === 'tambah') {
            jalankan('INSERT INTO kategori (nama_kategori, deskripsi) VALUES (?, ?)', 'ss', [$nama, $desk]);
            set_flash('success', 'Kategori baru berhasil ditambahkan.');
        } else {
            jalankan('UPDATE kategori SET nama_kategori = ?, deskripsi = ? WHERE id = ?', 'ssi', [$nama, $desk, $id]);
            set_flash('success', 'Kategori berhasil diperbarui.');
        }
    } elseif ($aksi === 'hapus') {
        if (ambil_nilai('SELECT COUNT(*) FROM produk WHERE kategori_id = ?', 'i', [$id]) > 0) {
            set_flash('danger', 'Kategori tidak bisa dihapus karena masih dipakai oleh produk.');
        } else {
            jalankan('DELETE FROM kategori WHERE id = ?', 'i', [$id]);
            set_flash('success', 'Kategori berhasil dihapus.');
        }
    }
    redirect('kategori.php');
}

/* ---------- Ambil data ---------- */
$daftar_kategori = ambil_semua(
    "SELECT k.*, COUNT(p.id) AS jumlah_produk
     FROM kategori k LEFT JOIN produk p ON p.kategori_id = k.id
     GROUP BY k.id ORDER BY k.id"
);

/* ---------- Tampilan ---------- */
$judul_halaman = 'Kategori Produk';
$menu_aktif    = 'kategori';
include __DIR__ . '/../includes/layout/admin_top.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 mb-0">Kategori Produk</h1>
        <p class="text-body-secondary mb-0">Kelompokkan barang agar mudah dicari di kasir dan katalog.</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalKategori" data-aksi="tambah" data-judul="Tambah Kategori">
        <i class="bi bi-plus-lg me-1"></i> Tambah Kategori
    </button>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th><th>Nama Kategori</th><th>Deskripsi</th>
                    <th class="text-center">Jumlah Produk</th><th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($daftar_kategori as $no => $k): ?>
                    <tr>
                        <td><?= $no + 1 ?></td>
                        <td class="fw-semibold"><?= e($k['nama_kategori']) ?></td>
                        <td class="text-body-secondary"><?= e($k['deskripsi']) ?></td>
                        <td class="text-center"><span class="badge text-bg-secondary"><?= $k['jumlah_produk'] ?></span></td>
                        <td class="text-end text-nowrap">
                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                    data-bs-toggle="modal" data-bs-target="#modalKategori" data-aksi="ubah" data-judul="Ubah Kategori"
                                    data-id="<?= $k['id'] ?>"
                                    data-nama_kategori="<?= e($k['nama_kategori']) ?>"
                                    data-deskripsi="<?= e($k['deskripsi']) ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="post" class="d-inline" data-konfirmasi="Hapus kategori &quot;<?= e($k['nama_kategori']) ?>&quot;?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="aksi" value="hapus">
                                <input type="hidden" name="id" value="<?= $k['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal tambah / ubah (satu modal dipakai untuk keduanya) -->
<div class="modal fade" id="modalKategori" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="tambah">
            <input type="hidden" name="id">
            <div class="modal-header">
                <h2 class="modal-title h5">Tambah Kategori</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="kategoriNama" class="form-label">Nama Kategori</label>
                    <input type="text" class="form-control" name="nama_kategori" id="kategoriNama" required>
                </div>
                <div>
                    <label for="kategoriDeskripsi" class="form-label">Deskripsi</label>
                    <textarea class="form-control" name="deskripsi" id="kategoriDeskripsi" rows="3"></textarea>
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
