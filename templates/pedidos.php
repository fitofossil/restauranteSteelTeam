<?php
// =============================================================
//              PAINEL DE PEDIDOS - DOGÃO LANCHES
// =============================================================
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Pedidos.php';
require_once __DIR__ . '/../src/Cardapio.php';
require_once __DIR__ . '/../config/conexao.php';

Auth::iniciarSessao();
// Bloqueia funcionário comum antes de qualquer consulta ao banco.
Auth::requirePedidosView();

// Permissões que controlam o que aparece na interface e o que o servidor aceita.
$mensagem = '';
$tipoMensagem = '';
$pedidoEditando = null;
$podeEditar = Auth::isRecepcao() || Auth::isAdmin() || Auth::isGerente();
$podeZerarDia = Auth::isAdmin() || Auth::isGerente();

try {
    // Mantém o banco compatível antes de listar ou gravar pedidos.
    Pedidos::garantirTabela($conn);
    Cardapio::garantirTabelas($conn);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['zerar_dia'])) {
            // Esta ação é destrutiva e permitida apenas a administrador ou gerente.
            Auth::requireZerarCaixa();
            $senha = $_POST['senha_confirmacao'] ?? '';
            $stmt = $conn->prepare('SELECT password_hash FROM users_login WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => Auth::getId()]);
            $hash = $stmt->fetchColumn();

            if (!$hash || !password_verify($senha, $hash)) {
                throw new RuntimeException('Senha incorreta. O dia não foi zerado.');
            }

            // Exclui somente pedidos da data atual, preservando dias anteriores.
            $stmt = $conn->prepare('DELETE FROM pedidos WHERE DATE(criado_em) = CURDATE()');
            $stmt->execute();
            $mensagem = $stmt->rowCount() . ' pedido(s) de hoje foram removidos e o dia foi zerado.';
            $tipoMensagem = 'sucesso';
        } else {
            // A verificação no servidor impede edição mesmo se alguém montar um POST manualmente.
            Auth::requireEditarPedidos();

            if (isset($_POST['excluir_pedido'])) {
                // Exclusão individual disponível aos mesmos perfis que editam pedidos.
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
            } else {
                // Dados compartilhados pelas ações de cadastrar e atualizar.
                $mesa = filter_input(INPUT_POST, 'mesa_numero', FILTER_VALIDATE_INT);
                $valor = str_replace(',', '.', trim($_POST['valor'] ?? ''));
                $status = $_POST['status_pagamento'] ?? '';

                if (!$mesa || $mesa < 1 || $mesa > 999) {
                    throw new RuntimeException('Informe um número de mesa entre 1 e 999.');
                }
                if (!is_numeric($valor) || (float) $valor <= 0) {
                    throw new RuntimeException('Informe um valor de pedido válido.');
                }
                if (!in_array($status, Pedidos::statusValidos(), true)) {
                    throw new RuntimeException('Selecione um status de pagamento válido.');
                }

                if (isset($_POST['adicionar_pedido'])) {
                    $stmt = $conn->prepare('INSERT INTO pedidos (mesa_numero, valor, status_pagamento) VALUES (:mesa, :valor, :status)');
                    $stmt->execute([':mesa' => $mesa, ':valor' => $valor, ':status' => $status]);
                    $mensagem = 'Pedido registrado com sucesso.';
                    $tipoMensagem = 'sucesso';
                }

                if (isset($_POST['atualizar_pedido'])) {
                    $id = filter_input(INPUT_POST, 'pedido_id', FILTER_VALIDATE_INT);
                    if (!$id) {
                        throw new RuntimeException('Pedido inválido.');
                    }

                    $stmt = $conn->prepare('UPDATE pedidos SET mesa_numero = :mesa, valor = :valor, status_pagamento = :status WHERE id = :id');
                    $stmt->execute([':mesa' => $mesa, ':valor' => $valor, ':status' => $status, ':id' => $id]);
                    $mensagem = 'Pedido atualizado com sucesso.';
                    $tipoMensagem = 'sucesso';
                }
            }
        }
    }

    if ($podeEditar && isset($_GET['editar'])) {
        // Carrega um pedido no formulário lateral somente quando há permissão de edição.
        $idEditar = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT);
        if ($idEditar) {
            $stmt = $conn->prepare('SELECT id, mesa_numero, valor, status_pagamento FROM pedidos WHERE id = ?');
            $stmt->execute([$idEditar]);
            $pedidoEditando = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    }

    // Busca em ordem cronológica para montar uma numeração visual que reinicia a cada dia.
    $pedidosOrdenados = $conn->query("SELECT p.id, p.mesa_numero, p.valor, p.status_pagamento, p.criado_em,
                                             GROUP_CONCAT(CONCAT(pi.quantidade, 'x ', pi.produto_nome) ORDER BY pi.id SEPARATOR ' • ') AS itens
                                      FROM pedidos p LEFT JOIN pedido_itens pi ON pi.pedido_id = p.id
                                      GROUP BY p.id, p.mesa_numero, p.valor, p.status_pagamento, p.criado_em
                                      ORDER BY p.criado_em ASC, p.id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $sequenciaPorDia = [];
    foreach ($pedidosOrdenados as &$pedido) {
        $dataPedido = date('Y-m-d', strtotime($pedido['criado_em']));
        $sequenciaPorDia[$dataPedido] = ($sequenciaPorDia[$dataPedido] ?? 0) + 1;
        $pedido['numero_do_dia'] = $sequenciaPorDia[$dataPedido];
    }
    unset($pedido);

    // A tela permanece com os pedidos mais recentes no topo.
    $pedidos = array_reverse($pedidosOrdenados);
    $resumoDia = $conn->query("SELECT COALESCE(SUM(CASE WHEN status_pagamento = 'pago' THEN valor ELSE 0 END), 0) AS pago, COALESCE(SUM(CASE WHEN status_pagamento = 'pendente' THEN valor ELSE 0 END), 0) AS pendente FROM pedidos WHERE DATE(criado_em) = CURDATE()")->fetch(PDO::FETCH_ASSOC);
} catch (RuntimeException $e) {
    $mensagem = $e->getMessage();
    $tipoMensagem = 'erro';
    $pedidos = $pedidos ?? [];
    $resumoDia = $resumoDia ?? ['pago' => 0, 'pendente' => 0];
} catch (PDOException $e) {
    $mensagem = 'Não foi possível carregar os pedidos.';
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
        <header class="topo">
            <div>
                <p class="marca">🌭 DOGÃO LANCHES</p>
                <h1>CAIXA</h1>
                <p class="boas-vindas">
                    <?php echo $podeEditar ? 'Você pode registrar e atualizar os pedidos.' : 'Você está visualizando os pedidos em modo leitura.'; ?>
                </p>
            </div>
            <div class="topo-botoes">
                <a class="btn-topo" href="painel.php">← Voltar ao Painel</a>
                <a class="btn-topo" href="../logout.php">Sair</a>
            </div>
        </header>

        <?php if ($mensagem): ?>
            <div class="alerta <?php echo $tipoMensagem; ?>"><?php echo htmlspecialchars($mensagem); ?></div>
        <?php endif; ?>

        <!-- Totais calculados novamente a cada abertura, por isso acumulam todos os pedidos pagos do dia. -->
        <section class="cards" aria-label="Resumo financeiro de hoje">
            <article class="card"><span class="icone">💰</span><div><p>Pago hoje</p><strong>R$ <?php echo number_format((float) $resumoDia['pago'], 2, ',', '.'); ?></strong></div></article>
            <article class="card"><span class="icone">🕒</span><div><p>A receber hoje</p><strong>R$ <?php echo number_format((float) $resumoDia['pendente'], 2, ',', '.'); ?></strong></div></article>
        </section>

        <!-- Tabela visível para todos os perfis autorizados; ações só para quem pode editar. -->
        <section class="grade pedidos-grade <?php echo $podeEditar ? '' : 'pedidos-leitura'; ?>">
            <article class="bloco lista">
                <div class="titulo-bloco">
                    <div><p class="etiqueta">CONTROLE DE MESAS</p><h2>Pedidos registrados</h2></div>
                    <span><?php echo count($pedidos); ?> pedidos</span>
                </div>

                <?php if (empty($pedidos)): ?>
                    <p class="vazio">Nenhum pedido registrado.</p>
                <?php else: ?>
                    <div class="tabela-wrap">
                        <table class="tabela">
                            <thead>
                                <tr><th>Pedido</th><th>Mesa</th><th>Itens</th><th>Valor</th><th>Pagamento</th><th>Registrado em</th><?php if ($podeEditar): ?><th>Ações</th><?php endif; ?></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pedidos as $pedido): ?>
                                    <tr>
                                        <td>#<?php echo (int) $pedido['numero_do_dia']; ?></td>
                                        <td>Mesa <?php echo (int) $pedido['mesa_numero']; ?></td>
                                        <td><?php echo htmlspecialchars($pedido['itens'] ?: 'Pedido sem itens'); ?></td>
                                        <td>R$ <?php echo number_format((float) $pedido['valor'], 2, ',', '.'); ?></td>
                                        <td><span class="badge pagamento-<?php echo htmlspecialchars($pedido['status_pagamento']); ?>"><?php echo $pedido['status_pagamento'] === Pedidos::PAGO ? 'Pago' : 'Ainda não pago'; ?></span></td>
                                        <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($pedido['criado_em']))); ?></td>
                                        <?php if ($podeEditar): ?>
                                            <td class="acoes">
                                                <a class="btn-editar" href="?editar=<?php echo (int) $pedido['id']; ?>">Editar</a>
                                                <form method="POST" class="inline" onsubmit="return confirm('Excluir este pedido?');">
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

            <!-- Formulários são renderizados apenas para os perfis que podem editar pedidos. -->
            <?php if ($podeEditar): ?>
                <aside class="bloco formularios">
                    <div class="formulario-card">
                        <p class="etiqueta"><?php echo $pedidoEditando ? 'EDITAR PEDIDO' : 'NOVO PEDIDO'; ?></p>
                        <h2><?php echo $pedidoEditando ? 'Editar pedido' : 'Registrar pedido'; ?></h2>
                        <form method="POST">
                            <?php if ($pedidoEditando): ?>
                                <input type="hidden" name="pedido_id" value="<?php echo (int) $pedidoEditando['id']; ?>">
                            <?php endif; ?>

                            <label for="mesa_numero">Número da mesa</label>
                            <input id="mesa_numero" type="number" name="mesa_numero" min="1" max="999" value="<?php echo (int) ($pedidoEditando['mesa_numero'] ?? ($_POST['mesa_numero'] ?? 1)); ?>" required>

                            <label for="valor">Valor do pedido</label>
                            <input id="valor" type="text" inputmode="decimal" name="valor" placeholder="0,00" value="<?php echo htmlspecialchars($pedidoEditando['valor'] ?? ($_POST['valor'] ?? '')); ?>" required>

                            <label for="status_pagamento">Status do pagamento</label>
                            <?php $statusSelecionado = $pedidoEditando['status_pagamento'] ?? ($_POST['status_pagamento'] ?? Pedidos::PENDENTE); ?>
                            <select id="status_pagamento" name="status_pagamento" required>
                                <option value="pendente" <?php echo $statusSelecionado === Pedidos::PENDENTE ? 'selected' : ''; ?>>Ainda não pago</option>
                                <option value="pago" <?php echo $statusSelecionado === Pedidos::PAGO ? 'selected' : ''; ?>>Pago</option>
                            </select>

                            <button type="submit" name="<?php echo $pedidoEditando ? 'atualizar_pedido' : 'adicionar_pedido'; ?>" class="botao-principal"><?php echo $pedidoEditando ? 'Salvar alterações' : 'Registrar pedido'; ?></button>

                            <?php if ($pedidoEditando): ?>
                                <a class="btn-cancelar" href="pedidos.php">Cancelar edição</a>
                            <?php endif; ?>
                        </form>
                    </div>
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
