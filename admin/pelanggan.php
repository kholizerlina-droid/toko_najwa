<?php
// admin/pelanggan.php - Data pelanggan: tambah, ubah, hapus, cari.
require_once __DIR__ . '/../includes/init_admin.php';

/* ---------- Proses form ---------- */
if (is_post()) {
    $aksi    = post('aksi');
    $id      = (int) post('id');
    $nama    = post('nama_pelanggan');
    $telepon = post('no_telepon');
    $alamat  = post('alamat');
    $batas   = max(0, (float) post('batas_utang'));
    $catatan = post('catatan');

    if ($aksi === 'tambah' || $aksi === 'ubah') {
        if ($nama === '') {
            set_flash('danger', 'Nama pelanggan wajib diisi.');
        } elseif ($telepon !== '' && !preg_match('/^[0-9+\-\s()]{6,30}$/', $telepon)) {
            set_flash('danger', 'Nomor telepon tidak valid (contoh: 081234567890).');
        } elseif (ambil_nilai('SELECT COUNT(*) FROM pelanggan WHERE nama_pelanggan = ? AND id <> ?', 'si', [$nama, $id]) > 0) {
            set_flash('danger', "Pelanggan \"$nama\" sudah terdaftar. Jika orangnya berbeda, tambahkan keterangan pada nama (contoh: Pak Budi RT 02).");
        } elseif ($aksi === 'tambah') {
            jalankan(
                'INSERT INTO pelanggan (nama_pelanggan, no_telepon, alamat, batas_utang, catatan) VALUES (?, ?, ?, ?, ?)',
                'sssds', [$nama, $telepon !== '' ? $telepon : null, $alamat, $batas, $catatan]
            );
            set_flash('success', 'Pelanggan baru berhasil ditambahkan.');
        } else {
            jalankan(
                'UPDATE pelanggan SET nama_pelanggan = ?, no_telepon = ?, alamat = ?, batas_utang = ?, catatan = ? WHERE id = ?',
                'sssdsi', [$nama, $telepon !== '' ? $telepon : null, $alamat, $batas, $catatan, $id]
            );
            set_flash('success', 'Data pelanggan berhasil diperbarui.');
        }
    } elseif ($aksi === 'hapus') {
        $sisa = (float) ambil_nilai(
            "SELECT COALESCE(SUM(sisa_utang), 0) FROM piutang WHERE pelanggan_id = ? AND status = 'belum_lunas'",
            'i', [$id]
        );
        if ($sisa > 0) {
            set_flash('danger', 'Pelanggan tidak bisa dihapus karena masih punya kasbon ' . rupiah($sisa) . '. Lunasi dulu di menu Buku Kasbon.');
        } else {
            // Nota & kasbon lama tetap tersimpan (nama di nota tidak hilang), hanya tautannya yang dilepas.
            jalankan('DELETE FROM pelanggan WHERE id = ?', 'i', [$id]);
            set_flash('success', 'Pelanggan berhasil dihapus.');
        }
    }
    redirect('pelanggan.php');
}

/* ---------- Ambil data ---------- */
$cari   = get('q');
$filter = get('filter');   // '' = semua | kasbon = hanya yang punya kasbon

$sql = "SELECT pl.*,
               (SELECT COALESCE(SUM(sisa_utang), 0) FROM piutang
                 WHERE pelanggan_id = pl.id AND status = 'belum_lunas') AS sisa_utang,
               (SELECT COUNT(*) FROM transaksi WHERE pelanggan_id = pl.id) AS jumlah_transaksi,
               (SELECT MAX(tanggal) FROM transaksi WHERE pelanggan_id = pl.id) AS terakhir_belanja
        FROM pelanggan pl WHERE 1=1";
$tipe  = '';
$param = [];

if ($cari !== '') {
    $sql .= ' AND (pl.nama_pelanggan LIKE ? OR pl.no_telepon LIKE ? OR pl.alamat LIKE ?)';
    $tipe .= 'sss';
    array_push($param, "%$cari%", "%$cari%", "%$cari%");
}
if ($filter === 'kasbon') {
    $sql .= " AND EXISTS (SELECT 1 FROM piutang WHERE pelanggan_id = pl.id AND status = 'belum_lunas')";
}
$daftar_pelanggan = ambil_semua($sql . ' ORDER BY pl.nama_pelanggan', $tipe, $param);

$jumlah_semua  = (int) ambil_nilai('SELECT COUNT(*) FROM pelanggan');
$jumlah_kasbon = (int) ambil_nilai("SELECT COUNT(DISTINCT pelanggan_id) FROM piutang WHERE status = 'belum_lunas' AND pelanggan_id IS NOT NULL");
$total_kasbon  = (float) ambil_nilai("SELECT COALESCE(SUM(sisa_utang), 0) FROM piutang WHERE status = 'belum_lunas'");

/* ---------- Tampilan ---------- */
$judul_halaman = 'Data Pelanggan';
$menu_aktif    = 'pelanggan';
include __DIR__ . '/../includes/layout/admin_top.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 mb-0">Data Pelanggan</h1>
        <p class="text-body-secondary mb-0">Daftar warga langganan, nomor WhatsApp, dan batas kasbonnya.</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalPelanggan" data-aksi="tambah" data-judul="Tambah Pelanggan">
        <i class="bi bi-plus-lg me-1"></i> Tambah Pelanggan
    </button>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card card-body"><div class="text-body-secondary small">Total pelanggan</div><div class="h4 mb-0"><?= $jumlah_semua ?></div></div></div>
    <div class="col-md-4"><div class="card card-body"><div class="text-body-secondary small">Pelanggan yang masih punya kasbon</div><div class="h4 mb-0"><?= $jumlah_kasbon ?></div></div></div>
    <div class="col-md-4"><div class="card card-body"><div class="text-body-secondary small">Total sisa kasbon</div><div class="h4 mb-0 text-danger"><?= rupiah($total_kasbon) ?></div></div></div>
