<?php
// admin/restock.php - Pencatatan Kulakan / Restock Sembako Toko Najwa
$page_title = 'Kulakan / Restock Barang';
require_once __DIR__ . '/header.php';

// Handle Simpan Kulakan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $produk_id      = (int)($_POST['produk_id'] ?? 0);
    $qty_masuk      = (int)($_POST['qty_masuk'] ?? 0);
    $harga_beli     = (float)($_POST['harga_beli_satuan'] ?? 0);
    $supplier       = trim($_POST['supplier'] ?? 'Pasar Grosir Suruh');
    $tgl_kadaluarsa = !empty($_POST['tgl_kadaluarsa_baru']) ? $_POST['tgl_kadaluarsa_baru'] : null;
    $catatan        = trim($_POST['catatan'] ?? '');
    $catat_kas      = isset($_POST['catat_ke_pengeluaran']);

    if ($produk_id <= 0 || $qty_masuk <= 0 || $harga_beli <= 0) {
        set_flash('danger', 'Produk, Jumlah Masuk, dan Harga Beli Kulakan wajib diisi dengan benar!');
    } else {
        $koneksi->begin_transaction();
        try {
            // 1. Simpan ke tabel restock
            $stmt = $koneksi->prepare("INSERT INTO restock (produk_id, tanggal, qty_masuk, harga_beli_satuan, supplier, tgl_kadaluarsa_baru, catatan) VALUES (?, NOW(), ?, ?, ?, ?, ?)");
            $stmt->bind_param("iidsss", $produk_id, $qty_masuk, $harga_beli, $supplier, $tgl_kadaluarsa, $catatan);
            $stmt->execute();

            // 2. Update stok & harga beli pada produk
            if ($tgl_kadaluarsa) {
                $upd = $koneksi->prepare("UPDATE produk SET stok = stok + ?, harga_beli = ?, tgl_kadaluarsa = ? WHERE id = ?");
                $upd->bind_param("idsi", $qty_masuk, $harga_beli, $tgl_kadaluarsa, $produk_id);
            } else {
                $upd = $koneksi->prepare("UPDATE produk SET stok = stok + ?, harga_beli = ? WHERE id = ?");
                $upd->bind_param("idi", $qty_masuk, $harga_beli, $produk_id);
            }
            $upd->execute();

            // 3. Catat ke pengeluaran jika dicentang
            if ($catat_kas) {
                $total_modal = $qty_masuk * $harga_beli;
                $keterangan = "Kulakan Stok: $supplier ($qty_masuk item)";
                $stmt_k = $koneksi->prepare("INSERT INTO pengeluaran (tanggal, kategori_pengeluaran, jumlah, keterangan) VALUES (CURDATE(), 'Operasional Kulakan', ?, ?)");
                $stmt_k->bind_param("ds", $total_modal, $keterangan);
                $stmt_k->execute();
            }

            $koneksi->commit();
            set_flash('success', 'Data kulakan berhasil disimpan dan stok otomatis bertambah!');
        } catch (Exception $e) {
            $koneksi->rollback();
            set_flash('danger', 'Gagal memproses kulakan: ' . $e->getMessage());
        }
        header("Location: restock.php");
        exit;
    }
}

// Ambil semua produk untuk pilihan kulakan
$produk_list = $koneksi->query("SELECT p.*, k.nama_kategori FROM produk p JOIN kategori k ON p.kategori_id = k.id ORDER BY p.nama_produk ASC")->fetch_all(MYSQLI_ASSOC);

// Pre-selected product jika ada produk_id dari dashboard
$selected_prod_id = isset($_GET['produk_id']) ? (int)$_GET['produk_id'] : 0;

