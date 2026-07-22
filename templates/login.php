<?php
// =============================================================
// TELA DE LOGIN
// =============================================================
require_once __DIR__ . '/../src/Auth.php';
Auth::iniciarSessao();
if (Auth::isLoggedIn()) {
    header('Location: painel.php');
    exit();
}

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

            <form method="POST" action="../auth.php">
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="senha" placeholder="Senha" required>
                <button type="submit" name="entrar">Entrar</button>
            </form>

            <?php if ($erro): ?>
                <p class="erro"><?php echo htmlspecialchars($erro); ?></p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