</div>

<form method="get" class="card card-body mb-3">
    <div class="row g-2">
        <div class="col-md-6"><input type="search" name="q" class="form-control" placeholder="Cari nama / telepon / alamat" value="<?= e($cari) ?>"></div>
        <div class="col-md-4">
            <select name="filter" class="form-select">
                <option value="">Semua pelanggan</option>
                <option value="kasbon" <?= $filter === 'kasbon' ? 'selected' : '' ?>>Hanya yang punya kasbon</option>
            </select>
        </div>
        <div class="col-md-2 d-grid"><button class="btn btn-outline-primary" type="submit">Terapkan</button></div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Pelanggan</th><th>Telepon</th><th class="text-center">Transaksi</th>
                    <th class="text-end">Sisa Kasbon</th><th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($daftar_pelanggan as $p): ?>
                    <tr>
                        <td>
                            <a href="pelanggan_detail.php?id=<?= $p['id'] ?>" class="fw-semibold text-decoration-none"><?= e($p['nama_pelanggan']) ?></a>
                            <?php if ($p['alamat']): ?><small class="d-block text-body-secondary"><?= e($p['alamat']) ?></small><?php endif; ?>
                        </td>
                        <td class="text-nowrap"><?= $p['no_telepon'] ? e($p['no_telepon']) : '<span class="text-body-secondary">-</span>' ?></td>
                        <td class="text-center">
                            <span class="badge text-bg-secondary"><?= (int) $p['jumlah_transaksi'] ?></span>
                            <?php if ($p['terakhir_belanja']): ?>
                                <small class="d-block text-body-secondary">terakhir <?= date('d/m/Y', strtotime($p['terakhir_belanja'])) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <span class="fw-semibold <?= $p['sisa_utang'] > 0 ? 'text-danger' : 'text-body-secondary' ?>"><?= rupiah($p['sisa_utang']) ?></span>
                            <?php if ($p['batas_utang'] > 0): ?>
                                <small class="d-block text-body-secondary">batas <?= rupiah($p['batas_utang']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="pelanggan_detail.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Lihat detail"><i class="bi bi-eye"></i></a>
                            <?php if ($p['no_telepon']): ?>
                                <a class="btn btn-sm btn-outline-success" target="_blank" title="Chat WhatsApp"
                                   href="https://wa.me/<?= nomor_wa($p['no_telepon']) ?>"><i class="bi bi-whatsapp"></i></a>
                            <?php endif; ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary" title="Ubah"
                                    data-bs-toggle="modal" data-bs-target="#modalPelanggan" data-aksi="ubah" data-judul="Ubah Pelanggan"
                                    data-id="<?= $p['id'] ?>"
                                    data-nama_pelanggan="<?= e($p['nama_pelanggan']) ?>"
                                    data-no_telepon="<?= e($p['no_telepon']) ?>"
                                    data-alamat="<?= e($p['alamat']) ?>"
                                    data-batas_utang="<?= (int) $p['batas_utang'] ?>"
                                    data-catatan="<?= e($p['catatan']) ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="post" class="d-inline" data-konfirmasi="Hapus pelanggan &quot;<?= e($p['nama_pelanggan']) ?>&quot;? Riwayat nota tetap tersimpan.">
                                <?= csrf_field() ?>
                                <input type="hidden" name="aksi" value="hapus">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$daftar_pelanggan): ?>
                    <tr><td colspan="5" class="text-center text-body-secondary py-5">Belum ada pelanggan<?= $cari !== '' || $filter !== '' ? ' yang cocok' : '' ?>.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal tambah / ubah (satu modal dipakai untuk keduanya) -->
<div class="modal fade" id="modalPelanggan" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="tambah">
            <input type="hidden" name="id">
            <div class="modal-header">
                <h2 class="modal-title h5">Tambah Pelanggan</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="pelangganNama" class="form-label">Nama pelanggan</label>
                    <input type="text" class="form-control" name="nama_pelanggan" id="pelangganNama" maxlength="100" required>
                </div>
                <div class="mb-3">
                    <label for="pelangganTelepon" class="form-label">Nomor WhatsApp (opsional)</label>
                    <input type="text" class="form-control" name="no_telepon" id="pelangganTelepon" maxlength="30" placeholder="08xxxxxxxxxx">
                </div>
                <div class="mb-3">
                    <label for="pelangganAlamat" class="form-label">Alamat (opsional)</label>
                    <textarea class="form-control" name="alamat" id="pelangganAlamat" rows="2"></textarea>
                </div>
                <div class="mb-3">
                    <label for="pelangganBatas" class="form-label">Batas kasbon (opsional)</label>
                    <div class="input-group">
                        <span class="input-group-text">Rp</span>
                        <input type="number" class="form-control" name="batas_utang" id="pelangganBatas" min="0" step="1000" value="0">
                    </div>
                    <div class="form-text">Isi 0 jika tidak dibatasi. Kasir menolak kasbon baru yang membuat total utang melewati batas ini.</div>
                </div>
                <div>
                    <label for="pelangganCatatan" class="form-label">Catatan (opsional)</label>
                    <textarea class="form-control" name="catatan" id="pelangganCatatan" rows="2" placeholder="Contoh: langganan tiap pagi, ambil barang untuk warung"></textarea>
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