<?php
session_start();
include('../conexoes/conexao.php'); // Inclua seu arquivo de conexão com o banco de dados

// Verificar se é uma requisição AJAX para buscar novas mensagens
if (isset($_GET['action']) && $_GET['action'] == 'get_messages') {
    $conn = conectarBanco();
    $profissional_id = $_SESSION['profissional_id'];
    $cliente_id = $_GET['cliente_id'];
    
    $query = "SELECT m.*, c.ga4_1_nome AS cliente_nome, p.ga4_2_nome AS profissional_nome 
              FROM ga4_4_mensagens m
              JOIN ga4_1_clientes c ON m.ga4_4_id_cliente = c.ga4_1_id
              JOIN ga4_2_profissionais p ON m.ga4_4_id_profissional = p.ga4_2_id
              WHERE m.ga4_4_id_profissional = $profissional_id AND m.ga4_4_id_cliente = $cliente_id
              ORDER BY m.ga4_4_data_envio ASC";
    $result = mysqli_query($conn, $query);
    $mensagens = mysqli_fetch_all($result, MYSQLI_ASSOC);
    
    // Atualizar status das mensagens do cliente para "lida" quando o profissional visualiza
    $update_query = "UPDATE ga4_4_mensagens 
                    SET ga4_4_status_mensagem = 'lida' 
                    WHERE ga4_4_id_profissional = $profissional_id 
                    AND ga4_4_id_cliente = $cliente_id 
                    AND ga4_4_enviado_por = 'cliente' 
                    AND ga4_4_status_mensagem != 'lida'";
    mysqli_query($conn, $update_query);
    
    // Retornar as mensagens em formato JSON
    header('Content-Type: application/json');
    echo json_encode($mensagens);
    exit;
}

// Verificar se é uma requisição AJAX para enviar mensagem
if (isset($_POST['action']) && $_POST['action'] == 'send_message') {
    $conn = conectarBanco();
    $profissional_id = $_SESSION['profissional_id'];
    $cliente_id = $_POST['cliente_id'];
    $mensagem = mysqli_real_escape_string($conn, $_POST['mensagem']);
    
    $query = "INSERT INTO ga4_4_mensagens (ga4_4_id_cliente, ga4_4_id_profissional, ga4_4_mensagem, ga4_4_enviado_por, ga4_4_data_envio, ga4_4_status_mensagem) 
              VALUES ('$cliente_id', '$profissional_id', '$mensagem', 'profissional', NOW(), 'não entregue')";
    $result = mysqli_query($conn, $query);
    
    if ($result) {
        $message_id = mysqli_insert_id($conn);
        
        // Retornar a mensagem recém-inserida
        $query = "SELECT m.*, c.ga4_1_nome AS cliente_nome, p.ga4_2_nome AS profissional_nome 
                  FROM ga4_4_mensagens m
                  JOIN ga4_1_clientes c ON m.ga4_4_id_cliente = c.ga4_1_id
                  JOIN ga4_2_profissionais p ON m.ga4_4_id_profissional = p.ga4_2_id
                  WHERE m.ga4_4_id = $message_id";
        $result = mysqli_query($conn, $query);
        $mensagem = mysqli_fetch_assoc($result);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => $mensagem]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false]);
    }
    exit;
}

// Verificar se é uma requisição para atualizar o status da mensagem
if (isset($_POST['action']) && $_POST['action'] == 'update_status') {
    $conn = conectarBanco();
    $message_id = $_POST['message_id'];
    $new_status = $_POST['status'];
    
    $query = "UPDATE ga4_4_mensagens SET ga4_4_status_mensagem = '$new_status' WHERE ga4_4_id = $message_id";
    $result = mysqli_query($conn, $query);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => $result ? true : false]);
    exit;
}

$conn = conectarBanco(); // Obtenha a conexão com o banco de dados

// Verifique se o profissional está logado
if (!isset($_SESSION['profissional_id'])) {
    header('Location: login_profissional.php');
    exit();
}

