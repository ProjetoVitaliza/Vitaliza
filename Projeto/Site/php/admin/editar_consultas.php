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

// Inicializar variáveis
$mensagem_sucesso = "";
$mensagem_erro = "";
$consulta = null;
$clientes = [];
$profissionais = [];

// Verificar se o ID da consulta foi fornecido
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $mensagem_erro = "ID da consulta não fornecido";
} else {
    $consulta_id = $_GET['id'];
    
    // Buscar informações da consulta
    $sql = "SELECT c.ga4_3_idconsul, c.id_cliente, c.id_profissional, c.ga4_3_data, c.ga4_3_hora, 
                   c.ga4_3_motivo, c.ga4_3_status, c.ga4_3_motcanc,
                   cl.ga4_1_nome AS nome_cliente, cl.ga4_1_email AS email_cliente,
                   p.ga4_2_nome AS nome_profissional, p.ga4_2_especialidade
            FROM ga4_3_consultas c
            JOIN ga4_1_clientes cl ON c.id_cliente = cl.ga4_1_id
            JOIN ga4_2_profissionais p ON c.id_profissional = p.ga4_2_id
            WHERE c.ga4_3_idconsul = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $consulta_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $mensagem_erro = "Consulta não encontrada";
    } else {
        $consulta = $result->fetch_assoc();
        $stmt->close();
        
        // Buscar todos os clientes ativos para o formulário de edição
        $sql = "SELECT ga4_1_id, ga4_1_nome, ga4_1_email FROM ga4_1_clientes WHERE ga4_1_status = 'Ativo' ORDER BY ga4_1_nome";
        $result = $conn->query($sql);
        $clientes = $result->fetch_all(MYSQLI_ASSOC);
        
        // Buscar todos os profissionais ativos e verificados para o formulário de edição
        $sql = "SELECT ga4_2_id, ga4_2_nome, ga4_2_especialidade FROM ga4_2_profissionais 
                WHERE ga4_2_status = 'Ativo' AND ga4_2_verificado = 'Sim' ORDER BY ga4_2_nome";
        $result = $conn->query($sql);
        $profissionais = $result->fetch_all(MYSQLI_ASSOC);
    }
}

