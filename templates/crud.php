<?php
// =============================================================
// CADASTRO DE FUNCIONÁRIOS — CRUD
// =============================================================
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../config/conexao.php';
Auth::requireAdmin();

$mensagem = '';
$tipoMensagem = '';
$funcionarioEditando = null;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // ADICIONAR FUNCIONÁRIO
        if (isset($_POST['adicionar_funcionario'])) {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $senha = $_POST['senha'] ?? '';
            $role = filter_input(INPUT_POST, 'role', FILTER_VALIDATE_INT);

            if ($username === '' || mb_strlen($username) > 45) throw new RuntimeException('Informe um nome válido (máx. 45 caracteres).');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Informe um e-mail válido.');
            if (strlen($senha) < 6) throw new RuntimeException('A senha deve ter no mínimo 6 caracteres.');
            if (!$role || $role < 1 || $role > 3) throw new RuntimeException('Selecione um perfil válido.');

            $check = $conn->prepare('SELECT id FROM users_login WHERE email = ? LIMIT 1');
            $check->execute([$email]);
            if ($check->fetch()) throw new RuntimeException('Este e-mail já está cadastrado.');

            $hash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('INSERT INTO users_login (username, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1)');
            $stmt->execute([$username, $email, $hash, $role]);
            $mensagem = 'Funcionário cadastrado com sucesso.';
            $tipoMensagem = 'sucesso';
        }

        // EDITAR FUNCIONÁRIO
        if (isset($_POST['atualizar_funcionario'])) {
            $id = filter_input(INPUT_POST, 'funcionario_id', FILTER_VALIDATE_INT);
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $role = filter_input(INPUT_POST, 'role', FILTER_VALIDATE_INT);
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if (!$id) throw new RuntimeException('Funcionário inválido.');
            if ($username === '' || mb_strlen($username) > 45) throw new RuntimeException('Informe um nome válido (máx. 45 caracteres).');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Informe um e-mail válido.');
            if (!$role || $role < 1 || $role > 3) throw new RuntimeException('Selecione um perfil válido.');

            $check = $conn->prepare('SELECT id FROM users_login WHERE email = ? AND id != ? LIMIT 1');
            $check->execute([$email, $id]);
            if ($check->fetch()) throw new RuntimeException('Este e-mail já está em uso por outro funcionário.');

            $senha = $_POST['senha'] ?? '';
            if (strlen($senha) > 0) {
                if (strlen($senha) < 6) throw new RuntimeException('A senha deve ter no mínimo 6 caracteres.');
                $hash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $conn->prepare('UPDATE users_login SET username = ?, email = ?, password_hash = ?, role = ?, is_active = ? WHERE id = ?');
                $stmt->execute([$username, $email, $hash, $role, $is_active, $id]);
            } else {
                $stmt = $conn->prepare('UPDATE users_login SET username = ?, email = ?, role = ?, is_active = ? WHERE id = ?');
                $stmt->execute([$username, $email, $role, $is_active, $id]);
            }

            if ($id == Auth::getId()) {
                $_SESSION['usuario'] = $username;
                $_SESSION['usuario_role'] = $role;
            }

            $mensagem = 'Funcionário atualizado com sucesso.';
            $tipoMensagem = 'sucesso';
        }

        // EXCLUIR FUNCIONÁRIO
        if (isset($_POST['excluir_funcionario'])) {
            $id = filter_input(INPUT_POST, 'funcionario_id', FILTER_VALIDATE_INT);
            if (!$id) throw new RuntimeException('Funcionário inválido.');
            if ($id == Auth::getId()) throw new RuntimeException('Você não pode excluir seu próprio usuário.');
            $stmt = $conn->prepare('DELETE FROM users_login WHERE id = ?');
            $stmt->execute([$id]);
            $mensagem = 'Funcionário excluído com sucesso.';
            $tipoMensagem = 'sucesso';
        }

        // ATIVAR / DESATIVAR
        if (isset($_POST['toggle_status'])) {
            $id = filter_input(INPUT_POST, 'funcionario_id', FILTER_VALIDATE_INT);
            if (!$id) throw new RuntimeException('Funcionário inválido.');
            $stmt = $conn->prepare('UPDATE users_login SET is_active = NOT is_active WHERE id = ?');
            $stmt->execute([$id]);
            $mensagem = 'Status do funcionário alterado.';
            $tipoMensagem = 'sucesso';
        }
    }

    // LISTAR FUNCIONÁRIOS
    $busca = trim($_GET['busca'] ?? '');
    if ($busca !== '') {
        $stmt = $conn->prepare('SELECT id, username, email, role, is_active FROM users_login WHERE username LIKE ? OR email LIKE ? ORDER BY username');
        $stmt->execute(["%$busca%", "%$busca%"]);
    } else {
        $stmt = $conn->query('SELECT id, username, email, role, is_active FROM users_login ORDER BY username');
    }
    $funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // CARREGAR PARA EDIÇÃO
    if (isset($_GET['editar'])) {
        $editarId = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT);
        if ($editarId) {
            $stmt = $conn->prepare('SELECT id, username, email, role, is_active FROM users_login WHERE id = ?');
            $stmt->execute([$editarId]);
            $funcionarioEditando = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
} catch (RuntimeException $e) {
    $mensagem = $e->getMessage(); $tipoMensagem = 'erro'; $funcionarios = $funcionarios ?? [];
} catch (PDOException $e) {
    $mensagem = 'Erro ao acessar o banco de dados.'; $tipoMensagem = 'erro'; $funcionarios = [];
}

