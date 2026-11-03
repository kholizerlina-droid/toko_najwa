<?php
// login.php - Halaman masuk untuk pemilik / kasir.
// Alamat ini sengaja tidak ditautkan di website publik; hanya petugas yang tahu.
require_once __DIR__ . '/includes/init.php';

if (sudah_login()) {
    redirect(url('admin/index.php'));
}

$error = '';
if (is_post()) {
    csrf_verifikasi();
    $error = proses_login(post('username'), post('password'));
    if ($error === null) {
        redirect(url('admin/index.php'));
    }
}

$toko = pengaturan_toko();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk - <?= e($toko['nama_toko']) ?></title>
    <link rel="stylesheet" href="<?= url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
</head>
<body class="bg-body-tertiary">
<main class="container d-flex align-items-center min-vh-100 py-4">
    <div class="card shadow-sm mx-auto w-100" style="max-width: 400px;">
        <div class="card-body p-4">
            <div class="d-flex align-items-center mb-4">
                <div class="me-auto">
                    <h1 class="h5 mb-0"><?= e($toko['nama_toko']) ?></h1>
                    <small class="text-body-secondary">Masuk ke sistem kasir</small>
                </div>
                <?= logo(52) ?>
            </div>

            <?php if (get('msg') === 'logout'): ?>
                <div class="alert alert-info py-2">Anda sudah keluar.</div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger py-2"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" value="<?= e(post('username')) ?>" required autofocus autocomplete="username">
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-primary w-100">Masuk</button>
            </form>

            <div class="text-center mt-3">
                <a href="<?= url('index.php') ?>" class="small">&larr; Kembali ke website</a>
            </div>
        </div>
    </div>
</main>
</body>
</html>
