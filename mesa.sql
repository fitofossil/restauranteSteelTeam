-- =========================================================
-- BANCO DE DADOS: restaurante
-- =========================================================
-- Simula o funcionamento de um restaurante:
-- - Cardápio (categorias, tipos e produtos)
-- - Mesas e pedidos
-- - Usuários e auditoria de login
-- =========================================================

-- =========================================================
-- TABELA: categorias_produto
-- =========================================================
-- Armazena categorias específicas de produtos (ex.: Carnes, Massas, Sucos).
CREATE TABLE `categorias_produto` (
  `id` int(11) NOT NULL,          -- Identificador único da categoria
  `nome` varchar(50) NOT NULL     -- Nome da categoria (ex.: "Carnes")
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- TABELA: tipos_produto
-- =========================================================
-- Define tipos gerais de produtos (ex.: Menu principal, Bebidas).
CREATE TABLE `tipos_produto` (
  `id` int(11) NOT NULL,          -- Identificador único do tipo
  `nome` varchar(50) NOT NULL     -- Nome do tipo (ex.: "Bebidas")
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- TABELA: produtos
-- =========================================================
-- Lista os itens do cardápio, vinculando tipo e categoria.
CREATE TABLE `produtos` (
  `id` int(11) NOT NULL,          -- Identificador único do produto
  `nome` varchar(100) NOT NULL,   -- Nome do produto (ex.: "Pizza Calabresa")
  `tipo_id` int(11) NOT NULL,     -- FK para tipos_produto
  `categoria_id` int(11) NOT NULL,-- FK para categorias_produto
  `preco` decimal(10,2) NOT NULL  -- Preço do produto
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- TABELA: mesas
-- =========================================================
-- Representa mesas físicas do restaurante, com capacidade e reservas.
CREATE TABLE `tables` (
  `id` smallint(5) UNSIGNED NOT NULL, -- Identificador único da mesa
  `numero` tinyint(3) UNSIGNED NOT NULL, -- Número da mesa (ex.: 1, 2, 3)
  `capacidade` tinyint(3) UNSIGNED NOT NULL, -- Quantidade de lugares
  `status` tinyint(3) UNSIGNED NOT NULL,     -- Status da mesa (ex.: livre, ocupada)
  `hora_reserva` datetime NOT NULL,          -- Data/hora da reserva
  `reservado_por` varchar(45) NOT NULL,      -- Nome da pessoa que reservou
  `tel_reseva` varchar(15) NOT NULL          -- Telefone de contato da reserva
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- TABELA: comandapedidos
-- =========================================================
-- Registra pedidos feitos nas mesas, vinculando ao produto.
CREATE TABLE `comandapedidos` (
  `id` int(11) NOT NULL,              -- Identificador único do pedido
  `mesa_id` smallint(5) UNSIGNED NOT NULL, -- FK para tables
  `produto_id` int(11) NOT NULL,      -- FK para produtos
  `preco` decimal(10,2) NOT NULL,     -- Preço unitário do produto
  `quantidade` int(10) NOT NULL,      -- Quantidade pedida
  `info` varchar(100) NOT NULL,       -- Observações adicionais (ex.: sem cebola)
  `status_pedido` varchar(20) NOT NULL DEFAULT 'recebido', -- Status (recebido, em preparo, entregue)
  `aberto` tinyint(1) NOT NULL DEFAULT 1, -- Indica se o pedido ainda está aberto
  `forma_pagamento` varchar(30) DEFAULT NULL, -- Forma de pagamento (dinheiro, cartão)
  `finalizado_em` datetime DEFAULT NULL,     -- Data/hora de finalização
  `criado_em` datetime NOT NULL DEFAULT current_timestamp() -- Data/hora de criação
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- TABELA: users
-- =========================================================
-- Cadastro de usuários do sistema (login administrativo).
-- Inclui controle de tentativas de login e bloqueio.
CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,     -- Identificador único do usuário
  `username` varchar(45) NOT NULL,    -- Nome de login
  `email` varchar(100) NOT NULL,      -- Email do usuário
  `password_hash` varchar(255) NOT NULL, -- Senha criptografada (bcrypt)
  `role` tinyint(3) UNSIGNED NOT NULL DEFAULT 3, -- Papel (1=admin, 2=gerente, 3=usuário comum)
  `is_active` tinyint(1) NOT NULL DEFAULT 1,     -- Indica se a conta está ativa
  `failed_attempts` smallint(5) UNSIGNED NOT NULL DEFAULT 0, -- Tentativas de login falhas
  `locked_until` datetime DEFAULT NULL, -- Data/hora até quando a conta está bloqueada
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(), -- Data de criação
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() -- Última atualização
);

-- =========================================================
-- TABELA: login_audit
-- =========================================================
-- Registra tentativas de login (sucesso ou falha),
-- incluindo IP e motivo em caso de erro.
CREATE TABLE `login_audit` (
  `id` bigint(20) UNSIGNED NOT NULL,  -- Identificador único do log
  `user_id` int(10) UNSIGNED DEFAULT NULL, -- FK para users
  `login_at` timestamp NOT NULL DEFAULT current_timestamp(), -- Data/hora da tentativa
  `ip_address` varchar(45) DEFAULT NULL, -- IP de origem
  `success` tinyint(1) NOT NULL,         -- 1=sucesso, 0=falha
  `reason` varchar(100) DEFAULT NULL     -- Motivo da falha (ex.: senha incorreta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


