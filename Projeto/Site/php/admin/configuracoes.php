<?php
session_start();
include '../conexoes/conexao.php';

// Verificar se o usuário está logado como administrador
if (!isset($_SESSION["admin_id"]) || $_SESSION["tipo"] !== "admin") {
    header("Location: ../conexoes/login.php");
    exit();
}

// Obter informações do administrador
$admin_id = $_SESSION["admin_id"];
$conn = conectarBanco();

// Obter nome e nível de acesso do administrador
$sql = "SELECT ga4_5_nome, ga4_5_nivel_acesso FROM ga4_5_administradores WHERE ga4_5_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$stmt->bind_result($admin_nome, $nivel_acesso);
$stmt->fetch();
$stmt->close();

// Inicializar variáveis para mensagens
$mensagem_sucesso = "";
$mensagem_erro = "";

// Obter configurações atuais do sistema
$config = [];

// Carregar todas as configurações
$sql = "SELECT ga4_6_config_nome, ga4_6_config_valor, ga4_6_config_descricao, ga4_6_config_tipo, ga4_6_config_grupo FROM ga4_6_configuracoes ORDER BY ga4_6_config_grupo, ga4_6_config_nome";
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    $grupo = $row['ga4_6_config_grupo'];
    if (!isset($config[$grupo])) {
        $config[$grupo] = [];
    }
    $config[$grupo][$row['ga4_6_config_nome']] = [
        'valor' => $row['ga4_6_config_valor'],
        'descricao' => $row['ga4_6_config_descricao'],
        'tipo' => $row['ga4_6_config_tipo']
    ];
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["salvar_configuracoes"])) {

    $grupo_selecionado = $_POST["grupo_configuracao"] ?? 'geral';
    $erro_encontrado = false;
    
    foreach ($_POST as $chave => $valor) {
        // Pular campos que não são configurações
        if (in_array($chave, ['salvar_configuracoes', 'grupo_configuracao'])) {
            continue;
        }
        
        // Verificar se existe uma configuração correspondente no banco
        $sql = "SELECT ga4_6_config_id FROM ga4_6_configuracoes WHERE ga4_6_config_nome = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $chave);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Configuração existe, fazer update
            $sql_update = "UPDATE ga4_6_configuracoes SET ga4_6_config_valor = ?, ga4_6_config_modificado_por = ? WHERE ga4_6_config_nome = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("sis", $valor, $admin_id, $chave);
            
            if (!$stmt_update->execute()) {
                $mensagem_erro = "Erro ao salvar configuração '$chave': " . $conn->error;
                $erro_encontrado = true;
                break;
            }
            $stmt_update->close();
        }
        $stmt->close();
    }
    
    if (!$erro_encontrado) {
        $mensagem_sucesso = "Configurações salvas com sucesso!";
        
        // Recarregar configurações
        $config = [];
        $sql = "SELECT ga4_6_config_nome, ga4_6_config_valor, ga4_6_config_descricao, ga4_6_config_tipo, ga4_6_config_grupo FROM ga4_6_configuracoes ORDER BY ga4_6_config_grupo, ga4_6_config_nome";
        $result = $conn->query($sql);
        
        while ($row = $result->fetch_assoc()) {
            $grupo = $row['ga4_6_config_grupo'];
            if (!isset($config[$grupo])) {
                $config[$grupo] = [];
            }
            $config[$grupo][$row['ga4_6_config_nome']] = [
                'valor' => $row['ga4_6_config_valor'],
                'descricao' => $row['ga4_6_config_descricao'],
                'tipo' => $row['ga4_6_config_tipo']
            ];
        }
    }
}

// Processar a exportação de configurações
if (isset($_POST["exportar_configuracoes"])) {
    // Preparar os dados para exportação
    $sql = "SELECT ga4_6_config_nome, ga4_6_config_valor, ga4_6_config_descricao, ga4_6_config_tipo, ga4_6_config_grupo FROM ga4_6_configuracoes ORDER BY ga4_6_config_grupo, ga4_6_config_nome";
    $result = $conn->query($sql);
    
    $dados_exportacao = [];
    while ($row = $result->fetch_assoc()) {
        $dados_exportacao[] = $row;
    }
    
    // Converter para JSON
    $json = json_encode($dados_exportacao, JSON_PRETTY_PRINT);
    
    // Definir cabeçalhos para download
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="vitaliza_configuracoes_' . date('Y-m-d') . '.json"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    // Enviar o JSON
    echo $json;
    exit;
}

