<?php
// admin/transaksi.php - Riwayat Transaksi Penjualan Toko Najwa
$page_title = 'Riwayat Transaksi Kasir';
require_once __DIR__ . '/header.php';

// Handle Pembatalan Transaksi (Rollback Stok)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'batalkan') {
    $trx_id = (int)($_POST['id'] ?? 0);
    if ($trx_id > 0) {
        $koneksi->begin_transaction();
        try {
            // Ambil detail produk untuk kembalikan stok
            $res_dt = $koneksi->query("SELECT produk_id, qty FROM transaksi_detail WHERE transaksi_id = $trx_id");
            $upd_stok = $koneksi->prepare("UPDATE produk SET stok = stok + ? WHERE id = ?");
            while ($row = $res_dt->fetch_assoc()) {
                $upd_stok->bind_param("ii", $row['qty'], $row['produk_id']);
                $upd_stok->execute();
            }

            // Hapus piutang terkait jika ada
            $koneksi->query("DELETE FROM piutang WHERE transaksi_id = $trx_id");

            // Hapus transaksi (cascade hapus transaksi_detail)
            $koneksi->query("DELETE FROM transaksi WHERE id = $trx_id");

            $koneksi->commit();
            set_flash('success', 'Transaksi berhasil dibatalkan dan stok produk telah dikembalikan.');
        } catch (Exception $e) {
            $koneksi->rollback();
            set_flash('danger', 'Gagal membatalkan transaksi: ' . $e->getMessage());
        }
    }
    header("Location: transaksi.php");
    exit;
}

// Filter Periode & Parameter
$tgl_mulai  = $_GET['tgl_mulai'] ?? date('Y-m-d');
$tgl_selesai = $_GET['tgl_selesai'] ?? date('Y-m-d');
$metode      = $_GET['metode'] ?? '';
$search_q    = trim($_GET['q'] ?? '');

$sql = "SELECT t.*, u.nama_lengkap AS nama_kasir, 
        (SELECT COALESCE(SUM(keuntungan), 0) FROM transaksi_detail WHERE transaksi_id = t.id) AS laba_transaksi 
        FROM transaksi t 
        LEFT JOIN users u ON t.user_id = u.id 
        WHERE DATE(t.tanggal) BETWEEN ? AND ?";
$params = [$tgl_mulai, $tgl_selesai];
$types = "ss";

if (!empty($metode)) {
    $sql .= " AND t.metode_pembayaran = ?";
    $params[] = $metode;
    $types .= "s";
}

