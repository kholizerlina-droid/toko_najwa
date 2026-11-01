<?php
// login.php - Halaman Masuk Petugas Kasir Toko Najwa (Design Reference: Image 1)
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

$pengaturan = get_pengaturan($koneksi);

if (is_logged_in()) {
    header("Location: admin/index.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Silakan isi username dan password.';
    } else {
        $stmt = $koneksi->prepare("SELECT id, username, password, nama_lengkap, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_username'] = $user['username'];
                $_SESSION['user_nama'] = $user['nama_lengkap'];
                $_SESSION['user_role'] = $user['role'];

                header("Location: admin/index.php");
                exit;
            } else {
                $error = 'Password salah.';
            }
        } else {
            $error = 'Username tidak ditemukan.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk Sistem Kasir - <?= htmlspecialchars($pengaturan['nama_toko']); ?></title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body class="login-screen-bg">

<div class="login-card-clean">
    <!-- Brand Header -->
    <div class="d-flex align-items-center gap-2 mb-4">
        <img src="assets/img/logo.svg" alt="Toko Najwa" style="width: 46px; height: 46px;">
        <div>
            <h4 class="fw-bold mb-0 text-dark lh-1" style="letter-spacing: -0.5px;">Toko <span style="color: var(--brand-green);">Najwa</span></h4>
            <small class="text-muted" style="font-size: 0.72rem;">Sembako & Digital POS</small>
        </div>
    </div>

    <!-- Title & Subtitle -->
    <div class="mb-3">
        <h2 class="fw-bold text-dark mb-1" style="line-height: 1.25; font-size: 1.75rem;">
            Penuhi kebutuhan<br>
            Sembako <span style="color: var(--brand-green);">terbaik</span>
        </h2>
        <div class="small text-muted">Sign in to continue Sistem Kasir</div>
    </div>

    <!-- Illustration Bag & Slogan -->
    <div class="text-center my-3">
        <div class="mx-auto" style="width: 90px; height: 90px;">
            <img src="assets/img/logo.svg" alt="Sembako Segar" style="width: 100%; height: 100%; object-fit: contain;">
        </div>
        <h6 class="fw-bold text-dark mt-2 mb-0" style="color: var(--brand-green) !important;">Sembako Segar, Terdata Cepat</h6>
        <small class="text-muted" style="font-size: 0.75rem;">Warung Sembako Ibu Dhiyah • Bonomerto</small>
        
        <!-- Status Dots -->
        <div class="d-flex justify-content-center gap-1 mt-2">
            <span style="width: 8px; height: 8px; border-radius: 50%; background: var(--brand-green);"></span>
            <span style="width: 8px; height: 8px; border-radius: 50%; background: #cbd5e1;"></span>
            <span style="width: 8px; height: 8px; border-radius: 50%; background: #cbd5e1;"></span>
        </div>
    </div>

    <!-- Error Alert -->
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2 small d-flex align-items-center mb-3 rounded-4" role="alert">
            <i class="bi bi-exclamation-circle-fill me-2 fs-6"></i>
            <div><?= htmlspecialchars($error); ?></div>
        </div>
    <?php endif; ?>

    <!-- Form Login -->
    <form method="POST" action="login.php" class="mt-3">
        <div class="mb-3">
            <div class="input-group">
                <span class="input-group-text bg-transparent border-0 pe-0" style="position: absolute; left: 16px; top: 12px; z-index: 5;">
                    <i class="bi bi-person text-muted fs-5"></i>
                </span>
                <input type="text" name="username" id="username" class="form-control login-pill-input ps-5" placeholder="Username akun kasir" value="admin" required autofocus>
            </div>
        </div>

        <div class="mb-4">
            <div class="input-group position-relative">
                <span class="input-group-text bg-transparent border-0 pe-0" style="position: absolute; left: 16px; top: 12px; z-index: 5;">
                    <i class="bi bi-lock text-muted fs-5"></i>
                </span>
                <input type="password" name="password" id="password" class="form-control login-pill-input ps-5 pe-5" placeholder="Password" value="admin123" required>
                <button type="button" class="btn bg-transparent border-0 text-muted" onclick="togglePassword()" style="position: absolute; right: 14px; top: 8px; z-index: 5;">
                    <i class="bi bi-eye" id="eyeIcon"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="btn btn-pill-green w-100 mb-3 shadow-sm">
            <span>Log in</span>
            <i class="bi bi-arrow-right fs-5"></i>
        </button>
    </form>

    <!-- Divider -->
    <div class="d-flex align-items-center my-3">
        <hr class="flex-grow-1 my-0 text-muted">
        <span class="px-2 small text-muted text-uppercase" style="font-size: 0.7rem; font-weight: 600;">Atau Demo Instan</span>
        <hr class="flex-grow-1 my-0 text-muted">
    </div>

    <!-- Quick Role Switcher for School Presentation -->
    <div class="row g-2">
        <div class="col-6">
            <button type="button" class="btn btn-pill-white w-100 small d-flex align-items-center justify-content-center gap-2" onclick="setCred('admin', 'admin123')">
                <i class="bi bi-shield-check text-success"></i>
                <span style="font-size: 0.8rem; font-weight: 600;">Admin</span>
            </button>
        </div>
        <div class="col-6">
            <button type="button" class="btn btn-pill-white w-100 small d-flex align-items-center justify-content-center gap-2" onclick="setCred('kasir', 'admin123')">
                <i class="bi bi-cart text-primary"></i>
                <span style="font-size: 0.8rem; font-weight: 600;">Kasir</span>
            </button>
        </div>
    </div>

    <div class="text-center mt-4">
        <a href="index.php" class="text-muted small text-decoration-none">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Halaman Utama Toko
        </a>
    </div>
</div>

<script>
    function setCred(u, p) {
        document.getElementById('username').value = u;
        document.getElementById('password').value = p;
    }

    function togglePassword() {
        const input = document.getElementById('password');
        const icon = document.getElementById('eyeIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('bi-eye', 'bi-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('bi-eye-slash', 'bi-eye');
        }
    }
</script>

</body>
</html>
