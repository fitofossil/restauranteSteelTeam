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

function pixCampoEmv(string $id, string $valor): string
{
    return $id . str_pad((string) strlen($valor), 2, '0', STR_PAD_LEFT) . $valor;
}

function pixNormalizarTexto(string $texto, int $limite): string
{
    $texto = trim($texto);
    $texto = function_exists('mb_strtoupper') ? mb_strtoupper($texto, 'UTF-8') : strtoupper($texto);

    $semAcentos = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
    if ($semAcentos !== false) {
        $texto = $semAcentos;
    }

    $texto = preg_replace('/[^A-Z0-9 .,\-\/]/', '', $texto) ?? '';
    $texto = preg_replace('/\s+/', ' ', $texto) ?? '';

    return substr(trim($texto), 0, $limite);
}

function pixCrc16(string $payload): string
{
    $polinomio = 0x1021;
    $resultado = 0xFFFF;

    for ($i = 0, $len = strlen($payload); $i < $len; $i++) {
        $resultado ^= (ord($payload[$i]) << 8);
        for ($bit = 0; $bit < 8; $bit++) {
            if (($resultado & 0x8000) !== 0) {
                $resultado = (($resultado << 1) ^ $polinomio) & 0xFFFF;
            } else {
                $resultado = ($resultado << 1) & 0xFFFF;
            }
        }
    }

    return strtoupper(str_pad(dechex($resultado), 4, '0', STR_PAD_LEFT));
}

function gerarPayloadPix(string $chave, float $valor, string $nomeRecebedor, string $cidadeRecebedor, string $txid, string $descricao = ''): string
{
    $chave = trim($chave);
    if ($chave === '' || $valor <= 0) {
        return '';
    }

    $nomeRecebedor = pixNormalizarTexto($nomeRecebedor, 25);
    $cidadeRecebedor = pixNormalizarTexto($cidadeRecebedor, 15);
    $descricao = pixNormalizarTexto($descricao, 40);
    $txid = pixNormalizarTexto($txid, 25);

    $contaPix = pixCampoEmv('00', 'br.gov.bcb.pix') . pixCampoEmv('01', $chave);
    if ($descricao !== '') {
        $contaPix .= pixCampoEmv('02', $descricao);
    }

    $adicional = pixCampoEmv('05', $txid !== '' ? $txid : '***');

    $payloadSemCrc = '';
    $payloadSemCrc .= pixCampoEmv('00', '01');
    $payloadSemCrc .= pixCampoEmv('26', $contaPix);
    $payloadSemCrc .= pixCampoEmv('52', '0000');
    $payloadSemCrc .= pixCampoEmv('53', '986');
    $payloadSemCrc .= pixCampoEmv('54', number_format($valor, 2, '.', ''));
    $payloadSemCrc .= pixCampoEmv('58', 'BR');
    $payloadSemCrc .= pixCampoEmv('59', $nomeRecebedor !== '' ? $nomeRecebedor : 'DOGAO LANCHES');
    $payloadSemCrc .= pixCampoEmv('60', $cidadeRecebedor !== '' ? $cidadeRecebedor : 'SAO PAULO');
    $payloadSemCrc .= pixCampoEmv('62', $adicional);
    $payloadSemCrc .= '6304';

    return $payloadSemCrc . pixCrc16($payloadSemCrc);
}

// Inicialização e segurança da sessão
Auth::iniciarSessao();
Auth::requirePedidosView();

