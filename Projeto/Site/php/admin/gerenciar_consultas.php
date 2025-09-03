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

// Processar ações de formulário
$mensagem_sucesso = "";
$mensagem_erro = "";

// Processar exclusão de consulta
if (isset($_POST['excluir_consulta']) && isset($_POST['consulta_id'])) {
    $consulta_id = $_POST['consulta_id'];
    
    // Excluir consulta
    $sql = "DELETE FROM ga4_3_consultas WHERE ga4_3_idconsul = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $consulta_id);
    
    if ($stmt->execute()) {
        $mensagem_sucesso = "Consulta excluída com sucesso!";
    } else {
        $mensagem_erro = "Erro ao excluir consulta: " . $conn->error;
    }
    $stmt->close();
}

// Processar ações de atualização de status
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && isset($_POST["id"])) {
    $action = $_POST["action"];
    $consulta_id = $_POST["id"];
    
    switch ($action) {
        case 'aceitar':
            $sql = "UPDATE ga4_3_consultas SET ga4_3_status = 'Aceita' WHERE ga4_3_idconsul = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $consulta_id);
            
            if ($stmt->execute()) {
                $mensagem_sucesso = "Consulta aceita com sucesso!";
            } else {
                $mensagem_erro = "Erro ao aceitar consulta: " . $stmt->error;
            }
            $stmt->close();
            break;
            
        case 'recusar':
            $motivo = isset($_POST["motivo"]) ? $_POST["motivo"] : "Recusada pelo administrador";
            $sql = "UPDATE ga4_3_consultas SET ga4_3_status = 'Recusada', ga4_3_motcanc = ? WHERE ga4_3_idconsul = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $motivo, $consulta_id);
            
            if ($stmt->execute()) {
                $mensagem_sucesso = "Consulta recusada com sucesso!";
            } else {
                $mensagem_erro = "Erro ao recusar consulta: " . $stmt->error;
            }
            $stmt->close();
            break;
            
        case 'concluir':
            $sql = "UPDATE ga4_3_consultas SET ga4_3_status = 'Concluída' WHERE ga4_3_idconsul = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $consulta_id);
            
            if ($stmt->execute()) {
                $mensagem_sucesso = "Consulta marcada como concluída com sucesso!";
            } else {
                $mensagem_erro = "Erro ao concluir consulta: " . $stmt->error;
            }
            $stmt->close();
            break;
            
        case 'arquivar':
            $sql = "UPDATE ga4_3_consultas SET ga4_3_status = 'Arquivada' WHERE ga4_3_idconsul = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $consulta_id);
            
            if ($stmt->execute()) {
                $mensagem_sucesso = "Consulta arquivada com sucesso!";
            } else {
                $mensagem_erro = "Erro ao arquivar consulta: " . $stmt->error;
            }
            $stmt->close();
            break;
    }
}

// Configuração de paginação
$registros_por_pagina = 10;
$pagina_atual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_atual - 1) * $registros_por_pagina;

// Filtros
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'todos';
$search_term = isset($_GET['pesquisa']) ? $_GET['pesquisa'] : '';
$campo_pesquisa = isset($_GET['campo']) ? $_GET['campo'] : 'cliente';

// Construir a consulta SQL base
$sql_count = "SELECT COUNT(*) FROM ga4_3_consultas";
$sql_select = "SELECT c.ga4_3_idconsul, c.ga4_3_data, c.ga4_3_hora, c.ga4_3_motivo, c.ga4_3_status, c.ga4_3_motcanc,
               cl.ga4_1_nome AS nome_cliente, cl.ga4_1_email AS email_cliente,
               p.ga4_2_nome AS nome_profissional, p.ga4_2_especialidade
               FROM ga4_3_consultas c
               JOIN ga4_1_clientes cl ON c.id_cliente = cl.ga4_1_id
               JOIN ga4_2_profissionais p ON c.id_profissional = p.ga4_2_id";

// Adicionar condições de filtro
$where_conditions = [];
$params = [];
$types = "";

