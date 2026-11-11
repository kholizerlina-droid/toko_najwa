<?php
// config/config.php
// Satu-satunya tempat untuk mengubah pengaturan server (database, zona waktu).

// Default lokal Laragon. Kredensial hosting disimpan terpisah di database.private.php.
$db_config = [
    'host' => '127.0.0.1',
    'user' => 'root',
    'pass' => '',
    'name' => 'db_tokonajwa',
];
$private_db_config = __DIR__ . '/database.private.php';
if (is_file($private_db_config)) {
    $override = require $private_db_config;
    if (is_array($override)) {
        $db_config = array_replace($db_config, $override);
    }
}
define('DB_HOST', (string) $db_config['host']);
define('DB_USER', (string) $db_config['user']);
define('DB_PASS', (string) $db_config['pass']);
define('DB_NAME', (string) $db_config['name']);
unset($db_config, $private_db_config, $override);
const ZONA_WAKTU = 'Asia/Jakarta';

// Folder utama project di komputer (contoh: D:\laragon\www\toko_najwa)
define('ROOT_PATH', dirname(__DIR__));

// Alamat dasar website, dideteksi otomatis dari lokasi folder (contoh: /toko_najwa).
// Jika dibuka lewat virtual host (toko_najwa.test) hasilnya kosong, dan itu sudah benar.
$_root_dokumen = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$_root_project = str_replace('\\', '/', ROOT_PATH);
define('BASE_URL', ($_root_dokumen !== '' && str_starts_with($_root_project, $_root_dokumen))
    ? substr($_root_project, strlen($_root_dokumen))
    : '/toko_najwa');
unset($_root_dokumen, $_root_project);

// Identitas proyek (tampil di footer halaman publik)
const INFO_PROYEK = [
    'sekolah'  => 'SMK Negeri Tengaran',
    'jurusan'  => 'PPLG / RPL Kelas XI',
    'kelompok' => 'Kelompok 6',
    'anggota'  => ['Kholiz Erlina', 'Sifa Fuji Febriyana'],
    'tahun'    => '2026/2027',
];

date_default_timezone_set(ZONA_WAKTU);