// Definição de permissões com base no papel do usuário logado
$mensagem = '';
$tipoMensagem = '';
$podeEditar = Auth::isRecepcao() || Auth::isAdmin() || Auth::isGerente();
$podeZerarDia = Auth::isAdmin() || Auth::isGerente();
$pixPedidoSelecionadoId = filter_input(INPUT_GET, 'pix_pedido_id', FILTER_VALIDATE_INT);
$pixPayload = '';
$pixQrCodeUrl = '';
$pixErro = '';
$pixAviso = '';

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
            $formaPagamento = $_POST['forma_pagamento'] ?? null;

            if (!$id) {
                throw new RuntimeException('Pedido inválido.');
            }
            if (!in_array($status, Pedidos::statusValidos(), true)) {
                throw new RuntimeException('Selecione um status de pagamento válido.');
            }

            if ($status === Pedidos::PAGO && !in_array($formaPagamento, Pedidos::formasPagamentoValidas(), true)) {
                throw new RuntimeException('Selecione a forma de pagamento para finalizar a venda.');
            }

            if ($status === Pedidos::PENDENTE) {
                $formaPagamento = null;
            }

            $stmtPedido = $conn->prepare('SELECT id, mesa_numero, tipo_entrega, criado_em FROM pedidos WHERE id = :id LIMIT 1');
            $stmtPedido->execute([':id' => $id]);
            $pedidoBase = $stmtPedido->fetch(PDO::FETCH_ASSOC);

            if (!$pedidoBase) {
                throw new RuntimeException('Pedido não encontrado.');
            }

            if ($status === Pedidos::PAGO && ($pedidoBase['tipo_entrega'] ?? '') === Pedidos::TIPO_MESA) {
                $mesaNumero = (int) ($pedidoBase['mesa_numero'] ?? 0);
                $dataPedido = date('Y-m-d', strtotime((string) $pedidoBase['criado_em']));

                $stmt = $conn->prepare("UPDATE pedidos
                    SET status_pagamento = :status, forma_pagamento = :forma_pagamento
                    WHERE tipo_entrega = :tipo_mesa
                      AND mesa_numero = :mesa
                      AND status_pagamento = :pendente
                      AND DATE(criado_em) = :data_pedido");
                $stmt->execute([
                    ':status' => $status,
                    ':forma_pagamento' => $formaPagamento,
                    ':tipo_mesa' => Pedidos::TIPO_MESA,
                    ':mesa' => $mesaNumero,
                    ':pendente' => Pedidos::PENDENTE,
                    ':data_pedido' => $dataPedido,
                ]);

                if ($stmt->rowCount() === 0) {
                    throw new RuntimeException('Não há pedidos pendentes nessa mesa para encerrar.');
                }

                Log::registrar($conn, 'update', 'pedidos', $id, "Mesa #$mesaNumero encerrada ao marcar o pedido #$id como pago.");
                $mensagem = "Pedido pago e mesa $mesaNumero encerrada automaticamente.";
                $tipoMensagem = 'sucesso';
            } else {
                $stmt = $conn->prepare('UPDATE pedidos SET status_pagamento = :status, forma_pagamento = :forma_pagamento WHERE id = :id');
                $stmt->execute([':status' => $status, ':forma_pagamento' => $formaPagamento, ':id' => $id]);

                if ($stmt->rowCount() === 0) {
                    throw new RuntimeException('Pedido não encontrado.');
                }

                Log::registrar($conn, 'update', 'pedidos', $id, "Pagamento do pedido #$id marcado como '$status'.");
                $mensagem = $status === Pedidos::PAGO ? 'Pedido marcado como pago.' : 'Pedido marcado como ainda não pago.';
                $tipoMensagem = 'sucesso';
            }
        }
    }

    // -------------------------------------------------------------------------
    // CONSTRUÇÃO DA LISTAGEM DE PEDIDOS (INTEGRADA COM CARDÁPIO)
    // -------------------------------------------------------------------------
    $queryPedidos = "
        SELECT p.id, 
               p.mesa_numero, 
               p.status_pagamento,
               p.forma_pagamento,
               p.tipo_entrega,
               p.observacao,
               p.cliente_nome,
               p.cep_entrega,
               p.endereco_entrega,
               p.numero_endereco,
               p.complemento_endereco,
               p.bairro_endereco,
               p.cidade_endereco,
               p.uf_endereco,
               p.criado_em,
               COALESCE(SUM(pi.preco_unitario * pi.quantidade), 0) AS valor,
               GROUP_CONCAT(CONCAT(pi.quantidade, 'x ', pr.nome) ORDER BY pi.id SEPARATOR ' • ') AS itens
        FROM pedidos p 
        LEFT JOIN pedido_itens pi ON pi.pedido_id = p.id
        LEFT JOIN produtos pr ON pr.id = pi.produto_id
        GROUP BY p.id, p.mesa_numero, p.status_pagamento, p.forma_pagamento, p.tipo_entrega, p.observacao, p.cliente_nome, p.cep_entrega, p.endereco_entrega, p.numero_endereco, p.complemento_endereco, p.bairro_endereco, p.cidade_endereco, p.uf_endereco, p.criado_em
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

    if ($pixPedidoSelecionadoId) {
        $pedidoPix = null;
        foreach ($pedidosOrdenados as $pedidoAtual) {
            if ((int) $pedidoAtual['id'] === (int) $pixPedidoSelecionadoId) {
                $pedidoPix = $pedidoAtual;
                break;
            }
        }

        if (!$pedidoPix) {
            $pixErro = 'Pedido selecionado para PIX não foi encontrado.';
        } else {
            $valorPix = (float) ($pedidoPix['valor'] ?? 0);
            if ($valorPix <= 0) {
                $pixErro = 'Este pedido não possui valor para gerar o PIX.';
            } else {
                $pixChave = trim((string) (getenv('PIX_CHAVE') ?: (defined('PIX_CHAVE') ? PIX_CHAVE : '')));
                $pixNome = trim((string) (getenv('PIX_NOME_RECEBEDOR') ?: 'DOGAO LANCHES'));
                $pixCidade = trim((string) (getenv('PIX_CIDADE_RECEBEDOR') ?: 'SAO PAULO'));

                if ($pixChave === '') {
                    $pixPayload = 'PIX-SIMULADO|PEDIDO:' . (int) $pedidoPix['id'] . '|VALOR:' . number_format($valorPix, 2, '.', '') . '|GERADO:' . date('YmdHis');
                    $pixQrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=' . rawurlencode($pixPayload);
                    $pixAviso = 'Modo de simulação ativo: QR Code apenas para demonstração (não realiza cobrança real).';
                } else {
                    $txid = 'PD' . (int) $pedidoPix['id'] . date('His');
                    $descricao = 'Pedido #' . (int) $pedidoPix['id'];
                    $pixPayload = gerarPayloadPix($pixChave, $valorPix, $pixNome, $pixCidade, $txid, $descricao);

                    if ($pixPayload === '') {
                        $pixErro = 'Não foi possível montar o código PIX deste pedido.';
                    } else {
                        $pixQrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=' . rawurlencode($pixPayload);
                    }
                }
            }
        }
    }

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

        <?php if ($pixPedidoSelecionadoId): ?>
            <section class="bloco pix-qr-bloco" aria-label="QR Code para pagamento PIX">
                <div class="titulo-bloco">
                    <div>
                        <p class="etiqueta">PAGAMENTO PIX</p>
                        <h2>Cobrança do pedido #<?php echo (int) $pixPedidoSelecionadoId; ?></h2>
                    </div>
                    <a class="btn-topo" href="pedidos.php">Fechar QR</a>
                </div>

                <?php if ($pixErro !== ''): ?>
                    <div class="alerta erro"><?php echo htmlspecialchars($pixErro); ?></div>
                <?php else: ?>
                    <?php if ($pixAviso !== ''): ?>
                        <div class="alerta"><?php echo htmlspecialchars($pixAviso); ?></div>
                    <?php endif; ?>
                    <div class="pix-qr-grid">
                        <div>
                            <img class="pix-qr-imagem" src="<?php echo htmlspecialchars($pixQrCodeUrl); ?>" alt="QR Code PIX do pedido <?php echo (int) $pixPedidoSelecionadoId; ?>">
                        </div>
                        <div>
                            <p class="pix-qr-instrucao">Escaneie no app do banco para pagar.</p>
                            <label class="pix-copia-label" for="pix-copia-cola">PIX copia e cola</label>
                            <textarea id="pix-copia-cola" class="pix-copia-cola" readonly><?php echo htmlspecialchars($pixPayload); ?></textarea>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

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
                                    <th>Tipo</th>
                                    <th>Mesa / Retirada</th>
                                    <th>Itens (Cardápio)</th>
                                    <th>Observação</th>
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
                                        <td><strong><?php echo htmlspecialchars(Pedidos::textoTipoEntrega((string) ($pedido['tipo_entrega'] ?? Pedidos::TIPO_MESA))); ?></strong></td>
                                        <td><strong><?php echo (($pedido['tipo_entrega'] ?? Pedidos::TIPO_MESA) === Pedidos::TIPO_MESA) ? 'Mesa ' . (int) $pedido['mesa_numero'] : (($pedido['tipo_entrega'] ?? '') === Pedidos::TIPO_VIAGEM ? 'Retirada' : 'Entrega'); ?></strong></td>
                                        
                                        <!-- Renderiza os pratos oficiais inseridos via cardapio.php -->
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
                                        <td>
                                            <span class="badge pagamento-<?php echo htmlspecialchars($pedido['status_pagamento']); ?>">
                                                <?php echo $pedido['status_pagamento'] === Pedidos::PAGO ? 'Pago - ' . htmlspecialchars(Pedidos::textoFormaPagamento($pedido['forma_pagamento'] ?? null)) : 'Ainda não pago'; ?>
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
                                                        <label class="sr-only" for="forma-pagamento-<?php echo (int) $pedido['id']; ?>">Forma de pagamento</label>
                                                        <select id="forma-pagamento-<?php echo (int) $pedido['id']; ?>" name="forma_pagamento" required>
                                                            <option value="">Forma de pagamento</option>
                                                            <option value="dinheiro">Dinheiro</option>
                                                            <option value="cartao">Cartão</option>
                                                            <option value="pix">PIX</option>
                                                        </select>
                                                        <button type="submit" name="marcar_pagamento" class="btn-pronto">Marcar como pago</button>
                                                    </form>
                                                    <a class="btn-editar btn-pix" href="?pix_pedido_id=<?php echo (int) $pedido['id']; ?>">Gerar QR PIX</a>
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