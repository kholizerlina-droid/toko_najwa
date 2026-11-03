// assets/js/admin.js
// Perilaku umum yang dipakai semua halaman admin.

// 1. Form dengan atribut data-konfirmasi="pesan" meminta konfirmasi sebelum dikirim.
//    Contoh: <form method="post" data-konfirmasi="Hapus data ini?">
document.addEventListener('submit', function (event) {
    const pesan = event.target.dataset.konfirmasi;
    if (pesan && !confirm(pesan)) {
        event.preventDefault();
    }
});

// 2. Modal yang dibuka oleh tombol ber-atribut data-* otomatis mengisi form di dalamnya.
//    Nama atribut = nama kolom form. Contoh tombol "ubah":
//    <button data-bs-toggle="modal" data-bs-target="#modalX" data-judul="Ubah Data"
//            data-aksi="ubah" data-id="5" data-nama_kategori="Minuman">
//    -> kolom name="aksi", name="id", name="nama_kategori" terisi, judul modal diganti.
document.querySelectorAll('.modal').forEach(function (modal) {
    modal.addEventListener('show.bs.modal', function (event) {
        const pemicu = event.relatedTarget;
        if (!pemicu) return;

        const form = modal.querySelector('form');
        if (form) form.reset();

        Object.entries(pemicu.dataset).forEach(function ([nama, nilai]) {
            if (nama === 'bsToggle' || nama === 'bsTarget') return;

            if (nama === 'judul') {
                modal.querySelector('.modal-title').textContent = nilai;
                return;
            }
            const kolom = modal.querySelector('[name="' + nama + '"]');
            if (kolom) kolom.value = nilai;
        });
    });
});

// 3. Format angka rupiah untuk skrip lain (dipakai kasir, kulakan, dll.)
function formatRupiah(angka) {
    return 'Rp ' + Number(angka || 0).toLocaleString('id-ID');
}

// 4. Escape teks sebelum disisipkan ke innerHTML (mencegah XSS)
function esc(teks) {
    const div = document.createElement('div');
    div.textContent = teks == null ? '' : String(teks);
    return div.innerHTML.replace(/"/g, '&quot;');
}
