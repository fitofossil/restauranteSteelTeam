<?php
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Pedidos.php';
require_once __DIR__ . '/../src/Cardapio.php';
require_once __DIR__ . '/../src/Log.php';
require_once __DIR__ . '/../config/conexao.php';

Auth::iniciarSessao();
Auth::requireGarcom();

$limiteMesas = Pedidos::LIMITE_MESAS;

function buscarDadosCep(string $cep): array
{
    $cep = preg_replace('/\D+/', '', $cep);
    if (strlen($cep) !== 8) {
        throw new RuntimeException('Informe um CEP válido com 8 números para a entrega.');
    }

    $url = 'https://viacep.com.br/ws/' . $cep . '/json/';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT => 'Mozilla/5.0',
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode >= 400 || $curlErr !== '') {
        throw new RuntimeException('Não foi possível consultar o CEP no momento. Tente novamente.');
    }

    $dados = json_decode($response, true);
    if (!is_array($dados) || (!empty($dados['erro']) && $dados['erro'])) {
        throw new RuntimeException('CEP não encontrado. Verifique e tente novamente.');
    }

    return $dados;
}

$mensagem = '';
$tipoMensagem = '';
$pedidoEmEdicaoId = null;
$tipoPedidoSelecionado = $_POST['tipo_entrega'] ?? Pedidos::TIPO_MESA;
$clienteNome = trim((string) ($_POST['cliente_nome'] ?? ''));
$cepEntrega = preg_replace('/\D+/', '', (string) ($_POST['cep_entrega'] ?? ''));
$enderecoEntrega = trim((string) ($_POST['endereco_entrega'] ?? ''));
$numeroEndereco = trim((string) ($_POST['numero_endereco'] ?? ''));
$complementoEndereco = trim((string) ($_POST['complemento_endereco'] ?? ''));
$bairroEndereco = trim((string) ($_POST['bairro_endereco'] ?? ''));
$cidadeEndereco = trim((string) ($_POST['cidade_endereco'] ?? ''));
$ufEndereco = trim((string) ($_POST['uf_endereco'] ?? ''));
$observacao = trim((string) ($_POST['observacao'] ?? ''));

