<?php
session_start();
include '../conexoes/conexao.php'; // Arquivo de conexão com o banco

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

// Busca todos os profissionais disponíveis
$sql = "SELECT ga4_2_id, ga4_2_nome, ga4_2_especialidade FROM ga4_2_profissionais ORDER BY ga4_2_especialidade, ga4_2_nome";
$result = $conn->query($sql);

// Agrupar profissionais por especialidade para o dropdown
$profissionais_por_especialidade = [];
while ($row = $result->fetch_assoc()) {
    if (!isset($profissionais_por_especialidade[$row['ga4_2_especialidade']])) {
        $profissionais_por_especialidade[$row['ga4_2_especialidade']] = [];
    }
    $profissionais_por_especialidade[$row['ga4_2_especialidade']][] = $row;
}

$message = "";
$message_type = "";
$form_data = [
    'profissional_id' => '',
    'data' => '',
    'hora' => '',
    'motivo' => ''
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Armazenar dados do formulário para mantê-los após o submit
    $form_data = [
        'profissional_id' => $_POST['profissional_id'] ?? '',
        'data' => $_POST['data'] ?? '',
        'hora' => $_POST['hora'] ?? '',
        'motivo' => $_POST['motivo'] ?? ''
    ];

    $profissional_id = $_POST['profissional_id'];
    $data = $_POST['data'];
    $hora = $_POST['hora'];
    $motivo = $_POST['motivo'];

    // Validação do lado do servidor
    $hoje = date('Y-m-d');
    if ($data < $hoje) {
        $message = "Não é possível agendar consultas para datas passadas.";
        $message_type = "error";
    } else {
        // Verificar se já existe uma consulta do mesmo cliente no mesmo horário
        $sql_check = "SELECT COUNT(*) FROM ga4_3_consultas WHERE id_cliente = ? AND ga4_3_data = ? AND ga4_3_hora = ? AND ga4_3_status != 'Cancelada' AND ga4_3_status != 'Recusada'";
        $stmt = $conn->prepare($sql_check);
        $stmt->bind_param("iss", $cliente_id, $data, $hora);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            $message = "Você já possui uma consulta agendada para este mesmo horário.";
            $message_type = "warning";
        } else {
            // Insere a solicitação de consulta no banco de dados
            $sql_insert = "INSERT INTO ga4_3_consultas (id_cliente, id_profissional, ga4_3_data, ga4_3_hora, ga4_3_motivo, ga4_3_status) VALUES (?, ?, ?, ?, ?, 'Pendente')";
            $stmt = $conn->prepare($sql_insert);
            $stmt->bind_param("iisss", $cliente_id, $profissional_id, $data, $hora, $motivo);

            if ($stmt->execute()) {
                $message = "Sua solicitação de consulta foi enviada com sucesso! Aguarde a confirmação do profissional.";
                $message_type = "success";
                
                // Limpar dados do formulário após sucesso
                $form_data = [
                    'profissional_id' => '',
                    'data' => '',
                    'hora' => '',
                    'motivo' => ''
                ];
            } else {
                $message = "Ocorreu um erro ao enviar sua solicitação. Por favor, tente novamente.";
                $message_type = "error";
            }

            $stmt->close();
        }
    }
}

