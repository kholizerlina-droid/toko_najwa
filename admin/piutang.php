<?php
// admin/piutang.php - Buku Kasbon & Piutang Warga (Clean Retail Style)
$page_title = 'Buku Kasbon Warga';
require_once __DIR__ . '/header.php';

// Handle Tambah Utang / Bayar Cicilan / Hapus
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah') {
        $nama        = trim($_POST['nama_pelanggan'] ?? '');
        $telp        = trim($_POST['no_telepon'] ?? '');
        $nominal     = (float)($_POST['nominal_utang'] ?? 0);
        $tgl_utang   = !empty($_POST['tgl_utang']) ? $_POST['tgl_utang'] : date('Y-m-d');
        $jatuh_tempo = !empty($_POST['tgl_jatuh_tempo']) ? $_POST['tgl_jatuh_tempo'] : null;
        $catatan     = trim($_POST['catatan'] ?? '');

        if (empty($nama) || $nominal <= 0) {
            set_flash('danger', 'Nama pelanggan dan nominal kasbon wajib diisi!');
        } else {
            $stmt = $koneksi->prepare("INSERT INTO piutang (nama_pelanggan, no_telepon, total_utang, sisa_utang, tgl_utang, tgl_jatuh_tempo, status, catatan) VALUES (?, ?, ?, ?, ?, ?, 'belum_lunas', ?)");
            $stmt->bind_param("ssddsss", $nama, $telp, $nominal, $nominal, $tgl_utang, $jatuh_tempo, $catatan);
            $stmt->execute();
            set_flash('success', 'Catatan kasbon warga berhasil disimpan.');
        }
        header("Location: piutang.php");
        exit;
    }

    if ($action === 'bayar') {
        $piutang_id   = (int)($_POST['piutang_id'] ?? 0);
        $jumlah_bayar = (float)($_POST['jumlah_bayar'] ?? 0);
        $metode       = $_POST['metode'] ?? 'tunai';
        $catatan_bayar = trim($_POST['catatan_bayar'] ?? 'Pembayaran kasbon');

        if ($piutang_id <= 0 || $jumlah_bayar <= 0) {
            set_flash('danger', 'Jumlah pembayaran tidak valid!');
        } else {
            $cek = $koneksi->query("SELECT * FROM piutang WHERE id = $piutang_id")->fetch_assoc();
            if ($cek) {
                if ($jumlah_bayar > $cek['sisa_utang']) {
                    set_flash('danger', 'Jumlah pembayaran melebihi sisa utang (' . rupiah($cek['sisa_utang']) . ')!');
                } else {
                    $koneksi->begin_transaction();
                    try {
                        $stmt_p = $koneksi->prepare("INSERT INTO piutang_pembayaran (piutang_id, tgl_bayar, jumlah_bayar, metode, catatan) VALUES (?, NOW(), ?, ?, ?)");
                        $stmt_p->bind_param("idss", $piutang_id, $jumlah_bayar, $metode, $catatan_bayar);
                        $stmt_p->execute();

                        $sisa_baru = $cek['sisa_utang'] - $jumlah_bayar;
                        $status_baru = ($sisa_baru <= 0) ? 'lunas' : 'belum_lunas';

                        $upd = $koneksi->prepare("UPDATE piutang SET sisa_utang = ?, status = ? WHERE id = ?");
                        $upd->bind_param("dsi", $sisa_baru, $status_baru, $piutang_id);
                        $upd->execute();

                        $koneksi->commit();
                        set_flash('success', 'Pembayaran sebesar ' . rupiah($jumlah_bayar) . ' berhasil dicatat.');
                    } catch (Exception $e) {
                        $koneksi->rollback();
                        set_flash('danger', 'Gagal memproses pembayaran: ' . $e->getMessage());
                    }
                }
            }
        }
        header("Location: piutang.php");
        exit;
    }

    if ($action === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $koneksi->prepare("DELETE FROM piutang WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        set_flash('success', 'Catatan kasbon berhasil dihapus.');
        header("Location: piutang.php");
        exit;
    }
}

// Filter status
$status_filter = $_GET['status'] ?? 'belum_lunas';
$search_q = trim($_GET['q'] ?? '');

$sql = "SELECT p.* FROM piutang p WHERE 1=1";
$params = [];
$types = "";

