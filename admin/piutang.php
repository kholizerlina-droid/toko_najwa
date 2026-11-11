<?php
// admin/piutang.php - Buku kasbon: utang warga, cicilan, dan pelunasan.
require_once __DIR__ . '/../includes/init_admin.php';

/* ---------- Proses form ---------- */
if (is_post()) {
    $aksi = post('aksi');

    if ($aksi === 'tambah') {
        $nominal = (float) post('total_utang');

        if ($nominal <= 0) {
            set_flash('danger', 'Pelanggan dan nominal kasbon wajib diisi.');
        } else {
            $db = db();
            $db->begin_transaction();
            try {
                // Kunci baris pelanggan supaya dua kasir tidak lolos cek batas secara bersamaan.
                $pel = ambil_satu(
                    'SELECT id, nama_pelanggan, no_telepon, batas_utang FROM pelanggan WHERE id = ? FOR UPDATE',
                    'i', [(int) post('pelanggan_id')]
                );
                if (!$pel) throw new Exception('Pelanggan tidak ditemukan.');

                $sisa_sekarang = (float) ambil_nilai(
                    "SELECT COALESCE(SUM(sisa_utang), 0) FROM piutang WHERE pelanggan_id = ? AND status = 'belum_lunas'",
                    'i', [$pel['id']]
                );
                if ($pel['batas_utang'] > 0 && $sisa_sekarang + $nominal > $pel['batas_utang']) {
                    throw new Exception('Melebihi batas kasbon ' . rupiah($pel['batas_utang']) . ' (kasbon saat ini ' . rupiah($sisa_sekarang) . ').');
                }

                jalankan(
                    "INSERT INTO piutang (pelanggan_id, nama_pelanggan, no_telepon, total_utang, sisa_utang, tgl_utang, tgl_jatuh_tempo, catatan)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    'issddsss',
                    [$pel['id'], $pel['nama_pelanggan'], $pel['no_telepon'], $nominal, $nominal, post('tgl_utang', date('Y-m-d')),
                     tanggal_atau_null(post('tgl_jatuh_tempo')), post('catatan')]
                );
                $db->commit();
                set_flash('success', 'Kasbon baru berhasil dicatat.');
            } catch (Exception $e) {
                $db->rollback();
                set_flash('danger', $e->getMessage());
            }
        }
    } elseif ($aksi === 'bayar') {
        $id     = (int) post('piutang_id');
        $jumlah = (float) post('jumlah_bayar');

        if ($jumlah <= 0) {
            set_flash('danger', 'Jumlah pembayaran tidak valid.');
        } else {
            $db = db();
            $db->begin_transaction();
            try {
                $utang = ambil_satu('SELECT * FROM piutang WHERE id = ? FOR UPDATE', 'i', [$id]);
                if (!$utang || $utang['status'] !== 'belum_lunas') {
                    throw new Exception('Kasbon ini tidak ditemukan atau sudah tidak aktif.');
                }
                if ($jumlah > $utang['sisa_utang']) {
                    throw new Exception('Pembayaran melebihi sisa utang (' . rupiah($utang['sisa_utang']) . ').');
                }

                $sisa = $utang['sisa_utang'] - $jumlah;
                jalankan(
                    'INSERT INTO piutang_pembayaran (piutang_id, jumlah_bayar, metode, catatan) VALUES (?, ?, ?, ?)',
                    'idss', [$id, $jumlah, post('metode', 'tunai'), $sisa > 0 ? 'Cicilan' : 'Pelunasan']
                );
                jalankan(
                    'UPDATE piutang SET sisa_utang = ?, status = ? WHERE id = ?',
                    'dsi', [$sisa, $sisa > 0 ? 'belum_lunas' : 'lunas', $id]
                );
                $db->commit();
                set_flash('success', 'Pembayaran ' . rupiah($jumlah) . ' berhasil dicatat.');
            } catch (Exception $e) {
                $db->rollback();
                set_flash('danger', 'Gagal memproses pembayaran: ' . $e->getMessage());
            }
        }
    } elseif ($aksi === 'hapus') {
        $id_hapus = (int) post('id');
        $utang_hapus = ambil_satu('SELECT transaksi_id FROM piutang WHERE id = ?', 'i', [$id_hapus]);
        if (!$utang_hapus) {
            set_flash('danger', 'Catatan kasbon tidak ditemukan.');
        } elseif ($utang_hapus['transaksi_id'] !== null) {
            set_flash('danger', 'Kasbon ini terkait transaksi. Batalkan melalui menu Riwayat Transaksi agar stok dan catatan tetap konsisten.');
        } elseif ((int) ambil_nilai('SELECT COUNT(*) FROM piutang_pembayaran WHERE piutang_id = ?', 'i', [$id_hapus]) > 0) {
            set_flash('danger', 'Kasbon memiliki riwayat pembayaran dan tidak dapat dihapus.');
        } else {
            jalankan('DELETE FROM piutang WHERE id = ?', 'i', [$id_hapus]);
            set_flash('success', 'Catatan kasbon dihapus.');
        }
    }
    redirect('piutang.php');
}

