<?php
// admin/pos.php - Kasir POS Retail (Design Reference: Image 2)
$page_title = 'Kasir Penjualan';
require_once __DIR__ . '/header.php';

// Ambil semua produk yang stoknya > 0
$produk_data = [];
$res_p = $koneksi->query("SELECT p.*, k.nama_kategori 
                          FROM produk p 
                          JOIN kategori k ON p.kategori_id = k.id 
                          WHERE p.stok > 0 
                          ORDER BY p.nama_produk ASC");
while ($r = $res_p->fetch_assoc()) {
    $r['img_svg'] = '../assets/img/' . get_product_svg($r['nama_produk'], $r['nama_kategori']);
    $produk_data[] = $r;
}

// Ambil kategori untuk filter pills
$kategori_list = [];
$res_k = $koneksi->query("SELECT * FROM kategori ORDER BY id ASC");
while ($rk = $res_k->fetch_assoc()) {
    $kategori_list[] = $rk;
}

// Handle Checkout
$trx_success = false;
$trx_invoice = '';
$trx_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'simpan_transaksi') {
    $cart_json = $_POST['cart_data'] ?? '[]';
    $items = json_decode($cart_json, true);
    
    $nama_pelanggan = trim($_POST['nama_pelanggan'] ?? 'Warga / Umum');
    if (empty($nama_pelanggan)) $nama_pelanggan = 'Warga / Umum';
    
    $metode_bayar = $_POST['metode_pembayaran'] ?? 'tunai';
    $total_bayar = (float)($_POST['nominal_bayar'] ?? 0);
    $catatan = trim($_POST['catatan_transaksi'] ?? '');
    
    if (empty($items)) {
        $trx_error = 'Keranjang belanja kasir masih kosong!';
    } else {
        $koneksi->begin_transaction();
        try {
            $total_harga = 0;
            $items_to_save = [];

            foreach ($items as $item) {
                $pid = (int)$item['id'];
                $qty = (int)$item['qty'];
                if ($qty <= 0) continue;

                $stmt = $koneksi->prepare("SELECT id, nama_produk, harga_beli, harga_jual, stok FROM produk WHERE id = ? FOR UPDATE");
                $stmt->bind_param("i", $pid);
                $stmt->execute();
                $prod = $stmt->get_result()->fetch_assoc();

                if (!$prod) throw new Exception("Produk tidak ditemukan.");
                if ($prod['stok'] < $qty) {
                    throw new Exception("Stok untuk " . $prod['nama_produk'] . " tidak mencukupi (sisa: " . $prod['stok'] . ").");
                }

                $subtotal = $prod['harga_jual'] * $qty;
                $keuntungan = ($prod['harga_jual'] - $prod['harga_beli']) * $qty;
                $total_harga += $subtotal;

                $items_to_save[] = [
                    'id' => $prod['id'],
                    'harga_beli' => $prod['harga_beli'],
                    'harga_jual' => $prod['harga_jual'],
                    'qty' => $qty,
                    'subtotal' => $subtotal,
                    'keuntungan' => $keuntungan
                ];
            }

            if (empty($items_to_save)) throw new Exception("Tidak ada barang valid.");

            $kembalian = 0;
            $status_bayar = 'lunas';

            if ($metode_bayar === 'tunai') {
                if ($total_bayar < $total_harga) {
                    throw new Exception("Nominal uang tunai kurang dari total belanja!");
                }
                $kembalian = $total_bayar - $total_harga;
            } elseif ($metode_bayar === 'qris') {
                $total_bayar = $total_harga;
                $kembalian = 0;
            } elseif ($metode_bayar === 'utang') {
                $status_bayar = 'belum_lunas';
                $kembalian = 0;
            }

            $today_prefix = 'TRX-' . date('Ymd') . '-';
            $res_count = $koneksi->query("SELECT COUNT(*) FROM transaksi WHERE no_faktur LIKE '$today_prefix%'");
            $next_seq = (int)$res_count->fetch_row()[0] + 1;
            $no_faktur = $today_prefix . str_pad($next_seq, 4, '0', STR_PAD_LEFT);

            $user_id = $user['id'];
            $stmt_t = $koneksi->prepare("INSERT INTO transaksi (no_faktur, tanggal, user_id, nama_pelanggan, total_harga, total_bayar, kembalian, metode_pembayaran, status_pembayaran, catatan) VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_t->bind_param("sisdddsss", $no_faktur, $user_id, $nama_pelanggan, $total_harga, $total_bayar, $kembalian, $metode_bayar, $status_bayar, $catatan);
            $stmt_t->execute();
            $transaksi_id = $koneksi->insert_id;

            $stmt_d = $koneksi->prepare("INSERT INTO transaksi_detail (transaksi_id, produk_id, harga_beli, harga_jual, qty, subtotal, keuntungan) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_upd_stok = $koneksi->prepare("UPDATE produk SET stok = stok - ? WHERE id = ?");

            foreach ($items_to_save as $its) {
                $stmt_d->bind_param("iiddddd", $transaksi_id, $its['id'], $its['harga_beli'], $its['harga_jual'], $its['qty'], $its['subtotal'], $its['keuntungan']);
                $stmt_d->execute();

                $stmt_upd_stok->bind_param("ii", $its['qty'], $its['id']);
                $stmt_upd_stok->execute();
            }

            if ($metode_bayar === 'utang') {
                $no_telp = trim($_POST['telepon_pelanggan'] ?? '');
                $jatuh_tempo = !empty($_POST['jatuh_tempo']) ? $_POST['jatuh_tempo'] : date('Y-m-d', strtotime('+14 days'));
                $sisa_utang = $total_harga;

                $stmt_p = $koneksi->prepare("INSERT INTO piutang (transaksi_id, nama_pelanggan, no_telepon, total_utang, sisa_utang, tgl_utang, tgl_jatuh_tempo, status, catatan) VALUES (?, ?, ?, ?, ?, CURDATE(), ?, 'belum_lunas', ?)");
                $stmt_p->bind_param("issddss", $transaksi_id, $nama_pelanggan, $no_telp, $total_harga, $sisa_utang, $jatuh_tempo, $catatan);
                $stmt_p->execute();
            }

            $koneksi->commit();
            $trx_success = true;
            $trx_invoice = $no_faktur;
        } catch (Exception $e) {
            $koneksi->rollback();
            $trx_error = $e->getMessage();
        }
    }
}
?>

<!-- Modal Sukses Transaksi -->
<?php if ($trx_success): ?>
<div class="modal fade show" id="modalTrxSuccess" tabindex="-1" style="display: block; background: rgba(15,23,42,0.4);" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 text-center p-4">
            <div class="my-3">
                <div class="mx-auto bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center" style="width: 72px; height: 72px; font-size: 2.2rem;">
                    <i class="bi bi-check-lg"></i>
                </div>
            </div>
            <h4 class="fw-bold text-dark mb-1">Transaksi Selesai!</h4>
            <p class="text-muted small mb-3">Faktur: <strong class="text-dark font-monospace"><?= htmlspecialchars($trx_invoice); ?></strong></p>
            <div class="d-grid gap-2">
                <a href="cetak_struk.php?faktur=<?= urlencode($trx_invoice); ?>" target="_blank" class="btn btn-pill-green py-2">
                    <i class="bi bi-printer me-1"></i> Cetak Struk Nota
                </a>
                <a href="pos.php" class="btn btn-pill-white py-2">
                    Transaksi Baru
                </a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($trx_error)): ?>
    <div class="alert alert-danger py-2 rounded-4 mb-3 d-flex align-items-center">
        <i class="bi bi-exclamation-circle-fill me-2 fs-5"></i>
        <span><?= htmlspecialchars($trx_error); ?></span>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Kolom Tengah: Katalog & Filter Kategori (Like Image 2 Left Side) -->
    <div class="col-lg-8">
        <!-- Top Search & Barcode -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <div>
                <h5 class="fw-bold text-dark mb-0">Pilih Sembako</h5>
                <small class="text-muted">Klik produk atau scan barcode untuk menambah pesanan</small>
            </div>
            <div class="d-flex gap-2">
                <div class="header-search-bar" style="width: 260px;">
                    <i class="bi bi-search text-muted"></i>
                    <input type="text" id="searchBox" placeholder="Cari beras, minyak...">
                </div>
                <div class="header-search-bar" style="width: 170px;">
                    <i class="bi bi-upc-scan text-muted"></i>
                    <input type="text" id="barcodeBox" placeholder="Scan Barcode...">
                </div>
            </div>
        </div>

        <!-- Category Horizontal Pills (Like Image 2) -->
        <div class="category-pills-scroll mb-3">
            <button class="cat-pill active cat-filter-btn" data-cat="all">Semua</button>
            <?php foreach ($kategori_list as $kat): ?>
                <button class="cat-pill cat-filter-btn" data-cat="<?= $kat['id']; ?>">
                    <?= htmlspecialchars($kat['nama_kategori']); ?>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- Product Cards Grid (Like Image 2) -->
        <div class="row g-3" id="posProductGrid" style="max-height: 640px; overflow-y: auto;">
            <!-- Rendered by JS -->
        </div>
    </div>

    <!-- Kolom Kanan: "My Order" / Panel Kasir (Like Image 2 Right Side) -->
    <div class="col-lg-4">
        <div class="order-panel-card">
            <!-- Header My Order -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-dark mb-0">Pesanan Kasir</h5>
                <button type="button" class="btn btn-sm text-danger p-0" onclick="clearCart()" title="Bersihkan">
                    <i class="bi bi-trash fs-5"></i>
                </button>
            </div>

            <!-- Service Mode Segmented Buttons (Dine In / Delivery) -->
            <div class="order-type-segmented">
                <button type="button" class="order-type-btn active" id="btnModeToko" onclick="setServiceMode('toko')">Di Toko</button>
                <button type="button" class="order-type-btn" id="btnModeAntar" onclick="setServiceMode('antar')">Pesan Antar</button>
            </div>

            <!-- List Item Keranjang (Scrollable) -->
            <div style="flex-grow: 1; max-height: 250px; min-height: 160px; overflow-y: auto;" class="mb-3 pe-1" id="orderItemsContainer">
                <!-- Rendered by JS -->
            </div>

            <!-- Calculation Summary -->
            <div class="border-top pt-3 mb-3">
                <div class="d-flex justify-content-between text-muted small mb-1">
                    <span>Jumlah Item</span>
                    <span id="orderItemCount">0</span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <strong class="text-dark">Total Tagihan</strong>
                    <h4 class="fw-bold text-success mb-0" id="orderTotalAmount">Rp 0</h4>
                </div>
            </div>

            <!-- Checkout Form -->
            <form method="POST" action="pos.php" id="formPOSCheckout" onsubmit="return validatePOS()">
                <input type="hidden" name="action" value="simpan_transaksi">
                <input type="hidden" name="cart_data" id="cartDataInput">

                <!-- Nama Pelanggan -->
                <div class="mb-2">
                    <input type="text" name="nama_pelanggan" id="namaPelanggan" class="form-control form-control-sm rounded-pill px-3" placeholder="Nama Pelanggan (Warga / Umum)" value="Warga / Umum" required>
                </div>

                <!-- Payment Method Pills -->
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-muted mb-1">Metode Pembayaran</label>
                    <div class="d-flex gap-2">
                        <input type="radio" class="btn-check" name="metode_pembayaran" id="payTunai" value="tunai" checked onchange="switchPayMode('tunai')">
                        <label class="btn btn-outline-dark btn-sm rounded-pill flex-fill py-1 fw-semibold" for="payTunai">Cash</label>

                        <input type="radio" class="btn-check" name="metode_pembayaran" id="payQRIS" value="qris" onchange="switchPayMode('qris')">
                        <label class="btn btn-outline-dark btn-sm rounded-pill flex-fill py-1 fw-semibold" for="payQRIS">QRIS</label>

                        <input type="radio" class="btn-check" name="metode_pembayaran" id="payKasbon" value="utang" onchange="switchPayMode('utang')">
                        <label class="btn btn-outline-dark btn-sm rounded-pill flex-fill py-1 fw-semibold" for="payKasbon">Kasbon</label>
                    </div>
                </div>

                <!-- Tunai Options -->
                <div id="cashFields" class="mb-3">
                    <div class="d-flex gap-1 mb-2">
                        <button type="button" class="btn btn-sm btn-light border rounded-pill flex-fill small" onclick="setCashExact()">Uang Pas</button>
                        <button type="button" class="btn btn-sm btn-light border rounded-pill flex-fill small" onclick="setNominal(50000)">50 rb</button>
                        <button type="button" class="btn btn-sm btn-light border rounded-pill flex-fill small" onclick="setNominal(100000)">100 rb</button>
                    </div>
                    <div class="input-group input-group-sm mb-2">
                        <span class="input-group-text bg-white border-end-0">Rp</span>
                        <input type="number" name="nominal_bayar" id="nominalBayar" class="form-control border-start-0" placeholder="Uang diterima" oninput="calcChange()">
                    </div>
                    <div class="d-flex justify-content-between align-items-center small p-2 bg-light rounded-3">
                        <span class="text-muted">Kembalian:</span>
                        <strong id="changeDisplay" class="text-dark">Rp 0</strong>
                    </div>
                </div>

                <!-- QRIS Preview -->
                <div id="qrisFields" style="display:none;" class="text-center p-2 mb-3 bg-light rounded-3 border">
                    <img src="../assets/img/qris.svg" alt="QRIS" style="max-height: 120px;" class="img-fluid rounded">
                    <div class="small text-muted mt-1" style="font-size: 0.72rem;">Scan QRIS Toko Najwa</div>
                </div>

                <!-- Kasbon Fields -->
                <div id="kasbonFields" style="display:none;" class="p-2 mb-3 bg-warning bg-opacity-10 rounded-3 border border-warning">
                    <small class="d-block fw-bold text-dark mb-1">Catatan Kasbon Warga:</small>
                    <input type="text" name="telepon_pelanggan" class="form-control form-control-sm rounded-3 mb-1" placeholder="Nomor WhatsApp (Opsional)">
                    <input type="date" name="jatuh_tempo" class="form-control form-control-sm rounded-3" value="<?= date('Y-m-d', strtotime('+14 days')); ?>">
                </div>

                <!-- Main Checkout Button (Like Image 2) -->
                <button type="submit" class="btn btn-pill-green w-100 py-2 shadow-sm" id="btnPOSCheckout" disabled>
                    <span>Checkout (Selesaikan)</span>
                    <i class="bi bi-arrow-right"></i>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    const allProducts = <?= json_encode($produk_data); ?>;
    let posCart = [];
    let activeCat = 'all';

    function renderProducts() {
        const grid = document.getElementById('posProductGrid');
        const q = document.getElementById('searchBox').value.toLowerCase();

        const filtered = allProducts.filter(p => {
            const matchCat = (activeCat === 'all' || p.kategori_id == activeCat);
            const matchSearch = (p.nama_produk.toLowerCase().includes(q) || p.kode_barcode.toLowerCase().includes(q));
            return matchCat && matchSearch;
        });

        if (filtered.length === 0) {
            grid.innerHTML = '<div class="col-12 py-5 text-center text-muted"><i class="bi bi-search fs-2 d-block mb-1"></i>Tidak ada sembako cocok.</div>';
            return;
        }

        let html = '';
        filtered.forEach(p => {
            html += `
                <div class="col-xl-4 col-md-6">
                    <div class="product-card-retail" onclick="addToPOSCart(${p.id})">
                        <div>
                            <div class="product-img-frame">
                                <img src="${p.img_svg}" alt="${p.nama_produk}">
                            </div>
                            <h6 class="fw-bold text-dark mb-1" style="min-height: 38px; font-size: 0.9rem;">${p.nama_produk}</h6>
                            <small class="text-muted d-block mb-2">Satuan: 1 ${p.satuan} • Stok: ${p.stok}</small>
                        </div>
                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <span class="fw-bold text-success" style="font-size: 1rem;">${formatRp(p.harga_jual)}</span>
                            <button type="button" class="btn-add-cart">
                                <i class="bi bi-plus"></i> Tambah
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });
        grid.innerHTML = html;
    }

    function addToPOSCart(id) {
        const prod = allProducts.find(p => p.id === id);
        if (!prod) return;

        const idx = posCart.findIndex(item => item.id === id);
        if (idx > -1) {
            if (posCart[idx].qty < prod.stok) {
                posCart[idx].qty++;
            } else {
                alert('Stok tersisa: ' + prod.stok);
            }
        } else {
            posCart.push({
                id: prod.id,
                nama: prod.nama_produk,
                harga: parseFloat(prod.harga_jual),
                stok: parseInt(prod.stok),
                satuan: prod.satuan,
                img: prod.img_svg,
                qty: 1
            });
        }
        renderCart();
    }

    function updateQty(id, delta) {
        const idx = posCart.findIndex(item => item.id === id);
        if (idx > -1) {
            posCart[idx].qty += delta;
            if (posCart[idx].qty > posCart[idx].stok) {
                posCart[idx].qty = posCart[idx].stok;
                alert('Maksimal stok: ' + posCart[idx].stok);
            }
            if (posCart[idx].qty <= 0) {
                posCart.splice(idx, 1);
            }
            renderCart();
        }
    }

    function removeCartItem(id) {
        posCart = posCart.filter(item => item.id !== id);
        renderCart();
    }

    function clearCart() {
        if (posCart.length > 0 && confirm('Kosongkan keranjang kasir?')) {
            posCart = [];
            renderCart();
        }
    }

    function getCartTotal() {
        return posCart.reduce((sum, item) => sum + (item.harga * item.qty), 0);
    }

    function renderCart() {
        const container = document.getElementById('orderItemsContainer');
        const countDisplay = document.getElementById('orderItemCount');
        const totalDisplay = document.getElementById('orderTotalAmount');
        const cartInput = document.getElementById('cartDataInput');
        const checkoutBtn = document.getElementById('btnPOSCheckout');

        const totalItems = posCart.reduce((sum, item) => sum + item.qty, 0);
        const totalAmount = getCartTotal();

        countDisplay.textContent = totalItems + ' Item';
        totalDisplay.textContent = formatRp(totalAmount);
        cartInput.value = JSON.stringify(posCart);

        checkoutBtn.disabled = (posCart.length === 0);

        if (posCart.length === 0) {
            container.innerHTML = '<div class="py-4 text-center text-muted small"><i class="bi bi-cart3 fs-3 d-block mb-1"></i>Belum ada item belanja.</div>';
            calcChange();
            return;
        }

        let html = '';
        posCart.forEach(item => {
            const sub = item.harga * item.qty;
            html += `
                <div class="cart-item-row">
                    <div class="d-flex align-items-center" style="max-width: 150px;">
                        <div class="cart-item-img">
                            <img src="${item.img}" style="width: 28px; height: 28px; object-fit: contain;">
                        </div>
                        <div>
                            <strong class="d-block text-dark text-truncate small" style="max-width: 100px;">${item.nama}</strong>
                            <small class="text-muted" style="font-size: 0.75rem;">${formatRp(item.harga)}</small>
                        </div>
                    </div>
                    <div class="cart-qty-ctrl">
                        <button type="button" class="btn-qty" onclick="updateQty(${item.id}, -1)">-</button>
                        <span class="small fw-bold px-1">${item.qty}</span>
                        <button type="button" class="btn-qty" onclick="updateQty(${item.id}, 1)">+</button>
                    </div>
                    <div class="text-end">
                        <div class="small fw-bold text-dark">${formatRp(sub)}</div>
                        <button type="button" class="btn text-danger p-0" onclick="removeCartItem(${item.id})" style="font-size: 0.75rem;">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            `;
        });
        container.innerHTML = html;
        calcChange();
    }

    function switchPayMode(mode) {
        document.getElementById('cashFields').style.display = (mode === 'tunai') ? 'block' : 'none';
        document.getElementById('qrisFields').style.display = (mode === 'qris') ? 'block' : 'none';
        document.getElementById('kasbonFields').style.display = (mode === 'utang') ? 'block' : 'none';
        if (mode === 'tunai') document.getElementById('nominalBayar').focus();
    }

    function setCashExact() {
        document.getElementById('nominalBayar').value = getCartTotal();
        calcChange();
    }

    function setNominal(val) {
        document.getElementById('nominalBayar').value = val;
        calcChange();
    }

    function calcChange() {
        const total = getCartTotal();
        const bayar = parseFloat(document.getElementById('nominalBayar').value) || 0;
        const disp = document.getElementById('changeDisplay');

        if (bayar >= total) {
            disp.textContent = formatRp(bayar - total);
            disp.className = 'text-success fw-bold';
        } else {
            disp.textContent = 'Kurang ' + formatRp(total - bayar);
            disp.className = 'text-danger fw-bold';
        }
    }

    function setServiceMode(mode) {
        document.getElementById('btnModeToko').classList.toggle('active', mode === 'toko');
        document.getElementById('btnModeAntar').classList.toggle('active', mode === 'antar');
    }

    function validatePOS() {
        if (posCart.length === 0) {
            alert('Keranjang kasir masih kosong!');
            return false;
        }
        const mode = document.querySelector('input[name="metode_pembayaran"]:checked').value;
        const total = getCartTotal();
        const bayar = parseFloat(document.getElementById('nominalBayar').value) || 0;

        if (mode === 'tunai' && bayar < total) {
            alert('Nominal uang tunai kurang dari total tagihan!');
            document.getElementById('nominalBayar').focus();
            return false;
        }

        if (mode === 'utang') {
            const nama = document.getElementById('namaPelanggan').value.trim();
            if (!nama || nama === 'Warga / Umum') {
                alert('Untuk Kasbon, mohon cantumkan nama warga dengan jelas!');
                document.getElementById('namaPelanggan').focus();
                return false;
            }
        }
        return true;
    }

    function formatRp(num) {
        return 'Rp ' + Number(num).toLocaleString('id-ID');
    }

    // Event filters
    document.querySelectorAll('.cat-filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.cat-filter-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            activeCat = this.getAttribute('data-cat');
            renderProducts();
        });
    });

    document.getElementById('searchBox').addEventListener('input', renderProducts);

    document.getElementById('barcodeBox').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const val = this.value.trim();
            const prod = allProducts.find(p => p.kode_barcode === val);
            if (prod) {
                addToPOSCart(prod.id);
                this.value = '';
            } else {
                alert('Barcode tidak ditemukan.');
            }
        }
    });

    renderProducts();
    renderCart();
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
