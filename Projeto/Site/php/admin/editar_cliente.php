<?php
session_start();
include '../conexoes/conexao.php';

// Verificar se o usuário está logado como administrador
if (!isset($_SESSION["admin_id"]) || $_SESSION["tipo"] !== "admin") {
    header("Location: ../conexoes/login.php");
    exit();
}

// Verificar se o ID do cliente foi fornecido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: gerenciar_clientes.php");
    exit();
}

$cliente_id = $_GET['id'];
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
    $telefone = trim($_POST['telefone']);
    $cep = trim($_POST['cep']);
    $sexo = $_POST['sexo'];
    $data_nascimento = $_POST['data_nascimento'];
    
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
    
    // Verificar se o email já está em uso por outro cliente
    $sql = "SELECT ga4_1_id FROM ga4_1_clientes WHERE ga4_1_email = ? AND ga4_1_id != ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $email, $cliente_id);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $erros[] = "Este email já está sendo usado por outro cliente";
    }
    $stmt->close();
    
    // Verificar se o CPF já está em uso por outro cliente
    $sql = "SELECT ga4_1_id FROM ga4_1_clientes WHERE ga4_1_cpf = ? AND ga4_1_id != ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $cpf, $cliente_id);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $erros[] = "Este CPF já está sendo usado por outro cliente";
    }
    $stmt->close();
    
    // Se não houver erros, atualizar o cliente
    if (empty($erros)) {
        $sql = "UPDATE ga4_1_clientes SET 
                ga4_1_nome = ?, 
                ga4_1_email = ?, 
                ga4_1_cpf = ?, 
                ga4_1_tel = ?, 
                ga4_1_cep = ?, 
                ga4_1_sexo = ?, 
                ga4_1_nasc = ? 
                WHERE ga4_1_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssi", $nome, $email, $cpf, $telefone, $cep, $sexo, $data_nascimento, $cliente_id);
        
        if ($stmt->execute()) {
            $mensagem = "Cliente atualizado com sucesso!";
            $tipo_mensagem = "success";
        } else {
            $mensagem = "Erro ao atualizar cliente: " . $conn->error;
            $tipo_mensagem = "error";
        }
        
        $stmt->close();
    } else {
        $mensagem = "Erros encontrados:<br>" . implode("<br>", $erros);
        $tipo_mensagem = "error";
    }
}

// Obter informações atuais do cliente
$sql = "SELECT ga4_1_cpf, ga4_1_nome, ga4_1_nasc, ga4_1_sexo, ga4_1_tel, ga4_1_cep, ga4_1_email
        FROM ga4_1_clientes 
        WHERE ga4_1_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $cliente_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Cliente não encontrado
    header("Location: gerenciar_clientes.php");
    exit();
}

$cliente = $result->fetch_assoc();
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Cliente - <?php echo htmlspecialchars($config_valor[1]); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../../style/profissionais.css">
    <link rel="stylesheet" href="../../style/admin.css">
    <link rel="icon" type="image/png" href="../../midia/logo.png">
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

    <main class="container">
        <div class="content-header">
            <h1><i class="fas fa-user-edit"></i> Editar Cliente</h1>
            <p>Atualize as informações do cliente</p>
        </div>

        <div class="admin-actions" style="margin-bottom: 20px;">
            <a href="gerenciar_clientes.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Voltar para Lista de Clientes
            </a>
        </div>

        <?php if (!empty($mensagem)): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?>">
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $cliente_id); ?>">
                <div class="form-header">
                    <div class="cliente-header-avatar">
                        <?php echo substr($cliente['ga4_1_nome'], 0, 1); ?>
                    </div>
                    <h2>Editar <?php echo htmlspecialchars($cliente['ga4_1_nome']); ?></h2>
                </div>

                <div class="form-section">
                    <h3><i class="fas fa-info-circle"></i> Informações Básicas</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nome">Nome Completo*</label>
                            <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($cliente['ga4_1_nome']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email*</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($cliente['ga4_1_email']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="cpf">CPF*</label>
                            <input type="text" id="cpf" name="cpf" value="<?php echo htmlspecialchars($cliente['ga4_1_cpf']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="telefone">Telefone</label>
                            <input type="text" id="telefone" name="telefone" value="<?php echo htmlspecialchars($cliente['ga4_1_tel']); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3><i class="fas fa-user"></i> Informações Pessoais</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="data_nascimento">Data de Nascimento</label>
                            <input type="date" id="data_nascimento" name="data_nascimento" value="<?php echo $cliente['ga4_1_nasc']; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="sexo">Sexo</label>
                            <select id="sexo" name="sexo">
                                <option value="Masculino" <?php echo $cliente['ga4_1_sexo'] === 'Masculino' ? 'selected' : ''; ?>>Masculino</option>
                                <option value="Feminino" <?php echo $cliente['ga4_1_sexo'] === 'Feminino' ? 'selected' : ''; ?>>Feminino</option>
                                <option value="Outro" <?php echo $cliente['ga4_1_sexo'] === 'Outro' ? 'selected' : ''; ?>>Outro</option>
                                <option value="Prefiro não informar" <?php echo $cliente['ga4_1_sexo'] === 'Prefiro não informar' ? 'selected' : ''; ?>>Prefiro não informar</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="cep">CEP</label>
                            <input type="text" id="cep" name="cep" value="<?php echo htmlspecialchars($cliente['ga4_1_cep']); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Salvar Alterações
                    </button>
                    <a href="gerenciar_clientes.php" class="btn btn-outline">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </form>
        </main>

        <footer class="footer-home">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($config_valor[1]); ?> - Todos os direitos reservados</p>
        </footer>
    </main>

    <style>
        /* Estilos específicos para editar_cliente.php */
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
        
        .cliente-header-avatar {
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
