<?php
session_start();
include '../conexoes/conexao.php';

// Verificar se o usuário está logado como administrador
if (!isset($_SESSION["admin_id"]) || $_SESSION["tipo"] !== "admin") {
    header("Location: ../conexoes/login.php");
    exit();
}

// Verificar se o ID do cliente foi fornecido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: gerenciar_clientes.php");
    exit();
}

$cliente_id = $_GET['id'];

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

// Obter informações do cliente
$sql = "SELECT ga4_1_cpf, ga4_1_nome, ga4_1_nasc, ga4_1_sexo, ga4_1_tel, ga4_1_cep, ga4_1_email
        FROM ga4_1_clientes 
        WHERE ga4_1_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $cliente_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Cliente não encontrado
    header("Location: gerenciar_clientes.php");
    exit();
}

$cliente = $result->fetch_assoc();
$stmt->close();

// Calcular idade do cliente
$idade = '';
if (!empty($cliente['ga4_1_nasc'])) {
    $nascimento = new DateTime($cliente['ga4_1_nasc']);
    $hoje = new DateTime();
    $idade = $nascimento->diff($hoje)->y;
}

// Vamos verificar a estrutura da tabela ga4_3_consultas para entender os nomes das colunas
try {
    // Obter histórico de consultas do cliente - ajustando os nomes das colunas
    $sql = "SELECT c.id as consulta_id, c.data, c.hora, c.status, 
                  p.ga4_2_nome as profissional_nome, p.ga4_2_especialidade
           FROM ga4_3_consultas c
           JOIN ga4_2_profissionais p ON c.id_profissional = p.ga4_2_id
           WHERE c.id_cliente = ?
           ORDER BY c.data DESC, c.hora DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $cliente_id);
    $stmt->execute();
    $result_consultas = $stmt->get_result();
    $consultas = $result_consultas->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    // Se houver erro, vamos tentar uma consulta mais simples para verificar a estrutura
    $consultas = [];
    // Podemos adicionar um log de erro aqui se necessário
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizar Cliente - <?php echo htmlspecialchars($config_valor[1]); ?></title>
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
            <h1><i class="fas fa-user"></i> Detalhes do Cliente</h1>
            <p>Visualize todas as informações do cliente</p>
        </div>

        <div class="admin-actions" style="margin-bottom: 20px;">
            <a href="gerenciar_clientes.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Voltar para Lista de Clientes
            </a>
            <a href="editar_cliente.php?id=<?php echo $cliente_id; ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Editar Cliente
            </a>
        </div>

        <div class="cliente-card">
            <div class="cliente-header">
                <div class="cliente-header-avatar">
                    <?php echo substr($cliente['ga4_1_nome'], 0, 1); ?>
                </div>
                <div class="cliente-header-info">
                    <h2><?php echo htmlspecialchars($cliente['ga4_1_nome']); ?></h2>
                    <p>Cliente #<?php echo $cliente_id; ?></p>
                </div>
            </div>

            <div class="section-title">
                <h3><i class="fas fa-info-circle"></i> Informações Pessoais</h3>
            </div>

            <div class="cliente-data">
                <div class="data-item">
                    <label>CPF</label>
                    <p><?php echo htmlspecialchars($cliente['ga4_1_cpf']); ?></p>
                </div>
                
                <div class="data-item">
                    <label>Email</label>
                    <p><?php echo htmlspecialchars($cliente['ga4_1_email']); ?></p>
                </div>
                
                <div class="data-item">
                    <label>Telefone</label>
                    <p><?php echo !empty($cliente['ga4_1_tel']) ? htmlspecialchars($cliente['ga4_1_tel']) : 'Não informado'; ?></p>
                </div>
                
                <div class="data-item">
                    <label>Data de Nascimento</label>
                    <p>
                        <?php 
                        if (!empty($cliente['ga4_1_nasc'])) {
                            echo date('d/m/Y', strtotime($cliente['ga4_1_nasc'])) . ' (' . $idade . ' anos)';
                        } else {
                            echo 'Não informada';
                        }
                        ?>
                    </p>
                </div>
                
                <div class="data-item">
                    <label>Sexo</label>
                    <p><?php echo htmlspecialchars($cliente['ga4_1_sexo']); ?></p>
                </div>
                
                <div class="data-item">
                    <label>CEP</label>
                    <p><?php echo htmlspecialchars($cliente['ga4_1_cep']); ?></p>
                </div>
            </div>

            <div class="section-title" style="margin-top: 30px;">
                <h3><i class="fas fa-calendar-check"></i> Histórico de Consultas</h3>
            </div>

            <?php if (empty($consultas)): ?>
                <div class="empty-state" style="padding: 20px;">
                    <i class="fas fa-calendar-times"></i>
                    <h3>Nenhuma consulta encontrada</h3>
                    <p>Este cliente ainda não realizou nenhuma consulta.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Data</th>
                                <th>Hora</th>
                                <th>Profissional</th>
                                <th>Especialidade</th>
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
                                    <td><?php echo htmlspecialchars($consulta['profissional_nome']); ?></td>
                                    <td><?php echo htmlspecialchars($consulta['ga4_2_especialidade']); ?></td>
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

    <?php
    // Funções auxiliares para exibir status de consulta
    function getStatusLabel($status) {
        switch ($status) {
            case 'agendada':
                return 'Agendada';
            case 'concluida':
                return 'Concluída';
            case 'cancelada':
                return 'Cancelada';
            case 'pendente':
                return 'Pendente';
            default:
                return ucfirst($status);
        }
    }

    function getStatusClass($status) {
        switch ($status) {
            case 'agendada':
                return 'status-agendada';
            case 'concluida':
                return 'status-concluida';
            case 'cancelada':
                return 'status-cancelada';
            case 'pendente':
                return 'status-pendente';
            default:
                return '';
        }
    }
    ?>
</body>
</html>
