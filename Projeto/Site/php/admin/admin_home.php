<?php
session_start();
include '../conexoes/conexao.php';

// Verificar se é uma requisição AJAX para obter contadores atualizados
if (isset($_GET['action']) && $_GET['action'] == 'get_counters') {
    $conn = conectarBanco();
    
    // Contar total de clientes
    $sql = "SELECT COUNT(*) FROM ga4_1_clientes";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $stmt->bind_result($clientes_count);
    $stmt->fetch();
    $stmt->close();
    
    // Contar total de profissionais
    $sql = "SELECT COUNT(*) FROM ga4_2_profissionais";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $stmt->bind_result($profissionais_count);
    $stmt->fetch();
    $stmt->close();
    
    // Contar consultas pendentes
    $sql = "SELECT COUNT(*) FROM ga4_3_consultas WHERE ga4_3_status = 'Pendente'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $stmt->bind_result($consultas_pendentes);
    $stmt->fetch();
    $stmt->close();
    
    $conn->close();
    
    // Retornar os contadores em formato JSON
    header('Content-Type: application/json');
    echo json_encode([
        'clientes_count' => $clientes_count,
        'profissionais_count' => $profissionais_count,
        'consultas_pendentes' => $consultas_pendentes
    ]);
    exit;
}

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

// Contar total de clientes
$sql = "SELECT COUNT(*) FROM ga4_1_clientes";
$stmt = $conn->prepare($sql);
$stmt->execute();
$stmt->bind_result($clientes_count);
$stmt->fetch();
$stmt->close();

// Contar total de profissionais
$sql = "SELECT COUNT(*) FROM ga4_2_profissionais";
$stmt = $conn->prepare($sql);
$stmt->execute();
$stmt->bind_result($profissionais_count);
$stmt->fetch();
$stmt->close();

// Contar consultas pendentes
$sql = "SELECT COUNT(*) FROM ga4_3_consultas WHERE ga4_3_status = 'Pendente'";
$stmt = $conn->prepare($sql);
$stmt->execute();
$stmt->bind_result($consultas_pendentes);
$stmt->fetch();
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo - <?php echo htmlspecialchars($config_valor[1]); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../../style/profissionais.css">
    <link rel="stylesheet" href="../../style/admin.css">
    <link rel="icon" type="image/png" href="../../midia/logo.png">
</head>
<body>

<header class="header">
    <nav class="navbar">
        <div>
            <div>
                <div class="logo">
                    <h1>
                        <a href="../../index.php">
                            <img class="img-logo" src="../../midia/logo.png" alt="Logo <?php echo htmlspecialchars($config_valor[1]); ?>" width="40" height="40"> 
                            <?php echo htmlspecialchars($config_valor[1]); ?>
                        </a>
                    </h1>
                </div>
            </div>
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
    </nav>
</header>    
    

    <main class="container"> 
        <section class="welcome-section">
            <div class="user-avatar-large"><?php echo substr($admin_nome, 0, 1); ?></div>
            <h1>Bem-vindo ao Painel Administrativo, <?php echo $admin_nome; ?>!</h1>
            <p>Gerencie todos os aspectos do sistema <?php echo htmlspecialchars($config_valor[1]); ?></p>
            
            <div class="stats">
                <a href="gerenciar_clientes.php" class="stat-item" style="text-decoration: none; color: inherit;">
                    <div class="number" id="clientes-count"><?php echo $clientes_count; ?></div>
                    <div class="label">Clientes Cadastrados</div>
                </a>
                <a href="gerenciar_profissionais.php" class="stat-item" style="text-decoration: none; color: inherit;">
                    <div class="number" id="profissionais-count"><?php echo $profissionais_count; ?></div>
                    <div class="label">Profissionais Cadastrados</div>
                </a>
                <a href="gerenciar_consultas.php" class="stat-item" style="text-decoration: none; color: inherit;">
                    <div class="number" id="consultas-pendentes"><?php echo $consultas_pendentes; ?></div>
                    <div class="label">Consultas Pendentes</div>
                </a>
            </div>
        </section>

        <section class="dashboard">
            <a href="gerenciar_clientes.php" class="card">
                <i class="fas fa-users"></i>
                <h3>Gerenciar Clientes</h3>
                <p>Visualize e gerencie todos os clientes cadastrados</p>
            </a>
            
            <a href="gerenciar_profissionais.php" class="card">
                <i class="fas fa-user-md"></i>
                <h3>Gerenciar Profissionais</h3>
                <p>Visualize e gerencie todos os profissionais cadastrados</p>
            </a>
            
            <a href="gerenciar_consultas.php" class="card">
                <i class="fas fa-calendar-check"></i>
                <h3>Gerenciar Consultas</h3>
                <p>Visualize e gerencie todas as consultas</p>
                <span class="badge" id="pendentes-badge" <?php if ($consultas_pendentes == 0) echo 'style="display:none;"'; ?>>
                    <span id="pendentes-badge-count"><?php echo $consultas_pendentes; ?></span> pendentes
                </span>
            </a>
            
            <a href="relatorios.php" class="card">
                <i class="fas fa-chart-bar"></i>
                <h3>Relatórios</h3>
                <p>Visualize estatísticas e relatórios do sistema</p>
            </a>
            
            <?php if ($nivel_acesso === 'superadmin'): ?>
            <a href="gerenciar_administradores.php" class="card">
                <i class="fas fa-user-shield"></i>
                <h3>Gerenciar Administradores</h3>
                <p>Adicione e gerencie outros administradores</p>
            </a>
            <?php endif; ?>
            
            <a href="configuracoes.php" class="card">
                <i class="fas fa-cog"></i>
                <h3>Configurações</h3>
                <p>Configure parâmetros do sistema</p>
            </a>
        </section>

        <footer class="footer-home">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($config_valor[1]); ?> - Todos os direitos reservados</p>
        </footer>
    </main>

    <script>
        // Função para atualizar os contadores dinamicamente
        function atualizarContadores() {
            if (!document.hidden) {
                fetch('admin_home.php?action=get_counters')
                    .then(response => response.json())
                    .then(data => {
                        // Atualizar contadores na seção de estatísticas
                        document.getElementById('clientes-count').textContent = data.clientes_count;
                        document.getElementById('profissionais-count').textContent = data.profissionais_count;
                        document.getElementById('consultas-pendentes').textContent = data.consultas_pendentes;
                        
                        // Atualizar badge de consultas pendentes
                        const pendentesBadge = document.getElementById('pendentes-badge');
                        document.getElementById('pendentes-badge-count').textContent = data.consultas_pendentes;
                        
                        if (data.consultas_pendentes > 0) {
                            pendentesBadge.css.display = '';
                        } else {
                            pendentesBadge.css.display = 'none';
                        }
                        
                        // Adicionar efeito visual se houver aumento nos contadores
                        if (data.consultas_pendentes > <?php echo $consultas_pendentes; ?>) {
                            destacarElemento('consultas-pendentes');
                            destacarElemento('pendentes-badge');
                        }
                    })
                    .catch(error => console.error('Erro ao atualizar contadores:', error));
            }
        }
        
        // Função para destacar um elemento brevemente
        function destacarElemento(elementId) {
            const elemento = document.getElementById(elementId);
            elemento.classList.add('highlight');
            setTimeout(() => {
                elemento.classList.remove('highlight');
            }, 2000);
        }
        
        // Atualizar contadores a cada 30 segundos
        setInterval(atualizarContadores, 30000);
        
        // Também atualizar quando a página voltar a ficar visível
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                atualizarContadores();
            }
        });
    </script>
</body>
</html>