if ($status_filter !== 'semua') {
    $sql .= " AND p.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($search_q)) {
    $sql .= " AND (p.nama_pelanggan LIKE ? OR p.catatan LIKE ?)";
    $like = "%$search_q%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}

$sql .= " ORDER BY p.status ASC, p.tgl_utang DESC";

$stmt = $koneksi->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$piutang_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$total_belum_lunas = $koneksi->query("SELECT COALESCE(SUM(sisa_utang), 0) FROM piutang WHERE status = 'belum_lunas'")->fetch_row()[0];
$total_telah_dibayar = $koneksi->query("SELECT COALESCE(SUM(jumlah_bayar), 0) FROM piutang_pembayaran")->fetch_row()[0];
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="fw-bold text-dark mb-1">Buku Kasbon & Piutang Warga</h4>
        <p class="text-muted small mb-0">Digitalisasi buku utang harian manual warga untuk mencegah catatan terselip atau rusak.</p>
    </div>
    <button type="button" class="btn btn-pill-green shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahUtang">
        <i class="bi bi-plus-lg"></i>
        <span>Catat Kasbon Baru</span>
    </button>
</div>

<!-- 2 Kartu Ringkasan -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="bento-card-clean">
            <span class="small text-muted fw-semibold d-block mb-1">Total Sisa Kasbon Belum Lunas</span>
            <h3 class="fw-bold text-danger mb-0"><?= rupiah($total_belum_lunas); ?></h3>
            <small class="text-muted" style="font-size: 0.72rem;">Harus ditagih saat jatuh tempo</small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="bento-card-clean">
            <span class="small text-muted fw-semibold d-block mb-1">Total Cicilan & Pelunasan Masuk</span>
            <h3 class="fw-bold text-success mb-0"><?= rupiah($total_telah_dibayar); ?></h3>
            <small class="text-muted" style="font-size: 0.72rem;">Uang kasbon yang sudah dilunasi warga</small>
        </div>
    </div>
</div>

<!-- Filter Box -->
<div class="bento-card-clean mb-4 p-3">
    <form method="GET" action="piutang.php" class="row g-2 align-items-center">
        <div class="col-md-6">
            <input type="text" name="q" class="form-control form-control-sm rounded-pill px-3" placeholder="Cari nama warga / catatan..." value="<?= htmlspecialchars($search_q); ?>">
        </div>
        <div class="col-md-4">
            <select name="status" class="form-select form-select-sm rounded-pill px-3">
                <option value="belum_lunas" <?= ($status_filter === 'belum_lunas') ? 'selected' : ''; ?>>Belum Lunas (Masih Ada Utang)</option>
                <option value="lunas" <?= ($status_filter === 'lunas') ? 'selected' : ''; ?>>Sudah Lunas</option>
                <option value="semua" <?= ($status_filter === 'semua') ? 'selected' : ''; ?>>Semua Catatan</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-sm btn-pill-green py-1 px-3 w-100"><i class="bi bi-funnel"></i></button>
            <a href="piutang.php" class="btn btn-sm btn-pill-white py-1 px-2" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
        </div>
    </form>
</div>

<!-- Table Piutang -->
<div class="bento-card-clean p-0 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small">
                <tr>
                    <th class="ps-4" style="width: 50px;">#</th>
                    <th>Nama Warga</th>
                    <th>Tgl Kasbon</th>
                    <th>Jatuh Tempo</th>
                    <th class="text-end">Total Utang</th>
                    <th class="text-end">Sisa Utang</th>
                    <th class="text-center">Status</th>
                    <th class="text-center pe-4" style="width: 140px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($piutang_list)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted small">
                            <i class="bi bi-journal-check fs-2 d-block mb-1"></i>
                            Tidak ada data kasbon.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($piutang_list as $idx => $pi): ?>
                        <tr>
                            <td class="ps-4 text-muted small"><?= $idx + 1; ?></td>
                            <td>
                                <strong class="d-block text-dark small"><?= htmlspecialchars($pi['nama_pelanggan']); ?></strong>
                                <small class="text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($pi['catatan'] ?? '-'); ?></small>
                            </td>
                            <td><small class="text-muted"><?= date('d/m/Y', strtotime($pi['tgl_utang'])); ?></small></td>
                            <td>
                                <small class="text-muted"><?= $pi['tgl_jatuh_tempo'] ? date('d/m/Y', strtotime($pi['tgl_jatuh_tempo'])) : '-'; ?></small>
                            </td>
                            <td class="text-end small text-muted"><?= rupiah($pi['total_utang']); ?></td>
                            <td class="text-end">
                                <strong class="<?= ($pi['sisa_utang'] > 0) ? 'text-danger' : 'text-success'; ?> small">
                                    <?= rupiah($pi['sisa_utang']); ?>
                                </strong>
                            </td>
                            <td class="text-center">
                                <?php if ($pi['status'] === 'lunas'): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill">Lunas</span>
                                <?php else: ?>
                                    <span class="badge bg-danger rounded-pill">Belum Lunas</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center pe-4">
                                <div class="btn-group btn-group-sm">
                                    <?php if ($pi['status'] === 'belum_lunas'): ?>
                                        <button type="button" class="btn btn-sm btn-success rounded-pill px-2 py-0" onclick="bukaModalBayar(<?= $pi['id']; ?>, '<?= addslashes(htmlspecialchars($pi['nama_pelanggan'])); ?>', <?= $pi['sisa_utang']; ?>)" title="Bayar Cicilan">
                                            Bayar
                                        </button>
                                    <?php endif; ?>

                                    <?php if (!empty($pi['no_telepon']) && $pi['status'] === 'belum_lunas'): ?>
                                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $pi['no_telepon']); ?>?text=<?= urlencode("Halo " . $pi['nama_pelanggan'] . ", salam dari Toko Najwa (Bu Dhiyah). Mengingatkan catatan kasbon sembako sebesar " . rupiah($pi['sisa_utang']) . " dapat diselesaikan saat longgar ya. Terima kasih!"); ?>" target="_blank" class="btn btn-sm btn-outline-success border-0" title="Kirim WA">
                                            <i class="bi bi-whatsapp"></i>
                                        </a>
                                    <?php endif; ?>

                                    <button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="konfirmasiHapus(<?= $pi['id']; ?>, '<?= addslashes(htmlspecialchars($pi['nama_pelanggan'])); ?>')" title="Hapus">
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