$roles = [1 => 'Administrador', 2 => 'Gerente', 3 => 'Funcionário'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Funcionários - Dogão Lanches</title>
    <link rel="stylesheet" href="../public/css/style.css">
</head>
<body>
    <main class="painel">
        <header class="topo">
            <div>
                <p class="marca">🌭 DOGÃO LANCHES</p>
                <h1>Cadastro de Funcionários</h1>
                <p class="boas-vindas">Olá, <?php echo Auth::sanitizarTexto(Auth::getNome()); ?>. Gerencie a equipe do restaurante.</p>
            </div>
            <div class="topo-botoes">
                <a class="btn-topo" href="painel.php">← Voltar ao Painel</a>
                <a class="btn-topo" href="../logout.php">Sair</a>
            </div>
        </header>

        <?php if ($mensagem): ?>
            <div class="alerta <?php echo $tipoMensagem; ?>"><?php echo htmlspecialchars($mensagem); ?></div>
        <?php endif; ?>

        <form class="busca" method="GET">
            <input type="text" name="busca" placeholder="Buscar por nome ou e-mail..." value="<?php echo htmlspecialchars($busca); ?>">
            <button type="submit">Buscar</button>
            <?php if ($busca !== ''): ?>
                <a class="limpar" href="crud.php">Limpar</a>
            <?php endif; ?>
        </form>

        <section class="grade">
            <article class="bloco lista">
                <div class="titulo-bloco">
                    <div><p class="etiqueta">EQUIPE</p><h2>Funcionários</h2></div>
                    <span><?php echo count($funcionarios); ?> cadastrados</span>
                </div>

                <?php if (empty($funcionarios)): ?>
                    <p class="vazio">Nenhum funcionário encontrado.</p>
                <?php else: ?>
                    <div class="tabela-wrap">
                        <table class="tabela">
                            <thead>
                                <tr><th></th><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Status</th><th>Ações</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($funcionarios as $f): ?>
                                    <tr>
                                        <td><div class="avatar-tabela"><?php echo strtoupper(htmlspecialchars(mb_substr($f['username'], 0, 1))); ?></div></td>
                                        <td class="nome-func"><?php echo htmlspecialchars($f['username']); ?></td>
                                        <td class="email-func"><?php echo htmlspecialchars($f['email']); ?></td>
                                        <td><span class="badge perfil-<?php echo (int)$f['role']; ?>"><?php echo $roles[(int)$f['role']] ?? 'Desconhecido'; ?></span></td>
                                        <td>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="funcionario_id" value="<?php echo (int)$f['id']; ?>">
                                                <button type="submit" name="toggle_status" class="status-btn <?php echo $f['is_active'] ? 'ativo' : 'inativo'; ?>"><?php echo $f['is_active'] ? 'Ativo' : 'Inativo'; ?></button>
                                            </form>
                                        </td>
                                        <td class="acoes">
                                            <a class="btn-editar" href="?editar=<?php echo (int)$f['id']; ?><?php echo $busca !== '' ? '&busca=' . urlencode($busca) : ''; ?>">Editar</a>
                                            <?php if ($f['id'] != Auth::getId()): ?>
                                                <form method="POST" class="inline" onsubmit="return confirm('Excluir este funcionário?');">
                                                    <input type="hidden" name="funcionario_id" value="<?php echo (int)$f['id']; ?>">
                                                    <button type="submit" name="excluir_funcionario" class="btn-excluir">Excluir</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </article>

            <aside class="bloco formularios">
                <div class="formulario-card">
                    <p class="etiqueta"><?php echo $funcionarioEditando ? 'EDITAR' : 'NOVO'; ?></p>
                    <h2><?php echo $funcionarioEditando ? 'Editar Funcionário' : 'Cadastrar Funcionário'; ?></h2>
                    <form method="POST">
                        <?php if ($funcionarioEditando): ?>
                            <input type="hidden" name="funcionario_id" value="<?php echo (int)$funcionarioEditando['id']; ?>">
                        <?php endif; ?>

                        <label for="username">Nome</label>
                        <input id="username" type="text" name="username" maxlength="45" placeholder="Nome do funcionário" value="<?php echo htmlspecialchars($funcionarioEditando['username'] ?? ($_POST['username'] ?? '')); ?>" required>

                        <label for="email">E-mail</label>
                        <input id="email" type="email" name="email" maxlength="100" placeholder="email@exemplo.com" value="<?php echo htmlspecialchars($funcionarioEditando['email'] ?? ($_POST['email'] ?? '')); ?>" required>

                        <label for="senha"><?php echo $funcionarioEditando ? 'Nova senha (deixe vazio para manter)' : 'Senha'; ?></label>
                        <input id="senha" type="password" name="senha" minlength="6" placeholder="<?php echo $funcionarioEditando ? '••••••' : 'Mínimo 6 caracteres'; ?>" <?php echo $funcionarioEditando ? '' : 'required'; ?>>

                        <label for="role">Perfil de acesso</label>
                        <select id="role" name="role" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($roles as $valor => $nome): ?>
                                <option value="<?php echo $valor; ?>" <?php echo (($funcionarioEditando['role'] ?? $_POST['role'] ?? '') == $valor) ? 'selected' : ''; ?>><?php echo $nome; ?></option>
                            <?php endforeach; ?>
                        </select>

                        <?php if ($funcionarioEditando): ?>
                            <label class="checkbox-label">
                                <input type="checkbox" name="is_active" value="1" <?php echo $funcionarioEditando['is_active'] ? 'checked' : ''; ?>>
                                Funcionário ativo
                            </label>
                        <?php endif; ?>

                        <button type="submit" name="<?php echo $funcionarioEditando ? 'atualizar_funcionario' : 'adicionar_funcionario'; ?>" class="botao-principal">
                            <?php echo $funcionarioEditando ? 'Salvar Alterações' : 'Cadastrar Funcionário'; ?>
                        </button>

                        <?php if ($funcionarioEditando): ?>
                            <a class="btn-cancelar" href="crud.php<?php echo $busca !== '' ? '?busca=' . urlencode($busca) : ''; ?>">Cancelar</a>
                        <?php endif; ?>
                    </form>
                </div>
            </aside>
        </section>
    </main>
</body>
</html>
