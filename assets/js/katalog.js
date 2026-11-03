// assets/js/katalog.js
// Keranjang belanja di website publik. Isi keranjang disimpan di browser (localStorage)
// dan dikirim sebagai pesan WhatsApp ke pemilik toko (nomor ada di atribut data-wa pada <body>).

const KUNCI_SIMPAN = 'tokonajwa_keranjang';
let keranjang = [];   // [{id, nama, harga, satuan, foto, qty}]

try {
    keranjang = JSON.parse(localStorage.getItem(KUNCI_SIMPAN)) || [];
} catch (e) {
    keranjang = [];
}

function rupiah(angka) {
    return 'Rp ' + Number(angka || 0).toLocaleString('id-ID');
}

function escapeHtml(teks) {
    const div = document.createElement('div');
    div.textContent = teks == null ? '' : String(teks);
    return div.innerHTML.replace(/"/g, '&quot;');
}

function simpan() {
    localStorage.setItem(KUNCI_SIMPAN, JSON.stringify(keranjang));
    tampilkan();
}

function tampilkan() {
    const daftar = document.getElementById('daftarKeranjang');
    const jumlah = keranjang.reduce(function (n, i) { return n + i.qty; }, 0);
    const total = keranjang.reduce(function (n, i) { return n + i.harga * i.qty; }, 0);

    document.getElementById('lencanaKeranjang').textContent = jumlah;
    document.getElementById('totalKeranjang').textContent = rupiah(total);
    document.getElementById('tombolPesan').disabled = keranjang.length === 0;

    if (keranjang.length === 0) {
        daftar.innerHTML = '<li class="list-group-item text-center text-body-secondary py-5">Keranjang masih kosong.<br>Pilih sembako di katalog.</li>';
        return;
    }

    daftar.innerHTML = keranjang.map(function (i) {
        return '<li class="list-group-item px-0">'
            + '<div class="d-flex align-items-center">'
            + '<img src="' + escapeHtml(i.foto) + '" alt="" class="foto-produk-sm me-2">'
            + '<div class="me-auto small"><div class="fw-semibold">' + escapeHtml(i.nama) + '</div>'
            + '<span class="text-body-secondary">' + rupiah(i.harga) + '</span></div>'
            + '<div class="btn-group btn-group-sm">'
            + '<button type="button" class="btn btn-outline-secondary" data-ubah="-1" data-id="' + i.id + '">&minus;</button>'
            + '<span class="btn btn-outline-secondary disabled">' + i.qty + '</span>'
            + '<button type="button" class="btn btn-outline-secondary" data-ubah="1" data-id="' + i.id + '">+</button>'
            + '</div></div></li>';
    }).join('');
}

// Tombol "Tambah" pada kartu produk
document.querySelectorAll('.tombol-tambah').forEach(function (tombol) {
    tombol.addEventListener('click', function () {
        const id = Number(tombol.dataset.id);
        const item = keranjang.find(function (i) { return i.id === id; });

        if (item) {
            item.qty++;
        } else {
            keranjang.push({
                id: id, nama: tombol.dataset.nama, harga: Number(tombol.dataset.harga),
                satuan: tombol.dataset.satuan, foto: tombol.dataset.foto, qty: 1
            });
        }
        simpan();
    });
});

// Tombol + dan - di dalam keranjang
document.getElementById('daftarKeranjang').addEventListener('click', function (event) {
    const tombol = event.target.closest('[data-ubah]');
    if (!tombol) return;

    const id = Number(tombol.dataset.id);
    const item = keranjang.find(function (i) { return i.id === id; });
    if (!item) return;

    item.qty += Number(tombol.dataset.ubah);
    if (item.qty <= 0) {
        keranjang = keranjang.filter(function (i) { return i.id !== id; });
    }
    simpan();
});

document.getElementById('tombolKosongkan').addEventListener('click', function () {
    if (keranjang.length && confirm('Kosongkan keranjang belanja?')) {
        keranjang = [];
        simpan();
    }
});

// Kirim pesanan ke WhatsApp
document.getElementById('tombolPesan').addEventListener('click', function () {
    const nama = document.getElementById('namaPemesan').value.trim();
    const alamat = document.getElementById('alamatPemesan').value.trim();
    const layanan = document.querySelector('input[name="layanan"]:checked').value;
    const bayar = document.querySelector('input[name="bayar"]:checked').value;

    if (!nama) return alert('Silakan isi nama Anda.');
    if (layanan === 'Antar ke Rumah' && !alamat) return alert('Silakan isi alamat pengantaran.');

    let total = 0;
    let pesan = '*PESANAN SEMBAKO - TOKO NAJWA*\n\nHalo, saya mau pesan:\n';
    keranjang.forEach(function (i, urutan) {
        const subtotal = i.harga * i.qty;
        total += subtotal;
        pesan += (urutan + 1) + '. *' + i.nama + '*\n   ' + i.qty + ' ' + i.satuan + ' x ' + rupiah(i.harga) + ' = ' + rupiah(subtotal) + '\n';
    });
    pesan += '\n*TOTAL: ' + rupiah(total) + '*\n------------------------\n';
    pesan += '*Nama:* ' + nama + '\n';
    pesan += '*Layanan:* ' + layanan + '\n';
    if (alamat) pesan += '*Alamat:* ' + alamat + '\n';
    pesan += '*Pembayaran:* ' + bayar + '\n\nTerima kasih!';

    const nomor = document.body.dataset.wa;
    window.open('https://wa.me/' + nomor + '?text=' + encodeURIComponent(pesan), '_blank');
});

tampilkan();
