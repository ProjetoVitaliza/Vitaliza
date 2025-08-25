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

// Processar exclusão de profissional
if (isset($_POST['excluir_profissional']) && isset($_POST['profissional_id'])) {
    $profissional_id = $_POST['profissional_id'];
    
    // Verificar se existem consultas associadas a este profissional
    $sql = "SELECT COUNT(*) FROM ga4_3_consultas WHERE id_profissional = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $profissional_id);
    $stmt->execute();
    $stmt->bind_result($consultas_count);
    $stmt->fetch();
    $stmt->close();
    
    if ($consultas_count > 0) {
        $mensagem_erro = "Não é possível excluir este profissional pois existem consultas associadas a ele.";
    } else {
        // Excluir profissional
        $sql = "DELETE FROM ga4_2_profissionais WHERE ga4_2_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $profissional_id);
        
        if ($stmt->execute()) {
            $mensagem_sucesso = "Profissional excluído com sucesso!";
        } else {
            $mensagem_erro = "Erro ao excluir profissional: " . $conn->error;
        }
        $stmt->close();
    }
}

// Processar ações de ativar/desativar/verificar/desverificar
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && isset($_POST["id"])) {
    $action = $_POST["action"];
    $profissional_id = $_POST["id"];
    
    switch ($action) {
        case 'ativar':
            $sql = "UPDATE ga4_2_profissionais SET ga4_2_status = 'Ativo' WHERE ga4_2_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $profissional_id);
            
            if ($stmt->execute()) {
                $mensagem_sucesso = "Profissional ativado com sucesso!";
            } else {
                $mensagem_erro = "Erro ao ativar profissional: " . $stmt->error;
            }
            $stmt->close();
            break;
            
        case 'desativar':
            $sql = "UPDATE ga4_2_profissionais SET ga4_2_status = 'Inativo' WHERE ga4_2_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $profissional_id);
            
            if ($stmt->execute()) {
                $mensagem_sucesso = "Profissional desativado com sucesso!";
            } else {
                $mensagem_erro = "Erro ao desativar profissional: " . $stmt->error;
            }
            $stmt->close();
            break;
            
        case 'verificar':
            $sql = "UPDATE ga4_2_profissionais SET ga4_2_verificado = 'Sim' WHERE ga4_2_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $profissional_id);
            
            if ($stmt->execute()) {
                $mensagem_sucesso = "Profissional verificado com sucesso!";
            } else {
                $mensagem_erro = "Erro ao verificar profissional: " . $stmt->error;
            }
            $stmt->close();
            break;
            
        case 'desverificar':
            $sql = "UPDATE ga4_2_profissionais SET ga4_2_verificado = 'Não' WHERE ga4_2_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $profissional_id);
            
            if ($stmt->execute()) {
                $mensagem_sucesso = "Verificação do profissional removida com sucesso!";
            } else {
                $mensagem_erro = "Erro ao remover verificação do profissional: " . $stmt->error;
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
$campo_pesquisa = isset($_GET['campo']) ? $_GET['campo'] : 'ga4_2_nome';

// Construir a consulta SQL base
$sql_count = "SELECT COUNT(*) FROM ga4_2_profissionais";
$sql_select = "SELECT ga4_2_id, ga4_2_nome, ga4_2_email, ga4_2_especialidade, ga4_2_crm, ga4_2_status, ga4_2_verificado 
               FROM ga4_2_profissionais";

// Adicionar condições de filtro
$where_conditions = [];
$params = [];
$types = "";

if ($status_filter === 'ativos') {
    $where_conditions[] = "ga4_2_status = 'Ativo'";
} elseif ($status_filter === 'inativos') {
    $where_conditions[] = "ga4_2_status = 'Inativo'";
} elseif ($status_filter === 'verificados') {
    $where_conditions[] = "ga4_2_verificado = 'Sim'";
} elseif ($status_filter === 'nao_verificados') {
    $where_conditions[] = "ga4_2_verificado = 'Não'";
}

if (!empty($search_term)) {
    switch ($campo_pesquisa) {
        case 'ga4_2_id':
            $where_conditions[] = "ga4_2_id = ?";
            $params[] = (int)$search_term;
            $types .= "i";
            break;
        case 'ga4_2_email':
            $where_conditions[] = "ga4_2_email LIKE ?";
            $params[] = "%$search_term%";
            $types .= "s";
            break;
        case 'ga4_2_especialidade':
            $where_conditions[] = "ga4_2_especialidade LIKE ?";
            $params[] = "%$search_term%";
            $types .= "s";
            break;
        case 'ga4_2_nome':
        default:
            $where_conditions[] = "ga4_2_nome LIKE ?";
            $params[] = "%$search_term%";
            $types .= "s";
            break;
    }
}

// Montar a cláusula WHERE
if (!empty($where_conditions)) {
    $sql_count .= " WHERE " . implode(" AND ", $where_conditions);
    $sql_select .= " WHERE " . implode(" AND ", $where_conditions);
}

// Adicionar ordenação e limite
$sql_select .= " ORDER BY ga4_2_nome ASC LIMIT ?, ?";
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
$profissionais = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Contar profissionais por status
$sql_count_status = "SELECT 
                        SUM(CASE WHEN ga4_2_status = 'Ativo' THEN 1 ELSE 0 END) as ativos,
                        SUM(CASE WHEN ga4_2_status = 'Inativo' THEN 1 ELSE 0 END) as inativos,
                        SUM(CASE WHEN ga4_2_verificado = 'Sim' THEN 1 ELSE 0 END) as verificados,
                        SUM(CASE WHEN ga4_2_verificado = 'Não' THEN 1 ELSE 0 END) as nao_verificados,
                        COUNT(*) as total
                      FROM ga4_2_profissionais";
$result_count_status = $conn->query($sql_count_status);
$counts = $result_count_status->fetch_assoc();

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Profissionais - <?php echo htmlspecialchars($config_valor[1]); ?></title>
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
        
        .filter-tab[href*="status=ativos"] {
            border-color: #10b981;
            color: #10b981;
        }
        .filter-tab[href*="status=ativos"].active {
            background-color: #10b981;
            color: white;
        }
        
        .filter-tab[href*="status=inativos"] {
            border-color: #ef4444;
            color: #ef4444;
        }
        .filter-tab[href*="status=inativos"].active {
            background-color: #ef4444;
            color: white;
        }
        
        .filter-tab[href*="status=verificados"] {
            border-color: #0ea5e9;
            color: #0ea5e9;
        }
        .filter-tab[href*="status=verificados"].active {
            background-color: #0ea5e9;
            color: white;
        }
        
        .filter-tab[href*="status=nao_verificados"] {
            border-color: #f59e0b;
            color: #f59e0b;
        }
        .filter-tab[href*="status=nao_verificados"].active {
            background-color: #f59e0b;
            color: white;
        }
    </style>
</head>
<body>

<div class="header">
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
</div> 

    <div class="container">
       
    

        <div class="content-header">
            <h1><i class="fas fa-user-md"></i> Gerenciar Profissionais</h1>
            <p>Visualize e gerencie todos os profissionais cadastrados no sistema</p>
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
                    <i class="fas fa-users"></i> Todos
                    <span class="count"><?php echo $counts['total']; ?></span>
                </a>
                <a href="?status=ativos<?php echo !empty($search_term) ? '&pesquisa=' . urlencode($search_term) . '&campo=' . $campo_pesquisa : ''; ?>" 
                   class="filter-tab <?php echo $status_filter === 'ativos' ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle"></i> Ativos
                    <span class="count"><?php echo $counts['ativos']; ?></span>
                </a>
                <a href="?status=inativos<?php echo !empty($search_term) ? '&pesquisa=' . urlencode($search_term) . '&campo=' . $campo_pesquisa : ''; ?>" 
                   class="filter-tab <?php echo $status_filter === 'inativos' ? 'active' : ''; ?>">
                    <i class="fas fa-times-circle"></i> Inativos
                    <span class="count"><?php echo $counts['inativos']; ?></span>
                </a>
                <a href="?status=verificados<?php echo !empty($search_term) ? '&pesquisa=' . urlencode($search_term) . '&campo=' . $campo_pesquisa : ''; ?>" 
                   class="filter-tab <?php echo $status_filter === 'verificados' ? 'active' : ''; ?>">
                    <i class="fas fa-certificate"></i> Verificados
                    <span class="count"><?php echo $counts['verificados']; ?></span>
                </a>
                <a href="?status=nao_verificados<?php echo !empty($search_term) ? '&pesquisa=' . urlencode($search_term) . '&campo=' . $campo_pesquisa : ''; ?>" 
                   class="filter-tab <?php echo $status_filter === 'nao_verificados' ? 'active' : ''; ?>">
                    <i class="fas fa-exclamation-circle"></i> Não Verificados
                    <span class="count"><?php echo $counts['nao_verificados']; ?></span>
                </a>
            </div>
            
            <div class="admin-actions">
                <a href="adicionar_profissional.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Adicionar Profissional
                </a>
            </div>
        </div>
        
        <form class="admin-search" method="GET" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
            <select name="campo" class="form-select">
                <option value="ga4_2_nome" <?php echo $campo_pesquisa === 'ga4_2_nome' ? 'selected' : ''; ?>>Nome</option>
                <option value="ga4_2_especialidade" <?php echo $campo_pesquisa === 'ga4_2_especialidade' ? 'selected' : ''; ?>>Especialidade</option>
                <option value="ga4_2_email" <?php echo $campo_pesquisa === 'ga4_2_email' ? 'selected' : ''; ?>>Email</option>
                <option value="ga4_2_id" <?php echo $campo_pesquisa === 'ga4_2_id' ? 'selected' : ''; ?>>ID</option>
            </select>
            <input type="text" name="pesquisa" placeholder="Pesquisar profissionais..." 
                   value="<?php echo htmlspecialchars($search_term); ?>">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Buscar
            </button>
        </form>

        <?php if (empty($profissionais)): ?>
            <div class="empty-state">
                <i class="fas fa-user-md"></i>
                <h3>Nenhum profissional encontrado</h3>
                <p>Não há profissionais que correspondam aos critérios de busca.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Profissional</th>
                            <th>Especialidade</th>
                            <th>CRM</th>
                            <th>Status</th>
                            <th>Verificação</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($profissionais as $profissional): ?>
                            <tr>
                                <td><?php echo $profissional['ga4_2_id']; ?></td>
                                <td>
                                    <div class="cliente-info">
                                        <div class="cliente-avatar"><?php echo substr($profissional['ga4_2_nome'], 0, 1); ?></div>
                                        <div class="cliente-details">
                                            <span class="cliente-name"><?php echo htmlspecialchars($profissional['ga4_2_nome']); ?></span>
                                            <span class="cliente-email"><?php echo htmlspecialchars($profissional['ga4_2_email']); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($profissional['ga4_2_especialidade']); ?></td>
                                <td><?php echo htmlspecialchars($profissional['ga4_2_crm']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $profissional['ga4_2_status'] === 'Ativo' ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $profissional['ga4_2_status'] === 'Ativo' ? 'Ativo' : 'Inativo'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $profissional['ga4_2_verificado'] === 'Sim' ? 'status-active' : 'status-pending'; ?>">
                                        <?php echo $profissional['ga4_2_verificado'] === 'Sim' ? 'Verificado' : 'Não Verificado'; ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <a href="visualizar_profissional.php?id=<?php echo $profissional['ga4_2_id']; ?>" class="btn-icon" title="Visualizar">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <a href="editar_profissional.php?id=<?php echo $profissional['ga4_2_id']; ?>" class="btn-icon" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    
                                    <?php if ($profissional['ga4_2_status'] === 'Ativo'): ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="id" value="<?php echo $profissional['ga4_2_id']; ?>">
                                            <input type="hidden" name="action" value="desativar">
                                            <button type="submit" class="btn-icon" title="Desativar">
                                                <i class="fas fa-user-slash"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="id" value="<?php echo $profissional['ga4_2_id']; ?>">
                                            <input type="hidden" name="action" value="ativar">
                                            <button type="submit" class="btn-icon" title="Ativar">
                                                <i class="fas fa-user-check"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($profissional['ga4_2_verificado'] === 'Não'): ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="id" value="<?php echo $profissional['ga4_2_id']; ?>">
                                            <input type="hidden" name="action" value="verificar">
                                            <button type="submit" class="btn-icon" title="Verificar">
                                                <i class="fas fa-certificate"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="id" value="<?php echo $profissional['ga4_2_id']; ?>">
                                            <input type="hidden" name="action" value="desverificar">
                                            <button type="submit" class="btn-icon" title="Remover Verificação">
                                                <i class="fas fa-times-circle"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <button type="button" class="btn-icon btn-delete" title="Excluir" 
                                            onclick="confirmarExclusao(<?php echo $profissional['ga4_2_id']; ?>, '<?php echo addslashes($profissional['ga4_2_nome']); ?>')">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
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
    </div>

    <!-- Modal de confirmação de exclusão -->
    <div id="modal-exclusao" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Confirmar Exclusão</h2>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir o profissional <strong id="nome-profissional"></strong>?</p>
                <p class="text-danger">Esta ação não pode ser desfeita.</p>
            </div>
            <div class="modal-footer">
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <input type="hidden" name="profissional_id" id="profissional_id_excluir">
                    <button type="button" class="btn btn-outline" onclick="fecharModal()">Cancelar</button>
                    <button type="submit" name="excluir_profissional" class="btn btn-danger">Excluir</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Funções para o modal de exclusão
        function confirmarExclusao(id, nome) {
            document.getElementById('profissional_id_excluir').value = id;
            document.getElementById('nome-profissional').textContent = nome;
            document.getElementById('modal-exclusao').css.display = 'block';
        }

        function fecharModal() {
            document.getElementById('modal-exclusao').css.display = 'none';
        }

        // Fechar o modal quando clicar no X
        document.querySelector('.close').addEventListener('click', fecharModal);

        // Fechar o modal quando clicar fora dele
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('modal-exclusao');
            if (event.target === modal) {
                fecharModal();
            }
        });
        
        // Fechar alertas após 5 segundos
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.css.display = 'none';
            });
        }, 5000);
    </script>
</body>
</html>
