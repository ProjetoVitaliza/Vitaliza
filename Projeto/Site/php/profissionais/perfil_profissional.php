<?php
session_start();
include '../conexoes/conexao.php';

if (!isset($_SESSION["profissional_id"])) {
    header("Location: ../conexoes/login.php");
    exit();
}

$profissional_id = $_SESSION["profissional_id"];
$conn = conectarBanco();

$message = "";
$message_type = "";

// Buscar informações do profissional
$sql = "SELECT ga4_2_nome, ga4_2_email, ga4_2_tel, ga4_2_especialidade, ga4_2_crm, ga4_2_cpf, ga4_2_cep, ga4_2_sexo, ga4_2_nasc 
        FROM ga4_2_profissionais WHERE ga4_2_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $profissional_id);
$stmt->execute();
$result = $stmt->get_result();
$profissional = $result->fetch_assoc();
$stmt->close();

// Processar atualização de perfil
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && $_POST["action"] == "update_profile") {
    $nome = $_POST["nome"];
    $email = $_POST["email"];
    $telefone = $_POST["telefone"];
    $especialidade = $_POST["especialidade"];
    $cep = $_POST["cep"];
    
    $sql = "UPDATE ga4_2_profissionais SET 
            ga4_2_nome = ?, 
            ga4_2_email = ?, 
            ga4_2_tel = ?, 
            ga4_2_especialidade = ?, 
            ga4_2_cep = ? 
            WHERE ga4_2_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssii", $nome, $email, $telefone, $especialidade, $cep, $profissional_id);
    
    if ($stmt->execute()) {
        $message = "Perfil atualizado com sucesso!";
        $message_type = "success";
        
        // Atualizar os dados exibidos
        $profissional["ga4_2_nome"] = $nome;
        $profissional["ga4_2_email"] = $email;
        $profissional["ga4_2_tel"] = $telefone;
        $profissional["ga4_2_especialidade"] = $especialidade;
        $profissional["ga4_2_cep"] = $cep;
    } else {
        $message = "Erro ao atualizar perfil: " . $stmt->error;
        $message_type = "error";
    }
    $stmt->close();
}

