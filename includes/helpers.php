<?php
// includes/helpers.php
// Fungsi bantu yang dipakai di banyak halaman. Dikelompokkan per kegunaan.

/* ------------------------------------------------------------
 * 1. QUERY DATABASE (prepared statement, aman dari SQL injection)
 *    $tipe  : jenis tiap parameter -> s = teks, i = angka bulat, d = desimal
 *    $param : nilai parameter, urutannya sama dengan tanda "?" di SQL
 * ------------------------------------------------------------ */

// Menjalankan query dan mengembalikan statement-nya.
function jalankan(string $sql, string $tipe = '', array $param = []): mysqli_stmt
{
    $stmt = db()->prepare($sql);
    if ($param) {
        $stmt->bind_param($tipe, ...$param);
    }
    $stmt->execute();
    return $stmt;
}

// Mengambil semua baris hasil query.
function ambil_semua(string $sql, string $tipe = '', array $param = []): array
{
    return jalankan($sql, $tipe, $param)->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Mengambil satu baris (atau null jika tidak ada).
function ambil_satu(string $sql, string $tipe = '', array $param = []): ?array
{
    return jalankan($sql, $tipe, $param)->get_result()->fetch_assoc() ?: null;
}

// Mengambil satu nilai saja, misalnya hasil COUNT(*) atau SUM(...).
function ambil_nilai(string $sql, string $tipe = '', array $param = [])
{
    $baris = jalankan($sql, $tipe, $param)->get_result()->fetch_row();
    return $baris[0] ?? null;
}

/* ------------------------------------------------------------
 * 2. INPUT & NAVIGASI
 * ------------------------------------------------------------ */

// Escape teks sebelum dicetak ke HTML (mencegah XSS).
function e($teks): string
{
    return htmlspecialchars((string) $teks, ENT_QUOTES, 'UTF-8');
}

// Alamat lengkap sebuah file project, contoh: url('assets/img/logo.png')
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $tujuan): never
{
    header('Location: ' . $tujuan);
    exit;
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

// Ambil nilai dari form POST (sudah di-trim).
function post(string $nama, string $bawaan = ''): string
{
    return trim((string) ($_POST[$nama] ?? $bawaan));
}

// Ambil nilai dari URL (?nama=...) (sudah di-trim).
function get(string $nama, string $bawaan = ''): string
{
    return trim((string) ($_GET[$nama] ?? $bawaan));
}

// Tanggal kosong dari form dikirim sebagai NULL ke database.
function tanggal_atau_null(string $nilai): ?string
{
    return $nilai !== '' ? $nilai : null;
}

/* ------------------------------------------------------------
 * 3. PESAN NOTIFIKASI (flash message) SETELAH SIMPAN / HAPUS
 * ------------------------------------------------------------ */

function set_flash(string $jenis, string $pesan): void
{
    $_SESSION['flash'] = ['jenis' => $jenis, 'pesan' => $pesan];
}

function tampil_flash(): void
{
    if (empty($_SESSION['flash'])) {
        return;
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);

    $kelas = $f['jenis'] === 'success' ? 'success' : 'danger';
    echo '<div class="alert alert-' . $kelas . ' alert-dismissible fade show" role="alert">'
        . e($f['pesan'])
        . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>'
        . '</div>';
}

/* ------------------------------------------------------------
 * 4. KEAMANAN FORM (CSRF)
 *    Setiap <form method="post"> wajib memanggil <?= csrf_field() ?>
 * ------------------------------------------------------------ */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_verifikasi(): void
{
    $kirim = $_POST['csrf'] ?? '';
    if (!is_string($kirim) || !hash_equals(csrf_token(), $kirim)) {
        http_response_code(403);
        exit('Sesi formulir tidak valid. Silakan kembali dan muat ulang halaman.');
    }
}

/* ------------------------------------------------------------
 * 5. FORMAT TAMPILAN
 * ------------------------------------------------------------ */

function rupiah($angka): string
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

function tanggal_indo(?string $tanggal, bool $dengan_jam = false): string
{
    if (!$tanggal || str_starts_with($tanggal, '0000')) {
        return '-';
    }
    $nama_bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                   'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $waktu = strtotime($tanggal);
    $hasil = date('d', $waktu) . ' ' . $nama_bulan[(int) date('n', $waktu)] . ' ' . date('Y', $waktu);

    return $dengan_jam ? $hasil . ' ' . date('H:i', $waktu) . ' WIB' : $hasil;
}

// Hanya angka (untuk nomor WhatsApp: 0857-123 -> 0857123). Awalan 0 diubah menjadi 62.
function nomor_wa(string $telepon): string
{
    $angka = preg_replace('/\D/', '', $telepon);
    return str_starts_with($angka, '0') ? '62' . substr($angka, 1) : $angka;
}

/* ------------------------------------------------------------
 * 6. DATA TOKO & PRODUK
 * ------------------------------------------------------------ */

function pengaturan_toko(): array
{
    static $cache = null;

    if ($cache === null) {
        $cache = ambil_satu('SELECT * FROM pengaturan LIMIT 1') ?? [
            'nama_toko'         => 'Warung Sembako Toko Najwa',
            'pemilik'           => 'Ibu Dhiyah',
            'telepon'           => '085712345678',
            'alamat'            => 'Jaten, Bonomerto Rt.02 Rw.04 Kec. Suruh, Kab. Semarang',
            'teks_footer_struk' => 'Terima kasih atas kunjungan Anda!',
            'qris_image'       => null,
        ];
    }
    return $cache;
}

// Status kadaluarsa produk: aman / segera (<= 30 hari) / kadaluarsa.
function status_kadaluarsa(?string $tanggal): array
{
    if (!$tanggal) {
        return ['kode' => 'tanpa', 'label' => 'Tanpa tanggal', 'badge' => 'text-bg-secondary'];
    }

    $sisa_hari = (int) floor((strtotime($tanggal) - strtotime(date('Y-m-d'))) / 86400);

    if ($sisa_hari < 0) {
        return ['kode' => 'kadaluarsa', 'label' => 'Kadaluarsa', 'badge' => 'text-bg-danger'];
    }
    if ($sisa_hari <= 30) {
        return ['kode' => 'segera', 'label' => $sisa_hari . ' hari lagi', 'badge' => 'text-bg-warning'];
    }
    return ['kode' => 'aman', 'label' => date('d/m/Y', strtotime($tanggal)), 'badge' => 'text-bg-success'];
}


/* ------------------------------------------------------------
 * 7. FOTO PRODUK & LOGO
 * ------------------------------------------------------------ */

const FOLDER_FOTO_PRODUK = 'assets/img/produk/';

// Alamat foto sebuah produk (kolom produk.gambar). Jika belum ada foto, dipakai gambar kosong.
function gambar_produk(?string $nama_file): string
{
    if ($nama_file && is_file(ROOT_PATH . '/' . FOLDER_FOTO_PRODUK . $nama_file)) {
        return url(FOLDER_FOTO_PRODUK . $nama_file);
    }
    return url('assets/img/produk-kosong.svg');
}

// Menyimpan foto yang diunggah lewat form. Mengembalikan nama file baru,
// null jika tidak ada file yang diunggah, atau melempar Exception jika file tidak valid.
function simpan_gambar(array $file, string $folder_relatif = FOLDER_FOTO_PRODUK, string $awalan = 'produk-'): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 2 * 1024 * 1024) {
        throw new Exception('Foto gagal diunggah (maksimal 2 MB).');
    }

    $jenis_diizinkan = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $jenis = mime_content_type($file['tmp_name']);
    if (!isset($jenis_diizinkan[$jenis])) {
        throw new Exception('Foto harus berformat JPG, PNG, atau WEBP.');
    }

    $folder = ROOT_PATH . '/' . $folder_relatif;
    if (!is_dir($folder)) {
        mkdir($folder, 0777, true);
    }
    $nama_file = $awalan . bin2hex(random_bytes(6)) . '.' . $jenis_diizinkan[$jenis];
    move_uploaded_file($file['tmp_name'], $folder . $nama_file);

    return $nama_file;
}

