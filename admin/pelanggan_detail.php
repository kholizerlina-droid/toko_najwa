<?php
// admin/pelanggan_detail.php - Profil satu pelanggan beserta kasbon dan riwayat belanjanya.
require_once __DIR__ . '/../includes/init_admin.php';

$id = (int) get('id');
$pelanggan = ambil_satu('SELECT * FROM pelanggan WHERE id = ?', 'i', [$id]);
if (!$pelanggan) {
    set_flash('danger', 'Pelanggan tidak ditemukan.');
    redirect('pelanggan.php');
}

/* ---------- Ambil data ---------- */
$daftar_kasbon = ambil_semua(
    'SELECT * FROM piutang WHERE pelanggan_id = ? ORDER BY status ASC, tgl_utang DESC',
    'i', [$id]
);
$daftar_transaksi = ambil_semua(
    'SELECT id, no_faktur, tanggal, total_harga, metode_pembayaran
     FROM transaksi WHERE pelanggan_id = ? ORDER BY tanggal DESC LIMIT 20',
    'i', [$id]
);

$sisa_kasbon     = (float) ambil_nilai("SELECT COALESCE(SUM(sisa_utang), 0) FROM piutang WHERE pelanggan_id = ? AND status = 'belum_lunas'", 'i', [$id]);
$jumlah_transaksi = (int) ambil_nilai('SELECT COUNT(*) FROM transaksi WHERE pelanggan_id = ?', 'i', [$id]);
$total_belanja   = (float) ambil_nilai('SELECT COALESCE(SUM(total_harga), 0) FROM transaksi WHERE pelanggan_id = ?', 'i', [$id]);

$toko = pengaturan_toko();
$label_metode = ['tunai' => 'Tunai', 'qris' => 'QRIS', 'utang' => 'Kasbon'];

$pesan_wa = "Halo {$pelanggan['nama_pelanggan']}, salam dari {$toko['nama_toko']}. Mengingatkan kasbon sembako sebesar "
          . rupiah($sisa_kasbon) . ", mohon diselesaikan saat longgar ya. Terima kasih!";

