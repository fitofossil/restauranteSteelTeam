<?php
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Cardapio.php';
require_once __DIR__ . '/../config/conexao.php';

Auth::iniciarSessao();
Auth::requireGerenciarCardapio();

$mensagem = '';
$tipoMensagem = '';
$produtoEditando = null;

try {
    Cardapio::garantirTabelas($conn);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nome = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $preco = str_replace(',', '.', trim($_POST['preco'] ?? ''));

        if (isset($_POST['adicionar_produto']) || isset($_POST['atualizar_produto'])) {
            if ($nome === '' || mb_strlen($nome) > 100) throw new RuntimeException('Informe um nome de prato válido.');
            if (mb_strlen($descricao) > 255) throw new RuntimeException('A descrição pode ter no máximo 255 caracteres.');
            if (!is_numeric($preco) || (float) $preco <= 0) throw new RuntimeException('Informe um preço válido.');
        }

        if (isset($_POST['adicionar_produto'])) {
            $stmt = $conn->prepare('INSERT INTO cardapio_produtos (nome, descricao, preco, ativo) VALUES (?, ?, ?, 1)');
            $stmt->execute([$nome, $descricao, $preco]);
            $mensagem = 'Prato cadastrado no cardápio.';
            $tipoMensagem = 'sucesso';
        }

        if (isset($_POST['atualizar_produto'])) {
            $id = filter_input(INPUT_POST, 'produto_id', FILTER_VALIDATE_INT);
            if (!$id) throw new RuntimeException('Produto inválido.');
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            $stmt = $conn->prepare('UPDATE cardapio_produtos SET nome = ?, descricao = ?, preco = ?, ativo = ? WHERE id = ?');
            $stmt->execute([$nome, $descricao, $preco, $ativo, $id]);
            $mensagem = 'Prato atualizado.';
            $tipoMensagem = 'sucesso';
        }

        if (isset($_POST['toggle_produto'])) {
            $id = filter_input(INPUT_POST, 'produto_id', FILTER_VALIDATE_INT);
            if (!$id) throw new RuntimeException('Produto inválido.');
            $conn->prepare('UPDATE cardapio_produtos SET ativo = NOT ativo WHERE id = ?')->execute([$id]);
            $mensagem = 'Disponibilidade do prato atualizada.';
            $tipoMensagem = 'sucesso';
        }
    }

    if (isset($_GET['editar'])) {
        $id = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT);
        if ($id) {
            $stmt = $conn->prepare('SELECT id, nome, descricao, preco, ativo FROM cardapio_produtos WHERE id = ?');
            $stmt->execute([$id]);
            $produtoEditando = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    }

    $produtos = $conn->query('SELECT id, nome, descricao, preco, ativo FROM cardapio_produtos ORDER BY ativo DESC, nome ASC')->fetchAll(PDO::FETCH_ASSOC);
} catch (RuntimeException $e) {
    $mensagem = $e->getMessage(); $tipoMensagem = 'erro'; $produtos = $produtos ?? [];
} catch (PDOException $e) {
    $mensagem = 'Não foi possível acessar o cardápio.'; $tipoMensagem = 'erro'; $produtos = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cardápio - Dogão Lanches</title><link rel="stylesheet" href="../public/css/style.css">
</head>
<body><main class="painel">
    <header class="topo"><div><p class="marca">🌭 DOGÃO LANCHES</p><h1>CARDÁPIO</h1><p class="boas-vindas">Cadastre os pratos disponíveis para a equipe.</p></div>
        <div class="topo-botoes"><a class="btn-topo" href="painel.php">← Voltar ao Painel</a><a class="btn-topo" href="../logout.php">Sair</a></div></header>
    <?php if ($mensagem): ?><div class="alerta <?php echo $tipoMensagem; ?>"><?php echo htmlspecialchars($mensagem); ?></div><?php endif; ?>
    <section class="grade">
        <article class="bloco lista"><div class="titulo-bloco"><div><p class="etiqueta">PRATOS CADASTRADOS</p><h2>Cardápio</h2></div><span><?php echo count($produtos); ?> pratos</span></div>
        <?php if (!$produtos): ?><p class="vazio">Nenhum prato cadastrado.</p><?php else: ?><div class="tabela-wrap"><table class="tabela"><thead><tr><th>Prato</th><th>Descrição</th><th>Preço</th><th>Disponibilidade</th><th>Ações</th></tr></thead><tbody>
        <?php foreach ($produtos as $produto): ?><tr><td class="nome-func"><?php echo htmlspecialchars($produto['nome']); ?></td><td><?php echo htmlspecialchars($produto['descricao']); ?></td><td>R$ <?php echo number_format((float) $produto['preco'], 2, ',', '.'); ?></td><td><form method="POST" class="inline"><input type="hidden" name="produto_id" value="<?php echo (int) $produto['id']; ?>"><button name="toggle_produto" class="status-btn <?php echo $produto['ativo'] ? 'ativo' : 'inativo'; ?>"><?php echo $produto['ativo'] ? 'Disponível' : 'Indisponível'; ?></button></form></td><td><a class="btn-editar" href="?editar=<?php echo (int) $produto['id']; ?>">Editar</a></td></tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?></article>
        <aside class="bloco formularios"><div class="formulario-card"><p class="etiqueta"><?php echo $produtoEditando ? 'EDITAR PRATO' : 'NOVO PRATO'; ?></p><h2><?php echo $produtoEditando ? 'Editar prato' : 'Cadastrar prato'; ?></h2><form method="POST">
        <?php if ($produtoEditando): ?><input type="hidden" name="produto_id" value="<?php echo (int) $produtoEditando['id']; ?>"><?php endif; ?>
        <label for="nome">Nome do prato</label><input id="nome" name="nome" maxlength="100" value="<?php echo htmlspecialchars($produtoEditando['nome'] ?? ($_POST['nome'] ?? '')); ?>" required>
        <label for="descricao">Descrição</label><input id="descricao" name="descricao" maxlength="255" value="<?php echo htmlspecialchars($produtoEditando['descricao'] ?? ($_POST['descricao'] ?? '')); ?>" placeholder="Ex.: pão, salsicha, molho e queijo">
        <label for="preco">Preço</label><input id="preco" name="preco" inputmode="decimal" value="<?php echo htmlspecialchars($produtoEditando['preco'] ?? ($_POST['preco'] ?? '')); ?>" placeholder="0,00" required>
        <?php if ($produtoEditando): ?><label class="checkbox-label"><input type="checkbox" name="ativo" value="1" <?php echo $produtoEditando['ativo'] ? 'checked' : ''; ?>>Prato disponível</label><?php endif; ?>
        <button class="botao-principal" name="<?php echo $produtoEditando ? 'atualizar_produto' : 'adicionar_produto'; ?>"><?php echo $produtoEditando ? 'Salvar alterações' : 'Cadastrar prato'; ?></button>
        <?php if ($produtoEditando): ?><a class="btn-cancelar" href="cardapio.php">Cancelar</a><?php endif; ?></form></div></aside>
    </section>
</main></body></html>
