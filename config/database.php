<?php
// config/database.php
// Koneksi database MySQL. Panggil db() dari mana saja untuk mendapatkan koneksi.

function db(): mysqli
{
    static $koneksi = null;

    if ($koneksi === null) {
        // Error MySQL dilempar sebagai exception supaya mudah ditangkap dengan try/catch.
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            $koneksi = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        } catch (mysqli_sql_exception $e) {
            http_response_code(500);
            exit('Koneksi database gagal. Pastikan MySQL menyala dan database sudah diimpor '
                . '(lihat langkah instalasi di README.md).');
        }

        $koneksi->set_charset('utf8mb4');
    }

    return $koneksi;
}
