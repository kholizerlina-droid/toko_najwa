<?php
// config/auth.php - Session & Autentikasi Pengguna
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        header("Location: ../login.php?msg=harus_login");
        exit;
    }
}

function require_admin() {
    require_login();
    if ($_SESSION['user_role'] !== 'admin') {
        header("Location: index.php?msg=akses_ditolak");
        exit;
    }
}

function current_user() {
    if (!is_logged_in()) return null;
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['user_username'],
        'nama' => $_SESSION['user_nama'],
        'role' => $_SESSION['user_role']
    ];
}
