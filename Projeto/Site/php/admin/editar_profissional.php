<?php
session_start();
include '../conexoes/conexao.php';

// Verificar se o usuário está logado como administrador
if (!isset($_SESSION["admin_id"]) || $_SESSION["tipo"] !== "admin") {
    header("Location: ../conexoes/login.php");
    exit();
}

// Verificar se o ID do profissional foi fornecido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: gerenciar_profissionais.php");
    exit();
}

$profissional_id = $_GET['id'];
$mensagem = '';
$tipo_mensagem = '';

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

// Processar o formulário quando enviado
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Obter dados do formulário
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $cpf = trim($_POST['cpf']);
    $crm = trim($_POST['crm']);
    $especialidade = trim($_POST['especialidade']);
    $telefone = trim($_POST['telefone']);
    $cep = trim($_POST['cep']);
    $sexo = $_POST['sexo'];
    $data_nascimento = $_POST['data_nascimento'];
    $status = $_POST['status'];
    $verificado = $_POST['verificado'];
    
    // Validar dados
    $erros = [];
    
    if (empty($nome)) {
        $erros[] = "Nome é obrigatório";
    }
    
    if (empty($email)) {
        $erros[] = "Email é obrigatório";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros[] = "Email inválido";
    }
    
    if (empty($cpf)) {
        $erros[] = "CPF é obrigatório";
    }
    
    if (empty($crm)) {
        $erros[] = "CRM é obrigatório";
    }
    
    if (empty($especialidade)) {
        $erros[] = "Especialidade é obrigatória";
    }
    
    // Verificar se o email já está em uso por outro profissional
    $sql = "SELECT ga4_2_id FROM ga4_2_profissionais WHERE ga4_2_email = ? AND ga4_2_id != ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $email, $profissional_id);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $erros[] = "Este email já está sendo usado por outro profissional";
    }
    $stmt->close();
    
    // Verificar se o CPF já está em uso por outro profissional
    $sql = "SELECT ga4_2_id FROM ga4_2_profissionais WHERE ga4_2_cpf = ? AND ga4_2_id != ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $cpf, $profissional_id);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $erros[] = "Este CPF já está sendo usado por outro profissional";
    }
    $stmt->close();
    
    // Verificar se o CRM já está em uso por outro profissional
    $sql = "SELECT ga4_2_id FROM ga4_2_profissionais WHERE ga4_2_crm = ? AND ga4_2_id != ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $crm, $profissional_id);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $erros[] = "Este CRM já está sendo usado por outro profissional";
    }
    $stmt->close();
    
    // Se não houver erros, atualizar o profissional
    if (empty($erros)) {
        $sql = "UPDATE ga4_2_profissionais SET 
                ga4_2_nome = ?, 
                ga4_2_email = ?, 
                ga4_2_cpf = ?, 
                ga4_2_crm = ?,
                ga4_2_especialidade = ?,
                ga4_2_tel = ?, 
                ga4_2_cep = ?, 
                ga4_2_sexo = ?, 
                ga4_2_nasc = ?,
                ga4_2_status = ?,
                ga4_2_verificado = ?
                WHERE ga4_2_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssssssi", $nome, $email, $cpf, $crm, $especialidade, $telefone, $cep, $sexo, $data_nascimento, $status, $verificado, $profissional_id);
        
        if ($stmt->execute()) {
            $mensagem = "Profissional atualizado com sucesso!";
            $tipo_mensagem = "success";
        } else {
            $mensagem = "Erro ao atualizar profissional: " . $conn->error;
            $tipo_mensagem = "error";
        }
        
        $stmt->close();
    } else {
        $mensagem = "Erros encontrados:<br>" . implode("<br>", $erros);
        $tipo_mensagem = "error";
    }
}

// Obter informações atuais do profissional
$sql = "SELECT ga4_2_crm, ga4_2_cpf, ga4_2_especialidade, ga4_2_nome, ga4_2_nasc, ga4_2_sexo, ga4_2_tel, ga4_2_cep, ga4_2_email, ga4_2_status, ga4_2_verificado
        FROM ga4_2_profissionais 
        WHERE ga4_2_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $profissional_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Profissional não encontrado
    header("Location: gerenciar_profissionais.php");
    exit();
}

$profissional = $result->fetch_assoc();
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Profissional - <?php echo htmlspecialchars($config_valor[1]); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../../style/profissionais.css">
    <link rel="stylesheet" href="../../style/admin.css">
    <link rel="icon" type="image/png" href="../../midia/logo.png">
