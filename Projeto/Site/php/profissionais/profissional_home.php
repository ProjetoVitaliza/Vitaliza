<?php
session_start();
include '../conexoes/conexao.php';

// Verificar se é uma requisição AJAX para obter contadores atualizados
if (isset($_GET['action']) && $_GET['action'] == 'get_counters') {
    $profissional_id = $_SESSION["profissional_id"];
    $conn = conectarBanco();
    
    // Contar consultas pendentes
    $sql = "SELECT COUNT(*) FROM ga4_3_consultas WHERE id_profissional = ? AND ga4_3_status = 'Pendente'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $profissional_id);
    $stmt->execute();
    $stmt->bind_result($pendentes_count);
    $stmt->fetch();
    $stmt->close();
    
    // Contar consultas para hoje
    $hoje = date('Y-m-d');
    $sql = "SELECT COUNT(*) FROM ga4_3_consultas WHERE id_profissional = ? AND ga4_3_data = ? AND ga4_3_status = 'Aceita'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $profissional_id, $hoje);
    $stmt->execute();
    $stmt->bind_result($hoje_count);
    $stmt->fetch();
    $stmt->close();
    
    // Contar mensagens não lidas
    $sql = "SELECT COUNT(*) FROM ga4_4_mensagens 
            WHERE ga4_4_id_profissional = ? 
            AND ga4_4_enviado_por = 'cliente' 
            AND ga4_4_status_mensagem != 'lida'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $profissional_id);
    $stmt->execute();
    $stmt->bind_result($mensagens_nao_lidas);
    $stmt->fetch();
    $stmt->close();
    
    $conn->close();
    
    // Retornar os contadores em formato JSON
    header('Content-Type: application/json');
    echo json_encode([
        'pendentes_count' => $pendentes_count,
        'hoje_count' => $hoje_count,
        'mensagens_nao_lidas' => $mensagens_nao_lidas
    ]);
    exit;
}

if (!isset($_SESSION["profissional_id"])) {
    header("Location: ../conexoes/login.php");
    exit();
}

// Obter informações do profissional
$profissional_id = $_SESSION["profissional_id"];
$conn = conectarBanco();

// Obter nome e especialidade do profissional
$sql = "SELECT ga4_2_nome, ga4_2_especialidade FROM ga4_2_profissionais WHERE ga4_2_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $profissional_id);
$stmt->execute();
$stmt->bind_result($profissional_nome, $especialidade);
$stmt->fetch();
$stmt->close();

// Contar consultas pendentes
$sql = "SELECT COUNT(*) FROM ga4_3_consultas WHERE id_profissional = ? AND ga4_3_status = 'Pendente'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $profissional_id);
$stmt->execute();
$stmt->bind_result($pendentes_count);
$stmt->fetch();
$stmt->close();

// Contar consultas para hoje
$hoje = date('Y-m-d');
$sql = "SELECT COUNT(*) FROM ga4_3_consultas WHERE id_profissional = ? AND ga4_3_data = ? AND ga4_3_status = 'Aceita'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $profissional_id, $hoje);
$stmt->execute();
$stmt->bind_result($hoje_count);
$stmt->fetch();
$stmt->close();

// Contar mensagens não lidas (enviadas pelo cliente e que não foram lidas pelo profissional)
$sql = "SELECT COUNT(*) FROM ga4_4_mensagens 
        WHERE ga4_4_id_profissional = ? 
        AND ga4_4_enviado_por = 'cliente' 
        AND ga4_4_status_mensagem != 'lida'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $profissional_id);
$stmt->execute();
$stmt->bind_result($mensagens_nao_lidas);
$stmt->fetch();
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Área do Profissional - <?php echo htmlspecialchars($config_valor[1]); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../../style/profissionais.css">
    <link rel="icon" type="image/png" href="../../midia/logo.png">
