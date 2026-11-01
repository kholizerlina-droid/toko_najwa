<?php
// admin/produk.php - Data Stok & Deteksi Kadaluarsa (Clean Retail Style)
$page_title = 'Data Stok & Inventaris';
require_once __DIR__ . '/header.php';

// Handle Tambah / Edit / Hapus
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah' || $action === 'edit') {
        $kode_barcode = trim($_POST['kode_barcode'] ?? '');
        $kategori_id  = (int)($_POST['kategori_id'] ?? 0);
        $nama_produk  = trim($_POST['nama_produk'] ?? '');
        $satuan       = trim($_POST['satuan'] ?? 'Pcs');
        $harga_beli   = (float)($_POST['harga_beli'] ?? 0);
        $harga_jual   = (float)($_POST['harga_jual'] ?? 0);
        $stok         = (int)($_POST['stok'] ?? 0);
        $min_stok     = (int)($_POST['min_stok'] ?? 5);
        $tgl_kadaluarsa = !empty($_POST['tgl_kadaluarsa']) ? $_POST['tgl_kadaluarsa'] : null;

        if (empty($kode_barcode) || empty($nama_produk) || $kategori_id <= 0) {
            set_flash('danger', 'Barcode, Kategori, dan Nama Produk wajib diisi!');
        } else {
            if ($action === 'tambah') {
                $cek = $koneksi->prepare("SELECT id FROM produk WHERE kode_barcode = ?");
                $cek->bind_param("s", $kode_barcode);
                $cek->execute();
                if ($cek->get_result()->num_rows > 0) {
                    set_flash('danger', 'Kode Barcode tersebut sudah digunakan.');
                } else {
                    $stmt = $koneksi->prepare("INSERT INTO produk (kode_barcode, kategori_id, nama_produk, satuan, harga_beli, harga_jual, stok, min_stok, tgl_kadaluarsa) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sisdddiis", $kode_barcode, $kategori_id, $nama_produk, $satuan, $harga_beli, $harga_jual, $stok, $min_stok, $tgl_kadaluarsa);
                    $stmt->execute();
                    set_flash('success', 'Produk sembako berhasil ditambahkan.');
                }
            } else {
                $id = (int)$_POST['id'];
                $cek = $koneksi->prepare("SELECT id FROM produk WHERE kode_barcode = ? AND id != ?");
                $cek->bind_param("si", $kode_barcode, $id);
                $cek->execute();
                if ($cek->get_result()->num_rows > 0) {
                    set_flash('danger', 'Kode Barcode tersebut sudah digunakan.');
                } else {
                    $stmt = $koneksi->prepare("UPDATE produk SET kode_barcode=?, kategori_id=?, nama_produk=?, satuan=?, harga_beli=?, harga_jual=?, stok=?, min_stok=?, tgl_kadaluarsa=? WHERE id=?");
                    $stmt->bind_param("sisdddiisi", $kode_barcode, $kategori_id, $nama_produk, $satuan, $harga_beli, $harga_jual, $stok, $min_stok, $tgl_kadaluarsa, $id);
                    $stmt->execute();
                    set_flash('success', 'Data produk sembako diperbarui.');
                }
            }
        }
        header("Location: produk.php");
        exit;
    }

    if ($action === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        $cek_trx = $koneksi->query("SELECT COUNT(*) FROM transaksi_detail WHERE produk_id = $id");
        if ($cek_trx->fetch_row()[0] > 0) {
            set_flash('danger', 'Produk tidak dapat dihapus karena sudah ada riwayat transaksi penjualan.');
        } else {
            $stmt = $koneksi->prepare("DELETE FROM produk WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            set_flash('success', 'Produk berhasil dihapus.');
        }
        header("Location: produk.php");
        exit;
    }
}

