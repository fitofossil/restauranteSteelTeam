<?php
/**
 * Painel de Caixa - Dogão Lanches
 * O caixa apenas acompanha os pedidos enviados pelo garçom e marca cada um
 * como pago ou ainda não pago. A criação de pedidos com itens é função do garçom.
 */

require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Pedidos.php';
require_once __DIR__ . '/../src/Cardapio.php';
require_once __DIR__ . '/../src/Log.php';
require_once __DIR__ . '/../config/conexao.php';

// Inicialização e segurança da sessão
Auth::iniciarSessao();
Auth::requirePedidosView();

// Definição de permissões com base no papel do usuário logado
$mensagem = '';
$tipoMensagem = '';
$podeEditar = Auth::isRecepcao() || Auth::isAdmin() || Auth::isGerente();
$podeZerarDia = Auth::isAdmin() || Auth::isGerente();

try {
    // Garante que a estrutura física de tabelas está pronta no banco
    Pedidos::garantirTabela($conn);
    Cardapio::garantirTabelas($conn);
    Log::garantirTabela($conn);

    // -------------------------------------------------------------------------
    // PROCESSAMENTO DOS FORMULÁRIOS (POST)
    // -------------------------------------------------------------------------
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // AÇÃO CRÍTICA: Zerar o dia (Apenas Admin/Gerente)
        if (isset($_POST['zerar_dia'])) {
            Auth::requireZerarCaixa();
            $senha = $_POST['senha_confirmacao'] ?? '';
            
            // Validação de segurança por senha
            $stmt = $conn->prepare('SELECT password_hash FROM users_login WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => Auth::getId()]);
            $hash = $stmt->fetchColumn();

            if (!$hash || !password_verify($senha, $hash)) {
                throw new RuntimeException('Senha incorreta. O dia não foi zerado.');
            }

            // Remove os itens e, em cascata, os pedidos iniciados na data atual
            $stmt = $conn->prepare('DELETE FROM pedido_itens WHERE pedido_id IN (SELECT id FROM pedidos WHERE DATE(criado_em) = CURDATE())');
            $stmt->execute();
            $stmt = $conn->prepare('DELETE FROM pedidos WHERE DATE(criado_em) = CURDATE()');
            $stmt->execute();
            $removidos = $stmt->rowCount();
            Log::registrar($conn, 'delete', 'pedidos', null, "$removidos pedido(s) de hoje removidos (zerar dia).");
            
            $mensagem = $removidos . ' pedido(s) de hoje foram removidos e o dia foi zerado.';
            $tipoMensagem = 'sucesso';
        } 
        
        // AÇÃO: Marcar Pedido como Pago ou Pendente (função exclusiva do caixa)
        else {
            Auth::requireEditarPedidos();

            $id = filter_input(INPUT_POST, 'pedido_id', FILTER_VALIDATE_INT);
            $status = $_POST['status_pagamento'] ?? '';

            if (!$id) {
                throw new RuntimeException('Pedido inválido.');
            }
            if (!in_array($status, Pedidos::statusValidos(), true)) {
                throw new RuntimeException('Selecione um status de pagamento válido.');
            }

            $stmt = $conn->prepare('UPDATE pedidos SET status_pagamento = :status WHERE id = :id');
            $stmt->execute([':status' => $status, ':id' => $id]);

            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('Pedido não encontrado.');
            }

            Log::registrar($conn, 'update', 'pedidos', $id, "Pagamento do pedido #$id marcado como '$status'.");
            $mensagem = $status === Pedidos::PAGO ? 'Pedido marcado como pago.' : 'Pedido marcado como ainda não pago.';
            $tipoMensagem = 'sucesso';
        }
    }

    // -------------------------------------------------------------------------
    // CONSTRUÇÃO DA LISTAGEM DE PEDIDOS (INTEGRADA COM CARDÁPIO)
    // -------------------------------------------------------------------------
    $queryPedidos = "
        SELECT p.id, 
               p.mesa_numero, 
               p.status_pagamento, 
               p.criado_em,
               COALESCE(SUM(pi.preco_unitario * pi.quantidade), 0) AS valor,
               GROUP_CONCAT(CONCAT(pi.quantidade, 'x ', pr.nome) ORDER BY pi.id SEPARATOR ' • ') AS itens
        FROM pedidos p 
        LEFT JOIN pedido_itens pi ON pi.pedido_id = p.id
        LEFT JOIN produtos pr ON pr.id = pi.produto_id
        GROUP BY p.id, p.mesa_numero, p.status_pagamento, p.criado_em
        ORDER BY p.criado_em ASC, p.id ASC
    ";
    
    $pedidosOrdenados = $conn->query($queryPedidos)->fetchAll(PDO::FETCH_ASSOC);
    
    // Gera numeração sequencial diária por software (#1, #2, #3...)
    $sequenciaPorDia = [];
    foreach ($pedidosOrdenados as &$pedido) {
        $dataPedido = date('Y-m-d', strtotime($pedido['criado_em']));
        $sequenciaPorDia[$dataPedido] = ($sequenciaPorDia[$dataPedido] ?? 0) + 1;
        $pedido['numero_do_dia'] = $sequenciaPorDia[$dataPedido];
    }
    unset($pedido);

    // Inverte o array para exibir os pedidos mais recentes sempre no topo da tela
    $pedidos = array_reverse($pedidosOrdenados);

    // Consolidação financeira do caixa de hoje com base no preço histórico calculado
    $queryResumo = "
        SELECT 
            COALESCE(SUM(CASE WHEN p.status_pagamento = 'pago' THEN pi.preco_unitario * pi.quantidade ELSE 0 END), 0) AS pago,
            COALESCE(SUM(CASE WHEN p.status_pagamento = 'pendente' THEN pi.preco_unitario * pi.quantidade ELSE 0 END), 0) AS pendente
        FROM pedidos p
        LEFT JOIN pedido_itens pi ON pi.pedido_id = p.id
        WHERE DATE(p.criado_em) = CURDATE()
    ";
    $resumoDia = $conn->query($queryResumo)->fetch(PDO::FETCH_ASSOC);

} catch (RuntimeException $e) {
    $mensagem = $e->getMessage();
    $tipoMensagem = 'erro';
    $pedidos = $pedidos ?? [];
    $resumoDia = $resumoDia ?? ['pago' => 0, 'pendente' => 0];
} catch (PDOException $e) {
    $mensagem = 'Não foi possível carregar os pedidos estruturados.';
    $tipoMensagem = 'erro';
    $pedidos = [];
    $resumoDia = ['pago' => 0, 'pendente' => 0];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAIXA - Dogão Lanches</title>
    <link rel="stylesheet" href="../public/css/style.css">
</head>
<body>
    <main class="painel">
        
        <!-- Topo e Boas-Vindas Moduladas por Nível de Acesso -->
        <header class="topo">
            <div>
                <p class="marca">🌭 DOGÃO LANCHES</p>
                <h1>CAIXA</h1>
                <p class="boas-vindas">
                    <?php echo $podeEditar ? 'Você pode marcar os pedidos como pago ou ainda não pago.' : 'Você está visualizando os pedidos em modo leitura.'; ?>
                </p>
            </div>
            <div class="topo-botoes">
                <a class="btn-topo" href="painel.php">← Voltar ao Panel</a>
                <a class="btn-topo" href="../logout.php">Sair</a>
            </div>
        </header>

        <!-- Bloco de Alertas Flash -->
        <?php if ($mensagem): ?>
            <div class="alerta <?php echo $tipoMensagem; ?>">
                <?php echo htmlspecialchars($mensagem); ?>
            </div>
        <?php endif; ?>

        <!-- Cards Financeiros Dinâmicos -->
        <section class="cards" aria-label="Resumo financeiro de hoje">
            <article class="card">
                <span class="icone">💰</span>
                <div>
                    <p>Pago hoje</p>
                    <strong>R$ <?php echo number_format((float) $resumoDia['pago'], 2, ',', '.'); ?></strong>
                </div>
            </article>
            <article class="card">
                <span class="icone">🕒</span>
                <div>
                    <p>A receber hoje</p>
                    <strong>R$ <?php echo number_format((float) $resumoDia['pendente'], 2, ',', '.'); ?></strong>
                </div>
            </article>
        </section>

        <!-- Grade de Operações do Caixa -->
        <section class="grade pedidos-grade <?php echo $podeEditar ? '' : 'pedidos-leitura'; ?>">
            
            <!-- Listagem Geral de Pedidos em Tempo Real -->
            <article class="bloco lista">
                <div class="titulo-bloco">
                    <div>
                        <p class="etiqueta">CONTROLE DE MESAS</p>
                        <h2>Pedidos registrados</h2>
                    </div>
                    <span><?php echo count($pedidos); ?> pedidos</span>
                </div>

                <?php if (empty($pedidos)): ?>
                    <p class="vazio">Nenhum pedido registrado.</p>
                <?php else: ?>
                    <div class="tabela-wrap">
                        <table class="tabela">
                            <thead>
                                <tr>
                                    <th>Pedido</th>
                                    <th>Mesa</th>
                                    <th>Itens (Cardápio)</th>
                                    <th>Valor Total</th>
                                    <th>Pagamento</th>
                                    <th>Registrado em</th>
                                    <?php if ($podeEditar): ?><th>Ações</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pedidos as $pedido): ?>
                                    <tr>
                                        <td>#<?php echo (int) $pedido['numero_do_dia']; ?></td>
                                        <td><strong>Mesa <?php echo (int) $pedido['mesa_numero']; ?></strong></td>
                                        
                                        <!-- Renderiza os pratos oficiais inseridos via cardapio.php -->
                                        <td><?php echo htmlspecialchars($pedido['itens'] ?: 'Pedido sem itens'); ?></td>
                                        
                                        <td>R$ <?php echo number_format((float) $pedido['valor'], 2, ',', '.'); ?></td>
                                        <td>
                                            <span class="badge pagamento-<?php echo htmlspecialchars($pedido['status_pagamento']); ?>">
                                                <?php echo $pedido['status_pagamento'] === Pedidos::PAGO ? 'Pago' : 'Ainda não pago'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($pedido['criado_em']))); ?></td>
                                        
                                        <?php if ($podeEditar): ?>
                                            <td class="acoes">
                                                <?php if ($pedido['status_pagamento'] === Pedidos::PAGO): ?>
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="pedido_id" value="<?php echo (int) $pedido['id']; ?>">
                                                        <input type="hidden" name="status_pagamento" value="pendente">
                                                        <button type="submit" name="marcar_pagamento" class="btn-voltar-preparo">Marcar como não pago</button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="pedido_id" value="<?php echo (int) $pedido['id']; ?>">
                                                        <input type="hidden" name="status_pagamento" value="pago">
                                                        <button type="submit" name="marcar_pagamento" class="btn-pronto">Marcar como pago</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </article>

            <!-- Painel Lateral (Ações de Gestão) -->
            <?php if ($podeZerarDia): ?>
                <aside class="bloco formularios">

                    <!-- Botão de Segurança: Limpeza de Caixa Diário -->
                    <div class="formulario-card formulario-perigo">
                        <p class="etiqueta">AÇÃO DE GESTÃO</p>
                        <h2>Zerar o dia</h2>
                        <p>Remove todos os pedidos registrados hoje, pagos e pendentes. Esta ação não pode ser desfeita.</p>
                        <form method="POST" onsubmit="return confirm('Deseja realmente remover todos os pedidos de hoje?');">
                            <label for="senha_confirmacao">Confirme sua senha</label>
                            <input id="senha_confirmacao" type="password" name="senha_confirmacao" required>
                            <button type="submit" name="zerar_dia" class="btn-excluir btn-zerar">Zerar pedidos de hoje</button>
                        </form>
                    </div>

                </aside>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>