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

// Inicializar variáveis para o formulário
$nome = $cpf = $email = $senha = $confirmar_senha = $data_nasc = $sexo = $telefone = $cep = "";
$erro_nome = $erro_cpf = $erro_email = $erro_senha = $erro_confirmar_senha = $erro_data_nasc = $erro_sexo = $erro_telefone = $erro_cep = "";
$mensagem_sucesso = $mensagem_erro = "";

// Processar o formulário quando enviado
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $validacao_ok = true;
    
    // Validar nome
    if (empty($_POST["nome"])) {
        $erro_nome = "Nome é obrigatório";
        $validacao_ok = false;
    } else {
        $nome = trim($_POST["nome"]);
        if (strlen($nome) < 3 || strlen($nome) > 100) {
            $erro_nome = "Nome deve ter entre 3 e 100 caracteres";
            $validacao_ok = false;
        }
    }
    
    // Validar CPF
    if (empty($_POST["cpf"])) {
        $erro_cpf = "CPF é obrigatório";
        $validacao_ok = false;
    } else {
        $cpf = preg_replace('/[^0-9]/', '', $_POST["cpf"]);
        if (strlen($cpf) != 11) {
            $erro_cpf = "CPF deve conter 11 dígitos";
            $validacao_ok = false;
        } else {
            // Verificar se CPF já existe
            $sql = "SELECT COUNT(*) FROM ga4_1_clientes WHERE ga4_1_cpf = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $cpf);
            $stmt->execute();
            $stmt->bind_result($cpf_count);
            $stmt->fetch();
            $stmt->close();
            
            if ($cpf_count > 0) {
                $erro_cpf = "Este CPF já está cadastrado";
                $validacao_ok = false;
            }
        }
    }
    
    // Validar email
    if (empty($_POST["email"])) {
        $erro_email = "Email é obrigatório";
        $validacao_ok = false;
    } else {
        $email = trim($_POST["email"]);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro_email = "Formato de email inválido";
            $validacao_ok = false;
        } else {
            // Verificar se email já existe
            $sql = "SELECT COUNT(*) FROM ga4_1_clientes WHERE ga4_1_email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->bind_result($email_count);
            $stmt->fetch();
            $stmt->close();
            
            if ($email_count > 0) {
                $erro_email = "Este email já está cadastrado";
                $validacao_ok = false;
            }
        }
    }
    
    // Validar senha
    if (empty($_POST["senha"])) {
        $erro_senha = "Senha é obrigatória";
        $validacao_ok = false;
    } else {
        $senha = $_POST["senha"];
        if (strlen($senha) < 6) {
            $erro_senha = "Senha deve ter pelo menos 6 caracteres";
            $validacao_ok = false;
        }
    }
    
    // Validar confirmação de senha
    if (empty($_POST["confirmar_senha"])) {
        $erro_confirmar_senha = "Confirmação de senha é obrigatória";
        $validacao_ok = false;
    } else {
        $confirmar_senha = $_POST["confirmar_senha"];
        if ($senha !== $confirmar_senha) {
            $erro_confirmar_senha = "As senhas não coincidem";
            $validacao_ok = false;
        }
    }
    
    // Validar data de nascimento
    if (!empty($_POST["data_nasc"])) {
        $data_nasc = $_POST["data_nasc"];
        $data_atual = date("Y-m-d");
        if ($data_nasc > $data_atual) {
            $erro_data_nasc = "Data de nascimento não pode ser no futuro";
            $validacao_ok = false;
        }
    }
    
    // Validar sexo
    if (empty($_POST["sexo"])) {
        $erro_sexo = "Sexo é obrigatório";
        $validacao_ok = false;
    } else {
        $sexo = $_POST["sexo"];
        if (!in_array($sexo, ["Masculino", "Feminino", "Outro"])) {
            $erro_sexo = "Opção de sexo inválida";
            $validacao_ok = false;
        }
    }
    
    // Validar telefone (opcional)
    if (!empty($_POST["telefone"])) {
        $telefone = preg_replace('/[^0-9]/', '', $_POST["telefone"]);
        if (strlen($telefone) < 10 || strlen($telefone) > 11) {
            $erro_telefone = "Telefone deve ter entre 10 e 11 dígitos";
            $validacao_ok = false;
        }
    }
    
    // Validar CEP
    if (empty($_POST["cep"])) {
        $erro_cep = "CEP é obrigatório";
        $validacao_ok = false;
    } else {
        $cep = preg_replace('/[^0-9]/', '', $_POST["cep"]);
        if (strlen($cep) != 8) {
            $erro_cep = "CEP deve conter 8 dígitos";
            $validacao_ok = false;
        }
    }
    
    // Se a validação passou, inserir o cliente no banco de dados
    if ($validacao_ok) {
        // Hash da senha
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        
        // Inserir cliente
        $sql = "INSERT INTO ga4_1_clientes (ga4_1_cpf, ga4_1_nome, ga4_1_nasc, ga4_1_sexo, ga4_1_tel, ga4_1_cep, ga4_1_email, ga4_1_senha) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssssss", $cpf, $nome, $data_nasc, $sexo, $telefone, $cep, $email, $senha_hash);
        
        if ($stmt->execute()) {
            $mensagem_sucesso = "Cliente cadastrado com sucesso!";
            // Limpar os campos do formulário
            $nome = $cpf = $email = $senha = $confirmar_senha = $data_nasc = $sexo = $telefone = $cep = "";
        } else {
            $mensagem_erro = "Erro ao cadastrar cliente: " . $conn->error;
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adicionar Cliente - <?php echo htmlspecialchars($config_valor[1]); ?></title>
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
            <h1><i class="fas fa-user-plus"></i> Adicionar Cliente</h1>
            <p>Cadastre um novo cliente no sistema</p>
        </div>


        <?php if (!empty($mensagem_sucesso)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $mensagem_sucesso; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($mensagem_erro)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $mensagem_erro; ?>
            </div>
        <?php endif; ?>

        <div class="admin-form">
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label for="nome">Nome Completo *</label>
                        <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($nome); ?>" required>
                        <?php if (!empty($erro_nome)): ?>
                            <span class="error-message"><?php echo $erro_nome; ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="cpf">CPF *</label>
                        <input type="text" id="cpf" name="cpf" value="<?php echo htmlspecialchars($cpf); ?>" placeholder="Apenas números" required maxlength="11">
                        <?php if (!empty($erro_cpf)): ?>
                            <span class="error-message"><?php echo $erro_cpf; ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                        <?php if (!empty($erro_email)): ?>
                            <span class="error-message"><?php echo $erro_email; ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="data_nasc">Data de Nascimento</label>
                        <input type="date" id="data_nasc" name="data_nasc" value="<?php echo htmlspecialchars($data_nasc); ?>">
                        <?php if (!empty($erro_data_nasc)): ?>
                            <span class="error-message"><?php echo $erro_data_nasc; ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="sexo">Sexo *</label>
                        <select id="sexo" name="sexo" required>
                            <option value="" disabled <?php echo empty($sexo) ? 'selected' : ''; ?>>Selecione</option>
                            <option value="Masculino" <?php echo $sexo === 'Masculino' ? 'selected' : ''; ?>>Masculino</option>
                            <option value="Feminino" <?php echo $sexo === 'Feminino' ? 'selected' : ''; ?>>Feminino</option>
                            <option value="Outro" <?php echo $sexo === 'Outro' ? 'selected' : ''; ?>>Outro</option>
                        </select>
                        <?php if (!empty($erro_sexo)): ?>
                            <span class="error-message"><?php echo $erro_sexo; ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="telefone">Telefone</label>
                        <input type="text" id="telefone" name="telefone" value="<?php echo htmlspecialchars($telefone); ?>" placeholder="DDD + número">
                        <?php if (!empty($erro_telefone)): ?>
                            <span class="error-message"><?php echo $erro_telefone; ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="cep">CEP *</label>
                        <input type="text" id="cep" name="cep" value="<?php echo htmlspecialchars($cep); ?>" placeholder="Apenas números" required maxlength="8">
                        <?php if (!empty($erro_cep)): ?>
                            <span class="error-message"><?php echo $erro_cep; ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="senha">Senha *</label>
                        <input type="password" id="senha" name="senha" required>
                        <?php if (!empty($erro_senha)): ?>
                            <span class="error-message"><?php echo $erro_senha; ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="confirmar_senha">Confirmar Senha *</label>
                        <input type="password" id="confirmar_senha" name="confirmar_senha" required>
                        <?php if (!empty($erro_confirmar_senha)): ?>
                            <span class="error-message"><?php echo $erro_confirmar_senha; ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <p class="form-help">* Campos obrigatórios</p>
                </div>

                <div class="form-buttons">
                    <button type="reset" class="btn btn-outline">
                        <i class="fas fa-undo"></i> Limpar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Cadastrar Cliente
                    </button>
                </div>
            </form>
        </div>

        <div class="admin-actions" style="margin-bottom: 20px;">
            <a href="gerenciar_clientes.php" class="btn btn-outline btn-voltar">
                <i class="fas fa-arrow-left"></i> Voltar para Lista de Clientes
            </a>
        </div>

        <footer class="footer-home">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($config_valor[1]); ?> - Todos os direitos reservados</p>
        </footer>
    </main>

    <style>
        /* Estilos específicos para o formulário de adicionar cliente */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
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
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 16px;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--admin-primary);
            outline: none;
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.2);
        }

        .error-message {
            display: block;
            color: var(--admin-danger);
            font-size: 12px;
            margin-top: 5px;
        }

        .form-help {
            font-size: 14px;
            color: var(--text-muted);
            margin-top: 5px;
        }

        .form-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
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
            e.target.value = value;
        });

        // Máscara para telefone
        document.getElementById('telefone').addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 11) {
                value = value.slice(0, 11);
            }
            e.target.value = value;
        });

        // Máscara para CEP
        document.getElementById('cep').addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 8) {
                value = value.slice(0, 8);
            }
            e.target.value = value;
        });
    </script>
</body>
</html>
