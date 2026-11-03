<?php
// admin/ajax/riwayat_cicilan.php
// Mengembalikan potongan HTML riwayat cicilan satu kasbon (dipanggil oleh assets/js/piutang.js).
require_once __DIR__ . '/../../includes/init.php';
wajib_login();

$daftar = ambil_semua(
    'SELECT * FROM piutang_pembayaran WHERE piutang_id = ? ORDER BY tgl_bayar DESC',
    'i', [(int) get('id')]
);

if (!$daftar) {
    exit('<div class="text-center text-body-secondary py-4">Belum ada cicilan yang tercatat.</div>');
}
?>
<div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
        <thead class="table-light"><tr><th>Waktu</th><th>Metode</th><th class="text-end">Jumlah</th><th>Keterangan</th></tr></thead>
        <tbody>
            <?php foreach ($daftar as $c): ?>
                <tr>
                    <td><?= tanggal_indo($c['tgl_bayar'], true) ?></td>
                    <td><span class="badge text-bg-secondary text-uppercase"><?= e($c['metode']) ?></span></td>
                    <td class="text-end fw-semibold text-success"><?= rupiah($c['jumlah_bayar']) ?></td>
                    <td><?= e($c['catatan']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