// Riwayat Kulakan
$res_history = $koneksi->query("SELECT r.*, p.nama_produk, p.satuan, k.nama_kategori 
                               FROM restock r 
                               JOIN produk p ON r.produk_id = p.id 
                               JOIN kategori k ON p.kategori_id = k.id 
                               ORDER BY r.tanggal DESC LIMIT 25");
$history = $res_history->fetch_all(MYSQLI_ASSOC);

// Hitung total pengeluaran kulakan bulan ini
$m_start = date('Y-m-01');
$m_end = date('Y-m-t');
$q_sum = $koneksi->query("SELECT COALESCE(SUM(qty_masuk * harga_beli_satuan), 0) FROM restock WHERE DATE(tanggal) BETWEEN '$m_start' AND '$m_end'");
$total_kulakan_bulan = $q_sum->fetch_row()[0];
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="fw-bold mb-1">Pencatatan Kulakan / Restock Sembako</h4>
        <p class="text-muted small mb-0">Catat pasokan barang dari Pasar Grosir Suruh maupun platform grosir digital untuk menambah stok otomatis.</p>
    </div>
    <div class="card border-0 bg-success bg-opacity-10 px-3 py-2 rounded-4">
        <small class="text-muted fw-semibold">Total Modal Kulakan Bulan Ini:</small>
        <span class="fs-5 fw-bold text-success"><?= rupiah($total_kulakan_bulan); ?></span>
    </div>
</div>

<div class="row g-4">
    <!-- Form Input Kulakan Baru -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-success text-white py-3 rounded-top-4">
                <h6 class="fw-bold mb-0"><i class="bi bi-cart-plus me-2"></i>Form Input Kulakan Barang</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="restock.php">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Pilih Produk Sembako</label>
                        <select name="produk_id" id="restockProductSelect" class="form-select" required onchange="onProductSelect()">
                            <option value="">-- Pilih Barang --</option>
                            <?php foreach ($produk_list as $pl): ?>
                                <option value="<?= $pl['id']; ?>" 
                                        data-harga="<?= (int)$pl['harga_beli']; ?>" 
                                        data-satuan="<?= htmlspecialchars($pl['satuan']); ?>"
                                        data-exp="<?= $pl['tgl_kadaluarsa']; ?>"
                                        <?= ($selected_prod_id === (int)$pl['id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($pl['nama_produk']); ?> (Sisa: <?= $pl['stok']; ?> <?= $pl['satuan']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-muted">Jumlah Masuk</label>
                            <div class="input-group">
                                <input type="number" name="qty_masuk" id="restockQty" class="form-control" placeholder="0" min="1" required oninput="calcRestockTotal()">
                                <span class="input-group-text small" id="labelSatuan">Pcs</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-muted">Harga Beli Baru</label>
                            <input type="number" name="harga_beli_satuan" id="restockHarga" class="form-control" placeholder="Rp" min="0" required oninput="calcRestockTotal()">
                        </div>
                    </div>

                    <div class="p-2 mb-3 bg-light rounded border d-flex justify-content-between align-items-center">
                        <small class="text-muted fw-semibold">Total Biaya Kulakan:</small>
                        <strong class="text-primary" id="restockSubtotalDisplay">Rp 0</strong>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Supplier / Sumber Grosir</label>
                        <input type="text" name="supplier" class="form-control" value="Pasar Grosir Suruh" placeholder="Contoh: Pasar Suruh / Aplikasi Grosir Digital" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Tanggal Kadaluarsa Baru (Batch Ini)</label>
                        <input type="date" name="tgl_kadaluarsa_baru" id="restockExp" class="form-control">
                        <small class="text-muted" style="font-size: 0.75rem;">Akan memperbarui tanggal kadaluarsa di sistem</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Catatan Kulakan (Opsional)</label>
                        <input type="text" name="catatan" class="form-control" placeholder="Contoh: Kulakan beras karung 50kg">
                    </div>

                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" name="catat_ke_pengeluaran" id="catatPengeluaran" checked>
                        <label class="form-check-label small" for="catatPengeluaran">
                            Otomatis catat biaya ini ke <strong>Arus Kas Keluar (Pengeluaran)</strong>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-success fw-bold w-100 py-2 shadow-sm">
                        <i class="bi bi-check-circle me-1"></i> Simpan & Tambah Stok
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Riwayat Kulakan Terakhir -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-clock-history me-2 text-primary"></i>Riwayat Kulakan Terakhir</h6>
                <span class="badge bg-light text-secondary border">25 Transaksi Terakhir</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Tanggal</th>
                                <th>Produk Sembako</th>
                                <th class="text-center">Jumlah Masuk</th>
                                <th class="text-end">Harga Satuan</th>
                                <th class="text-end">Total Kulakan</th>
                                <th>Supplier / Sumber</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($history)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">Belum ada riwayat kulakan dicatat.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($history as $h): 
                                    $sub = $h['qty_masuk'] * $h['harga_beli_satuan'];
                                ?>
                                    <tr>
                                        <td>
                                            <span class="text-dark fw-semibold small d-block"><?= date('d/m/Y', strtotime($h['tanggal'])); ?></span>
                                            <small class="text-muted"><?= date('H:i', strtotime($h['tanggal'])); ?> WIB</small>
                                        </td>
                                        <td>
                                            <strong class="text-dark d-block"><?= htmlspecialchars($h['nama_produk']); ?></strong>
                                            <small class="text-muted"><?= htmlspecialchars($h['nama_kategori']); ?></small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-success bg-opacity-10 text-success fw-bold px-2 py-1">
                                                +<?= $h['qty_masuk']; ?> <?= htmlspecialchars($h['satuan']); ?>
                                            </span>
                                        </td>
                                        <td class="text-end text-muted small"><?= rupiah($h['harga_beli_satuan']); ?></td>
                                        <td class="text-end fw-bold text-dark"><?= rupiah($sub); ?></td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($h['supplier'] ?? 'Pasar Suruh'); ?></span>
                                            <?php if (!empty($h['catatan'])): ?>
                                                <small class="d-block text-muted"><?= htmlspecialchars($h['catatan']); ?></small>
                                            <?php endif; ?>
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
</div>

<script>
    function onProductSelect() {
        const select = document.getElementById('restockProductSelect');
        const selected = select.options[select.selectedIndex];
        if (selected && selected.value) {
            const harga = selected.getAttribute('data-harga') || 0;
            const satuan = selected.getAttribute('data-satuan') || 'Pcs';
            const exp = selected.getAttribute('data-exp') || '';

            document.getElementById('restockHarga').value = harga;
            document.getElementById('labelSatuan').textContent = satuan;
            if (exp) {
                document.getElementById('restockExp').value = exp;
            }
            calcRestockTotal();
        }
    }

    function calcRestockTotal() {
        const qty = parseFloat(document.getElementById('restockQty').value) || 0;
        const harga = parseFloat(document.getElementById('restockHarga').value) || 0;
        const total = qty * harga;
        document.getElementById('restockSubtotalDisplay').textContent = 'Rp ' + Number(total).toLocaleString('id-ID');
    }

    // Trigger on load if preselected
    window.addEventListener('DOMContentLoaded', onProductSelect);
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
