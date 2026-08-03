<?php
/**
 * Painel de Caixa e Pedidos - Dogão Lanches
 * Permite que a recepção, gerentes e administradores gerenciem o fluxo financeiro 
 * das mesas, visualizem itens dinâmicos do cardápio e fechem contas.
 */

require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Pedidos.php';
require_once __DIR__ . '/../src/Cardapio.php';
require_once __DIR__ . '/../config/conexao.php';

// Inicialização e segurança da sessão
Auth::iniciarSessao();
Auth::requirePedidosView();

// Definição de permissões com base no papel do usuário logado
$mensagem = '';
$tipoMensagem = '';
$pedidoEditando = null;
$podeEditar = Auth::isRecepcao() || Auth::isAdmin() || Auth::isGerente();
$podeZerarDia = Auth::isAdmin() || Auth::isGerente();

try {
    // Garante que a estrutura física de tabelas está pronta no banco
    Pedidos::garantirTabela($conn);
    Cardapio::garantirTabelas($conn);

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

            // Remove em cascata apenas os pedidos iniciados na data atual
            $stmt = $conn->prepare('DELETE FROM pedidos WHERE DATE(criado_em) = CURDATE()');
            $stmt->execute();
            
            $mensagem = $stmt->rowCount() . ' pedido(s) de hoje foram removidos e o dia foi zerado.';
            $tipoMensagem = 'sucesso';
        } 
        
        // AÇÕES DE ESCRITA: Exigem nível de edição ativo no servidor
        else {
            Auth::requireEditarPedidos();

            // AÇÃO: Excluir Pedido Individual
            if (isset($_POST['excluir_pedido'])) {
                $id = filter_input(INPUT_POST, 'pedido_id', FILTER_VALIDATE_INT);
                if (!$id) {
                    throw new RuntimeException('Pedido inválido.');
                }

                $stmt = $conn->prepare('DELETE FROM pedidos WHERE id = :id');
                $stmt->execute([':id' => $id]);
                
                if ($stmt->rowCount() === 0) {
                    throw new RuntimeException('Pedido não encontrado.');
                }
                
                $mensagem = 'Pedido excluído com sucesso.';
                $tipoMensagem = 'sucesso';
            } 
            
            // AÇÃO: Cadastrar ou Atualizar Pedido
            else {
                $mesaId = filter_input(INPUT_POST, 'mesa_id', FILTER_VALIDATE_INT);
                $valor = str_replace(',', '.', trim($_POST['valor'] ?? ''));
                $status = $_POST['status_pagamento'] ?? '';

                if (!$mesaId) {
                    throw new RuntimeException('Selecione uma mesa válida.');
                }
                if (!is_numeric($valor) || (float) $valor <= 0) {
                    throw new RuntimeException('Informe um valor de pedido válido.');
                }
                if (!in_array($status, Pedidos::statusValidos(), true)) {
                    throw new RuntimeException('Selecione um status de pagamento válido.');
                }

                // Inserir Novo Pedido
                if (isset($_POST['adicionar_pedido'])) {
                    $stmt = $conn->prepare('INSERT INTO pedidos (mesa_id, status_pagamento) VALUES (:mesa, :status)');
                    $stmt->execute([':mesa' => $mesaId, ':status' => $status]);
                    
                    $mensagem = 'Pedido registrado com sucesso.';
                    $tipoMensagem = 'sucesso';
                }

                // Atualizar Pedido Existente
                if (isset($_POST['atualizar_pedido'])) {
                    $id = filter_input(INPUT_POST, 'pedido_id', FILTER_VALIDATE_INT);
                    if (!$id) {
                        throw new RuntimeException('Pedido inválido.');
                    }

                    $stmt = $conn->prepare('UPDATE pedidos SET mesa_id = :mesa, status_pagamento = :status WHERE id = :id');
                    $stmt->execute([':mesa' => $mesaId, ':status' => $status, ':id' => $id]);
                    
                    $mensagem = 'Pedido atualizado com sucesso.';
                    $tipoMensagem = 'sucesso';
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // CARREGAMENTO DOS DADOS PARA EDICÃO (GET)
    // -------------------------------------------------------------------------
    if ($podeEditar && isset($_GET['editar'])) {
        $idEditar = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT);
        if ($idEditar) {
            $stmt = $conn->prepare('SELECT id, mesa_id, status_pagamento FROM pedidos WHERE id = ?');
            $stmt->execute([$idEditar]);
            $pedidoEditando = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    }

    // Busca opcional de mesas para alimentar o combobox do formulário
    $mesasDisponiveis = $conn->query('SELECT id, numero FROM tables ORDER BY numero ASC')->fetchAll(PDO::FETCH_ASSOC);

    // -------------------------------------------------------------------------
    // CONSTRUÇÃO DA LISTAGEM DE PEDIDOS (INTEGRADA COM CARDÁPIO)
    // -------------------------------------------------------------------------
    $queryPedidos = "
        SELECT p.id, 
               m.numero AS mesa_numero, 
               p.status_pagamento, 
               p.criado_em,
               COALESCE(SUM(pi.preco_unitario * pi.quantidade), 0) AS valor,
               GROUP_CONCAT(CONCAT(pi.quantidade, 'x ', pr.nome) ORDER BY pi.id SEPARATOR ' • ') AS itens
        FROM pedidos p 
        INNER JOIN tables m ON m.id = p.mesa_id
        LEFT JOIN pedido_itens pi ON pi.pedido_id = p.id
        LEFT JOIN produtos pr ON pr.id = pi.produto_id
        GROUP BY p.id, m.numero, p.status_pagamento, p.criado_em
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
    $mesasDisponiveis = $mesasDisponiveis ?? [];
    $resumoDia = $resumoDia ?? ['pago' => 0, 'pendente' => 0];
} catch (PDOException $e) {
    $mensagem = 'Não foi possível carregar os pedidos estruturados.';
    $tipoMensagem = 'erro';
    $pedidos = [];
    $mesasDisponiveis = [];
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
                    <?php echo $podeEditar ? 'Você pode registrar e atualizar os pedidos.' : 'Você está visualizando os pedidos em modo leitura.'; ?>
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
                                                <a class="btn-editar" href="?editar=<?php echo (int) $pedido['id']; ?>">Editar</a>
                                                <form method="POST" class="inline" onsubmit="return confirm('Excluir este pedido permanentemente?');">
                                                    <input type="hidden" name="pedido_id" value="<?php echo (int) $pedido['id']; ?>">
                                                    <button type="submit" name="excluir_pedido" class="btn-excluir">Excluir</button>
                                                </form>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </article>

            <!-- Painel Lateral Direitado (Formulários de Ação Administrativa) -->
            <?php if ($podeEditar): ?>
                <aside class="bloco formularios">
                    
                    <!-- Formulário de Criação/Edição Manual de Comandas -->
                    <div class="formulario-card">
                        <p class="etiqueta"><?php echo $pedidoEditando ? 'EDITAR PEDIDO' : 'NOVO PEDIDO'; ?></p>
                        <h2><?php echo $pedidoEditando ? 'Editar pedido' : 'Registrar pedido'; ?></h2>
                        <form method="POST">
                            <?php if ($pedidoEditando): ?>
                                <input type="hidden" name="pedido_id" value="<?php echo (int) $pedidoEditando['id']; ?>">
                            <?php endif; ?>

                            <label for="mesa_id">Selecione a Mesa</label>
                            <select id="mesa_id" name="mesa_id" required>
                                <option value="">Selecione</option>
                                <?php foreach ($mesasDisponiveis as $m): ?>
                                    <option value="<?php echo (int) $m['id']; ?>" <?php echo (($pedidoEditando['mesa_id'] ?? '') == $m['id']) ? 'selected' : ''; ?>>
                                        Mesa <?php echo (int) $m['numero']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <label for="status_pagamento">Status do pagamento</label>
                            <?php $statusSelecionado = $pedidoEditando['status_pagamento'] ?? ($_POST['status_pagamento'] ?? Pedidos::PENDENTE); ?>
                            <select id="status_pagamento" name="status_pagamento" required>
                                <option value="pendente" <?php echo $statusSelecionado === Pedidos::PENDENTE ? 'selected' : ''; ?>>Ainda não pago</option>
                                <option value="pago" <?php echo $statusSelecionado === Pedidos::PAGO ? 'selected' : ''; ?>>Pago</option>
                            </select>

                            <button type="submit" name="<?php echo $pedidoEditando ? 'atualizar_pedido' : 'adicionar_pedido'; ?>" class="botao-principal">
                                <?php echo $pedidoEditando ? 'Salvar alterações' : 'Registrar pedido'; ?>
                            </button>

                            <?php if ($pedidoEditando): ?>
                                <a class="btn-cancelar" href="pedidos.php">Cancelar edição</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- Botão de Segurança: Limpeza de Caixa Diário -->
                    <?php if ($podeZerarDia): ?>
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
                    <?php endif; ?>

                </aside>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>