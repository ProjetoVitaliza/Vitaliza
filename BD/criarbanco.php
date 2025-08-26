<?php
// Configurações de conexão
$host = "localhost";
$username = "root";
$password = "";

// Inicializa variáveis para mensagens
$message = "";
$error = "";

// Função para executar o script SQL
function executarScript() {
    global $host, $username, $password, $message, $error;
    
    try {
        // Conecta ao servidor MySQL sem selecionar um banco de dados
        $conn = new mysqli($host, $username, $password);
        
        if ($conn->connect_error) {
            throw new Exception("Falha na conexão: " . $conn->connect_error);
        }
        
        // Verifica se o banco de dados existe e o exclui
        $conn->query("DROP DATABASE IF EXISTS `ga4_vitaliza`");
        
        // Cria o banco de dados
        if (!$conn->query("CREATE DATABASE `ga4_vitaliza`")) {
            throw new Exception("Erro ao criar banco de dados: " . $conn->error);
        }
        
        // Seleciona o banco de dados
        $conn->select_db("ga4_vitaliza");
        
        // Cria as tabelas
        $sql = "
        CREATE TABLE IF NOT EXISTS `ga4_1_clientes` (
          `ga4_1_id` INT NOT NULL AUTO_INCREMENT,
          `ga4_1_cpf` INT(11) NOT NULL,
          `ga4_1_nome` VARCHAR(100) NOT NULL,
          `ga4_1_nasc` DATE DEFAULT NULL,
          `ga4_1_sexo` ENUM('Masculino','Feminino','Outro') NOT NULL,
          `ga4_1_tel` VARCHAR(15) DEFAULT NULL,
          `ga4_1_cep` INT(8) NOT NULL,
          `ga4_1_email` VARCHAR(100) NOT NULL,
          `ga4_1_senha` VARCHAR(255) NOT NULL,
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
          `ga4_2_tel` VARCHAR(15) DEFAULT NULL,
          `ga4_2_cep` INT(8) NOT NULL,
          `ga4_2_email` VARCHAR(100) NOT NULL,
          `ga4_2_senha` VARCHAR(255) NOT NULL,
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
          `id_cliente` INT NOT NULL,
          `id_profissional` INT NOT NULL,
          `ga4_3_data` DATE DEFAULT NULL,
          `ga4_3_hora` TIME DEFAULT NULL,
          `ga4_3_motivo` VARCHAR(255) DEFAULT NULL,
          `ga4_3_status` ENUM('Pendente','Aceita','Recusada','Cancelado','Aguardando confirmação', 'Concluída', 'Arquivada') NOT NULL,
          `ga4_3_motcanc` VARCHAR(255),
          PRIMARY KEY(`ga4_3_idconsul`),
          CONSTRAINT `ga4_3_consultas_ifbk_1` FOREIGN KEY (`id_cliente`) REFERENCES `ga4_1_clientes` (`ga4_1_id`),
          CONSTRAINT `ga4_3_consultas_ifbk_2` FOREIGN KEY (`id_profissional`) REFERENCES `ga4_2_profissionais` (`ga4_2_id`)
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
          `ga4_5_senha` VARCHAR(255) NOT NULL,
          `ga4_5_nivel_acesso` ENUM('admin', 'superadmin') NOT NULL DEFAULT 'admin',
          `ga4_5_data_criacao` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          `ga4_5_ultimo_acesso` TIMESTAMP NULL,
          PRIMARY KEY (`ga4_5_id`),
          UNIQUE KEY (`ga4_5_email`)
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

        CREATE TABLE IF NOT EXISTS `ga4_6_configuracoes` (
            `ga4_6_config_id` INT NOT NULL AUTO_INCREMENT,
            `ga4_6_config_nome` VARCHAR(100) NOT NULL,
            `ga4_6_config_valor` TEXT NOT NULL,
            `ga4_6_config_descricao` TEXT,
            `ga4_6_config_tipo` VARCHAR(50) NOT NULL,
            `ga4_6_config_grupo` VARCHAR(50) NOT NULL,
            `ga4_6_config_data_modificacao` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `ga4_6_config_modificado_por` INT,
            PRIMARY KEY (`ga4_6_config_id`),
            UNIQUE KEY (`ga4_6_config_nome`),
            CONSTRAINT `ga4_6_configuracoes_ifbk_1` FOREIGN KEY (`ga4_6_config_modificado_por`) REFERENCES `ga4_5_administradores` (`ga4_5_id`)
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ";
        
        // Executa o script SQL para criar as tabelas
        if (!$conn->multi_query($sql)) {
            throw new Exception("Erro ao criar tabelas: " . $conn->error);
        }
        
        // Aguarda a conclusão de todas as consultas
        while ($conn->more_results() && $conn->next_result()) {
            // Consome os resultados para liberar a conexão
        }
        
        // Insere o administrador padrão
        $admin_nome = "Admin";
        $admin_email = "admin@admin";
        $admin_senha = password_hash("adminadmin", PASSWORD_DEFAULT); // Hash da senha
        
        $sql = "INSERT INTO `ga4_5_administradores` 
               (`ga4_5_nome`, `ga4_5_email`, `ga4_5_senha`, `ga4_5_nivel_acesso`) 
               VALUES ('$admin_nome', '$admin_email', '$admin_senha', 'superadmin')";
        
        if (!$conn->query($sql)) {
            throw new Exception("Erro ao inserir administrador: " . $conn->error);
        }
        
        // Insere os 5 médicos
        for ($i = 1; $i <= 5; $i++) {
            $crm = 10000 + $i;
            $cpf = 10000000 + $i;
            $email = "medico{$i}@{$i}";
            $senha = password_hash((string)$i, PASSWORD_DEFAULT); // Hash da senha
            $nome = "Médico {$i}";
            $especialidade = "Especialidade {$i}";
            $nasc = "1980-01-0{$i}";
            $tel = "999999990{$i}";
            $cep = 10000000 + $i;
            $status = ($i <= 4) ? 'Ativo' : 'Inativo'; // O último médico será inativo
            $verificado = ($i <= 3) ? 'Sim' : 'Não'; // Os 3 primeiros médicos serão verificados
            
            // Corrigido: Inserção direta para evitar problemas com bind_param
            $sql = "INSERT INTO `ga4_2_profissionais` 
                   (`ga4_2_crm`, `ga4_2_cpf`, `ga4_2_especialidade`, `ga4_2_nome`, `ga4_2_nasc`, `ga4_2_sexo`, `ga4_2_tel`, `ga4_2_cep`, `ga4_2_email`, `ga4_2_senha`, `ga4_2_status`, `ga4_2_verificado`) 
                   VALUES ($crm, $cpf, '$especialidade', '$nome', '$nasc', 'Masculino', '$tel', $cep, '$email', '$senha', '$status', '$verificado')";
            
            if (!$conn->query($sql)) {
                throw new Exception("Erro ao inserir médico {$i}: " . $conn->error);
            }
        }
        
        // Insere os 5 clientes
        for ($i = 1; $i <= 5; $i++) {
            $cpf = 20000000 + $i;
            $email = "cliente{$i}@{$i}";
            $senha = password_hash((string)$i, PASSWORD_DEFAULT); // Hash da senha
            $nome = "Cliente {$i}";
            $nasc = "1990-01-0{$i}";
            $tel = "888888880{$i}";
            $cep = 20000000 + $i;
            $status = ($i <= 4) ? 'Ativo' : 'Inativo'; // O último cliente será inativo
            
            // Corrigido: Inserção direta para evitar problemas com bind_param
            $sql = "INSERT INTO `ga4_1_clientes` 
                   (`ga4_1_cpf`, `ga4_1_nome`, `ga4_1_nasc`, `ga4_1_sexo`, `ga4_1_tel`, `ga4_1_cep`, `ga4_1_email`, `ga4_1_senha`, `ga4_1_status`) 
                   VALUES ($cpf, '$nome', '$nasc', 'Feminino', '$tel', $cep, '$email', '$senha', '$status')";
            
            if (!$conn->query($sql)) {
                throw new Exception("Erro ao inserir cliente {$i}: " . $conn->error);
            }
        }

        // Inserir configurações padrão
        $configuracoes_padrao = [
            // Configurações gerais
            $c_nome_site = ['nome_site', 'Vitaliza', 'Nome do site', 'text', 'geral'],
            $c_descricao_site = ['descricao_site', 'Plataforma de agendamento de consultas de saúde', 'Descrição curta do site', 'textarea', 'geral'],
            $c_email_contato = ['email_contato', 'vitalizaprojeto@gmail.com', 'Email para contato', 'email', 'geral'],
            $c_telefone_contato = ['telefone_contato', '(12) 99783-2290', 'Telefone para contato', 'text', 'geral'],
            
            // Configurações de consultas
            $c_intervalo_consultas = ['intervalo_consultas', '30', 'Intervalo mínimo entre consultas (em minutos)', 'number', 'consultas'],
            $c_prazo_cancelamento = ['prazo_cancelamento', '24', 'Prazo para cancelamento de consultas (em horas)', 'number', 'consultas'],
            $c_horario_inicio = ['horario_inicio', '08:00', 'Horário de início das consultas', 'time', 'consultas'],
            $c_horario_fim = ['horario_fim', '18:00', 'Horário de término das consultas', 'time', 'consultas'],
            
            // Configurações de email
            $c_email_servidor = ['email_servidor', 'smtp.vitaliza.com', 'Servidor SMTP', 'text', 'email'],
            $c_email_porta = ['email_porta', '587', 'Porta do servidor SMTP', 'number', 'email'],
            $c_email_usuario = ['email_usuario', 'sistema@vitaliza.com', 'Usuário SMTP', 'text', 'email'],
            $c_email_senha = ['email_senha', '', 'Senha SMTP', 'password', 'email'],
            $c_email_seguranca = ['email_seguranca', 'tls', 'Protocolo de segurança (tls/ssl)', 'select', 'email'],
            $c_notificar_consulta = ['notificar_consulta', '1', 'Enviar notificação de novas consultas', 'boolean', 'email'],
            
            // Configurações de aparência
            $c_cor_primaria = ['cor_primaria', '#4f46e5', 'Cor primária do site', 'color', 'aparencia'],
            $c_cor_secundaria = ['cor_secundaria', '#10b981', 'Cor secundária do site', 'color', 'aparencia'],
            $c_mostrar_logo = ['mostrar_logo', '1', 'Mostrar logo no cabeçalho', 'boolean', 'aparencia'],
            $c_tema = ['tema', 'claro', 'Tema do painel administrativo', 'select', 'aparencia'],
            
            // Configurações de segurança
            $c_tentativas_login = ['tentativas_login', '5', 'Número máximo de tentativas de login', 'number', 'seguranca'],
            $c_tempo_bloqueio = ['tempo_bloqueio', '30', 'Tempo de bloqueio após tentativas (em minutos)', 'number', 'seguranca'],
            $c_tempo_sessao = ['tempo_sessao', '60', 'Tempo de inatividade da sessão (em minutos)', 'number', 'seguranca'],
            $c_verificacao_profissionais = ['verificacao_profissionais', '1', 'Exigir verificação de profissionais', 'boolean', 'seguranca']
        ];
        
        $sql = "INSERT INTO ga4_6_configuracoes (ga4_6_config_nome, ga4_6_config_valor, ga4_6_config_descricao, ga4_6_config_tipo, ga4_6_config_grupo) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        foreach ($configuracoes_padrao as $config) {
            $stmt->bind_param("sssss", $config[0], $config[1], $config[2], $config[3], $config[4]);
            $stmt->execute();
        }
        
        $stmt->close();
        
        $conn->close();
        
        $message = "Banco de dados 'ga4_vitaliza' criado com sucesso! Foram inseridos 5 médicos, 5 clientes e 1 administrador.";
        
    } catch (Exception $e) {
        $error = "Erro: " . $e->getMessage();
    }
}

// Verifica se o formulário foi enviado
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    executarScript();
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicializar Banco de Dados ga4_vitaliza</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
        }
        h1 {
            color: #3b82f6;
            text-align: center;
        }
        .container {
            background-color: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .info {
            background-color: #e0f2fe;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .success {
            background-color: #dcfce7;
            border-left: 4px solid #10b981;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .error {
            background-color: #fee2e2;
            border-left: 4px solid #ef4444;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        button {
            background-color: #3b82f6;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
        }
        button:hover {
            background-color: #2563eb;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #e2e8f0;
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #f1f5f9;
        }
        .status-active {
            color: #10b981;
            font-weight: bold;
        }
        .status-inactive {
            color: #ef4444;
            font-weight: bold;
        }
        .verified {
            color: #3b82f6;
            font-weight: bold;
        }
        .not-verified {
            color: #f59e0b;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h1>Inicializar Banco de Dados ga4_vitaliza</h1>
    
    <div class="container">
        <div class="info">
            <p><strong>Atenção:</strong> Este script irá:</p>
            <ol>
                <li>Excluir o banco de dados 'ga4_vitaliza' se ele já existir</li>
                <li>Criar um novo banco de dados 'ga4_vitaliza'</li>
                <li>Criar todas as tabelas necessárias</li>
                <li>Inserir 1 administrador, 5 médicos e 5 clientes para teste</li>
            </ol>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="success">
                <p><?php echo $message; ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="error">
                <p><?php echo $error; ?></p>
            </div>
        <?php endif; ?>
        
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <button type="submit">Inicializar Banco de Dados</button>
        </form>
        
        <?php if (!empty($message)): ?>
            <h2>Contas criadas:</h2>
            
            <h3>Administrador</h3>
            <table>
                <tr>
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Senha</th>
                    <th>Nível de Acesso</th>
                </tr>
                <tr>
                    <td>Admin</td>
                    <td>admin@admin</td>
                    <td>adminadmin</td>
                    <td>Super Admin</td>
                </tr>
            </table>
            
            <h3>Médicos</h3>
            <table>
                <tr>
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Senha</th>
                    <th>CRM</th>
                    <th>Especialidade</th>
                    <th>Status</th>
                    <th>Verificado</th>
                </tr>
                <?php for ($i = 1; $i <= 5; $i++): 
                    $status = ($i <= 4) ? 'Ativo' : 'Inativo';
                    $verificado = ($i <= 3) ? 'Sim' : 'Não';
                ?>
                <tr>
                    <td>Médico <?php echo $i; ?></td>
                    <td>medico<?php echo $i; ?>@<?php echo $i; ?></td>
                    <td><?php echo $i; ?></td>
                    <td><?php echo 10000 + $i; ?></td>
                    <td>Especialidade <?php echo $i; ?></td>
                    <td class="<?php echo $status === 'Ativo' ? 'status-active' : 'status-inactive'; ?>"><?php echo $status; ?></td>
                    <td class="<?php echo $verificado === 'Sim' ? 'verified' : 'not-verified'; ?>"><?php echo $verificado; ?></td>
                </tr>
                <?php endfor; ?>
            </table>
            
            <h3>Clientes</h3>
            <table>
                <tr>
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Senha</th>
                    <th>Status</th>
                </tr>
                <?php for ($i = 1; $i <= 5; $i++): 
                    $status = ($i <= 4) ? 'Ativo' : 'Inativo';
                ?>
                <tr>
                    <td>Cliente <?php echo $i; ?></td>
                    <td>cliente<?php echo $i; ?>@<?php echo $i; ?></td>
                    <td><?php echo $i; ?></td>
                    <td class="<?php echo $status === 'Ativo' ? 'status-active' : 'status-inactive'; ?>"><?php echo $status; ?></td>
                </tr>
                <?php endfor; ?>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
