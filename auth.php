<?php
// =============================================================
// AUTH.PHP — Handler de Autenticação
// =============================================================
define('BASE_URL', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'));

require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/config/conexao.php';

Auth::iniciarSessao();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: templates/login.php');
    exit();
}

$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha'] ?? '';

$auth = new Auth($conn);
$resultado = $auth->login($email, $senha);

if ($resultado['sucesso']) {
    header('Location: templates/painel.php');
    exit();
} else {
    header('Location: templates/login.php?erro=' . urlencode($resultado['mensagem']));
    exit();
}
