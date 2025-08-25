CREATE DATABASE IF NOT EXISTS `vitaliza`;
USE `vitaliza`;

CREATE TABLE IF NOT EXISTS `tb1_clientes` (
  `tb1_id` INT NOT NULL AUTO_INCREMENT,
  `tb1_cpf` INT(11) NOT NULL,
  `tb1_nome` VARCHAR(100) NOT NULL,
  `tb1_nasc` DATE DEFAULT NULL,
  `tb1_sexo` ENUM('Masculino','Feminino','Outro') NOT NULL,
  `tb1_tel` VARCHAR(10) DEFAULT NULL,
  `tb1_cep` INT(8) NOT NULL,
  `tb1_email` VARCHAR(100) NOT NULL,
  `tb1_senha` VARCHAR(100) NOT NULL,
  `tb1_status` ENUM('Ativo','Inativo') DEFAULT 'Ativo',
  PRIMARY KEY (`tb1_id`),
  UNIQUE KEY (`tb1_cpf`), 
  UNIQUE KEY (`tb1_email`) 
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tb2_profissionais` (
  `tb2_id` INT NOT NULL AUTO_INCREMENT,
  `tb2_crm` INT(11) NOT NULL,
  `tb2_cpf` INT(11) NOT NULL,
  `tb2_especialidade` VARCHAR(100) NOT NULL,
  `tb2_nome` VARCHAR(100) NOT NULL,
  `tb2_nasc` DATE DEFAULT NULL,
  `tb2_sexo` ENUM('Masculino','Feminino','Outro') NOT NULL,
  `tb2_tel` VARCHAR(11) DEFAULT NULL,
  `tb2_cep` INT(8) NOT NULL,
  `tb2_email` VARCHAR(100) NOT NULL,
  `tb2_senha` VARCHAR(100) NOT NULL,
  `tb2_fotocert` VARCHAR(255),
  `tb2_status` ENUM('Ativo','Inativo') DEFAULT 'Ativo',
  `tb2_verificado` ENUM('Sim','Não') DEFAULT 'Não',
  PRIMARY KEY(`tb2_id`),
  UNIQUE KEY(`tb2_cpf`),
  UNIQUE KEY(`tb2_crm`),
  UNIQUE KEY(`tb2_email`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tb3_consultas` (
  `tb3_idconsul` INT NOT NULL AUTO_INCREMENT,
  `id_cliente` INT NOT NULL,
  `id_profissional` INT NOT NULL,
  `tb3_data` DATE DEFAULT NULL,
  `tb3_hora` TIME DEFAULT NULL,
  `tb3_motivo` VARCHAR(255) DEFAULT NULL,
  `tb3_status` ENUM('Pendente','Aceita','Recusada','Cancelado','Aguardando confirmação', 'Concluída', 'Arquivada') NOT NULL,
  `tb3_motcanc` VARCHAR(255),
  PRIMARY KEY(`tb3_idconsul`),
  CONSTRAINT `tb3_consultas_ifbk_1` FOREIGN KEY (`id_cliente`) REFERENCES `tb1_clientes` (`tb1_id`),
  CONSTRAINT `tb3_consultas_ifbk_2` FOREIGN KEY (`id_profissional`) REFERENCES `tb2_profissionais` (`tb2_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tb4_mensagens` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_cliente` INT,
  `id_profissional` INT,
  `mensagem` TEXT NOT NULL,
  `data_envio` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `enviado_por` ENUM('cliente', 'profissional') NOT NULL,
  `status_mensagem` ENUM('não entregue', 'entregue', 'lida') DEFAULT 'não entregue',
  PRIMARY KEY (`id`),
  CONSTRAINT `tb4_mensagens_ifbk_1` FOREIGN KEY (`id_cliente`) REFERENCES `tb1_clientes` (`tb1_id`),
  CONSTRAINT `tb4_mensagens_ifbk_2` FOREIGN KEY (`id_profissional`) REFERENCES `tb2_profissionais` (`tb2_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tb5_administradores` (
  `tb5_id` INT NOT NULL AUTO_INCREMENT,
  `tb5_nome` VARCHAR(100) NOT NULL,
  `tb5_email` VARCHAR(100) NOT NULL,
  `tb5_senha` VARCHAR(100) NOT NULL,
  `tb5_nivel_acesso` ENUM('admin', 'superadmin') DEFAULT 'admin',
  `tb5_data_criacao` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `tb5_ultimo_acesso` TIMESTAMP NULL,
  PRIMARY KEY (`tb5_id`),
  UNIQUE KEY (`tb5_email`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
