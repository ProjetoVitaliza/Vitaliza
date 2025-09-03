<?php
session_start();
include '../conexoes/conexao.php';

if (!isset($_SESSION["cliente_id"])) {
    header("Location: login_cliente.php");
    exit();
}

$cliente_id = $_SESSION["cliente_id"];
$conn = conectarBanco();

// Obter informações do cliente
$sql_cliente = "SELECT ga4_1_nome FROM ga4_1_clientes WHERE ga4_1_id = ?";
$stmt = $conn->prepare($sql_cliente);
$stmt->bind_param("i", $cliente_id);
$stmt->execute();
$stmt->bind_result($cliente_nome);
$stmt->fetch();
$stmt->close();

// Configurar filtros
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'todas';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Base query - Adicionado ga4_2_especialidade
$base_query = "SELECT c.ga4_3_idconsul, c.ga4_3_data, c.ga4_3_hora, c.ga4_3_motivo, c.ga4_3_status, 
               p.ga4_2_nome AS profissional_nome, p.ga4_2_especialidade AS especialidade
               FROM ga4_3_consultas c
               JOIN ga4_2_profissionais p ON c.id_profissional = p.ga4_2_id
               WHERE c.id_cliente = ?";

// Adicionar filtros específicos
switch ($filtro) {
    case 'pendentes':
        $base_query .= " AND c.ga4_3_status = 'Pendente'";
        break;
    case 'aceitas':
        $base_query .= " AND c.ga4_3_status = 'Aceita'";
        break;
    case 'concluidas':
        $base_query .= " AND c.ga4_3_status = 'Concluída'";
        break;
    case 'canceladas':
        $base_query .= " AND c.ga4_3_status IN ('Cancelada', 'Recusada')";
        break;
}

// Adicionar busca
if (!empty($search)) {
    $base_query .= " AND (p.ga4_2_nome LIKE ? OR c.ga4_3_motivo LIKE ? OR p.ga4_2_especialidade LIKE ?)";
}

// Ordenação
$base_query .= " ORDER BY 
                 CASE 
                    WHEN c.ga4_3_data = CURDATE() THEN 0 
                    WHEN c.ga4_3_data > CURDATE() THEN 1
                    ELSE 2
                 END,
                 c.ga4_3_data ASC, c.ga4_3_hora ASC";

// Preparar e executar a consulta
$stmt = $conn->prepare($base_query);

if (!empty($search)) {
    $search_param = "%$search%";
    $stmt->bind_param("isss", $cliente_id, $search_param, $search_param, $search_param);
} else {
    $stmt->bind_param("i", $cliente_id);
}

$stmt->execute();
$result = $stmt->get_result();
$consultas = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Separar consultas de hoje
$hoje = date('Y-m-d');
$consultas_hoje = array_filter($consultas, function($consulta) use ($hoje) {
    return $consulta['ga4_3_data'] == $hoje;
});

$outras_consultas = array_filter($consultas, function($consulta) use ($hoje) {
    return $consulta['ga4_3_data'] != $hoje;
});

// Contar consultas por status
$sql_count = "SELECT ga4_3_status, COUNT(*) as count FROM ga4_3_consultas WHERE id_cliente = ? GROUP BY ga4_3_status";
$stmt_count = $conn->prepare($sql_count);
$stmt_count->bind_param("i", $cliente_id);
$stmt_count->execute();
$result_count = $stmt_count->get_result();
$counts = [];

while ($row = $result_count->fetch_assoc()) {
    $counts[$row['ga4_3_status']] = $row['count'];
}

$pendentes_count = $counts['Pendente'] ?? 0;
$aceitas_count = $counts['Aceita'] ?? 0;
$concluidas_count = $counts['Concluída'] ?? 0;
$canceladas_count = ($counts['Cancelada'] ?? 0) + ($counts['Recusada'] ?? 0);
$total_count = array_sum($counts);

// Contar consultas de hoje
$sql_hoje = "SELECT COUNT(*) as count FROM ga4_3_consultas WHERE id_cliente = ? AND ga4_3_data = CURDATE()";
$stmt_hoje = $conn->prepare($sql_hoje);
$stmt_hoje->bind_param("i", $cliente_id);
$stmt_hoje->execute();
$stmt_hoje->bind_result($hoje_count);
$stmt_hoje->fetch();
$stmt_hoje->close();

