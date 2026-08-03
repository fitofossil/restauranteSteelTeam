<?php
/**
 * Gerenciamento de Cardápio - Dogão Lanches
 * Permite listar, cadastrar, editar, alternar a disponibilidade e EXCLUIR pratos, 
 * além de gerenciar categorias e tipos de produtos no sistema.
 */

require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Cardapio.php';
require_once __DIR__ . '/../src/Log.php';
require_once __DIR__ . '/../config/conexao.php';

// Controle de Acesso e Sessão
Auth::iniciarSessao();
Auth::requireGerenciarCardapio();

// Inicialização de variáveis de controle da View
$mensagem = '';
$tipoMensagem = '';
$produtoEditando = null;
$categorias = [];
$tipos = [];

try {
    // Garante estruturalmente que as tabelas necessárias existam no banco
    Cardapio::garantirTabelas($conn);
    Log::garantirTabela($conn);

    // Busca as opções de Categorias e Tipos para popular os seletores do formulário
    $categorias = $conn->query('SELECT id, nome FROM categorias_produto ORDER BY nome ASC')->fetchAll(PDO::FETCH_ASSOC);
    $tipos = $conn->query('SELECT id, nome FROM tipos_produto ORDER BY nome ASC')->fetchAll(PDO::FETCH_ASSOC);

    // -------------------------------------------------------------------------
    // PROCESSAMENTO DOS FORMULÁRIOS (POST)
    // -------------------------------------------------------------------------
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Sanitização de entradas genéricas de produtos
        $nome = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $preco = str_replace(',', '.', trim($_POST['preco'] ?? '')); // Trata vírgula decimal
        $categoriaId = filter_input(INPUT_POST, 'categoria_id', FILTER_VALIDATE_INT);
        $tipoId = filter_input(INPUT_POST, 'tipo_id', FILTER_VALIDATE_INT);

        // AÇÃO: Adicionar Nova Categoria
        if (isset($_POST['adicionar_categoria'])) {
            $nomeCategoria = trim($_POST['nome_categoria'] ?? '');
            if ($nomeCategoria === '' || mb_strlen($nomeCategoria) > 50) {
                throw new RuntimeException('Informe uma categoria válida.');
            }
            
            $stmt = $conn->prepare('INSERT INTO categorias_produto (nome) VALUES (?)');
            $stmt->execute([$nomeCategoria]);
            Log::registrar($conn, 'insert', 'categorias_produto', (int) $conn->lastInsertId(), "Categoria cadastrada: $nomeCategoria.");
            
            $mensagem = 'Categoria cadastrada.';
            $tipoMensagem = 'sucesso';
        } 
        
        // AÇÃO: Adicionar Novo Tipo
        elseif (isset($_POST['adicionar_tipo'])) {
            $nomeTipo = trim($_POST['nome_tipo'] ?? '');
            if ($nomeTipo === '' || mb_strlen($nomeTipo) > 50) {
                throw new RuntimeException('Informe um tipo válido.');
            }
            
            $stmt = $conn->prepare('INSERT INTO tipos_produto (nome) VALUES (?)');
            $stmt->execute([$nomeTipo]);
            Log::registrar($conn, 'insert', 'tipos_produto', (int) $conn->lastInsertId(), "Tipo cadastrado: $nomeTipo.");
            
            $mensagem = 'Tipo cadastrado.';
            $tipoMensagem = 'sucesso';
        } 
        
        // VALIDAÇÃO: Regras de negócio comuns para inserção/edição de produtos
        elseif (isset($_POST['adicionar_produto']) || isset($_POST['atualizar_produto'])) {
            if ($nome === '' || mb_strlen($nome) > 100) {
                throw new RuntimeException('Informe um nome de prato válido.');
            }
            if (mb_strlen($descricao) > 255) {
                throw new RuntimeException('A descrição pode ter no máximo 255 caracteres.');
            }
            if (!is_numeric($preco) || (float) $preco <= 0) {
                throw new RuntimeException('Informe um preço válido.');
            }
            if (!$categoriaId) {
                throw new RuntimeException('Selecione uma categoria.');
            }
            if (!$tipoId) {
                throw new RuntimeException('Selecione um tipo.');
            }
        }

        // AÇÃO: Efetivar Cadastro do Produto
        if (isset($_POST['adicionar_produto'])) {
            $stmt = $conn->prepare('INSERT INTO produtos (nome, tipo_id, categoria_id, descricao, preco, ativo) VALUES (?, ?, ?, ?, ?, 1)');
            $stmt->execute([$nome, $tipoId, $categoriaId, $descricao, $preco]);
            Log::registrar($conn, 'insert', 'produtos', (int) $conn->lastInsertId(), "Prato cadastrado: $nome (R$ $preco).");
            
            $mensagem = 'Prato cadastrado no cardápio.';
            $tipoMensagem = 'sucesso';
        }

        // AÇÃO: Efetivar Atualização do Produto Existente
        if (isset($_POST['atualizar_produto'])) {
            $id = filter_input(INPUT_POST, 'produto_id', FILTER_VALIDATE_INT);
            if (!$id) {
                throw new RuntimeException('Produto inválido.');
            }
            
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            $stmt = $conn->prepare('UPDATE produtos SET nome = ?, tipo_id = ?, categoria_id = ?, descricao = ?, preco = ?, ativo = ? WHERE id = ?');
            $stmt->execute([$nome, $tipoId, $categoriaId, $descricao, $preco, $ativo, $id]);
            Log::registrar($conn, 'update', 'produtos', $id, "Prato #$id atualizado: $nome (R$ $preco, ativo=" . ($ativo ? 'sim' : 'não') . ').');
            
            $mensagem = 'Prato atualizado.';
            $tipoMensagem = 'sucesso';
        }

        // AÇÃO: Alternar Disponibilidade (Ativo/Inativo) via atalho na listagem
        if (isset($_POST['toggle_produto'])) {
            $id = filter_input(INPUT_POST, 'produto_id', FILTER_VALIDATE_INT);
            if (!$id) {
                throw new RuntimeException('Produto inválido.');
            }
            
            $conn->prepare('UPDATE produtos SET ativo = NOT ativo WHERE id = ?')->execute([$id]);
            Log::registrar($conn, 'update', 'produtos', $id, "Disponibilidade do prato #$id alternada.");
            
            $mensagem = 'Disponibilidade do prato atualizada.';
            $tipoMensagem = 'sucesso';
        }

        // AÇÃO: Excluir Produto do Cardápio
        if (isset($_POST['deletar_produto'])) {
            $id = filter_input(INPUT_POST, 'produto_id', FILTER_VALIDATE_INT);
            if (!$id) {
                throw new RuntimeException('Produto inválido.');
            }

            $stmt = $conn->prepare('DELETE FROM produtos WHERE id = ?');
            $stmt->execute([$id]);
            Log::registrar($conn, 'delete', 'produtos', $id, "Prato #$id removido do cardápio.");

            $mensagem = 'Prato removido do cardápio com sucesso.';
            $tipoMensagem = 'sucesso';

            // Se o prato deletado for o mesmo que estava sendo editado na lateral, limpa a tela de edição
            if (isset($_GET['editar']) && (int)$_GET['editar'] === $id) {
                header('Location: cardapio.php');
                exit;
            }
        }
    }

    // -------------------------------------------------------------------------
    // VERIFICAÇÃO DE MODO DE EDIÇÃO (GET)
    // -------------------------------------------------------------------------
    if (isset($_GET['editar'])) {
        $id = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT);
        if ($id) {
            $stmt = $conn->prepare('SELECT id, nome, tipo_id, categoria_id, descricao, preco, ativo FROM produtos WHERE id = ?');
            $stmt->execute([$id]);
            $produtoEditando = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    }

    // Busca a lista atualizada de produtos para exibição na grade principal
    $produtos = $conn->query('
        SELECT p.id, p.nome, p.descricao, p.preco, p.ativo, p.tipo_id, p.categoria_id, 
               c.nome AS categoria_nome, t.nome AS tipo_nome
        FROM produtos p
        LEFT JOIN categorias_produto c ON c.id = p.categoria_id
        LEFT JOIN tipos_produto t ON t.id = p.tipo_id
        ORDER BY p.ativo DESC, p.nome ASC
    ')->fetchAll(PDO::FETCH_ASSOC);

} catch (RuntimeException $e) {
    $mensagem = $e->getMessage();
    $tipoMensagem = 'erro';
    $produtos = $produtos ?? [];
} catch (PDOException $e) {
    // Caso ocorra violação de Chave Estrangeira (se o item já estiver em um pedido antigo)
    if ($e->getCode() == 23000) {
        $mensagem = 'Este prato não pode ser excluído porque já está vinculado a um histórico de pedidos. Sugestão: Altere a disponibilidade para "Indisponível".';
    } else {
        $mensagem = 'Não foi possível acessar ou alterar o cardápio.';
    }
    $tipoMensagem = 'erro';
    $produtos = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cardápio - Dogão Lanches</title>
    <link rel="stylesheet" href="../public/css/style.css">
</head>
<body>
    <main class="painel">
        <!-- Topo da página -->
        <header class="topo">
            <div>
                <p class="marca">🌭 DOGÃO LANCHES</p>
                <h1>CARDÁPIO</h1>
                <p class="boas-vindas">Cadastre os pratos disponíveis para a equipe.</p>
            </div>
            <div class="topo-botoes">
                <a class="btn-topo" href="painel.php">← Voltar ao Painel</a>
                <a class="btn-topo" href="../logout.php">Sair</a>
            </div>
        </header>

        <!-- Alertas do Sistema (Sucesso / Erro) -->
        <?php if ($mensagem): ?>
            <div class="alerta <?php echo $tipoMensagem; ?>">
                <?php echo htmlspecialchars($mensagem); ?>
            </div>
        <?php endif; ?>

        <!-- Conteúdo Principal em Grade -->
        <section class="grade">
            
            <!-- Bloco Esquerdo: Listagem de Itens Cadastrados -->
            <article class="bloco lista">
                <div class="titulo-bloco">
                    <div>
                        <p class="etiqueta">PRATOS CADASTRADOS</p>
                        <h2>Cardápio</h2>
                    </div>
                    <span><?php echo count($produtos); ?> pratos</span>
                </div>

                <?php if (!$produtos): ?>
                    <p class="vazio">Nenhum prato cadastrado.</p>
                <?php else: ?>
                    <div class="tabela-wrap">
                        <table class="tabela">
                            <thead>
                                <tr>
                                    <th>Prato</th>
                                    <th>Categoria</th>
                                    <th>Tipo</th>
                                    <th>Descrição</th>
                                    <th>Preço</th>
                                    <th>Disponibilidade</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($produtos as $produto): ?>
                                    <tr>
                                        <td class="nome-func"><?php echo htmlspecialchars($produto['nome']); ?></td>
                                        <td><?php echo htmlspecialchars($produto['categoria_nome'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($produto['tipo_nome'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($produto['descricao']); ?></td>
                                        <td>R$ <?php echo number_format((float) $produto['preco'], 2, ',', '.'); ?></td>
                                        <td>
                                            <!-- Form para alterar status com um único clique -->
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="produto_id" value="<?php echo (int) $produto['id']; ?>">
                                                <button name="toggle_produto" class="status-btn <?php echo $produto['ativo'] ? 'ativo' : 'inativo'; ?>">
                                                    <?php echo $produto['ativo'] ? 'Disponível' : 'Indisponível'; ?>
                                                </button>
                                            </form>
                                        </td>
                                        <td>
                                            <div class="acoes-flex" style="display: flex; gap: 8px;">
                                                <a class="btn-editar" href="?editar=<?php echo (int) $produto['id']; ?>">Editar</a>
                                                
                                                <!-- Form dinâmico para exclusão segura -->
                                                <form method="POST" class="inline" onsubmit="return confirm('Tem certeza que deseja excluir o prato \'<?php echo addslashes($produto['nome']); ?>\' do cardápio?');">
                                                    <input type="hidden" name="produto_id" value="<?php echo (int) $produto['id']; ?>">
                                                    <button name="deletar_produto" class="btn-cancelar" style="padding: 4px 8px; font-size: 0.85em; margin: 0; background-color: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer;">Excluir</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </article>

            <!-- Bloco Direito: Formulários Dinâmicos Laterais -->
            <aside class="bloco formularios">
                
                <!-- Formulário: Criar / Editar Prato -->
                <div class="formulario-card">
                    <p class="etiqueta"><?php echo $produtoEditando ? 'EDITAR PRATO' : 'NOVO PRATO'; ?></p>
                    <h2><?php echo $produtoEditando ? 'Editar prato' : 'Cadastrar prato'; ?></h2>
                    
                    <form method="POST">
                        <?php if ($produtoEditando): ?>
                            <input type="hidden" name="produto_id" value="<?php echo (int) $produtoEditando['id']; ?>">
                        <?php endif; ?>

                        <label for="nome">Nome do prato</label>
                        <input id="nome" name="nome" maxlength="100" value="<?php echo htmlspecialchars($produtoEditando['nome'] ?? ($_POST['nome'] ?? '')); ?>" required>

                        <label for="categoria_id">Categoria</label>
                        <select id="categoria_id" name="categoria_id" required>
                            <option value="">Selecione</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?php echo (int) $categoria['id']; ?>" <?php echo (($produtoEditando['categoria_id'] ?? ($_POST['categoria_id'] ?? '')) == $categoria['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($categoria['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label for="tipo_id">Tipo</label>
                        <select id="tipo_id" name="tipo_id" required>
                            <option value="">Selecione</option>
                            <?php foreach ($tipos as $tipo): ?>
                                <option value="<?php echo (int) $tipo['id']; ?>" <?php echo (($produtoEditando['tipo_id'] ?? ($_POST['tipo_id'] ?? '')) == $tipo['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tipo['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label for="descricao">Descrição</label>
                        <input id="descricao" name="descricao" maxlength="255" value="<?php echo htmlspecialchars($produtoEditando['descricao'] ?? ($_POST['descricao'] ?? '')); ?>" placeholder="Ex.: pão, salsicha, molho e queijo">

                        <label for="preco">Preço</label>
                        <input id="preco" name="preco" inputmode="decimal" value="<?php echo htmlspecialchars($produtoEditando['preco'] ?? ($_POST['preco'] ?? '')); ?>" placeholder="0,00" required>

                        <?php if ($produtoEditando): ?>
                            <label class="checkbox-label">
                                <input type="checkbox" name="ativo" value="1" <?php echo $produtoEditando['ativo'] ? 'checked' : ''; ?>>
                                Prato disponível
                            </label>
                        <?php endif; ?>

                        <button class="botao-principal" name="<?php echo $produtoEditando ? 'atualizar_produto' : 'adicionar_produto'; ?>">
                            <?php echo $produtoEditando ? 'Salvar alterações' : 'Cadastrar prato'; ?>
                        </button>
                        
                        <?php if ($produtoEditando): ?>
                            <a class="btn-cancelar" href="cardapio.php">Cancelar</a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Formulário: Rápido para Nova Categoria -->
                <div class="formulario-card">
                    <p class="etiqueta">NOVA CATEGORIA</p>
                    <h2>Cadastrar categoria</h2>
                    <form method="POST">
                        <label for="nome_categoria">Nome da categoria</label>
                        <input id="nome_categoria" name="nome_categoria" maxlength="50" required>
                        <button class="botao-principal" name="adicionar_categoria">Salvar categoria</button>
                    </form>
                </div>

                <!-- Formulário: Rápido para Novo Tipo -->
                <div class="formulario-card">
                    <p class="etiqueta">NOVO TIPO</p>
                    <h2>Cadastrar tipo</h2>
                    <form method="POST">
                        <label for="nome_tipo">Nome do tipo</label>
                        <input id="nome_tipo" name="nome_tipo" maxlength="50" required>
                        <button class="botao-principal" name="adicionar_tipo">Salvar tipo</button>
                    </form>
                </div>

            </aside>
        </section>
    </main>
</body>
</html>