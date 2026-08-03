<?php
// =============================================================
// TELA DE LOGIN
// =============================================================
require_once __DIR__ . '/../src/Auth.php';

// Caso a pessoa já esteja logada, evita exibir o formulário novamente.
Auth::iniciarSessao();
if (Auth::isLoggedIn()) {
    $destino = Auth::isRecepcao() ? 'pedidos.php' : (Auth::isCozinheiro() ? 'cozinha.php' : (Auth::isGarcom() ? 'garcom.php' : 'painel.php'));
    header('Location: ' . $destino);
    exit();
}

// Mensagem gerada pelo handler de login, se houve falha de autenticação.
$erro = $_GET['erro'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dogão Lanches - Login</title>
    <link rel="stylesheet" href="../public/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <h1>🌭 Dogão Lanches</h1>
            <p class="subtitulo">Acesse o painel administrativo</p>

            <!-- Envia as credenciais ao arquivo responsável por autenticar. -->
            <form method="POST" action="../auth.php">
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="senha" placeholder="Senha" required>
                <button type="submit" name="entrar">Entrar</button>
            </form>

            <!-- A saída é escapada para que uma mensagem não injete HTML na página. -->
            <?php if ($erro): ?>
                <p class="erro"><?php echo htmlspecialchars($erro); ?></p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
