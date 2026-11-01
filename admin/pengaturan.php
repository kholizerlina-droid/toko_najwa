<?php
// admin/pengaturan.php - Pengaturan Toko Najwa & Profil Akun
$page_title = 'Pengaturan Sistem';
require_once __DIR__ . '/header.php';
require_admin();

$pengaturan = get_pengaturan($koneksi);

// Handle Update Profil Toko
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'update_toko') {
        $nama_toko = trim($_POST['nama_toko'] ?? '');
        $pemilik   = trim($_POST['pemilik'] ?? '');
        $telepon   = trim($_POST['telepon'] ?? '');
        $alamat    = trim($_POST['alamat'] ?? '');
        $footer    = trim($_POST['teks_footer_struk'] ?? '');

        if (!empty($nama_toko) && !empty($pemilik)) {
            $stmt = $koneksi->prepare("UPDATE pengaturan SET nama_toko=?, pemilik=?, telepon=?, alamat=?, teks_footer_struk=? WHERE id=1");
            $stmt->bind_param("sssss", $nama_toko, $pemilik, $telepon, $alamat, $footer);
            if ($stmt->execute()) {
                set_flash('success', 'Profil toko berhasil diperbarui.');
            } else {
                set_flash('danger', 'Gagal memperbarui profil toko.');
            }
        }
        header("Location: pengaturan.php");
        exit;
    }

    if ($action === 'ganti_password') {
        $pwd_lama = trim($_POST['pwd_lama'] ?? '');
        $pwd_baru = trim($_POST['pwd_baru'] ?? '');
        $pwd_konf = trim($_POST['pwd_konf'] ?? '');

        $uid = $user['id'];
        $res_u = $koneksi->query("SELECT password FROM users WHERE id = $uid");
        $u_row = $res_u->fetch_assoc();

        if (!password_verify($pwd_lama, $u_row['password'])) {
            set_flash('danger', 'Password lama tidak sesuai!');
        } elseif (strlen($pwd_baru) < 6) {
            set_flash('danger', 'Password baru minimal 6 karakter!');
        } elseif ($pwd_baru !== $pwd_konf) {
            set_flash('danger', 'Konfirmasi password baru tidak cocok!');
        } else {
            $hashed = password_hash($pwd_baru, PASSWORD_BCRYPT);
            $stmt_u = $koneksi->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt_u->bind_param("si", $hashed, $uid);
            if ($stmt_u->execute()) {
                set_flash('success', 'Password Anda berhasil diperbarui!');
            }
        }
        header("Location: pengaturan.php");
        exit;
    }

    if ($action === 'tambah_user') {
        $username = trim($_POST['username'] ?? '');
        $nama     = trim($_POST['nama_lengkap'] ?? '');
        $role     = $_POST['role'] ?? 'kasir';
        $pwd      = trim($_POST['password'] ?? '');

        if (empty($username) || empty($nama) || empty($pwd)) {
            set_flash('danger', 'Semua kolom pengguna baru wajib diisi!');
        } else {
            // Cek username
            $cek = $koneksi->prepare("SELECT id FROM users WHERE username = ?");
            $cek->bind_param("s", $username);
            $cek->execute();
            if ($cek->get_result()->num_rows > 0) {
                set_flash('danger', 'Username tersebut sudah digunakan.');
            } else {
                $hashed = password_hash($pwd, PASSWORD_BCRYPT);
                $stmt_nu = $koneksi->prepare("INSERT INTO users (username, password, nama_lengkap, role) VALUES (?, ?, ?, ?)");
                $stmt_nu->bind_param("ssss", $username, $hashed, $nama, $role);
                if ($stmt_nu->execute()) {
                    set_flash('success', 'Pengguna baru berhasil ditambahkan.');
                }
            }
        }
        header("Location: pengaturan.php");
        exit;
    }
}

