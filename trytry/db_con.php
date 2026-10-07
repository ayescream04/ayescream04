<?php
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'sol_system_db';

/* ---------- mysqli connection ($con) ---------- */
mysqli_report(MYSQLI_REPORT_OFF);
$con = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (!$con) {
    die('Database connection failed. Please check db_con.php settings.');
}
mysqli_set_charset($con, 'utf8mb4');

/* ---------- PDO connection ($pdo) ---------- */
try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed. Please check db_con.php settings.');
}
