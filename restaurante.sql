-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Tempo de geração: 03/08/2026 às 21:07
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "-03:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `restaurante` 
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `categorias_produto`
--

CREATE TABLE `categorias_produto` (
  `id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `categorias_produto`
--

INSERT INTO `categorias_produto` (`id`, `nome`) VALUES
(1, 'Carnes e Grelhados'),
(2, 'Aves'),
(3, 'Peixes e Frutos do Mar'),
(4, 'Massas e Risotos'),
(5, 'Saladas e Saudáveis'),
(6, 'Pizzas e Hambúrgueres'),
(7, 'Doces e Tortas'),
(8, 'Sorvetes'),
(9, 'Sucos Naturais e Refrigerantes'),
(10, 'Cervejas e Chopes'),
(11, 'Vinhos e Espumantes'),
(12, 'Drinks e Coquetéis'),
(13, 'Cafés e Digestivos');

-- --------------------------------------------------------

--
-- Estrutura para tabela `login_audit`
--

CREATE TABLE `login_audit` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `login_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `success` tinyint(1) NOT NULL,
  `reason` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `mesas`
--

CREATE TABLE `mesas` (
  `id` smallint(5) UNSIGNED NOT NULL,
  `numero` tinyint(3) UNSIGNED NOT NULL,
  `capacidade` tinyint(3) UNSIGNED NOT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `hora_reserva` datetime DEFAULT NULL,
  `reservado_por` varchar(45) DEFAULT NULL,
  `tel_reserva` varchar(15) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `mesas`
--

INSERT INTO `mesas` (`id`, `numero`, `capacidade`, `status`, `hora_reserva`, `reservado_por`, `tel_reserva`) VALUES
(1, 1, 4, 0, NULL, NULL, NULL),
(2, 2, 4, 0, NULL, NULL, NULL),
(3, 3, 4, 0, NULL, NULL, NULL),
(4, 4, 4, 0, NULL, NULL, NULL),
(5, 5, 4, 0, NULL, NULL, NULL),
(6, 6, 6, 0, NULL, NULL, NULL),
(7, 7, 6, 0, NULL, NULL, NULL),
(8, 8, 6, 0, NULL, NULL, NULL),
(9, 9, 8, 0, NULL, NULL, NULL),
(10, 10, 8, 0, NULL, NULL, NULL),
(11, 11, 2, 0, NULL, NULL, NULL),
(12, 12, 2, 0, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `pedidos`
--

CREATE TABLE `pedidos` (
  `id` int(10) UNSIGNED NOT NULL,
  `mesa_numero` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `valor` decimal(10,2) NOT NULL,
  `status_pagamento` varchar(10) NOT NULL DEFAULT 'pendente',
  `status_preparo` varchar(10) NOT NULL DEFAULT 'aguardando',
  `tipo_entrega` varchar(20) NOT NULL DEFAULT 'mesa',
  `observacao` text DEFAULT NULL,
  `cliente_nome` varchar(100) DEFAULT NULL,
  `cep_entrega` varchar(9) DEFAULT NULL,
  `endereco_entrega` varchar(255) DEFAULT NULL,
  `numero_endereco` varchar(20) DEFAULT NULL,
  `complemento_endereco` varchar(100) DEFAULT NULL,
  `bairro_endereco` varchar(100) DEFAULT NULL,
  `cidade_endereco` varchar(100) DEFAULT NULL,
  `uf_endereco` char(2) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `pedido_itens`
--

CREATE TABLE `pedido_itens` (
  `id` int(10) UNSIGNED NOT NULL,
  `pedido_id` int(10) UNSIGNED NOT NULL,
  `produto_id` int(10) UNSIGNED NOT NULL,
  `produto_nome` varchar(100) NOT NULL,
  `quantidade` smallint(5) UNSIGNED NOT NULL,
  `preco_unitario` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `produtos`
--

CREATE TABLE `produtos` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(100) NOT NULL,
  `tipo_id` int(11) NOT NULL,
  `categoria_id` int(11) NOT NULL,
  `preco` decimal(10,2) NOT NULL,
  `descricao` varchar(255) NOT NULL DEFAULT '',
  `ativo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `tables`
--

CREATE TABLE `tables` (
  `id` smallint(5) UNSIGNED NOT NULL,
  `numero` tinyint(3) UNSIGNED NOT NULL,
  `capacidade` tinyint(3) UNSIGNED NOT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL,
  `hora_reserva` datetime NOT NULL,
  `reservado_por` varchar(45) NOT NULL,
  `tel_reseva` varchar(15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `tipos_produto`
--

CREATE TABLE `tipos_produto` (
  `id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `tipos_produto`
--

INSERT INTO `tipos_produto` (`id`, `nome`) VALUES
(1, 'Entradas e Petiscos'),
(2, 'Pratos Principais'),
(3, 'Sobremesas'),
(4, 'Bebidas'),
(5, 'Menu Infantil');

-- --------------------------------------------------------

--
-- Estrutura para tabela `users_login`
--

CREATE TABLE `users_login` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(45) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` tinyint(3) UNSIGNED NOT NULL DEFAULT 3,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `failed_attempts` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `users_login`
--

INSERT INTO `users_login` (`id`, `username`, `email`, `password_hash`, `role`, `is_active`, `failed_attempts`, `locked_until`, `created_at`, `updated_at`) VALUES
(1, 'administrador', 'admin@email.com', '$2y$10$5zTHbdjw0Gb7COv2VZW9Vei8gfhKn47GYOUo5wJUe.Tdoavln2Yvi', 1, 1, 0, NULL, '2026-07-22 16:38:30', '2026-08-03 19:06:40'),
(6, 'recepcao', 'recepcao@email.comm', '$2y$10$fRKSslGIDpFYDUVBjSnT.eYS/s93psTZ7nyWCwGAaH8A3vd2phrAS', 3, 1, 0, NULL, '2026-08-03 16:53:22', '2026-08-03 19:05:52'),
(7, 'gerente', 'gerente@email.com', '$2y$10$3CgZu1oqsdzUaFmQpSyKW.s/Ij91IBjz23tDkH25jrr44YHPdGSXy', 2, 1, 0, NULL, '2026-08-03 17:13:42', '2026-08-03 19:06:11'),
(8, 'cozinheiro', 'cozinheiro@email.com', '$2y$10$Z9s8xVgzvT.BW9lFmWY2q.qcBs9GamUtL6THkdQTxBffpA3qnF..W', 4, 1, 0, NULL, '2026-08-03 17:22:47', '2026-08-03 19:06:03'),
(9, 'garcom', 'garcom@email.com', '$2y$10$IIcMTnVnNQlzW08pDcYKt.NVMVnEIyh.D1paaHTv8Y9qemKtjkiCi', 5, 1, 0, NULL, '2026-08-03 17:23:08', '2026-08-03 17:23:08');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `categorias_produto`
--
ALTER TABLE `categorias_produto`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `login_audit`
--
ALTER TABLE `login_audit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_login_audit_user_id` (`user_id`);

--
-- Índices de tabela `mesas`
--
ALTER TABLE `mesas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_mesa_numero` (`numero`);

--
-- Índices de tabela `pedidos`
--
ALTER TABLE `pedidos`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `pedido_itens`
--
ALTER TABLE `pedido_itens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pedido_itens_pedido` (`pedido_id`);

--
-- Índices de tabela `produtos`
--
ALTER TABLE `produtos`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `tipos_produto`
--
ALTER TABLE `tipos_produto`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `users_login`
--
ALTER TABLE `users_login`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_login_username` (`username`),
  ADD UNIQUE KEY `uq_users_login_email` (`email`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `categorias_produto`
--
ALTER TABLE `categorias_produto`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de tabela `login_audit`
--
ALTER TABLE `login_audit`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `mesas`
--
ALTER TABLE `mesas`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de tabela `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de tabela `pedido_itens`
--
ALTER TABLE `pedido_itens`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT de tabela `produtos`
--
ALTER TABLE `produtos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `tipos_produto`
--
ALTER TABLE `tipos_produto`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `users_login`
--
ALTER TABLE `users_login`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `login_audit`
--
ALTER TABLE `login_audit`
  ADD CONSTRAINT `fk_login_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users_login` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