</head>
<body>

    <div class="header">
        <nav class="navbar">
        
            <div>
                <div class="logo">
                    <h1>
                        <a href="../../index.html">
                            <img class="img-logo" src="../../midia/logo.png" alt="Logo Vitaliza" width="40" height="40"> 
                            <?php echo htmlspecialchars($config_valor[1]); ?>
                        </a>
                    </h1>
                </div>
            </div>
            <div class="user-info">
                <div class="user-avatar"><?php echo substr($profissional_nome, 0, 1); ?></div>
                <span><?php echo $profissional_nome; ?></span>
                <form method="post" action="../conexoes/logout.php" style="margin-left: 15px;">
                    <button type="submit" class="btn btn-outline btn-sm">
                        <i class="fas fa-sign-out-alt"></i> Sair
                    </button>
                </form>
            </div>
        </nav>
    </div>


    <div class="container">
        
        <div class="welcome-section">
            <div class="user-avatar-large"><?php echo substr($profissional_nome, 0, 1); ?></div>
            <h1>Bem-vindo, <?php echo $profissional_nome; ?>!</h1>
            <p><?php echo $especialidade; ?></p>
            
            <div class="stats">
                <div class="stat-item">
                    <div class="number" id="pendentes-count"><?php echo $pendentes_count; ?></div>
                    <div class="label">Consultas Pendentes</div>
                </div>
                <a href="gerenciar_consultas.php?filtro=hoje" class="stat-item" style="text-decoration: none; color: inherit;">
                    <div class="number" id="hoje-count"><?php echo $hoje_count; ?></div>
                    <div class="label">Consultas Hoje</div>
                </a>
                <a href="chat_profissional.php" class="stat-item" style="text-decoration: none; color: inherit;">
                    <div class="number" id="mensagens-nao-lidas"><?php echo $mensagens_nao_lidas; ?></div>
                    <div class="label">Mensagens Não Lidas</div>
                </a>
            </div>
        </div>

        <div class="dashboard">
            <a href="gerenciar_consultas.php" class="card">
                <i class="fas fa-calendar-check"></i>
                <h3>Gerenciar Consultas</h3>
                <p>Visualize e gerencie todas as suas consultas</p>
                <span class="badge" id="pendentes-badge" <?php if ($pendentes_count == 0) echo 'style="display:none;"'; ?>>
                    <span id="pendentes-badge-count"><?php echo $pendentes_count; ?></span> pendentes
                </span>
            </a>
            
            <a href="chat_profissional.php" class="card">
                <i class="fas fa-comments"></i>
                <h3>Chat com Clientes</h3>
                <p>Comunique-se com seus pacientes</p>
                <span class="badge" id="mensagens-badge" <?php if ($mensagens_nao_lidas == 0) echo 'style="display:none;"'; ?>>
                    <span id="mensagens-badge-count"><?php echo $mensagens_nao_lidas; ?></span> não lidas
                </span>
            </a>
            
            <a href="perfil_profissional.php" class="card">
                <i class="fas fa-user-md"></i>
                <h3>Meu Perfil</h3>
                <p>Visualize e edite suas informações</p>
            </a>
        </div>

        <div class="footer-home">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($config_valor[1]); ?> - Todos os direitos reservados</p>
        </div>
    </div>

    <script>
        // Função para atualizar os contadores dinamicamente
        function atualizarContadores() {
            if (!document.hidden) {
                fetch('profissional_home.php?action=get_counters')
                    .then(response => response.json())
                    .then(data => {
                        // Atualizar contadores na seção de estatísticas
                        document.getElementById('pendentes-count').textContent = data.pendentes_count;
                        document.getElementById('hoje-count').textContent = data.hoje_count;
                        document.getElementById('mensagens-nao-lidas').textContent = data.mensagens_nao_lidas;
                        
                        // Atualizar badges nos cards
                        const pendentesBadge = document.getElementById('pendentes-badge');
                        const mensagensBadge = document.getElementById('mensagens-badge');
                        
                        // Atualizar badge de consultas pendentes
                        document.getElementById('pendentes-badge-count').textContent = data.pendentes_count;
                        if (data.pendentes_count > 0) {
                            pendentesBadge.css.display = '';
                        } else {
                            pendentesBadge.css.display = 'none';
                        }
                        
                        // Atualizar badge de mensagens não lidas
                        document.getElementById('mensagens-badge-count').textContent = data.mensagens_nao_lidas;
                        if (data.mensagens_nao_lidas > 0) {
                            mensagensBadge.css.display = '';
                        } else {
                            mensagensBadge.css.display = 'none';
                        }
                        
                        // Adicionar efeito visual se houver aumento nos contadores
                        if (data.pendentes_count > <?php echo $pendentes_count; ?>) {
                            destacarElemento('pendentes-count');
                            destacarElemento('pendentes-badge');
                        }
                        
                        if (data.mensagens_nao_lidas > <?php echo $mensagens_nao_lidas; ?>) {
                            destacarElemento('mensagens-nao-lidas');
                            destacarElemento('mensagens-badge');
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
        
        // Adicionar estilo para o efeito de destaque
        const style = document.createElement('style');
        style.textContent = `
            @keyframes highlight-pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.1); color: #3b82f6; }
                100% { transform: scale(1); }
            }
            .highlight {
                animation: highlight-pulse 2s ease;
            }
        `;
        document.head.appendChild(style);
        
        // Atualizar contadores a cada 10 segundos
        setInterval(atualizarContadores, 10000);
        
        // Também atualizar quando a página voltar a ficar visível
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                atualizarContadores();
            }
        });
    </script>
</body>
</html>
