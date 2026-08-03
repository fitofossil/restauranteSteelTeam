-- =========================================================
-- BANCO DE DADOS: restaurante (Versão Corrigida e Otimizada)
-- =========================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Criando banco de dados
CREATE DATABASE IF NOT EXISTS `restaurante` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `restaurante`;

-- Remoção das tabelas na ordem correta das chaves estrangeiras
DROP TABLE IF EXISTS `login_audit`;
DROP TABLE IF EXISTS `users_login`;
DROP TABLE IF EXISTS `pedido_itens`;
DROP TABLE IF EXISTS `pedidos`;
DROP TABLE IF EXISTS `mesas`;
DROP TABLE IF EXISTS `produtos`;
DROP TABLE IF EXISTS `tipos_produto`;
DROP TABLE IF EXISTS `categorias_produto`;

-- =========================================================
-- TABELA: categorias_produto
-- =========================================================
CREATE TABLE `categorias_produto` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- TABELA: tipos_produto
-- =========================================================
CREATE TABLE `tipos_produto` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- TABELA: produtos
-- =========================================================
CREATE TABLE `produtos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `tipo_id` int(11) NOT NULL,
  `categoria_id` int(11) NOT NULL,
  `preco` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`tipo_id`) REFERENCES `tipos_produto`(`id`),
  FOREIGN KEY (`categoria_id`) REFERENCES `categorias_produto`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- TABELA: mesas
-- =========================================================
CREATE TABLE `mesas` (
  `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `numero` tinyint(3) UNSIGNED NOT NULL,
  `capacidade` tinyint(3) UNSIGNED NOT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 0, -- 0=Livre, 1=Ocupada
  `hora_reserva` datetime DEFAULT NULL,
  `reservado_por` varchar(45) DEFAULT NULL,
  `tel_reserva` varchar(15) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mesa_numero` (`numero`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- TABELA: pedidos (Cabeçalho da Comanda)
-- =========================================================
CREATE TABLE `pedidos` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `mesa_id` smallint(5) UNSIGNED NOT NULL,
  `status_pagamento` varchar(10) NOT NULL DEFAULT 'pendente', -- pendente, pago
  `forma_pagamento` varchar(30) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `finalizado_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`mesa_id`) REFERENCES `mesas`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- TABELA: pedido_itens (Produtos lançados na comanda)
-- =========================================================
CREATE TABLE `pedido_itens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pedido_id` int(10) UNSIGNED NOT NULL,
  `produto_id` int(11) NOT NULL,
  `preco_historico` decimal(10,2) NOT NULL,
  `quantidade` int(10) NOT NULL DEFAULT 1,
  `info` varchar(100) DEFAULT NULL,
  `status_preparo` varchar(20) NOT NULL DEFAULT 'recebido', -- recebido, em preparo, entregue
  PRIMARY KEY (`id`),
  FOREIGN KEY (`pedido_id`) REFERENCES `pedidos`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`produto_id`) REFERENCES `produtos`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- TABELA: users_login
-- =========================================================
CREATE TABLE `users_login` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(45) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` tinyint(3) UNSIGNED NOT NULL, -- 1=admin, 2=gerente, 3=recepção
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `failed_attempts` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_login_username` (`username`),
  UNIQUE KEY `uq_users_login_email` (`email`),
  CONSTRAINT `chk_users_login_role` CHECK (`role` BETWEEN 1 AND 3),
  CONSTRAINT `chk_users_login_failed_attempts` CHECK (`failed_attempts` <= 10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- TABELA: login_audit
-- =========================================================
CREATE TABLE `login_audit` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `login_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `success` tinyint(1) NOT NULL,
  `reason` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users_login`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =========================================================
-- CARGA DE DADOS INICIAIS (INSERTS)
-- =========================================================

-- 1. Tipos de Produto
INSERT INTO `tipos_produto` (`nome`) VALUES 
('Entradas e Petiscos'),
('Pratos Principais'),
('Sobremesas'),
('Bebidas'),
('Menu Infantil');

-- 2. Categorias de Produto
INSERT INTO `categorias_produto` (`nome`) VALUES 
('Carnes e Grelhados'),
('Aves'),
('Peixes e Frutos do Mar'),
('Massas e Risotos'),
('Saladas e Saudáveis'),
('Pizzas e Hambúrgueres'),
('Doces e Tortas'),
('Sorvetes'),
('Sucos Naturais e Refrigerantes'),
('Cervejas e Chopes'),
('Vinhos e Espumantes'),
('Drinks e Coquetéis'),
('Cafés e Digestivos');

-- 3. Usuário Administrador Padrão
INSERT INTO `users_login` (`username`, `email`, `password_hash`, `role`) 
VALUES ('admin', 'admin@restaurante.local', '$2y$10$C.p3cBfBgZ1No8unosWRvuDZ.xTeNLgoJxKZEt4fLljGBaXdSNzUy', 1);

COMMIT;