// Processar cancelamento de consulta
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancelar' && isset($_POST['consulta_id'])) {
    $consulta_id = $_POST['consulta_id'];
    
    // Verificar se a consulta é do cliente
    $sql_verify = "SELECT c.ga4_3_data, c.ga4_3_status FROM ga4_3_consultas c WHERE c.ga4_3_idconsul = ? AND c.id_cliente = ?";
    $stmt_verify = $conn->prepare($sql_verify);
    $stmt_verify->bind_param("ii", $consulta_id, $cliente_id);
    $stmt_verify->execute();
    $result_verify = $stmt_verify->get_result();
    
    if ($row = $result_verify->fetch_assoc()) {
        if ($row['ga4_3_status'] != 'Aceita' && $row['ga4_3_status'] != 'Pendente') {
            $message = "Apenas consultas aceitas ou pendentes podem ser canceladas.";
            $message_type = "error";
        } else {
            $data_consulta = new DateTime($row['ga4_3_data']);
            $data_atual = new DateTime();
            $interval = $data_atual->diff($data_consulta);
            
            if ($data_consulta < $data_atual) {
                $message = "Não é possível cancelar consultas passadas.";
                $message_type = "error";
            } elseif ($interval->days < 1 && $row['ga4_3_status'] == 'Aceita') {
                $message = "Consultas aceitas só podem ser canceladas com pelo menos 24 horas de antecedência.";
                $message_type = "warning";
            } else {
                // Proceder com o cancelamento
                $sql_cancel = "UPDATE ga4_3_consultas SET ga4_3_status = 'Cancelado' WHERE ga4_3_idconsul = ?";
                $stmt_cancel = $conn->prepare($sql_cancel);
                $stmt_cancel->bind_param("i", $consulta_id);
                
                if ($stmt_cancel->execute()) {
                    $message = "Consulta cancelada com sucesso!";
                    $message_type = "success";
                    
                    // Recarregar página para atualizar contagens e lista
                    header("Location: situacao_consulta.php?filtro=$filtro&search=$search&message=Consulta cancelada com sucesso&type=success");
                    exit();
                } else {
                    $message = "Erro ao cancelar consulta. Por favor, tente novamente.";
                    $message_type = "error";
                }
                $stmt_cancel->close();
            }
        }
    } else {
        $message = "Consulta não encontrada ou você não tem permissão para cancelá-la.";
        $message_type = "error";
    }
    $stmt_verify->close();
}

// Verificar se há mensagem passada via URL
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = $_GET['message'];
    $message_type = $_GET['type'];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Consultas - <?php echo htmlspecialchars($config_valor[1]); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../../style/clientes.css">
    <link rel="stylesheet" href="../../style/consultas.css">
    <link rel="icon" type="image/png" href="../../midia/logo.png">
    <script src="../script/cliente.js"> </script>
    <style>
        /* Cores para as bolinhas de contagem nas abas */
        .filter-tab.pendentes:not(.active) .count {
            background-color: var(--warning-color); /* Laranja */
        }
        
        .filter-tab.aceitas:not(.active) .count {
            background-color: var(--success-color); /* Verde */
        }
        
        .filter-tab.concluidas:not(.active) .count {
            background-color: var(--primary-color); /* Azul */
        }
        
        .filter-tab.canceladas:not(.active) .count {
            background-color: var(--danger-color); /* Vermelho */
        }
        
        /* Cores para as bordas laterais dos cards */
        .consulta-card.status-Pendente::before {
            background-color: var(--warning-color); /* Laranja */
        }
        
        .consulta-card.status-Aceita::before {
            background-color: var(--success-color); /* Verde */
        }
        
        .consulta-card.status-concluida::before {
            background-color: var(--primary-color); /* Azul */
        }
        
        .consulta-card.status-Cancelado::before {
            background-color: var(--secondary-color); /* Cinza */
        }
        
        .consulta-card.status-Recusada::before {
            background-color: var(--danger-color); /* Vermelho */
        }
    </style>
</head>
<body>

<header class="header">
    <nav class="navbar"> 
        <div class="logo">
                <h1>
                    <a href="index.php">
                        <img class="img-logo" src="../../midia/logo.png" alt="Logo Vitaliza" width="40" height="40"> 
                        <?php echo htmlspecialchars($config_valor[1]); ?>
                    </a>
                </h1>
            </div>
        <div class="user-info">
            <div class="user-avatar"><?php echo substr($cliente_nome, 0, 1); ?></div>
            <span><?php echo $cliente_nome; ?></span>
            <form method="post" action="../conexoes/logout.php" style="margin-left: 15px;">
                <button type="submit" class="btn btn-outline btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Sair
                </button>
            </form>
        </div>
    </nav>    
