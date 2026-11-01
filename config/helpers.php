<?php
// config/helpers.php - Fungsi-fungsi bantuan (formatting, alert, date, assets)

function rupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

function tgl_indo($tanggal, $with_time = false) {
    if (!$tanggal || $tanggal === '0000-00-00' || $tanggal === '0000-00-00 00:00:00') {
        return '-';
    }
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $timestamp = strtotime($tanggal);
    $d = date('d', $timestamp);
    $m = $bulan[(int)date('m', $timestamp)];
    $y = date('Y', $timestamp);
    
    $hasil = "$d $m $y";
    if ($with_time) {
        $hasil .= ' ' . date('H:i', $timestamp) . ' WIB';
    }
    return $hasil;
}

function get_pengaturan($koneksi) {
    $res = $koneksi->query("SELECT * FROM pengaturan LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        return $row;
    }
    return [
        'nama_toko' => 'Warung Sembako Toko Najwa',
        'pemilik' => 'Ibu Dhiyah',
        'telepon' => '085712345678',
        'alamat' => 'Jaten, Bonomerto Rt.02 Rw.04 Kec. Suruh, Kab. Semarang',
        'teks_footer_struk' => 'Terima kasih atas kunjungan Anda!',
        'qris_image' => ''
    ];
}

// Cek status kadaluarsa
function cek_status_kadaluarsa($tgl_kadaluarsa) {
    if (!$tgl_kadaluarsa || $tgl_kadaluarsa === '0000-00-00') {
        return ['status' => 'normal', 'label' => 'Aman', 'badge' => 'secondary', 'hari' => null];
    }
    
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    $exp = new DateTime($tgl_kadaluarsa);
    $exp->setTime(0, 0, 0);
    
    $diff = $today->diff($exp);
    $is_past = $exp < $today;
    $days = $is_past ? -$diff->days : $diff->days;
    
    if ($is_past) {
        return ['status' => 'expired', 'label' => 'Kadaluarsa!', 'badge' => 'danger', 'hari' => $days];
    } elseif ($days <= 30) {
        return ['status' => 'warning', 'label' => $days . ' hari lagi', 'badge' => 'warning text-dark', 'hari' => $days];
    } else {
        return ['status' => 'safe', 'label' => 'Aman (' . date('d/m/Y', strtotime($tgl_kadaluarsa)) . ')', 'badge' => 'success', 'hari' => $days];
    }
}

// Helper ikon ilustrasi produk
function get_product_svg($nama, $kategori = '') {
    $n = strtolower($nama);
    $k = strtolower($kategori);

    if (strpos($n, 'minyak') !== false) return 'items/minyak.svg';
    if (strpos($n, 'beras') !== false) return 'items/beras.svg';
    if (strpos($n, 'telur') !== false) return 'items/telur.svg';
    if (strpos($n, 'gula') !== false || strpos($n, 'tepung') !== false) return 'items/gula.svg';
    if (strpos($n, 'royco') !== false || strpos($n, 'masako') !== false || strpos($n, 'garam') !== false || strpos($n, 'kecap') !== false || strpos($n, 'saus') !== false || strpos($n, 'lada') !== false || strpos($k, 'bumbu') !== false) return 'items/bumbu.svg';
    if (strpos($n, 'kopi') !== false || strpos($n, 'teh') !== false || strpos($n, 'susu') !== false || strpos($n, 'aqua') !== false || strpos($n, 'mineral') !== false || strpos($k, 'minuman') !== false) return 'items/minuman.svg';
    if (strpos($n, 'mie') !== false || strpos($n, 'indomie') !== false || strpos($n, 'sedaap') !== false || strpos($n, 'biskuit') !== false || strpos($k, 'snack') !== false || strpos($k, 'makanan') !== false) return 'items/mie.svg';
    if (strpos($n, 'sabun') !== false || strpos($n, 'daia') !== false || strpos($n, 'sunlight') !== false || strpos($n, 'pepsodent') !== false || strpos($k, 'rumah tangga') !== false) return 'items/sabun.svg';

    return 'items/default.svg';
}

// Flash message
function set_flash($type, $message) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function show_flash() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        $alert_type = ($f['type'] === 'success') ? 'alert-success' : (($f['type'] === 'error' || $f['type'] === 'danger') ? 'alert-danger' : 'alert-info');
        echo '<div class="alert ' . $alert_type . ' alert-dismissible fade show rounded-4 shadow-sm" role="alert">
                ' . htmlspecialchars($f['message']) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
    }
}