<!-- Modal Tambah Kasbon -->
<div class="modal fade" id="modalTambahUtang" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-journal-plus text-warning me-2"></i>Catat Kasbon Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="piutang.php">
                <input type="hidden" name="action" value="tambah">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Nama Warga</label>
                        <input type="text" name="nama_pelanggan" class="form-control rounded-pill px-3" placeholder="Contoh: Pak Budi RT 02" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Nomor WhatsApp (Opsional)</label>
                        <input type="text" name="no_telepon" class="form-control rounded-pill px-3" placeholder="08xxxxxxxx">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Nominal Kasbon (Rp)</label>
                        <input type="number" name="nominal_utang" class="form-control rounded-pill px-3" placeholder="0" min="1000" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-muted">Tanggal Utang</label>
                            <input type="date" name="tgl_utang" class="form-control rounded-pill px-3" value="<?= date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-muted">Jatuh Tempo</label>
                            <input type="date" name="tgl_jatuh_tempo" class="form-control rounded-pill px-3" value="<?= date('Y-m-d', strtotime('+14 days')); ?>">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold text-muted">Keterangan Barang</label>
                        <input type="text" name="catatan" class="form-control rounded-pill px-3" placeholder="Contoh: Beras 5kg + Minyak 1L">
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-pill-white" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-pill-green">Simpan Kasbon</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Bayar Cicilan -->
<div class="modal fade" id="modalBayarCicilan" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-cash-coin text-success me-2"></i>Bayar Cicilan / Pelunasan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="piutang.php">
                <input type="hidden" name="action" value="bayar">
                <input type="hidden" name="piutang_id" id="bayar_piutang_id">
                <div class="modal-body p-4">
                    <div class="p-3 bg-light rounded-4 mb-3 border">
                        <small class="text-muted d-block">Pembeli:</small>
                        <strong id="bayar_nama" class="text-dark fs-6 d-block mb-1"></strong>
                        <small class="text-muted d-block">Sisa Tagihan:</small>
                        <strong id="bayar_sisa_display" class="text-danger fs-5"></strong>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Jumlah yang Dibayarkan (Rp)</label>
                        <input type="number" name="jumlah_bayar" id="bayar_jumlah" class="form-control rounded-pill px-3 fs-5 fw-bold text-success" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Metode Pembayaran</label>
                        <select name="metode" class="form-select rounded-pill px-3">
                            <option value="tunai">Tunai (Cash)</option>
                            <option value="qris">Scan QRIS</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-pill-white" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-pill-green">Proses Pembayaran</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="formHapus" method="POST" action="piutang.php" style="display:none;">
    <input type="hidden" name="action" value="hapus">
    <input type="hidden" name="id" id="hapus_id">
</form>

<script>
    function bukaModalBayar(id, nama, sisa) {
        document.getElementById('bayar_piutang_id').value = id;
        document.getElementById('bayar_nama').textContent = nama;
        document.getElementById('bayar_sisa_display').textContent = 'Rp ' + Number(sisa).toLocaleString('id-ID');
        document.getElementById('bayar_jumlah').value = sisa;
        document.getElementById('bayar_jumlah').max = sisa;

        new bootstrap.Modal(document.getElementById('modalBayarCicilan')).show();
    }

    function konfirmasiHapus(id, nama) {
        if (confirm('Yakin ingin menghapus catatan kasbon: "' + nama + '"?')) {
            document.getElementById('hapus_id').value = id;
            document.getElementById('formHapus').submit();
        }
    }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
