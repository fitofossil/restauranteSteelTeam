<?php
// =============================================================
// PAINEL DO GARÇOM — DOGÃO LANCHES
// =============================================================
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Pedidos.php';
require_once __DIR__ . '/../src/Cardapio.php';
require_once __DIR__ . '/../src/Log.php';
require_once __DIR__ . '/../config/conexao.php';

Auth::iniciarSessao();
Auth::requireGarcom();

$mensagem = '';
$tipoMensagem = '';

try {
    // Garante que as tabelas existem
    Pedidos::garantirTabela($conn);
    Cardapio::garantirTabelas($conn);
    Log::garantirTabela($conn);

    // =========================================================
    // PROCESSAMENTO DO NOVO PEDIDO (POST)
    // =========================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar_pedido'])) {
        // Recebe a chave primária (ID) da mesa, e não mais um número solto
        $mesaId = filter_input(INPUT_POST, 'mesa_id', FILTER_VALIDATE_INT);
        $quantidadesRecebidas = $_POST['quantidades'] ?? [];
        $quantidades = [];

        if (!$mesaId) {
            throw new RuntimeException('Selecione uma mesa válida.');
        }
        if (!is_array($quantidadesRecebidas)) {
            throw new RuntimeException('Itens do pedido inválidos.');
        }

        // Filtra apenas os pratos que tiveram quantidade maior que zero
        foreach ($quantidadesRecebidas as $produtoId => $quantidade) {
            $id = filter_var($produtoId, FILTER_VALIDATE_INT);
            $qtd = filter_var($quantidade, FILTER_VALIDATE_INT);
            if ($id && $qtd && $qtd > 0 && $qtd <= 99) {
                $quantidades[$id] = $qtd;
            }
        }

        if (empty($quantidades)) {
            throw new RuntimeException('Informe a quantidade de pelo menos um prato.');
        }

        // Busca os dados oficiais dos produtos selecionados para garantir que existem e estão ativos
        $marcadores = implode(',', array_fill(0, count($quantidades), '?'));
        $stmt = $conn->prepare("SELECT id, nome, preco FROM produtos WHERE ativo = 1 AND id IN ($marcadores)");
        $stmt->execute(array_keys($quantidades));
        $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($produtos) !== count($quantidades)) {
            throw new RuntimeException('Um dos pratos selecionados não está mais disponível no cardápio.');
        }

        // Inicia a transação para garantir que pedido e itens sejam salvos juntos
        $conn->beginTransaction();
        
        // 1. Grava o cabeçalho do pedido (sem a coluna 'valor')
        $stmt = $conn->prepare("INSERT INTO pedidos (mesa_id, status_pagamento, status_preparo) VALUES (?, ?, ?)");
        $stmt->execute([$mesaId, Pedidos::PENDENTE, Pedidos::AGUARDANDO_PREPARO]);
        $pedidoId = (int) $conn->lastInsertId();
        
        $totalLog = 0; // Calculado apenas para salvar no histórico/log

        // 2. Grava os itens do pedido (sem a coluna 'produto_nome')
        $itemStmt = $conn->prepare('INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES (?, ?, ?, ?)');
        foreach ($produtos as $produto) {
            $qtdItem = $quantidades[(int) $produto['id']];
            $precoItem = $produto['preco'];
            
            $itemStmt->execute([$pedidoId, $produto['id'], $qtdItem, $precoItem]);
            $totalLog += ((float) $precoItem * $qtdItem);
        }
        
        $conn->commit();
        
        // Busca o número da mesa real apenas para deixar o log legível
        $numeroMesa = $conn->query("SELECT numero FROM mesas WHERE id = $mesaId")->fetchColumn();
        Log::registrar($conn, 'insert', 'pedidos', $pedidoId, "Pedido #$pedidoId enviado pelo garçom — Mesa $numeroMesa, R$ " . number_format($totalLog, 2, ',', '.'));
        
        $mensagem = 'Pedido enviado para o caixa e para a cozinha.';
        $tipoMensagem = 'sucesso';
        $_POST = []; // Limpa o formulário
    }

    // =========================================================
    // CONSULTAS PARA A TELA (GET)
    // =========================================================
    
    // Busca as mesas cadastradas para o menu suspenso (Select)
    $mesasDisponiveis = $conn->query('SELECT id, numero FROM mesas ORDER BY numero ASC')->fetchAll(PDO::FETCH_ASSOC);

    // Busca os pratos ativos
    $produtosCardapio = $conn->query('SELECT id, nome, descricao, preco FROM produtos WHERE ativo = 1 ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);
    
    // Busca os pedidos do dia, agrupando os pratos e calculando o valor dinamicamente
    $pedidos = $conn->query("
        SELECT 
            p.id, 
            m.numero AS mesa_numero, 
            p.status_pagamento, 
            p.status_preparo, 
            p.criado_em,
            COALESCE(SUM(pi.quantidade * pi.preco_unitario), 0.00) AS valor,
            GROUP_CONCAT(CONCAT(pi.quantidade, 'x ', pr.nome) ORDER BY pi.id SEPARATOR ' • ') AS itens
        FROM pedidos p 
        INNER JOIN mesas m ON m.id = p.mesa_id
        LEFT JOIN pedido_itens pi ON pi.pedido_id = p.id
        LEFT JOIN produtos pr ON pr.id = pi.produto_id
        WHERE DATE(p.criado_em) = CURDATE()
        GROUP BY p.id, m.numero, p.status_pagamento, p.status_preparo, p.criado_em
        ORDER BY p.criado_em DESC, p.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (RuntimeException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    $mensagem = $e->getMessage(); 
    $tipoMensagem = 'erro'; 
    $produtosCardapio = $produtosCardapio ?? []; 
    $pedidos = $pedidos ?? [];
    $mesasDisponiveis = $mesasDisponiveis ?? [];
} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    $mensagem = 'Não foi possível registrar ou carregar os pedidos.'; 
    $tipoMensagem = 'erro'; 
    $produtosCardapio = []; 
    $pedidos = [];
    $mesasDisponiveis = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Garçom - Dogão Lanches</title>
    <link rel="stylesheet" href="../public/css/style.css">
</head>
<body>
    <main class="painel">
        
        <!-- Cabeçalho -->
        <header class="topo">
            <div>
                <p class="marca">🌭 DOGÃO LANCHES</p>
                <h1>GARÇOM</h1>
                <p class="boas-vindas">Monte o pedido da mesa para o caixa e a cozinha.</p>
            </div>
            <div class="topo-botoes">
                <?php if (Auth::isAdmin() || Auth::isGerente()): ?>
                    <a class="btn-topo" href="painel.php">← Voltar ao Painel</a>
                <?php endif; ?>
                <a class="btn-topo" href="../logout.php">Sair</a>
            </div>
        </header>
        
        <?php if ($mensagem): ?>
            <div class="alerta <?php echo $tipoMensagem; ?>">
                <?php echo htmlspecialchars($mensagem); ?>
            </div>
        <?php endif; ?>
        
        <section class="grade garcom-grade">
            
            <!-- Listagem de Pedidos Enviados -->
            <article class="bloco lista">
                <div class="titulo-bloco">
                    <div>
                        <p class="etiqueta">PEDIDOS ENVIADOS</p>
                        <h2>Pedidos de hoje</h2>
                    </div>
                    <span><?php echo count($pedidos); ?> pedidos</span>
                </div>
                
                <?php if (!$pedidos): ?>
                    <p class="vazio">Nenhum pedido registrado hoje.</p>
                <?php else: ?>
                    <div class="tabela-wrap">
                        <table class="tabela">
                            <thead>
                                <tr>
                                    <th>Mesa</th>
                                    <th>Itens</th>
                                    <th>Valor Total</th>
                                    <th>Caixa</th>
                                    <th>Cozinha</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pedidos as $pedido): ?>
                                    <?php $pronto = $pedido['status_preparo'] === Pedidos::PRONTO; ?>
                                    <tr>
                                        <td class="mesa-cozinha">Mesa <?php echo (int) $pedido['mesa_numero']; ?></td>
                                        <td><?php echo htmlspecialchars($pedido['itens'] ?: 'Pedido sem itens'); ?></td>
                                        <td>R$ <?php echo number_format((float) $pedido['valor'], 2, ',', '.'); ?></td>
                                        <td>
                                            <span class="badge pagamento-<?php echo htmlspecialchars($pedido['status_pagamento']); ?>">
                                                <?php echo $pedido['status_pagamento'] === Pedidos::PAGO ? 'Pago' : 'Pendente'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge preparo-<?php echo $pronto ? 'pronto' : 'aguardando'; ?>">
                                                <?php echo $pronto ? 'Pronto' : 'Em preparo'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </article>
            
            <!-- Formulário para Lançamento de Novos Pedidos -->
            <aside class="bloco formularios">
                <div class="formulario-card">
                    <p class="etiqueta">NOVO PEDIDO</p>
                    <h2>Itens da mesa</h2>
                    
                    <?php if (!$produtosCardapio): ?>
                        <p>Não há pratos disponíveis. Peça ao gerente ou administrador para cadastrar o cardápio.</p>
                    <?php else: ?>
                        <form method="POST">
                            
                            <!-- Nova seleção de mesas via combobox (evita IDs falsos) -->
                            <label for="mesa_id">Selecione a mesa</label>
                            <select id="mesa_id" name="mesa_id" required>
                                <option value="">Escolha a mesa...</option>
                                <?php foreach ($mesasDisponiveis as $m): ?>
                                    <option value="<?php echo (int) $m['id']; ?>" <?php echo (($_POST['mesa_id'] ?? '') == $m['id']) ? 'selected' : ''; ?>>
                                        Mesa <?php echo (int) $m['numero']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <!-- Lista do Cardápio Ativo -->
                            <div class="itens-cardapio">
                                <?php foreach ($produtosCardapio as $produto): ?>
                                    <div class="item-cardapio">
                                        <div>
                                            <strong><?php echo htmlspecialchars($produto['nome']); ?></strong>
                                            <small><?php echo htmlspecialchars($produto['descricao']); ?></small>
                                            <span>R$ <?php echo number_format((float) $produto['preco'], 2, ',', '.'); ?></span>
                                        </div>
                                        <input type="number" 
                                               name="quantidades[<?php echo (int) $produto['id']; ?>]" 
                                               min="0" max="99" 
                                               value="<?php echo (int) ($_POST['quantidades'][$produto['id']] ?? 0); ?>" 
                                               aria-label="Quantidade de <?php echo htmlspecialchars($produto['nome']); ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <button type="submit" name="adicionar_pedido" class="botao-principal">Enviar pedido</button>
                        </form>
                    <?php endif; ?>
                </div>
            </aside>
            
        </section>
    </main>
</body>
</html>