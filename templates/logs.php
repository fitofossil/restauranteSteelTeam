<?php
// =============================================================
// LOGS — Histórico das alterações feitas no banco de dados
// =============================================================
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Log.php';
require_once __DIR__ . '/../config/conexao.php';
// A consulta ao histórico é sensível e exclusiva de administradores.
Auth::requireAdmin();

$mensagem = '';
$tipoMensagem = '';

try {
    Log::garantirTabela($conn);

    // AÇÃO: Limpar o histórico completo de logs (exclusivo de administrador)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['limpar_logs'])) {
        $stmt = $conn->prepare('DELETE FROM logs');
        $stmt->execute();
        Log::registrar($conn, 'delete', 'logs', null, 'Histórico de logs limpo pelo administrador.');
        $mensagem = 'Histórico de logs limpo com sucesso.';
        $tipoMensagem = 'sucesso';
    }

    $logs = Log::listar($conn, 300);
} catch (PDOException $e) {
    $mensagem = 'Não foi possível carregar o histórico de alterações.';
    $tipoMensagem = 'erro';
    $logs = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs de Alterações - Dogão Lanches</title>
    <link rel="stylesheet" href="../public/css/style.css">
</head>
<body>
    <main class="painel">
        <header class="topo">
            <div>
                <p class="marca">🌭 DOGÃO LANCHES</p>
                <h1>Logs do banco de dados</h1>
                <p class="boas-vindas">Histórico das alterações feitas no sistema.</p>
            </div>
            <div class="topo-botoes">
                <a class="btn-topo" href="painel.php">← Voltar ao Painel</a>
                <a class="btn-topo" href="../logout.php">Sair</a>
            </div>
        </header>

        <?php if ($mensagem): ?>
            <div class="alerta <?php echo $tipoMensagem; ?>"><?php echo htmlspecialchars($mensagem); ?></div>
        <?php endif; ?>

        <section class="bloco lista">
            <div class="titulo-bloco">
                <div>
                    <p class="etiqueta">AUDITORIA</p>
                    <h2>Alterações registradas</h2>
                </div>
                <div class="titulo-acoes">
                    <span><?php echo count($logs); ?> registros</span>
                    <form method="POST" onsubmit="return confirm('Apagar TODO o histórico de logs? Esta ação não pode ser desfeita.');">
                        <button type="submit" name="limpar_logs" class="btn-excluir btn-zerar">Limpar logs</button>
                    </form>
                </div>
            </div>

            <?php if (!$logs): ?>
                <p class="vazio">Nenhuma alteração registrada ainda.</p>
            <?php else: ?>
                <div class="tabela-wrap">
                    <table class="tabela">
                        <thead>
                            <tr>
                                <th>Quando</th>
                                <th>Usuário</th>
                                <th>Ação</th>
                                <th>Tabela</th>
                                <th>Registro</th>
                                <th>Detalhe</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <?php
                                    $classeAcao = $log['acao'] === 'insert' ? 'log-insert'
                                        : ($log['acao'] === 'update' ? 'log-update' : 'log-delete');
                                    $rotuloAcao = $log['acao'] === 'insert' ? 'Inserção'
                                        : ($log['acao'] === 'update' ? 'Atualização' : 'Exclusão');
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(date('d/m/Y H:i:s', strtotime($log['criado_em']))); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($log['usuario'] ?: '—'); ?></strong>
                                        <?php if ($log['usuario_id']): ?><small>ID <?php echo (int) $log['usuario_id']; ?></small><?php endif; ?>
                                    </td>
                                    <td><span class="badge <?php echo $classeAcao; ?>"><?php echo $rotuloAcao; ?></span></td>
                                    <td><?php echo htmlspecialchars($log['tabela']); ?></td>
                                    <td><?php echo $log['registro_id'] ? (int) $log['registro_id'] : '—'; ?></td>
                                    <td><?php echo htmlspecialchars($log['detalhe'] ?: '—'); ?></td>
                                    <td><?php echo htmlspecialchars($log['ip'] ?: '—'); ?></td>
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
