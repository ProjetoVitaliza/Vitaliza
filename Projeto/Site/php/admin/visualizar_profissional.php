<?php
session_start();
include '../conexoes/conexao.php';

// Verificar se o usuário está logado como administrador
if (!isset($_SESSION["admin_id"]) || $_SESSION["tipo"] !== "admin") {
    header("Location: ../conexoes/login.php");
    exit();
}

// Verificar se o ID do profissional foi fornecido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: gerenciar_profissionais.php");
    exit();
}

$profissional_id = $_GET['id'];

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

// Obter informações do profissional
$sql = "SELECT ga4_2_crm, ga4_2_cpf, ga4_2_especialidade, ga4_2_nome, ga4_2_nasc, ga4_2_sexo, ga4_2_tel, ga4_2_cep, ga4_2_email, ga4_2_status, ga4_2_verificado, ga4_2_fotocert
        FROM ga4_2_profissionais 
        WHERE ga4_2_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $profissional_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Profissional não encontrado
    header("Location: gerenciar_profissionais.php");
    exit();
}

$profissional = $result->fetch_assoc();
$stmt->close();

// Calcular idade do profissional
$idade = '';
if (!empty($profissional['ga4_2_nasc'])) {
    $nascimento = new DateTime($profissional['ga4_2_nasc']);
    $hoje = new DateTime();
    $idade = $nascimento->diff($hoje)->y;
}

// Processar ações de ativar/desativar/verificar
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && isset($_POST["id"])) {
    $action = $_POST["action"];
    $prof_id = $_POST["id"];
    
    switch ($action) {
        case 'ativar':
            $sql = "UPDATE ga4_2_profissionais SET ga4_2_status = 'Ativo' WHERE ga4_2_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $prof_id);
            
            if ($stmt->execute()) {
                $mensagem_sucesso = "Profissional ativado com sucesso!";
                $profissional['ga4_2_status'] = 'Ativo'; // Atualiza o status na página
            } else {
                $mensagem_erro = "Erro ao ativar profissional: " . $stmt->error;
            }
            $stmt->close();
            break;
            
        case 'desativar':
            $sql = "UPDATE ga4_2_profissionais SET ga4_2_status = 'Inativo' WHERE ga4_2_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $prof_id);
            
            if ($stmt->execute()) {
                $mensagem_sucesso = "Profissional desativado com sucesso!";
                $profissional['ga4_2_status'] = 'Inativo'; // Atualiza o status na página
            } else {
                $mensagem_erro = "Erro ao desativar profissional: " . $stmt->error;
            }
            $stmt->close();
            break;
            
        case 'verificar':
            $sql = "UPDATE ga4_2_profissionais SET ga4_2_verificado = 'Sim' WHERE ga4_2_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $prof_id);
            
            if ($stmt->execute()) {
                $mensagem_sucesso = "Profissional verificado com sucesso!";
                $profissional['ga4_2_verificado'] = 'Sim'; // Atualiza o status na página
            } else {
                $mensagem_erro = "Erro ao verificar profissional: " . $stmt->error;
            }
            $stmt->close();
            break;
    }
}

// Obter histórico de consultas do profissional
try {
    $sql = "SELECT c.ga4_3_idconsul as consulta_id, c.ga4_3_data as data, c.ga4_3_hora as hora, c.ga4_3_status as status, 
                  cl.ga4_1_nome as cliente_nome, cl.ga4_1_id as cliente_id
           FROM ga4_3_consultas c
           JOIN ga4_1_clientes cl ON c.id_cliente = cl.ga4_1_id
           WHERE c.id_profissional = ?
           ORDER BY c.ga4_3_data DESC, c.ga4_3_hora DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $profissional_id);
    $stmt->execute();
    $result_consultas = $stmt->get_result();
    $consultas = $result_consultas->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $consultas = [];
}

// Contar consultas por status
$total_consultas = count($consultas);
$consultas_pendentes = 0;
$consultas_concluidas = 0;
$consultas_canceladas = 0;

