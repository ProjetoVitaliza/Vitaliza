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

// Verificar se o ID da consulta foi fornecido
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: gerenciar_consultas.php");
    exit();
}

$consulta_id = $_GET['id'];
$mensagem_sucesso = "";
$mensagem_erro = "";

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

// Buscar detalhes da consulta
$sql = "SELECT c.ga4_3_idconsul, c.ga4_3_data, c.ga4_3_hora, c.ga4_3_motivo, c.ga4_3_status, c.ga4_3_motcanc,
        cl.ga4_1_id, cl.ga4_1_nome, cl.ga4_1_email, cl.ga4_1_tel, cl.ga4_1_cpf, cl.ga4_1_nasc, cl.ga4_1_sexo,
        p.ga4_2_id, p.ga4_2_nome, p.ga4_2_especialidade, p.ga4_2_email, p.ga4_2_tel, p.ga4_2_crm
        FROM ga4_3_consultas c
        JOIN ga4_1_clientes cl ON c.id_cliente = cl.ga4_1_id
        JOIN ga4_2_profissionais p ON c.id_profissional = p.ga4_2_id
        WHERE c.ga4_3_idconsul = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $consulta_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $conn->close();
    header("Location: gerenciar_consultas.php");
    exit();
}

$consulta = $result->fetch_assoc();
$stmt->close();

// Calcular idade do cliente
$data_nascimento = new DateTime($consulta['ga4_1_nasc']);
$hoje = new DateTime();
$idade = $data_nascimento->diff($hoje)->y;

// Determinar classe style do status
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

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizar Consulta - <?php echo htmlspecialchars($config_valor[1]); ?></title>
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
                <h1><i class="fas fa-calendar-check"></i> Detalhes da Consulta</h1>
                <p>Visualize informações detalhadas sobre esta consulta</p>
            </div>

            <div class="admin-actions" style="margin-bottom: 20px;">
                    <a href="gerenciar_consultas.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Voltar para Lista de Consultas
                    </a>
                    <a href="editar_consutas.php?id=<?php echo $consulta_id; ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Editar Consulta
                    </a>
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

            <div class="consulta-card">
                <div class="consulta-header">
                    <h2>Consulta #<?php echo $consulta['ga4_3_idconsul']; ?></h2>
                    <div class="consulta-status">
                        <span class="status-badge <?php echo $status_class; ?>">
                            <?php echo $consulta['ga4_3_status']; ?>
                        </span>
                    </div>
                </div>
                
                <div class="consulta-body">
                    <div class="consulta-section">
                        <h3><i class="fas fa-calendar-alt"></i> Informações da Consulta</h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Data</span>
                                <span class="info-value"><?php echo date("d/m/Y", strtotime($consulta['ga4_3_data'])); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Hora</span>
                                <span class="info-value"><?php echo date("H:i", strtotime($consulta['ga4_3_hora'])); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Status</span>
                                <span class="info-value">
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo $consulta['ga4_3_status']; ?>
                                    </span>
                                </span>
                            </div>
                        </div>
                        
                        <div class="motivo-box">
                            <span class="info-label">Motivo da Consulta</span>
                            <p class="info-value"><?php echo nl2br(htmlspecialchars($consulta['ga4_3_motivo'])); ?></p>
                        </div> <br>
                        
                        <?php if (!empty($consulta['ga4_3_motcanc']) && ($consulta['ga4_3_status'] === 'Recusada' || $consulta['ga4_3_status'] === 'Cancelado')): ?>
                            <div class="motivo-cancelamento">
                                <span class="info-label">Motivo do Cancelamento/Recusa</span>
                                <p class="info-value"><?php echo nl2br(htmlspecialchars($consulta['ga4_3_motcanc'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="consulta-section">
                        <h3><i class="fas fa-user"></i> Informações do Cliente</h3>
                        <div class="cliente-card">
                            <div class="cliente-header">
                                <div class="avatar"><?php echo substr($consulta['ga4_1_nome'], 0, 1); ?></div>
                                <div class="name-email">
                                    <div class="name"><?php echo htmlspecialchars($consulta['ga4_1_nome']); ?></div>
                                    <div class="email"><?php echo htmlspecialchars($consulta['ga4_1_email']); ?></div>
                                </div>
                            </div>
                            
                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="info-label">CPF</span>
                                    <span class="info-value"><?php echo htmlspecialchars($consulta['ga4_1_cpf']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Telefone</span>
                                    <span class="info-value"><?php echo htmlspecialchars($consulta['ga4_1_tel']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Data de Nascimento</span>
                                    <span class="info-value"><?php echo date("d/m/Y", strtotime($consulta['ga4_1_nasc'])) . ' (' . $idade . ' anos)'; ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Sexo</span>
                                    <span class="info-value"><?php echo htmlspecialchars($consulta['ga4_1_sexo']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="consulta-section">
                        <h3><i class="fas fa-user-md"></i> Informações do Profissional</h3>
                        <div class="profissional-card">
                            <div class="profissional-header">
                                <div class="avatar"><?php echo substr($consulta['ga4_2_nome'], 0, 1); ?></div>
                                <div class="name-email">
                                    <div class="name"><?php echo htmlspecialchars($consulta['ga4_2_nome']); ?></div>
                                    <div class="email"><?php echo htmlspecialchars($consulta['ga4_2_email']); ?></div>
                                </div>
                            </div>
                            
                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="info-label">CRM</span>
                                    <span class="info-value"><?php echo htmlspecialchars($consulta['ga4_2_crm']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Especialidade</span>
                                    <span class="info-value"><?php echo htmlspecialchars($consulta['ga4_2_especialidade']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Telefone</span>
                                    <span class="info-value"><?php echo htmlspecialchars($consulta['ga4_2_tel']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                </div>
            </div>
        </div>

        <div class="footer-home">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($config_valor[1]); ?> - Todos os direitos reservados</p>
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
        // Manipulação do modal de recusa
        const modalRecusar = document.getElementById('modal-recusar');
        const closeRecusar = modalRecusar.querySelector('.close');
        const btnCancelarRecusa = document.getElementById('btn-cancelar-recusa');
        
        // Fechar modal ao clicar no X ou no botão cancelar
        closeRecusar.addEventListener('click', () => {
            modalRecusar.css.display = 'none';
        });
        
        btnCancelarRecusa.addEventListener('click', () => {
            modalRecusar.css.display = 'none';
        });
        
        // Fechar modal ao clicar fora dele
        window.addEventListener('click', (event) => {
            if (event.target === modalRecusar) {
                modalRecusar.css.display = 'none';
            }
        });
        
        // Função para abrir o modal de recusa
        function abrirModalRecusar(id) {
            document.getElementById('id-consulta-recusar').value = id;
            modalRecusar.css.display = 'block';
        }
        
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