/* ---------- Ambil data ---------- */
$status = get('status', 'semua');   // Semua riwayat ditampilkan jika tidak ada filter status.
if (!in_array($status, ['belum_lunas', 'lunas', 'semua'], true)) $status = 'semua';
$cari   = get('q');

// Nama & telepon dibaca dari tabel pelanggan (ikut berubah jika data pelanggan diedit);
// kolom lama di piutang dipakai hanya jika kasbon belum tertaut ke pelanggan.
$sql   = 'SELECT p.*, COALESCE(pl.nama_pelanggan, p.nama_pelanggan) AS nama_pelanggan,
                 COALESCE(pl.no_telepon, p.no_telepon) AS no_telepon
          FROM piutang p LEFT JOIN pelanggan pl ON pl.id = p.pelanggan_id WHERE 1=1';
$tipe  = '';
$param = [];
if ($status !== 'semua') {
    $sql .= ' AND p.status = ?';
    $tipe .= 's';
    $param[] = $status;
}
if ($cari !== '') {
    $sql .= ' AND (COALESCE(pl.nama_pelanggan, p.nama_pelanggan) LIKE ? OR p.catatan LIKE ?)';
    $tipe .= 'ss';
    $param[] = "%$cari%";
    $param[] = "%$cari%";
}
$daftar_piutang = ambil_semua($sql . ' ORDER BY p.status ASC, p.tgl_utang DESC', $tipe, $param);
$daftar_pelanggan = ambil_semua('SELECT id, nama_pelanggan FROM pelanggan ORDER BY nama_pelanggan');

$total_sisa      = ambil_nilai("SELECT COALESCE(SUM(sisa_utang), 0) FROM piutang WHERE status = 'belum_lunas'");
$total_terbayar  = ambil_nilai('SELECT COALESCE(SUM(jumlah_bayar), 0) FROM piutang_pembayaran');
$toko            = pengaturan_toko();

/* ---------- Tampilan ---------- */
$judul_halaman  = 'Buku Kasbon';
$menu_aktif     = 'piutang';
$skrip_tambahan = ['piutang.js'];
include __DIR__ . '/../includes/layout/admin_top.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 mb-0">Buku Kasbon</h1>
        <p class="text-body-secondary mb-0">Pengganti buku utang manual agar catatan tidak terselip atau hilang.</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalKasbon">
        <i class="bi bi-plus-lg me-1"></i> Catat Kasbon
    </button>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6"><div class="card card-body"><div class="text-body-secondary small">Sisa kasbon belum lunas</div><div class="h4 mb-0 text-danger"><?= rupiah($total_sisa) ?></div></div></div>
    <div class="col-md-6"><div class="card card-body"><div class="text-body-secondary small">Total cicilan &amp; pelunasan masuk</div><div class="h4 mb-0 text-success"><?= rupiah($total_terbayar) ?></div></div></div>
</div>