foreach ($consultas as $consulta) {
    if ($consulta['status'] == 'Pendente' || $consulta['status'] == 'Aceita' || $consulta['status'] == 'Aguardando confirmação') {
        $consultas_pendentes++;
    } elseif ($consulta['status'] == 'Concluída') {
        $consultas_concluidas++;
    } elseif ($consulta['status'] == 'Cancelado' || $consulta['status'] == 'Recusada') {
        $consultas_canceladas++;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizar Profissional - <?php echo htmlspecialchars($config_valor[1]); ?></title>
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
            <h1><i class="fas fa-user-md"></i> Detalhes do Profissional</h1>
            <p>Visualize todas as informações do profissional</p>
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

        <div class="admin-actions" style="margin-bottom: 20px;">
            <a href="gerenciar_profissionais.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Voltar para Lista de Profissionais
            </a>
            <a href="editar_profissional.php?id=<?php echo $profissional_id; ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Editar Profissional
            </a>
        </div>

        <div class="profissional-card">
            <div class="profissional-header">
                <div class="profissional-header-avatar">
                    <?php echo substr($profissional['ga4_2_nome'], 0, 1); ?>
                </div>
                <div class="profissional-header-info">
                    <h2><?php echo htmlspecialchars($profissional['ga4_2_nome']); ?></h2>
                    <p class="profissional-especialidade"><?php echo htmlspecialchars($profissional['ga4_2_especialidade']); ?></p>
                    <div class="profissional-badges">
                        <span class="status-badge <?php echo $profissional['ga4_2_status'] === 'Ativo' ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo $profissional['ga4_2_status'] === 'Ativo' ? 'Ativo' : 'Inativo'; ?>
                        </span>
                        <span class="status-badge <?php echo $profissional['ga4_2_verificado'] === 'Sim' ? 'status-verified' : 'status-unverified'; ?>">
                            <?php echo $profissional['ga4_2_verificado'] === 'Sim' ? 'Verificado' : 'Não Verificado'; ?>
                        </span>
                    </div>
                </div>
                <div class="profissional-actions">
                    <?php if ($profissional['ga4_2_status'] === 'Ativo'): ?>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="id" value="<?php echo $profissional_id; ?>">
                            <input type="hidden" name="action" value="desativar">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-user-slash"></i> Desativar
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="id" value="<?php echo $profissional_id; ?>">
                            <input type="hidden" name="action" value="ativar">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-user-check"></i> Ativar
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <?php if ($profissional['ga4_2_verificado'] === 'Não'): ?>
                        <form method="post" style="display:inline; margin-left: 10px;">
                            <input type="hidden" name="id" value="<?php echo $profissional_id; ?>">
                            <input type="hidden" name="action" value="verificar">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-certificate"></i> Verificar
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="section-title">
                <h3><i class="fas fa-info-circle"></i> Informações Profissionais</h3>
            </div>

            <div class="profissional-data">
                <div class="data-item">
                    <label>CRM</label>
                    <p><?php echo htmlspecialchars($profissional['ga4_2_crm']); ?></p>
                </div>
                
                <div class="data-item">
                    <label>Especialidade</label>
                    <p><?php echo htmlspecialchars($profissional['ga4_2_especialidade']); ?></p>
                </div>
                
                <div class="data-item">
                    <label>Email</label>
                    <p><?php echo htmlspecialchars($profissional['ga4_2_email']); ?></p>
                </div>
                
                <div class="data-item">
                    <label>Telefone</label>
                    <p><?php echo !empty($profissional['ga4_2_tel']) ? htmlspecialchars($profissional['ga4_2_tel']) : 'Não informado'; ?></p>
                </div>
            </div>

            <div class="section-title" style="margin-top: 30px;">
                <h3><i class="fas fa-user"></i> Informações Pessoais</h3>
            </div>

            <div class="profissional-data">
                <div class="data-item">
                    <label>CPF</label>
                    <p><?php echo htmlspecialchars($profissional['ga4_2_cpf']); ?></p>
                </div>
                
                <div class="data-item">
                    <label>Data de Nascimento</label>
                    <p>
                        <?php 
                        if (!empty($profissional['ga4_2_nasc'])) {
                            echo date('d/m/Y', strtotime($profissional['ga4_2_nasc'])) . ' (' . $idade . ' anos)';
                        } else {
                            echo 'Não informada';
                        }
                        ?>
                    </p>
                </div>
                
                <div class="data-item">
                    <label>Sexo</label>
                    <p><?php echo htmlspecialchars($profissional['ga4_2_sexo']); ?></p>
                </div>
                
                <div class="data-item">
                    <label>CEP</label>
                    <p><?php echo htmlspecialchars($profissional['ga4_2_cep']); ?></p>
                </div>
            </div>

            <?php if (!empty($profissional['ga4_2_fotocert'])): ?>
            <div class="section-title" style="margin-top: 30px;">
                <h3><i class="fas fa-file-alt"></i> Certificado Profissional</h3>
            </div>

            <div class="certificate-container">
                <a href="../../uploads/certificados/<?php echo $profissional['ga4_2_fotocert']; ?>" target="_blank" class="btn btn-outline">
                    <i class="fas fa-eye"></i> Visualizar Certificado
                </a>
            </div>
            <?php endif; ?>

            <div class="section-title" style="margin-top: 30px;">
                <h3><i class="fas fa-chart-bar"></i> Estatísticas de Consultas</h3>
            </div>

            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_consultas; ?></div>
                    <div class="stat-label">Total de Consultas</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $consultas_pendentes; ?></div>
                    <div class="stat-label">Consultas Pendentes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $consultas_concluidas; ?></div>
                    <div class="stat-label">Consultas Concluídas</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $consultas_canceladas; ?></div>
                    <div class="stat-label">Consultas Canceladas</div>
                </div>
            </div>

            <div class="section-title" style="margin-top: 30px;">
                <h3><i class="fas fa-calendar-check"></i> Histórico de Consultas</h3>
            </div>

            <?php if (empty($consultas)): ?>
                <div class="empty-state" style="padding: 20px;">
                    <i class="fas fa-calendar-times"></i>
                    <h3>Nenhuma consulta encontrada</h3>
                    <p>Este profissional ainda não realizou nenhuma consulta.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Data</th>
                                <th>Hora</th>
                                <th>Cliente</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($consultas as $consulta): ?>
                                <tr>
                                    <td><?php echo $consulta['consulta_id']; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($consulta['data'])); ?></td>
                                    <td><?php echo $consulta['hora']; ?></td>
                                    <td>
                                        <a href="visualizar_cliente.php?id=<?php echo $consulta['cliente_id']; ?>" class="cliente-link">
                                            <?php echo htmlspecialchars($consulta['cliente_nome']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo getStatusClass($consulta['status']); ?>">
                                            <?php echo getStatusLabel($consulta['status']); ?>
                                        </span>
                                    </td>
                                    <td class="actions">
                                        <a href="visualizar_consulta.php?id=<?php echo $consulta['consulta_id']; ?>" class="btn-icon" title="Visualizar Consulta">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="footer-home">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($config_valor[1]); ?> - Todos os direitos reservados</p>
        </div>
    </div>

    <style>
        /* Estilos específicos para visualizar_profissional.php */
        .section-title {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .section-title h3 {
            color: var(--text-color);
            font-size: 1.2rem;
            margin: 0;
            display: flex;
            align-items: center;
        }
        
        .section-title h3 i {
            margin-right: 8px;
            color: var(--admin-primary);
        }
        
        .profissional-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .profissional-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap;
        }
        
        .profissional-header-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background-color: var(--admin-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: bold;
            flex-shrink: 0;
        }
        
        .profissional-header-info {
            flex: 1;
        }
        
        .profissional-header-info h2 {
            margin: 0 0 8px 0;
            color: var(--text-color);
            font-size: 1.5rem;
        }
        
        .profissional-especialidade {
            margin: 0 0 8px 0;
            color: var(--text-muted);
            font-size: 1.1rem;
        }
        
        .profissional-badges {
            display: flex;
            gap: 10px;
        }
        
        .profissional-actions {
            display: flex;
            gap: 10px;
            margin-left: auto;
            flex-wrap: wrap;
        }
        
        .profissional-data {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .data-item {
            margin-bottom: 15px;
        }
        
        .data-item label {
            display: block;
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .data-item p {
            margin: 0;
            font-weight: 500;
            color: var(--text-color);
        }
        
        /* Status badges */
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-active {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .status-inactive {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        
        .status-verified {
            background-color: #e0f2fe;
            color: #0369a1;
        }
        
        .status-unverified {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .status-agendada, .status-aceita, .status-pendente, .status-aguardando {
            background-color: #e0f2fe;
            color: #0369a1;
        }
        
        .status-concluida {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .status-cancelada, .status-recusada {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        
        /* Estatísticas */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--admin-primary);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        
        /* Certificado */
        .certificate-container {
            margin-bottom: 30px;
        }
        
        /* Cliente link */
        .cliente-link {
            color: var(--admin-primary);
            text-decoration: none;
            font-weight: 500;
        }
        
        .cliente-link:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .profissional-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .profissional-header-avatar {
                margin: 0 auto;
            }
            
            .profissional-header-info {
                width: 100%;
                text-align: center;
            }
            
            .profissional-badges {
                justify-content: center;
            }
            
            .profissional-actions {
                width: 100%;
                justify-content: center;
                margin-top: 15px;
            }
            
            .profissional-data {
                grid-template-columns: 1fr;
            }
            
            .stats-container {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        /* Estado vazio */
        .empty-state {
            text-align: center;
            padding: 30px 20px;
            background-color: #f8fafc;
            border-radius: 8px;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #94a3b8;
            margin-bottom: 15px;
        }
        
        .empty-state h3 {
            margin: 0 0 10px 0;
            color: #334155;
        }
        
        .empty-state p {
            color: #64748b;
            max-width: 400px;
            margin: 0 auto;
        }
    </style>

    <script>
        // Fechar alertas após 5 segundos
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.css.display = 'none';
            });
        }, 5000);
    </script>

    <?php
    // Funções auxiliares para exibir status de consulta
    function getStatusLabel($status) {
        switch ($status) {
            case 'Aceita':
                return 'Aceita';
            case 'Pendente':
                return 'Pendente';
            case 'Concluída':
                return 'Concluída';
            case 'Cancelado':
                return 'Cancelada';
            case 'Recusada':
                return 'Recusada';
            case 'Aguardando confirmação':
                return 'Aguardando';
            case 'Arquivada':
                return 'Arquivada';
            default:
                return ucfirst($status);
        }
    }

    function getStatusClass($status) {
        switch ($status) {
            case 'Aceita':
                return 'status-aceita';
            case 'Pendente':
                return 'status-pendente';
            case 'Concluída':
                return 'status-concluida';
            case 'Cancelado':
                return 'status-cancelada';
            case 'Recusada':
                return 'status-recusada';
            case 'Aguardando confirmação':
                return 'status-aguardando';
            case 'Arquivada':
                return 'status-arquivada';
            default:
                return '';
        }
    }
    ?>
</body>
</html>
