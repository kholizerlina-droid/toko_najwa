<?php
// config/config.php
// Satu-satunya tempat untuk mengubah pengaturan server (database, zona waktu).

const DB_HOST = '127.0.0.1';
const DB_USER = 'root';
const DB_PASS = '';
const DB_NAME = 'db_tokonajwa';

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
