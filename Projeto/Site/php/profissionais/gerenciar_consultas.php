<?php
session_start();
include '../conexoes/conexao.php';

if (!isset($_SESSION["profissional_id"])) {
    header("Location: ../conexoes/login.php");
    exit();
}

$profissional_id = $_SESSION["profissional_id"];
$conn = conectarBanco();

// Obter nome do profissional
$sql_profissional = "SELECT ga4_2_nome FROM ga4_2_profissionais WHERE ga4_2_id = ?";
$stmt = $conn->prepare($sql_profissional);
$stmt->bind_param("i", $profissional_id);
$stmt->execute();
$stmt->bind_result($profissional_nome);
$stmt->fetch();
$stmt->close();

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"])) {
    $action = $_POST["action"];
    $consulta_id = $_POST["id"] ?? null;

    switch ($action) {
        case 'excluir':
            $sql = "UPDATE ga4_3_consultas SET ga4_3_status = 'Arquivada' WHERE ga4_3_idconsul = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $consulta_id);
            
            if ($stmt->execute()) {
                $message = "Consulta arquivada com sucesso!";
                $message_type = "success";
            } else {
                $message = "Erro ao arquivar consulta: " . $stmt->error;
                $message_type = "error";
            }
            $stmt->close();
            break;

        case 'cancelar':
            $sql = "SELECT ga4_3_data FROM ga4_3_consultas WHERE ga4_3_idconsul = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $consulta_id);
            $stmt->execute();
            $stmt->bind_result($data_consulta);
            $stmt->fetch();
            $stmt->close();

            $data_atual = new DateTime();
            $data_consulta = new DateTime($data_consulta);
            $intervalo = $data_atual->diff($data_consulta);

            if ($intervalo->days >= 1 && $data_consulta > $data_atual) {
                $sql = "UPDATE ga4_3_consultas SET ga4_3_status = 'Cancelada' WHERE ga4_3_idconsul = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $consulta_id);

                if ($stmt->execute()) {
                    $message = "Consulta cancelada com sucesso!";
                    $message_type = "success";
                } else {
                    $message = "Erro ao cancelar consulta: " . $stmt->error;
                    $message_type = "error";
                }
                $stmt->close();
            } else {
                $message = "Consultas só podem ser canceladas com pelo menos 1 dia de antecedência.";
                $message_type = "warning";
            }
            break;

        case 'concluir':
            $sql = "UPDATE ga4_3_consultas SET ga4_3_status = 'Concluída' WHERE ga4_3_idconsul = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $consulta_id);

            if ($stmt->execute()) {
                $message = "Consulta concluída com sucesso!";
                $message_type = "success";
            } else {
                $message = "Erro ao concluir consulta: " . $stmt->error;
                $message_type = "error";
            }
            $stmt->close();
            break;

        case 'logout':
            session_destroy();
            header("Location: ../conexoes/login.php");
            exit();
            break;

        case 'modificar':
            $novo_status = $_POST["novo_status"];
            $sql = "UPDATE ga4_3_consultas SET ga4_3_status = ? WHERE ga4_3_idconsul = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $novo_status, $consulta_id);

            if ($stmt->execute()) {
                $message = "Status da consulta atualizado para $novo_status com sucesso!";
                $message_type = "success";
            } else {
                $message = "Erro ao atualizar status da consulta: " . $stmt->error;
                $message_type = "error";
            }
            $stmt->close();
            break;

        case 'aceitar':
        case 'recusar':
            $status = $action === 'aceitar' ? 'Aceita' : 'Recusada';
            $sql = "UPDATE ga4_3_consultas SET ga4_3_status = ? WHERE ga4_3_idconsul = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $status, $consulta_id);

            if ($stmt->execute()) {
                $message = "Consulta $status com sucesso!";
                $message_type = "success";
            } else {
                $message = "Erro ao atualizar consulta: " . $stmt->error;
                $message_type = "error";
            }
            $stmt->close();
            break;
    }
}

// Filtro de status
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_term = isset($_GET['search']) ? $_GET['search'] : '';

// Consulta base para todas as consultas
$base_query = "SELECT c.ga4_3_idconsul, cl.ga4_1_nome, c.ga4_3_data, c.ga4_3_hora, c.ga4_3_motivo, c.ga4_3_status 
               FROM ga4_3_consultas c
               JOIN ga4_1_clientes cl ON c.id_cliente = cl.ga4_1_id
               WHERE c.id_profissional = ?";

// Adicionar filtro de busca se existir
if (!empty($search_term)) {
    $base_query .= " AND (cl.ga4_1_nome LIKE ? OR c.ga4_3_motivo LIKE ?)";
}

