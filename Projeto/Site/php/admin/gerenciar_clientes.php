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

// Inicializar variáveis para paginação e filtros
$pagina_atual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$registros_por_pagina = 10;
$offset = ($pagina_atual - 1) * $registros_por_pagina;

// Inicializar variáveis para pesquisa
$termo_pesquisa = isset($_GET['pesquisa']) ? $_GET['pesquisa'] : '';
$campo_pesquisa = isset($_GET['campo']) ? $_GET['campo'] : 'ga4_1_nome';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'todos';

// Processar ações de ativar/desativar
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && isset($_POST["id"])) {
    $action = $_POST["action"];
    $cliente_id = $_POST["id"];
    
    switch ($action) {
        case 'ativar':
            $sql = "UPDATE ga4_1_clientes SET ga4_1_status = 'Ativo' WHERE ga4_1_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $cliente_id);
            
            if ($stmt->execute()) {
                $mensagem_sucesso = "Cliente ativado com sucesso!";
            } else {
                $mensagem_erro = "Erro ao ativar cliente: " . $stmt->error;
            }
            $stmt->close();
            break;
            
        case 'desativar':
            $sql = "UPDATE ga4_1_clientes SET ga4_1_status = 'Inativo' WHERE ga4_1_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $cliente_id);
            
            if ($stmt->execute()) {
                $mensagem_sucesso = "Cliente desativado com sucesso!";
            } else {
                $mensagem_erro = "Erro ao desativar cliente: " . $stmt->error;
            }
            $stmt->close();
            break;
    }
}

// Processar exclusão de cliente
if (isset($_POST['excluir_cliente']) && isset($_POST['cliente_id'])) {
    $cliente_id = $_POST['cliente_id'];
    
    // Verificar se o cliente tem consultas associadas
    $sql = "SELECT COUNT(*) FROM ga4_3_consultas WHERE id_cliente = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $cliente_id);
    $stmt->execute();
    $stmt->bind_result($consultas_count);
    $stmt->fetch();
    $stmt->close();
    
    if ($consultas_count > 0) {
        $mensagem_erro = "Não é possível excluir este cliente pois existem consultas associadas a ele.";
    } else {
        // Excluir cliente
        $sql = "DELETE FROM ga4_1_clientes WHERE ga4_1_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $cliente_id);
        
        if ($stmt->execute()) {
            $mensagem_sucesso = "Cliente excluído com sucesso!";
        } else {
            $mensagem_erro = "Erro ao excluir cliente: " . $conn->error;
        }
        $stmt->close();
    }
}

// Construir a consulta SQL com base nos filtros
$where_conditions = [];
$params = [];
$types = "";

if ($status_filter === 'ativos') {
    $where_conditions[] = "ga4_1_status = 'Ativo'";
} elseif ($status_filter === 'inativos') {
    $where_conditions[] = "ga4_1_status = 'Inativo'";
}

if (!empty($termo_pesquisa)) {
    // Armazenar o termo de pesquisa original para exibição no formulário
    $termo_pesquisa_exibicao = $termo_pesquisa;
    
    // Adicionar wildcards para a consulta SQL
    $termo_pesquisa_sql = "%$termo_pesquisa%";
    
    switch ($campo_pesquisa) {
        case 'ga4_1_id':
            $where_conditions[] = "ga4_1_id = ?";
            $params[] = (int)$termo_pesquisa;
            $types .= "i";
            break;
        case 'ga4_1_cpf':
            $where_conditions[] = "ga4_1_cpf LIKE ?";
            $params[] = $termo_pesquisa_sql;
            $types .= "s";
            break;
        case 'ga4_1_email':
            $where_conditions[] = "ga4_1_email LIKE ?";
            $params[] = $termo_pesquisa_sql;
            $types .= "s";
            break;
        case 'ga4_1_nome':
        default:
            $where_conditions[] = "ga4_1_nome LIKE ?";
            $params[] = $termo_pesquisa_sql;
            $types .= "s";
            break;
    }
} else {
    $termo_pesquisa_exibicao = '';
}

// Montar a cláusula WHERE
$where_clause = "";
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// Contar total de registros para paginação
$sql_count = "SELECT COUNT(*) FROM ga4_1_clientes $where_clause";
$stmt_count = $conn->prepare($sql_count);

if (!empty($params)) {
    $stmt_count->bind_param($types, ...$params);
}

