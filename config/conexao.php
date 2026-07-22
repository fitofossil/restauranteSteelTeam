<?php
// =============================================================
// CONEXÃO COM O BANCO DE DADOS
// =============================================================

if (!defined('BASE_URL')) {
    define('BASE_URL', '/restauranteSteelTeam');
}

$host = "localhost";
$port = "3306";
$dbname = "restaurante";
$user = "root";
$password = "";

try {
    $conn = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $user,
        $password
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}
