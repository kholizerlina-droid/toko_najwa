<?php
// admin/kategori.php - Manajemen Kategori Sembako Toko Najwa
$page_title = 'Kategori Sembako';
require_once __DIR__ . '/header.php';

// Handle Tambah / Edit / Hapus
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah') {
        $nama_kategori = trim($_POST['nama_kategori'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');

        if (!empty($nama_kategori)) {
            $stmt = $koneksi->prepare("INSERT INTO kategori (nama_kategori, deskripsi) VALUES (?, ?)");
            $stmt->bind_param("ss", $nama_kategori, $deskripsi);
            if ($stmt->execute()) {
                set_flash('success', 'Kategori baru berhasil ditambahkan.');
            } else {
                set_flash('danger', 'Gagal menambahkan kategori: ' . $koneksi->error);
            }
        }
        header("Location: kategori.php");
        exit;
    }

    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $nama_kategori = trim($_POST['nama_kategori'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');

        if ($id > 0 && !empty($nama_kategori)) {
            $stmt = $koneksi->prepare("UPDATE kategori SET nama_kategori = ?, deskripsi = ? WHERE id = ?");
            $stmt->bind_param("ssi", $nama_kategori, $deskripsi, $id);
            if ($stmt->execute()) {
                set_flash('success', 'Kategori berhasil diperbarui.');
            } else {
                set_flash('danger', 'Gagal memperbarui kategori.');
            }
        }
        header("Location: kategori.php");
        exit;
    }

    if ($action === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        // Cek jika kategori memiliki produk
        $cek = $koneksi->query("SELECT COUNT(*) FROM produk WHERE kategori_id = $id");
        if ($cek->fetch_row()[0] > 0) {
            set_flash('danger', 'Kategori tidak dapat dihapus karena masih berisi data produk sembako!');
        } else {
            $stmt = $koneksi->prepare("DELETE FROM kategori WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                set_flash('success', 'Kategori berhasil dihapus.');
            }
        }
        header("Location: kategori.php");
        exit;
    }
}

// Ambil data kategori beserta jumlah produknya
$res = $koneksi->query("SELECT k.*, COUNT(p.id) AS total_produk 
                       FROM kategori k 
                       LEFT JOIN produk p ON k.id = p.kategori_id 
                       GROUP BY k.id 
                       ORDER BY k.nama_kategori ASC");
$kategoris = $res->fetch_all(MYSQLI_ASSOC);
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="fw-bold mb-1">Kategori Produk Sembako</h4>
        <p class="text-muted small mb-0">Pengelompokan barang untuk mempermudah pencarian kasir dan katalog pembeli.</p>
    </div>
    <button type="button" class="btn btn-success fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahKategori">
        <i class="bi bi-plus-circle me-1"></i> Tambah Kategori
    </button>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Nama Kategori</th>
                                <th>Deskripsi / Jenis Barang</th>
                                <th class="text-center">Jumlah Produk</th>
                                <th class="text-center" style="width: 120px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($kategoris)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">Belum ada kategori sembako.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($kategoris as $idx => $kat): ?>
                                    <tr>
                                        <td class="text-muted small"><?= $idx + 1; ?></td>
                                        <td>
                                            <strong class="text-dark"><?= htmlspecialchars($kat['nama_kategori']); ?></strong>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?= htmlspecialchars($kat['deskripsi'] ?? '-'); ?></small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-success bg-opacity-10 text-success fw-bold px-3 py-2 rounded-pill">
                                                <?= $kat['total_produk']; ?> Produk
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary" onclick="bukaModalEdit(<?= htmlspecialchars(json_encode($kat)); ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger" onclick="konfirmasiHapus(<?= $kat['id']; ?>, '<?= addslashes(htmlspecialchars($kat['nama_kategori'])); ?>')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Informasi Bantuan Pengelompokan -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <h6 class="fw-bold mb-2"><i class="bi bi-lightbulb text-warning me-2"></i>Tips Pengkategorian Sembako</h6>
            <p class="text-muted small mb-3">
                Sesuai catatan refleksi proyek kolaborasi SMK Tengaran, pengelompokan produk sembako sangat krusial untuk:
            </p>
            <ul class="text-muted small ps-3 mb-0">
                <li class="mb-2">Mempercepat pencarian kasir saat antrian pembeli sedang ramai.</li>
                <li class="mb-2">Memisahkan produk yang memiliki batas kadaluarsa cepat (seperti telur/bumbu) dengan barang tahan lama.</li>
                <li class="mb-2">Memudahkan penataan stok di rak toko Bu Dhiyah.</li>
            </ul>
        </div>
    </div>
</div>

<!-- Modal Tambah Kategori -->
<div class="modal fade" id="modalTambahKategori" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-tags me-2"></i>Tambah Kategori Sembako</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="kategori.php">
                <input type="hidden" name="action" value="tambah">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Nama Kategori</label>
                        <input type="text" name="nama_kategori" class="form-control" placeholder="Contoh: Sembako Utama" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Deskripsi Barang (Contoh jenis sembakonya)</label>
                        <textarea name="deskripsi" class="form-control" rows="3" placeholder="Contoh: Beras, Telur, Minyak Goreng, Gula Pasir"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success fw-bold px-4">Simpan Kategori</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Kategori -->
<div class="modal fade" id="modalEditKategori" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Kategori Sembako</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="kategori.php">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Nama Kategori</label>
                        <input type="text" name="nama_kategori" id="edit_nama" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Deskripsi Barang</label>
                        <textarea name="deskripsi" id="edit_deskripsi" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Form Hapus Hidden -->
<form id="formHapus" method="POST" action="kategori.php" style="display:none;">
    <input type="hidden" name="action" value="hapus">
    <input type="hidden" name="id" id="hapus_id">
</form>

<script>
    function bukaModalEdit(kat) {
        document.getElementById('edit_id').value = kat.id;
        document.getElementById('edit_nama').value = kat.nama_kategori;
        document.getElementById('edit_deskripsi').value = kat.deskripsi || '';
        new bootstrap.Modal(document.getElementById('modalEditKategori')).show();
    }

    function konfirmasiHapus(id, nama) {
        if (confirm('Yakin ingin menghapus kategori: "' + nama + '"?')) {
            document.getElementById('hapus_id').value = id;
            document.getElementById('formHapus').submit();
        }
    }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
