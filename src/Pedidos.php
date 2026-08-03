<?php
// =============================================================
// PEDIDOS — Estrutura e regras compartilhadas dos pedidos
// =============================================================

class Pedidos
{
    public const PENDENTE = 'pendente';
    public const PAGO = 'pago';
    public const AGUARDANDO_PREPARO = 'aguardando';
    public const PRONTO = 'pronto';

    // Cria a tabela em uma instalação nova e adiciona as colunas em uma instalação antiga.
    public static function garantirTabela(PDO $conn): void
    {
        $conn->exec("CREATE TABLE IF NOT EXISTS pedidos (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            mesa_numero SMALLINT UNSIGNED NOT NULL,
            valor DECIMAL(10,2) NOT NULL,
            status_pagamento VARCHAR(10) NOT NULL DEFAULT 'pendente',
            status_preparo VARCHAR(10) NOT NULL DEFAULT 'aguardando',
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        if (!self::colunaExiste($conn, 'mesa_numero')) {
            // Pedidos antigos ficam temporariamente com mesa 0 até serem corrigidos pela recepção.
            $conn->exec('ALTER TABLE pedidos ADD COLUMN mesa_numero SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER id');
        }

        if (!self::colunaExiste($conn, 'status_pagamento')) {
            $conn->exec("ALTER TABLE pedidos ADD COLUMN status_pagamento VARCHAR(10) NOT NULL DEFAULT 'pendente' AFTER valor");
        }

        if (!self::colunaExiste($conn, 'status_preparo')) {
            $conn->exec("ALTER TABLE pedidos ADD COLUMN status_preparo VARCHAR(10) NOT NULL DEFAULT 'aguardando' AFTER status_pagamento");
        }
    }

    public static function statusValidos(): array
    {
        // Lista única usada pela validação do formulário de pedidos.
        return [self::PENDENTE, self::PAGO];
    }

    public static function statusPreparoValidos(): array
    {
        return [self::AGUARDANDO_PREPARO, self::PRONTO];
    }

    private static function colunaExiste(PDO $conn, string $coluna): bool
    {
        // O nome vem apenas das chamadas internas desta classe.
        $colunas = $conn->query('SHOW COLUMNS FROM pedidos')->fetchAll(PDO::FETCH_COLUMN);
        return in_array($coluna, $colunas, true);
    }
}
