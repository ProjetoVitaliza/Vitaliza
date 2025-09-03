<?php
session_start();
include '../conexoes/conexao.php';

// Verificar se o usuário está logado como administrador
if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
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

// Definir se é superadmin
$is_superadmin = ($nivel_acesso === 'superadmin');

// Inicializar variáveis para o formulário
$nome = $email = $senha = $confirmar_senha = $nivel = "";
$erro_nome = $erro_email = $erro_senha = $erro_confirmar_senha = $erro_nivel = "";
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
    
    // Validar email
    if (empty($_POST["email"])) {
        $erro_email = "Email é obrigatório";
        $validacao_ok = false;
    } else {
        $email = trim($_POST["email"]);
        // Verificar se email já existe
        $sql = "SELECT COUNT(*) FROM ga4_5_administradores WHERE ga4_5_email = ?";
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
    
    // Validar senha
    if (empty($_POST["senha"])) {
        $erro_senha = "Senha é obrigatória";
        $validacao_ok = false;
    } else {
        $senha = $_POST["senha"];
    }
    
    // Validar nível de acesso
    if (empty($_POST["nivel_acesso"])) {
        $erro_nivel = "Nível de acesso é obrigatório";
        $validacao_ok = false;
    } else {
        $nivel = $_POST["nivel_acesso"];
        if (!in_array($nivel, ["admin", "superadmin"])) {
            $erro_nivel = "Nível de acesso inválido";
            $validacao_ok = false;
        }
        
        // Verificar se o admin atual tem permissão para adicionar o nível solicitado
        if (!$is_superadmin && $nivel === 'superadmin') {
            $erro_nivel = "Apenas Super Administradores podem adicionar outros Super Administradores";
            $validacao_ok = false;
        }
    }
    
    // Se a validação passou, inserir o administrador no banco de dados
    if ($validacao_ok) {
        // Hash da senha
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        
        // Inserir admin
        $sql = "INSERT INTO ga4_5_administradores (ga4_5_nome, ga4_5_email, ga4_5_senha, ga4_5_nivel_acesso) 
                VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $nome, $email, $senha_hash, $nivel);
        
        if ($stmt->execute()) {
            $mensagem_sucesso = "Administrador adicionado com sucesso!";
            // Limpar os campos do formulário
            $nome = $email = $senha = $confirmar_senha = $nivel = "";
        } else {
            $mensagem_erro = "Erro ao cadastrar administrador: " . $conn->error;
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
    <title>Adicionar Administrador - <?php echo htmlspecialchars($config_valor[1]); ?></title>
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
            <h1><i class="fas fa-user-shield"></i> Adicionar Administrador</h1>
            <p>Preencha os dados do novo administrador</p>
        </div>
        
        <div class="admin-actions" style="margin-bottom: 20px;">
            <a href="gerenciar_administradores.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Voltar para Lista de Administradores
            </a>
        </div>

        <?php if (!empty($mensagem_sucesso)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= $mensagem_sucesso ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($mensagem_erro)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= $mensagem_erro ?>
            </div>
        <?php endif; ?>

        <footer class="admin-form-container">
            <form method="post" class="admin-form" action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label for="nome"><i class="fas fa-user-tag"></i> Nome Completo *</label>
                        <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($nome); ?>" required>
                        <?php if (!empty($erro_nome)): ?>
                            <span class="error-message"><?= $erro_nome; ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="email"><i class="fas fa-at"></i> Email *</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($email); ?>" placeholder="exemplo@dominio.com" required>
                        <?php if (!empty($erro_email)): ?>
                            <span class="error-message"><?= $erro_email; ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="senha"><i class="fas fa-lock"></i> Senha *</label>
                        <input type="password" id="senha" name="senha" required>
                        <?php if (!empty($erro_senha)): ?>
                            <span class="error-message"><?= $erro_senha; ?></span>
                        <?php endif; ?>
                    </div>
                    
                <div class="form-group">
                    <label for="nivel_acesso"><i class="fas fa-user-lock"></i> Nível de Acesso *</label>
                    <select id="nivel_acesso" name="nivel_acesso" required>
                        <option value="" disabled <?= empty($nivel) ? 'selected' : ''; ?>>Selecione</option>
                        <option value="admin" <?= $nivel === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                        <?php if ($is_superadmin): ?>
                        <option value="superadmin" <?= $nivel === 'superadmin' ? 'selected' : ''; ?>>Super Administrador</option>
                        <?php endif; ?>
                    </select>
                    <?php if (!empty($erro_nivel)): ?>
                        <span class="error-message"><?= $erro_nivel; ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <button type="reset" class="btn btn-outline">
                        <i class="fas fa-undo"></i> Limpar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Cadastrar
                    </button>
                </div>
            </form>
        </div>

        <div class="footer-home">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($config_valor[1]); ?> - Todos os direitos reservados</p>
        </footer>
    </main>
</body>
</html>