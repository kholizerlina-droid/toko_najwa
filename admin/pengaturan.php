<?php
// admin/pengaturan.php - Profil toko, ganti password, dan kelola akun (khusus admin).
require_once __DIR__ . '/../includes/init_admin.php';
wajib_admin();

// Tabel pengaturan harus berisi 1 baris; dibuat otomatis jika masih kosong.
if (!ambil_nilai('SELECT COUNT(*) FROM pengaturan')) {
    $bawaan = pengaturan_toko();
    jalankan(
        'INSERT INTO pengaturan (nama_toko, pemilik, telepon, alamat, teks_footer_struk) VALUES (?, ?, ?, ?, ?)',
        'sssss', [$bawaan['nama_toko'], $bawaan['pemilik'], $bawaan['telepon'], $bawaan['alamat'], $bawaan['teks_footer_struk']]
    );
}

/* ---------- Proses form ---------- */
if (is_post()) {
    $aksi = post('aksi');
    $me   = user_login()['id'];

    if ($aksi === 'profil_toko') {
        if (post('nama_toko') === '' || post('pemilik') === '') {
            set_flash('danger', 'Nama toko dan pemilik wajib diisi.');
        } else {
            jalankan(
                'UPDATE pengaturan SET nama_toko = ?, pemilik = ?, telepon = ?, alamat = ?, teks_footer_struk = ?',   // tabel pengaturan hanya berisi 1 baris
                'sssss', [post('nama_toko'), post('pemilik'), post('telepon'), post('alamat'), post('teks_footer_struk')]
            );
            set_flash('success', 'Profil toko diperbarui.');
        }
    } elseif ($aksi === 'qris') {
        $qris_lama = pengaturan_toko()['qris_image'];
        try {
            if (post('hapus_qris') === '1') {
                jalankan('UPDATE pengaturan SET qris_image = NULL');
                hapus_gambar($qris_lama, FOLDER_QRIS);
                set_flash('success', 'QRIS dihapus, kembali memakai gambar contoh.');
            } elseif ($qris_baru = simpan_gambar($_FILES['qris'] ?? [], FOLDER_QRIS, 'qris-')) {
                jalankan('UPDATE pengaturan SET qris_image = ?', 's', [$qris_baru]);
                hapus_gambar($qris_lama, FOLDER_QRIS);
                set_flash('success', 'QRIS berhasil diunggah.');
            } else {
                set_flash('danger', 'Pilih file gambar QRIS terlebih dahulu.');
            }
        } catch (Exception $e) {
            set_flash('danger', $e->getMessage());
        }
    } elseif ($aksi === 'ganti_password') {
        $hash = ambil_nilai('SELECT password FROM users WHERE id = ?', 'i', [$me]);

        if (!password_verify(post('password_lama'), $hash)) {
            set_flash('danger', 'Password lama tidak sesuai.');
        } elseif (strlen(post('password_baru')) < 6) {
            set_flash('danger', 'Password baru minimal 6 karakter.');
        } elseif (post('password_baru') !== post('password_konfirmasi')) {
            set_flash('danger', 'Konfirmasi password tidak cocok.');
        } else {
            jalankan('UPDATE users SET password = ? WHERE id = ?', 'si', [password_hash(post('password_baru'), PASSWORD_DEFAULT), $me]);
            set_flash('success', 'Password berhasil diganti.');
        }
    } elseif ($aksi === 'tambah_user') {
        $username = post('username');

        if ($username === '' || post('nama_lengkap') === '' || strlen(post('password')) < 6) {
            set_flash('danger', 'Lengkapi data akun (password minimal 6 karakter).');
        } elseif (ambil_nilai('SELECT COUNT(*) FROM users WHERE username = ?', 's', [$username]) > 0) {
            set_flash('danger', 'Username sudah dipakai.');
        } else {
            $role = post('role') === 'admin' ? 'admin' : 'kasir';
            jalankan(
                'INSERT INTO users (username, password, nama_lengkap, role) VALUES (?, ?, ?, ?)',
                'ssss', [$username, password_hash(post('password'), PASSWORD_DEFAULT), post('nama_lengkap'), $role]
            );
            set_flash('success', 'Akun baru berhasil dibuat.');
        }
    } elseif ($aksi === 'hapus_user') {
        $id = (int) post('id');
        if ($id === $me) {
            set_flash('danger', 'Anda tidak bisa menghapus akun yang sedang dipakai.');
        } else {
            jalankan('DELETE FROM users WHERE id = ?', 'i', [$id]);   // nota lama tetap ada (kasir menjadi kosong)
            set_flash('success', 'Akun dihapus.');
        }
    }
    redirect('pengaturan.php');
}

/* ---------- Ambil data ---------- */
$toko        = pengaturan_toko();
$daftar_user = ambil_semua('SELECT id, username, nama_lengkap, role FROM users ORDER BY role, username');

/* ---------- Tampilan ---------- */
$judul_halaman = 'Pengaturan';
$menu_aktif    = 'pengaturan';
include __DIR__ . '/../includes/layout/admin_top.php';
?>

