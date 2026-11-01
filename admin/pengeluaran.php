<?php
// admin/pengeluaran.php - Pencatatan Biaya Operasional & Kas Keluar
$page_title = 'Biaya Operasional Toko';
require_once __DIR__ . '/header.php';

// Handle Tambah / Hapus
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah') {
        $tanggal   = !empty($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d');
        $kategori  = trim($_POST['kategori_pengeluaran'] ?? 'Lain-lain');
        $jumlah    = (float)($_POST['jumlah'] ?? 0);
        $keterangan = trim($_POST['keterangan'] ?? '');

        if ($jumlah <= 0) {
            set_flash('danger', 'Jumlah pengeluaran harus lebih besar dari 0!');
        } else {
            $stmt = $koneksi->prepare("INSERT INTO pengeluaran (tanggal, kategori_pengeluaran, jumlah, keterangan) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssds", $tanggal, $kategori, $jumlah, $keterangan);
            if ($stmt->execute()) {
                set_flash('success', 'Biaya operasional berhasil dicatat.');
            } else {
                set_flash('danger', 'Gagal mencatat pengeluaran.');
            }
        }
        header("Location: pengeluaran.php");
        exit;
    }

    if ($action === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $koneksi->prepare("DELETE FROM pengeluaran WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            set_flash('success', 'Catatan pengeluaran berhasil dihapus.');
        }
        header("Location: pengeluaran.php");
        exit;
    }
}

// Filter Periode
$bulan_pilih = $_GET['bulan'] ?? date('Y-m');
$start_date = $bulan_pilih . '-01';
$end_date   = date('Y-m-t', strtotime($start_date));

$stmt_p = $koneksi->prepare("SELECT * FROM pengeluaran WHERE tanggal BETWEEN ? AND ? ORDER BY tanggal DESC, id DESC");
$stmt_p->bind_param("ss", $start_date, $end_date);
$stmt_p->execute();
$pengeluaran_list = $stmt_p->get_result()->fetch_all(MYSQLI_ASSOC);

// Hitung total pengeluaran bulan terpilih
$total_bulan = 0;
foreach ($pengeluaran_list as $row) {
    $total_bulan += $row['jumlah'];
}

// Pengeluaran hari ini
$today = date('Y-m-d');
$res_today = $koneksi->query("SELECT COALESCE(SUM(jumlah), 0) FROM pengeluaran WHERE tanggal = '$today'");
$total_today = $res_today->fetch_row()[0];
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="fw-bold mb-1">Pencatatan Biaya Operasional Toko</h4>
        <p class="text-muted small mb-0">Catat semua arus kas keluar (listrik, bensin kulakan, plastik, dll) untuk menghitung Laba Bersih yang akurat.</p>
    </div>
    <button type="button" class="btn btn-danger fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahPengeluaran">
        <i class="bi bi-plus-circle me-1"></i> Catat Biaya Baru
    </button>
</div>

<!-- Kartu Ringkasan Biaya -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card card-stat p-3 bg-white border-0 shadow-sm border-start border-warning border-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Pengeluaran Hari Ini</span>
                    <h3 class="fw-bold text-dark mb-0"><?= rupiah($total_today); ?></h3>
                </div>
                <div class="stat-icon bg-warning bg-opacity-10 text-dark">
                    <i class="bi bi-calendar-day"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card card-stat p-3 bg-white border-0 shadow-sm border-start border-danger border-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Total Pengeluaran Bulan Ini (<?= date('F Y', strtotime($start_date)); ?>)</span>
                    <h3 class="fw-bold text-danger mb-0"><?= rupiah($total_bulan); ?></h3>
                </div>
                <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                    <i class="bi bi-cash-stack"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Bulan -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-3">
        <form method="GET" action="pengeluaran.php" class="row g-2 align-items-center">
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted mb-1">Pilih Bulan Operasional</label>
                <input type="month" name="bulan" class="form-control form-control-sm" value="<?= htmlspecialchars($bulan_pilih); ?>" onchange="this.form.submit()">
            </div>
        </form>
    </div>
</div>

<!-- Tabel Pengeluaran -->
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Tanggal</th>
                        <th>Kategori Pengeluaran</th>
                        <th>Keterangan / Keperluan</th>
                        <th class="text-end">Jumlah Biaya</th>
                        <th class="text-center" style="width: 80px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pengeluaran_list)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-receipt-cutoff fs-1 d-block mb-2"></i>
                                Tidak ada catatan pengeluaran pada bulan ini.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pengeluaran_list as $idx => $pg): ?>
                            <tr>
                                <td class="text-muted small"><?= $idx + 1; ?></td>
                                <td>
                                    <strong class="text-dark"><?= tgl_indo($pg['tanggal']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($pg['kategori_pengeluaran']); ?></span>
                                </td>
                                <td>
                                    <span class="text-muted"><?= htmlspecialchars($pg['keterangan'] ?? '-'); ?></span>
                                </td>
                                <td class="text-end fw-bold text-danger">
                                    <?= rupiah($pg['jumlah']); ?>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-outline-danger btn-sm p-1" onclick="konfirmasiHapus(<?= $pg['id']; ?>)" title="Hapus">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah Pengeluaran -->
<div class="modal fade" id="modalTambahPengeluaran" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Catat Biaya Operasional Toko</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="pengeluaran.php">
                <input type="hidden" name="action" value="tambah">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Tanggal Pengeluaran</label>
                        <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Kategori Pengeluaran</label>
                        <select name="kategori_pengeluaran" class="form-select" required>
                            <option value="Operasional Kulakan">Operasional Kulakan (Bensin / Transport Pasar)</option>
                            <option value="Perlengkapan Warung">Perlengkapan Warung (Plastik Kresek, Kertas, Nota)</option>
                            <option value="Listrik & Penerangan">Listrik & Penerangan Warung (Token PLN)</option>
                            <option value="Perawatan & Kebersihan">Perawatan & Kebersihan Toko</option>
                            <option value="Konsumsi & Pribadi">Konsumsi / Makan Harian Pemilik</option>
                            <option value="Lain-lain">Lain-lain</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Jumlah Pengeluaran (Rp)</label>
                        <input type="number" name="jumlah" class="form-control fs-5 fw-bold text-danger" placeholder="0" min="500" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Keterangan Tambahan</label>
                        <input type="text" name="keterangan" class="form-control" placeholder="Contoh: Beli bensin motor kulakan ke pasar suruh">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger fw-bold px-4">Simpan Biaya</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Form Hapus Hidden -->
<form id="formHapus" method="POST" action="pengeluaran.php" style="display:none;">
    <input type="hidden" name="action" value="hapus">
    <input type="hidden" name="id" id="hapus_id">
</form>

<script>
    function konfirmasiHapus(id) {
        if (confirm('Yakin ingin menghapus catatan pengeluaran ini?')) {
            document.getElementById('hapus_id').value = id;
            document.getElementById('formHapus').submit();
        }
    }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