// Processar o formulário de edição quando submetido
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["editar_consulta"])) {
    $consulta_id = $_POST["consulta_id"];
    $cliente_id = $_POST["cliente_id"];
    $profissional_id = $_POST["profissional_id"];
    $data = $_POST["data"];
    $hora = $_POST["hora"];
    $motivo = $_POST["motivo"];
    $status = $_POST["status"];
    $motivo_cancelamento = $_POST["motivo_cancelamento"] ?? null;
    
    // Validação básica
    if (empty($cliente_id) || empty($profissional_id) || empty($data) || empty($hora) || empty($motivo) || empty($status)) {
        $mensagem_erro = "Todos os campos obrigatórios devem ser preenchidos";
    } else {
        // Preparar consulta SQL para atualizar a consulta
        $sql = "UPDATE ga4_3_consultas 
                SET id_cliente = ?, id_profissional = ?, ga4_3_data = ?, ga4_3_hora = ?, 
                    ga4_3_motivo = ?, ga4_3_status = ?, ga4_3_motcanc = ?
                WHERE ga4_3_idconsul = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iisssssi", $cliente_id, $profissional_id, $data, $hora, $motivo, $status, $motivo_cancelamento, $consulta_id);
        
        if ($stmt->execute()) {
            $mensagem_sucesso = "Consulta atualizada com sucesso!";
            
            // Recarregar informações da consulta
            $sql = "SELECT c.ga4_3_idconsul, c.id_cliente, c.id_profissional, c.ga4_3_data, c.ga4_3_hora, 
                         c.ga4_3_motivo, c.ga4_3_status, c.ga4_3_motcanc,
                         cl.ga4_1_nome AS nome_cliente, cl.ga4_1_email AS email_cliente,
                         p.ga4_2_nome AS nome_profissional, p.ga4_2_especialidade
                  FROM ga4_3_consultas c
                  JOIN ga4_1_clientes cl ON c.id_cliente = cl.ga4_1_id
                  JOIN ga4_2_profissionais p ON c.id_profissional = p.ga4_2_id
                  WHERE c.ga4_3_idconsul = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $consulta_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $consulta = $result->fetch_assoc();
        } else {
            $mensagem_erro = "Erro ao atualizar consulta: " . $conn->error;
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Consulta - <?php echo htmlspecialchars($config_valor[1]); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../../style/profissionais.css">
    <link rel="stylesheet" href="../../style/admin.css">
    <link rel="icon" type="image/png" href="../../midia/logo.png">
    <style>
        .editar-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .editar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 30px;
        }
        
        .editar-header-title h1 {
            color: #4f46e5;
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
        }
        
        .editar-user-info {
            display: flex;
            align-items: center;
        }
        
        .editar-user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #4f46e5;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
            margin-right: 10px;
        }
        
        .editar-admin-badge {
            background-color: #3b82f6;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-left: 10px;
        }
        
        .editar-superadmin-badge {
            background-color: #7c3aed;
        }
        
        .editar-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .editar-btn-sm {
            padding: 4px 12px;
            font-size: 0.875rem;
        }
        
        .editar-btn-outline {
            border: 1px solid #4f46e5;
            color: #4f46e5;
            background-color: transparent;
        }
        
        .editar-btn-outline:hover {
            background-color: #f3f4f6;
        }
        
        .editar-btn-primary {
            background-color: #4f46e5;
            color: white;
            border: 1px solid #4f46e5;
        }
        
        .editar-btn-primary:hover {
            background-color: #4338ca;
        }
        
        .editar-btn-voltar {
            margin-left: auto;
        }
        
        .editar-content {
            padding: 20px 0;
        }
        
        .editar-alert {
            padding: 12px 16px;
            margin-bottom: 20px;
            border-radius: 6px;
            display: flex;
            align-items: center;
        }
        
        .editar-alert i {
            margin-right: 10px;
            font-size: 1.1rem;
        }
        
        .editar-alert-success {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .editar-alert-danger {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }
        
        .editar-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .editar-card-header {
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .editar-card-header h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
        }
        
        .editar-card-header h2 i {
            margin-right: 10px;
            color: #4f46e5;
        }
        
        .editar-form {
            padding: 20px;
        }
        
        .editar-form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .editar-form-group {
            flex: 1;
            margin-bottom: 20px;
        }
        
        .editar-label {
            display: block;
            font-weight: 500;
            margin-bottom: 6px;
            color: #374151;
        }
        
        .editar-form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.15s ease-in-out;
        }
        
        .editar-form-control:focus {
            border-color: #4f46e5;
            outline: none;
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.2);
        }
        
        .editar-status-options {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        
        .editar-status-option {
            padding: 8px 16px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }
        
        .editar-status-option i {
            margin-right: 6px;
        }
        
        .editar-status-option.editar-selected {
            background-color: #4f46e5;
            color: white;
            border-color: #4f46e5;
        }
        
        .editar-status-option:hover:not(.editar-selected) {
            background-color: #f3f4f6;
        }
        
        .editar-motivo-cancelamento {
            display: none;
            margin-top: 20px;
            padding: 15px;
            background-color: #f9fafb;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
        }
        
        .editar-form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
        }
        
        .editar-empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            text-align: center;
        }
        
        .editar-empty-state i {
            font-size: 3rem;
            color: #9ca3af;
            margin-bottom: 20px;
        }
        
        .editar-empty-state h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 10px;
        }
        
        .editar-empty-state p {
            color: #6b7280;
            margin-bottom: 20px;
        }
        
        .editar-footer {
            text-align: center;
            padding: 20px 0;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            margin-top: 40px;
        }
        
        /* Status colors */
        .editar-status-option[data-value="Pendente"] {
            border-color: #f59e0b;
            color: #f59e0b;
        }
        .editar-status-option[data-value="Pendente"].editar-selected {
            background-color: #f59e0b;
            color: white;
        }
        
        .editar-status-option[data-value="Aceita"] {
            border-color: #10b981;
            color: #10b981;
        }
        .editar-status-option[data-value="Aceita"].editar-selected {
            background-color: #10b981;
            color: white;
        }
        
        .editar-status-option[data-value="Recusada"] {
            border-color: #ef4444;
            color: #ef4444;
        }
        .editar-status-option[data-value="Recusada"].editar-selected {
            background-color: #ef4444;
            color: white;
        }
        
        .editar-status-option[data-value="Cancelado"] {
            border-color: #dc2626;
            color: #dc2626;
        }
        .editar-status-option[data-value="Cancelado"].editar-selected {
            background-color: #dc2626;
            color: white;
        }
        
        .editar-status-option[data-value="Aguardando confirmação"] {
            border-color: #f97316;
            color: #f97316;
        }
        .editar-status-option[data-value="Aguardando confirmação"].editar-selected {
            background-color: #f97316;
            color: white;
        }
        
        .editar-status-option[data-value="Concluída"] {
            border-color: #0ea5e9;
            color: #0ea5e9;
        }
        .editar-status-option[data-value="Concluída"].editar-selected {
            background-color: #0ea5e9;
            color: white;
        }
        
        .editar-status-option[data-value="Arquivada"] {
            border-color: #6b7280;
            color: #6b7280;
        }
        .editar-status-option[data-value="Arquivada"].editar-selected {
            background-color: #6b7280;
            color: white;
        }
    </style>
