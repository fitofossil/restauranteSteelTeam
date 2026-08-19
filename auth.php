<?php
// =============================================================
// AUTH.PHP — Handler de Autenticação
// =============================================================
define('BASE_URL', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'));

// Carrega as regras de autenticação e a conexão usada na consulta do login.
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/config/conexao.php';

// Garante que a sessão exista antes de gravar os dados do usuário logado.
Auth::iniciarSessao();

// Este arquivo só recebe o envio do formulário de login.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: templates/login.php');
    exit();
}

// Dados vindos da tela de login; a validação final é feita pela classe Auth.
$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha'] ?? '';

$auth = new Auth($conn);
$resultado = $auth->login($email, $senha);

if ($resultado['sucesso']) {
    // Recepção e cozinha iniciam diretamente nas telas usadas durante o atendimento.
    $destino = Auth::isRecepcao()
        ? 'templates/pedidos.php'
        : (Auth::isCozinheiro() ? 'templates/cozinha.php' : (Auth::isGarcom() ? 'templates/garcom.php' : 'templates/painel.php'));
    header('Location: ' . $destino);
    exit();
} else {
    // A mensagem é enviada pela URL e exibida com segurança em login.php.
    header('Location: templates/login.php?erro=' . urlencode($resultado['mensagem']));
    exit();
}