</header>
    
    <main class="container">
       

        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
            <h2><i class="fas fa-calendar-check" style="color: var(--primary-color); margin-right: 10px;"></i>Minhas Consultas</h2>
            <a href="solicitar_consulta.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nova Consulta
            </a>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="filters">
            <div class="filter-tabs">
                <a href="?filtro=todas" class="filter-tab <?php echo $filtro === 'todas' ? 'active' : ''; ?> <?php echo $total_count > 0 ? 'has-items' : ''; ?>">
                    <i class="fas fa-list"></i> Todas
                    <span class="count"><?php echo $total_count; ?></span>
                </a>
                <a href="?filtro=pendentes" class="filter-tab pendentes <?php echo $filtro === 'pendentes' ? 'active' : ''; ?> <?php echo $pendentes_count > 0 ? 'has-items urgent' : ''; ?>">
                    <i class="fas fa-clock"></i> Pendentes
                    <span class="count"><?php echo $pendentes_count; ?></span>
                </a>
                <a href="?filtro=aceitas" class="filter-tab aceitas <?php echo $filtro === 'aceitas' ? 'active' : ''; ?> <?php echo $aceitas_count > 0 ? 'has-items' : ''; ?>">
                    <i class="fas fa-check-circle"></i> Aceitas
                    <span class="count"><?php echo $aceitas_count; ?></span>
                </a>
                <a href="?filtro=concluidas" class="filter-tab concluidas <?php echo $filtro === 'concluidas' ? 'active' : ''; ?> <?php echo $concluidas_count > 0 ? 'has-items' : ''; ?>">
                    <i class="fas fa-check-double"></i> Concluídas
                    <span class="count"><?php echo $concluidas_count; ?></span>
                </a>
                <a href="?filtro=canceladas" class="filter-tab canceladas <?php echo $filtro === 'canceladas' ? 'active' : ''; ?> <?php echo $canceladas_count > 0 ? 'has-items' : ''; ?>">
                    <i class="fas fa-ban"></i> Canceladas
                    <span class="count"><?php echo $canceladas_count; ?></span>
                </a>
            </div>
            <form class="search-box" method="GET">
                <input type="hidden" name="filtro" value="<?php echo $filtro; ?>">
                <input type="text" name="search" placeholder="Buscar por profissional, especialidade ou motivo..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit"><i class="fas fa-search"></i> Buscar</button>
            </form>
        </div>

        <?php if (empty($consultas)): ?>
            <div style="text-align: center; padding: 40px 20px; background-color: var(--card-bg); border-radius: 10px; margin-top: 20px;">
                <i class="fas fa-calendar-times" style="font-size: 48px; color: var(--text-muted); margin-bottom: 20px;"></i>
                <h3>Nenhuma consulta encontrada</h3>
                <p style="color: var(--text-muted);">Não há consultas que correspondam aos critérios selecionados.</p>
                <?php if ($filtro !== 'todas' || !empty($search)): ?>
                    <a href="?filtro=todas" class="btn btn-outline" style="margin-top: 15px;">Ver todas as consultas</a>
                <?php else: ?>
                    <a href="solicitar_consulta.php" class="btn btn-primary" style="margin-top: 15px;">Agendar uma consulta</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php if (!empty($consultas_hoje) && $filtro === 'todas'): ?>
                <div class="section-title">
                    <h3><i class="fas fa-calendar-day"></i> Consultas para hoje (<?php echo count($consultas_hoje); ?>)</h3>
                </div>
                <div class="consultas-grid">
                    <?php foreach ($consultas_hoje as $consulta): ?>
                        <?php renderConsultaCard($consulta); ?>
                    <?php endforeach; ?>
                </div>
                
                <?php if (!empty($outras_consultas)): ?>
                    <div class="section-title">
                        <h3><i class="fas fa-calendar"></i> Outras consultas</h3>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <div class="consultas-grid">
                <?php 
                if ($filtro === 'todas' && !empty($outras_consultas)) {
                    foreach ($outras_consultas as $consulta) {
                        renderConsultaCard($consulta);
                    }
                } elseif ($filtro !== 'todas') {
                    foreach ($consultas as $consulta) {
                        renderConsultaCard($consulta);
                    }
                }
                ?>
            </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 20px;">
            <a href="cliente_home.php" class="back-button">
                <i class="fas fa-arrow-left"></i> Voltar para a Página Inicial
            </a>
        </div>

        <footer class="footer-home">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($config_valor[1]); ?> - Todos os direitos reservados</p>
        </footer>
    </main>
</body>
</html>