$stmt_count->execute();
$stmt_count->bind_result($total_registros);
$stmt_count->fetch();
$stmt_count->close();

$total_paginas = ceil($total_registros / $registros_por_pagina);

// Obter clientes com paginação
$sql = "SELECT ga4_1_id, ga4_1_cpf, ga4_1_nome, ga4_1_nasc, ga4_1_sexo, ga4_1_tel, ga4_1_email, ga4_1_status 
        FROM ga4_1_clientes 
        $where_clause 
        ORDER BY ga4_1_nome ASC 
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $params[] = $registros_por_pagina;
    $params[] = $offset;
    $types .= "ii";
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param("ii", $registros_por_pagina, $offset);
}

$stmt->execute();
$result = $stmt->get_result();
$clientes = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Contar clientes por status
$sql_count_status = "SELECT 
                        SUM(CASE WHEN ga4_1_status = 'Ativo' THEN 1 ELSE 0 END) as ativos,
                        SUM(CASE WHEN ga4_1_status = 'Inativo' THEN 1 ELSE 0 END) as inativos,
                        COUNT(*) as total
                      FROM ga4_1_clientes";
$result_count_status = $conn->query($sql_count_status);
$counts = $result_count_status->fetch_assoc();

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Clientes - <?php echo htmlspecialchars($config_valor[1]); ?></title>
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
            <h1><i class="fas fa-users"></i> Gerenciar Clientes</h1>
            <p>Visualize e gerencie todos os clientes cadastrados no sistema</p>
        </div>

        <?php if (isset($mensagem_sucesso)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $mensagem_sucesso; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($mensagem_erro)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $mensagem_erro; ?>
            </div>
        <?php endif; ?>

        <div class="admin-filters">
            <div class="filter-tabs">
                <a href="?status=todos<?php echo !empty($termo_pesquisa) ? '&pesquisa=' . urlencode($termo_pesquisa) . '&campo=' . $campo_pesquisa : ''; ?>" 
                   class="filter-tab <?php echo $status_filter === 'todos' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i> Todos
                    <span class="count"><?php echo $counts['total']; ?></span>
                </a>
                <a href="?status=ativos<?php echo !empty($termo_pesquisa) ? '&pesquisa=' . urlencode($termo_pesquisa) . '&campo=' . $campo_pesquisa : ''; ?>" 
                   class="filter-tab <?php echo $status_filter === 'ativos' ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle"></i> Ativos
                    <span class="count"><?php echo $counts['ativos']; ?></span>
                </a>
                <a href="?status=inativos<?php echo !empty($termo_pesquisa) ? '&pesquisa=' . urlencode($termo_pesquisa) . '&campo=' . $campo_pesquisa : ''; ?>" 
                   class="filter-tab <?php echo $status_filter === 'inativos' ? 'active' : ''; ?>">
                    <i class="fas fa-times-circle"></i> Inativos
                    <span class="count"><?php echo $counts['inativos']; ?></span>
                </a>
            </div>
            
            <div class="admin-actions">
                <a href="adicionar_cliente.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Adicionar Cliente
                </a>
            </div>
        </div>
        
        <form class="admin-search" method="GET" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
            <select name="campo" class="form-select">
                <option value="ga4_1_nome" <?php echo $campo_pesquisa === 'ga4_1_nome' ? 'selected' : ''; ?>>Nome</option>
                <option value="ga4_1_cpf" <?php echo $campo_pesquisa === 'ga4_1_cpf' ? 'selected' : ''; ?>>CPF</option>
                <option value="ga4_1_email" <?php echo $campo_pesquisa === 'ga4_1_email' ? 'selected' : ''; ?>>Email</option>
                <option value="ga4_1_id" <?php echo $campo_pesquisa === 'ga4_1_id' ? 'selected' : ''; ?>>ID</option>
            </select>
            <input type="text" name="pesquisa" placeholder="Pesquisar clientes..." value="<?php echo htmlspecialchars($termo_pesquisa_exibicao); ?>">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Buscar
            </button>
        </form>

        <?php if (empty($clientes)): ?>
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <h3>Nenhum cliente encontrado</h3>
                <p>Tente ajustar os critérios de pesquisa ou adicione novos clientes.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>CPF</th>
                            <th>Data Nasc.</th>
                            <th>Sexo</th>
                            <th>Telefone</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientes as $cliente): ?>
                            <tr>
                                <td><?php echo $cliente['ga4_1_id']; ?></td>
                                <td>
                                    <div class="cliente-info">
                                        <div class="cliente-avatar"><?php echo substr($cliente['ga4_1_nome'], 0, 1); ?></div>
                                        <div class="cliente-details">
                                            <span class="cliente-name"><?php echo htmlspecialchars($cliente['ga4_1_nome']); ?></span>
                                            <span class="cliente-email"><?php echo htmlspecialchars($cliente['ga4_1_email']); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($cliente['ga4_1_cpf']); ?></td>
                                <td><?php echo $cliente['ga4_1_nasc'] ? date('d/m/Y', strtotime($cliente['ga4_1_nasc'])) : '-'; ?></td>
                                <td><?php echo htmlspecialchars($cliente['ga4_1_sexo']); ?></td>
                                <td><?php echo htmlspecialchars($cliente['ga4_1_tel']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $cliente['ga4_1_status'] === 'Ativo' ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $cliente['ga4_1_status'] === 'Ativo' ? 'Ativo' : 'Inativo'; ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <a href="visualizar_cliente.php?id=<?php echo $cliente['ga4_1_id']; ?>" class="btn-icon" title="Visualizar">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="editar_cliente.php?id=<?php echo $cliente['ga4_1_id']; ?>" class="btn-icon" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    
                                    <?php if ($cliente['ga4_1_status'] === 'Ativo'): ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="id" value="<?php echo $cliente['ga4_1_id']; ?>">
                                            <input type="hidden" name="action" value="desativar">
                                            <button type="submit" class="btn-icon" title="Desativar">
                                                <i class="fas fa-user-slash"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="id" value="<?php echo $cliente['ga4_1_id']; ?>">
                                            <input type="hidden" name="action" value="ativar">
                                            <button type="submit" class="btn-icon" title="Ativar">
                                                <i class="fas fa-user-check"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <button type="button" class="btn-icon btn-delete" title="Excluir" 
                                            onclick="confirmarExclusao(<?php echo $cliente['ga4_1_id']; ?>, '<?php echo addslashes($cliente['ga4_1_nome']); ?>')">
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
                        <a href="?pagina=1&status=<?php echo $status_filter; ?>&campo=<?php echo $campo_pesquisa; ?>&pesquisa=<?php echo urlencode($termo_pesquisa_exibicao); ?>" class="btn-page">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?pagina=<?php echo $pagina_atual - 1; ?>&status=<?php echo $status_filter; ?>&campo=<?php echo $campo_pesquisa; ?>&pesquisa=<?php echo urlencode($termo_pesquisa_exibicao); ?>" class="btn-page">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    <?php endif; ?>

                    <?php
                    $inicio = max(1, $pagina_atual - 2);
                    $fim = min($inicio + 4, $total_paginas);
                    $inicio = max(1, $fim - 4);

                    for ($i = $inicio; $i <= $fim; $i++):
                    ?>
                        <a href="?pagina=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&campo=<?php echo $campo_pesquisa; ?>&pesquisa=<?php echo urlencode($termo_pesquisa_exibicao); ?>" 
                           class="btn-page <?php echo $i === $pagina_atual ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($pagina_atual < $total_paginas): ?>
                        <a href="?pagina=<?php echo $pagina_atual + 1; ?>&status=<?php echo $status_filter; ?>&campo=<?php echo $campo_pesquisa; ?>&pesquisa=<?php echo urlencode($termo_pesquisa_exibicao); ?>" class="btn-page">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="?pagina=<?php echo $total_paginas; ?>&status=<?php echo $status_filter; ?>&campo=<?php echo $campo_pesquisa; ?>&pesquisa=<?php echo urlencode($termo_pesquisa_exibicao); ?>" class="btn-page">
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
                <p>Tem certeza que deseja excluir o cliente <strong id="nome-cliente"></strong>?</p>
                <p class="text-danger">Esta ação não pode ser desfeita.</p>
            </div>
            <div class="modal-footer">
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <input type="hidden" name="cliente_id" id="cliente_id_excluir">
                    <button type="button" class="btn btn-outline" onclick="fecharModal()">Cancelar</button>
                    <button type="submit" name="excluir_cliente" class="btn btn-danger">Excluir</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Funções para o modal de exclusão
        function confirmarExclusao(id, nome) {
            document.getElementById('cliente_id_excluir').value = id;
            document.getElementById('nome-cliente').textContent = nome;
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
