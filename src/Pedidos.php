<?php
// =============================================================
// PEDIDOS — Estrutura e regras compartilhadas dos pedidos
// =============================================================

class Pedidos
{
    public const LIMITE_MESAS = 13;
    public const PENDENTE = 'pendente';
    public const PAGO = 'pago';
    public const AGUARDANDO_PREPARO = 'aguardando';
    public const PRONTO = 'pronto';
    public const TIPO_MESA = 'mesa';
    public const TIPO_VIAGEM = 'viagem';
    public const TIPO_ENTREGA = 'entrega';

    // Cria a tabela em uma instalação nova e adiciona as colunas em uma instalação antiga.
    public static function garantirTabela(PDO $conn): void
    {
        $conn->exec("CREATE TABLE IF NOT EXISTS pedidos (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            mesa_numero SMALLINT UNSIGNED NOT NULL,
            valor DECIMAL(10,2) NOT NULL,
            status_pagamento VARCHAR(10) NOT NULL DEFAULT 'pendente',
            forma_pagamento VARCHAR(20) NULL,
            status_preparo VARCHAR(10) NOT NULL DEFAULT 'aguardando',
            tipo_entrega VARCHAR(20) NOT NULL DEFAULT 'mesa',
            observacao TEXT NULL,
            cliente_nome VARCHAR(100) NULL,
            cep_entrega VARCHAR(9) NULL,
            endereco_entrega VARCHAR(255) NULL,
            numero_endereco VARCHAR(20) NULL,
            complemento_endereco VARCHAR(100) NULL,
            bairro_endereco VARCHAR(100) NULL,
            cidade_endereco VARCHAR(100) NULL,
            uf_endereco CHAR(2) NULL,
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        if (!self::colunaExiste($conn, 'mesa_numero')) {
            // Pedidos antigos ficam temporariamente com mesa 0 até serem corrigidos pela recepção.
            $conn->exec('ALTER TABLE pedidos ADD COLUMN mesa_numero SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER id');
        }

        if (!self::colunaExiste($conn, 'valor')) {
            $conn->exec('ALTER TABLE pedidos ADD COLUMN valor DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER mesa_numero');
        }

        if (!self::colunaExiste($conn, 'status_pagamento')) {
            $conn->exec("ALTER TABLE pedidos ADD COLUMN status_pagamento VARCHAR(10) NOT NULL DEFAULT 'pendente' AFTER valor");
        }

        if (!self::colunaExiste($conn, 'forma_pagamento')) {
            $conn->exec("ALTER TABLE pedidos ADD COLUMN forma_pagamento VARCHAR(20) NULL AFTER status_pagamento");
        }

        if (!self::colunaExiste($conn, 'status_preparo')) {
            $conn->exec("ALTER TABLE pedidos ADD COLUMN status_preparo VARCHAR(10) NOT NULL DEFAULT 'aguardando' AFTER status_pagamento");
        }

        if (!self::colunaExiste($conn, 'tipo_entrega')) {
            $conn->exec("ALTER TABLE pedidos ADD COLUMN tipo_entrega VARCHAR(20) NOT NULL DEFAULT 'mesa' AFTER status_preparo");
        }

        if (!self::colunaExiste($conn, 'observacao')) {
            $conn->exec('ALTER TABLE pedidos ADD COLUMN observacao TEXT NULL AFTER tipo_entrega');
        }

        foreach (['cliente_nome', 'cep_entrega', 'endereco_entrega', 'numero_endereco', 'complemento_endereco', 'bairro_endereco', 'cidade_endereco', 'uf_endereco'] as $campo) {
            if (!self::colunaExiste($conn, $campo)) {
                $tipo = in_array($campo, ['cliente_nome', 'endereco_entrega', 'complemento_endereco', 'bairro_endereco', 'cidade_endereco'], true)
                    ? 'VARCHAR(255) NULL'
                    : (in_array($campo, ['cep_entrega'], true) ? 'VARCHAR(9) NULL' : (in_array($campo, ['numero_endereco'], true) ? 'VARCHAR(20) NULL' : 'CHAR(2) NULL'));
                $conn->exec("ALTER TABLE pedidos ADD COLUMN $campo $tipo AFTER observacao");
            }
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

    public static function formasPagamentoValidas(): array
    {
        return ['dinheiro', 'cartao', 'pix'];
    }

    public static function textoFormaPagamento(?string $forma): string
    {
        return match ($forma) {
            'dinheiro' => 'Dinheiro',
            'cartao' => 'Cartão',
            'pix' => 'PIX',
            default => 'Não informado',
        };
    }

    public static function tiposEntregaValidos(): array
    {
        return [self::TIPO_MESA, self::TIPO_VIAGEM, self::TIPO_ENTREGA];
    }

    public static function textoTipoEntrega(string $tipo): string
    {
        return match ($tipo) {
            self::TIPO_VIAGEM => 'Para viagem',
            self::TIPO_ENTREGA => 'Entrega',
            default => 'Mesa',
        };
    }

    private static function colunaExiste(PDO $conn, string $coluna): bool
    {
        // O nome vem apenas das chamadas internas desta classe.
        $colunas = $conn->query('SHOW COLUMNS FROM pedidos')->fetchAll(PDO::FETCH_COLUMN);
        return in_array($coluna, $colunas, true);
    }
}
