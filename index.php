<?php
// index.php - Web Utama Toko Najwa (Design Reference: Image 2 Retail UI)
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/config/helpers.php';

$pengaturan = get_pengaturan($koneksi);

// Kategori Produk
$kategori_list = [];
$res_kat = $koneksi->query("SELECT * FROM kategori ORDER BY id ASC");
while ($row = $res_kat->fetch_assoc()) {
    $kategori_list[] = $row;
}

// Filter Produk
$kategori_filter = isset($_GET['kategori']) ? (int)$_GET['kategori'] : 0;
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

$sql = "SELECT p.*, k.nama_kategori FROM produk p JOIN kategori k ON p.kategori_id = k.id WHERE p.stok > 0";
$params = [];
$types = "";

if ($kategori_filter > 0) {
    $sql .= " AND p.kategori_id = ?";
    $params[] = $kategori_filter;
    $types .= "i";
}

if (!empty($search)) {
    $sql .= " AND (p.nama_produk LIKE ? OR p.kode_barcode LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}

$sql .= " ORDER BY p.nama_produk ASC";

$stmt = $koneksi->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$produk_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pengaturan['nama_toko']); ?> - Belanja Sembako Warga</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body>

<div class="retail-window-wrapper">
    <!-- Slim Left Navigation Rail (Like Image 2 Left Sidebar) -->
    <aside class="retail-sidebar-rail">
        <a href="index.php" class="rail-logo-badge" title="<?= htmlspecialchars($pengaturan['nama_toko']); ?>">
            <img src="assets/img/logo.svg" alt="Toko Najwa Logo">
        </a>

        <ul class="rail-nav-list">
            <li>
                <a href="index.php" class="rail-btn active" title="Katalog Sembako">
                    <i class="bi bi-house-door"></i>
                </a>
            </li>
            <li>
                <a href="#katalog" class="rail-btn" title="Pilihan Sembako">
                    <i class="bi bi-grid"></i>
                </a>
            </li>
            <li>
                <a href="#lokasiModal" data-bs-toggle="modal" class="rail-btn" title="Lokasi & Jam Buka">
                    <i class="bi bi-geo-alt"></i>
                </a>
            </li>
            <li>
                <a href="#qrisModal" data-bs-toggle="modal" class="rail-btn" title="QRIS Toko Najwa">
                    <i class="bi bi-qr-code"></i>
                </a>
            </li>
            <li>
                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $pengaturan['telepon']); ?>" target="_blank" class="rail-btn" title="WhatsApp Bu Dhiyah">
                    <i class="bi bi-whatsapp"></i>
                </a>
            </li>
        </ul>

        <!-- Hidden Discreet Admin Link at Bottom (Only admin/presenter knows) -->
        <div class="mt-auto d-none d-lg-block">
            <a href="login.php" class="rail-btn" title="Akses Kasir" style="opacity: 0.35;">
                <i class="bi bi-shield-lock" style="font-size: 1rem;"></i>
            </a>
        </div>
    </aside>

    <!-- Main Workspace Area -->
    <div class="retail-main-area">
        <!-- Catalog Pane (Like Image 2 Left & Center) -->
        <div class="retail-catalog-pane" id="katalog">
            <!-- Topbar (Date & Store Title & Search) -->
            <div class="pane-topbar">
                <div>
                    <div class="topbar-date"><?= tgl_indo(date('Y-m-d')); ?></div>
                    <h2 class="topbar-title"><?= htmlspecialchars($pengaturan['nama_toko']); ?></h2>
                </div>

                <form method="GET" action="index.php" class="d-flex align-items-center">
                    <?php if ($kategori_filter > 0): ?>
                        <input type="hidden" name="kategori" value="<?= $kategori_filter; ?>">
                    <?php endif; ?>
                    <div class="search-pill-box">
                        <i class="bi bi-search text-muted"></i>
                        <input type="text" name="q" placeholder="Cari sembako di sini..." value="<?= htmlspecialchars($search); ?>">
                    </div>
                </form>
            </div>

            <!-- Section Headline & View All -->
            <div class="section-headline-row">
                <h4 class="section-headline">Pilihan Sembako Terbaik</h4>
                <a href="index.php" class="text-decoration-none small fw-bold" style="color: var(--primary-green);">Lihat Semua</a>
            </div>

            <!-- Category Pills Bar (Horizontal Scrollable like Image 2) -->
            <div class="category-pills-bar">
                <a href="index.php?q=<?= urlencode($search); ?>" class="category-pill-btn <?= ($kategori_filter == 0) ? 'active' : ''; ?>">
                    Semua
                </a>
                <?php foreach ($kategori_list as $kat): ?>
                    <a href="index.php?kategori=<?= $kat['id']; ?>&q=<?= urlencode($search); ?>" class="category-pill-btn <?= ($kategori_filter == $kat['id']) ? 'active' : ''; ?>">
                        <?= htmlspecialchars($kat['nama_kategori']); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Product Cards Grid (Like Image 2 Food Cards) -->
            <div class="row g-3">
                <?php if (empty($produk_list)): ?>
                    <div class="col-12 py-5 text-center">
                        <div class="p-5 bg-white rounded-4 border text-center max-w-500 mx-auto">
                            <i class="bi bi-basket3 fs-1 text-muted d-block mb-2"></i>
                            <h5 class="fw-bold text-dark">Sembako Tidak Ditemukan</h5>
                            <p class="text-muted small">Silakan cari nama sembako lainnya.</p>
                            <a href="index.php" class="btn btn-add-to-cart px-4 py-2">Tampilkan Semua</a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($produk_list as $p): 
                        $svg_icon = 'assets/img/' . get_product_svg($p['nama_produk'], $p['nama_kategori']);
                    ?>
                        <div class="col-xl-4 col-md-6">
                            <div class="retail-product-card">
                                <span class="product-wish-badge" onclick="this.classList.toggle('text-danger'); this.classList.toggle('bi-heart-fill'); this.classList.toggle('bi-heart');">
                                    <i class="bi bi-heart"></i>
                                </span>

                                <div>
                                    <div class="product-thumb-container">
                                        <img src="<?= $svg_icon; ?>" alt="<?= htmlspecialchars($p['nama_produk']); ?>">
                                    </div>

                                    <div class="product-card-title-row">
                                        <h6 class="product-card-name"><?= htmlspecialchars($p['nama_produk']); ?></h6>
                                        <div class="product-card-price"><?= rupiah($p['harga_jual']); ?></div>
                                    </div>

                                    <div class="product-card-desc">
                                        Kategori: <?= htmlspecialchars($p['nama_kategori']); ?> • Satuan kemasan: 1 <?= htmlspecialchars($p['satuan']); ?>. Stok segar harian.
                                    </div>
                                </div>

                                <div class="product-card-footer">
                                    <div class="product-rating">
                                        <i class="bi bi-star-fill"></i>
                                        <span>5.0</span>
                                    </div>
                                    <button type="button" class="btn-add-to-cart" onclick="addToOrder(<?= $p['id']; ?>, '<?= addslashes(htmlspecialchars($p['nama_produk'])); ?>', <?= $p['harga_jual']; ?>, '<?= addslashes(htmlspecialchars($p['satuan'])); ?>', '<?= $svg_icon; ?>')">
                                        <i class="bi bi-plus"></i> Add to cart
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Order Panel / "My Order" (Exact Match to Image 2 Right Side) -->
        <aside class="retail-order-pane">
            <div class="order-header-row">
                <h4 class="order-header-title">Pesanan Saya</h4>
                <button type="button" class="btn btn-sm text-danger p-0" onclick="clearCart()" title="Kosongkan Keranjang">
                    <i class="bi bi-x-circle fs-5"></i>
                </button>
            </div>

            <!-- Service Mode Segmented Buttons (Dine In / Pick Up / Delivery) -->
            <div class="order-segmented-pills">
                <button type="button" class="order-seg-btn active" id="segAntar" onclick="setServiceType('Antar ke Rumah')">Antar Rumah</button>
                <button type="button" class="order-seg-btn" id="segAmbil" onclick="setServiceType('Ambil di Toko')">Ambil Sendiri</button>
            </div>

            <!-- Scrollable Items List -->
            <div class="order-items-scrollbox" id="cartItemList">
                <!-- Injected by JavaScript -->
            </div>

            <!-- Price Breakdown Calculation -->
            <div class="order-pricing-box">
                <div class="order-price-row">
                    <span>Jumlah Belanja (<span id="totalItemCount">0</span> Item)</span>
                    <span id="subtotalDisplay">Rp 0</span>
                </div>
                <div class="order-price-row">
                    <span>Ongkir Dusun Jaten</span>
                    <span class="text-success fw-bold">GRATIS</span>
                </div>
                <div class="order-price-row total">
                    <span>Total Pembayaran</span>
                    <span class="price-val" id="grandTotalDisplay">Rp 0</span>
                </div>
            </div>

            <!-- Payments Method Pill Selector (Exact match to Image 2) -->
            <div class="order-payments-title">Pembayaran</div>
            <div class="order-payments-row">
                <div class="payment-type-pill active" id="payPillCash" onclick="setPaymentType('Tunai (Cash / COD)')">Cash</div>
                <div class="payment-type-pill" id="payPillQRIS" onclick="setPaymentType('QRIS Digital')">QRIS</div>
            </div>

            <!-- Customer Details Form -->
            <div class="order-customer-box">
                <input type="text" id="custName" class="order-input-field" placeholder="Nama Anda (contoh: Ibu Ani)">
                <input type="text" id="custAddress" class="order-input-field" placeholder="Alamat Rumah (contoh: Jaten RT 02)">
            </div>

            <!-- Giant Checkout Button (Exact match to Image 2) -->
            <button type="button" class="btn-checkout-giant" id="btnCheckout" onclick="submitToWhatsApp()" disabled>
                <span>Pesan Sekarang via WhatsApp</span>
                <i class="bi bi-arrow-right"></i>
            </button>
        </aside>
    </div>
