// assets/js/restock.js
// Halaman kulakan: isi harga & satuan otomatis saat produk dipilih, hitung total biaya.

const pilihProduk = document.getElementById('produkId');
const inputQty = document.getElementById('qtyMasuk');
const inputHarga = document.getElementById('hargaBeli');

function hitungTotal() {
    const total = (Number(inputQty.value) || 0) * (Number(inputHarga.value) || 0);
    document.getElementById('totalKulakan').textContent = formatRupiah(total);
}

function produkDipilih() {
    const opsi = pilihProduk.options[pilihProduk.selectedIndex];
    if (!opsi || !opsi.value) return;

    inputHarga.value = opsi.dataset.harga;
    document.getElementById('teksSatuan').textContent = opsi.dataset.satuan;
    hitungTotal();
}

pilihProduk.addEventListener('change', produkDipilih);
inputQty.addEventListener('input', hitungTotal);
inputHarga.addEventListener('input', hitungTotal);

produkDipilih();   // jika halaman dibuka dari tombol "Kulakan" di Data Produk
