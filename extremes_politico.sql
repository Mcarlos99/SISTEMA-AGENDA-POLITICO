-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Tempo de geração: 17/02/2026 às 15:14
-- Versão do servidor: 10.6.24-MariaDB-cll-lve
-- Versão do PHP: 8.4.17

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `extremes_politico`
--
CREATE DATABASE IF NOT EXISTS `extremes_politico` DEFAULT CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci;
USE `extremes_politico`;

-- --------------------------------------------------------

--
-- Estrutura para tabela `administradores`
--

DROP TABLE IF EXISTS `administradores`;
CREATE TABLE `administradores` (
  `id` int(11) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `ultimo_acesso` timestamp NULL DEFAULT NULL,
  `status` enum('ativo','inativo') DEFAULT 'ativo',
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Despejando dados para a tabela `administradores`
--

INSERT INTO `administradores` (`id`, `usuario`, `senha`, `nome`, `email`, `ultimo_acesso`, `status`, `data_criacao`) VALUES
(1, 'DepChamon', '$2y$10$1tb7dUdviqLtR82K.6q2/OHMInVT9fXVj48Jiq3T8n0L0VA6pjNXW', 'Administrador', '', '2026-02-14 20:01:45', 'ativo', '2025-06-14 17:10:03');

-- --------------------------------------------------------

--
-- Estrutura para tabela `agendamentos`
--

DROP TABLE IF EXISTS `agendamentos`;
CREATE TABLE `agendamentos` (
  `id` int(11) NOT NULL,
  `cadastro_id` int(11) DEFAULT NULL,
  `titulo` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  `data_agendamento` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fim` time DEFAULT NULL,
  `tipo` enum('retorno','reuniao','visita','evento','outro') DEFAULT 'retorno',
  `status` enum('agendado','confirmado','realizado','cancelado') DEFAULT 'agendado',
  `prioridade` enum('baixa','media','alta','urgente') DEFAULT 'media',
  `local` varchar(255) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `lembrete_antecedencia` int(11) DEFAULT 60,
  `criado_por` int(11) NOT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_atualizacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `cadastros`
--

DROP TABLE IF EXISTS `cadastros`;
CREATE TABLE `cadastros` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `cidade` varchar(100) NOT NULL,
  `cargo` varchar(100) NOT NULL,
  `telefone` varchar(20) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `data_nascimento` date NOT NULL,
  `observacoes` text DEFAULT NULL,
  `observacoes_admin` text DEFAULT NULL,
  `data_cadastro` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_ultima_alteracao` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `status` enum('ativo','inativo') DEFAULT 'ativo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Despejando dados para a tabela `cadastros`
--

INSERT INTO `cadastros` (`id`, `nome`, `cidade`, `cargo`, `telefone`, `email`, `data_nascimento`, `observacoes`, `observacoes_admin`, `data_cadastro`, `data_ultima_alteracao`, `ip_address`, `status`) VALUES
(1, 'MAURO CARLOS MARTINS DE SA', 'Tucuruí', 'ANALISTA DE T.I', '(94) 98170-9809', 'maurocarlos.ti@gmail.com', '1989-08-05', 'TESTE DE OBS', '15/06/2025 - Visitou gabinete solicitando apoio para creche.\r\nAgendar reunião com Secretária de Educação.\r\n\r\n20/06/2025 - Ligou pedindo informações sobre programa habitacional.\r\nEnviado contato da COHAB por WhatsApp.\r\n\r\n25/06/2025 - Participou de audiência pública sobre saneamento.\r\nMuito engajado, convidar para próximas reuniões.', '2025-06-14 17:12:53', '2025-06-22 11:04:53', '138.122.35.253', 'ativo'),
(4, 'Carolina Silva', 'Belém', 'Chefe de gabinete', '(94) 99256-1087', NULL, '1986-07-22', '….', 'Veio ao gabinete no dia 17/06', '2025-06-17 13:49:32', NULL, '138.84.42.90', 'ativo'),
(5, 'Euripedes Reis', 'Belém', 'Assessor', '(94) 99248-6398', NULL, '1979-01-06', 'Reunião', NULL, '2025-06-17 13:56:29', NULL, '138.84.42.90', 'ativo'),
(6, 'Luciano', 'Marabá', 'Assessor', '(94) 99132-7291', NULL, '1974-06-23', '', NULL, '2025-06-17 14:01:26', NULL, '138.84.42.90', 'ativo'),
(9, 'Eduardo de Oliveira Gomes', 'Belém', 'Superintendente Banpará', '(91) 98864-6631', NULL, '1986-04-25', 'Visita alinhamento', NULL, '2025-06-22 03:21:36', NULL, '191.246.233.91', 'ativo'),
(10, 'Juliane Corrêa', 'Belém', 'Servidora', '(91) 98738-2313', 'julianeferreira2505@gmail.com', '2000-05-25', '', NULL, '2025-06-24 18:10:48', NULL, '191.246.249.154', 'ativo'),
(12, 'Cristiany Borges', 'Belém', 'Severina', '(91) 98220-2760', 'cristianyb2@gmail.com', '1974-04-02', '', NULL, '2025-06-26 16:13:46', '2025-06-26 16:14:31', '191.246.242.244', 'inativo'),
(13, 'Wandressa Sodré', 'Belém', 'Assessora Parlamentar', '(91) 98953-0873', 'wandressasodre@gmail.com', '2001-05-30', '', NULL, '2025-06-26 16:22:46', NULL, '189.40.106.8', 'ativo'),
(14, 'Larissa negrão Fernandes', 'Belém', 'Acessor', '(91) 99206-3466', 'larissafernandes4640@gmail.com', '1999-07-20', '', NULL, '2025-06-26 16:23:22', NULL, '148.222.209.77', 'ativo'),
(15, 'Honorato Luis Lima Cosenza Nogueira', 'Belém', 'Xxx', '(91) 98187-1400', 'honoratocosenza@hotmail.com', '1964-02-19', 'Teste', NULL, '2025-06-26 16:24:25', NULL, '191.246.239.175', 'ativo'),
(16, 'Francileide Almeida da Silva', 'Belem', 'Assessora Parlamentar', '(91) 98514-3486', 'francya125@gmail.com', '1972-03-28', '', NULL, '2025-06-26 16:27:32', NULL, '191.246.246.79', 'ativo');

-- --------------------------------------------------------

--
-- Estrutura para tabela `cadastros_backup`
--

DROP TABLE IF EXISTS `cadastros_backup`;
CREATE TABLE `cadastros_backup` (
  `id` int(11) NOT NULL DEFAULT 0,
  `nome` varchar(255) NOT NULL,
  `cidade` varchar(100) NOT NULL,
  `cargo` varchar(100) NOT NULL,
  `telefone` varchar(20) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `data_nascimento` date NOT NULL,
  `observacoes` text DEFAULT NULL,
  `observacoes_admin` text DEFAULT NULL,
  `data_cadastro` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_ultima_alteracao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `status` enum('ativo','inativo') DEFAULT 'ativo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Despejando dados para a tabela `cadastros_backup`
--

INSERT INTO `cadastros_backup` (`id`, `nome`, `cidade`, `cargo`, `telefone`, `email`, `data_nascimento`, `observacoes`, `observacoes_admin`, `data_cadastro`, `data_ultima_alteracao`, `ip_address`, `status`) VALUES
(1, 'MAURO C M SA', 'Tucuruí', 'ANALISTA DE T.I', '(94) 98170-9809', NULL, '1989-08-05', 'TESTE DE OBS', '15/06/2025 - Visitou gabinete solicitando apoio para creche.\r\nAgendar reunião com Secretária de Educação.\r\n\r\n20/06/2025 - Ligou pedindo informações sobre programa habitacional.\r\nEnviado contato da COHAB por WhatsApp.\r\n\r\n25/06/2025 - Participou de audiência pública sobre saneamento.\r\nMuito engajado, convidar para próximas reuniões.', '2025-06-14 17:12:53', '2025-06-16 18:28:22', '138.122.35.253', 'ativo'),
(3, 'Carolina', 'Belém', 'Chefe de gabinete', '(94) 99256-1087', NULL, '2025-06-13', '', '', '2025-06-16 16:54:45', '2025-06-16 18:38:21', '179.176.238.131', 'ativo'),
(4, 'Carolina Silva', 'Belém', 'Chefe de gabinete', '(94) 99256-1087', NULL, '1986-07-22', '….', 'Veio ao gabinete no dia 17/06', '2025-06-17 13:49:32', '2025-06-17 13:52:34', '138.84.42.90', 'ativo'),
(5, 'Euripedes Reis', 'Belém', 'Assessor', '(94) 99248-6398', NULL, '1979-01-06', 'Reunião', NULL, '2025-06-17 13:56:29', '2025-06-17 13:56:29', '138.84.42.90', 'ativo'),
(6, 'Luciano', 'Marabá', 'Assessor', '(94) 99132-7291', NULL, '1974-06-23', '', NULL, '2025-06-17 14:01:26', '2025-06-17 14:01:26', '138.84.42.90', 'ativo'),
(7, 'Robson Fernando Coelho Luciano', 'Tucuruí', 'Acessor', '(94) 98126-2144', NULL, '1979-10-05', 'Teste de formulário', NULL, '2025-06-22 01:14:27', '2025-06-22 01:14:27', '177.104.242.111', 'ativo'),
(8, 'Eduardo de Oliveira Gomes', 'Belém', 'Superintendente Banpará', '(91) 98864-6631', NULL, '1986-04-25', 'Visita alinhamento', NULL, '2025-06-22 01:26:32', '2025-06-22 01:29:52', '191.246.227.94', 'ativo'),
(9, 'Eduardo de Oliveira Gomes', 'Belém', 'Superintendente Banpará', '(91) 98864-6631', NULL, '1986-04-25', 'Visita alinhamento', NULL, '2025-06-22 03:21:36', '2025-06-22 03:21:36', '191.246.233.91', 'ativo');

-- --------------------------------------------------------

--
-- Estrutura para tabela `logs_acesso`
--

DROP TABLE IF EXISTS `logs_acesso`;
CREATE TABLE `logs_acesso` (
  `id` int(11) NOT NULL,
  `tipo` enum('cadastro','admin_login','admin_action') NOT NULL,
  `descricao` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `data_hora` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Despejando dados para a tabela `logs_acesso`
--

INSERT INTO `logs_acesso` (`id`, `tipo`, `descricao`, `ip_address`, `user_agent`, `data_hora`) VALUES
(1, 'admin_login', 'Tentativa de login falhada: admin', '138.122.35.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-14 17:10:31'),
(2, 'admin_login', 'Login realizado por: DepChamon', '138.122.35.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-14 17:10:43'),
(3, 'cadastro', 'Novo cadastro: MAURO C M SA de Tucuruí', '138.122.35.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-14 17:12:53'),
(4, 'admin_login', 'Login realizado por: DepChamon', '138.122.35.253', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-14 17:15:15'),
(5, 'admin_login', 'Login realizado por: DepChamon', '138.122.35.253', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-14 18:06:19'),
(6, 'admin_login', 'Login realizado por: DepChamon', '177.55.73.91', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_6_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/137.0.7151.107 Mobile/15E148 Safari/604.1', '2025-06-14 19:16:23'),
(7, 'cadastro', 'Novo cadastro: Robson Fernando Coelho Luciano de Tucuruí', '177.55.73.91', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_6_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/137.0.7151.107 Mobile/15E148 Safari/604.1', '2025-06-14 19:18:34'),
(8, 'admin_login', 'Login realizado por: DepChamon', '177.55.73.91', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_6_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/137.0.7151.107 Mobile/15E148 Safari/604.1', '2025-06-14 19:19:49'),
(9, 'admin_action', 'Status do cadastro ID 2 alterado para inativo por DepChamon', '138.122.35.253', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-14 19:26:31'),
(10, 'admin_action', 'Status do cadastro ID 2 alterado para ativo por DepChamon', '138.122.35.253', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-14 19:26:36'),
(11, 'admin_action', 'Cadastro ID 2 deletado por DepChamon', '189.40.106.171', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-14 21:33:54'),
(12, 'admin_login', 'Logout realizado por: DepChamon', '138.122.35.253', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-14 22:41:52'),
(13, 'admin_login', 'Login realizado por: DepChamon', '138.122.35.253', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-14 22:45:28'),
(14, 'admin_login', 'Logout realizado por: DepChamon', '138.122.35.253', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-15 00:53:48'),
(15, 'admin_login', 'Login realizado por: DepChamon', '138.122.35.253', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-15 00:54:12'),
(16, 'admin_login', 'Login realizado por: DepChamon', '138.122.35.253', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-15 08:08:55'),
(17, 'admin_login', 'Login realizado por: DepChamon', '138.122.35.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-15 08:14:18'),
(18, 'admin_login', 'Login realizado por: DepChamon', '138.122.35.253', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-15 11:34:06'),
(19, 'admin_login', 'Login realizado por: DepChamon', '152.248.46.89', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_3_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.3.1 Mobile/15E148 Safari/604.1', '2025-06-15 13:37:57'),
(20, 'admin_login', 'Login realizado por: DepChamon', '138.122.35.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-15 17:10:25'),
(21, 'admin_login', 'Logout realizado por: DepChamon', '138.122.35.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-16 14:46:01'),
(22, 'cadastro', 'Novo cadastro: Carolina de Belém', '179.176.238.131', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-06-16 16:54:45'),
(23, 'admin_login', 'Login realizado por: DepChamon', '179.176.238.131', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-06-16 16:57:13'),
(24, 'admin_login', 'Login realizado por: DepChamon', '138.122.35.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-16 18:03:59'),
(25, 'admin_login', 'Logout realizado por: DepChamon', '138.122.35.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-16 18:08:57'),
(26, 'admin_login', 'Login realizado por: DepChamon', '138.122.35.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-16 18:09:00'),
(27, 'admin_action', 'Status do cadastro ID 1 alterado para inativo por DepChamon', '138.122.35.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-16 18:09:04'),
(28, 'admin_action', 'Status do cadastro ID 1 alterado para ativo por DepChamon', '138.122.35.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-16 18:09:08'),
(29, 'admin_action', 'Status do cadastro ID 1 alterado para ativo por DepChamon', '138.122.35.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-16 18:11:05'),
(30, 'admin_action', 'Status do cadastro ID 1 alterado para ativo por DepChamon', '138.122.35.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-16 18:11:16'),
(31, 'admin_action', 'Status do cadastro ID 1 alterado para ativo por DepChamon', '138.122.35.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-16 18:11:21'),
(32, 'admin_action', 'Status do cadastro ID 3 alterado para inativo por DepChamon', '138.122.35.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-16 18:11:25'),
(33, 'admin_action', 'Status do cadastro ID 3 alterado para ativo por DepChamon', '138.122.35.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-16 18:11:36'),
(34, 'admin_login', 'Logout realizado por: DepChamon', '138.122.35.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-16 18:12:24'),
(35, 'admin_login', 'Login realizado por: DepChamon', '138.122.35.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-16 18:12:27'),
(36, 'admin_login', 'Login realizado por: DepChamon', '138.122.35.253', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36', '2025-06-16 18:13:41'),
(37, 'admin_login', 'Logout realizado por: DepChamon', '138.122.35.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-16 18:15:50'),
(38, 'admin_login', 'Login realizado por: DepChamon', '138.122.35.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-16 18:15:53'),
(39, 'admin_login', 'Login realizado por: DepChamon', '138.122.35.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-16 18:22:18'),
(40, 'admin_action', 'Cadastro ID 1 editado por DepChamon - Obs Admin: 15/06/2025 - Visitou gabinete solicitando apoio pa', '138.122.35.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-16 18:28:22'),
(41, 'admin_action', 'Cadastro ID 1 editado por DepChamon - Obs Admin: 15/06/2025 - Visitou gabinete solicitando apoio pa', '138.122.35.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-16 18:38:06'),
(42, 'admin_action', 'Cadastro ID 3 editado por DepChamon - Obs Admin: ', '138.122.35.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-16 18:38:21'),
(43, 'admin_login', 'Logout realizado por: DepChamon', '138.122.35.253', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36', '2025-06-16 18:54:19'),
(44, 'admin_login', 'Login realizado por: DepChamon', '138.122.35.253', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36', '2025-06-16 18:54:36'),
(45, 'admin_action', 'Cadastro ID 1 editado por DepChamon - Obs Admin: 15/06/2025 - Visitou gabinete solicitando apoio pa', '138.122.35.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-16 19:08:27'),
(46, 'admin_login', 'Logout realizado por: DepChamon', '138.122.35.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-16 19:10:16'),
(47, 'admin_login', 'Login realizado por: DepChamon', '138.122.35.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-16 19:26:23'),
(48, 'admin_login', 'Logout realizado por: DepChamon', '138.122.35.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-16 19:26:27'),
(49, 'admin_login', 'Logout realizado por: DepChamon', '138.122.35.253', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36', '2025-06-16 19:27:15'),
(50, 'admin_login', 'Login realizado por: DepChamon', '138.122.35.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-16 19:54:13'),
(51, 'admin_login', 'Login realizado por: DepChamon', '138.122.35.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-16 21:38:37'),
(52, 'admin_login', 'Login realizado por: DepChamon', '138.122.35.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-16 23:20:03'),
(53, 'admin_login', 'Login realizado por: DepChamon', '138.122.35.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-17 07:37:20'),
(54, 'admin_login', 'Login realizado por: DepChamon', '138.122.35.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-17 11:25:36'),
(55, 'admin_login', 'Login realizado por: DepChamon', '138.122.35.253', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-17 13:03:56'),
(56, 'cadastro', 'Novo cadastro: Carolina Silva de Belém', '138.84.42.90', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-06-17 13:49:32'),
(57, 'admin_login', 'Login realizado por: DepChamon', '138.84.42.90', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-06-17 13:51:42'),
(58, 'admin_action', 'Cadastro ID 4 editado por DepChamon - Obs Admin: Veio ao gabinete no dia 17/06', '138.84.42.90', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-06-17 13:52:34'),
(59, 'admin_action', 'Cadastro ID 4 editado por DepChamon - Obs Admin: Veio ao gabinete no dia 17/06', '138.84.42.90', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-06-17 13:52:34'),
(60, 'admin_login', 'Login realizado por: DepChamon', '138.122.35.253', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36', '2025-06-17 13:54:24'),
(61, 'cadastro', 'Novo cadastro: Euripedes Reis de Belém', '138.84.42.90', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-06-17 13:56:29'),
(62, 'cadastro', 'Novo cadastro: Luciano de Marabá', '138.84.42.90', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-06-17 14:01:26'),
(63, 'admin_login', 'Login realizado por: DepChamon', '138.122.35.253', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36', '2025-06-17 18:04:24'),
(64, 'admin_login', 'Login realizado por: DepChamon', '138.122.35.253', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36', '2025-06-17 18:46:22'),
(65, 'admin_login', 'Login realizado por: DepChamon', '138.122.35.253', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36', '2025-06-17 19:22:36'),
(66, 'admin_login', 'Login realizado por: DepChamon', '138.122.34.178', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36', '2025-06-17 20:47:25'),
(67, 'admin_login', 'Login realizado por: DepChamon', '138.122.34.178', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36', '2025-06-17 21:04:10'),
(68, 'admin_login', 'Login realizado por: DepChamon', '138.122.34.178', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36', '2025-06-17 21:43:38'),
(69, 'admin_login', 'Login realizado por: DepChamon', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-17 22:43:42'),
(70, 'admin_login', 'Login realizado por: DepChamon', '138.122.34.178', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36', '2025-06-18 21:44:14'),
(71, 'cadastro', 'Novo cadastro: Robson Fernando Coelho Luciano de Tucuruí', '177.104.242.111', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_6_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/137.0.7151.107 Mobile/15E148 Safari/604.1', '2025-06-22 01:14:27'),
(72, 'cadastro', 'Novo cadastro: Eduardo de Oliveira Gomes de Belém', '191.246.227.94', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/137.0.7151.107 Mobile/15E148 Safari/604.1', '2025-06-22 01:26:32'),
(73, 'admin_login', 'Login realizado por: DepChamon', '179.151.221.131', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-06-22 01:27:09'),
(74, 'admin_action', 'Status do cadastro ID 8 alterado para inativo por DepChamon', '179.151.221.131', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-06-22 01:29:33'),
(75, 'admin_action', 'Status do cadastro ID 8 alterado para ativo por DepChamon', '179.151.221.131', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-06-22 01:29:52'),
(76, 'cadastro', 'Novo cadastro: Eduardo de Oliveira Gomes de Belém', '191.246.233.91', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/137.0.7151.107 Mobile/15E148 Safari/604.1', '2025-06-22 03:21:36'),
(77, 'admin_login', 'Tentativa de login falhada: admin', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-22 11:01:39'),
(78, 'admin_login', 'Login realizado por: DepChamon', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-22 11:01:43'),
(79, 'admin_action', 'Cadastro ID 1 editado por DepChamon - Obs Admin: 15/06/2025 - Visitou gabinete solicitando apoio pa', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-22 11:04:53'),
(80, 'admin_action', 'Cadastro ID 1 editado por DepChamon - Obs Admin: 15/06/2025 - Visitou gabinete solicitando apoio pa', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-22 11:05:14'),
(81, 'admin_login', 'Logout realizado por: DepChamon', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-22 11:05:23'),
(82, 'admin_login', 'Login realizado por: DepChamon', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-22 11:06:37'),
(83, 'admin_action', 'Cadastro ID 7 deletado por DepChamon', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-22 11:22:51'),
(84, 'admin_action', 'Cadastro ID 7 deletado por DepChamon', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-22 11:22:59'),
(85, 'admin_action', 'Cadastro ID 7 deletado por DepChamon', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-22 11:26:32'),
(86, 'admin_action', 'Agendamento criado: MAURO CARLOS para 2025-06-22 por DepChamon', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-22 11:29:07'),
(87, 'admin_login', 'Login realizado por: DepChamon', '138.122.34.178', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36', '2025-06-22 12:01:24'),
(88, 'admin_action', 'Cadastro ID 1 editado por DepChamon - Obs Admin: 15/06/2025 - Visitou gabinete solicitando apoio pa', '138.122.34.178', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36', '2025-06-22 12:04:02'),
(89, 'admin_action', 'Agendamento ID 1 editado por DepChamon', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-22 12:16:47'),
(90, 'admin_login', 'Logout realizado por: DepChamon', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-22 12:20:26'),
(91, 'admin_login', 'Login realizado por: DepChamon', '189.40.104.100', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36', '2025-06-22 13:01:12'),
(92, 'admin_login', 'Login realizado por: DepChamon', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-22 13:25:04'),
(93, 'admin_login', 'Login realizado por: DepChamon', '138.122.34.178', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36', '2025-06-22 16:28:32'),
(94, 'admin_login', 'Logout realizado por: DepChamon', '138.122.34.178', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36', '2025-06-22 16:28:45'),
(95, 'admin_login', 'Login realizado por: DepChamon', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-23 10:26:54'),
(96, 'admin_action', 'Status do agendamento ID 1 \'MAURO CARLOS\' alterado de \'agendado\' para \'realizado\' por DepChamon', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-23 10:49:40'),
(97, 'admin_action', 'Agendamento ID 1 editado por DepChamon - Status: realizado', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-23 11:48:33'),
(98, 'admin_action', 'Agendamento ID 1 editado por DepChamon - Status: cancelado', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-23 11:49:00'),
(99, 'admin_action', 'Agendamento ID 1 \'MAURO CARLOS\' excluído por DepChamon', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-23 11:49:17'),
(100, 'admin_login', 'Login realizado por: DepChamon', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-23 18:39:26'),
(101, 'admin_login', 'Login realizado por: DepChamon', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-23 22:39:45'),
(102, 'admin_login', 'Login realizado por: DepChamon', '138.84.42.178', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-06-24 11:48:41'),
(103, 'admin_login', 'Login realizado por: DepChamon', '179.151.208.98', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-06-24 13:22:01'),
(104, 'cadastro', 'Novo cadastro: Juliane Corrêa de Belém', '191.246.249.154', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1', '2025-06-24 18:10:48'),
(105, 'admin_login', 'Login realizado por: DepChamon', '138.84.42.178', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-06-24 18:11:20'),
(106, 'cadastro', 'Novo cadastro: Juliane Corrêa de Belém', '148.222.209.77', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1', '2025-06-25 12:36:54'),
(107, 'admin_login', 'Login realizado por: DepChamon', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 02:07:19'),
(108, 'admin_login', 'Login realizado por: DepChamon', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 03:17:36'),
(109, 'admin_action', 'Cadastro ID 11 deletado por DepChamon', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 03:17:48'),
(110, 'admin_action', 'Cadastro ID 8 deletado por DepChamon', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 03:17:55'),
(111, 'admin_action', 'Cadastro ID 3 deletado por DepChamon', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 03:18:03'),
(112, 'admin_login', 'Logout realizado por: DepChamon', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 03:18:19'),
(113, '', 'Tentativa de cadastro duplicado - Telefone: (94) 98170-9809 - Nome tentativa: MAURO CARLOS MARTINS DE SA', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 03:23:27'),
(114, '', 'Tentativa de cadastro duplicado - Telefone: (94) 98170-9809 - Nome tentativa: MAURO CARLOS MARTINS DE SA', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 03:23:49'),
(115, '', 'TELEFONE DUPLICADO - Telefone: (94) 98170-9809 - Tentativa: MAURO CARLOS MARTINS DE SA - Existente: MAURO CARLOS MARTINS DE SA', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 03:30:46'),
(116, '', 'TELEFONE DUPLICADO - Telefone: (94) 98170-9809 - Tentativa: MAURO CARLOS MARTINS DE SA - Existente: MAURO CARLOS MARTINS DE SA', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 03:30:46'),
(117, '', 'EMAIL DUPLICADO - Email: maurocarlos.ti@gmail.com - Telefone tentativa: (94) 98170-9808 - Nome tentativa: MAURO CARLOS MARTINS DE SA - Existente: MAURO CARLOS MARTINS DE SA ((94) 98170-9809)', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 03:30:57'),
(118, '', 'TELEFONE DUPLICADO - Telefone: (94) 98170-9809 - Tentativa: MAURO CARLOS MARTINS DE SA - Existente: MAURO CARLOS MARTINS DE SA', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 03:31:58'),
(119, '', 'TELEFONE DUPLICADO - Telefone: (94) 98170-9809 - Tentativa: MAURO CARLOS MARTINS DE SA - Existente: MAURO CARLOS MARTINS DE SA', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 03:31:58'),
(120, '', 'EMAIL DUPLICADO - Email: maurocarlos.ti@gmail.com - Telefone tentativa: (94) 98170-9808 - Nome tentativa: MAURO CARLOS MARTINS DE SA - Existente: MAURO CARLOS MARTINS DE SA ((94) 98170-9809)', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 03:32:30'),
(121, '', 'EMAIL DUPLICADO - Email: maurocarlos.ti@gmail.com - Telefone tentativa: (94) 98170-9808 - Nome tentativa: MAURO CARLOS MARTINS DE SA - Existente: MAURO CARLOS MARTINS DE SA ((94) 98170-9809)', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 03:32:41'),
(122, '', 'TELEFONE DUPLICADO - Telefone: (94) 98170-9809 - Tentativa: MAURO CARLOS MARTINS DE SA - Existente: MAURO CARLOS MARTINS DE SA', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 03:33:39'),
(123, '', 'TELEFONE DUPLICADO - Telefone: (94) 98170-9809 - Tentativa: MAURO CARLOS MARTINS DE SA - Existente: MAURO CARLOS MARTINS DE SA', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 03:33:41'),
(124, '', 'EMAIL DUPLICADO - Email: maurocarlos.ti@gmail.com - Telefone tentativa: (94) 98170-9808 - Nome tentativa: MAURO CARLOS MARTINS DE SA - Existente: MAURO CARLOS MARTINS DE SA ((94) 98170-9809)', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 03:33:43'),
(125, '', 'EMAIL DUPLICADO - Email: maurocarlos.ti@gmail.com - Telefone tentativa: (94) 98170-9808 - Nome tentativa: MAURO CARLOS MARTINS DE SA - Existente: MAURO CARLOS MARTINS DE SA ((94) 98170-9809)', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 03:33:50'),
(126, '', 'EMAIL DUPLICADO - Email: maurocarlos.ti@gmail.com - Telefone tentativa: (94) 98170-9808 - Nome tentativa: MAURO CARLOS MARTINS DE SA - Existente: MAURO CARLOS MARTINS DE SA ((94) 98170-9809)', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 03:34:02'),
(127, '', 'EMAIL DUPLICADO - Email: maurocarlos.ti@gmail.com - Telefone tentativa: (94) 98170-9808 - Nome tentativa: MAURO CARLOS MARTINS DE SA - Existente: MAURO CARLOS MARTINS DE SA ((94) 98170-9809)', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 03:34:51'),
(128, '', 'EMAIL DUPLICADO - Email: maurocarlos.ti@gmail.com - Telefone tentativa: (94) 98170-9807 - Nome tentativa: MAURO CARLOS MARTINS DE SA - Existente: MAURO CARLOS MARTINS DE SA ((94) 98170-9809)', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 03:34:56'),
(129, '', 'TELEFONE DUPLICADO - Telefone: (94) 98170-9809 - Tentativa: MAURO CARLOS MARTINS DE SA - Existente: MAURO CARLOS MARTINS DE SA', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 03:35:00'),
(130, '', 'TELEFONE DUPLICADO - Telefone: (94) 98170-9809 - Tentativa: MAURO CARLOS MARTINS DE SA - Existente: MAURO CARLOS MARTINS DE SA', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 03:35:00'),
(131, '', 'EMAIL DUPLICADO - Email: maurocarlos.ti@gmail.com - Telefone tentativa: (94) 98170-9808 - Nome tentativa: MAURO CARLOS MARTINS DE SA - Existente: MAURO CARLOS MARTINS DE SA ((94) 98170-9809)', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 03:35:03'),
(132, '', 'EMAIL DUPLICADO - Email: maurocarlos.ti@gmail.com - Telefone tentativa: (94) 98170-9808 - Nome tentativa: MAURO CARLOS MARTINS DE SA - Existente: MAURO CARLOS MARTINS DE SA ((94) 98170-9809)', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 03:35:15'),
(133, '', 'TELEFONE DUPLICADO - Telefone: (94) 98170-9809 - Tentativa: MAURO CARLOS MARTINS DE SA - Existente: MAURO CARLOS MARTINS DE SA', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 03:35:22'),
(134, '', 'TELEFONE DUPLICADO - Telefone: (94) 98170-9809 - Tentativa: MAURO CARLOS MARTINS DE SA - Existente: MAURO CARLOS MARTINS DE SA', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 03:35:34'),
(135, '', 'TELEFONE DUPLICADO - Telefone: (94) 98170-9809 - Tentativa: MAURO CARLOS MARTINS DE SA - Existente: MAURO CARLOS MARTINS DE SA', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 03:35:39'),
(136, '', 'TELEFONE DUPLICADO - Telefone: (94) 98170-9809 - Tentativa: MAURO CARLOS MARTINS DE SA - Existente: MAURO CARLOS MARTINS DE SA', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 03:35:49'),
(137, '', 'TELEFONE DUPLICADO - Telefone: (94) 98170-9809 - Tentativa: MAURO CARLOS MARTINS DE SA - Existente: MAURO CARLOS MARTINS DE SA', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 03:35:58'),
(138, '', 'TELEFONE DUPLICADO - Telefone: (94) 98170-9809 - Tentativa: mauro carlos - Existente: MAURO CARLOS MARTINS DE SA', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 03:36:21'),
(139, 'admin_login', 'Login realizado por: DepChamon', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 03:36:44'),
(140, 'admin_login', 'Login realizado por: DepChamon', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 11:11:23'),
(141, 'admin_login', 'Logout realizado por: DepChamon', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 11:24:43'),
(142, '', 'TELEFONE DUPLICADO - Telefone: (94) 98170-9809 - Tentativa:  - Existente: MAURO CARLOS MARTINS DE SA', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 11:25:17'),
(143, '', 'EMAIL DUPLICADO - Email: maurocarlos.ti@gmail.com - Telefone tentativa: (94) 98170-9808 - Nome tentativa:  - Existente: MAURO CARLOS MARTINS DE SA ((94) 98170-9809)', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 11:25:56'),
(144, '', 'EMAIL DUPLICADO - Email: maurocarlos.ti@gmail.com - Telefone tentativa: (94) 98170-9808 - Nome tentativa: MAURO CARLOS MARTINS DE SA - Existente: MAURO CARLOS MARTINS DE SA ((94) 98170-9809)', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 11:26:00'),
(145, '', 'EMAIL DUPLICADO - Email: maurocarlos.ti@gmail.com - Telefone tentativa: (94) 98170-9808 - Nome tentativa: MAURO CARLOS MARTINS DE SA - Existente: MAURO CARLOS MARTINS DE SA ((94) 98170-9809)', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 11:26:08'),
(146, '', 'EMAIL DUPLICADO - Email: maurocarlos.ti@gmail.com - Telefone tentativa: (94) 98170-9808 - Nome tentativa: MAURO CARLOS MARTINS DE SA - Existente: MAURO CARLOS MARTINS DE SA ((94) 98170-9809)', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 11:26:16'),
(147, 'admin_login', 'Login realizado por: DepChamon', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 12:13:21'),
(148, 'admin_login', 'Login realizado por: DepChamon', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 14:10:08'),
(149, 'admin_login', 'Login realizado por: DepChamon', '148.222.209.77', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-06-26 16:13:35'),
(150, 'cadastro', 'Novo cadastro aprovado: Cristiany Borges de Belém - Telefone: (91) 98220-2760', '191.246.242.244', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/28.0 Chrome/130.0.0.0 Mobile Safari/537.36', '2025-06-26 16:13:46'),
(151, 'admin_action', 'Status do cadastro ID 12 alterado para inativo por DepChamon', '148.222.209.77', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-06-26 16:14:32'),
(152, 'cadastro', 'Novo cadastro aprovado: Wandressa Sodré de Belém - Telefone: (91) 98953-0873', '189.40.106.8', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1', '2025-06-26 16:22:46'),
(153, 'cadastro', 'Novo cadastro aprovado: Larissa negrão Fernandes de Belém - Telefone: (91) 99206-3466', '148.222.209.77', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/28.0 Chrome/130.0.0.0 Mobile Safari/537.36', '2025-06-26 16:23:22'),
(154, 'cadastro', 'Novo cadastro aprovado: Honorato Luis Lima Cosenza Nogueira de Belém - Telefone: (91) 98187-1400', '191.246.239.175', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', '2025-06-26 16:24:25'),
(155, 'cadastro', 'Novo cadastro aprovado: Francileide Almeida da Silva de Belem - Telefone: (91) 98514-3486', '191.246.246.79', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-06-26 16:27:32'),
(156, 'admin_login', 'Login realizado por: DepChamon', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 17:24:59'),
(157, 'admin_login', 'Login realizado por: DepChamon', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-26 18:38:55'),
(158, 'admin_login', 'Login realizado por: DepChamon', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-27 11:20:58'),
(159, 'admin_login', 'Login realizado por: DepChamon', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-27 12:17:03'),
(160, 'admin_login', 'Login realizado por: DepChamon', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-27 15:10:07'),
(161, 'admin_login', 'Login realizado por: DepChamon', '179.183.248.96', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2025-06-28 00:38:48'),
(162, 'admin_login', 'Login realizado por: DepChamon', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-28 23:54:49'),
(163, 'admin_login', 'Login realizado por: DepChamon', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-30 22:28:40'),
(164, 'admin_login', 'Login realizado por: DepChamon', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-07-01 11:37:31'),
(165, 'admin_login', 'Login realizado por: DepChamon', '177.104.241.84', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36', '2025-07-02 20:49:26'),
(166, 'admin_login', 'Login realizado por: DepChamon', '138.122.34.178', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36', '2025-07-03 03:06:46'),
(167, 'admin_login', 'Login realizado por: DepChamon', '138.122.34.178', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-07-03 18:48:20'),
(168, 'admin_login', 'Login realizado por: DepChamon', '164.163.193.196', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-10 09:40:52'),
(169, 'admin_login', 'Login realizado por: DepChamon', '138.122.35.252', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-30 14:28:39'),
(170, 'admin_login', 'Login realizado por: DepChamon', '138.122.35.252', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-30 17:20:00'),
(171, '', 'TELEFONE DUPLICADO BLOQUEADO - Telefone: (94) 99248-6398 - Tentativa: Euripedes Reis (Belém) - Existente: Euripedes Reis (Belém)', '189.114.249.146', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', '2025-08-19 10:21:10'),
(172, 'admin_login', 'Login realizado por: DepChamon', '138.122.35.200', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 01:18:52'),
(173, 'admin_login', 'Login realizado por: DepChamon', '138.122.35.197', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-16 13:38:39'),
(174, 'admin_login', 'Login realizado por: DepChamon', '138.122.35.168', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-29 15:50:03'),
(175, 'admin_login', 'Logout realizado por: DepChamon', '138.122.35.168', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-29 15:50:15'),
(176, 'admin_login', 'Tentativa de login falhada: admin', '138.122.35.164', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-14 20:00:46'),
(177, 'admin_login', 'Tentativa de login falhada: admin', '138.122.35.164', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-14 20:00:51'),
(178, 'admin_login', 'Tentativa de login falhada: admin', '138.122.35.164', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-14 20:00:55'),
(179, 'admin_login', 'Login realizado por: DepChamon', '138.122.35.164', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-14 20:01:45');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `administradores`
--
ALTER TABLE `administradores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario` (`usuario`);

--
-- Índices de tabela `agendamentos`
--
ALTER TABLE `agendamentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_data_agendamento` (`data_agendamento`),
  ADD KEY `idx_cadastro_id` (`cadastro_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_tipo` (`tipo`),
  ADD KEY `idx_data_hora` (`data_agendamento`,`hora_inicio`),
  ADD KEY `criado_por` (`criado_por`);

--
-- Índices de tabela `cadastros`
--
ALTER TABLE `cadastros`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_nome` (`nome`),
  ADD KEY `idx_cidade` (`cidade`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_telefone` (`telefone`),
  ADD KEY `idx_data_cadastro` (`data_cadastro`);

--
-- Índices de tabela `logs_acesso`
--
ALTER TABLE `logs_acesso`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tipo` (`tipo`),
  ADD KEY `idx_data_hora` (`data_hora`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `administradores`
--
ALTER TABLE `administradores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `agendamentos`
--
ALTER TABLE `agendamentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `cadastros`
--
ALTER TABLE `cadastros`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de tabela `logs_acesso`
--
ALTER TABLE `logs_acesso`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=180;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `agendamentos`
--
ALTER TABLE `agendamentos`
  ADD CONSTRAINT `agendamentos_ibfk_1` FOREIGN KEY (`cadastro_id`) REFERENCES `cadastros` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `agendamentos_ibfk_2` FOREIGN KEY (`criado_por`) REFERENCES `administradores` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
