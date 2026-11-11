// assets/js/pos.js
// Logika halaman kasir: daftar produk, keranjang, dan perhitungan kembalian.
// Membutuhkan admin.js (formatRupiah, esc).

const semuaProduk = JSON.parse(document.getElementById('dataProduk').textContent);
const pesananOnline = JSON.parse(document.getElementById('dataPesananOnline').textContent || 'null');
let keranjang = [];          // [{id, nama, harga, stok, foto, qty}]
let kategoriAktif = 'semua';
let pesananOnlineValid = true;

const el = {
    daftarProduk: document.getElementById('daftarProduk'),
    daftarKeranjang: document.getElementById('daftarKeranjang'),
    cari: document.getElementById('cariProduk'),
    scan: document.getElementById('scanBarcode'),
    total: document.getElementById('teksTotal'),
    inputKeranjang: document.getElementById('inputKeranjang'),
    tombolBayar: document.getElementById('tombolBayar'),
    inputUang: document.getElementById('inputUang'),
    kembalian: document.getElementById('teksKembalian'),
    form: document.getElementById('formBayar'),
};

/* ---------- Daftar produk ---------- */
function tampilkanProduk() {
    const kata = el.cari.value.toLowerCase();
    const hasil = semuaProduk.filter(function (p) {
        const cocokKategori = kategoriAktif === 'semua' || String(p.kategori_id) === kategoriAktif;
        return cocokKategori && p.nama_produk.toLowerCase().includes(kata);
    });

    if (hasil.length === 0) {
        el.daftarProduk.innerHTML = '<div class="col-12 text-center text-body-secondary py-5">Tidak ada produk yang cocok.</div>';
        return;
    }

    el.daftarProduk.innerHTML = hasil.map(function (p) {
        return '<div class="col-6 col-md-4">'
            + '<div class="card h-100 kartu-klik" data-id="' + p.id + '">'
            + '<div class="card-body text-center">'
            + '<img src="' + esc(p.foto) + '" alt="" class="foto-produk mb-2">'
            + '<div class="small fw-semibold">' + esc(p.nama_produk) + '</div>'
            + '<div class="text-primary fw-bold">' + formatRupiah(p.harga_jual) + '</div>'
            + '<small class="text-body-secondary">Stok ' + p.stok + ' ' + esc(p.satuan) + '</small>'
            + '</div></div></div>';
    }).join('');
}

el.daftarProduk.addEventListener('click', function (event) {
    const kartu = event.target.closest('[data-id]');
    if (kartu) tambahKeKeranjang(Number(kartu.dataset.id));
});

document.getElementById('filterKategori').addEventListener('click', function (event) {
    const tombol = event.target.closest('[data-kategori]');
    if (!tombol) return;
    this.querySelectorAll('.btn').forEach(function (b) { b.classList.remove('active'); });
    tombol.classList.add('active');
    kategoriAktif = tombol.dataset.kategori;
    tampilkanProduk();
});

el.cari.addEventListener('input', tampilkanProduk);

el.scan.addEventListener('keydown', function (event) {
    if (event.key !== 'Enter') return;
    event.preventDefault();
    const produk = semuaProduk.find(function (p) { return p.kode_barcode === el.scan.value.trim(); });
    if (produk) {
        tambahKeKeranjang(produk.id);
        el.scan.value = '';
    } else {
        alert('Barcode tidak ditemukan atau stok habis.');
    }
});

/* ---------- Keranjang ---------- */
function tambahKeKeranjang(id) {
    if (pesananOnline) return;
    const produk = semuaProduk.find(function (p) { return p.id === id; });
    if (!produk) return;

    const item = keranjang.find(function (i) { return i.id === id; });
    if (item) {
        if (item.qty >= produk.stok) return alert('Stok tersisa hanya ' + produk.stok);
        item.qty++;
    } else {
        keranjang.push({ id: produk.id, nama: produk.nama_produk, harga: produk.harga_jual, stok: produk.stok, foto: produk.foto, qty: 1 });
    }
    tampilkanKeranjang();
}

function ubahJumlah(id, selisih) {
    if (pesananOnline) return;
    const item = keranjang.find(function (i) { return i.id === id; });
    if (!item) return;

    item.qty += selisih;
    if (item.qty > item.stok) {
        item.qty = item.stok;
        alert('Stok tersisa hanya ' + item.stok);
    }
    if (item.qty <= 0) {
        keranjang = keranjang.filter(function (i) { return i.id !== id; });
    }
    tampilkanKeranjang();
}

function totalBelanja() {
    return keranjang.reduce(function (jumlah, i) { return jumlah + i.harga * i.qty; }, 0);
}