// Obter horários de funcionamento gerais
$horarios_disponiveis = [
    '08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '11:00', '11:30',
    '13:00', '13:30', '14:00', '14:30', '15:00', '15:30', '16:00', '16:30', '17:00'
];

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitar Consulta - <?php echo htmlspecialchars($config_valor[1]); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../../style/clientes.css">
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
                <div class="user-avatar"><?php echo substr($cliente_nome, 0, 1); ?></div>
                <span><?php echo $cliente_nome; ?></span>
                <form method="post" action="../conexoes/logout.php" style="margin-left: 15px;">
                    <button type="submit" class="btn btn-outline btn-sm">
                        <i class="fas fa-sign-out-alt"></i> Sair
                    </button>
                </form>
            </div>
        </nav>    
    </div>
    
    <div class="container">
        

        <div class="form-container">
            <h2><i class="fas fa-calendar-plus" style="color: var(--primary-color); margin-right: 10px;"></i>Solicitar Consulta</h2>
            <p class="text-muted" style="margin-bottom: 20px;">Preencha o formulário abaixo para solicitar uma consulta com um profissional de saúde</p>
            
            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" id="consulta-form">
                <div class="form-group">
                    <label for="profissional_id"><i class="fas fa-user-md"></i> Escolha um Profissional:</label>
                    <select name="profissional_id" id="profissional_id" required class="form-control">
                        <option value="">Selecione um profissional</option>
                        <?php foreach ($profissionais_por_especialidade as $especialidade => $profissionais): ?>
                            <optgroup label="<?php echo htmlspecialchars($especialidade); ?>">
                                <?php foreach ($profissionais as $profissional): ?>
                                    <option value="<?php echo $profissional['ga4_2_id']; ?>" <?php echo ($form_data['profissional_id'] == $profissional['ga4_2_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($profissional['ga4_2_nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="data"><i class="fas fa-calendar-alt"></i> Data:</label>
                    <input type="date" name="data" id="data" required class="form-control" 
                           min="<?php echo date('Y-m-d'); ?>" value="<?php echo $form_data['data']; ?>">
                    <small class="text-muted">Selecione uma data a partir de hoje</small>
                </div>

                <div class="form-group">
                    <label for="hora"><i class="fas fa-clock"></i> Hora:</label>
                    <select name="hora" id="hora" required class="form-control">
                        <option value="">Selecione um horário</option>
                        <?php foreach ($horarios_disponiveis as $horario): ?>
                            <option value="<?php echo $horario; ?>" <?php echo ($form_data['hora'] == $horario) ? 'selected' : ''; ?>>
                                <?php echo $horario; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Horários disponíveis de segunda a sexta</small>
                </div>

                <div class="form-group">
                    <label for="motivo"><i class="fas fa-comment-medical"></i> Motivo da Consulta:</label>
                    <textarea name="motivo" id="motivo" required class="form-control" rows="4" 
                              placeholder="Descreva o motivo da sua consulta..."><?php echo $form_data['motivo']; ?></textarea>
                    <small class="text-muted">Seja específico sobre suas necessidades para ajudar o profissional</small>
                </div>

                <div class="form-submit">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Solicitar Consulta
                    </button>
                </div>
            </form>
        </div>

        <div style="text-align: center; margin-top: 20px;">
            <a href="cliente_home.php" class="back-button">
                <i class="fas fa-arrow-left"></i> Voltar para a Página Inicial
            </a>
        </div>

        <div class="footer-home">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($config_valor[1]); ?> - Todos os direitos reservados</p>
        </div>
    </div>

    <script>
        // Validação do lado do cliente
        document.getElementById('consulta-form').addEventListener('submit', function(event) {
            const dataSelect = document.getElementById('data');
            const horaSelect = document.getElementById('hora');
            const motivoText = document.getElementById('motivo');
            
            const hoje = new Date();
            hoje.setHours(0, 0, 0, 0);
            
            const dataSelecionada = new Date(dataSelect.value);
            dataSelecionada.setHours(0, 0, 0, 0);
            
            if (dataSelecionada < hoje) {
                alert('Não é possível agendar consultas para datas passadas.');
                event.preventDefault();
                return false;
            }
            
            if (motivoText.value.trim().length < 10) {
                alert('Por favor, forneça uma descrição mais detalhada do motivo da consulta (mínimo 10 caracteres).');
                event.preventDefault();
                return false;
            }
            
            return true;
        });
        
        // Destacar dias úteis no calendário
        document.getElementById('data').addEventListener('input', function() {
            const dataSelecionada = new Date(this.value);
            const diaSemana = dataSelecionada.getDay();
            
            // 0 = Domingo, 6 = Sábado
            if (diaSemana === 0 || diaSemana === 6) {
                alert('Atenção: Os atendimentos são realizados apenas de segunda a sexta-feira. Por favor, selecione outra data.');
            }
        });
    </script>
</body>
</html>