if (!empty($search_q)) {
    $sql .= " AND (t.no_faktur LIKE ? OR t.nama_pelanggan LIKE ?)";
    $like = "%$search_q%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}

$sql .= " ORDER BY t.tanggal DESC";

$stmt = $koneksi->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$transaksi_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Hitung total ringkasan
$total_omset_periode = 0;
$total_laba_periode  = 0;
foreach ($transaksi_list as $t) {
    $total_omset_periode += $t['total_harga'];
    $total_laba_periode  += $t['laba_transaksi'];
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="fw-bold mb-1">Riwayat Transaksi Penjualan</h4>
        <p class="text-muted small mb-0">Daftar rekapan transaksi kasir, rincian barang belanjaan, dan cetak ulang nota pembeli.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="pos.php" class="btn btn-warning fw-bold text-dark shadow-sm">
            <i class="bi bi-cart3 me-1"></i> Buka Kasir
        </a>
    </div>
</div>

<!-- Ringkasan Periode Ini -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card card-stat p-3 bg-white border-0 shadow-sm border-start border-success border-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Total Omset Penjualan</span>
                    <h3 class="fw-bold text-success mb-0"><?= rupiah($total_omset_periode); ?></h3>
                </div>
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-cash-coin"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-stat p-3 bg-white border-0 shadow-sm border-start border-primary border-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Total Laba Penjualan</span>
                    <h3 class="fw-bold text-primary mb-0"><?= rupiah($total_laba_periode); ?></h3>
                </div>
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-stat p-3 bg-white border-0 shadow-sm border-start border-dark border-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Jumlah Transaksi</span>
                    <h3 class="fw-bold text-dark mb-0"><?= count($transaksi_list); ?> Nota</h3>
                </div>
                <div class="stat-icon bg-dark bg-opacity-10 text-dark">
                    <i class="bi bi-receipt"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-3">
        <form method="GET" action="transaksi.php" class="row g-2 align-items-center">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">Mulai Tanggal</label>
                <input type="date" name="tgl_mulai" class="form-control form-control-sm" value="<?= htmlspecialchars($tgl_mulai); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">Sampai Tanggal</label>
                <input type="date" name="tgl_selesai" class="form-control form-control-sm" value="<?= htmlspecialchars($tgl_selesai); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Metode Bayar</label>
                <select name="metode" class="form-select form-select-sm">
                    <option value="">Semua Metode</option>
                    <option value="tunai" <?= ($metode === 'tunai') ? 'selected' : ''; ?>>Tunai</option>
                    <option value="qris" <?= ($metode === 'qris') ? 'selected' : ''; ?>>QRIS</option>
                    <option value="utang" <?= ($metode === 'utang') ? 'selected' : ''; ?>>Kasbon</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted mb-1">Cari No / Nama</label>
                <input type="text" name="q" class="form-control form-control-sm" placeholder="No faktur / warga..." value="<?= htmlspecialchars($search_q); ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end gap-1 pt-4">
                <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-funnel"></i> Filter</button>
                <a href="transaksi.php" class="btn btn-sm btn-outline-secondary" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Tabel Daftar Transaksi -->
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>No Faktur</th>
                        <th>Waktu Transaksi</th>
                        <th>Nama Pelanggan</th>
                        <th>Metode Bayar</th>
                        <th>Kasir</th>
                        <th class="text-end">Total Belanja</th>
                        <th class="text-end">Laba Bersih</th>
                        <th class="text-center" style="width: 160px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transaksi_list)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="bi bi-search fs-1 d-block mb-2"></i>
                                Tidak ada transaksi pada periode yang dipilih.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transaksi_list as $idx => $t): ?>
                            <tr>
                                <td class="text-muted small"><?= $idx + 1; ?></td>
                                <td>
                                    <strong class="text-dark font-monospace"><?= htmlspecialchars($t['no_faktur']); ?></strong>
                                </td>
                                <td>
                                    <span class="small text-dark d-block"><?= date('d/m/Y', strtotime($t['tanggal'])); ?></span>
                                    <small class="text-muted"><?= date('H:i', strtotime($t['tanggal'])); ?> WIB</small>
                                </td>
                                <td>
                                    <span class="text-dark fw-semibold"><?= htmlspecialchars($t['nama_pelanggan']); ?></span>
                                </td>
                                <td>
                                    <?php if ($t['metode_pembayaran'] === 'tunai'): ?>
                                        <span class="badge bg-success">Tunai</span>
                                    <?php elseif ($t['metode_pembayaran'] === 'qris'): ?>
                                        <span class="badge bg-danger">QRIS</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Kasbon</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted"><?= htmlspecialchars($t['nama_kasir'] ?? 'Petugas'); ?></small>
                                </td>
                                <td class="text-end fw-bold text-success">
                                    <?= rupiah($t['total_harga']); ?>
                                </td>
                                <td class="text-end fw-bold text-primary">
                                    +<?= rupiah($t['laba_transaksi']); ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="cetak_struk.php?faktur=<?= urlencode($t['no_faktur']); ?>" target="_blank" class="btn btn-outline-secondary" title="Cetak Struk">
                                            <i class="bi bi-printer"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-info" onclick="lihatDetailTrx(<?= $t['id']; ?>)" title="Lihat Rincian Item">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" onclick="konfirmasiBatal(<?= $t['id']; ?>, '<?= htmlspecialchars($t['no_faktur']); ?>')" title="Batalkan & Kembalikan Stok">
                                            <i class="bi bi-arrow-counterclockwise"></i>
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

<!-- Modal Detail Transaksi -->
<div class="modal fade" id="modalDetailTrx" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-light">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-receipt me-2"></i>Rincian Barang Belanjaan</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3" id="detailTrxContent">
                <div class="text-center py-4 text-muted">Memuat data...</div>
            </div>
        </div>
    </div>
</div>

<!-- Form Batalkan Hidden -->
<form id="formBatal" method="POST" action="transaksi.php" style="display:none;">
    <input type="hidden" name="action" value="batalkan">
    <input type="hidden" name="id" id="batal_id">
</form>

<script>
    function lihatDetailTrx(id) {
        const modal = new bootstrap.Modal(document.getElementById('modalDetailTrx'));
        modal.show();
        const content = document.getElementById('detailTrxContent');
        content.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-success"></div> Memuat rincian...</div>';

        fetch('transaksi_detail_ajax.php?id=' + id)
            .then(res => res.text())
            .then(html => {
                content.innerHTML = html;
            })
            .catch(err => {
                content.innerHTML = '<div class="alert alert-danger">Gagal memuat rincian transaksi.</div>';
            });
    }

    function konfirmasiBatal(id, faktur) {
        if (confirm('PERINGATAN: Yakin ingin membatalkan transaksi faktur ' + faktur + '?\nStok seluruh barang akan dikembalikan ke inventaris!')) {
            document.getElementById('batal_id').value = id;
            document.getElementById('formBatal').submit();
        }
    }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
