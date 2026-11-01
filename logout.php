<?php
// logout.php
require_once __DIR__ . '/config/auth.php';

session_unset();
session_destroy();

header("Location: login.php?msg=logout");
exit;
