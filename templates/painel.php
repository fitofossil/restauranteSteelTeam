<?php
// =============================================================
// PAINEL ADMINISTRATIVO
// =============================================================
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Pedidos.php';
require_once __DIR__ . '/../src/Cardapio.php';
require_once __DIR__ . '/../config/conexao.php';

// A recepção é encaminhada ao caixa; os demais perfis podem abrir o painel.
Auth::iniciarSessao();
Auth::requirePainel();

$mensagem = '';
$tipoMensagem = '';
$limiteMesas = Pedidos::LIMITE_MESAS;

try {
    // A ação de manutenção da equipe no painel não permite editar e-mails.

    // Dados exibidos no painel: lista da equipe e quantidade de contas ativas.
    $usuarios = $conn->query('SELECT id, username, email, is_active FROM users_login ORDER BY username')->fetchAll(PDO::FETCH_ASSOC);
    $equipe = $conn->query('SELECT COUNT(*) FROM users_login WHERE is_active = 1')->fetchColumn();

    Pedidos::garantirTabela($conn);
    Cardapio::garantirTabelas($conn);

    $stmtMesas = $conn->prepare("SELECT COUNT(DISTINCT mesa_numero)
        FROM pedidos
        WHERE DATE(criado_em) = CURDATE()
          AND tipo_entrega = :tipo_mesa
          AND status_pagamento = :status_pendente
          AND mesa_numero BETWEEN 1 AND :limite_mesas");
    $stmtMesas->bindValue(':tipo_mesa', Pedidos::TIPO_MESA);
    $stmtMesas->bindValue(':status_pendente', Pedidos::PENDENTE);
    $stmtMesas->bindValue(':limite_mesas', $limiteMesas, PDO::PARAM_INT);
    $stmtMesas->execute();
    $mesasOcupadas = (int) $stmtMesas->fetchColumn();
    $mesasLivres = max(0, $limiteMesas - $mesasOcupadas);

    $resumoPreparo = $conn->query("SELECT
            SUM(status_preparo = 'aguardando') AS em_preparo,
            SUM(status_preparo = 'pronto') AS concluidos
        FROM pedidos
        WHERE DATE(criado_em) = CURDATE()")->fetch(PDO::FETCH_ASSOC);
    $pedidosEmPreparo = (int) ($resumoPreparo['em_preparo'] ?? 0);
    $pedidosConcluidos = (int) ($resumoPreparo['concluidos'] ?? 0);

    $faturamentoHoje = (float) $conn->query("SELECT COALESCE(SUM(pi.preco_unitario * pi.quantidade), 0)
        FROM pedidos p
        LEFT JOIN pedido_itens pi ON pi.pedido_id = p.id
        WHERE DATE(p.criado_em) = CURDATE()
          AND p.status_pagamento = 'pago'")->fetchColumn();

    $produtosMaisVendidos = $conn->query("SELECT
            pi.produto_nome,
            SUM(pi.quantidade) AS quantidade_vendida,
            SUM(pi.quantidade * pi.preco_unitario) AS total_vendido
        FROM pedidos p
        INNER JOIN pedido_itens pi ON pi.pedido_id = p.id
        WHERE DATE(p.criado_em) = CURDATE()
          AND p.status_pagamento = 'pago'
        GROUP BY pi.produto_id, pi.produto_nome
        ORDER BY quantidade_vendida DESC, total_vendido DESC
        LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

    $relatorioVendas = $conn->query("SELECT
            p.id,
            p.criado_em,
            p.tipo_entrega,
            p.mesa_numero,
            p.forma_pagamento,
            p.status_preparo,
            COALESCE(SUM(pi.preco_unitario * pi.quantidade), 0) AS total,
            COALESCE(SUM(pi.quantidade), 0) AS itens
        FROM pedidos p
        LEFT JOIN pedido_itens pi ON pi.pedido_id = p.id
        WHERE DATE(p.criado_em) = CURDATE()
          AND p.status_pagamento = 'pago'
        GROUP BY p.id, p.criado_em, p.tipo_entrega, p.mesa_numero, p.forma_pagamento, p.status_preparo
        ORDER BY p.criado_em DESC, p.id DESC")->fetchAll(PDO::FETCH_ASSOC);

} catch (RuntimeException $e) {
    $mensagem = $e->getMessage(); $tipoMensagem = 'erro';
    $usuarios = $usuarios ?? [];
    $equipe = $equipe ?? 0;
    $mesasOcupadas = $mesasOcupadas ?? 0;
    $mesasLivres = $mesasLivres ?? $limiteMesas;
    $pedidosEmPreparo = $pedidosEmPreparo ?? 0;
    $pedidosConcluidos = $pedidosConcluidos ?? 0;
    $faturamentoHoje = $faturamentoHoje ?? 0;
    $produtosMaisVendidos = $produtosMaisVendidos ?? [];
    $relatorioVendas = $relatorioVendas ?? [];
} catch (PDOException $e) {
    $mensagem = 'Não foi possível carregar os dados do painel.'; $tipoMensagem = 'erro';
    $usuarios = [];
    $equipe = 0;
    $mesasOcupadas = 0;
    $mesasLivres = $limiteMesas;
    $pedidosEmPreparo = 0;
    $pedidosConcluidos = 0;
    $faturamentoHoje = 0;
    $produtosMaisVendidos = [];
    $relatorioVendas = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo - Dogão Lanches</title>
    <link rel="stylesheet" href="../public/css/style.css">
</head>
<body>
    <main class="painel">
        <header class="topo">
            <div>
                <p class="marca">🌭 DOGÃO LANCHES</p>
                <h1>Painel administrativo</h1>
                <p class="boas-vindas">Olá, <?php echo Auth::sanitizarTexto(Auth::getNome()); ?>. Acompanhe o movimento de hoje.</p>
            </div>
            <div class="topo-botoes">
                <?php if (Auth::isAdmin()): ?>
                    <a class="btn-topo destaque" href="crud.php">Cadastro Funcionários</a>
                    <a class="btn-topo destaque" href="logs.php">Logs</a>
                <?php endif; ?>
                <?php if (Auth::isAdmin() || Auth::isGerente() || Auth::isRecepcao()): ?>
                    <a class="btn-topo destaque" href="pedidos.php">Caixa</a>
                <?php endif; ?>
                <?php if (Auth::isAdmin() || Auth::isGerente()): ?>
                    <a class="btn-topo destaque" href="cozinha.php">Cozinha</a>
                    <a class="btn-topo destaque" href="garcom.php">Garçom</a>
                    <a class="btn-topo destaque" href="cardapio.php">Cardápio</a>
                <?php endif; ?>
                <a class="btn-topo" href="../logout.php">Sair</a>
            </div>
        </header>

        <?php if ($mensagem): ?>
            <div class="alerta <?php echo $tipoMensagem; ?>"><?php echo htmlspecialchars($mensagem); ?></div>
        <?php endif; ?>

        <section class="cards cards-gerencial" aria-label="Indicadores operacionais do dia">
            <article class="card"><span class="icone">🪑</span><div><p>Mesas ocupadas</p><strong><?php echo (int) $mesasOcupadas; ?></strong></div></article>
            <article class="card"><span class="icone">✨</span><div><p>Mesas livres</p><strong><?php echo (int) $mesasLivres; ?></strong></div></article>
            <article class="card"><span class="icone">👨‍🍳</span><div><p>Pedidos em preparo</p><strong><?php echo (int) $pedidosEmPreparo; ?></strong></div></article>
            <article class="card"><span class="icone">✅</span><div><p>Pedidos concluídos</p><strong><?php echo (int) $pedidosConcluidos; ?></strong></div></article>
            <article class="card"><span class="icone">💰</span><div><p>Faturamento do dia</p><strong>R$ <?php echo number_format((float) $faturamentoHoje, 2, ',', '.'); ?></strong></div></article>
            <article class="card"><span class="icone">👥</span><div><p>Pessoas trabalhando</p><strong><?php echo (int) $equipe; ?></strong></div></article>
        </section>

        <section class="grade painel-relatorios">
            <article class="bloco usuarios">
                <div class="titulo-bloco"><div><p class="etiqueta">VENDAS</p><h2>Produtos mais vendidos hoje</h2></div><span>Top 5</span></div>
                <?php if (!$produtosMaisVendidos): ?>
                    <p class="vazio">Nenhuma venda paga registrada hoje.</p>
                <?php else: ?>
                    <div class="tabela-wrap">
                        <table class="tabela">
                            <thead>
                                <tr>
                                    <th>Produto</th>
                                    <th>Qtd.</th>
                                    <th>Total vendido</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($produtosMaisVendidos as $produto): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string) ($produto['produto_nome'] ?? 'Produto')); ?></td>
                                        <td><?php echo (int) ($produto['quantidade_vendida'] ?? 0); ?></td>
                                        <td>R$ <?php echo number_format((float) ($produto['total_vendido'] ?? 0), 2, ',', '.'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </article>

            <article class="bloco usuarios">
                <div class="titulo-bloco"><div><p class="etiqueta">RELATÓRIO</p><h2>Vendas de hoje</h2></div><span><?php echo count($relatorioVendas); ?> pedidos pagos</span></div>
                <?php if (!$relatorioVendas): ?>
                    <p class="vazio">Nenhuma venda concluída hoje.</p>
                <?php else: ?>
                    <div class="tabela-wrap">
                        <table class="tabela">
                            <thead>
                                <tr>
                                    <th>Pedido</th>
                                    <th>Horário</th>
                                    <th>Origem</th>
                                    <th>Forma</th>
                                    <th>Itens</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($relatorioVendas as $venda): ?>
                                    <tr>
                                        <td>#<?php echo (int) ($venda['id'] ?? 0); ?></td>
                                        <td><?php echo htmlspecialchars(date('H:i', strtotime((string) ($venda['criado_em'] ?? 'now')))); ?></td>
                                        <td>
                                            <?php
                                                $tipo = (string) ($venda['tipo_entrega'] ?? Pedidos::TIPO_MESA);
                                                if ($tipo === Pedidos::TIPO_MESA) {
                                                    echo 'Mesa ' . (int) ($venda['mesa_numero'] ?? 0);
                                                } elseif ($tipo === Pedidos::TIPO_VIAGEM) {
                                                    echo 'Retirada';
                                                } else {
                                                    echo 'Entrega';
                                                }
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars(Pedidos::textoFormaPagamento((string) ($venda['forma_pagamento'] ?? null))); ?></td>
                                        <td><?php echo (int) ($venda['itens'] ?? 0); ?></td>
                                        <td>R$ <?php echo number_format((float) ($venda['total'] ?? 0), 2, ',', '.'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </article>
        </section>

        <section class="grade">
            <article class="bloco usuarios">
                <div class="titulo-bloco"><div><p class="etiqueta">EQUIPE</p><h2>Usuários cadastrados</h2></div><span><?php echo count($usuarios); ?> usuários</span></div>
                <div class="lista-usuarios">
                    <?php foreach ($usuarios as $usuario): ?>
                        <div class="linha-usuario">
                            <div class="avatar"><?php echo strtoupper(htmlspecialchars(mb_substr($usuario['username'], 0, 1))); ?></div>
                            <div class="nome"><strong><?php echo htmlspecialchars($usuario['username']); ?></strong><small><?php echo $usuario['is_active'] ? 'Ativo' : 'Inativo'; ?></small></div>
                            <div class="email-usuario"><?php echo htmlspecialchars($usuario['email']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>
        </section>
    </main>
</body>
</html>
