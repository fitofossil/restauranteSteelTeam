<?php
// =============================================================
// PAINEL ADMINISTRATIVO — DOGÃO LANCHES
// =============================================================
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Pedidos.php';
require_once __DIR__ . '/../config/conexao.php';

// Segurança: A recepção é encaminhada ao caixa; os demais perfis podem abrir o painel principal.
Auth::iniciarSessao();
Auth::requirePainel();

$mensagem = '';
$tipoMensagem = '';

// Permissão de visualização: Apenas gerentes, admins ou recepção veem o faturamento financeiro.
$mostrarResumoPedidos = Auth::isAdmin() || Auth::isGerente() || Auth::isRecepcao();

try {
    // -------------------------------------------------------------------------
    // CONSULTAS BASE DO PAINEL
    // -------------------------------------------------------------------------
    
    // Busca a lista da equipe para montar os cards de usuários
    $usuarios = $conn->query('SELECT id, username, email, is_active FROM users_login ORDER BY username')->fetchAll(PDO::FETCH_ASSOC);
    
    // Conta quantas pessoas da equipe estão com o login ativo
    $equipe = $conn->query('SELECT COUNT(*) FROM users_login WHERE is_active = 1')->fetchColumn();

    // -------------------------------------------------------------------------
    // CÁLCULO FINANCEIRO (Apenas para perfis autorizados)
    // -------------------------------------------------------------------------
    if ($mostrarResumoPedidos) {
        Pedidos::garantirTabela($conn);
        
        // Soma o valor multiplicando quantidade e preço unitário dos itens.
        // O COALESCE garante que, se não houver vendas, retorne 0 ao invés de Nulo.
        $queryFaturamento = "
            SELECT COALESCE(SUM(pi.quantidade * pi.preco_unitario), 0.00) 
            FROM pedidos p 
            LEFT JOIN pedido_itens pi ON pi.pedido_id = p.id 
            WHERE DATE(p.criado_em) = CURDATE() 
              AND p.status_pagamento = 'pago'
        ";
        
        $faturamentoHoje = $conn->query($queryFaturamento)->fetchColumn();
    }

} catch (RuntimeException $e) {
    $mensagem = $e->getMessage(); 
    $tipoMensagem = 'erro';
    $usuarios = $usuarios ?? []; 
    $equipe = $equipe ?? 0; 
    $faturamentoHoje = $faturamentoHoje ?? 0;
} catch (PDOException $e) {
    $mensagem = 'Não foi possível carregar os dados do painel.'; 
    $tipoMensagem = 'erro';
    $usuarios = []; 
    $equipe = 0; 
    $faturamentoHoje = 0;
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
        
        <!-- Cabeçalho de Navegação Inteligente (Mostra apenas botões autorizados) -->
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
                    <a class="btn-topo destaque" href="pedidos.php">Pedidos</a>
                <?php endif; ?>
                
                <?php if (Auth::isAdmin() || Auth::isGerente()): ?>
                    <a class="btn-topo destaque" href="cozinha.php">Cozinha</a>
                    <a class="btn-topo destaque" href="garcom.php">Garçom</a>
                    <a class="btn-topo destaque" href="cardapio.php">Cardápio</a>
                <?php endif; ?>
                
                <a class="btn-topo" href="../logout.php">Sair</a>
            </div>
        </header>

        <!-- Exibição de Alertas -->
        <?php if ($mensagem): ?>
            <div class="alerta <?php echo $tipoMensagem; ?>">
                <?php echo htmlspecialchars($mensagem); ?>
            </div>
        <?php endif; ?>

        <!-- Indicadores Rápidos (Cards Superiores) -->
        <!-- A classe 'cards-uma' centraliza o bloco caso o usuário não tenha permissão de ver os valores. -->
        <section class="cards <?php echo $mostrarResumoPedidos ? '' : 'cards-uma'; ?>" aria-label="Resumo do dia">
            
            <?php if ($mostrarResumoPedidos): ?>
                <article class="card">
                    <span class="icone">💰</span>
                    <div>
                        <p>Valor pago hoje</p>
                        <strong>R$ <?php echo number_format((float) ($faturamentoHoje ?? 0), 2, ',', '.'); ?></strong>
                    </div>
                </article>
            <?php endif; ?>
            
            <article class="card">
                <span class="icone">👥</span>
                <div>
                    <p>Pessoas trabalhando</p>
                    <strong><?php echo (int) $equipe; ?></strong>
                </div>
            </article>
            
        </section>

        <!-- Grade Principal de Listagem -->
        <section class="grade">
            
            <!-- Lista da Equipe Cadastrada -->
            <article class="bloco usuarios">
                <div class="titulo-bloco">
                    <div>
                        <p class="etiqueta">EQUIPE</p>
                        <h2>Usuários cadastrados</h2>
                    </div>
                    <span><?php echo count($usuarios); ?> usuários</span>
                </div>
                
                <div class="lista-usuarios">
                    <?php foreach ($usuarios as $usuario): ?>
                        <div class="linha-usuario">
                            <!-- Pega a primeira letra do nome de usuário para o Avatar -->
                            <div class="avatar">
                                <?php echo strtoupper(htmlspecialchars(mb_substr($usuario['username'], 0, 1))); ?>
                            </div>
                            <div class="nome">
                                <strong><?php echo htmlspecialchars($usuario['username']); ?></strong>
                                <small><?php echo $usuario['is_active'] ? 'Ativo' : 'Inativo'; ?></small>
                            </div>
                            <div class="email-usuario">
                                <?php echo htmlspecialchars($usuario['email']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>

        </section>
    </main>
</body>
</html>