<div class="mb-4">
    <h1 class="h3 mb-0">Pengaturan</h1>
    <p class="text-body-secondary mb-0">Profil toko (tampil di website &amp; struk), password, dan akun pengguna.</p>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <form method="post" class="card h-100">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="profil_toko">
            <div class="card-header fw-semibold"><i class="bi bi-shop me-1"></i> Profil Toko</div>
            <div class="card-body">
                <div class="mb-3"><label class="form-label">Nama toko</label>
                    <input type="text" name="nama_toko" class="form-control" value="<?= e($toko['nama_toko']) ?>" required></div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6"><label class="form-label">Pemilik</label>
                        <input type="text" name="pemilik" class="form-control" value="<?= e($toko['pemilik']) ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Telepon / WhatsApp</label>
                        <input type="text" name="telepon" class="form-control" value="<?= e($toko['telepon']) ?>"></div>
                </div>
                <div class="mb-3"><label class="form-label">Alamat</label>
                    <textarea name="alamat" class="form-control" rows="2"><?= e($toko['alamat']) ?></textarea></div>
                <div><label class="form-label">Pesan di bawah struk</label>
                    <textarea name="teks_footer_struk" class="form-control" rows="2"><?= e($toko['teks_footer_struk']) ?></textarea></div>
            </div>
            <div class="card-footer"><button type="submit" class="btn btn-primary">Simpan Profil</button></div>
        </form>
    </div>

    <div class="col-lg-6">
        <form method="post" class="card h-100">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="ganti_password">
            <div class="card-header fw-semibold"><i class="bi bi-key me-1"></i> Ganti Password Saya</div>
            <div class="card-body">
                <div class="mb-3"><label class="form-label">Password lama</label>
                    <input type="password" name="password_lama" class="form-control" required autocomplete="current-password"></div>
                <div class="mb-3"><label class="form-label">Password baru</label>
                    <input type="password" name="password_baru" class="form-control" minlength="6" required autocomplete="new-password"></div>
                <div><label class="form-label">Ulangi password baru</label>
                    <input type="password" name="password_konfirmasi" class="form-control" minlength="6" required autocomplete="new-password"></div>
            </div>
            <div class="card-footer"><button type="submit" class="btn btn-primary">Ganti Password</button></div>
        </form>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header fw-semibold"><i class="bi bi-qr-code me-1"></i> QRIS Toko</div>
            <div class="card-body">
                <div class="row g-4 align-items-center">
                    <div class="col-md-3 text-center">
                        <img src="<?= gambar_qris() ?>" alt="QRIS saat ini" class="img-fluid border rounded" style="max-height: 200px;">
                        <div class="small text-body-secondary mt-1">
                            <?= $toko['qris_image'] ? 'QRIS toko Anda' : 'Gambar contoh (belum diunggah)' ?>
                        </div>
                    </div>
                    <div class="col-md-9">
                        <form method="post" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <input type="hidden" name="aksi" value="qris">
                            <label class="form-label" for="fileQris">Unggah gambar QRIS</label>
                            <div class="input-group mb-1">
                                <input type="file" class="form-control" id="fileQris" name="qris" accept="image/jpeg,image/png,image/webp" required>
                                <button type="submit" class="btn btn-primary">Unggah</button>
                            </div>
                            <div class="form-text">JPG, PNG, atau WEBP, maksimal 2 MB. Tampil di halaman kasir (pembayaran QRIS) dan website publik.</div>
                        </form>
                        <?php if ($toko['qris_image']): ?>
                            <form method="post" class="mt-2" data-konfirmasi="Hapus QRIS yang sudah diunggah?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="aksi" value="qris">
                                <input type="hidden" name="hapus_qris" value="1">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Hapus QRIS</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header fw-semibold"><i class="bi bi-people me-1"></i> Akun Pengguna</div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light"><tr><th>Username</th><th>Nama</th><th>Peran</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($daftar_user as $u): ?>
                            <tr>
                                <td class="font-monospace"><?= e($u['username']) ?></td>
                                <td><?= e($u['nama_lengkap']) ?></td>
                                <td><span class="badge <?= $u['role'] === 'admin' ? 'text-bg-primary' : 'text-bg-secondary' ?> text-uppercase"><?= e($u['role']) ?></span></td>
                                <td class="text-end">
                                    <?php if ((int) $u['id'] !== user_login()['id']): ?>
                                        <form method="post" data-konfirmasi="Hapus akun <?= e($u['username']) ?>?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="aksi" value="hapus_user">
                                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    <?php else: ?>
                                        <small class="text-body-secondary">akun Anda</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <form method="post" class="card-footer">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="tambah_user">
                <div class="row g-2">
                    <div class="col-md-3"><input type="text" name="username" class="form-control" placeholder="Username" required></div>
                    <div class="col-md-3"><input type="text" name="nama_lengkap" class="form-control" placeholder="Nama lengkap" required></div>
                    <div class="col-md-2"><input type="password" name="password" class="form-control" placeholder="Password" minlength="6" required autocomplete="new-password"></div>
                    <div class="col-md-2"><select name="role" class="form-select"><option value="kasir">Kasir</option><option value="admin">Admin</option></select></div>
                    <div class="col-md-2 d-grid"><button type="submit" class="btn btn-outline-primary">Tambah Akun</button></div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/layout/admin_bottom.php'; ?>