try {
    Pedidos::garantirTabela($conn);
    Cardapio::garantirTabelas($conn);
    Log::garantirTabela($conn);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buscar_cep_entrega'])) {
        $dados = buscarDadosCep($cepEntrega);

        $_POST['endereco_entrega'] = $dados['logradouro'] ?? '';
        $_POST['bairro_endereco'] = $dados['bairro'] ?? '';
        $_POST['cidade_endereco'] = $dados['localidade'] ?? '';
        $_POST['uf_endereco'] = $dados['uf'] ?? '';

        $enderecoEntrega = trim((string) ($_POST['endereco_entrega'] ?? ''));
        $bairroEndereco = trim((string) ($_POST['bairro_endereco'] ?? ''));
        $cidadeEndereco = trim((string) ($_POST['cidade_endereco'] ?? ''));
        $ufEndereco = trim((string) ($_POST['uf_endereco'] ?? ''));

        $mensagem = 'CEP localizado com sucesso. Revise os dados antes de enviar o pedido.';
        $tipoMensagem = 'sucesso';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar_pedido'])) {
        $mesa = filter_input(INPUT_POST, 'mesa_numero', FILTER_VALIDATE_INT);
        $tipoEntrega = $_POST['tipo_entrega'] ?? Pedidos::TIPO_MESA;
        $observacao = trim((string) ($_POST['observacao'] ?? ''));
        $clienteNome = trim((string) ($_POST['cliente_nome'] ?? ''));
        $cepEntrega = preg_replace('/\D+/', '', (string) ($_POST['cep_entrega'] ?? ''));
        $enderecoEntrega = trim((string) ($_POST['endereco_entrega'] ?? ''));
        $numeroEndereco = trim((string) ($_POST['numero_endereco'] ?? ''));
        $complementoEndereco = trim((string) ($_POST['complemento_endereco'] ?? ''));
        $bairroEndereco = trim((string) ($_POST['bairro_endereco'] ?? ''));
        $cidadeEndereco = trim((string) ($_POST['cidade_endereco'] ?? ''));
        $ufEndereco = trim((string) ($_POST['uf_endereco'] ?? ''));
        $quantidadesRecebidas = $_POST['quantidades'] ?? [];
        $quantidades = [];

        if (!in_array($tipoEntrega, Pedidos::tiposEntregaValidos(), true)) {
            throw new RuntimeException('Selecione um tipo de pedido válido.');
        }

        if ($tipoEntrega === Pedidos::TIPO_MESA) {
            if (!$mesa || $mesa < 1 || $mesa > $limiteMesas) {
                throw new RuntimeException('Informe um número de mesa entre 1 e ' . $limiteMesas . '.');
            }
        } else {
            $mesa = 0;
        }

        if ($tipoEntrega === Pedidos::TIPO_ENTREGA) {
            if ($clienteNome === '' || $cepEntrega === '' || $enderecoEntrega === '' || $numeroEndereco === '' || $bairroEndereco === '' || $cidadeEndereco === '' || $ufEndereco === '') {
                throw new RuntimeException('Para entrega, informe nome do cliente, CEP, endereço, número, bairro, cidade e UF.');
            }
        }

        if (!is_array($quantidadesRecebidas)) {
            throw new RuntimeException('Itens do pedido inválidos.');
        }

        foreach ($quantidadesRecebidas as $produtoId => $quantidade) {
            $id = filter_var($produtoId, FILTER_VALIDATE_INT);
            $qtd = filter_var($quantidade, FILTER_VALIDATE_INT);
            if ($id && $qtd && $qtd > 0 && $qtd <= 99) {
                $quantidades[$id] = $qtd;
            }
        }

        if (!$quantidades) {
            throw new RuntimeException('Informe a quantidade de pelo menos um prato.');
        }

        $marcadores = implode(',', array_fill(0, count($quantidades), '?'));
        $stmt = $conn->prepare("SELECT id, nome, preco FROM produtos WHERE ativo = 1 AND id IN ($marcadores)");
        $stmt->execute(array_keys($quantidades));
        $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($produtos) !== count($quantidades)) {
            throw new RuntimeException('Um dos pratos selecionados não está mais disponível.');
        }

        $total = 0;
        foreach ($produtos as $produto) {
            $total += (float) $produto['preco'] * $quantidades[(int) $produto['id']];
        }

        $conn->beginTransaction();
        $stmt = $conn->prepare("INSERT INTO pedidos (mesa_numero, valor, status_pagamento, status_preparo, tipo_entrega, observacao, cliente_nome, cep_entrega, endereco_entrega, numero_endereco, complemento_endereco, bairro_endereco, cidade_endereco, uf_endereco) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $mesa,
            $total,
            Pedidos::PENDENTE,
            Pedidos::AGUARDANDO_PREPARO,
            $tipoEntrega,
            $observacao !== '' ? $observacao : null,
            $clienteNome !== '' ? $clienteNome : null,
            $cepEntrega !== '' ? $cepEntrega : null,
            $enderecoEntrega !== '' ? $enderecoEntrega : null,
            $numeroEndereco !== '' ? $numeroEndereco : null,
            $complementoEndereco !== '' ? $complementoEndereco : null,
            $bairroEndereco !== '' ? $bairroEndereco : null,
            $cidadeEndereco !== '' ? $cidadeEndereco : null,
            $ufEndereco !== '' ? strtoupper($ufEndereco) : null,
        ]);

        $pedidoId = (int) $conn->lastInsertId();
        $itemStmt = $conn->prepare('INSERT INTO pedido_itens (pedido_id, produto_id, produto_nome, quantidade, preco_unitario) VALUES (?, ?, ?, ?, ?)');
        foreach ($produtos as $produto) {
            $itemStmt->execute([$pedidoId, $produto['id'], $produto['nome'], $quantidades[(int) $produto['id']], $produto['preco']]);
        }

        $conn->commit();
        Log::registrar($conn, 'insert', 'pedidos', $pedidoId, "Pedido #$pedidoId enviado pelo garçom — {$tipoEntrega}, R$ " . number_format($total, 2, ',', '.') . ($observacao ? " | Obs: $observacao" : ''));

        $mensagem = 'Pedido enviado para o caixa e para a cozinha.';
        $tipoMensagem = 'sucesso';
        $_POST = [];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_pedido'])) {
        $pedidoId = filter_input(INPUT_POST, 'pedido_id', FILTER_VALIDATE_INT);
        $novasQuantidades = $_POST['quantidades_pedido'] ?? [];

        if (!$pedidoId) {
            throw new RuntimeException('Pedido inválido para alteração.');
        }

        if (!is_array($novasQuantidades)) {
            throw new RuntimeException('As quantidades informadas são inválidas.');
        }

        $quantidadesValidas = [];
        foreach ($novasQuantidades as $produtoId => $quantidade) {
            $produtoIdValidado = filter_var($produtoId, FILTER_VALIDATE_INT);
            $quantidadeValidada = filter_var($quantidade, FILTER_VALIDATE_INT);
            if ($produtoIdValidado && $quantidadeValidada !== false && $quantidadeValidada >= 0 && $quantidadeValidada <= 99) {
                $quantidadesValidas[$produtoIdValidado] = (int) $quantidadeValidada;
            }
        }

        if (!$quantidadesValidas) {
            throw new RuntimeException('Informe pelo menos uma quantidade para ajustar o pedido.');
        }

        $conn->beginTransaction();
        $stmtItens = $conn->prepare('SELECT id, produto_id, produto_nome, quantidade, preco_unitario FROM pedido_itens WHERE pedido_id = ? ORDER BY id');
        $stmtItens->execute([$pedidoId]);
        $itensPedido = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

        $itensAtuais = [];
        foreach ($itensPedido as $item) {
            $itensAtuais[(int) $item['produto_id']] = $item;
        }

        $totalAtualizado = 0;
        $temItemNoPedido = false;
        foreach ($quantidadesValidas as $produtoId => $quantidade) {
            if ($quantidade === 0) {
                if (isset($itensAtuais[$produtoId])) {
                    $deleteItem = $conn->prepare('DELETE FROM pedido_itens WHERE id = ?');
                    $deleteItem->execute([$itensAtuais[$produtoId]['id']]);
                }
                continue;
            }

            $produto = $conn->prepare('SELECT id, nome, preco FROM produtos WHERE id = ? AND ativo = 1 LIMIT 1');
            $produto->execute([$produtoId]);
            $produtoAtual = $produto->fetch(PDO::FETCH_ASSOC);

            if (!$produtoAtual) {
                throw new RuntimeException('Um prato selecionado não está mais disponível.');
            }

            $temItemNoPedido = true;
            $totalAtualizado += (float) $produtoAtual['preco'] * $quantidade;

            if (isset($itensAtuais[$produtoId])) {
                $updateItem = $conn->prepare('UPDATE pedido_itens SET produto_nome = ?, quantidade = ?, preco_unitario = ? WHERE id = ?');
                $updateItem->execute([$produtoAtual['nome'], $quantidade, $produtoAtual['preco'], $itensAtuais[$produtoId]['id']]);
            } else {
                $insertItem = $conn->prepare('INSERT INTO pedido_itens (pedido_id, produto_id, produto_nome, quantidade, preco_unitario) VALUES (?, ?, ?, ?, ?)');
                $insertItem->execute([$pedidoId, $produtoAtual['id'], $produtoAtual['nome'], $quantidade, $produtoAtual['preco']]);
            }
        }

        foreach ($itensPedido as $item) {
            $produtoId = (int) $item['produto_id'];
            if (!isset($quantidadesValidas[$produtoId])) {
                $deleteItem = $conn->prepare('DELETE FROM pedido_itens WHERE id = ?');
                $deleteItem->execute([$item['id']]);
            }
        }

        if (!$temItemNoPedido) {
            $removerPedido = $conn->prepare('DELETE FROM pedidos WHERE id = ?');
            $removerPedido->execute([$pedidoId]);
            $conn->commit();

            Log::registrar($conn, 'delete', 'pedidos', $pedidoId, "Pedido #$pedidoId removido pelo garçom após exclusão de todos os itens.");
            $mensagem = 'Todos os itens foram removidos e o pedido foi excluído.';
        } else {
            $updatePedido = $conn->prepare('UPDATE pedidos SET valor = ? WHERE id = ?');
            $updatePedido->execute([$totalAtualizado, $pedidoId]);
            $conn->commit();

            Log::registrar($conn, 'update', 'pedido_itens', $pedidoId, "Quantidades do pedido #$pedidoId ajustadas pelo garçom.");
            $mensagem = 'Quantidade do pedido ajustada com sucesso.';
        }

        $tipoMensagem = 'sucesso';
        $pedidoEmEdicaoId = null;
    } elseif (isset($_POST['abrir_edicao'])) {
        $pedidoEmEdicaoId = filter_input(INPUT_POST, 'pedido_id', FILTER_VALIDATE_INT) ?: null;
    }

    $produtosCardapio = $conn->query('SELECT id, nome, descricao, preco FROM produtos WHERE ativo = 1 ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);
    $pedidos = $conn->query("SELECT p.id, p.mesa_numero, p.valor, p.status_pagamento, p.status_preparo, p.tipo_entrega, p.observacao, p.cliente_nome, p.cep_entrega, p.endereco_entrega, p.numero_endereco, p.complemento_endereco, p.bairro_endereco, p.cidade_endereco, p.uf_endereco, p.criado_em,
                                    GROUP_CONCAT(CONCAT(pi.quantidade, 'x ', pi.produto_nome) ORDER BY pi.id SEPARATOR ' • ') AS itens
                             FROM pedidos p LEFT JOIN pedido_itens pi ON pi.pedido_id = p.id
                             WHERE DATE(p.criado_em) = CURDATE()
                             GROUP BY p.id, p.mesa_numero, p.valor, p.status_pagamento, p.status_preparo, p.tipo_entrega, p.observacao, p.cliente_nome, p.cep_entrega, p.endereco_entrega, p.numero_endereco, p.complemento_endereco, p.bairro_endereco, p.cidade_endereco, p.uf_endereco, p.criado_em
                             ORDER BY p.criado_em DESC, p.id DESC")->fetchAll(PDO::FETCH_ASSOC);

    $mesasStatus = [];
    for ($numeroMesa = 1; $numeroMesa <= $limiteMesas; $numeroMesa++) {
        $mesasStatus[$numeroMesa] = [
            'numero' => $numeroMesa,
            'ocupada' => false,
            'pedido_id' => null,
        ];
    }

    foreach ($pedidos as $pedidoAtual) {
        if (($pedidoAtual['tipo_entrega'] ?? '') !== Pedidos::TIPO_MESA) {
            continue;
        }

        $numeroMesa = (int) ($pedidoAtual['mesa_numero'] ?? 0);
        if ($numeroMesa < 1 || $numeroMesa > $limiteMesas) {
            continue;
        }

        if (($pedidoAtual['status_pagamento'] ?? '') === Pedidos::PENDENTE) {
            $mesasStatus[$numeroMesa]['ocupada'] = true;
            $mesasStatus[$numeroMesa]['pedido_id'] = (int) $pedidoAtual['id'];
        }
    }

    $mesasOcupadas = count(array_filter($mesasStatus, static fn($mesa) => $mesa['ocupada']));
    $mesasLivres = $limiteMesas - $mesasOcupadas;

    $pedidoSelecionadoParaEdicao = null;
    if ($pedidoEmEdicaoId) {
        $stmtPedidoEdicao = $conn->prepare('SELECT p.id, p.mesa_numero, p.tipo_entrega, p.observacao, p.valor, pi.id AS item_id, pi.produto_id, pi.produto_nome, pi.quantidade, pi.preco_unitario
            FROM pedidos p
            LEFT JOIN pedido_itens pi ON pi.pedido_id = p.id
            WHERE p.id = ?
            ORDER BY pi.id');
        $stmtPedidoEdicao->execute([$pedidoEmEdicaoId]);
        $pedidoSelecionadoParaEdicao = $stmtPedidoEdicao->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (RuntimeException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $mensagem = $e->getMessage();
    $tipoMensagem = 'erro';
    $produtosCardapio = $produtosCardapio ?? [];
    $pedidos = $pedidos ?? [];
    $mesasStatus = $mesasStatus ?? [];
    $mesasOcupadas = $mesasOcupadas ?? 0;
    $mesasLivres = $mesasLivres ?? $limiteMesas;
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $mensagem = 'Não foi possível registrar ou carregar os pedidos.';
    $tipoMensagem = 'erro';
    $produtosCardapio = [];
    $pedidos = [];
    $mesasStatus = [];
    $mesasOcupadas = 0;
    $mesasLivres = $limiteMesas;
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
            <div class="alerta <?php echo $tipoMensagem; ?>"><?php echo htmlspecialchars($mensagem); ?></div>
        <?php endif; ?>

        <section class="bloco mesas-painel" aria-label="Status das mesas">
            <div class="titulo-bloco">
                <div>
                    <h2>Mesas do salão (1 a <?php echo (int) $limiteMesas; ?>)</h2>
                </div>
                <span><?php echo (int) $mesasLivres; ?> livres • <?php echo (int) $mesasOcupadas; ?> ocupadas</span>
            </div>

            <div class="mesas-grid">
                <?php foreach ($mesasStatus as $mesa): ?>
                    <article class="mesa-card <?php echo $mesa['ocupada'] ? 'mesa-ocupada' : 'mesa-livre'; ?>">
                        <strong>Mesa <?php echo (int) $mesa['numero']; ?></strong>
                        <span><?php echo $mesa['ocupada'] ? 'Ocupada' : 'Livre'; ?></span>
                        <?php if ($mesa['ocupada'] && $mesa['pedido_id']): ?>
                            <small>Pedido #<?php echo (int) $mesa['pedido_id']; ?></small>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="grade garcom-grade">
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
                                    <th>Tipo</th>
                                    <th>Mesa</th>
                                    <th>Itens</th>
                                    <th>Observação</th>
                                    <th>Valor</th>
                                    <th>Caixa</th>
                                    <th>Cozinha</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pedidos as $pedido): ?>
                                    <?php
                                        $pronto = $pedido['status_preparo'] === Pedidos::PRONTO;
                                        $tipo = Pedidos::textoTipoEntrega((string) ($pedido['tipo_entrega'] ?? Pedidos::TIPO_MESA));
                                    ?>
                                    <tr>
                                        <td><span class="badge pagamento-<?php echo htmlspecialchars($pedido['tipo_entrega'] ?? Pedidos::TIPO_MESA); ?>"><?php echo htmlspecialchars($tipo); ?></span></td>
                                        <td class="mesa-cozinha"><?php echo (($pedido['tipo_entrega'] ?? Pedidos::TIPO_MESA) === Pedidos::TIPO_MESA) ? 'Mesa ' . (int) $pedido['mesa_numero'] : 'Retirada/Entrega'; ?></td>
                                        <td><?php echo htmlspecialchars($pedido['itens'] ?: 'Pedido sem itens'); ?></td>
                                        <td><?php 
                                            $dadosEntrega = array_filter([
                                                $pedido['cliente_nome'] ?? '',
                                                $pedido['endereco_entrega'] ?? '',
                                                $pedido['numero_endereco'] ?? '',
                                                $pedido['bairro_endereco'] ?? '',
                                                trim((($pedido['cidade_endereco'] ?? '') . (isset($pedido['uf_endereco']) && $pedido['uf_endereco'] !== '' ? '/' . $pedido['uf_endereco'] : ''))),
                                                !empty($pedido['cep_entrega']) ? 'CEP ' . $pedido['cep_entrega'] : ''
                                            ], static fn($valor) => trim((string) $valor) !== '');

                                            $textoObservacao = (($pedido['tipo_entrega'] ?? '') === Pedidos::TIPO_ENTREGA)
                                                ? (count($dadosEntrega) > 0 ? implode(' • ', $dadosEntrega) : 'Sem endereço')
                                                : ($pedido['observacao'] ?: 'Sem observações');

                                            if (($pedido['tipo_entrega'] ?? '') === Pedidos::TIPO_ENTREGA && !empty($pedido['observacao'])) {
                                                $textoObservacao .= ' | Obs: ' . $pedido['observacao'];
                                            }

                                            echo htmlspecialchars($textoObservacao ?: 'Sem observações');
                                        ?></td>
                                        <td>R$ <?php echo number_format((float) $pedido['valor'], 2, ',', '.'); ?></td>
                                        <td><span class="badge pagamento-<?php echo htmlspecialchars($pedido['status_pagamento']); ?>"><?php echo $pedido['status_pagamento'] === Pedidos::PAGO ? 'Pago' : 'Pendente'; ?></span></td>
                                        <td><span class="badge preparo-<?php echo $pronto ? 'pronto' : 'aguardando'; ?>"><?php echo $pronto ? 'Pronto' : 'Em preparo'; ?></span></td>
                                        <td>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="pedido_id" value="<?php echo (int) $pedido['id']; ?>">
                                                <button type="submit" name="abrir_edicao" class="btn-topo">Ajustar</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if ($pedidoSelecionadoParaEdicao && !empty($pedidoSelecionadoParaEdicao)): ?>
                    <div class="formulario-card" style="margin-top: 1.5rem;">
                        <p class="etiqueta">AJUSTAR QUANTIDADES</p>
                        <h2>Pedido #<?php echo (int) $pedidoSelecionadoParaEdicao[0]['id']; ?></h2>
                        <p>Para remover um item, defina a quantidade como 0 e salve.</p>
                        <form method="POST">
                            <input type="hidden" name="pedido_id" value="<?php echo (int) $pedidoSelecionadoParaEdicao[0]['id']; ?>">
                            <?php foreach ($pedidoSelecionadoParaEdicao as $item): ?>
                                <?php if ($item['produto_id'] === null) continue; ?>
                                <div class="item-cardapio" style="padding: 0.75rem 0; margin-bottom: 0.5rem; display: block;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem;">
                                        <div>
                                            <strong><?php echo htmlspecialchars($item['produto_nome'] ?: 'Item'); ?></strong>
                                            <small>Valor unitário: R$ <?php echo number_format((float) ($item['preco_unitario'] ?? 0), 2, ',', '.'); ?></small>
                                        </div>
                                        <input type="number" name="quantidades_pedido[<?php echo (int) $item['produto_id']; ?>]" min="0" max="99" value="<?php echo (int) ($item['quantidade'] ?? 0); ?>" aria-label="Quantidade de <?php echo htmlspecialchars($item['produto_nome'] ?: 'item'); ?>" style="width: 80px;">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <button type="submit" name="editar_pedido" class="botao-principal">Salvar quantidade</button>
                        </form>
                    </div>
                <?php endif; ?>
            </article>

            <aside class="bloco formularios">
                <div class="formulario-card">
                    <p class="etiqueta">NOVO PEDIDO</p>
                    <h2>Itens da mesa</h2>

                    <?php if (!$produtosCardapio): ?>
                        <p>Não há pratos disponíveis. Peça ao gerente ou administrador para cadastrar o cardápio.</p>
                    <?php else: ?>
                        <form method="POST">
                            <div class="pedido-tipo">
                                <label for="tipo_entrega">Tipo do pedido</label>
                                <div class="pedido-tipo-controles">
                                    <select id="tipo_entrega" name="tipo_entrega" class="pedido-tipo-select">
                                        <option value="mesa" <?php echo ($tipoPedidoSelecionado === Pedidos::TIPO_MESA) ? 'selected' : ''; ?>>Mesa</option>
                                        <option value="viagem" <?php echo ($tipoPedidoSelecionado === Pedidos::TIPO_VIAGEM) ? 'selected' : ''; ?>>Para viagem</option>
                                        <option value="entrega" <?php echo ($tipoPedidoSelecionado === Pedidos::TIPO_ENTREGA) ? 'selected' : ''; ?>>Entrega</option>
                                    </select>
                                    <button type="submit" name="selecionar_tipo" class="btn-topo">Aplicar</button>
                                </div>
                            </div>

                            <?php if ($tipoPedidoSelecionado === Pedidos::TIPO_MESA): ?>
                                <div class="pedido-contexto">
                                    <p class="etiqueta">ATENDIMENTO NA MESA</p>
                                    <label for="mesa_numero">Número da mesa</label>
                                    <input id="mesa_numero" type="number" name="mesa_numero" min="1" max="<?php echo (int) $limiteMesas; ?>" value="<?php echo htmlspecialchars((string) ($_POST['mesa_numero'] ?? 1)); ?>" required>
                                </div>
                            <?php endif; ?>

                            <?php if ($tipoPedidoSelecionado === Pedidos::TIPO_VIAGEM): ?>
                                <div class="pedido-contexto">
                                    <p class="etiqueta">RETIRADA</p>
                                    <p>Pedido para retirada no balcão. Use a observação para orientar a cozinha sobre embalagem.</p>
                                </div>
                            <?php endif; ?>

                            <?php if ($tipoPedidoSelecionado === Pedidos::TIPO_ENTREGA): ?>
                                <div class="pedido-contexto">
                                    <p class="etiqueta">ENTREGA</p>
                                    <label for="cliente_nome">Nome do cliente</label>
                                    <input id="cliente_nome" type="text" name="cliente_nome" value="<?php echo htmlspecialchars($clienteNome); ?>" placeholder="Ex.: Maria Souza">

                                    <div class="campo-grupo">
                                        <label for="cep_entrega">CEP da entrega</label>
                                        <div class="campo-com-acao">
                                            <input id="cep_entrega" type="text" name="cep_entrega" value="<?php echo htmlspecialchars($cepEntrega); ?>" placeholder="Digite o CEP" maxlength="9">
                                            <button type="submit" name="buscar_cep_entrega" class="btn-topo">Buscar CEP</button>
                                        </div>
                                    </div>

                                    <div class="campo-grupo">
                                        <label for="endereco_entrega">Rua / Logradouro</label>
                                        <input id="endereco_entrega" type="text" name="endereco_entrega" value="<?php echo htmlspecialchars($enderecoEntrega); ?>" placeholder="Rua, avenida...">
                                    </div>

                                    <div class="endereco-grid endereco-grid--numero">
                                        <div>
                                            <label for="bairro_endereco">Bairro</label>
                                            <input id="bairro_endereco" type="text" name="bairro_endereco" value="<?php echo htmlspecialchars($bairroEndereco); ?>" placeholder="Bairro">
                                        </div>
                                        <div>
                                            <label for="numero_endereco">Número</label>
                                            <input id="numero_endereco" type="text" name="numero_endereco" value="<?php echo htmlspecialchars($numeroEndereco); ?>" placeholder="123">
                                        </div>
                                    </div>

                                    <div class="endereco-grid endereco-grid--uf">
                                        <div>
                                            <label for="cidade_endereco">Cidade</label>
                                            <input id="cidade_endereco" type="text" name="cidade_endereco" value="<?php echo htmlspecialchars($cidadeEndereco); ?>" placeholder="Cidade">
                                        </div>
                                        <div>
                                            <label for="uf_endereco">UF</label>
                                            <input id="uf_endereco" type="text" name="uf_endereco" value="<?php echo htmlspecialchars($ufEndereco); ?>" maxlength="2" placeholder="SP">
                                        </div>
                                    </div>

                                    <div class="campo-grupo">
                                        <label for="complemento_endereco">Complemento</label>
                                        <input id="complemento_endereco" type="text" name="complemento_endereco" value="<?php echo htmlspecialchars($complementoEndereco); ?>" placeholder="Apartamento, bloco, referência...">
                                    </div>
                                </div>
                            <?php endif; ?>

                            <label for="observacao" class="label-com-espaco">Observação para a cozinha / embalagem</label>
                            <textarea id="observacao" name="observacao" class="campo-formulario" rows="3" placeholder="Ex.: sem molho, bem quente, para viagem, embalagem em caixa, entrega em 20 min... "><?php echo htmlspecialchars($observacao); ?></textarea>

                            <label for="busca-cardapio">Buscar item</label>
                            <input type="search" id="busca-cardapio" class="campo-formulario busca-cardapio" placeholder="Digite o nome do prato..." autocomplete="off">
                            <div class="itens-cardapio" style="height: 360px; max-height: 52vh; overflow-x: hidden; overflow-y: scroll;">
                                <?php foreach ($produtosCardapio as $produto): ?>
                                    <div class="item-cardapio" data-nome="<?php echo htmlspecialchars($produto['nome'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <div>
                                            <strong><?php echo htmlspecialchars($produto['nome']); ?></strong>
                                            <small><?php echo htmlspecialchars($produto['descricao']); ?></small>
                                            <span>R$ <?php echo number_format((float) $produto['preco'], 2, ',', '.'); ?></span>
                                        </div>
                                        <input type="number" name="quantidades[<?php echo (int) $produto['id']; ?>]" min="0" max="99" value="<?php echo (int) ($_POST['quantidades'][$produto['id']] ?? 0); ?>" aria-label="Quantidade de <?php echo htmlspecialchars($produto['nome']); ?>">
                                    </div>
                                <?php endforeach; ?>
                                <p class="cardapio-sem-resultados" aria-live="polite" hidden>Nenhum item encontrado.</p>
                            </div>

                            <button type="submit" name="adicionar_pedido" class="botao-principal">Enviar pedido</button>
                        </form>
                    <?php endif; ?>
                </div>
            </aside>
        </section>
    </main>
        <script>
            const buscaCardapio = document.querySelector('#busca-cardapio');
            const itensCardapio = document.querySelectorAll('.item-cardapio');
            const semResultados = document.querySelector('.cardapio-sem-resultados');

            buscaCardapio?.addEventListener('input', (evento) => {
                const termo = evento.target.value
                    .toLocaleLowerCase('pt-BR')
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '');
                let itensVisiveis = 0;

                itensCardapio.forEach((item) => {
                    const nome = item.dataset.nome
                        .toLocaleLowerCase('pt-BR')
                        .normalize('NFD')
                        .replace(/[\u0300-\u036f]/g, '');
                    const corresponde = nome.includes(termo);

                    // Em vez de usar .hidden, forçamos o CSS a esconder o elemento
                    item.style.display = corresponde ? '' : 'none';
                    
                    if (corresponde) itensVisiveis += 1;
                });

                if (semResultados) {
                    semResultados.style.display = itensVisiveis > 0 ? 'none' : 'block';
                }
            });
        </script>
</body>
</html>
