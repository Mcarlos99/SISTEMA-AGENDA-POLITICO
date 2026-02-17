-- =============================================================
--   Extreme - PoLiX SaaS  |  Agenda Política Profissional
--   polix_saas.sql  -  Schema Multi-Tenant v3.0
--   Desenvolvido por: Mauro Carlos (94) 98170-9809
-- =============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS `polix_saas`
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE `polix_saas`;

-- -----------------------------------------------------------
--  PLANOS DE ASSINATURA
-- -----------------------------------------------------------
DROP TABLE IF EXISTS `planos`;
CREATE TABLE `planos` (
    `id`                  INT          NOT NULL AUTO_INCREMENT,
    `nome`                VARCHAR(100) NOT NULL,
    `slug`                VARCHAR(50)  NOT NULL,
    `max_cadastros`       INT          DEFAULT 1000,
    `max_usuarios`        INT          DEFAULT 3,
    `tem_calendario`      TINYINT(1)   DEFAULT 1,
    `tem_relatorios`      TINYINT(1)   DEFAULT 1,
    `tem_exportacao`      TINYINT(1)   DEFAULT 1,
    `preco_mensal`        DECIMAL(8,2) DEFAULT 0.00,
    `status`              ENUM('ativo','inativo') DEFAULT 'ativo',
    `data_criacao`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `planos` (`nome`, `slug`, `max_cadastros`, `max_usuarios`, `preco_mensal`) VALUES
    ('Básico',       'basico',       500,   2,  97.00),
    ('Profissional', 'profissional', 2000,  5,  197.00),
    ('Premium',      'premium',      10000, 15, 397.00),
    ('Ilimitado',    'ilimitado',    0,     0,  697.00);

-- -----------------------------------------------------------
--  TENANTS (políticos / escritórios)
-- -----------------------------------------------------------
DROP TABLE IF EXISTS `tenants`;
CREATE TABLE `tenants` (
    `id`              INT          NOT NULL AUTO_INCREMENT,
    `slug`            VARCHAR(60)  NOT NULL  COMMENT 'URL amigável: /joao-silva/',
    `plano_id`        INT          DEFAULT 2,
    `nome_politico`   VARCHAR(255) NOT NULL,
    `cargo`           VARCHAR(100) NOT NULL,
    `partido`         VARCHAR(50)  DEFAULT NULL,
    `estado`          CHAR(2)      DEFAULT NULL,
    `municipio`       VARCHAR(100) DEFAULT NULL,
    `foto_url`        VARCHAR(500) DEFAULT NULL,
    `cor_primaria`    VARCHAR(7)   DEFAULT '#003366',
    `cor_secundaria`  VARCHAR(7)   DEFAULT '#0055aa',
    `cor_acento`      VARCHAR(7)   DEFAULT '#0077cc',
    `email_contato`   VARCHAR(255) DEFAULT NULL,
    `telefone`        VARCHAR(20)  DEFAULT NULL,
    `whatsapp`        VARCHAR(20)  DEFAULT NULL,
    `site`            VARCHAR(255) DEFAULT NULL,
    `status`          ENUM('ativo','inativo','suspenso','trial') DEFAULT 'trial',
    `trial_expira`    DATE         DEFAULT NULL,
    `assinatura_ate`  DATE         DEFAULT NULL,
    `data_criacao`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `data_atualizacao` TIMESTAMP   NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `slug` (`slug`),
    KEY `idx_status`  (`status`),
    CONSTRAINT `fk_tenant_plano` FOREIGN KEY (`plano_id`) REFERENCES `planos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
--  SUPER-ADMINISTRADORES (gestão da plataforma)
-- -----------------------------------------------------------
DROP TABLE IF EXISTS `super_admins`;
CREATE TABLE `super_admins` (
    `id`           INT          NOT NULL AUTO_INCREMENT,
    `usuario`      VARCHAR(50)  NOT NULL,
    `senha`        VARCHAR(255) NOT NULL,
    `nome`         VARCHAR(255) NOT NULL,
    `email`        VARCHAR(255) DEFAULT NULL,
    `ultimo_acesso` TIMESTAMP   NULL DEFAULT NULL,
    `status`       ENUM('ativo','inativo') DEFAULT 'ativo',
    `data_criacao` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `usuario` (`usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Senha padrão: SuperAdmin@2025  (ALTERE IMEDIATAMENTE)
INSERT INTO `super_admins` (`usuario`, `senha`, `nome`) VALUES
    ('superadmin', '$2y$12$O5CFsbddndV8d3oIYYSw6.N/JcwrjNLc/r2nOtKF3CBBWq64OUo3m', 'Super Administrador');

-- -----------------------------------------------------------
--  ADMINISTRADORES DE TENANT
-- -----------------------------------------------------------
DROP TABLE IF EXISTS `administradores`;
CREATE TABLE `administradores` (
    `id`            INT          NOT NULL AUTO_INCREMENT,
    `tenant_id`     INT          NOT NULL,
    `usuario`       VARCHAR(50)  NOT NULL,
    `senha`         VARCHAR(255) NOT NULL,
    `nome`          VARCHAR(255) NOT NULL,
    `email`         VARCHAR(255) DEFAULT NULL,
    `nivel`         ENUM('master','admin','operador') DEFAULT 'admin',
    `ultimo_acesso` TIMESTAMP    NULL DEFAULT NULL,
    `status`        ENUM('ativo','inativo') DEFAULT 'ativo',
    `data_criacao`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `tenant_usuario` (`tenant_id`, `usuario`),
    KEY `idx_tenant_id` (`tenant_id`),
    CONSTRAINT `fk_adm_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
--  CADASTROS (isolados por tenant)
-- -----------------------------------------------------------
DROP TABLE IF EXISTS `cadastros`;
CREATE TABLE `cadastros` (
    `id`                    INT          NOT NULL AUTO_INCREMENT,
    `tenant_id`             INT          NOT NULL,
    `nome`                  VARCHAR(255) NOT NULL,
    `cidade`                VARCHAR(100) NOT NULL,
    `cargo`                 VARCHAR(100) NOT NULL,
    `telefone`              VARCHAR(20)  NOT NULL,
    `email`                 VARCHAR(255) DEFAULT NULL,
    `data_nascimento`       DATE         NOT NULL,
    `categoria`             VARCHAR(50)  DEFAULT 'eleitor',
    `observacoes`           TEXT         DEFAULT NULL COMMENT 'Texto livre pelo cidadão',
    `observacoes_admin`     TEXT         DEFAULT NULL COMMENT 'Notas internas do gabinete',
    `partido_vinculo`       VARCHAR(50)  DEFAULT NULL,
    `nivel_politico`        VARCHAR(100) DEFAULT NULL,
    `tags`                  VARCHAR(500) DEFAULT NULL,
    `status`                ENUM('ativo','inativo') DEFAULT 'ativo',
    `ip_address`            VARCHAR(45)  DEFAULT NULL,
    `data_cadastro`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `data_ultima_alteracao` TIMESTAMP    NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tenant`        (`tenant_id`),
    KEY `idx_nome`          (`nome`),
    KEY `idx_cidade`        (`cidade`),
    KEY `idx_telefone`      (`telefone`),
    KEY `idx_email`         (`email`),
    KEY `idx_categoria`     (`categoria`),
    KEY `idx_status`        (`status`),
    KEY `idx_data_cadastro` (`data_cadastro`),
    UNIQUE KEY `tenant_telefone` (`tenant_id`, `telefone`),
    CONSTRAINT `fk_cad_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
--  AGENDAMENTOS (isolados por tenant)
-- -----------------------------------------------------------
DROP TABLE IF EXISTS `agendamentos`;
CREATE TABLE `agendamentos` (
    `id`                    INT          NOT NULL AUTO_INCREMENT,
    `tenant_id`             INT          NOT NULL,
    `cadastro_id`           INT          DEFAULT NULL,
    `titulo`                VARCHAR(255) NOT NULL,
    `descricao`             TEXT         DEFAULT NULL,
    `data_agendamento`      DATE         NOT NULL,
    `hora_inicio`           TIME         NOT NULL,
    `hora_fim`              TIME         DEFAULT NULL,
    `tipo`                  ENUM('retorno','reuniao','visita','evento','audiencia','outro') DEFAULT 'retorno',
    `status`                ENUM('agendado','confirmado','realizado','cancelado') DEFAULT 'agendado',
    `prioridade`            ENUM('baixa','media','alta','urgente') DEFAULT 'media',
    `local`                 VARCHAR(255) DEFAULT NULL,
    `observacoes`           TEXT         DEFAULT NULL,
    `lembrete_antecedencia` INT          DEFAULT 60,
    `criado_por`            INT          NOT NULL,
    `data_criacao`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `data_atualizacao`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tenant`           (`tenant_id`),
    KEY `idx_data_agendamento` (`data_agendamento`),
    KEY `idx_cadastro_id`      (`cadastro_id`),
    KEY `idx_status`           (`status`),
    KEY `idx_criado_por`       (`criado_por`),
    KEY `idx_data_hora`        (`data_agendamento`, `hora_inicio`),
    CONSTRAINT `fk_ag_tenant`    FOREIGN KEY (`tenant_id`)   REFERENCES `tenants`          (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ag_cadastro`  FOREIGN KEY (`cadastro_id`) REFERENCES `cadastros`        (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_ag_criado_por` FOREIGN KEY (`criado_por`) REFERENCES `administradores`  (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
--  LOGS DE ACESSO (com tenant opcional)
-- -----------------------------------------------------------
DROP TABLE IF EXISTS `logs_acesso`;
CREATE TABLE `logs_acesso` (
    `id`          INT           NOT NULL AUTO_INCREMENT,
    `tenant_id`   INT           DEFAULT NULL,
    `tipo`        VARCHAR(50)   NOT NULL DEFAULT '',
    `descricao`   TEXT          DEFAULT NULL,
    `ip_address`  VARCHAR(45)   DEFAULT NULL,
    `user_agent`  TEXT          DEFAULT NULL,
    `data_hora`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tenant`    (`tenant_id`),
    KEY `idx_tipo`      (`tipo`),
    KEY `idx_data_hora` (`data_hora`),
    CONSTRAINT `fk_log_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

-- =============================================================
--  INSTRUÇÕES PÓS-INSTALAÇÃO
--  1. Importe este arquivo no MySQL
--  2. Acesse /superadmin/login.php (senha: SuperAdmin@2025)
--  3. Crie o primeiro tenant em /superadmin/tenants.php
--  4. O link de cadastro público será: /{slug}/
--  5. O admin do tenant acessa: /{slug}/admin/
-- =============================================================
