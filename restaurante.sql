SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

CREATE DATABASE IF NOT EXISTS `restaurante` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `restaurante`;

-- Remoção na ordem inversa das chaves estrangeiras
DROP TABLE IF EXISTS `login_audit`;
DROP TABLE IF EXISTS `pedido_itens`;
DROP TABLE IF EXISTS `pedidos`;
DROP TABLE IF EXISTS `mesas`;
DROP TABLE IF EXISTS `produtos`;
DROP TABLE IF EXISTS `tipos_produto`;
DROP TABLE IF EXISTS `categorias_produto`;
DROP TABLE IF EXISTS `users_login`;

-- --------------------------------------------------------
-- TABELA: categorias_produto
-- --------------------------------------------------------
CREATE TABLE `categorias_produto` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- TABELA: tipos_produto
-- --------------------------------------------------------
CREATE TABLE `tipos_produto` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `tipos_produto` (`id`, `nome`) VALUES
(1, 'Entradas e Petiscos'),
(2, 'Pratos Principais'),
(3, 'Sobremesas'),
(4, 'Bebidas'),
(5, 'Menu Infantil');

-- --------------------------------------------------------
-- TABELA: produtos
-- --------------------------------------------------------
CREATE TABLE `produtos` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `tipo_id` int(11) NOT NULL,
  `categoria_id` int(11) NOT NULL,
  `preco` decimal(10,2) NOT NULL,
  `descricao` varchar(255) NOT NULL DEFAULT '',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_produtos_tipo` FOREIGN KEY (`tipo_id`) REFERENCES `tipos_produto` (`id`),
  CONSTRAINT `fk_produtos_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias_produto` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- TABELA: mesas
-- --------------------------------------------------------
CREATE TABLE `mesas` (
  `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `numero` tinyint(3) UNSIGNED NOT NULL,
  `capacidade` tinyint(3) UNSIGNED NOT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `hora_reserva` datetime DEFAULT NULL,
  `reservado_por` varchar(45) DEFAULT NULL,
  `tel_reserva` varchar(15) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mesa_numero` (`numero`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- TABELA: pedidos
-- --------------------------------------------------------
CREATE TABLE `pedidos` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `mesa_id` smallint(5) UNSIGNED NOT NULL,
  `status_pagamento` varchar(10) NOT NULL DEFAULT 'pendente',
  `status_preparo` varchar(10) NOT NULL DEFAULT 'aguardando',
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_pedidos_mesa` FOREIGN KEY (`mesa_id`) REFERENCES `mesas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- TABELA: pedido_itens
-- --------------------------------------------------------
CREATE TABLE `pedido_itens` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `pedido_id` int(10) UNSIGNED NOT NULL,
  `produto_id` int(10) UNSIGNED NOT NULL,
  `quantidade` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `preco_unitario` decimal(10,2) NOT NULL, -- Mantido para congelar o valor histórico da venda
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_pedido_itens_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pedido_itens_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- TABELA: users_login
-- --------------------------------------------------------
CREATE TABLE `users_login` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` varchar(45) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` tinyint(3) UNSIGNED NOT NULL DEFAULT 3,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `failed_attempts` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_login_username` (`username`),
  UNIQUE KEY `uq_users_login_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users_login` (`id`, `username`, `email`, `password_hash`, `role`, `is_active`, `failed_attempts`, `locked_until`, `created_at`, `updated_at`) VALUES
(1, 'administrador', 'admin@email.com', '$2y$10$5zTHbdjw0Gb7COv2VZW9Vei8gfhKn47GYOUo5wJUe.Tdoavln2Yvi', 1, 1, 0, NULL, '2026-07-22 16:38:30', '2026-08-03 19:06:40'),
(6, 'recepcao', 'recepcao@email.comm', '$2y$10$fRKSslGIDpFYDUVBjSnT.eYS/s93psTZ7nyWCwGAaH8A3vd2phrAS', 3, 1, 0, NULL, '2026-08-03 16:53:22', '2026-08-03 19:05:52'),
(7, 'gerente', 'gerente@email.com', '$2y$10$3CgZu1oqsdzUaFmQpSyKW.s/Ij91IBjz23tDkH25jrr44YHPdGSXy', 2, 1, 0, NULL, '2026-08-03 17:13:42', '2026-08-03 19:06:11'),
(8, 'cozinheiro', 'cozinheiro@email.com', '$2y$10$Z9s8xVgzvT.BW9lFmWY2q.qcBs9GamUtL6THkdQTxBffpA3qnF..W', 4, 1, 0, NULL, '2026-08-03 17:22:47', '2026-08-03 19:06:03'),
(9, 'garcom', 'garcom@email.com', '$2y$10$IIcMTnVnNQlzW08pDcYKt.NVMVnEIyh.D1paaHTv8Y9qemKtjkiCi', 5, 1, 0, NULL, '2026-08-03 17:23:08', '2026-08-03 17:23:08');

-- --------------------------------------------------------
-- TABELA: login_audit
-- --------------------------------------------------------
CREATE TABLE `login_audit` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `login_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `success` tinyint(1) NOT NULL,
  `reason` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_login_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users_login` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;