$profissional_id = $_SESSION['profissional_id'];

// Obtenha o nome do profissional
$query = "SELECT ga4_2_nome FROM ga4_2_profissionais WHERE ga4_2_id = $profissional_id";
$result = mysqli_query($conn, $query);
$profissional = mysqli_fetch_assoc($result);
$profissional_nome = $profissional['ga4_2_nome'];

// Verifique se um novo cliente foi selecionado - CORRIGIDO
if (isset($_POST['cliente_id']) && !empty($_POST['cliente_id'])) {
    $_SESSION['cliente_selecionado'] = $_POST['cliente_id'];
}

// Obtenha o cliente selecionado da sessão - CORRIGIDO
$cliente_id = isset($_SESSION['cliente_selecionado']) ? $_SESSION['cliente_selecionado'] : null;

$mensagens = [];

// Carregamento inicial das mensagens - CORRIGIDO
if ($cliente_id) {
    $query = "SELECT m.*, c.ga4_1_nome AS cliente_nome, p.ga4_2_nome AS profissional_nome 
              FROM ga4_4_mensagens m
              JOIN ga4_1_clientes c ON m.ga4_4_id_cliente = c.ga4_1_id
              JOIN ga4_2_profissionais p ON m.ga4_4_id_profissional = p.ga4_2_id
              WHERE m.ga4_4_id_profissional = $profissional_id 
              AND m.ga4_4_id_cliente = $cliente_id
              ORDER BY m.ga4_4_data_envio ASC";
    
    $result = mysqli_query($conn, $query);
    $mensagens = mysqli_fetch_all($result, MYSQLI_ASSOC);
    
    // Atualizar status das mensagens do cliente para "lida" quando o profissional visualiza
    $update_query = "UPDATE ga4_4_mensagens 
                    SET ga4_4_status_mensagem = 'lida' 
                    WHERE ga4_4_id_profissional = $profissional_id 
                    AND ga4_4_id_cliente = $cliente_id 
                    AND ga4_4_enviado_por = 'cliente' 
                    AND ga4_4_status_mensagem != 'lida'";
    mysqli_query($conn, $update_query);
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat com Clientes</title>
    <link rel="stylesheet" href="../../style/chat.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="icon" type="image/png" href="../../midia/logo.png">
    <style>
        /* Estilos para os ícones de status */
        .status-icon {
            margin-left: 5px;
            font-size: 0.8em;
        }
        .status-nao-entregue {
            color: #9ca3af; /* Cinza */
        }
        .status-entregue {
            color: #3b82f6; /* Azul */
        }
        .status-lida {
            color: #10b981; /* Verde */
        }
    </style>
</head>
<body>
    <main class="container">
        <h2>Chat com Clientes</h2>
        
        <div class="user-info">
            <div class="user-avatar"><?php echo substr($profissional_nome, 0, 1); ?></div>
            <p>Você está logado como <strong><?php echo $profissional_nome; ?></strong></p>
        </div>
        
        <div class="select-container">
            <form method="POST" action="chat_profissional.php">
                <label for="cliente_id">Selecione um cliente para conversar:</label>
                <select name="cliente_id" id="cliente_id" onchange="this.form.submit()">
                    <option value="">Escolha um cliente</option>
                    <?php
                    $query = "SELECT ga4_1_id, ga4_1_nome FROM ga4_1_clientes";
                    $result = mysqli_query($conn, $query);
                    while ($cliente = mysqli_fetch_assoc($result)) {
                        $selected = ($cliente_id == $cliente['ga4_1_id']) ? 'selected' : '';
                        echo "<option value='{$cliente['ga4_1_id']}' $selected>{$cliente['ga4_1_nome']}</option>";
                    }
                    ?>
                </select>
            </form>
        </div>
        
        <div class="chat-box" id="chat-box">
            <?php if ($cliente_id): ?>
                <div id="mensagens-container">
                    <?php foreach ($mensagens as $mensagem): ?>
                        <div class="mensagem <?php echo ($mensagem['ga4_4_enviado_por'] == 'profissional') ? 'cliente' : 'profissional'; ?>" data-id="<?php echo $mensagem['ga4_4_id']; ?>">
                            <strong>
                                <?php 
                                if ($mensagem['ga4_4_enviado_por'] == 'profissional') {
                                    echo $mensagem['profissional_nome'];
                                } else {
                                    echo $mensagem['cliente_nome'];
                                }
                                ?>:
                            </strong> 
                            <?php echo $mensagem['ga4_4_mensagem']; ?> 
                            <em class="data">
                                <?php echo date('d/m/Y H:i', strtotime($mensagem['ga4_4_data_envio'])); ?>
                                <?php if ($mensagem['ga4_4_enviado_por'] == 'profissional'): ?>
                                    <span class="status-icon <?php echo 'status-' . str_replace(' ', '-', $mensagem['ga4_4_status_mensagem']); ?>">
                                        <?php 
                                        if ($mensagem['ga4_4_status_mensagem'] == 'não entregue') {
                                            echo '<i class="fas fa-clock" title="Não entregue"></i>';
                                        } elseif ($mensagem['ga4_4_status_mensagem'] == 'entregue') {
                                            echo '<i class="fas fa-check" title="Entregue"></i>';
                                        } elseif ($mensagem['ga4_4_status_mensagem'] == 'lida') {
                                            echo '<i class="fas fa-check-double" title="Lida"></i>';
                                        }
                                        ?>
                                    </span>
                                <?php endif; ?>
                            </em>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="select-prompt">Por favor, selecione um cliente para iniciar o chat.</p>
            <?php endif; ?>
        </div>
        
        <form id="mensagemForm" <?php echo !$cliente_id ? 'style="display:none;"' : ''; ?>>
            <div class="message-input-container">
                <textarea name="mensagem" id="mensagem" placeholder="Digite sua mensagem..." required></textarea>
            </div>
            <button type="submit" id="enviar-btn">Enviar</button>
        </form>
        
        <a href="profissional_home.php" class="btn-voltar">Voltar</a>
    </main>

    <script>
        // Função para rolar o chat para o final
        function rolarParaFinal() {
            var chatBox = document.getElementById('chat-box');
            chatBox.scrollTop = chatBox.scrollHeight;
        }
        
        // Rola o chat para o final quando a página carrega
        window.onload = function() {
            rolarParaFinal();
        };
        
        // Função para formatar a data
        function formatarData(dataString) {
            const data = new Date(dataString);
            return data.toLocaleDateString('pt-BR') + ' ' + 
                   data.toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'});
        }
        
        // Função para obter o ícone de status
        function getStatusIcon(status) {
            if (status === 'não entregue') {
                return '<i class="fas fa-clock" title="Não entregue"></i>';
            } else if (status === 'entregue') {
                return '<i class="fas fa-check" title="Entregue"></i>';
            } else if (status === 'lida') {
                return '<i class="fas fa-check-double" title="Lida"></i>';
            }
            return '';
        }
        
        // Função para criar elemento de mensagem
        function criarElementoMensagem(mensagem) {
            const div = document.createElement('div');
            div.className = `mensagem ${mensagem.ga4_4_enviado_por == 'profissional' ? 'cliente' : 'profissional'}`;
            div.setAttribute('data-id', mensagem.ga4_4_id);
            
            const strong = document.createElement('strong');
            strong.textContent = mensagem.ga4_4_enviado_por == 'profissional' ? 
                                 mensagem.profissional_nome + ': ' : 
                                 mensagem.cliente_nome + ': ';
            
            const texto = document.createTextNode(mensagem.ga4_4_mensagem + ' ');
            
            const em = document.createElement('em');
            em.className = 'data';
            em.innerHTML = formatarData(mensagem.ga4_4_data_envio);
            
            // Adicionar ícone de status para mensagens enviadas pelo profissional
            if (mensagem.ga4_4_enviado_por == 'profissional') {
                const statusSpan = document.createElement('span');
                statusSpan.className = `status-icon status-${mensagem.ga4_4_status_mensagem.replace(' ', '-')}`;
                statusSpan.innerHTML = getStatusIcon(mensagem.ga4_4_status_mensagem);
                em.appendChild(statusSpan);
            }
            
            div.appendChild(strong);
            div.appendChild(texto);
            div.appendChild(em);
            
            return div;
        }
        
        // Variável para armazenar o número de mensagens atual
        let numeroMensagensAtual = <?php echo count($mensagens); ?>;
        
        // Função para buscar novas mensagens
        function buscarNovasMensagens() {
            if (!document.hidden && <?php echo !empty($cliente_id) ? 'true' : 'false'; ?>) {
                fetch('chat_profissional.php?action=get_messages&cliente_id=<?php echo $cliente_id; ?>')
                    .then(response => response.json())
                    .then(mensagens => {
                        if (mensagens.length > numeroMensagensAtual) {
                            // Há novas mensagens
                            const container = document.getElementById('mensagens-container');
                            container.innerHTML = ''; // Limpa o container
                            
                            mensagens.forEach(mensagem => {
                                container.appendChild(criarElementoMensagem(mensagem));
                            });
                            
                            numeroMensagensAtual = mensagens.length;
                            rolarParaFinal();
                        } else {
                            // Atualizar status das mensagens existentes
                            mensagens.forEach(mensagem => {
                                if (mensagem.ga4_4_enviado_por == 'profissional') {
                                    const msgElement = document.querySelector(`.mensagem[data-id="${mensagem.ga4_4_id}"] .status-icon`);
                                    if (msgElement) {
                                        msgElement.className = `status-icon status-${mensagem.ga4_4_status_mensagem.replace(' ', '-')}`;
                                        msgElement.innerHTML = getStatusIcon(mensagem.ga4_4_status_mensagem);
                                    }
                                }
                            });
                        }
                    })
                    .catch(error => console.error('Erro ao buscar mensagens:', error));
            }
        }
        
        // Atualiza o chat a cada 2 segundos
        setInterval(buscarNovasMensagens, 2000);
        
        // Envio de mensagem via AJAX
        document.getElementById('mensagemForm').addEventListener('submit', function(e) {
            e.preventDefault(); // Previne o envio normal do formulário
            
            const mensagem = document.getElementById('mensagem').value;
            if (!mensagem.trim()) return; // Não envia mensagens vazias
            
            // Cria um objeto FormData para enviar os dados
            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('mensagem', mensagem);
            formData.append('cliente_id', '<?php echo $cliente_id; ?>');
            
            // Envia a mensagem via AJAX
            fetch('chat_profissional.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Limpa o campo de mensagem
                    document.getElementById('mensagem').value = '';
                    // Adiciona a nova mensagem ao chat
                    if (data.message) {
                        const container = document.getElementById('mensagens-container');
                        container.appendChild(criarElementoMensagem(data.message));
                        numeroMensagensAtual++;
                        rolarParaFinal();
                    } else {
                        // Busca as mensagens atualizadas
                        buscarNovasMensagens();
                    }
                }
            })
            .catch(error => console.error('Erro ao enviar mensagem:', error));
        });
        
        // Foco automático no campo de mensagem quando disponível
        if (document.getElementById('mensagem')) {
            document.getElementById('mensagem').focus();
        }
        
        // Ajusta a altura do textarea conforme o conteúdo
        document.getElementById('mensagem').addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
            if (this.scrollHeight > 150) {
                this.style.height = '150px';
                this.style.overflowY = 'auto';
            } else {
                this.style.overflowY = 'hidden';
            }
        });

        // Envio de mensagem com Enter
        document.getElementById('mensagem').addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                document.getElementById('enviar-btn').click();
            }
        });
    </script>
</body>
</html>