<form method="get" class="card card-body mb-3">
    <div class="row g-2">
        <div class="col-md-6"><input type="search" name="q" class="form-control" placeholder="Cari nama warga / catatan" value="<?= e($cari) ?>"></div>
        <div class="col-md-4">
            <select name="status" class="form-select">
                <option value="belum_lunas" <?= $status === 'belum_lunas' ? 'selected' : '' ?>>Belum lunas</option>
                <option value="lunas" <?= $status === 'lunas' ? 'selected' : '' ?>>Sudah lunas</option>
                <option value="semua" <?= $status === 'semua' ? 'selected' : '' ?>>Semua (bawaan)</option>
            </select>
        </div>
        <div class="col-md-2 d-grid"><button class="btn btn-outline-primary" type="submit">Terapkan</button></div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Warga</th><th>Tgl Utang</th><th>Jatuh Tempo</th><th class="text-end">Total</th><th class="text-end">Sisa</th><th>Status</th><th class="text-end">Aksi</th></tr>
            </thead>
            <tbody>
                <?php foreach ($daftar_piutang as $p):
                    $terlambat = $p['status'] === 'belum_lunas' && $p['tgl_jatuh_tempo'] && $p['tgl_jatuh_tempo'] < date('Y-m-d');
                    $pesan_wa = "Halo {$p['nama_pelanggan']}, salam dari {$toko['nama_toko']}. Mengingatkan kasbon sembako sebesar "
                              . rupiah($p['sisa_utang']) . ", mohon diselesaikan saat longgar ya. Terima kasih!";
                ?>
                    <tr>
                        <td><div class="fw-semibold"><?= e($p['nama_pelanggan']) ?></div><small class="text-body-secondary"><?= e($p['catatan']) ?></small></td>
                        <td class="text-nowrap"><?= date('d/m/Y', strtotime($p['tgl_utang'])) ?></td>
                        <td class="text-nowrap">
                            <?= $p['tgl_jatuh_tempo'] ? date('d/m/Y', strtotime($p['tgl_jatuh_tempo'])) : '-' ?>
                            <?php if ($terlambat): ?><span class="badge text-bg-danger">Terlambat</span><?php endif; ?>
                        </td>
                        <td class="text-end"><?= rupiah($p['total_utang']) ?></td>
                        <td class="text-end fw-semibold <?= $p['sisa_utang'] > 0 ? 'text-danger' : 'text-success' ?>"><?= rupiah($p['sisa_utang']) ?></td>
                        <td><span class="badge <?= $p['status'] === 'lunas' ? 'text-bg-success' : 'text-bg-danger' ?>"><?= $p['status'] === 'lunas' ? 'Lunas' : 'Belum lunas' ?></span></td>
                        <td class="text-end text-nowrap">
                            <?php if ($p['status'] === 'belum_lunas'): ?>
                                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalBayar"
                                        data-piutang_id="<?= $p['id'] ?>" data-jumlah_bayar="<?= (int) $p['sisa_utang'] ?>"
                                        data-nama="<?= e($p['nama_pelanggan']) ?>" data-sisa="<?= (int) $p['sisa_utang'] ?>">Bayar</button>
                                <?php if ($p['no_telepon']): ?>
                                    <a class="btn btn-sm btn-outline-success" target="_blank" title="Ingatkan lewat WhatsApp"
                                       href="https://wa.me/<?= nomor_wa($p['no_telepon']) ?>?text=<?= urlencode($pesan_wa) ?>"><i class="bi bi-whatsapp"></i></a>
                                <?php endif; ?>
                            <?php endif; ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-riwayat="<?= $p['id'] ?>" title="Riwayat cicilan"><i class="bi bi-clock-history"></i></button>
                            <form method="post" class="d-inline" data-konfirmasi="Hapus catatan kasbon <?= e($p['nama_pelanggan']) ?>?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="aksi" value="hapus">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$daftar_piutang): ?>
                    <tr><td colspan="7" class="text-center text-body-secondary py-5">Tidak ada data kasbon.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal catat kasbon -->
<div class="modal fade" id="modalKasbon" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="tambah">
            <div class="modal-header"><h2 class="modal-title h5">Catat Kasbon Baru</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label" for="kasbonPelanggan">Pelanggan</label>
                    <select name="pelanggan_id" id="kasbonPelanggan" class="form-select" required>
                        <option value="">- Pilih pelanggan -</option>
                        <?php foreach ($daftar_pelanggan as $pl): ?>
                            <option value="<?= $pl['id'] ?>"><?= e($pl['nama_pelanggan']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Belum terdaftar? <a href="pelanggan.php">Tambah di Data Pelanggan</a> dulu.</div></div>
                <div class="mb-3"><label class="form-label">Nominal kasbon</label>
                    <div class="input-group"><span class="input-group-text">Rp</span>
                        <input type="number" name="total_utang" class="form-control" min="1000" required></div></div>
                <div class="row g-2 mb-3">
                    <div class="col-6"><label class="form-label">Tanggal utang</label>
                        <input type="date" name="tgl_utang" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                    <div class="col-6"><label class="form-label">Jatuh tempo</label>
                        <input type="date" name="tgl_jatuh_tempo" class="form-control" value="<?= date('Y-m-d', strtotime('+14 days')) ?>"></div>
                </div>
                <div><label class="form-label">Keterangan barang</label>
                    <input type="text" name="catatan" class="form-control" placeholder="Contoh: Beras 5kg + minyak 1L"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal bayar cicilan -->
<div class="modal fade" id="modalBayar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="bayar">
            <input type="hidden" name="piutang_id">
            <div class="modal-header"><h2 class="modal-title h5">Bayar Cicilan / Pelunasan</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body">
                <div class="alert alert-secondary">
                    <div>Warga: <strong id="bayarNama"></strong></div>
                    <div>Sisa utang: <strong id="bayarSisa" class="text-danger"></strong></div>
                </div>
                <div class="mb-3"><label class="form-label">Jumlah dibayar</label>
                    <div class="input-group"><span class="input-group-text">Rp</span>
                        <input type="number" name="jumlah_bayar" class="form-control" min="1" required></div></div>
                <div><label class="form-label">Metode</label>
                    <select name="metode" class="form-select"><option value="tunai">Tunai</option><option value="qris">QRIS</option></select></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success">Proses Pembayaran</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal riwayat cicilan -->
<div class="modal fade" id="modalRiwayat" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h2 class="modal-title h5">Riwayat Cicilan</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body" id="isiRiwayat"></div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/layout/admin_bottom.php'; ?>
