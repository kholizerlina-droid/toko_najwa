<?php
// includes/layout/admin_bottom.php
// Bagian BAWAH semua halaman admin. Halaman bisa mengisi $skrip_tambahan
// (daftar file JS di assets/js/) untuk dimuat setelah Bootstrap.
?>
    </main>
</div>

<script src="<?= url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= url('assets/js/admin.js') ?>"></script>
<?php foreach (($skrip_tambahan ?? []) as $skrip): ?>
    <script src="<?= url('assets/js/' . $skrip) ?>"></script>
<?php endforeach; ?>
</body>
</html>
