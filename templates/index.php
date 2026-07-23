<?php
session_start();

if (empty($_SESSION['usuario'])) {
    header('Location: ../public/index.php');
    exit();
}

require_once __DIR__ . '/../config/conexao.php';

$mensagem = '';
$tipoMensagem = '';

try {
    // Cria a tabela de pedidos na primeira abertura do painel.
    $conn->exec("CREATE TABLE IF NOT EXISTS pedidos (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        valor DECIMAL(10,2) NOT NULL,
        criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['salvar_email'])) {
            $id = filter_input(INPUT_POST, 'usuario_id', FILTER_VALIDATE_INT);
            $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

            if (!$id || !$email) {
                throw new RuntimeException('Informe um e-mail válido.');
            }

            $stmt = $conn->prepare('UPDATE users_login SET email = ? WHERE id = ?');
            $stmt->execute([$email, $id]);
            $mensagem = 'E-mail atualizado com sucesso.';
            $tipoMensagem = 'sucesso';
        }

        if (isset($_POST['registrar_pedido'])) {
            $valor = str_replace(',', '.', trim($_POST['valor'] ?? ''));
            if (!is_numeric($valor) || (float)$valor <= 0) {
                throw new RuntimeException('Informe um valor de pedido válido.');
            }

            $stmt = $conn->prepare('INSERT INTO pedidos (valor) VALUES (?)');
            $stmt->execute([$valor]);
            $mensagem = 'Pedido registrado no caixa.';
            $tipoMensagem = 'sucesso';
        }
    }

    $usuarios = $conn->query('SELECT id, username, email, is_active FROM users_login ORDER BY username')->fetchAll(PDO::FETCH_ASSOC);
    $resumo = $conn->query("SELECT COUNT(*) AS pedidos, COALESCE(SUM(valor), 0) AS faturamento FROM pedidos WHERE DATE(criado_em) = CURDATE()")->fetch(PDO::FETCH_ASSOC);
    $equipe = $conn->query('SELECT COUNT(*) FROM users_login WHERE is_active = 1')->fetchColumn();
} catch (RuntimeException $e) {
    $mensagem = $e->getMessage();
    $tipoMensagem = 'erro';
    $usuarios = $usuarios ?? [];
    $resumo = $resumo ?? ['pedidos' => 0, 'faturamento' => 0];
    $equipe = $equipe ?? 0;
} catch (PDOException $e) {
    $mensagem = 'Não foi possível carregar os dados do painel.';
    $tipoMensagem = 'erro';
    $usuarios = [];
    $resumo = ['pedidos' => 0, 'faturamento' => 0];
    $equipe = 0;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel administrativo - Dogão Lanches</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <main class="painel">
        <header class="topo">
            <div>
                <p class="marca">🌭 DOGÃO LANCHES</p>
                <h1>Painel administrativo</h1>
                <p class="boas-vindas">Olá, <?php echo htmlspecialchars($_SESSION['usuario']); ?>. Acompanhe o movimento de hoje.</p>
            </div>
            <a class="sair" href="../public/index.php">Sair</a>
        </header>

        <?php if ($mensagem): ?>
            <div class="alerta <?php echo $tipoMensagem; ?>"><?php echo htmlspecialchars($mensagem); ?></div>
        <?php endif; ?>

        <section class="cards" aria-label="Resumo do dia">
            <article class="card"><span>🧾</span><div><p>Pedidos hoje</p><strong><?php echo (int)$resumo['pedidos']; ?></strong></div></article>
            <article class="card"><span>💰</span><div><p>Dinheiro no caixa</p><strong>R$ <?php echo number_format((float)$resumo['faturamento'], 2, ',', '.'); ?></strong></div></article>
            <article class="card"><span>👥</span><div><p>Pessoas trabalhando</p><strong><?php echo (int)$equipe; ?></strong></div></article>
        </section>

        <section class="grade">
            <article class="bloco usuarios">
                <div class="titulo-bloco"><div><p class="etiqueta">EQUIPE</p><h2>Usuários cadastrados</h2></div><span><?php echo count($usuarios); ?> usuários</span></div>
                <div class="lista-usuarios">
                    <?php foreach ($usuarios as $usuario): ?>
                        <form method="POST" class="linha-usuario">
                            <div class="avatar"><?php echo strtoupper(htmlspecialchars(mb_substr($usuario['username'], 0, 1))); ?></div>
                            <div class="nome"><strong><?php echo htmlspecialchars($usuario['username']); ?></strong><small><?php echo $usuario['is_active'] ? 'Ativo' : 'Inativo'; ?></small></div>
                            <input type="hidden" name="usuario_id" value="<?php echo (int)$usuario['id']; ?>">
                            <input type="email" name="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" aria-label="E-mail de <?php echo htmlspecialchars($usuario['username']); ?>" required>
                            <button type="submit" name="salvar_email">Salvar</button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </article>

            <aside class="bloco caixa">
                <p class="etiqueta">CAIXA</p>
                <h2>Registrar pedido</h2>
                <p class="descricao">Adicione o valor recebido para atualizar os indicadores do dia.</p>
                <form method="POST">
                    <label for="valor">Valor do pedido</label>
                    <div class="campo-valor"><span>R$</span><input id="valor" name="valor" inputmode="decimal" placeholder="0,00" required></div>
                    <button type="submit" name="registrar_pedido" class="botao-principal">Adicionar pedido</button>
                </form>
            </aside>
        </section>
    </main>
</body>
</html>
