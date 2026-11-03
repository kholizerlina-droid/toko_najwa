<?php
// includes/auth.php
// Login, logout, dan pembatasan akses halaman admin.

function sudah_login(): bool
{
    return !empty($_SESSION['user']);
}

// Data user yang sedang login: id, username, nama, role.
function user_login(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_admin(): bool
{
    return (user_login()['role'] ?? '') === 'admin';
}

// Dipasang di setiap halaman admin: belum login -> diarahkan ke halaman login.
function wajib_login(): void
{
    if (!sudah_login()) {
        redirect(url('login.php'));
    }
}

// Dipasang di halaman khusus pemilik (admin).
function wajib_admin(): void
{
    wajib_login();
    if (!is_admin()) {
        set_flash('danger', 'Halaman tersebut hanya untuk pemilik (admin).');
        redirect(url('admin/index.php'));
    }
}

// Mengembalikan pesan error jika gagal, atau null jika berhasil login.
function proses_login(string $username, string $password): ?string
{
    if ($username === '' || $password === '') {
        return 'Silakan isi username dan password.';
    }

    $user = ambil_satu('SELECT id, username, password, nama_lengkap, role FROM users WHERE username = ?', 's', [$username]);

    if (!$user || !password_verify($password, $user['password'])) {
        return 'Username atau password salah.';
    }

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'       => (int) $user['id'],
        'username' => $user['username'],
        'nama'     => $user['nama_lengkap'],
        'role'     => $user['role'],
    ];
    return null;
}

function proses_logout(): void
{
    $_SESSION = [];
    session_destroy();
}