// Processar a importação de configurações
if (isset($_POST["importar_configuracoes"]) && isset($_FILES["arquivo_configuracoes"])) {
    $arquivo = $_FILES["arquivo_configuracoes"];
    
    // Verificar se é um arquivo JSON válido
    if ($arquivo["type"] !== "application/json") {
        $mensagem_erro = "O arquivo deve ser do tipo JSON";
    } else {
        // Ler o conteúdo do arquivo
        $conteudo = file_get_contents($arquivo["tmp_name"]);
        $dados_importacao = json_decode($conteudo, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $mensagem_erro = "O arquivo JSON é inválido";
        } else {
            // Iniciar transação
            $conn->begin_transaction();
            try {
                foreach ($dados_importacao as $item) {
                    $nome = $item['ga4_6_config_nome'];
                    $valor = $item['ga4_6_config_valor'];
                    $descricao = $item['ga4_6_config_descricao'];
                    $tipo = $item['ga4_6_config_tipo'];
                    $grupo = $item['ga4_6_config_grupo'];
                    
                    // Verificar se a configuração já existe
                    $sql = "SELECT ga4_6_config_id FROM ga4_6_configuracoes WHERE ga4_6_config_nome = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("s", $nome);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        // Atualizar configuração existente
                        $sql = "UPDATE ga4_6_configuracoes SET ga4_6_config_valor = ?, ga4_6_config_descricao = ?, ga4_6_config_tipo = ?, ga4_6_config_grupo = ?, ga4_6_config_modificado_por = ? WHERE ga4_6_config_nome = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("ssssss", $valor, $descricao, $tipo, $grupo, $admin_id, $nome);
                    } else {
                        // Inserir nova configuração
                        $sql = "INSERT INTO ga4_6_configuracoes (ga4_6_config_nome, ga4_6_config_valor, ga4_6_config_descricao, ga4_6_config_tipo, ga4_6_config_grupo, ga4_6_config_modificado_por) VALUES (?, ?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("sssssi", $nome, $valor, $descricao, $tipo, $grupo, $admin_id);
                    }
                    
                    $stmt->execute();
                }
                
                // Confirmar transação
                $conn->commit();
                $mensagem_sucesso = "Configurações importadas com sucesso!";
                
                // Recarregar configurações
                $config = [];
                $sql = "SELECT ga4_6_config_nome, ga4_6_config_valor, ga4_6_config_descricao, ga4_6_config_tipo, ga4_6_config_grupo FROM ga4_6_configuracoes ORDER BY ga4_6_config_grupo, ga4_6_config_nome";
                $result = $conn->query($sql);
                
                while ($row = $result->fetch_assoc()) {
                    $grupo = $row['ga4_6_config_grupo'];
                    if (!isset($config[$grupo])) {
                        $config[$grupo] = [];
                    }
                    $config[$grupo][$row['ga4_6_config_nome']] = [
                        'valor' => $row['ga4_6_config_valor'],
                        'descricao' => $row['ga4_6_config_descricao'],
                        'tipo' => $row['ga4_6_config_tipo']
                    ];
                }
                
            } catch (Exception $e) {
                // Reverter transação em caso de erro
                $conn->rollback();
                $mensagem_erro = "Erro ao importar configurações: " . $e->getMessage();
            }
        }
    }
}

// Processar o backup do banco de dados
if (isset($_POST["backup_banco"])) {
    // Diretório para salvar o backup
    $backup_dir = "../backups/";
    
    // Criar o diretório se não existir
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    
    // Nome do arquivo de backup
    $backup_file = $backup_dir . "vitaliza_backup_" . date("Y-m-d_H-i-s") . ".sql";
    
    // Obter informações de conexão do banco
    $db_name = "vitaliza"; // Altere conforme necessário
    $db_user = "root"; // Altere conforme necessário
    $db_pass = ""; // Altere conforme necessário
    $db_host = "localhost"; // Altere conforme necessário
    
    // Comando para fazer o backup (ajuste conforme seu ambiente)
    $command = "mysqldump --opt -h $db_host -u $db_user ";
    if (!empty($db_pass)) {
        $command .= "-p$db_pass ";
    }
    $command .= "$db_name > $backup_file";
    
    // Executar o comando
    $output = [];
    $return_var = 0;
    exec($command, $output, $return_var);
    
    if ($return_var === 0) {
        $mensagem_sucesso = "Backup do banco de dados criado com sucesso: " . basename($backup_file);
        
        // Opcionalmente, oferecer o arquivo para download
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($backup_file) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($backup_file));
        readfile($backup_file);
        exit;
    } else {
        $mensagem_erro = "Erro ao criar backup do banco de dados";
    }
}

