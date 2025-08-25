CREATE DATABASE IF NOT EXISTS `ga4_vitaliza`;
USE `ga4_vitaliza`;

CREATE TABLE IF NOT EXISTS `ga4_1_clientes` (
  `ga4_1_id` INT NOT NULL AUTO_INCREMENT,
  `ga4_1_cpf` INT(11) NOT NULL,
  `ga4_1_nome` VARCHAR(100) NOT NULL,
  `ga4_1_nasc` DATE DEFAULT NULL,
  `ga4_1_sexo` ENUM('Masculino','Feminino','Outro') NOT NULL,
  `ga4_1_tel` VARCHAR(10) DEFAULT NULL,
  `ga4_1_cep` INT(8) NOT NULL,
  `ga4_1_email` VARCHAR(100) NOT NULL,
  `ga4_1_senha` VARCHAR(100) NOT NULL,
  `ga4_1_status` ENUM('Ativo','Inativo') DEFAULT 'Ativo',
  PRIMARY KEY (`ga4_1_id`),
  UNIQUE KEY (`ga4_1_cpf`), 
  UNIQUE KEY (`ga4_1_email`) 
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `ga4_2_profissionais` (
  `ga4_2_id` INT NOT NULL AUTO_INCREMENT,
  `ga4_2_crm` INT(11) NOT NULL,
  `ga4_2_cpf` INT(11) NOT NULL,
  `ga4_2_especialidade` VARCHAR(100) NOT NULL,
  `ga4_2_nome` VARCHAR(100) NOT NULL,
  `ga4_2_nasc` DATE DEFAULT NULL,
  `ga4_2_sexo` ENUM('Masculino','Feminino','Outro') NOT NULL,
  `ga4_2_tel` VARCHAR(11) DEFAULT NULL,
  `ga4_2_cep` INT(8) NOT NULL,
  `ga4_2_email` VARCHAR(100) NOT NULL,
  `ga4_2_senha` VARCHAR(100) NOT NULL,
  `ga4_2_fotocert` VARCHAR(255),
  `ga4_2_status` ENUM('Ativo','Inativo') DEFAULT 'Ativo',
  `ga4_2_verificado` ENUM('Sim','Não') DEFAULT 'Não',
  PRIMARY KEY(`ga4_2_id`),
  UNIQUE KEY(`ga4_2_cpf`),
  UNIQUE KEY(`ga4_2_crm`),
  UNIQUE KEY(`ga4_2_email`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `ga4_3_consultas` (
  `ga4_3_idconsul` INT NOT NULL AUTO_INCREMENT,
  `ga4_3_id_cliente` INT NOT NULL,
  `ga4_3_id_profissional` INT NOT NULL,
  `ga4_3_data` DATE DEFAULT NULL,
  `ga4_3_hora` TIME DEFAULT NULL,
  `ga4_3_motivo` VARCHAR(255) DEFAULT NULL,
  `ga4_3_status` ENUM('Pendente','Aceita','Recusada','Cancelado','Aguardando confirmação', 'Concluída', 'Arquivada') NOT NULL,
  `ga4_3_motcanc` VARCHAR(255),
  PRIMARY KEY(`ga4_3_idconsul`),
  CONSTRAINT `ga4_3_consultas_ifbk_1` FOREIGN KEY (`ga4_3_id_cliente`) REFERENCES `ga4_1_clientes` (`ga4_1_id`),
  CONSTRAINT `ga4_3_consultas_ifbk_2` FOREIGN KEY (`ga4_3_id_profissional`) REFERENCES `ga4_2_profissionais` (`ga4_2_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `ga4_4_mensagens` (
  `ga4_4_id` INT NOT NULL AUTO_INCREMENT,
  `ga4_4_id_cliente` INT,
  `ga4_4_id_profissional` INT,
  `ga4_4_mensagem` TEXT NOT NULL,
  `ga4_4_data_envio` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `ga4_4_enviado_por` ENUM('cliente', 'profissional') NOT NULL,
  `ga4_4_status_mensagem` ENUM('não entregue', 'entregue', 'lida') DEFAULT 'não entregue',
  PRIMARY KEY (`ga4_4_id`),
  CONSTRAINT `ga4_4_mensagens_ifbk_1` FOREIGN KEY (`ga4_4_id_cliente`) REFERENCES `ga4_1_clientes` (`ga4_1_id`),
  CONSTRAINT `ga4_4_mensagens_ifbk_2` FOREIGN KEY (`ga4_4_id_profissional`) REFERENCES `ga4_2_profissionais` (`ga4_2_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `ga4_5_administradores` (
  `ga4_5_id` INT NOT NULL AUTO_INCREMENT,
  `ga4_5_nome` VARCHAR(100) NOT NULL,
  `ga4_5_email` VARCHAR(100) NOT NULL,
  `ga4_5_senha` VARCHAR(100) NOT NULL,
  `ga4_5_nivel_acesso` ENUM('admin', 'superadmin') DEFAULT 'admin',
  `ga4_5_data_criacao` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `ga4_5_ultimo_acesso` TIMESTAMP NULL,
  PRIMARY KEY (`ga4_5_id`),
  UNIQUE KEY (`ga4_5_email`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