if ($status_filter !== 'todos') {
    $where_conditions[] = "c.ga4_3_status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($search_term)) {
    switch ($campo_pesquisa) {
        case 'profissional':
            $where_conditions[] = "p.ga4_2_nome LIKE ?";
            $params[] = "%$search_term%";
            $types .= "s";
            break;
        case 'especialidade':
            $where_conditions[] = "p.ga4_2_especialidade LIKE ?";
            $params[] = "%$search_term%";
            $types .= "s";
            break;
        case 'data':
            // Tentar converter a data para o formato do banco (yyyy-mm-dd)
            $date_parts = explode('/', $search_term);
            if (count($date_parts) === 3) {
                $formatted_date = $date_parts[2] . '-' . $date_parts[1] . '-' . $date_parts[0];
                $where_conditions[] = "c.ga4_3_data = ?";
                $params[] = $formatted_date;
                $types .= "s";
            } else {
                $where_conditions[] = "c.ga4_3_data = ?";
                $params[] = $search_term;
                $types .= "s";
            }
            break;
        case 'cliente':
        default:
            $where_conditions[] = "cl.ga4_1_nome LIKE ?";
            $params[] = "%$search_term%";
            $types .= "s";
            break;
    }
}

// Montar a cláusula WHERE
if (!empty($where_conditions)) {
    $where_clause = " WHERE " . implode(" AND ", $where_conditions);
    $sql_count .= $where_clause;
    $sql_select .= $where_clause;
}

// Adicionar join à consulta de contagem
if (strpos($sql_count, "JOIN") === false) {
    $sql_count = "SELECT COUNT(*) FROM ga4_3_consultas c
                 JOIN ga4_1_clientes cl ON c.id_cliente = cl.ga4_1_id
                 JOIN ga4_2_profissionais p ON c.id_profissional = p.ga4_2_id";
    
    if (!empty($where_conditions)) {
        $sql_count .= $where_clause;
    }
}

// Adicionar ordenação e limite
$sql_select .= " ORDER BY c.ga4_3_data DESC, c.ga4_3_hora DESC LIMIT ?, ?";
$params[] = $offset;
$params[] = $registros_por_pagina;
$types .= "ii";

// Preparar e executar a consulta de contagem
$stmt_count = $conn->prepare($sql_count);
if (!empty($params) && count($params) > 2) {
    $stmt_count->bind_param(substr($types, 0, -2), ...array_slice($params, 0, -2));
}
$stmt_count->execute();
$stmt_count->bind_result($total_registros);
$stmt_count->fetch();
$stmt_count->close();

// Calcular total de páginas
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Preparar e executar a consulta principal
$stmt = $conn->prepare($sql_select);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$consultas = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Contar consultas por status
$sql_count_status = "SELECT 
                    SUM(CASE WHEN ga4_3_status = 'Pendente' THEN 1 ELSE 0 END) as pendentes,
                    SUM(CASE WHEN ga4_3_status = 'Aceita' THEN 1 ELSE 0 END) as aceitas,
                    SUM(CASE WHEN ga4_3_status = 'Recusada' THEN 1 ELSE 0 END) as recusadas,
                    SUM(CASE WHEN ga4_3_status = 'Cancelado' THEN 1 ELSE 0 END) as canceladas,
                    SUM(CASE WHEN ga4_3_status = 'Aguardando confirmação' THEN 1 ELSE 0 END) as aguardando,
                    SUM(CASE WHEN ga4_3_status = 'Concluída' THEN 1 ELSE 0 END) as concluidas,
                    SUM(CASE WHEN ga4_3_status = 'Arquivada' THEN 1 ELSE 0 END) as arquivadas,
                    COUNT(*) as total
                    FROM ga4_3_consultas";
