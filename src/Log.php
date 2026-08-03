<?php
// =============================================================
// LOG — Registro das alterações feitas no banco de dados
// =============================================================

class Log
{
    // Cria a tabela de logs em instalações novas.
    public static function garantirTabela(PDO $conn): void
    {
        $conn->exec("CREATE TABLE IF NOT EXISTS logs (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            usuario_id INT UNSIGNED NULL,
            usuario VARCHAR(50) NOT NULL DEFAULT '',
            papel TINYINT UNSIGNED NULL,
            ip VARCHAR(45) NOT NULL DEFAULT '',
            acao VARCHAR(10) NOT NULL,
            tabela VARCHAR(50) NOT NULL DEFAULT '',
            registro_id INT UNSIGNED NULL,
            detalhe VARCHAR(500) NULL,
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_logs_criado_em (criado_em),
            INDEX idx_logs_tabela (tabela)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // Registra uma alteração (insert, update, delete) feita na aplicação.
    public static function registrar(PDO $conn, string $acao, string $tabela, ?int $registroId = null, ?string $detalhe = null): void
    {
        $stmt = $conn->prepare('INSERT INTO logs (usuario_id, usuario, papel, ip, acao, tabela, registro_id, detalhe) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            Auth::getId() ?: null,
            Auth::getNome(),
            Auth::getRole() ?: null,
            $_SERVER['REMOTE_ADDR'] ?? '',
            strtolower(substr($acao, 0, 10)),
            $tabela,
            $registroId,
            $detalhe !== null ? substr($detalhe, 0, 500) : null,
        ]);
    }

    // Lista os registros mais recentes para a tela de consulta.
    public static function listar(PDO $conn, int $limite = 200): array
    {
        $stmt = $conn->prepare('SELECT * FROM logs ORDER BY id DESC LIMIT ?');
        $stmt->bindValue(1, $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