// Grupo selecionado para exibição (padrão: geral)
$grupo_selecionado = $_GET['grupo'] ?? 'geral';

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações do Sistema - <?php echo htmlspecialchars($config_valor[1]); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../../style/profissionais.css">
    <link rel="stylesheet" href="../../style/admin.css">
    <link rel="icon" type="image/png" href="../../midia/logo.png">
    <style>
        .config-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .config-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 30px;
        }

        @media (max-width: 660px) {
            .config-header {
                flex-direction: column
            }
        }
        
        .config-header-title h1 {
            color: #4f46e5;
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
        }
        
        .config-user-info {
            display: flex;
            align-items: center;
        }
        
        .config-user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #4f46e5;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
            margin-right: 10px;
        }
        
        .config-admin-badge {
            background-color: #3b82f6;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-left: 10px;
        }
        
        .config-superadmin-badge {
            background-color: #7c3aed;
        }
        
        .config-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            border: none;
        }
        
        .config-btn-sm {
            padding: 4px 12px;
            font-size: 0.875rem;
        }
        
        .config-btn-outline {
            border: 1px solid #4f46e5;
            color: #4f46e5;
            background-color: transparent;
        }
        
        .config-btn-outline:hover {
            background-color: #f3f4f6;
        }
        
        .config-btn-primary {
            background-color: #4f46e5;
            color: white;
            border: 1px solid #4f46e5;
        }
        
        .config-btn-primary:hover {
            background-color: #4338ca;
        }
        
        .config-btn-success {
            background-color: #10b981;
            color: white;
            border: 1px solid #10b981;
        }
        
        .config-btn-success:hover {
            background-color: #059669;
        }
        
        .config-btn-danger {
            background-color: #ef4444;
            color: white;
            border: 1px solid #ef4444;
        }
        
        .config-btn-danger:hover {
            background-color: #dc2626;
        }
        
        .config-btn-warning {
            background-color: #f59e0b;
            color: white;
            border: 1px solid #f59e0b;
        }
        
        .config-btn-warning:hover {
            background-color: #d97706;
        }
        
        .config-content {
            padding: 20px 0;
        }
        
        .config-alert {
            padding: 12px 16px;
            margin-bottom: 20px;
            border-radius: 6px;
            display: flex;
            align-items: center;
        }
        
        .config-alert i {
            margin-right: 10px;
            font-size: 1.1rem;
        }
        
        .config-alert-success {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .config-alert-danger {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }
        
        .config-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .config-card-header {
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .config-card-header h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
        }
        
        .config-card-header h2 i {
            margin-right: 10px;
            color: #4f46e5;
        }
        
        .config-tabs {
            display: flex;
            border-bottom: 1px solid #e5e7eb;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            margin-bottom: 20px;
        }
        
        .config-tabs::-webkit-scrollbar {
            display: none;
        }
        
        .config-tab {
            padding: 15px 20px;
            white-space: nowrap;
            color: #6b7280;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .config-tab:hover {
            color: #4f46e5;
        }
        
        .config-tab.config-active {
            color: #4f46e5;
            font-weight: 600;
        }
        
        .config-tab.config-active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 2px;
            background-color: #4f46e5;
        }
        
        .config-tab i {
            margin-right: 8px;
        }
        
        .config-form {
            padding: 20px;
        }
        
        .config-section {
            margin-bottom: 30px;
        }
        
        .config-section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .config-section-title i {
            margin-right: 8px;
            color: #4f46e5;
        }
        
        .config-form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .config-form-group {
            flex: 1 0 100px;
            margin-bottom: 20px;
        }
        
        .config-label {
            display: block;
            font-weight: 500;
            margin-bottom: 6px;
            color: #374151;
        }
        
        .config-description {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 4px;
            margin-bottom: 8px;
        }
        
        .config-form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        
        .config-form-control:focus {
            border-color: #4f46e5;
            outline: none;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
        }
        
        .config-form-control.config-color {
            height: 42px;
            padding: 5px;
        }
        
        .config-toggle {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        
        .config-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .config-toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }
        
        .config-toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .config-toggle-slider {
            background-color: #4f46e5;
        }
        
        input:focus + .config-toggle-slider {
            box-shadow: 0 0 1px #4f46e5;
        }
        
        input:checked + .config-toggle-slider:before {
            transform: translateX(26px);
        }
        
        .config-toggle-label {
            display: flex;
            align-items: center;
        }
        
        .config-toggle {
            margin-right: 10px;
        }
        
        .config-form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
            border-top: 1px solid #e5e7eb;
            padding-top: 20px;
        }
        
        .config-divider {
            height: 1px;
            background-color: #e5e7eb;
            margin: 30px 0;
        }
        
        .config-footer {
            text-align: center;
            padding: 20px 0;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            margin-top: 40px;
        }
        
        .config-backup-restore {
            display: flex;
            gap: 20px;
            margin-top: 20px;
            padding: 20px;
            background-color: #f9fafb;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        
        .config-backup-item {
            flex: 1;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        }
        
        .config-backup-item h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: #1f2937;
            display: flex;
            align-items: center;
        }
        
        .config-backup-item h3 i {
            margin-right: 8px;
            color: #4f46e5;
        }
        
        .config-backup-item p {
            color: #6b7280;
            margin-bottom: 15px;
        }
        
        .config-file-input {
            display: flex;
            flex-direction: column;
            margin-bottom: 15px;
        }
        
        .config-file-input input[type="file"] {
            display: none;
        }
        
        .config-file-label {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f3f4f6;
            border: 1px dashed #d1d5db;
            border-radius: 6px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .config-file-label:hover {
            background-color: #e5e7eb;
        }
        
        .config-file-label i {
            font-size: 24px;
            margin-right: 10px;
            color: #6b7280;
        }
        
        .config-file-info {
            display: none;
            margin-top: 10px;
            padding: 10px;
            background-color: #f3f4f6;
            border-radius: 6px;
            font-size: 0.875rem;
        }
        
        @media (max-width: 768px) {
            .config-form-row {
                flex-direction: column;
                gap: 10px;
            }
            
            .config-backup-restore {
                flex-direction: column;
            }
        }
        
        .config-card-footer {
            padding: 15px 20px;
            background-color: #f9fafb;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .config-version {
            font-size: 0.875rem;
            color: #6b7280;
        }

        i {
            margin-right: 6px;
        }
    </style>
</head>
<body>

<header class="header">
    <nav class="navbar">
            <div class="logo">
                    <h1>
                        <a href="index.php">
                            <img class="img-logo" src="../../midia/logo.png" alt="Logo <?php echo htmlspecialchars($config_valor[1]); ?>" width="40" height="40"> 
                            <?php echo htmlspecialchars($config_valor[1]); ?>
                        </a>
                    </h1>
                </div>
            <div class="user-info">
                <div class="user-avatar"><?php echo substr($admin_nome, 0, 1); ?></div>
                <span><?php echo $admin_nome; ?></span>
                <span class="admin-badge <?php echo $nivel_acesso === 'superadmin' ? 'superadmin-badge' : ''; ?>">
                    <?php echo $nivel_acesso === 'superadmin' ? 'Super Admin' : 'Admin'; ?>
                </span>
                <a href="admin_home.php" class="btn btn-outline btn-sm" style="margin-left: 15px;">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <form method="post" action="../conexoes/logout.php" style="margin-left: 15px;">
                    <button type="submit" class="btn btn-outline btn-sm">
                        <i class="fas fa-sign-out-alt"></i> Sair
                    </button>
                </form>
            </div>
        </div>
    </nav>
</header> 
    <main class="config-container">
    <div class="content-header">
        <h1><i class="fas fa-calendar-check"></i> Configurações</h1>
        <p>Configure o sistema Vitaliza como administrador</p>
    </div>
        
        <section class="config-content">
            <?php if (!empty($mensagem_sucesso)): ?>
                <div class="config-alert config-alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $mensagem_sucesso; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($mensagem_erro)): ?>
                <div class="config-alert config-alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $mensagem_erro; ?>
                </div>
            <?php endif; ?>
            
            <div class="config-card">
                <div class="config-card-header">
                    <h2><i class="fas fa-sliders-h"></i> Painel de Configurações</h2>
                    <div>
                        <a href="admin_home.php" class="config-btn config-btn-outline config-btn-sm">
                            <i class="fas fa-arrow-left"></i> Voltar ao Dashboard
                        </a>
                    </div>
                </div>
                
                <div class="config-tabs">
                    <a href="?grupo=geral" class="config-tab <?php echo $grupo_selecionado === 'geral' ? 'config-active' : ''; ?>">
                        <i class="fas fa-globe"></i>Geral
                    </a>
                    <a href="?grupo=consultas" class="config-tab <?php echo $grupo_selecionado === 'consultas' ? 'config-active' : ''; ?>">
                        <i class="fas fa-calendar-check"></i>Consultas
                    </a>
                    <a href="?grupo=email" class="config-tab <?php echo $grupo_selecionado === 'email' ? 'config-active' : ''; ?>">
                        <i class="fas fa-envelope"></i>E-mail
                    </a>
                    <a href="?grupo=aparencia" class="config-tab <?php echo $grupo_selecionado === 'aparencia' ? 'config-active' : ''; ?>">
                        <i class="fas fa-paint-brush"></i>Aparência
                    </a>
                    <a href="?grupo=seguranca" class="config-tab <?php echo $grupo_selecionado === 'seguranca' ? 'config-active' : ''; ?>">
                        <i class="fas fa-shield-alt"></i>Segurança
                    </a>
                    <a href="?grupo=backup" class="config-tab <?php echo $grupo_selecionado === 'backup' ? 'config-active' : ''; ?>">
                        <i class="fas fa-database"></i>Backup e Restauração
                    </a>
                </div>
                
                <?php if ($grupo_selecionado !== 'backup'): ?>
                    <form method="post" class="config-form">
                        <input type="hidden" name="grupo_configuracao" value="<?php echo $grupo_selecionado; ?>">
                        
                        <?php if ($grupo_selecionado === 'geral'): ?>
                            <div class="config-section">
                                <h3 class="config-section-title"><i class="fas fa-info-circle"></i> Informações Básicas</h3>
                                <div class="config-form-row">
                                    <div class="config-form-group">
                                        <label class="config-label" for="nome_site">Nome do Site</label>
                                        <input type="text" id="nome_site" name="nome_site" class="config-form-control" 
                                               value="<?php echo htmlspecialchars($config['geral']['nome_site']['valor'] ?? ''); ?>">
                                        <p class="config-description">Nome exibido no site e em comunicações</p>
                                    </div>
                                    <div class="config-form-group">
                                        <label class="config-label" for="descricao_site">Descrição do Site</label>
                                        <textarea id="descricao_site" name="descricao_site" class="config-form-control" rows="3"><?php echo htmlspecialchars($config['geral']['descricao_site']['valor'] ?? ''); ?></textarea>
                                        <p class="config-description">Breve descrição utilizada em metatags</p>
                                    </div>
                                </div>
                                <div class="config-form-row">
                                    <div class="config-form-group">
                                        <label class="config-label" for="email_contato">E-mail de Contato</label>
                                        <input type="email" id="email_contato" name="email_contato" class="config-form-control" 
                                               value="<?php echo htmlspecialchars($config['geral']['email_contato']['valor'] ?? ''); ?>">
                                        <p class="config-description">E-mail principal para contato</p>
                                    </div>
                                    <div class="config-form-group">
                                        <label class="config-label" for="telefone_contato">Telefone de Contato</label>
                                        <input type="text" id="telefone_contato" name="telefone_contato" class="config-form-control" 
                                               value="<?php echo htmlspecialchars($config['geral']['telefone_contato']['valor'] ?? ''); ?>">
                                        <p class="config-description">Telefone principal para contato</p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($grupo_selecionado === 'consultas'): ?>
                            <div class="config-section">
                                <h3 class="config-section-title"><i class="fas fa-calendar-alt"></i> Configurações de Consultas</h3>
                                <div class="config-form-row">
                                    <div class="config-form-group">
                                        <label class="config-label" for="intervalo_consultas">Intervalo Entre Consultas (minutos)</label>
                                        <input type="number" id="intervalo_consultas" name="intervalo_consultas" class="config-form-control" 
                                               value="<?php echo htmlspecialchars($config['consultas']['intervalo_consultas']['valor'] ?? '30'); ?>" min="5" step="5">
                                        <p class="config-description">Intervalo mínimo entre consultas consecutivas</p>
                                    </div>
                                    <div class="config-form-group">
                                        <label class="config-label" for="prazo_cancelamento">Prazo para Cancelamento (horas)</label>
                                        <input type="number" id="prazo_cancelamento" name="prazo_cancelamento" class="config-form-control" 
                                               value="<?php echo htmlspecialchars($config['consultas']['prazo_cancelamento']['valor'] ?? '24'); ?>" min="1">
                                        <p class="config-description">Antecedência mínima para cancelamento de consultas</p>
                                    </div>
                                </div>
                                <div class="config-form-row">
                                    <div class="config-form-group">
                                        <label class="config-label" for="horario_inicio">Horário de Início</label>
                                        <input type="time" id="horario_inicio" name="horario_inicio" class="config-form-control" 
                                               value="<?php echo htmlspecialchars($config['consultas']['horario_inicio']['valor'] ?? '08:00'); ?>">
                                        <p class="config-description">Horário de início para agendamentos</p>
                                    </div>
                                    <div class="config-form-group">
                                        <label class="config-label" for="horario_fim">Horário de Término</label>
                                        <input type="time" id="horario_fim" name="horario_fim" class="config-form-control" 
                                               value="<?php echo htmlspecialchars($config['consultas']['horario_fim']['valor'] ?? '18:00'); ?>">
                                        <p class="config-description">Horário de término para agendamentos</p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($grupo_selecionado === 'email'): ?>
                            <div class="config-section">
                                <h3 class="config-section-title"><i class="fas fa-envelope"></i> Configurações de E-mail</h3>
                                <div class="config-form-row">
                                    <div class="config-form-group">
                                        <label class="config-label" for="email_servidor">Servidor SMTP</label>
                                        <input type="text" id="email_servidor" name="email_servidor" class="config-form-control" 
                                               value="<?php echo htmlspecialchars($config['email']['email_servidor']['valor'] ?? ''); ?>">
                                        <p class="config-description">Servidor para envio de e-mails</p>
                                    </div>
                                    <div class="config-form-group">
                                        <label class="config-label" for="email_porta">Porta SMTP</label>
                                        <input type="number" id="email_porta" name="email_porta" class="config-form-control" 
                                               value="<?php echo htmlspecialchars($config['email']['email_porta']['valor'] ?? '587'); ?>">
                                        <p class="config-description">Porta para conexão com o servidor SMTP</p>
                                    </div>
                                </div>
                                <div class="config-form-row">
                                    <div class="config-form-group">
                                        <label class="config-label" for="email_usuario">Usuário SMTP</label>
                                        <input type="text" id="email_usuario" name="email_usuario" class="config-form-control" 
                                               value="<?php echo htmlspecialchars($config['email']['email_usuario']['valor'] ?? ''); ?>">
                                        <p class="config-description">Usuário para autenticação SMTP</p>
                                    </div>
                                    <div class="config-form-group">
                                        <label class="config-label" for="email_senha">Senha SMTP</label>
                                        <input type="password" id="email_senha" name="email_senha" class="config-form-control" 
                                               value="<?php echo htmlspecialchars($config['email']['email_senha']['valor'] ?? ''); ?>">
                                        <p class="config-description">Senha para autenticação SMTP</p>
                                    </div>
                                </div>
                                <div class="config-form-row">
                                    <div class="config-form-group">
                                        <label class="config-label" for="email_seguranca">Protocolo de Segurança</label>
                                        <select id="email_seguranca" name="email_seguranca" class="config-form-control">
                                            <option value="tls" <?php echo ($config['email']['email_seguranca']['valor'] ?? '') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                            <option value="ssl" <?php echo ($config['email']['email_seguranca']['valor'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                            <option value="none" <?php echo ($config['email']['email_seguranca']['valor'] ?? '') === 'none' ? 'selected' : ''; ?>>Nenhum</option>
                                        </select>
                                        <p class="config-description">Protocolo de segurança para conexão SMTP</p>
                                    </div>
                                    <div class="config-form-group">
                                        <label class="config-label">Notificações</label>
                                        <div class="config-toggle-label">
                                            <label class="config-toggle">
                                                <input type="checkbox" name="notificar_consulta" value="1" 
                                                       <?php echo ($config['email']['notificar_consulta']['valor'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                                <span class="config-toggle-slider"></span>
                                            </label>
                                            <span>Enviar notificações de novas consultas</span>
                                        </div>
                                        <p class="config-description">Habilita o envio de e-mails para novas consultas</p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($grupo_selecionado === 'aparencia'): ?>
                            <div class="config-section">
                                <h3 class="config-section-title"><i class="fas fa-paint-brush"></i> Configurações de Aparência</h3>
                                <div class="config-form-row">
                                    <div class="config-form-group">
                                        <label class="config-label" for="cor_primaria">Cor Primária</label>
                                        <input type="color" id="cor_primaria" name="cor_primaria" class="config-form-control config-color" 
                                               value="<?php echo htmlspecialchars($config['aparencia']['cor_primaria']['valor'] ?? '#4f46e5'); ?>">
                                        <p class="config-description">Cor principal do site e elementos de destaque</p>
                                    </div>
                                    <div class="config-form-group">
                                        <label class="config-label" for="cor_secundaria">Cor Secundária</label>
                                        <input type="color" id="cor_secundaria" name="cor_secundaria" class="config-form-control config-color" 
                                               value="<?php echo htmlspecialchars($config['aparencia']['cor_secundaria']['valor'] ?? '#10b981'); ?>">
                                        <p class="config-description">Cor secundária para elementos de apoio</p>
                                    </div>
                                </div>
                                <div class="config-form-row">
                                    <div class="config-form-group">
                                        <label class="config-label">Exibir Logo</label>
                                        <div class="config-toggle-label">
                                            <label class="config-toggle">
                                                <input type="checkbox" name="mostrar_logo" value="1" 
                                                       <?php echo ($config['aparencia']['mostrar_logo']['valor'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                                <span class="config-toggle-slider"></span>
                                            </label>
                                            <span>Mostrar logo no cabeçalho</span>
                                        </div>
                                        <p class="config-description">Exibe a logo no cabeçalho do site</p>
                                    </div>
                                    <div class="config-form-group">
                                        <label class="config-label" for="tema">Tema do Painel</label>
                                        <select id="tema" name="tema" class="config-form-control">
                                            <option value="claro" <?php echo ($config['aparencia']['tema']['valor'] ?? '') === 'claro' ? 'selected' : ''; ?>>Claro</option>
                                            <option value="escuro" <?php echo ($config['aparencia']['tema']['valor'] ?? '') === 'escuro' ? 'selected' : ''; ?>>Escuro</option>
                                            <option value="sistema" <?php echo ($config['aparencia']['tema']['valor'] ?? '') === 'sistema' ? 'selected' : ''; ?>>Seguir Sistema</option>
                                        </select>
                                        <p class="config-description">Tema do painel administrativo</p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($grupo_selecionado === 'seguranca'): ?>
                            <div class="config-section">
                                <h3 class="config-section-title"><i class="fas fa-shield-alt"></i> Configurações de Segurança</h3>
                                <div class="config-form-row">
                                    <div class="config-form-group">
                                        <label class="config-label" for="tentativas_login">Tentativas de Login</label>
                                        <input type="number" id="tentativas_login" name="tentativas_login" class="config-form-control" 
                                               value="<?php echo htmlspecialchars($config['seguranca']['tentativas_login']['valor'] ?? '5'); ?>" min="1" max="10">
                                        <p class="config-description">Número máximo de tentativas de login antes do bloqueio</p>
                                    </div>
                                    <div class="config-form-group">
                                        <label class="config-label" for="tempo_bloqueio">Tempo de Bloqueio (minutos)</label>
                                        <input type="number" id="tempo_bloqueio" name="tempo_bloqueio" class="config-form-control" 
                                               value="<?php echo htmlspecialchars($config['seguranca']['tempo_bloqueio']['valor'] ?? '30'); ?>" min="5">
                                        <p class="config-description">Tempo de bloqueio após exceder tentativas de login</p>
                                    </div>
                                </div>
                                <div class="config-form-row">
                                    <div class="config-form-group">
                                        <label class="config-label" for="tempo_sessao">Tempo de Sessão (minutos)</label>
                                        <input type="number" id="tempo_sessao" name="tempo_sessao" class="config-form-control" 
                                               value="<?php echo htmlspecialchars($config['seguranca']['tempo_sessao']['valor'] ?? '60'); ?>" min="5">
                                        <p class="config-description">Tempo máximo de inatividade antes de encerrar a sessão</p>
                                    </div>
                                    <div class="config-form-group">
                                        <label class="config-label">Verificação de Profissionais</label>
                                        <div class="config-toggle-label">
                                            <label class="config-toggle">
                                                <input type="checkbox" name="verificacao_profissionais" value="1" 
                                                       <?php echo ($config['seguranca']['verificacao_profissionais']['valor'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                                <span class="config-toggle-slider"></span>
                                            </label>
                                            <span>Exigir verificação de profissionais</span>
                                        </div>
                                        <p class="config-description">Requer verificação de documentos de profissionais antes de permitir acesso</p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($grupo_selecionado !== 'backup'): ?>
                            <div class="config-form-actions">
                                <button type="reset" class="config-btn config-btn-outline">
                                    <i class="fas fa-undo"></i> Restaurar
                                </button>
                                <button type="submit" name="salvar_configuracoes" class="config-btn config-btn-primary">
                                    <i class="fas fa-save"></i> Salvar Configurações
                                </button>
                            </div>
                        <?php endif; ?>
                    </form>
                <?php else: ?>
                    <!-- Seção de Backup e Restauração -->
                    <div class="config-form">
                        <div class="config-section">
                            <h3 class="config-section-title"><i class="fas fa-database"></i> Backup e Restauração</h3>
                            <p>Gerencie backups e restaure seus dados para garantir a segurança das informações do sistema.</p>
                            
                            <div class="config-backup-restore">
                                <div class="config-backup-item">
                                    <h3><i class="fas fa-download"></i> Backup do Sistema</h3>
                                    <p>Crie um backup completo do banco de dados do sistema para armazenamento seguro.</p>
                                    <form method="post">
                                        <button type="submit" name="backup_banco" class="config-btn config-btn-primary">
                                            <i class="fas fa-database"></i> Criar Backup do Banco de Dados
                                        </button>
                                    </form>
                                </div>
                                
                                <div class="config-backup-item">
                                    <h3><i class="fas fa-cog"></i> Exportar Configurações</h3>
                                    <p>Exporte as configurações atuais do sistema para backup ou transferência.</p>
                                    <form method="post">
                                        <button type="submit" name="exportar_configuracoes" class="config-btn config-btn-outline">
                                            <i class="fas fa-file-export"></i> Exportar Configurações
                                        </button>
                                    </form>
                                </div>
                                
                                <div class="config-backup-item">
                                    <h3><i class="fas fa-upload"></i> Importar Configurações</h3>
                                    <p>Importe configurações de um arquivo de backup previamente exportado.</p>
                                    <form method="post" enctype="multipart/form-data">
                                        <div class="config-file-input">
                                            <label for="arquivo_configuracoes" class="config-file-label">
                                                <i class="fas fa-file-upload"></i>
                                                <span>Selecionar arquivo JSON</span>
                                            </label>
                                            <input type="file" id="arquivo_configuracoes" name="arquivo_configuracoes" accept=".json">
                                            <div id="arquivo-info" class="config-file-info"></div>
                                        </div>
                                        <button type="submit" name="importar_configuracoes" class="config-btn config-btn-warning">
                                            <i class="fas fa-file-import"></i> Importar Configurações
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="config-card-footer">
                    <div class="config-version">
                        <?php echo htmlspecialchars($config_valor[1]); ?> v1.0.0
                    </div>
                    <div>
                        <a href="#" class="config-btn config-btn-sm config-btn-outline">
                            <i class="fas fa-question-circle"></i> Ajuda
                        </a>
                    </div>
                </div>
            </div>
        </section>
        
        <footer class="config-footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($config_valor[1]); ?> - Todos os direitos reservados</p>
        </footer>
    </main>

    <?php
    // Atualizar checkbox
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["salvar_configuracoes"])) {
        if (!isset($_POST['verificacao_profissionais'])) {
            $_POST['verificacao_profissionais'] = 0;
        }

        if (!isset($_POST['mostrar_logo'])) {
            $_POST['mostrar_logo'] = 0;
        }
    }
    ?>
    
    <script>
        // Script para exibir o nome do arquivo selecionado
        document.addEventListener('DOMContentLoaded', function() {
            const inputArquivo = document.getElementById('arquivo_configuracoes');
            const arquivoInfo = document.getElementById('arquivo-info');
            
            if (inputArquivo && arquivoInfo) {
                inputArquivo.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        const arquivo = this.files[0];
                        arquivoInfo.textContent = `Arquivo selecionado: ${arquivo.name}`;
                        arquivoInfo.css.display = 'block';
                    } else {
                        arquivoInfo.css.display = 'none';
                    }
                });
            }
            
            // Alternar visualização da senha
            const camposSenha = document.querySelectorAll('input[type="password"]');
            camposSenha.forEach(function(campo) {
                const id = campo.id;
                const container = campo.parentElement;
                
                // Criar botão de toggle
                const toggleBtn = document.createElement('button');
                toggleBtn.type = 'button';
                toggleBtn.className = 'config-btn config-btn-sm config-btn-outline';
                toggleBtn.css.position = 'absolute';
                toggleBtn.css.right = '10px';
                toggleBtn.css.top = '40px';
                toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
                
                // Posicionar relativamente o container
                container.css.position = 'relative';
                
                // Adicionar botão ao container
                container.appendChild(toggleBtn);
                
                // Adicionar evento de clique
                toggleBtn.addEventListener('click', function() {
                    if (campo.type === 'password') {
                        campo.type = 'text';
                        toggleBtn.innerHTML = '<i class="fas fa-eye-slash"></i>';
                    } else {
                        campo.type = 'password';
                        toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
                    }
                });
            });
        });
    </script>
</body>
</html>