// Processar alteração de senha
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && $_POST["action"] == "change_password") {
    $senha_atual = $_POST["senha_atual"];
    $nova_senha = $_POST["nova_senha"];
    $confirmar_senha = $_POST["confirmar_senha"];
    
    // Verificar se a senha atual está correta
    $sql = "SELECT ga4_2_senha FROM ga4_2_profissionais WHERE ga4_2_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $profissional_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if (password_verify($senha_atual, $row['ga4_2_senha'])) {
        // Verificar se as novas senhas coincidem
        if ($nova_senha === $confirmar_senha) {
            // Verificar requisitos mínimos da senha
            if (strlen($nova_senha) >= 6) {
                $nova_senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                
                $sql = "UPDATE ga4_2_profissionais SET ga4_2_senha = ? WHERE ga4_2_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $nova_senha_hash, $profissional_id);
                
                if ($stmt->execute()) {
                    $message = "Senha alterada com sucesso!";
                    $message_type = "success";
                } else {
                    $message = "Erro ao alterar senha: " . $stmt->error;
                    $message_type = "error";
                }
                $stmt->close();
            } else {
                $message = "A nova senha deve ter pelo menos 6 caracteres.";
                $message_type = "error";
            }
        } else {
            $message = "As novas senhas não coincidem.";
            $message_type = "error";
        }
    } else {
        $message = "Senha atual incorreta.";
        $message_type = "error";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - <?php echo htmlspecialchars($config_valor[1]); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../../style/profissionais.css">
    <link rel="stylesheet" href="../../style/perfil_profissional.css">
    <link rel="icon" type="image/png" href="../../midia/logo.png">
</head>
<body>

    <header class="header">
        <nav class="navbar">
            <div class="logo">
                <h1>
                    <a href="index.html">
                        <img class="img-logo" src="../../midia/logo.png" alt="Logo Vitaliza" width="40" height="40"> 
                        <?php echo htmlspecialchars($config_valor[1]); ?>
                    </a>
                </h1>
            </div>
            
            <div class="user-info">
                <div class="user-avatar"><?php echo substr($profissional["ga4_2_nome"], 0, 1); ?></div>
                <span><?php echo $profissional["ga4_2_nome"]; ?></span>
                <a href="profissional_home.php" class="btn btn-outline btn-sm" style="margin-left: 15px;">
                    <i class="fas fa-home"></i> Início
                </a>
            </div>
        </nav>
    </header>

    <main class="container">
        

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="profile-container">
            <div class="profile-header">
                <div class="profile-avatar">
                    <?php echo substr($profissional["ga4_2_nome"], 0, 1); ?>
                </div>
                <div class="profile-title">
                    <h2><?php echo $profissional["ga4_2_nome"]; ?></h2>
                    <p><?php echo $profissional["ga4_2_especialidade"]; ?></p>
                </div>
            </div>

            <div class="tabs">
                <button class="tab-btn active" onclick="openTab(event, 'info')">Informações Pessoais</button>
                <button class="tab-btn" onclick="openTab(event, 'security')">Segurança</button>
            </div>

            <div id="info" class="tab-content active">
                <form method="POST" action="perfil_profissional.php" class="profile-form">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-group">
                        <label for="nome"><i class="fas fa-user"></i> Nome Completo</label>
                        <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($profissional["ga4_2_nome"]); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> E-mail</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($profissional["ga4_2_email"]); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="telefone"><i class="fas fa-phone"></i> Telefone</label>
                        <input type="tel" id="telefone" name="telefone" value="<?php echo htmlspecialchars($profissional["ga4_2_tel"]); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="especialidade"><i class="fas fa-stethoscope"></i> Especialidade</label>
                        <input type="text" id="especialidade" name="especialidade" value="<?php echo htmlspecialchars($profissional["ga4_2_especialidade"]); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="crm"><i class="fas fa-id-card"></i> CRM</label>
                        <input type="text" id="crm" name="crm" value="<?php echo htmlspecialchars($profissional["ga4_2_crm"]); ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="cpf"><i class="fas fa-id-card"></i> CPF</label>
                        <input type="text" id="cpf" name="cpf" value="<?php echo htmlspecialchars($profissional["ga4_2_cpf"]); ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="cep"><i class="fas fa-map-marker-alt"></i> CEP</label>
                        <input type="text" id="cep" name="cep" value="<?php echo htmlspecialchars($profissional["ga4_2_cep"]); ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Salvar Alterações
                    </button>
                </form>
            </div>

            <div id="security" class="tab-content">
                <form method="POST" action="perfil_profissional.php" class="profile-form" id="password-form">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label for="senha_atual"><i class="fas fa-lock"></i> Senha Atual</label>
                        <input type="password" id="senha_atual" name="senha_atual" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="nova_senha"><i class="fas fa-key"></i> Nova Senha</label>
                        <input type="password" id="nova_senha" name="nova_senha" required>
                        <div class="password-requirements">
                            A senha deve ter pelo menos 6 caracteres.
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirmar_senha"><i class="fas fa-check"></i> Confirmar Nova Senha</label>
                        <input type="password" id="confirmar_senha" name="confirmar_senha" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Alterar Senha
                    </button>
                </form>
            </div>
        </div>

        <a href="profissional_home.php" class="back-button">
            <i class="fas fa-arrow-left"></i> Voltar
        </a>
    </main>

    <script>
        function openTab(evt, tabName) {
            var tabContents = document.getElementsByClassName("tab-content");
            for (var i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove("active");
            }
            
            var tabButtons = document.getElementsByClassName("tab-btn");
            for (var i = 0; i < tabButtons.length; i++) {
                tabButtons[i].classList.remove("active");
            }
            
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }

        // Validar confirmação de senha
        document.getElementById('confirmar_senha').addEventListener('input', function() {
            const novaSenha = document.getElementById('nova_senha').value;
            const confirmarSenha = this.value;
            const mensagem = document.getElementById('senha-match');
            
            if (!mensagem) {
                const div = document.createElement('div');
                div.id = 'senha-match';
                div.css.marginTop = '5px';
                div.css.fontSize = '12px';
                this.parentNode.appendChild(div);
            }
            
            if (confirmarSenha.length === 0) {
                document.getElementById('senha-match').innerHTML = '';
            } else if (novaSenha === confirmarSenha) {
                document.getElementById('senha-match').innerHTML = '<span style="color: green;">Senhas coincidem</span>';
            } else {
                document.getElementById('senha-match').innerHTML = '<span style="color: red;">Senhas não coincidem</span>';
            }
        });

        // Validar formulário antes de enviar
        document.getElementById('password-form').addEventListener('submit', function(e) {
            const novaSenha = document.getElementById('nova_senha').value;
            const confirmarSenha = document.getElementById('confirmar_senha').value;
            
            if (novaSenha.length < 6) {
                e.preventDefault();
                alert('A senha deve ter pelo menos 6 caracteres.');
                return false;
            }
            
            if (novaSenha !== confirmarSenha) {
                e.preventDefault();
                alert('As senhas não coincidem.');
                return false;
            }
        });
    </script>
</body>
</html>
