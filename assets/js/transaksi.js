// assets/js/transaksi.js
// Tombol "mata" pada riwayat transaksi: ambil rincian nota lalu tampilkan dalam modal.

const modalDetail = new bootstrap.Modal(document.getElementById('modalDetail'));
const isiDetail = document.getElementById('isiDetail');

document.querySelectorAll('[data-detail]').forEach(function (tombol) {
    tombol.addEventListener('click', function () {
        isiDetail.innerHTML = '<div class="text-center py-4"><div class="spinner-border" role="status"></div></div>';
        modalDetail.show();

        fetch('ajax/transaksi_detail.php?id=' + encodeURIComponent(tombol.dataset.detail))
            .then(function (respon) { return respon.text(); })
            .then(function (html) { isiDetail.innerHTML = html; })
            .catch(function () { isiDetail.innerHTML = '<div class="alert alert-danger mb-0">Gagal memuat rincian.</div>'; });
    });
});
