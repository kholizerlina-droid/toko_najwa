<?php
// admin/riwayat_cicilan_ajax.php
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die("ID tidak valid");

$res = $koneksi->query("SELECT * FROM piutang_pembayaran WHERE piutang_id = $id ORDER BY tgl_bayar DESC");
$list = $res->fetch_all(MYSQLI_ASSOC);

if (empty($list)) {
    echo '<div class="text-center py-4 text-muted"><i class="bi bi-info-circle fs-3 d-block mb-1"></i>Belum ada pembayaran cicilan yang tercatat untuk utang ini.</div>';
    exit;
}
?>
<div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
        <thead class="table-light small">
            <tr>
                <th>Waktu Bayar</th>
                <th>Metode</th>
                <th class="text-end">Jumlah Bayar</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($list as $c): ?>
                <tr>
                    <td>
                        <small class="fw-semibold text-dark"><?= date('d/m/Y', strtotime($c['tgl_bayar'])); ?></small>
                        <small class="text-muted d-block"><?= date('H:i', strtotime($c['tgl_bayar'])); ?> WIB</small>
                    </td>
                    <td><span class="badge bg-light text-dark border text-uppercase"><?= htmlspecialchars($c['metode']); ?></span></td>
                    <td class="text-end fw-bold text-success"><?= rupiah($c['jumlah_bayar']); ?></td>
                    <td><small class="text-muted"><?= htmlspecialchars($c['catatan'] ?? '-'); ?></small></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