function tampilkanKeranjang() {
    if (keranjang.length === 0) {
        el.daftarKeranjang.innerHTML = '<li class="list-group-item text-center text-body-secondary py-4">Belum ada barang.</li>';
    } else {
        el.daftarKeranjang.innerHTML = keranjang.map(function (i) {
            const kontrol = pesananOnline
                ? '<span class="badge text-bg-light">Qty ' + i.qty + '</span>'
                : '<div class="btn-group btn-group-sm">'
                    + '<button type="button" class="btn btn-outline-secondary" data-ubah="-1" data-id="' + i.id + '">&minus;</button>'
                    + '<span class="btn btn-outline-secondary disabled">' + i.qty + '</span>'
                    + '<button type="button" class="btn btn-outline-secondary" data-ubah="1" data-id="' + i.id + '">+</button>'
                    + '</div>';
            return '<li class="list-group-item">'
                + '<div class="d-flex align-items-center">'
                + '<img src="' + esc(i.foto) + '" alt="" class="foto-produk-sm me-2">'
                + '<div class="me-auto small"><div class="fw-semibold">' + esc(i.nama) + '</div>'
                + '<span class="text-body-secondary">' + formatRupiah(i.harga) + '</span></div>'
                + kontrol + '</div></li>';
        }).join('');
    }

    el.total.textContent = formatRupiah(totalBelanja());
    el.inputKeranjang.value = JSON.stringify(keranjang.map(function (i) { return { id: i.id, qty: i.qty }; }));
    el.tombolBayar.disabled = keranjang.length === 0 || !pesananOnlineValid;
    hitungKembalian();
}

el.daftarKeranjang.addEventListener('click', function (event) {
    const tombol = event.target.closest('[data-ubah]');
    if (tombol) ubahJumlah(Number(tombol.dataset.id), Number(tombol.dataset.ubah));
});

document.getElementById('tombolKosongkan').addEventListener('click', function () {
    if (keranjang.length && confirm('Kosongkan keranjang?')) {
        keranjang = [];
        tampilkanKeranjang();
    }
});

/* ---------- Pembayaran ---------- */
function metodeBayar() {
    return document.querySelector('input[name="metode_pembayaran"]:checked').value;
}

function hitungKembalian() {
    const selisih = (Number(el.inputUang.value) || 0) - totalBelanja();
    el.kembalian.textContent = selisih >= 0 ? formatRupiah(selisih) : 'Kurang ' + formatRupiah(-selisih);
    el.kembalian.className = selisih >= 0 ? 'text-success' : 'text-danger';
}

document.querySelectorAll('input[name="metode_pembayaran"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
        const metode = metodeBayar();
        document.getElementById('panelTunai').classList.toggle('d-none', metode !== 'tunai');
        document.getElementById('panelQris').classList.toggle('d-none', metode !== 'qris');
        document.getElementById('panelUtang').classList.toggle('d-none', metode !== 'utang');
    });
});

el.inputUang.addEventListener('input', hitungKembalian);

document.getElementById('tombolUangPas').addEventListener('click', function () {
    el.inputUang.value = totalBelanja();
    hitungKembalian();
});

document.querySelectorAll('[data-uang]').forEach(function (tombol) {
    tombol.addEventListener('click', function () {
        el.inputUang.value = tombol.dataset.uang;
        hitungKembalian();
    });
});

el.form.addEventListener('submit', function (event) {
    if (metodeBayar() === 'tunai' && (Number(el.inputUang.value) || 0) < totalBelanja()) {
        event.preventDefault();
        alert('Uang tunai yang diterima kurang dari total belanja.');
        el.inputUang.focus();
    }
});

const pilihPelanggan = document.getElementById('pilihPelanggan');
if (pesananOnline) {
    keranjang = (pesananOnline.items || []).map(function (item) {
        const produk = semuaProduk.find(function (p) { return p.id === Number(item.id); });
        const qty = Number(item.qty);
        if (!produk || qty > Number(produk.stok)) pesananOnlineValid = false;
        return {
            id: Number(item.id), nama: produk ? produk.nama_produk : item.nama,
            harga: Number(item.harga), stok: produk ? Number(produk.stok) : 0,
            foto: produk ? produk.foto : '', qty: qty
        };
    });
    pilihPelanggan.disabled = true;
    document.getElementById('tombolKosongkan').classList.add('d-none');
    const metodeUtang = document.querySelector('input[name="metode_pembayaran"][value="utang"]');
    metodeUtang.disabled = true;
    document.querySelector('label[for="' + metodeUtang.id + '"]').classList.add('d-none');
    const metodePesanan = String(pesananOnline.metode || '').toLowerCase().includes('qris')
        ? document.querySelector('input[name="metode_pembayaran"][value="qris"]')
        : document.querySelector('input[name="metode_pembayaran"][value="tunai"]');
    if (metodePesanan) {
        metodePesanan.checked = true;
        metodePesanan.dispatchEvent(new Event('change'));
    }
    if (!pesananOnlineValid) {
        alert('Stok salah satu barang pesanan tidak mencukupi. Perbarui stok sebelum menyelesaikan transaksi.');
    }
}

/* ---------- Pelanggan ---------- */
const panelPelangganBaru = document.getElementById('panelPelangganBaru');
const inputNamaBaru = panelPelangganBaru.querySelector('[name="nama_pelanggan_baru"]');

pilihPelanggan.addEventListener('change', function () {
    const baru = pilihPelanggan.value === 'baru';
    panelPelangganBaru.classList.toggle('d-none', !baru);
    inputNamaBaru.required = baru;
    if (baru) inputNamaBaru.focus();
});

// Kasbon wajib atas nama pelanggan (kasir juga memeriksa ulang di server)
el.form.addEventListener('submit', function (event) {
    if (metodeBayar() === 'utang' && pilihPelanggan.value === '') {
        event.preventDefault();
        alert('Untuk kasbon, pilih pelanggan atau daftarkan pelanggan baru.');
        pilihPelanggan.focus();
    }
});

tampilkanProduk();
tampilkanKeranjang();