// Consultas por status
$status_queries = [
    'pendentes' => $base_query . " AND c.ga4_3_status = 'Pendente' ORDER BY c.ga4_3_data ASC, c.ga4_3_hora ASC",
    'aceitas' => $base_query . " AND c.ga4_3_status = 'Aceita' ORDER BY c.ga4_3_data ASC, c.ga4_3_hora ASC",
    'outras' => $base_query . " AND c.ga4_3_status IN ('Arquivada', 'Concluída', 'Recusada', 'Cancelada') ORDER BY c.ga4_3_data DESC, c.ga4_3_hora ASC",
    'all' => $base_query . " ORDER BY FIELD(c.ga4_3_status, 'Pendente', 'Aceita') DESC, c.ga4_3_data ASC, c.ga4_3_hora ASC"
];

// Preparar e executar a consulta com base no filtro
$query = $status_queries[$status_filter] ?? $status_queries['all'];
$stmt = $conn->prepare($query);

if (!empty($search_term)) {
    $search_param = "%$search_term%";
    $stmt->bind_param("iss", $profissional_id, $search_param, $search_param);
} else {
    $stmt->bind_param("i", $profissional_id);
}

$stmt->execute();
$result = $stmt->get_result();
$consultas = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Contar consultas por status
$sql_count = "SELECT ga4_3_status, COUNT(*) as count FROM ga4_3_consultas WHERE id_profissional = ? GROUP BY ga4_3_status";
$stmt_count = $conn->prepare($sql_count);
$stmt_count->bind_param("i", $profissional_id);
$stmt_count->execute();
$result_count = $stmt_count->get_result();
$counts = [];

while ($row = $result_count->fetch_assoc()) {
    $counts[$row['ga4_3_status']] = $row['count'];
}

