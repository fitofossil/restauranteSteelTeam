<?php
// =============================================================
// PAINEL DA COZINHA — DOGÃO LANCHES
// =============================================================

// 1. Inclusão de dependências (Autenticação, Classes de Negócio e Conexão BD)
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Pedidos.php';
require_once __DIR__ . '/../src/Cardapio.php';
require_once __DIR__ . '/../src/Log.php';
require_once __DIR__ . '/../config/conexao.php';

// 2. Segurança: Inicia a sessão e bloqueia usuários que não sejam da Cozinha/Gerência
Auth::iniciarSessao();
Auth::requireCozinha();

$mensagem = '';
$tipoMensagem = '';

try {
    // 3. Garantia de Estrutura: Verifica se as tabelas existem antes de rodar o código
    Pedidos::garantirTabela($conn);
    Cardapio::garantirTabelas($conn);
    Log::garantirTabela($conn);

    // =========================================================
    // 4. PROCESSAMENTO (POST): Ação de atualizar o status do pedido
    // =========================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_preparo'])) {
        $pedidoId = filter_input(INPUT_POST, 'pedido_id', FILTER_VALIDATE_INT);
        $statusPreparo = $_POST['status_preparo'] ?? '';

        // Valida se o ID existe e se o status enviado é válido ('aguardando' ou 'pronto')
        if (!$pedidoId || !in_array($statusPreparo, Pedidos::statusPreparoValidos(), true)) {
            throw new RuntimeException('Pedido ou status de preparo inválido.');
        }

        // Atualiza no BD. Segurança extra: a cozinha só pode alterar pedidos feitos HOJE (CURDATE)
        $stmt = $conn->prepare('UPDATE pedidos SET status_preparo = :status WHERE id = :id AND DATE(criado_em) = CURDATE()');
        $stmt->execute([':status' => $statusPreparo, ':id' => $pedidoId]);
        
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Pedido não encontrado ou já não pertence ao dia de hoje.');
        }
        
        // Registra a alteração na tabela de auditoria (Log)
        Log::registrar($conn, 'update', 'pedidos', $pedidoId, "Status de preparo do pedido #$pedidoId alterado para '$statusPreparo'.");

        // Define a mensagem de sucesso que será exibida na tela
        $mensagem = $statusPreparo === Pedidos::PRONTO
            ? 'Pedido marcado como pronto para entrega.'
            : 'Pedido retornou para a fila de preparo.';
        $tipoMensagem = 'sucesso';
    }

    // =========================================================
    // 5. CONSULTAS (GET): Busca os dados para montar a tela
    // =========================================================
    
    // Busca os pedidos de hoje, concatenando os itens num texto único (Ex: "2x X-Tudo • 1x Refri")
    $stmt = $conn->query("
        SELECT p.id, 
               m.numero AS mesa_numero, 
               p.status_pagamento, 
               p.status_preparo, 
               p.criado_em,
               GROUP_CONCAT(CONCAT(pi.quantidade, 'x ', pr.nome) ORDER BY pi.id SEPARATOR ' • ') AS itens
        FROM pedidos p 
        INNER JOIN mesas m ON m.id = p.mesa_id
        LEFT JOIN pedido_itens pi ON pi.pedido_id = p.id
        LEFT JOIN produtos pr ON pr.id = pi.produto_id
        WHERE DATE(p.criado_em) = CURDATE()
        GROUP BY p.id, m.numero, p.status_pagamento, p.status_preparo, p.criado_em
        -- Ordenação: Pedidos 'aguardando' aparecem no topo, seguidos pela ordem de chegada.
        ORDER BY p.status_preparo = 'pronto', p.criado_em ASC, p.id ASC
    ");
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Conta quantos pedidos estão 'aguardando' e quantos estão 'prontos' para alimentar os cards superiores
    $resumo = $conn->query("SELECT
                                SUM(status_preparo = 'aguardando') AS aguardando,
                                SUM(status_preparo = 'pronto') AS pronto
                            FROM pedidos
                            WHERE DATE(criado_em) = CURDATE()")->fetch(PDO::FETCH_ASSOC);
    
    $aguardando = (int) ($resumo['aguardando'] ?? 0);
    $prontos = (int) ($resumo['pronto'] ?? 0);

// Tratamento de erros caso algo falhe na inserção (RuntimeException) ou no banco (PDOException)
} catch (RuntimeException $e) {
    $mensagem = $e->getMessage();
    $tipoMensagem = 'erro';
    $pedidos = $pedidos ?? [];
    $aguardando = $aguardando ?? 0;
    $prontos = $prontos ?? 0;
} catch (PDOException $e) {
    $mensagem = 'Não foi possível carregar os pedidos da cozinha.';
    $tipoMensagem = 'erro';
    $pedidos = [];
    $aguardando = 0;
    $prontos = 0;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cozinha - Dogão Lanches</title>
    <link rel="stylesheet" href="../public/css/style.css">
</head>
<body>
    <main class="painel">
        
        <!-- Cabeçalho de Navegação -->
        <header class="topo">
            <div>
                <p class="marca">🌭 DOGÃO LANCHES</p>
                <h1>COZINHA</h1>
                <p class="boas-vindas">Olá, <?php echo Auth::sanitizarTexto(Auth::getNome()); ?>. Acompanhe a fila de preparo de hoje.</p>
            </div>
            <div class="topo-botoes">
                <?php if (Auth::isAdmin() || Auth::isGerente()): ?>
                    <a class="btn-topo" href="painel.php">← Voltar ao Painel</a>
                <?php endif; ?>
                <a class="btn-topo" href="../logout.php">Sair</a>
            </div>
        </header>

        <!-- Exibe os alertas (erros ou sucessos) capturados no bloco PHP acima -->
        <?php if ($mensagem): ?>
            <div class="alerta <?php echo $tipoMensagem; ?>"><?php echo htmlspecialchars($mensagem); ?></div>
        <?php endif; ?>

        <!-- Cards com o resumo do volume de trabalho atual -->
        <section class="cards cozinha-cards" aria-label="Resumo da fila da cozinha">
            <article class="card"><span class="icone">👨‍🍳</span><div><p>Aguardando preparo</p><strong><?php echo $aguardando; ?></strong></div></article>
            <article class="card"><span class="icone">✅</span><div><p>Prontos para entrega</p><strong><?php echo $prontos; ?></strong></div></article>
        </section>

        <!-- Lista da Fila de Produção -->
        <section class="bloco lista">
            <div class="titulo-bloco">
                <div><p class="etiqueta">FILA DE PRODUÇÃO</p><h2>Pedidos de hoje</h2></div>
                <span><?php echo count($pedidos); ?> pedidos</span>
            </div>

            <?php if (empty($pedidos)): ?>
                <p class="vazio">Nenhum pedido registrado para hoje.</p>
            <?php else: ?>
                <div class="tabela-wrap">
                    <table class="tabela tabela-cozinha">
                        <thead>
                            <tr><th>Pedido</th><th>Mesa</th><th>Itens</th><th>Recebido às</th><th>Pagamento</th><th>Preparo</th><th>Ação</th></tr>
                        </thead>
                        <tbody>
                            <!-- Laço que constrói uma linha para cada pedido recebido -->
                            <?php foreach ($pedidos as $pedido): ?>
                                <?php $estaPronto = $pedido['status_preparo'] === Pedidos::PRONTO; ?>
                                <tr>
                                    <td>#<?php echo (int) $pedido['id']; ?></td>
                                    <td class="mesa-cozinha">Mesa <?php echo (int) $pedido['mesa_numero']; ?></td>
                                    <td><?php echo htmlspecialchars($pedido['itens'] ?: 'Pedido sem itens'); ?></td>
                                    <td><?php echo htmlspecialchars(date('H:i', strtotime($pedido['criado_em']))); ?></td>
                                    <td><span class="badge pagamento-<?php echo htmlspecialchars($pedido['status_pagamento']); ?>"><?php echo $pedido['status_pagamento'] === Pedidos::PAGO ? 'Pago' : 'Pendente'; ?></span></td>
                                    <td><span class="badge preparo-<?php echo $estaPronto ? 'pronto' : 'aguardando'; ?>"><?php echo $estaPronto ? 'Pronto' : 'Em preparo'; ?></span></td>
                                    <td>
                                        <!-- Formulário embutido na tabela para acionar o POST (Aguardando <-> Pronto) -->
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="pedido_id" value="<?php echo (int) $pedido['id']; ?>">
                                            <input type="hidden" name="status_preparo" value="<?php echo $estaPronto ? Pedidos::AGUARDANDO_PREPARO : Pedidos::PRONTO; ?>">
                                            <button type="submit" name="atualizar_preparo" class="<?php echo $estaPronto ? 'btn-voltar-preparo' : 'btn-pronto'; ?>">
                                                <?php echo $estaPronto ? 'Voltar ao preparo' : 'Marcar como pronto'; ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>