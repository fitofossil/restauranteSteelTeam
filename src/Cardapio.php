<?php
// =============================================================
// CARDÁPIO — Estrutura dos pratos e itens de cada pedido
// =============================================================

class Cardapio
{
    public static function garantirTabelas(PDO $conn): void
    {
        $conn->exec("CREATE TABLE IF NOT EXISTS categorias_produto (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            nome VARCHAR(50) NOT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->exec("CREATE TABLE IF NOT EXISTS tipos_produto (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            nome VARCHAR(50) NOT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->exec("CREATE TABLE IF NOT EXISTS produtos (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            nome VARCHAR(100) NOT NULL,
            tipo_id INT UNSIGNED NOT NULL,
            categoria_id INT UNSIGNED NOT NULL,
            descricao VARCHAR(255) NOT NULL DEFAULT '',
            preco DECIMAL(10,2) NOT NULL,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_produtos_tipo (tipo_id),
            INDEX idx_produtos_categoria (categoria_id)
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

        self::garantirAutoIncrement($conn, 'categorias_produto');
        self::garantirAutoIncrement($conn, 'tipos_produto');
        self::garantirAutoIncrement($conn, 'produtos');
        self::garantirAutoIncrement($conn, 'pedido_itens');
    }

    private static function garantirAutoIncrement(PDO $conn, string $tabela): void
    {
        $coluna = $conn->query("SHOW COLUMNS FROM `$tabela` LIKE 'id'")->fetch(PDO::FETCH_ASSOC);
        if (!$coluna) {
            return;
        }

        $indicesPrimarios = $conn->query("SHOW KEYS FROM `$tabela` WHERE Key_name = 'PRIMARY'")->fetchAll(PDO::FETCH_ASSOC);
        if (empty($indicesPrimarios)) {
            $conn->exec("ALTER TABLE `$tabela` ADD PRIMARY KEY (`id`)");
        }

        if (stripos($coluna['Extra'] ?? '', 'auto_increment') !== false) {
            return;
        }

        $conn->exec("ALTER TABLE `$tabela` MODIFY COLUMN `id` INT UNSIGNED NOT NULL AUTO_INCREMENT");
    }
}