</div>

<!-- Modal Info Lokasi -->
<div class="modal fade" id="lokasiModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 p-3">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold text-dark mb-0">Lokasi & Jam Buka</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="p-3 bg-light rounded-3 mb-3">
                    <strong class="d-block text-dark mb-1">Warung Sembako Toko Najwa</strong>
                    <div class="text-muted small mb-2"><?= htmlspecialchars($pengaturan['alamat']); ?></div>
                    <div class="text-dark small"><strong>Pemilik:</strong> <?= htmlspecialchars($pengaturan['pemilik']); ?></div>
                    <div class="text-dark small"><strong>WhatsApp:</strong> <?= htmlspecialchars($pengaturan['telepon']); ?></div>
                </div>
                <div class="p-3 bg-success bg-opacity-10 text-success rounded-3 small">
                    <i class="bi bi-clock me-1"></i> Buka Setiap Hari: 06.00 WIB - 21.00 WIB
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal QRIS -->
<div class="modal fade" id="qrisModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 p-3 text-center">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold text-dark w-100 mb-0">QRIS Resmi Toko Najwa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <img src="assets/img/qris.svg" alt="QRIS" class="img-fluid rounded-4 my-2" style="max-height: 260px;">
                <p class="text-muted small mb-0">Mendukung pembayaran dari BCA, BRI, Mandiri, GoPay, OVO, ShopeePay & DANA.</p>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script>
    let orderCart = [];
    let selectedService = 'Antar ke Rumah';
    let selectedPayment = 'Tunai (Cash / COD)';

    try {
        const saved = localStorage.getItem('tokonajwa_order_cart');
        if (saved) orderCart = JSON.parse(saved);
    } catch(e) { orderCart = []; }

    function saveOrder() {
        localStorage.setItem('tokonajwa_order_cart', JSON.stringify(orderCart));
        renderOrder();
    }

    function addToOrder(id, name, price, unit, img) {
        const idx = orderCart.findIndex(i => i.id === id);
        if (idx > -1) {
            orderCart[idx].qty++;
        } else {
            orderCart.push({ id, name, price, unit, img, qty: 1 });
        }
        saveOrder();
    }

    function changeQty(id, delta) {
        const idx = orderCart.findIndex(i => i.id === id);
        if (idx > -1) {
            orderCart[idx].qty += delta;
            if (orderCart[idx].qty <= 0) {
                orderCart.splice(idx, 1);
            }
            saveOrder();
        }
    }

    function removeOrderItem(id) {
        orderCart = orderCart.filter(i => i.id !== id);
        saveOrder();
    }

    function clearCart() {
        if (orderCart.length > 0 && confirm('Kosongkan keranjang belanja?')) {
            orderCart = [];
            saveOrder();
        }
    }

    function setServiceType(type) {
        selectedService = type;
        document.getElementById('segAntar').classList.toggle('active', type === 'Antar ke Rumah');
        document.getElementById('segAmbil').classList.toggle('active', type === 'Ambil di Toko');
    }

    function setPaymentType(type) {
        selectedPayment = type;
        document.getElementById('payPillCash').classList.toggle('active', type.includes('Tunai'));
        document.getElementById('payPillQRIS').classList.toggle('active', type.includes('QRIS'));
    }

    function formatRp(num) {
        return 'Rp ' + Number(num).toLocaleString('id-ID');
    }

    function renderOrder() {
        const container = document.getElementById('cartItemList');
        const countDisplay = document.getElementById('totalItemCount');
        const subtotalDisplay = document.getElementById('subtotalDisplay');
        const grandTotalDisplay = document.getElementById('grandTotalDisplay');
        const btnCheckout = document.getElementById('btnCheckout');

        const totalQty = orderCart.reduce((sum, item) => sum + item.qty, 0);
        const totalAmount = orderCart.reduce((sum, item) => sum + (item.price * item.qty), 0);

        countDisplay.textContent = totalQty;
        subtotalDisplay.textContent = formatRp(totalAmount);
        grandTotalDisplay.textContent = formatRp(totalAmount);

        btnCheckout.disabled = (orderCart.length === 0);

        if (orderCart.length === 0) {
            container.innerHTML = `
                <div class="py-5 text-center text-muted">
                    <i class="bi bi-cart3 fs-2 d-block mb-1 opacity-50"></i>
                    <small class="d-block fw-semibold">Keranjang Masih Kosong</small>
                    <small class="text-muted" style="font-size: 0.72rem;">Pilih sembako di sebelah kiri.</small>
                </div>
            `;
            return;
        }

        let html = '';
        orderCart.forEach(item => {
            const sub = item.price * item.qty;
            html += `
                <div class="order-item-card">
                    <div class="order-item-thumb">
                        <img src="${item.img}" alt="${item.name}">
                    </div>
                    <div class="order-item-info">
                        <h6 class="order-item-name">${item.name}</h6>
                        <span class="order-item-price">${formatRp(item.price)}</span>
                    </div>
                    <div class="order-item-qty-box">
                        <button type="button" class="order-qty-btn" onclick="changeQty(${item.id}, -1)">-</button>
                        <span class="order-qty-num">${item.qty < 10 ? '0' + item.qty : item.qty}</span>
                        <button type="button" class="order-qty-btn" onclick="changeQty(${item.id}, 1)">+</button>
                    </div>
                    <button type="button" class="order-item-trash" onclick="removeOrderItem(${item.id})" title="Hapus">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            `;
        });
        container.innerHTML = html;
    }

    function submitToWhatsApp() {
        if (orderCart.length === 0) {
            alert('Keranjang belanja Anda masih kosong!');
            return;
        }

        const name = document.getElementById('custName').value.trim();
        const address = document.getElementById('custAddress').value.trim();

        if (!name) {
            alert('Silakan tulis nama Anda!');
            document.getElementById('custName').focus();
            return;
        }
        if (!address) {
            alert('Silakan tulis alamat rumah Anda!');
            document.getElementById('custAddress').focus();
            return;
        }

        const ownerPhone = "<?= preg_replace('/[^0-9]/', '', $pengaturan['telepon']); ?>";
        let message = `*PESANAN BELANJA SEMBAKO - TOKO NAJWA*\n\n`;
        message += `Halo Bu Dhiyah, saya mau pesan sembako:\n`;
        message += `Layanan: *${selectedService}*\n\n`;

        let total = 0;
        orderCart.forEach((item, idx) => {
            const sub = item.price * item.qty;
            total += sub;
            message += `${idx + 1}. *${item.name}*\n   ${item.qty} ${item.unit} x ${formatRp(item.price)} = ${formatRp(sub)}\n`;
        });

        message += `\n*TOTAL PEMBAYARAN: ${formatRp(total)}*\n`;
        message += `-------------------------\n`;
        message += `*Pemesan:* ${name}\n`;
        message += `*Alamat:* ${address}\n`;
        message += `*Metode Bayar:* ${selectedPayment}\n\n`;
        message += `Mohon segera diantar ya Bu Dhiyah. Terima kasih banyak!`;

        const waUrl = `https://wa.me/${ownerPhone}?text=${encodeURIComponent(message)}`;
        window.open(waUrl, '_blank');
    }

    renderOrder();
</script>

</body>
</html>