</head>
<body>
    <main class="container">
        <div class="header">
            <div class="header-title">
                <h1><?php echo htmlspecialchars($config_valor[1]); ?></h1>
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

        <div class="content-header">
            <h1><i class="fas fa-user-edit"></i> Editar Profissional</h1>
            <p>Atualize as informações do profissional</p>
        </div>

        <div class="admin-actions" style="margin-bottom: 20px;">
            <a href="gerenciar_profissionais.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Voltar para Lista de Profissionais
            </a>
            <a href="visualizar_profissional.php?id=<?php echo $profissional_id; ?>" class="btn btn-outline">
                <i class="fas fa-eye"></i> Visualizar Profissional
            </a>
        </div>

        <?php if (!empty($mensagem)): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?>">
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $profissional_id); ?>">
                <div class="form-header">
                    <div class="profissional-header-avatar">
                        <?php echo substr($profissional['ga4_2_nome'], 0, 1); ?>
                    </div>
                    <h2>Editar <?php echo htmlspecialchars($profissional['ga4_2_nome']); ?></h2>
                </div>

                <div class="form-section">
                    <h3><i class="fas fa-info-circle"></i> Informações Básicas</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nome">Nome Completo*</label>
                            <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($profissional['ga4_2_nome']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email*</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($profissional['ga4_2_email']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="cpf">CPF*</label>
                            <input type="text" id="cpf" name="cpf" value="<?php echo htmlspecialchars($profissional['ga4_2_cpf']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="telefone">Telefone</label>
                            <input type="text" id="telefone" name="telefone" value="<?php echo htmlspecialchars($profissional['ga4_2_tel']); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3><i class="fas fa-user-md"></i> Informações Profissionais</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="crm">CRM*</label>
                            <input type="text" id="crm" name="crm" value="<?php echo htmlspecialchars($profissional['ga4_2_crm']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="especialidade">Especialidade*</label>
                            <input type="text" id="especialidade" name="especialidade" value="<?php echo htmlspecialchars($profissional['ga4_2_especialidade']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="Ativo" <?php echo $profissional['ga4_2_status'] === 'Ativo' ? 'selected' : ''; ?>>Ativo</option>
                                <option value="Inativo" <?php echo $profissional['ga4_2_status'] === 'Inativo' ? 'selected' : ''; ?>>Inativo</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="verificado">Verificado</label>
                            <select id="verificado" name="verificado">
                                <option value="Sim" <?php echo $profissional['ga4_2_verificado'] === 'Sim' ? 'selected' : ''; ?>>Sim</option>
                                <option value="Não" <?php echo $profissional['ga4_2_verificado'] === 'Não' ? 'selected' : ''; ?>>Não</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3><i class="fas fa-user"></i> Informações Pessoais</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="data_nascimento">Data de Nascimento</label>
                            <input type="date" id="data_nascimento" name="data_nascimento" value="<?php echo $profissional['ga4_2_nasc']; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="sexo">Sexo</label>
                            <select id="sexo" name="sexo">
                                <option value="Masculino" <?php echo $profissional['ga4_2_sexo'] === 'Masculino' ? 'selected' : ''; ?>>Masculino</option>
                                <option value="Feminino" <?php echo $profissional['ga4_2_sexo'] === 'Feminino' ? 'selected' : ''; ?>>Feminino</option>
                                <option value="Outro" <?php echo $profissional['ga4_2_sexo'] === 'Outro' ? 'selected' : ''; ?>>Outro</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="cep">CEP</label>
                            <input type="text" id="cep" name="cep" value="<?php echo htmlspecialchars($profissional['ga4_2_cep']); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Salvar Alterações
                    </button>
                    <a href="visualizar_profissional.php?id=<?php echo $profissional_id; ?>" class="btn btn-outline">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>

        <footer class="footer-home">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($config_valor[1]); ?> - Todos os direitos reservados</p>
        </footer>
    </main>

    <style>
        /* Estilos específicos para editar_profissional.php */
        .form-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .form-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .profissional-header-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: var(--admin-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
        }
        
        .form-header h2 {
            margin: 0;
            color: var(--text-color);
            font-size: 1.5rem;
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .form-section h3 {
            font-size: 1.1rem;
            color: var(--text-color);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
        }
        
        .form-section h3 i {
            margin-right: 8px;
            color: var(--admin-primary);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--text-color);
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--admin-primary);
            outline: none;
            box-shadow: 0 0 0 2px rgba(var(--admin-primary-rgb), 0.1);
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }
        
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .form-actions button,
            .form-actions a {
                width: 100%;
                text-align: center;
            }
        }
    </style>

    <script>
        // Máscara para CPF
        document.getElementById('cpf').addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 11) {
                value = value.slice(0, 11);
            }
            
            if (value.length > 9) {
                value = value.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})$/, '$1.$2.$3-$4');
            } else if (value.length > 6) {
                value = value.replace(/^(\d{3})(\d{3})(\d{0,3})/, '$1.$2.$3');
            } else if (value.length > 3) {
                value = value.replace(/^(\d{3})(\d{0,3})/, '$1.$2');
            }
            
            e.target.value = value;
        });
        
        // Máscara para CRM
        document.getElementById('crm').addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, '');
            e.target.value = value;
        });
        
        // Máscara para telefone
        document.getElementById('telefone').addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 11) {
                value = value.slice(0, 11);
            }
            
            if (value.length > 10) {
                value = value.replace(/^(\d{2})(\d{5})(\d{4})$/, '($1) $2-$3');
            } else if (value.length > 6) {
                value = value.replace(/^(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
            } else if (value.length > 2) {
                value = value.replace(/^(\d{2})(\d{0,5})/, '($1) $2');
            }
            
            e.target.value = value;
        });
        
        // Máscara para CEP
        document.getElementById('cep').addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 8) {
                value = value.slice(0, 8);
            }
            
            if (value.length > 5) {
                value = value.replace(/^(\d{5})(\d{0,3})/, '$1-$2');
            }
            
            e.target.value = value;
        });
    </script>
</body>
</html>
