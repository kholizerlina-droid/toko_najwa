<?php
// includes/laporan.php
// Perhitungan laba rugi satu periode. Dipakai oleh admin/laporan.php dan admin/cetak_laporan.php
// supaya angka di layar dan di kertas cetak selalu sama.

function hitung_laporan(string $tgl_mulai, string $tgl_selesai): array
{
    $penjualan = ambil_satu(
        "SELECT COALESCE(SUM(d.subtotal), 0)            AS omset,
                COALESCE(SUM(d.harga_beli * d.qty), 0)  AS hpp,
                COALESCE(SUM(d.keuntungan), 0)          AS laba_kotor,
                COUNT(DISTINCT t.id)                    AS jumlah_nota
         FROM transaksi t JOIN transaksi_detail d ON d.transaksi_id = t.id
         WHERE DATE(t.tanggal) BETWEEN ? AND ?",
        'ss', [$tgl_mulai, $tgl_selesai]
    );

    $biaya = (float) ambil_nilai(
        'SELECT COALESCE(SUM(jumlah), 0) FROM pengeluaran WHERE tanggal BETWEEN ? AND ?',
        'ss', [$tgl_mulai, $tgl_selesai]
    );

    $omset       = (float) $penjualan['omset'];
    $laba_bersih = (float) $penjualan['laba_kotor'] - $biaya;

    return [
        'omset'       => $omset,
        'hpp'         => (float) $penjualan['hpp'],
        'laba_kotor'  => (float) $penjualan['laba_kotor'],
        'jumlah_nota' => (int) $penjualan['jumlah_nota'],
        'biaya'       => $biaya,
        'laba_bersih' => $laba_bersih,
        'margin'      => $omset > 0 ? round($laba_bersih / $omset * 100, 1) : 0,
    ];
}

// Tanggal dari URL, dipastikan berformat YYYY-MM-DD; kalau tidak valid pakai nilai bawaan.
function tanggal_dari_url(string $nama, string $bawaan): string
{
    $nilai = get($nama);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $nilai) ? $nilai : $bawaan;
}
