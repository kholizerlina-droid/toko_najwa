// assets/js/piutang.js
// Halaman kasbon: isi info di modal bayar & tampilkan riwayat cicilan.

// Nama & sisa utang di modal "Bayar" (kolom form lain diisi otomatis oleh admin.js)
document.getElementById('modalBayar').addEventListener('show.bs.modal', function (event) {
    const tombol = event.relatedTarget;
    document.getElementById('bayarNama').textContent = tombol.dataset.nama;
    document.getElementById('bayarSisa').textContent = formatRupiah(tombol.dataset.sisa);
});

// Riwayat cicilan: ambil dari ajax/riwayat_cicilan.php
const modalRiwayat = new bootstrap.Modal(document.getElementById('modalRiwayat'));
const isiRiwayat = document.getElementById('isiRiwayat');

document.querySelectorAll('[data-riwayat]').forEach(function (tombol) {
    tombol.addEventListener('click', function () {
        isiRiwayat.innerHTML = '<div class="text-center py-4"><div class="spinner-border" role="status"></div></div>';
        modalRiwayat.show();

        fetch('ajax/riwayat_cicilan.php?id=' + encodeURIComponent(tombol.dataset.riwayat))
            .then(function (respon) { return respon.text(); })
            .then(function (html) { isiRiwayat.innerHTML = html; })
            .catch(function () { isiRiwayat.innerHTML = '<div class="alert alert-danger mb-0">Gagal memuat riwayat.</div>'; });
    });
});
