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

// Verificar se o ID do administrador a ser editado foi passado
if (!isset($_GET['id'])) {
    header("Location: gerenciar_administradores.php");
    exit();
}

// Obter informações do administrador a ser editado
$editar_id = $_GET['id'];
$sql_editar = "SELECT ga4_5_nome, ga4_5_email, ga4_5_nivel_acesso FROM ga4_5_administradores WHERE ga4_5_id = ?";
$stmt = $conn->prepare($sql_editar);
$stmt->bind_param("i", $editar_id);
$stmt->execute();
$stmt->bind_result($editar_nome, $editar_email, $editar_nivel_acesso);
$stmt->fetch();
$stmt->close();

// Mensagem de feedback
$mensagem = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $nivel_acesso = $_POST['nivel_acesso'];

    // Atualizar informações do administrador
    $sql_update = "UPDATE ga4_5_administradores SET ga4_5_nome = ?, ga4_5_email = ?, ga4_5_nivel_acesso = ? WHERE ga4_5_id = ?";
    $stmt = $conn->prepare($sql_update);
    $stmt->bind_param("sssi", $nome, $email, $nivel_acesso, $editar_id);
    
    if ($stmt->execute()) {
        $mensagem = "Administrador atualizado com sucesso!";
    } else {
        $mensagem = "Erro ao atualizar administrador. Tente novamente.";
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Administrador - <?php echo htmlspecialchars($config_valor[1]); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../../style/profissionais.css">
    <link rel="stylesheet" href="../../style/admin.css">
    <link rel="icon" type="image/png" href="../../midia/logo.png">
</head>
<body>
<div class="header">
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
</div> 

        <div class="content-header">
            <h1><i class="fas fa-user-edit"></i> Editar Administrador</h1>
            <p>Atualize as informações do administrador abaixo.</p>
        </div>

        <div class="form-container">
            <?php if ($mensagem): ?>
                <div class="alert alert-info">
                    <?php echo $mensagem; ?>
                </div>
            <?php endif; ?>
            <form method="post" action="">
                <input type="hidden" name="id" value="<?php echo $editar_id; ?>">
                <div class="form-group">
                    <label for="nome">Nome:</label>
                    <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($editar_nome); ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($editar_email); ?>" required>
                </div>
                <div class="form-group">
                    <label for="nivel_acesso">Nível de Acesso:</label>
                    <select id="nivel_acesso" name="nivel_acesso" required>
                        <option value="admin" <?php echo $editar_nivel_acesso === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="superadmin" <?php echo $editar_nivel_acesso === 'superadmin' ? 'selected' : ''; ?>>Super Admin</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Salvar
                    </button>
                    <a href="gerenciar_administradores.php" class="btn btn-danger">
                        <i class="fas fa-arrow-left"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>

        <div class="footer-home">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($config_valor[1]); ?> - Todos os direitos reservados</p>
        </div>
    </div>
</body>
</html>