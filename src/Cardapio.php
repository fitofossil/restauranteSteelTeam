<?php
// =============================================================
// CARDÁPIO — Estrutura dos pratos e itens de cada pedido
// =============================================================

class Cardapio
{
    public static function garantirTabelas(PDO $conn): void
    {
        $conn->exec("CREATE TABLE IF NOT EXISTS cardapio_produtos (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            nome VARCHAR(100) NOT NULL,
            descricao VARCHAR(255) NOT NULL DEFAULT '',
            preco DECIMAL(10,2) NOT NULL,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->exec("CREATE TABLE IF NOT EXISTS pedido_itens (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            pedido_id INT UNSIGNED NOT NULL,
            produto_id INT UNSIGNED NOT NULL,
            produto_nome VARCHAR(100) NOT NULL,
            quantidade SMALLINT UNSIGNED NOT NULL,
            preco_unitario DECIMAL(10,2) NOT NULL,
            PRIMARY KEY (id),
            INDEX idx_pedido_itens_pedido (pedido_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}