/* ---------- Tampilan ---------- */
$judul_halaman = $pelanggan['nama_pelanggan'];
$menu_aktif    = 'pelanggan';
include __DIR__ . '/../includes/layout/admin_top.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <a href="pelanggan.php" class="text-decoration-none small"><i class="bi bi-arrow-left me-1"></i>Kembali ke daftar pelanggan</a>
        <h1 class="h3 mb-0 mt-1"><?= e($pelanggan['nama_pelanggan']) ?></h1>
    </div>
    <?php if ($pelanggan['no_telepon']): ?>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-success" target="_blank" href="https://wa.me/<?= nomor_wa($pelanggan['no_telepon']) ?>">
                <i class="bi bi-whatsapp me-1"></i> Chat WhatsApp
            </a>
            <?php if ($sisa_kasbon > 0): ?>
                <a class="btn btn-success" target="_blank" href="https://wa.me/<?= nomor_wa($pelanggan['no_telepon']) ?>?text=<?= urlencode($pesan_wa) ?>">
                    <i class="bi bi-bell me-1"></i> Ingatkan Kasbon
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header fw-semibold">Profil</div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between"><span class="text-body-secondary">Telepon</span><span><?= $pelanggan['no_telepon'] ? e($pelanggan['no_telepon']) : '-' ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-body-secondary">Alamat</span><span class="text-end"><?= $pelanggan['alamat'] ? e($pelanggan['alamat']) : '-' ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-body-secondary">Batas kasbon</span><span><?= $pelanggan['batas_utang'] > 0 ? rupiah($pelanggan['batas_utang']) : 'Tanpa batas' ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-body-secondary">Terdaftar</span><span><?= tanggal_indo($pelanggan['created_at']) ?></span></li>
                <?php if ($pelanggan['catatan']): ?>
                    <li class="list-group-item"><span class="text-body-secondary d-block small">Catatan</span><?= e($pelanggan['catatan']) ?></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="row g-3 h-100">
            <div class="col-sm-4"><div class="card card-body h-100"><div class="text-body-secondary small">Sisa kasbon</div><div class="h5 mb-0 <?= $sisa_kasbon > 0 ? 'text-danger' : 'text-success' ?>"><?= rupiah($sisa_kasbon) ?></div></div></div>
            <div class="col-sm-4"><div class="card card-body h-100"><div class="text-body-secondary small">Jumlah transaksi</div><div class="h5 mb-0"><?= $jumlah_transaksi ?></div></div></div>
            <div class="col-sm-4"><div class="card card-body h-100"><div class="text-body-secondary small">Total belanja</div><div class="h5 mb-0"><?= rupiah($total_belanja) ?></div></div></div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header fw-semibold">Kasbon</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Tgl Utang</th><th>Jatuh Tempo</th><th>Keterangan</th><th class="text-end">Total</th><th class="text-end">Sisa</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php foreach ($daftar_kasbon as $k):
                    $terlambat = $k['status'] === 'belum_lunas' && $k['tgl_jatuh_tempo'] && $k['tgl_jatuh_tempo'] < date('Y-m-d');
                ?>
                    <tr>
                        <td class="text-nowrap"><?= date('d/m/Y', strtotime($k['tgl_utang'])) ?></td>
                        <td class="text-nowrap">
                            <?= $k['tgl_jatuh_tempo'] ? date('d/m/Y', strtotime($k['tgl_jatuh_tempo'])) : '-' ?>
                            <?php if ($terlambat): ?><span class="badge text-bg-danger">Terlambat</span><?php endif; ?>
                        </td>
                        <td class="text-body-secondary"><?= e($k['catatan']) ?></td>
                        <td class="text-end"><?= rupiah($k['total_utang']) ?></td>
                        <td class="text-end fw-semibold <?= $k['sisa_utang'] > 0 ? 'text-danger' : 'text-success' ?>"><?= rupiah($k['sisa_utang']) ?></td>
                        <td><span class="badge <?= $k['status'] === 'lunas' ? 'text-bg-success' : 'text-bg-danger' ?>"><?= $k['status'] === 'lunas' ? 'Lunas' : 'Belum lunas' ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$daftar_kasbon): ?>
                    <tr><td colspan="6" class="text-center text-body-secondary py-4">Belum pernah kasbon.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($sisa_kasbon > 0): ?>
        <div class="card-footer text-end"><a href="piutang.php?q=<?= urlencode($pelanggan['nama_pelanggan']) ?>" class="btn btn-sm btn-success">Bayar di Buku Kasbon</a></div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header fw-semibold">Riwayat belanja <small class="text-body-secondary fw-normal">(20 terakhir)</small></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>No. Faktur</th><th>Tanggal</th><th>Metode</th><th class="text-end">Total</th><th class="text-end">Struk</th></tr>
            </thead>
            <tbody>
                <?php foreach ($daftar_transaksi as $t): ?>
                    <tr>
                        <td class="font-monospace small"><?= e($t['no_faktur']) ?></td>
                        <td class="text-nowrap"><?= tanggal_indo($t['tanggal'], true) ?></td>
                        <td><?= e($label_metode[$t['metode_pembayaran']] ?? $t['metode_pembayaran']) ?></td>
                        <td class="text-end"><?= rupiah($t['total_harga']) ?></td>
                        <td class="text-end"><a href="cetak_struk.php?faktur=<?= urlencode($t['no_faktur']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Cetak struk"><i class="bi bi-printer"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$daftar_transaksi): ?>
                    <tr><td colspan="5" class="text-center text-body-secondary py-4">Belum ada transaksi atas nama pelanggan ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/layout/admin_bottom.php'; ?>