// Filter dan Query Data
$kat_filter = isset($_GET['kategori']) ? (int)$_GET['kategori'] : 0;
$stok_filter = $_GET['filter_stok'] ?? '';
$exp_filter  = $_GET['filter_exp'] ?? '';
$search_q    = trim($_GET['q'] ?? '');

$sql = "SELECT p.*, k.nama_kategori FROM produk p JOIN kategori k ON p.kategori_id = k.id WHERE 1=1";
$params = [];
$types = "";

if ($kat_filter > 0) {
    $sql .= " AND p.kategori_id = ?";
    $params[] = $kat_filter;
    $types .= "i";
}

if ($stok_filter === 'low') {
    $sql .= " AND p.stok <= p.min_stok AND p.stok > 0";
} elseif ($stok_filter === 'empty') {
    $sql .= " AND p.stok = 0";
}

if ($exp_filter === 'expired') {
    $sql .= " AND p.tgl_kadaluarsa IS NOT NULL AND p.tgl_kadaluarsa < CURDATE()";
} elseif ($exp_filter === 'warning') {
    $sql .= " AND p.tgl_kadaluarsa IS NOT NULL AND p.tgl_kadaluarsa <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
}

if (!empty($search_q)) {
    $sql .= " AND (p.nama_produk LIKE ? OR p.kode_barcode LIKE ?)";
    $like = "%$search_q%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}

$sql .= " ORDER BY p.nama_produk ASC";

$stmt = $koneksi->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$daftar_produk = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$kategori_options = $koneksi->query("SELECT * FROM kategori ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="fw-bold text-dark mb-1">Data Stok & Deteksi Kadaluarsa</h4>
        <p class="text-muted small mb-0">Pantau ketersediaan barang, modal beli, harga jual, dan pencegahan kadaluarsa produk sembako.</p>
    </div>
    <button type="button" class="btn btn-pill-green shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahProduk">
        <i class="bi bi-plus-lg"></i>
        <span>Tambah Produk</span>
    </button>
</div>

<!-- Filter Bar -->
<div class="bento-card-clean mb-4 p-3">
    <form method="GET" action="produk.php" class="row g-2 align-items-center">
        <div class="col-md-4">
            <input type="text" name="q" class="form-control form-control-sm rounded-pill px-3" placeholder="Cari nama sembako / barcode..." value="<?= htmlspecialchars($search_q); ?>">
        </div>
        <div class="col-md-3">
            <select name="kategori" class="form-select form-select-sm rounded-pill px-3">
                <option value="0">Semua Kategori</option>
                <?php foreach ($kategori_options as $ko): ?>
                    <option value="<?= $ko['id']; ?>" <?= ($kat_filter === (int)$ko['id']) ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($ko['nama_kategori']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="filter_exp" class="form-select form-select-sm rounded-pill px-3">
                <option value="">Status Kadaluarsa</option>
                <option value="warning" <?= ($exp_filter === 'warning') ? 'selected' : ''; ?>>Mendekati Expired (&lt; 30 Hari)</option>
                <option value="expired" <?= ($exp_filter === 'expired') ? 'selected' : ''; ?>>Sudah Expired</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="filter_stok" class="form-select form-select-sm rounded-pill px-3">
                <option value="">Status Stok</option>
                <option value="low" <?= ($stok_filter === 'low') ? 'selected' : ''; ?>>Stok Menipis</option>
                <option value="empty" <?= ($stok_filter === 'empty') ? 'selected' : ''; ?>>Stok Habis</option>
            </select>
        </div>
        <div class="col-md-1 d-flex gap-1">
            <button type="submit" class="btn btn-sm btn-pill-green py-1 px-3 w-100"><i class="bi bi-funnel"></i></button>
            <a href="produk.php" class="btn btn-sm btn-pill-white py-1 px-2" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
        </div>
    </form>
</div>

<!-- Table Data Produk -->
<div class="bento-card-clean p-0 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small">
                <tr>
                    <th class="ps-4" style="width: 50px;">#</th>
                    <th>Nama Produk Sembako</th>
                    <th>Kategori</th>
                    <th class="text-end">Harga Beli</th>
                    <th class="text-end">Harga Jual</th>
                    <th class="text-center">Sisa Stok</th>
                    <th>Kadaluarsa</th>
                    <th class="text-center pe-4" style="width: 100px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($daftar_produk)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted small">
                            <i class="bi bi-box fs-2 d-block mb-1"></i>
                            Tidak ada produk cocok.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($daftar_produk as $idx => $p): 
                        $exp_status = cek_status_kadaluarsa($p['tgl_kadaluarsa']);
                        $svg = '../assets/img/' . get_product_svg($p['nama_produk'], $p['nama_kategori']);
                    ?>
                        <tr>
                            <td class="ps-4 text-muted small"><?= $idx + 1; ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="cart-item-img me-2">
                                        <img src="<?= $svg; ?>" style="width: 28px; height: 28px; object-fit: contain;">
                                    </div>
                                    <div>
                                        <strong class="text-dark small d-block"><?= htmlspecialchars($p['nama_produk']); ?></strong>
                                        <small class="text-muted font-monospace" style="font-size: 0.72rem;"><?= htmlspecialchars($p['kode_barcode']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-secondary border rounded-pill"><?= htmlspecialchars($p['nama_kategori']); ?></span>
                            </td>
                            <td class="text-end small text-muted"><?= rupiah($p['harga_beli']); ?></td>
                            <td class="text-end fw-bold text-success small"><?= rupiah($p['harga_jual']); ?></td>
                            <td class="text-center">
                                <?php if ($p['stok'] <= $p['min_stok']): ?>
                                    <span class="badge bg-danger rounded-pill"><?= $p['stok']; ?> <?= $p['satuan']; ?></span>
                                <?php else: ?>
                                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill fw-bold"><?= $p['stok']; ?> <?= $p['satuan']; ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= $exp_status['badge']; ?> rounded-pill small">
                                    <?= $exp_status['label']; ?>
                                </span>
                            </td>
                            <td class="text-center pe-4">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-secondary btn-sm p-1" onclick="bukaModalEdit(<?= htmlspecialchars(json_encode($p)); ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm p-1" onclick="konfirmasiHapus(<?= $p['id']; ?>, '<?= addslashes(htmlspecialchars($p['nama_produk'])); ?>')">
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

<!-- Modal Tambah Produk -->
<div class="modal fade" id="modalTambahProduk" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-plus-circle text-success me-2"></i>Tambah Produk Sembako</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="produk.php">
                <input type="hidden" name="action" value="tambah">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Kode Barcode</label>
                            <div class="input-group">
                                <input type="text" name="kode_barcode" id="tambah_barcode" class="form-control rounded-start-pill ps-3" placeholder="Contoh: 8991234567" required>
                                <button type="button" class="btn btn-outline-secondary rounded-end-pill px-3" onclick="generateBarcode('tambah_barcode')">Acak</button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Kategori</label>
                            <select name="kategori_id" class="form-select rounded-pill px-3" required>
                                <option value="">Pilih Kategori</option>
                                <?php foreach ($kategori_options as $ko): ?>
                                    <option value="<?= $ko['id']; ?>"><?= htmlspecialchars($ko['nama_kategori']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold text-muted">Nama Produk Sembako</label>
                            <input type="text" name="nama_produk" class="form-control rounded-pill px-3" placeholder="Contoh: Beras C4 Super 5 Kg" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Satuan</label>
                            <select name="satuan" class="form-select rounded-pill px-3">
                                <option value="Pcs">Pcs</option>
                                <option value="Kg">Kg</option>
                                <option value="Liter">Liter</option>
                                <option value="Pouch">Pouch</option>
                                <option value="Bungkus">Bungkus</option>
                                <option value="Botol">Botol</option>
                                <option value="Kaleng">Kaleng</option>
                                <option value="Renceng">Renceng</option>
                                <option value="Karung">Karung</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Harga Beli Modal (HPP)</label>
                            <input type="number" name="harga_beli" class="form-control rounded-pill px-3" placeholder="Rp" min="0" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Harga Jual Eceran</label>
                            <input type="number" name="harga_jual" class="form-control rounded-pill px-3" placeholder="Rp" min="0" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Stok Awal</label>
                            <input type="number" name="stok" class="form-control rounded-pill px-3" value="10" min="0" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Batas Minimal Stok (Alert)</label>
                            <input type="number" name="min_stok" class="form-control rounded-pill px-3" value="5" min="1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Tanggal Kadaluarsa</label>
                            <input type="date" name="tgl_kadaluarsa" class="form-control rounded-pill px-3">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-pill-white" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-pill-green">Simpan Produk</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Produk -->
<div class="modal fade" id="modalEditProduk" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Produk</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="produk.php">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Kode Barcode</label>
                            <input type="text" name="kode_barcode" id="edit_barcode" class="form-control rounded-pill px-3" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Kategori</label>
                            <select name="kategori_id" id="edit_kategori" class="form-select rounded-pill px-3" required>
                                <?php foreach ($kategori_options as $ko): ?>
                                    <option value="<?= $ko['id']; ?>"><?= htmlspecialchars($ko['nama_kategori']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold text-muted">Nama Produk Sembako</label>
                            <input type="text" name="nama_produk" id="edit_nama" class="form-control rounded-pill px-3" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Satuan</label>
                            <input type="text" name="satuan" id="edit_satuan" class="form-control rounded-pill px-3" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Harga Beli Modal</label>
                            <input type="number" name="harga_beli" id="edit_harga_beli" class="form-control rounded-pill px-3" min="0" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted">Harga Jual</label>
                            <input type="number" name="harga_jual" id="edit_harga_jual" class="form-control rounded-pill px-3" min="0" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Stok Sekarang</label>
                            <input type="number" name="stok" id="edit_stok" class="form-control rounded-pill px-3" min="0" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Batas Minimal Stok</label>
                            <input type="number" name="min_stok" id="edit_min_stok" class="form-control rounded-pill px-3" min="1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted">Tanggal Kadaluarsa</label>
                            <input type="date" name="tgl_kadaluarsa" id="edit_kadaluarsa" class="form-control rounded-pill px-3">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-pill-white" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-pill-green">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="formHapus" method="POST" action="produk.php" style="display:none;">
    <input type="hidden" name="action" value="hapus">
    <input type="hidden" name="id" id="hapus_id">
</form>

<script>
    function generateBarcode(inputId) {
        const rand = Math.floor(1000000 + Math.random() * 9000000);
        document.getElementById(inputId).value = '899' + rand;
    }

    function bukaModalEdit(p) {
        document.getElementById('edit_id').value = p.id;
        document.getElementById('edit_barcode').value = p.kode_barcode;
        document.getElementById('edit_kategori').value = p.kategori_id;
        document.getElementById('edit_nama').value = p.nama_produk;
        document.getElementById('edit_satuan').value = p.satuan;
        document.getElementById('edit_harga_beli').value = parseInt(p.harga_beli);
        document.getElementById('edit_harga_jual').value = parseInt(p.harga_jual);
        document.getElementById('edit_stok').value = p.stok;
        document.getElementById('edit_min_stok').value = p.min_stok;
        document.getElementById('edit_kadaluarsa').value = p.tgl_kadaluarsa || '';

        new bootstrap.Modal(document.getElementById('modalEditProduk')).show();
    }

    function konfirmasiHapus(id, nama) {
        if (confirm('Yakin ingin menghapus sembako: "' + nama + '"?')) {
            document.getElementById('hapus_id').value = id;
            document.getElementById('formHapus').submit();
        }
    }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