// Ambil daftar pengguna
$daftar_user = $koneksi->query("SELECT id, username, nama_lengkap, role, created_at FROM users ORDER BY role ASC, username ASC")->fetch_all(MYSQLI_ASSOC);
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="fw-bold mb-1">Pengaturan Sistem Toko Najwa</h4>
        <p class="text-muted small mb-0">Atur profil UMKM, kontak WhatsApp pengantaran, format nota struk belanja, dan akun kasir.</p>
    </div>
</div>

<div class="row g-4">
    <!-- Profil Toko -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-success text-white py-3 rounded-top-4">
                <h6 class="fw-bold mb-0"><i class="bi bi-shop me-2"></i>Informasi Profil Warung Sembako</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="pengaturan.php">
                    <input type="hidden" name="action" value="update_toko">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Nama Warung / Toko</label>
                        <input type="text" name="nama_toko" class="form-control" value="<?= htmlspecialchars($pengaturan['nama_toko']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Nama Pemilik Usaha</label>
                        <input type="text" name="pemilik" class="form-control" value="<?= htmlspecialchars($pengaturan['pemilik']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Nomor WhatsApp Toko (Untuk Pesanan Antar Warga)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-success"><i class="bi bi-whatsapp"></i></span>
                            <input type="text" name="telepon" class="form-control" value="<?= htmlspecialchars($pengaturan['telepon']); ?>" placeholder="08xxxxxxxxxx" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Alamat Lengkap Toko</label>
                        <textarea name="alamat" class="form-control" rows="2" required><?= htmlspecialchars($pengaturan['alamat']); ?></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-muted">Pesan Footer Struk Belanja</label>
                        <textarea name="teks_footer_struk" class="form-control" rows="3"><?= htmlspecialchars($pengaturan['teks_footer_struk']); ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-success fw-bold w-100 py-2">
                        <i class="bi bi-save me-1"></i> Simpan Profil Toko
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Ganti Password & Akun Pengguna -->
    <div class="col-lg-6">
        <!-- Ganti Password -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-primary text-white py-3 rounded-top-4">
                <h6 class="fw-bold mb-0"><i class="bi bi-shield-lock me-2"></i>Ubah Password Akun Anda</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="pengaturan.php">
                    <input type="hidden" name="action" value="ganti_password">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Password Lama</label>
                        <input type="password" name="pwd_lama" class="form-control" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-muted">Password Baru</label>
                            <input type="password" name="pwd_baru" class="form-control" minlength="6" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-muted">Konfirmasi Password Baru</label>
                            <input type="password" name="pwd_konf" class="form-control" minlength="6" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary fw-bold w-100 py-2">
                        <i class="bi bi-check-lg me-1"></i> Perbarui Password
                    </button>
                </form>
            </div>
        </div>

        <!-- Daftar Pengguna Kasir -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-people me-2 text-info"></i>Daftar Pengguna Sistem</h6>
                <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#modalTambahUser">
                    <i class="bi bi-person-plus"></i> Tambah Akun
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Username</th>
                                <th>Nama Lengkap</th>
                                <th>Peran (Role)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($daftar_user as $u): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($u['username']); ?></strong></td>
                                    <td><?= htmlspecialchars($u['nama_lengkap']); ?></td>
                                    <td>
                                        <span class="badge bg-<?= ($u['role'] === 'admin') ? 'primary' : 'success'; ?>">
                                            <?= strtoupper($u['role']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah Pengguna Baru -->
<div class="modal fade" id="modalTambahUser" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus me-2"></i>Tambah Akun Pengguna</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="pengaturan.php">
                <input type="hidden" name="action" value="tambah_user">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Username</label>
                        <input type="text" name="username" class="form-control" placeholder="Contoh: kasir2" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" class="form-control" placeholder="Contoh: Siti Aisyah" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Peran / Role</label>
                        <select name="role" class="form-select">
                            <option value="kasir">Kasir (Hanya Penjualan & Kasir)</option>
                            <option value="admin">Administrator (Akses Penuh)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" minlength="6" required>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-info text-white fw-bold px-4">Simpan Akun</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
