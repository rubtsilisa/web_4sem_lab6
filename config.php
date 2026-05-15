<?php
$db_host = 'localhost';
$db_user = 'u82191';
$db_pass = '7564858';
$db_name = 'u82191';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die('Ошибка подключения к БД');
}
?>
