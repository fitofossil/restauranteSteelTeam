<?php
// =============================================================
// CONEXÃO COM O BANCO DE DADOS
// =============================================================

if (!defined('BASE_URL')) {
    // Descobre automaticamente a pasta do projeto dentro do servidor web.
    $projetoRoot = dirname(__DIR__);
    $docRoot     = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
    define('BASE_URL', rtrim(substr($projetoRoot, strlen($docRoot)), '/'));
}

$host = "localhost";
// porta padrao do Mysql 3306
$port = "3306";

// nome do banco de dados
$dbname = "restaurante";
$user = "root";
$password = "";

try {
    // PDO centraliza as consultas e lança exceções quando o banco retorna erro.
    $conn = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $user,
        $password
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    // Sem conexão não é possível continuar, por isso a execução é encerrada.
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}
