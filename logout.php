<?php
// logout.php - Keluar dari sistem.
require_once __DIR__ . '/includes/init.php';

proses_logout();
redirect(url('login.php?msg=logout'));
