<?php

$db_host = 'sql207.infinityfree.com';          
$db_name = 'if0_41899490_kt_db';
$db_user = 'if0_41899490';
$db_pass = 'qPCmEclPwK0X';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Database connection failed');
}