function hapus_gambar(?string $nama_file, string $folder_relatif = FOLDER_FOTO_PRODUK): void
{
    if ($nama_file && is_file(ROOT_PATH . '/' . $folder_relatif . $nama_file)) {
        unlink(ROOT_PATH . '/' . $folder_relatif . $nama_file);
    }
}

// QRIS toko. Diunggah lewat menu Pengaturan; jika belum ada, dipakai gambar contoh.
const FOLDER_QRIS = 'assets/img/qris/';

function gambar_qris(): string
{
    $nama_file = pengaturan_toko()['qris_image'] ?? null;
    if ($nama_file && is_file(ROOT_PATH . '/' . FOLDER_QRIS . $nama_file)) {
        return url(FOLDER_QRIS . $nama_file);
    }
    return url('assets/img/qris.svg');
}

// LOGO TOKO. Letakkan file logo di folder assets/img/ dengan nama  logo.png  (atau logo.svg / logo.jpg / logo.webp).
// Selama file belum ada, yang tampil adalah kotak kosong bertuliskan "LOGO" di posisi yang sama.
function logo(int $ukuran = 44, string $kelas = ''): string
{
    foreach (['png', 'svg', 'jpg', 'webp'] as $ekstensi) {
        if (is_file(ROOT_PATH . '/assets/img/logo.' . $ekstensi)) {
            return '<img src="' . url('assets/img/logo.' . $ekstensi) . '" alt="Logo" width="' . $ukuran
                . '" height="' . $ukuran . '" class="' . e($kelas) . '" style="object-fit: contain;">';
        }
    }
    return '<span class="logo-kosong ' . e($kelas) . '" style="width: ' . $ukuran . 'px; height: ' . $ukuran . 'px;">LOGO</span>';
}
