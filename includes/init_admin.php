<?php
// includes/init_admin.php
// Dipanggil paling atas oleh semua halaman di folder admin/:
// memuat init, memastikan sudah login, dan memeriksa token CSRF pada setiap form POST.

require_once __DIR__ . '/init.php';

wajib_login();

if (is_post()) {
    csrf_verifikasi();
}