$pendentes_count = $counts['Pendente'] ?? 0;
$aceitas_count = $counts['Aceita'] ?? 0;
$outras_count = array_sum(array_intersect_key($counts, array_flip(['Arquivada', 'Concluída', 'Recusada', 'Cancelada'])));
$total_count = array_sum($counts);

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Consultas - <?php echo htmlspecialchars($config_valor[1]); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../../style/profissionais.css">
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
                <div class="user-avatar"><?php echo substr($profissional_nome, 0, 1); ?></div>
                <span><?php echo $profissional_nome; ?></span>
                <form method="post" action="../conexoes/logout.php" style="margin-left: 15px;">
                    <button type="submit" class="btn btn-outline btn-sm">
                        <i class="fas fa-sign-out-alt"></i> Sair
                    </button>
                </form>
            </div>
        </nav>
    </header>

    <main class="container">
        <div class="filters">
            <div class="filter-tabs">
                <a href="?status=all" class="filter-tab <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i> Todas
                    <span class="count"><?php echo $total_count; ?></span>
                </a>
                <a href="?status=pendentes" class="filter-tab <?php echo $status_filter === 'pendentes' ? 'active' : ''; ?>">
                    <i class="fas fa-clock"></i> Pendentes
                    <span class="count"><?php echo $pendentes_count; ?></span>
                </a>
                <a href="?status=aceitas" class="filter-tab <?php echo $status_filter === 'aceitas' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-check"></i> Aceitas
                    <span class="count"><?php echo $aceitas_count; ?></span>
                </a>
                <a href="?status=outras" class="filter-tab <?php echo $status_filter === 'outras' ? 'active' : ''; ?>">
                    <i class="fas fa-archive"></i> Outras
                    <span class="count"><?php echo $outras_count; ?></span>
                </a>
            </div>
            <form class="search-box" method="GET">
                <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                <input type="text" name="search" placeholder="Buscar por nome ou motivo..." value="<?php echo htmlspecialchars($search_term); ?>">
                <button type="submit"><i class="fas fa-search"></i> Buscar</button>
            </form>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($consultas)): ?>
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <h3>Nenhuma consulta encontrada</h3>
                <p>Não há consultas que correspondam aos critérios de busca.</p>
            </div>
        <?php else: ?>
            <div class="consultas-grid">
                <?php foreach ($consultas as $consulta): ?>
                    <?php
                    $status_class = '';
                    switch ($consulta['ga4_3_status']) {
                        case 'Pendente':
                            $status_class = 'status-pendente';
                            $icon_class = 'fas fa-clock';
                            break;
                        case 'Aceita':
                            $status_class = 'status-aceita';
                            $icon_class = 'fas fa-check-circle';
                            break;
                        case 'Concluída':
                            $status_class = 'status-concluida';
                            $icon_class = 'fas fa-check-double';
                            break;
                        case 'Cancelada':
                            $status_class = 'status-cancelada';
                            $icon_class = 'fas fa-ban';
                            break;
                        case 'Recusada':
                            $status_class = 'status-recusada';
                            $icon_class = 'fas fa-times-circle';
                            break;
                        case 'Arquivada':
                            $status_class = 'status-arquivada';
                            $icon_class = 'fas fa-archive';
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
                    ?>

                    <div class="consulta-card">
                        <div class="consulta-header">
                            <span class="consulta-status <?php echo $status_class; ?>">
                                <i class="<?php echo $icon_class; ?>"></i> <?php echo $consulta['ga4_3_status']; ?>
                            </span>
                            <?php if ($is_today): ?>
                                <span class="consulta-status status-pendente">Hoje</span>
                            <?php elseif ($is_past): ?>
                                <span class="consulta-status status-recusada">Passada</span>
                            <?php endif; ?>
                        </div>
                        <div class="consulta-info">
                            <p><i class="fas fa-user"></i> <strong>Paciente:</strong> <?php echo htmlspecialchars($consulta['ga4_1_nome']); ?></p>
                            <p><i class="fas fa-calendar"></i> <strong>Data:</strong> <?php echo $data_formatada; ?></p>
                            <p><i class="fas fa-clock"></i> <strong>Hora:</strong> <?php echo $consulta['ga4_3_hora']; ?></p>
                            <p><i class="fas fa-comment-medical"></i> <strong>Motivo:</strong> <?php echo htmlspecialchars($consulta['ga4_3_motivo']); ?></p>
                        </div>

                        <div class="consulta-actions">
                            <?php if ($consulta['ga4_3_status'] === 'Pendente'): ?>
                                <form method="post" style="display:inline; flex: 1;">
                                    <input type="hidden" name="id" value="<?php echo $consulta['ga4_3_idconsul']; ?>">
                                    <input type="hidden" name="action" value="aceitar">
                                    <button type="submit" class="btn btn-success btn-block">
                                        <i class="fas fa-check"></i> Aceitar
                                    </button>
                                </form>
                                <form method="post" style="display:inline; flex: 1;">
                                    <input type="hidden" name="id" value="<?php echo $consulta['ga4_3_idconsul']; ?>">
                                    <input type="hidden" name="action" value="recusar">
                                    <button type="submit" class="btn btn-danger btn-block">
                                        <i class="fas fa-times"></i> Recusar
                                    </button>
                                </form>
                            <?php elseif ($consulta['ga4_3_status'] === 'Aceita'): ?>
                                <form method="post" style="display:inline; flex: 1;">
                                    <input type="hidden" name="id" value="<?php echo $consulta['ga4_3_idconsul']; ?>">
                                    <input type="hidden" name="action" value="concluir">
                                    <button type="submit" class="btn btn-success btn-block">
                                        <i class="fas fa-check-double"></i> Concluir
                                    </button>
                                </form>
                                <form method="post" style="display:inline; flex: 1;">
                                    <input type="hidden" name="id" value="<?php echo $consulta['ga4_3_idconsul']; ?>">
                                    <input type="hidden" name="action" value="cancelar">
                                    <button type="submit" class="btn btn-warning btn-block">
                                        <i class="fas fa-ban"></i> Cancelar
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <!-- Botão para modificar status (disponível para todas as consultas) -->
                            <form method="post" style="display:inline; width: 100%; margin-top: <?php echo ($consulta['ga4_3_status'] === 'Pendente' || $consulta['ga4_3_status'] === 'Aceita') ? '10px' : '0'; ?>;">
                                <input type="hidden" name="id" value="<?php echo $consulta['ga4_3_idconsul']; ?>">
                                <input type="hidden" name="action" value="modificar">
                                <div style="display: flex; gap: 10px;">
                                    <select name="novo_status" class="status-select">
                                        <option value="">Alterar status</option>
                                        <option value="Pendente" <?php echo $consulta['ga4_3_status'] === 'Pendente' ? 'selected' : ''; ?>>Pendente</option>
                                        <option value="Aceita" <?php echo $consulta['ga4_3_status'] === 'Aceita' ? 'selected' : ''; ?>>Aceita</option>
                                        <option value="Concluída" <?php echo $consulta['ga4_3_status'] === 'Concluída' ? 'selected' : ''; ?>>Concluída</option>
                                        <option value="Cancelada" <?php echo $consulta['ga4_3_status'] === 'Cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                                        <option value="Recusada" <?php echo $consulta['ga4_3_status'] === 'Recusada' ? 'selected' : ''; ?>>Recusada</option>
                                        <option value="Arquivada" <?php echo $consulta['ga4_3_status'] === 'Arquivada' ? 'selected' : ''; ?>>Arquivada</option>
                                    </select>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i>
                                    </button>
                                    </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <a href="profissional_home.php" class="back-button">
            <i class="fas fa-arrow-left"></i> Voltar
        </a>
    </main>
</body>
</html>