</head>
<body>
    <main class="editar-container">
        <div class="editar-header">
            <div class="editar-header-title">
                <h1><?php echo htmlspecialchars($config_valor[1]); ?></h1>
            </div>
            <div class="editar-user-info">
                <div class="editar-user-avatar"><?php echo substr($admin_nome, 0, 1); ?></div>
                <span><?php echo $admin_nome; ?></span>
                <span class="editar-admin-badge <?php echo $nivel_acesso === 'superadmin' ? 'editar-superadmin-badge' : ''; ?>">
                    <?php echo $nivel_acesso === 'superadmin' ? 'Super Admin' : 'Admin'; ?>
                </span>
                <a href="admin_home.php" class="editar-btn editar-btn-outline editar-btn-sm" style="margin-left: 15px;">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <form method="post" action="../conexoes/logout.php" style="margin-left: 15px;">
                    <button type="submit" class="editar-btn editar-btn-outline editar-btn-sm">
                        <i class="fas fa-sign-out-alt"></i> Sair
                    </button>
                </form>
            </div>
        </div>

        <div class="editar-content">
            <?php if ($mensagem_sucesso): ?>
                <div class="editar-alert editar-alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $mensagem_sucesso; ?>
                </div>
            <?php endif; ?>

            <?php if ($mensagem_erro): ?>
                <div class="editar-alert editar-alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $mensagem_erro; ?>
                </div>
            <?php endif; ?>

            <?php if ($consulta): ?>
                <div class="editar-card">
                    <div class="editar-card-header">
                        <h2><i class="fas fa-edit"></i> Editar Consulta #<?php echo $consulta['ga4_3_idconsul']; ?></h2>
                        <a href="gerenciar_consultas.php" class="editar-btn editar-btn-outline editar-btn-voltar">
                            <i class="fas fa-arrow-left"></i> ‌  Voltar para listagem
                        </a>
                    </div>
                    
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . $consulta['ga4_3_idconsul']; ?>" class="editar-form">
                        <input type="hidden" name="consulta_id" value="<?php echo $consulta['ga4_3_idconsul']; ?>">
                        
                        <div class="editar-form-row">
                            <div class="editar-form-group">
                                <label for="cliente_id" class="editar-label">Cliente</label>
                                <select name="cliente_id" id="cliente_id" class="editar-form-control" required>
                                    <option value="">Selecione um cliente</option>
                                    <?php foreach ($clientes as $cliente): ?>
                                        <option value="<?php echo $cliente['ga4_1_id']; ?>" <?php echo ($cliente['ga4_1_id'] == $consulta['id_cliente']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cliente['ga4_1_nome']); ?> (<?php echo htmlspecialchars($cliente['ga4_1_email']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="editar-form-group">
                                <label for="profissional_id" class="editar-label">Profissional</label>
                                <select name="profissional_id" id="profissional_id" class="editar-form-control" required>
                                    <option value="">Selecione um profissional</option>
                                    <?php foreach ($profissionais as $profissional): ?>
                                        <option value="<?php echo $profissional['ga4_2_id']; ?>" <?php echo ($profissional['ga4_2_id'] == $consulta['id_profissional']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($profissional['ga4_2_nome']); ?> (<?php echo htmlspecialchars($profissional['ga4_2_especialidade']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="editar-form-row">
                            <div class="editar-form-group">
                                <label for="data" class="editar-label">Data da Consulta</label>
                                <input type="date" id="data" name="data" class="editar-form-control" value="<?php echo $consulta['ga4_3_data']; ?>" required>
                            </div>
                            
                            <div class="editar-form-group">
                                <label for="hora" class="editar-label">Hora da Consulta</label>
                                <input type="time" id="hora" name="hora" class="editar-form-control" value="<?php echo $consulta['ga4_3_hora']; ?>" required>
                            </div>
                        </div>
                        
                        <div class="editar-form-group">
                            <label for="motivo" class="editar-label">Motivo da Consulta</label>
                            <textarea id="motivo" name="motivo" class="editar-form-control" rows="3" required><?php echo htmlspecialchars($consulta['ga4_3_motivo']); ?></textarea>
                        </div>
                        
                        <div class="editar-form-group">
                            <label class="editar-label">Status da Consulta</label>
                            <input type="hidden" id="status" name="status" value="<?php echo $consulta['ga4_3_status']; ?>">
                            
                            <div class="editar-status-options">
                                <div class="editar-status-option <?php echo ($consulta['ga4_3_status'] === 'Pendente') ? 'editar-selected' : ''; ?>" data-value="Pendente">
                                    <i class="fas fa-clock"></i> Pendente
                                </div>
                                <div class="editar-status-option <?php echo ($consulta['ga4_3_status'] === 'Aceita') ? 'editar-selected' : ''; ?>" data-value="Aceita">
                                    <i class="fas fa-check"></i> Aceita
                                </div>
                                <div class="editar-status-option <?php echo ($consulta['ga4_3_status'] === 'Recusada') ? 'editar-selected' : ''; ?>" data-value="Recusada">
                                    <i class="fas fa-times"></i> Recusada
                                </div>
                                <div class="editar-status-option <?php echo ($consulta['ga4_3_status'] === 'Cancelado') ? 'editar-selected' : ''; ?>" data-value="Cancelado">
                                    <i class="fas fa-ban"></i> Cancelado
                                </div>
                                <div class="editar-status-option <?php echo ($consulta['ga4_3_status'] === 'Aguardando confirmação') ? 'editar-selected' : ''; ?>" data-value="Aguardando confirmação">
                                    <i class="fas fa-hourglass-half"></i> Aguardando confirmação
                                </div>
                                <div class="editar-status-option <?php echo ($consulta['ga4_3_status'] === 'Concluída') ? 'editar-selected' : ''; ?>" data-value="Concluída">
                                    <i class="fas fa-check-double"></i> Concluída
                                </div>
                                <div class="editar-status-option <?php echo ($consulta['ga4_3_status'] === 'Arquivada') ? 'editar-selected' : ''; ?>" data-value="Arquivada">
                                    <i class="fas fa-archive"></i> Arquivada
                                </div>
                            </div>
                        </div>
                        
                        <div id="motivo-cancelamento" class="editar-motivo-cancelamento" <?php echo (in_array($consulta['ga4_3_status'], ['Recusada', 'Cancelado'])) ? 'style="display:block"' : ''; ?>>
                            <label for="motivo_cancelamento" class="editar-label">Motivo do Cancelamento/Recusa</label>
                            <textarea id="motivo_cancelamento" name="motivo_cancelamento" class="editar-form-control" rows="3"><?php echo htmlspecialchars($consulta['ga4_3_motcanc'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="editar-form-actions" style="margin-top: 30px;">
                            <a href="gerenciar_consultas.php" class="editar-btn editar-btn-outline">Cancelar</a>
                            <button type="submit" name="editar_consulta" class="editar-btn editar-btn-primary">
                                <i class="fas fa-save"></i> ‌ Salvar Alterações
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="editar-empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3>Consulta não encontrada</h3>
                    <p>A consulta solicitada não existe ou não foi encontrada.</p>
                    <a href="gerenciar_consultas.php" class="editar-btn editar-btn-primary">Voltar para a lista de consultas</a>
                </div>
            <?php endif; ?>
        </div>

        <footer class="editar-footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($config_valor[1]); ?> - Todos os direitos reservados</p>
        </footer>
    </main>
    <script>
// Espera que o DOM esteja totalmente carregado
document.addEventListener('DOMContentLoaded', function() {
    // Manipulação das opções de status
    const statusOptions = document.querySelectorAll('.editar-status-option');
    const statusInput = document.getElementById('status');
    const motivoCancelamentoDiv = document.getElementById('motivo-cancelamento');
    
    statusOptions.forEach(option => {
        option.addEventListener('click', function() {
            // Remover a classe 'editar-selected' de todas as opções
            statusOptions.forEach(opt => opt.classList.remove('editar-selected'));
            
            // Adicionar a classe 'editar-selected' à opção clicada
            this.classList.add('editar-selected');
            
            // Atualizar o valor do input hidden
            const statusValue = this.getAttribute('data-value');
            statusInput.value = statusValue;
            
            // Mostrar ou ocultar o campo de motivo de cancelamento
            if (statusValue === 'Recusada' || statusValue === 'Cancelado') {
                motivoCancelamentoDiv.css.display = 'block';
            } else {
                motivoCancelamentoDiv.css.display = 'none';
            }
        });
    });
    
    // Validação do formulário antes de enviar
    document.querySelector('form').addEventListener('submit', function(event) {
        const cliente = document.getElementById('cliente_id').value;
        const profissional = document.getElementById('profissional_id').value;
        const data = document.getElementById('data').value;
        const hora = document.getElementById('hora').value;
        const motivo = document.getElementById('motivo').value;
        const status = document.getElementById('status').value;
        
        if (!cliente || !profissional || !data || !hora || !motivo || !status) {
            event.preventDefault();
            alert('Por favor, preencha todos os campos obrigatórios');
        }
        
        // Validar se o motivo de cancelamento está preenchido quando necessário
        if ((status === 'Recusada' || status === 'Cancelado') && 
            document.getElementById('motivo_cancelamento').value.trim() === '') {
            event.preventDefault();
            alert('Por favor, informe o motivo do cancelamento/recusa');
        }
    });
    
    // Animação para mensagens de alerta
    const alertMessages = document.querySelectorAll('.editar-alert');
    
    if (alertMessages.length > 0) {
        // Fade out das mensagens de alerta após 5 segundos
        setTimeout(function() {
            alertMessages.forEach(function(alert) {
                alert.css.transition = 'opacity 1s ease-out';
                alert.css.opacity = '0';
                
                setTimeout(function() {
                    alert.css.display = 'none';
                }, 1000);
            });
        }, 5000);
    }
    
    // Formato de data para uma melhor experiência do usuário
    const dataInput = document.getElementById('data');
    if (dataInput) {
        // Garantir que a data mínima seja hoje para evitar agendamentos no passado
        const hoje = new Date().toISOString().split('T')[0];
        dataInput.setAttribute('min', hoje);
    }
});
</script>