$result_count_status = $conn->query($sql_count_status);
$counts = $result_count_status->fetch_assoc();

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Consultas - <?php echo htmlspecialchars($config_valor[1]); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../../style/profissionais.css">
    <link rel="stylesheet" href="../../style/admin.css">
    <link rel="icon" type="image/png" href="../../midia/logo.png">
    <style>
        /* Cores específicas para as abas de filtro */
        .filter-tab[href*="status=todos"] {
            border-color: #4f46e5;
            color: #4f46e5;
        }
        .filter-tab[href*="status=todos"].active {
            background-color: #4f46e5;
            color: white;
        }
        
        .filter-tab[href*="status=Pendente"] {
            border-color: #f59e0b;
            color: #f59e0b;
        }
        .filter-tab[href*="status=Pendente"].active {
            background-color: #f59e0b;
            color: white;
        }
        
        .filter-tab[href*="status=Aceita"] {
            border-color: #10b981;
            color: #10b981;
        }
        .filter-tab[href*="status=Aceita"].active {
            background-color: #10b981;
            color: white;
        }
        
        .filter-tab[href*="status=Recusada"] {
            border-color: #ef4444;
            color: #ef4444;
        }
        .filter-tab[href*="status=Recusada"].active {
            background-color: #ef4444;
            color: white;
        }
        
        .filter-tab[href*="status=Concluída"] {
            border-color: #0ea5e9;
            color: #0ea5e9;
        }
        .filter-tab[href*="status=Concluída"].active {
            background-color: #0ea5e9;
            color: white;
        }
        
        .filter-tab[href*="status=Arquivada"] {
            border-color: #6b7280;
            color: #6b7280;
        }
        .filter-tab[href*="status=Arquivada"].active {
            background-color: #6b7280;
            color: white;
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
            <h1><i class="fas fa-calendar-check"></i> Gerenciar Consultas</h1>
            <p>Visualize e gerencie todas as consultas agendadas no sistema</p>
        </div>

        <?php if ($mensagem_sucesso): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $mensagem_sucesso; ?>
            </div>
        <?php endif; ?>

        <?php if ($mensagem_erro): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $mensagem_erro; ?>
            </div>
        <?php endif; ?>

        <div class="admin-filters">
            <div class="filter-tabs">
                <a href="?status=todos<?php echo !empty($search_term) ? '&pesquisa=' . urlencode($search_term) . '&campo=' . $campo_pesquisa : ''; ?>" 
                   class="filter-tab <?php echo $status_filter === 'todos' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar"></i> Todas
                    <span class="count"><?php echo $counts['total']; ?></span>
                </a>
                <a href="?status=Pendente<?php echo !empty($search_term) ? '&pesquisa=' . urlencode($search_term) . '&campo=' . $campo_pesquisa : ''; ?>" 
                   class="filter-tab <?php echo $status_filter === 'Pendente' ? 'active' : ''; ?>">
                    <i class="fas fa-clock"></i> Pendentes
                    <span class="count"><?php echo $counts['pendentes']; ?></span>
                </a>
                <a href="?status=Aceita<?php echo !empty($search_term) ? '&pesquisa=' . urlencode($search_term) . '&campo=' . $campo_pesquisa : ''; ?>" 
                   class="filter-tab <?php echo $status_filter === 'Aceita' ? 'active' : ''; ?>">
                    <i class="fas fa-check"></i> Aceitas
                    <span class="count"><?php echo $counts['aceitas']; ?></span>
                </a>
                <a href="?status=Recusada<?php echo !empty($search_term) ? '&pesquisa=' . urlencode($search_term) . '&campo=' . $campo_pesquisa : ''; ?>" 
                   class="filter-tab <?php echo $status_filter === 'Recusada' ? 'active' : ''; ?>">
                    <i class="fas fa-times"></i> Recusadas
                    <span class="count"><?php echo $counts['recusadas']; ?></span>
                </a>
                <a href="?status=Concluída<?php echo !empty($search_term) ? '&pesquisa=' . urlencode($search_term) . '&campo=' . $campo_pesquisa : ''; ?>" 
                   class="filter-tab <?php echo $status_filter === 'Concluída' ? 'active' : ''; ?>">
                    <i class="fas fa-check-double"></i> Concluídas
                    <span class="count"><?php echo $counts['concluidas']; ?></span>
                </a>
                <a href="?status=Arquivada<?php echo !empty($search_term) ? '&pesquisa=' . urlencode($search_term) . '&campo=' . $campo_pesquisa : ''; ?>" 
                   class="filter-tab <?php echo $status_filter === 'Arquivada' ? 'active' : ''; ?>">
                    <i class="fas fa-archive"></i> Arquivadas
                    <span class="count"><?php echo $counts['arquivadas']; ?></span>
                </a>
            </div>
        </div>
        
        <form class="admin-search" method="GET" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
            <select name="campo" class="form-select">
                <option value="cliente" <?php echo $campo_pesquisa === 'cliente' ? 'selected' : ''; ?>>Cliente</option>
                <option value="profissional" <?php echo $campo_pesquisa === 'profissional' ? 'selected' : ''; ?>>Profissional</option>
                <option value="especialidade" <?php echo $campo_pesquisa === 'especialidade' ? 'selected' : ''; ?>>Especialidade</option>
                <option value="data" <?php echo $campo_pesquisa === 'data' ? 'selected' : ''; ?>>Data (AAAA-MM-DD)</option>
            </select>
            <input type="text" name="pesquisa" placeholder="Pesquisar consultas..." 
                   value="<?php echo htmlspecialchars($search_term); ?>">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Buscar
            </button>
        </form>

        <?php if (empty($consultas)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h3>Nenhuma consulta encontrada</h3>
                <p>Não há consultas que correspondam aos critérios de busca.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Data / Hora</th>
                            <th>Cliente</th>
                            <th>Profissional</th>
                            <th>Especialidade</th>
                            <th>Motivo</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($consultas as $consulta): ?>
                            <?php 
                                // Formatação da data para exibição
                                $data_formatada = date("d/m/Y", strtotime($consulta['ga4_3_data']));
                                $hora_formatada = date("H:i", strtotime($consulta['ga4_3_hora']));
                                
                                // Determinando a classe style do status
                                $status_class = '';
                                switch ($consulta['ga4_3_status']) {
                                    case 'Pendente':
                                        $status_class = 'status-pending';
                                        break;
                                    case 'Aceita':
                                        $status_class = 'status-active';
                                        break;
                                    case 'Recusada':
                                    case 'Cancelado':
                                        $status_class = 'status-inactive';
                                        break;
                                    case 'Aguardando confirmação':
                                        $status_class = 'status-waiting';
                                        break;
                                    case 'Concluída':
                                        $status_class = 'status-completed';
                                        break;
                                    case 'Arquivada':
                                        $status_class = 'status-archived';
                                        break;
                                }
                            ?>
                            <tr>
                                <td><?php echo $consulta['ga4_3_idconsul']; ?></td>
                                <td>
                                    <div class="data-hora">
                                        <div class="data"><?php echo $data_formatada; ?></div>
                                        <div class="hora"><?php echo $hora_formatada; ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="cliente-info">
                                        <div class="cliente-avatar"><?php echo substr($consulta['nome_cliente'], 0, 1); ?></div>
                                        <div class="cliente-details">
                                            <span class="cliente-name"><?php echo htmlspecialchars($consulta['nome_cliente']); ?></span>
                                            <span class="cliente-email"><?php echo htmlspecialchars($consulta['email_cliente']); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($consulta['nome_profissional']); ?></td>
                                <td><?php echo htmlspecialchars($consulta['ga4_2_especialidade']); ?></td>
                                <td><?php echo htmlspecialchars($consulta['ga4_3_motivo']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo $consulta['ga4_3_status']; ?>
                                    </span>
                                    <?php if (!empty($consulta['ga4_3_motcanc']) && ($consulta['ga4_3_status'] === 'Recusada' || $consulta['ga4_3_status'] === 'Cancelado')): ?>
                                        <div class="motivo-cancelamento" title="<?php echo htmlspecialchars($consulta['ga4_3_motcanc']); ?>">
                                            <i class="fas fa-info-circle"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="actions">
                                    <a href="visualizar_consultas.php?id=<?php echo $consulta['ga4_3_idconsul']; ?>" class="btn-icon" title="Visualizar">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="editar_consultas.php?id=<?php echo $consulta['ga4_3_idconsul']; ?>" class="btn-icon" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    
                                    <?php if ($consulta['ga4_3_status'] === 'Pendente'): ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="id" value="<?php echo $consulta['ga4_3_idconsul']; ?>">
                                            <input type="hidden" name="action" value="aceitar">
                                            <button type="submit" class="btn-icon" title="Aceitar">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                        
                                        <button type="button" class="btn-icon" title="Recusar" 
                                                onclick="abrirModalRecusar(<?php echo $consulta['ga4_3_idconsul']; ?>)">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($consulta['ga4_3_status'] === 'Aceita'): ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="id" value="<?php echo $consulta['ga4_3_idconsul']; ?>">
                                            <input type="hidden" name="action" value="concluir">
                                            <button type="submit" class="btn-icon" title="Marcar como Concluída">
                                                <i class="fas fa-check-double"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($consulta['ga4_3_status'] === 'Concluída'): ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="id" value="<?php echo $consulta['ga4_3_idconsul']; ?>">
                                            <input type="hidden" name="action" value="arquivar">
                                            <button type="submit" class="btn-icon" title="Arquivar">
                                                <i class="fas fa-archive"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($consulta['ga4_3_status'] !== 'Concluída' && $consulta['ga4_3_status'] !== 'Arquivada'): ?>
                                        <button type="button" class="btn-icon btn-delete" title="Excluir" 
                                                onclick="confirmarExclusao(<?php echo $consulta['ga4_3_idconsul']; ?>, '<?php echo $data_formatada; ?> - <?php echo $hora_formatada; ?>', '<?php echo addslashes($consulta['nome_cliente']); ?>', '<?php echo addslashes($consulta['nome_profissional']); ?>')">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Paginação -->
            <?php if ($total_paginas > 1): ?>
                <div class="admin-pagination">
                    <?php if ($pagina_atual > 1): ?>
                        <a href="?pagina=1&status=<?php echo $status_filter; ?>&campo=<?php echo $campo_pesquisa; ?>&pesquisa=<?php echo urlencode($search_term); ?>" class="btn-page">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?pagina=<?php echo $pagina_atual - 1; ?>&status=<?php echo $status_filter; ?>&campo=<?php echo $campo_pesquisa; ?>&pesquisa=<?php echo urlencode($search_term); ?>" class="btn-page">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php
                    $inicio = max(1, $pagina_atual - 2);
                    $fim = min($inicio + 4, $total_paginas);
                    $inicio = max(1, $fim - 4);

                    for ($i = $inicio; $i <= $fim; $i++):
                    ?>
                        <a href="?pagina=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&campo=<?php echo $campo_pesquisa; ?>&pesquisa=<?php echo urlencode($search_term); ?>" 
                           class="btn-page <?php echo $i === $pagina_atual ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($pagina_atual < $total_paginas): ?>
                        <a href="?pagina=<?php echo $pagina_atual + 1; ?>&status=<?php echo $status_filter; ?>&campo=<?php echo $campo_pesquisa; ?>&pesquisa=<?php echo urlencode($search_term); ?>" class="btn-page">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="?pagina=<?php echo $total_paginas; ?>&status=<?php echo $status_filter; ?>&campo=<?php echo $campo_pesquisa; ?>&pesquisa=<?php echo urlencode($search_term); ?>" class="btn-page">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="footer-home">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($config_valor[1]); ?> - Todos os direitos reservados</p>
        </div>
    </main>

    <!-- Modal de confirmação de exclusão -->
    <div id="modal-exclusao" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Confirmar Exclusão</h2>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir esta consulta?</p>
                <ul>
                    <li><strong>Data/Hora:</strong> <span id="data-hora-consulta"></span></li>
                    <li><strong>Cliente:</strong> <span id="nome-cliente"></span></li>
                    <li><strong>Profissional:</strong> <span id="nome-profissional"></span></li>
                </ul>
                <p class="warning-text">
                    <i class="fas fa-exclamation-triangle"></i> Esta ação não pode ser desfeita!
                </p>
            </div>
            <div class="modal-footer">
                <form method="post" id="form-excluir">
                    <input type="hidden" name="consulta_id" id="id-consulta-excluir">
                    <input type="hidden" name="excluir_consulta" value="1">
                    <button type="button" class="btn btn-outline" id="btn-cancelar">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Confirmar Exclusão</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para recusar consulta -->
    <div id="modal-recusar" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Recusar Consulta</h2>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <form method="post" id="form-recusar">
                    <input type="hidden" name="id" id="id-consulta-recusar">
                    <input type="hidden" name="action" value="recusar">
                    
                    <div class="form-group">
                        <label for="motivo">Motivo da recusa:</label>
                        <textarea name="motivo" id="motivo" class="form-control" rows="4" required></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" id="btn-cancelar-recusa">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Confirmar Recusa</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Manipulação do modal de exclusão
        const modalExclusao = document.getElementById('modal-exclusao');
        const closeExclusao = modalExclusao.querySelector('.close');
        const btnCancelar = document.getElementById('btn-cancelar');
        const formExcluir = document.getElementById('form-excluir');
        
        // Manipulação do modal de recusa
        const modalRecusar = document.getElementById('modal-recusar');
        const closeRecusar = modalRecusar.querySelector('.close');
        const btnCancelarRecusa = document.getElementById('btn-cancelar-recusa');
        
        // Fechar modais ao clicar no X ou no botão cancelar
        closeExclusao.addEventListener('click', () => {
            modalExclusao.css.display = 'none';
        });
        
        btnCancelar.addEventListener('click', () => {
            modalExclusao.css.display = 'none';
        });
        
        closeRecusar.addEventListener('click', () => {
            modalRecusar.css.display = 'none';
        });
        
        btnCancelarRecusa.addEventListener('click', () => {
            modalRecusar.css.display = 'none';
        });
        
        // Fechar modais ao clicar fora deles
        window.addEventListener('click', (event) => {
            if (event.target === modalExclusao) {
                modalExclusao.css.display = 'none';
            }
            if (event.target === modalRecusar) {
                modalRecusar.css.display = 'none';
            }
        });
        
        // Função para abrir o modal de exclusão
        function confirmarExclusao(id, dataHora, cliente, profissional) {
            document.getElementById('id-consulta-excluir').value = id;
            document.getElementById('data-hora-consulta').textContent = dataHora;
            document.getElementById('nome-cliente').textContent = cliente;
            document.getElementById('nome-profissional').textContent = profissional;
            modalExclusao.css.display = 'block';
        }
        
        // Função para abrir o modal de recusa
        function abrirModalRecusar(id) {
            document.getElementById('id-consulta-recusar').value = id;
            modalRecusar.css.display = 'block';
        }
        
        // Mostrar tooltip para motivo de cancelamento ao passar o mouse
        document.querySelectorAll('.motivo-cancelamento').forEach(element => {
            element.addEventListener('mouseover', function() {
                const motivo = this.getAttribute('title');
                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip';
                tooltip.textContent = motivo;
                
                // Posicionamento do tooltip
                const rect = this.getBoundingClientRect();
                tooltip.css.left = `${rect.left + window.scrollX}px`;
                tooltip.css.top = `${rect.bottom + window.scrollY + 5}px`;
                
                document.body.appendChild(tooltip);
                
                this.addEventListener('mouseout', function() {
                    document.querySelectorAll('.tooltip').forEach(t => t.remove());
                });
            });
        });
        
        // Destacar linha da tabela ao passar o mouse
        document.querySelectorAll('.admin-table tbody tr').forEach(row => {
            row.addEventListener('mouseover', function() {
                this.classList.add('highlight');
            });
            
            row.addEventListener('mouseout', function() {
                this.classList.remove('highlight');
            });
        });
        
        // Verificar mensagens de alerta e removê-las após 5 segundos
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            if (alerts.length > 0) {
                setTimeout(() => {
                    alerts.forEach(alert => {
                        alert.css.opacity = '0';
                        setTimeout(() => {
                            alert.css.display = 'none';
                        }, 500);
                    });
                }, 5000);
            }
        });
    </script>
</body>
</html>