<?php
// Função para renderizar o card de consulta
function renderConsultaCard($consulta) {
    // Determinar classes e ícones para o status
    $status_class = '';
    $icon_class = '';
    
    switch ($consulta['ga4_3_status']) {
        case 'Pendente':
            $status_class = 'status-Pendente';
            $icon_class = 'fas fa-clock';
            break;
        case 'Aceita':
            $status_class = 'status-Aceita';
            $icon_class = 'fas fa-check-circle';
            break;
        case 'Concluída':
            $status_class = 'status-concluida';
            $icon_class = 'fas fa-check-double';
            break;
        case 'Cancelado':
            $status_class = 'status-Cancelado';
            $icon_class = 'fas fa-ban';
            break;
        case 'Recusada':
            $status_class = 'status-Recusada';
            $icon_class = 'fas fa-times-circle';
            break;
        default:
            $status_class = '';
            $icon_class = 'fas fa-question-circle';
    }

    // Formatar data
    $data_consulta = new DateTime($consulta['ga4_3_data']);
    $data_formatada = $data_consulta->format('d/m/Y');
    
    // Verificar se é hoje
    $hoje = new DateTime();
    $is_today = $data_consulta->format('Y-m-d') === $hoje->format('Y-m-d');
    
    // Verificar se já passou
    $is_past = $data_consulta < $hoje && !$is_today;
    
    // Verificar se pode cancelar
    $can_cancel = ($consulta['ga4_3_status'] == 'Aceita' || $consulta['ga4_3_status'] == 'Pendente') && !$is_past;
    $interval = $hoje->diff($data_consulta);
    $cancel_warning = ($consulta['ga4_3_status'] == 'Aceita' && $interval->days < 1) ? true : false;
    ?>

    <div class="consulta-card <?php echo $status_class; ?>">
        <div class="consulta-header">
            <span class="status <?php echo $status_class; ?>">
                <i class="<?php echo $icon_class; ?>"></i> <?php echo $consulta['ga4_3_status']; ?>
            </span>
            <?php if ($is_today): ?>
                <span class="today-badge">HOJE</span>
            <?php endif; ?>
        </div>
        <div class="consulta-info">
            <p>
                <i class="fas fa-user-md"></i> 
                <strong>Profissional:</strong> &nbsp;<?php echo htmlspecialchars($consulta['profissional_nome']); ?>
            </p>
            <p>
                <i class="fas fa-stethoscope"></i> 
                <strong>Especialidade:</strong> &nbsp;<?php echo htmlspecialchars($consulta['especialidade']); ?>
            </p>
            <p>
                <i class="fas fa-calendar"></i> 
                <strong>Data:</strong> &nbsp;<?php echo $data_formatada; ?>
            </p>
            <p>
                <i class="fas fa-clock"></i> 
                <strong>Hora:</strong> &nbsp;<?php echo $consulta['ga4_3_hora']; ?>
            </p>
            <p>
                <i class="fas fa-comment-medical"></i> 
                <span><strong>Motivo:</strong> &nbsp;<?php echo htmlspecialchars($consulta['ga4_3_motivo']); ?></span>
            </p>
        </div>

        <?php if ($can_cancel): ?>
            <div class="consulta-actions">
                <?php if ($cancel_warning): ?>
                    <span class="warning-text">
                        <i class="fas fa-exclamation-triangle"></i> Cancelar consulta menos de 24h antes pode gerar taxa
                    </span>
                <?php endif; ?>
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="cancelar">
                    <input type="hidden" name="consulta_id" value="<?php echo $consulta['ga4_3_idconsul']; ?>">
                    <button type="submit" class="btn btn-outline btn-sm" onclick="return confirm('Tem certeza que deseja cancelar esta consulta?');">
                        <i class="fas fa-times"></i> Cancelar Consulta
                    </button>
                </form>
            </div>
        <?php endif; ?>
        
        <?php if ($consulta['ga4_3_status'] == 'Aceita' && $is_today): ?>
            <div class="chat-action">
                <a href="chat_cliente.php" class="btn btn-primary">
                    <i class="fas fa-comments"></i> Falar com o Profissional
                </a>
            </div>
        <?php endif; ?>
    </div>
<?php
}
?>

<script> 
        document.addEventListener('DOMContentLoaded', function() {
            const horaElements = document.querySelectorAll('.consulta-card p:nth-child(3)');
            horaElements.forEach(el => {
                const horaText = el.textContent;
                if (horaText.includes('Hora:')) {
                    const hora = horaText.split('Hora:')[1].trim();
                    const [hours, minutes] = hora.split(':');
                    el.innerHTML = el.innerHTML.replace(hora, `${hours}:${minutes}h`);
                }
            });
        });
</script>
