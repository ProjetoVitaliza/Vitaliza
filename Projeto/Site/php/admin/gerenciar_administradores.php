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

// Processar exclusão de administrador
if (isset($_POST['excluir_administrador']) && isset($_POST['admin_id'])) {
    $admin_excluir_id = $_POST['admin_id'];
    
    // Impedir que um administrador exclua a si mesmo
    if ($admin_excluir_id == $admin_id) {
        $mensagem_erro = "Você não pode excluir seu próprio usuário.";
    } 
    // Impedir que administradores comuns excluam super administradores
    else {
        // Verificar o nível de acesso do administrador a ser excluído
        $sql = "SELECT ga4_5_nivel_acesso FROM ga4_5_administradores WHERE ga4_5_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $admin_excluir_id);
        $stmt->execute();
        $stmt->bind_result($nivel_excluir);
        $stmt->fetch();
        $stmt->close();
        
        if ($nivel_acesso !== 'superadmin' && $nivel_excluir === 'superadmin') {
            $mensagem_erro = "Apenas Super Administradores podem excluir outros Super Administradores.";
        } else {
            // Excluir administrador
            $sql = "DELETE FROM ga4_5_administradores WHERE ga4_5_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $admin_excluir_id);
            
            if ($stmt->execute()) {
                $mensagem_sucesso = "Administrador excluído com sucesso!";
            } else {
                $mensagem_erro = "Erro ao excluir administrador: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Configuração de paginação
$registros_por_pagina = 10;
$pagina_atual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_atual - 1) * $registros_por_pagina;

// Filtros
$acesso_filter = isset($_GET['acesso']) ? $_GET['acesso'] : 'todos';
$search_term = isset($_GET['pesquisa']) ? $_GET['pesquisa'] : '';
$campo_pesquisa = isset($_GET['campo']) ? $_GET['campo'] : 'ga4_5_nome';

// Construir a consulta SQL base
$sql_count = "SELECT COUNT(*) FROM ga4_5_administradores";
$sql_select = "SELECT ga4_5_id, ga4_5_nome, ga4_5_email, ga4_5_nivel_acesso FROM ga4_5_administradores";

// Adicionar condições de filtro
$where_conditions = [];
$params = [];
$types = "";

if ($acesso_filter === 'admin') {
    $where_conditions[] = "ga4_5_nivel_acesso = 'admin'";
} elseif ($acesso_filter === 'superadmin') {
    $where_conditions[] = "ga4_5_nivel_acesso = 'superadmin'";
}

if (!empty($search_term)) {
    switch ($campo_pesquisa) {
        case 'ga4_5_id':
            $where_conditions[] = "ga4_5_id = ?";
            $params[] = (int)$search_term;
            $types .= "i";
            break;
        case 'ga4_5_email':
            $where_conditions[] = "ga4_5_email LIKE ?";
            $params[] = "%$search_term%";
            $types .= "s";
            break;
        case 'ga4_5_nome':
        default:
            $where_conditions[] = "ga4_5_nome LIKE ?";
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
$sql_select .= " ORDER BY ga4_5_id ASC LIMIT ?, ?";  // Alterado de ga4_5_nome para ga4_5_id
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
$administradores = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Contar administradores por nível
$sql_count_nivel = "SELECT 
                      SUM(CASE WHEN ga4_5_nivel_acesso = 'admin' THEN 1 ELSE 0 END) as admins,
                      SUM(CASE WHEN ga4_5_nivel_acesso = 'superadmin' THEN 1 ELSE 0 END) as superadmins,
                      COUNT(*) as total
                    FROM ga4_5_administradores";
$result_count_nivel = $conn->query($sql_count_nivel);
$counts = $result_count_nivel->fetch_assoc();

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Administradores - <?php echo htmlspecialchars($config_valor[1]); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../../style/profissionais.css">
    <link rel="stylesheet" href="../../style/admin.css">
    <link rel="icon" type="image/png" href="../../midia/logo.png">
    <style>
        /* Cores específicas para as abas de filtro */
        .filter-tab[href*="acesso=todos"] {
            border-color: #4f46e5;
            color: #4f46e5;
        }
        .filter-tab[href*="acesso=todos"].active {
            background-color: #4f46e5;
            color: white;
        }
        
        .filter-tab[href*="acesso=admin"] {
            border-color: #10b981;
            color: #10b981;
        }
        .filter-tab[href*="acesso=admin"].active {
            background-color: #10b981;
            color: white;
        }
        
        .filter-tab[href*="acesso=superadmin"] {
            border-color: #4f46e5;
            color: #4f46e5;
        }
        .filter-tab[href*="acesso=superadmin"].active {
            background-color: #4f46e5;
            color: white;
        }
        
        /* Badge de SuperAdmin */
        .superadmin-badge {
            background-color: #4f46e5 !important;
        }
        
        /* Badge de Admin */
        .admin-badge {
            background-color: #10b981;
            color: white;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }
    </style>
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
            <h1><i class="fas fa-users-cog"></i> Gerenciar Administradores</h1>
            <p>Adicione, edite ou exclua administradores do sistema.</p>
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
                <a href="?acesso=todos<?php echo !empty($search_term) ? '&pesquisa=' . urlencode($search_term) . '&campo=' . $campo_pesquisa : ''; ?>" 
                   class="filter-tab <?php echo $acesso_filter === 'todos' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i> Todos
                    <span class="count"><?php echo $counts['total']; ?></span>
                </a>
                <a href="?acesso=admin<?php echo !empty($search_term) ? '&pesquisa=' . urlencode($search_term) . '&campo=' . $campo_pesquisa : ''; ?>" 
                   class="filter-tab <?php echo $acesso_filter === 'admin' ? 'active' : ''; ?>">
                    <i class="fas fa-user-shield"></i> Administradores
                    <span class="count"><?php echo $counts['admins']; ?></span>
                </a>
                <a href="?acesso=superadmin<?php echo !empty($search_term) ? '&pesquisa=' . urlencode($search_term) . '&campo=' . $campo_pesquisa : ''; ?>" 
                   class="filter-tab <?php echo $acesso_filter === 'superadmin' ? 'active' : ''; ?>">
                    <i class="fas fa-user-tie"></i> Super Administradores
                    <span class="count"><?php echo $counts['superadmins']; ?></span>
                </a>
            </div>
            
            <div class="admin-actions">
                <a href="adicionar_administrador.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Adicionar Administrador
                </a>
            </div>
        </div>
        
        <form class="admin-search" method="GET" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <input type="hidden" name="acesso" value="<?php echo $acesso_filter; ?>">
            <select name="campo" class="form-select">
                <option value="ga4_5_nome" <?php echo $campo_pesquisa === 'ga4_5_nome' ? 'selected' : ''; ?>>Nome</option>
                <option value="ga4_5_email" <?php echo $campo_pesquisa === 'ga4_5_email' ? 'selected' : ''; ?>>Email</option>
                <option value="ga4_5_id" <?php echo $campo_pesquisa === 'ga4_5_id' ? 'selected' : ''; ?>>ID</option>
            </select>
            <input type="text" name="pesquisa" placeholder="Pesquisar administradores..." 
                   value="<?php echo htmlspecialchars($search_term); ?>">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Buscar
            </button>
        </form>

        <?php if (empty($administradores)): ?>
            <div class="empty-state">
                <i class="fas fa-users-cog"></i>
                <h3>Nenhum administrador encontrado</h3>
                <p>Não há administradores que correspondam aos critérios de busca.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Nível de Acesso</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($administradores as $admin_item): ?>
                            <tr>
                                <td><?php echo $admin_item['ga4_5_id']; ?></td>
                                <td>
                                    <div class="cliente-info">
                                        <div class="cliente-avatar"><?php echo substr($admin_item['ga4_5_nome'], 0, 1); ?></div>
                                        <div class="cliente-details">
                                            <span class="cliente-name"><?php echo htmlspecialchars($admin_item['ga4_5_nome']); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($admin_item['ga4_5_email']); ?></td>
                                <td>
                                    <span class="admin-badge <?php echo $admin_item['ga4_5_nivel_acesso'] === 'superadmin' ? 'superadmin-badge' : ''; ?>">
                                        <?php echo $admin_item['ga4_5_nivel_acesso'] === 'superadmin' ? 'Super Admin' : 'Admin'; ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <a href="visualizar_administrador.php?id=<?php echo $admin_item['ga4_5_id']; ?>" class="btn-icon" title="Visualizar">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <!-- Apenas superadmin pode editar outro superadmin -->
                                    <?php if ($nivel_acesso === 'superadmin' || $admin_item['ga4_5_nivel_acesso'] !== 'superadmin'): ?>
                                        <a href="editar_administrador.php?id=<?php echo $admin_item['ga4_5_id']; ?>" class="btn-icon" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <!-- Não mostrar botão de excluir para o próprio usuário -->
                                    <?php if ($admin_item['ga4_5_id'] != $admin_id): ?>
                                        <!-- Apenas superadmin pode excluir outro superadmin -->
                                        <?php if ($nivel_acesso === 'superadmin' || $admin_item['ga4_5_nivel_acesso'] !== 'superadmin'): ?>
                                            <button type="button" class="btn-icon btn-delete" title="Excluir" 
                                                    onclick="confirmarExclusao(<?php echo $admin_item['ga4_5_id']; ?>, '<?php echo addslashes($admin_item['ga4_5_nome']); ?>')">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        <?php endif; ?>
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
                        <a href="?pagina=1&acesso=<?php echo $acesso_filter; ?>&campo=<?php echo $campo_pesquisa; ?>&pesquisa=<?php echo urlencode($search_term); ?>" class="btn-page">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?pagina=<?php echo $pagina_atual - 1; ?>&acesso=<?php echo $acesso_filter; ?>&campo=<?php echo $campo_pesquisa; ?>&pesquisa=<?php echo urlencode($search_term); ?>" class="btn-page">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php
                    $inicio = max(1, $pagina_atual - 2);
                    $fim = min($inicio + 4, $total_paginas);
                    $inicio = max(1, $fim - 4);

                    for ($i = $inicio; $i <= $fim; $i++):
                    ?>
                        <a href="?pagina=<?php echo $i; ?>&acesso=<?php echo $acesso_filter; ?>&campo=<?php echo $campo_pesquisa; ?>&pesquisa=<?php echo urlencode($search_term); ?>" 
                           class="btn-page <?php echo $i === $pagina_atual ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($pagina_atual < $total_paginas): ?>
                        <a href="?pagina=<?php echo $pagina_atual + 1; ?>&acesso=<?php echo $acesso_filter; ?>&campo=<?php echo $campo_pesquisa; ?>&pesquisa=<?php echo urlencode($search_term); ?>" class="btn-page">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="?pagina=<?php echo $total_paginas; ?>&acesso=<?php echo $acesso_filter; ?>&campo=<?php echo $campo_pesquisa; ?>&pesquisa=<?php echo urlencode($search_term); ?>" class="btn-page">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <footer class="footer-home">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($config_valor[1]); ?> - Todos os direitos reservados</p>
        </footer>
    </main>

    <!-- Modal de confirmação de exclusão -->
    <div id="modal-exclusao" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Confirmar Exclusão</h2>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir o administrador <strong id="nome-admin"></strong>?</p>
                <p class="text-danger">Esta ação não pode ser desfeita.</p>
            </div>
            <div class="modal-footer">
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <input type="hidden" name="admin_id" id="admin_id_excluir">
                    <button type="button" class="btn btn-outline" onclick="fecharModal()">Cancelar</button>
                    <button type="submit" name="excluir_administrador" class="btn btn-danger">Excluir</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Funções para o modal de exclusão
        function confirmarExclusao(id, nome) {
            document.getElementById('admin_id_excluir').value = id;
            document.getElementById('nome-admin').textContent = nome;
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