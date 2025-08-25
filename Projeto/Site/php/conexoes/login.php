<?php
session_start();
include 'conexao.php'; // Arquivo para conectar ao banco de dados

// Inicializa variáveis
$erro = '';
$email = '';
$mensagem = "";
$tipo_mensagem = "";
$dados = [
    'nome' => '',
    'cpf' => '',
    'crm' => '',
    'nasc' => '',
    'sexo' => 'Masculino',
    'tel' => '',
    'cep' => '',
    'email' => ''
];
$tipoCadastro = 'cliente';

function verificarDuplicidade($email, $cpf, $conn) {
    // Verifica se o CPF ou o Email já existem em ambas as tabelas
    $sql = "SELECT 'cliente' AS origem FROM ga4_1_clientes WHERE ga4_1_email = ? OR ga4_1_cpf = ? 
            UNION 
            SELECT 'profissional' AS origem FROM ga4_2_profissionais WHERE ga4_2_email = ? OR ga4_2_cpf = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sisi", $email, $cpf, $email, $cpf);
    $stmt->execute();
    $stmt->store_result();

    return $stmt->num_rows > 0;
}

function verificarDuplicidadeCRM($crm, $conn) {
    // Verifica se o CRM já existe na tabela de profissionais
    $sql = "SELECT ga4_2_id FROM ga4_2_profissionais WHERE ga4_2_crm = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $crm);
    $stmt->execute();
    $stmt->store_result();

    return $stmt->num_rows > 0;
}

// Lógica de login
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["acao"]) && $_POST["acao"] == "login") {
    $email = filter_input(INPUT_POST, "email", FILTER_SANITIZE_EMAIL);
    $senha = trim($_POST["senha"]);

    if (!empty($email) && !empty($senha)) {
        try {
            $conn = conectarBanco(); // Função do arquivo 'conexao.php'
            
            // Verifica na tabela de administradores primeiro
            $sql_admin = "SELECT ga4_5_id, ga4_5_nome, ga4_5_senha, ga4_5_nivel_acesso FROM ga4_5_administradores WHERE ga4_5_email = ?";
            $stmt_admin = $conn->prepare($sql_admin);
            $stmt_admin->bind_param("s", $email);
            $stmt_admin->execute();
            $resultado_admin = $stmt_admin->get_result();
            
            // Verifica na tabela de clientes
            $sql_cliente = "SELECT ga4_1_id, ga4_1_nome, ga4_1_senha FROM ga4_1_clientes WHERE ga4_1_email = ?";
            $stmt_cliente = $conn->prepare($sql_cliente);
            $stmt_cliente->bind_param("s", $email);
            $stmt_cliente->execute();
            $resultado_cliente = $stmt_cliente->get_result();

            // Verifica na tabela de profissionais
            $sql_profissional = "SELECT ga4_2_id, ga4_2_nome, ga4_2_senha FROM ga4_2_profissionais WHERE ga4_2_email = ?";
            $stmt_profissional = $conn->prepare($sql_profissional);
            $stmt_profissional->bind_param("s", $email);
            $stmt_profissional->execute();
            $resultado_profissional = $stmt_profissional->get_result();

            // Caso o email seja encontrado na tabela de administradores
            if ($resultado_admin->num_rows > 0) {
                $usuario = $resultado_admin->fetch_assoc();
                if (password_verify($senha, $usuario["ga4_5_senha"])) {
                    // Atualiza o último acesso
                    $sql_update = "UPDATE ga4_5_administradores SET ga4_5_ultimo_acesso = NOW() WHERE ga4_5_id = ?";
                    $stmt_update = $conn->prepare($sql_update);
                    $stmt_update->bind_param("i", $usuario["ga4_5_id"]);
                    $stmt_update->execute();
                    
                    $_SESSION["admin_id"] = $usuario["ga4_5_id"];
                    $_SESSION["usuario_nome"] = $usuario["ga4_5_nome"];
                    $_SESSION["tipo"] = "admin";
                    $_SESSION["nivel_acesso"] = $usuario["ga4_5_nivel_acesso"];

                    // Redireciona para a página do administrador
                    header("Location: ../admin/admin_home.php");
                    exit;
                }
            }

            // Caso o email seja encontrado na tabela de clientes
            if ($resultado_cliente->num_rows > 0) {
                $usuario = $resultado_cliente->fetch_assoc();
                if (password_verify($senha, $usuario["ga4_1_senha"])) {
                    $_SESSION["cliente_id"] = $usuario["ga4_1_id"];
                    $_SESSION["usuario_nome"] = $usuario["ga4_1_nome"];
                    $_SESSION["tipo"] = "cliente";

                    // Redireciona para a página do cliente
                    header("Location: ../clientes/cliente_home.php");
                    exit;
                }
            }

            // Caso o email seja encontrado na tabela de profissionais
            if ($resultado_profissional->num_rows > 0) {
                $usuario = $resultado_profissional->fetch_assoc();
                if (password_verify($senha, $usuario["ga4_2_senha"])) {
                    $_SESSION["profissional_id"] = $usuario["ga4_2_id"];
                    $_SESSION["usuario_nome"] = $usuario["ga4_2_nome"];
                    $_SESSION["tipo"] = "profissional";

                    // Redireciona para a página do profissional
                    header("Location: ../profissionais/profissional_home.php");
                    exit;
                }
            }

            // Se o email não for encontrado em nenhuma tabela ou senha incorreta
            $erro = "Email ou senha inválidos.";
            
            // Fecha as conexões
            $stmt_admin->close();
            $stmt_cliente->close();
            $stmt_profissional->close();
            $conn->close();
        } catch (Exception $e) {
            $erro = "Erro ao conectar com o banco de dados. Tente novamente mais tarde.";
            // Em ambiente de produção, registre o erro em um log em vez de exibi-lo
            // error_log($e->getMessage());
        }
    } else {
        $erro = "Preencha todos os campos!";
    }
}

// Lógica de cadastro
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["acao"]) && $_POST["acao"] == "cadastro") {
    $tipoCadastro = isset($_POST['tipo_cadastro']) ? $_POST['tipo_cadastro'] : 'cliente';
    
    // Captura e sanitiza os dados do formulário
    $dados = [
        'nome' => trim(filter_input(INPUT_POST, "nome", FILTER_SANITIZE_SPECIAL_CHARS)),
        'cpf' => preg_replace('/[^0-9]/', '', trim($_POST["cpf"])),
        'crm' => isset($_POST["crm"]) ? preg_replace('/[^0-9]/', '', trim($_POST["crm"])) : '',
        'nasc' => $_POST["nascimento"], // Ajustado para o nome do campo no HTML
        'sexo' => filter_input(INPUT_POST, "sexo", FILTER_SANITIZE_SPECIAL_CHARS),
        'tel' => preg_replace('/[^0-9]/', '', trim($_POST["telefone"])), // Ajustado para o nome do campo no HTML
        'cep' => preg_replace('/[^0-9]/', '', trim($_POST["cep"])),
        'email' => filter_input(INPUT_POST, "email", FILTER_SANITIZE_EMAIL)
    ];
    
    $senha = password_hash($_POST["senha"], PASSWORD_DEFAULT);

    try {
        $conn = conectarBanco();

        // Verifica duplicidade de CPF ou Email em ambas as tabelas
        if (verificarDuplicidade($dados['email'], $dados['cpf'], $conn)) {
            $mensagem = "Erro: CPF ou Email já cadastrados!";
            $tipo_mensagem = "error";
        } else if ($tipoCadastro === 'profissional' && empty($dados['crm'])) {
            $mensagem = "Erro: CRM é obrigatório para profissionais!";
            $tipo_mensagem = "error";
        } else if ($tipoCadastro === 'profissional' && verificarDuplicidadeCRM($dados['crm'], $conn)) {
            $mensagem = "Erro: CRM já cadastrado!";
            $tipo_mensagem = "error";
        } else {
            // Inserção no banco
            if ($tipoCadastro === 'cliente') {
                $sql = "INSERT INTO ga4_1_clientes (ga4_1_cpf, ga4_1_nome, ga4_1_nasc, ga4_1_sexo, ga4_1_tel, ga4_1_cep, ga4_1_email, ga4_1_senha) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("issssiss", $dados['cpf'], $dados['nome'], $dados['nasc'], $dados['sexo'], 
                                $dados['tel'], $dados['cep'], $dados['email'], $senha);
            } else {
                $sql = "INSERT INTO ga4_2_profissionais (ga4_2_crm, ga4_2_cpf, ga4_2_nome, ga4_2_nasc, ga4_2_sexo, ga4_2_tel, ga4_2_cep, ga4_2_email, ga4_2_senha) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iissssiss", $dados['crm'], $dados['cpf'], $dados['nome'], $dados['nasc'], 
                                $dados['sexo'], $dados['tel'], $dados['cep'], $dados['email'], $senha);
            }

            if ($stmt->execute()) {
                $mensagem = "Cadastro realizado com sucesso! Você já pode fazer login.";
                $tipo_mensagem = "success";
                
                // Limpa os dados após cadastro bem-sucedido
                $dados = [
                    'nome' => '',
                    'cpf' => '',
                    'crm' => '',
                    'nasc' => '',
                    'sexo' => 'Masculino',
                    'tel' => '',
                    'cep' => '',
                    'email' => ''
                ];
            } else {
                $mensagem = "Erro ao cadastrar: " . $stmt->error;
                $tipo_mensagem = "error";
            }

            $stmt->close();
        }

        $conn->close();
    } catch (Exception $e) {
        $mensagem = "Erro ao conectar com o banco de dados. Tente novamente mais tarde.";
        $tipo_mensagem = "error";
        // Em ambiente de produção, registre o erro em um log em vez de exibi-lo
        // error_log($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="description" content="<?php echo htmlspecialchars($config_valor[1]); ?>: Agende consultas, encontre especialistas e cuide da sua saúde com facilidade.">
        <meta name="theme-color" content="#3b82f6">
        <title>Login/Cadastro</title>
        
        <!-- Preload de recursos críticos -->
        <link rel="stylesheet" href="../../style/main.css">
        <link rel="preload" href="../../script/javascript.js" as="script">
        
        <link rel="stylesheet" href="../../style/main.css">
        <link rel="icon" type="image/png" href="../../midia/logo.png">
        
        <!-- Fontes -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/style2?family=Segoe+UI:wght@400;500;700&display=swap" rel="stylesheet">
        
        <!-- Font Awesome para ícones -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/style/all.min.css">
        
        <style>
            .alert {
                padding: 15px;
                margin-bottom: 20px;
                border-radius: 4px;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .alert-success {
                background-color: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }
            
            .alert-error {
                background-color: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }
        </style>
    </head>
    <body>
    <!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo htmlspecialchars($config_valor[1]); ?>: Agende consultas, encontre especialistas e cuide da sua saúde com facilidade.">
    <meta name="theme-color" content="#3b82f6">
    <title><?php echo htmlspecialchars($config_valor[1]); ?> - Bem-estar na palma da sua mão</title>
    
    <!-- Preload de recursos críticos -->
    <link rel="stylesheet" href="style/main.css">
    <link rel="preload" href="script/javascript.js" as="script">
    
    <link rel="icon" type="image/png" href="midia/logo.png">
    
    <!-- Fontes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;500;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome para ícones -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
    <body>
        <header>
            <nav class="navbar">
                <div class="logo">
                    <h1>
                        <a href="../../index.php">
                            <img class="img-logo" src="../../midia/logo.png" alt="Logo Vitaliza" width="40" height="40"> 
                            <?php echo htmlspecialchars($config_valor[1]); ?>
                        </a>
                    </h1>
                </div>
                <ul class="nav-links">
                    <li><a href="../../index.php">Home</a></li>
                    <li><a href="../../servicos.php">Serviços</a></li>
                    <li><a href="../../sobre.php">Sobre nós</a></li>
                    <li><a href="../../contato.php">Contato</a></li>
                </ul>

                <a href="login.php" class="btn-cadastrar">Login</a>
                
                <button class="hamburger" onclick="toggleMenu()" aria-label="Menu de navegação" aria-expanded="false">
                    <div></div>
                    <div></div>
                    <div></div>
                </button>
            </nav>
            <div class="mobile-menu" id="mobileMenu">
                <a href="../../index.php" class="mobile-menu-a">Início</a>
                <a href="../../servicos.php">Serviços</a>
                <a href="../../sobre.php" class="mobile-menu-a">Sobre</a>
                <a href="../../contato.php">Contato</a>
                <a href='login.php' class="mobile-menu-a">Login</a>
            </div>
        </header>

        <div id="divSign" class="hidden">
            <div class="form-title">
                <h1>Cadastro</h1>
            </div>
            
            <?php if (!empty($mensagem)): ?>
                <div class="alert alert-<?php echo $tipo_mensagem; ?>">
                    <i class="fas fa-<?php echo $tipo_mensagem === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $mensagem; ?>
                </div>
            <?php endif; ?>
            
            <main class="container">
                <section class="form-section">

                    <div class="toggle-buttons">
                        <button id="clienteBtn" class="btn-select-user-type toggle-btn active" onclick="mostrarFormulario('cliente')">Cliente</button>
                        <button id="profissionalBtn" class="btn-select-user-type toggle-btn" onclick="mostrarFormulario('profissional')">Profissional</button>
                    </div>

                    <!-- Formulário de Cliente -->
                    <form id="formCliente" action="login.php" method="POST" class="form">
                        <input type="hidden" name="acao" value="cadastro">
                        <input type="hidden" name="tipo_cadastro" id="tipo_cadastro" value="cliente">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="nome">Nome</label>
                                <input type="text" placeholder="Nome completo" id="nome" name="nome" value="<?php echo htmlspecialchars($dados['nome']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="senha">Senha</label>
                                <input type="password" placeholder="Use uma senha forte" id="senha" name="senha" required>
                            </div>

                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" placeholder="exemplo@gmail.com" name="email" value="<?php echo htmlspecialchars($dados['email']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="telefone">Telefone</label>
                                <input type="text" id="telefone" placeholder="(DDD) XXXXX-YYYY" name="telefone" value="<?php echo htmlspecialchars($dados['tel']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="sexo">Sexo</label>
                                <select id="sexo" name="sexo" required>
                                    <option value="" disabled>Selecione</option>
                                    <option value="Masculino" <?php echo $dados['sexo'] === 'Masculino' ? 'selected' : ''; ?>>Masculino</option>
                                    <option value="Feminino" <?php echo $dados['sexo'] === 'Feminino' ? 'selected' : ''; ?>>Feminino</option>
                                    <option value="Outro" <?php echo $dados['sexo'] === 'Outro' ? 'selected' : ''; ?>>Outro</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="cpf">CPF</label>
                                <input type="text" placeholder="000.000.000-00" id="cpf" name="cpf" value="<?php echo htmlspecialchars($dados['cpf']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="nascimento">Nascimento</label>
                                <input type="date" id="nascimento" name="nascimento" value="<?php echo htmlspecialchars($dados['nasc']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="cep">CEP</label>
                                <input type="text" placeholder="XXXXX-YYY" id="cep" name="cep" value="<?php echo htmlspecialchars($dados['cep']); ?>" required>
                            </div>
                        </div>

                        <p class="login-link">Já está cadastrado? <a onclick="mostrarFormulario('sign')">Faça login</a></p>
                        
                        <button type="submit" class="form-button">Cadastre-se</button>
                    </form>

                    <!-- Formulário de Profissional -->
                    <form id="formProfissional" action="login.php" method="POST" class="form hidden" enctype="multipart/form-data">
                        <input type="hidden" name="acao" value="cadastro">
                        <input type="hidden" name="tipo_cadastro" value="profissional">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="nome_prof">Nome</label>
                                <input type="text" placeholder="Nome completo" id="nome_prof" name="nome" value="<?php echo htmlspecialchars($dados['nome']); ?>" required>
                            </div>
                    
                            <div class="form-group">
                                <label for="senha_prof">Senha</label>
                                <input type="password" placeholder="Use uma senha forte" id="senha_prof" name="senha" required>
                            </div>
                    
                            <div class="form-group">
                                <label for="email_prof">Email</label>
                                <input type="text" placeholder="exemplo@gmail.com" id="email_prof" name="email" value="<?php echo htmlspecialchars($dados['email']); ?>" required>
                            </div>
                    
                            <div class="form-group">
                                <label for="telefone_prof">Telefone</label>
                                <input type="text" placeholder="(DDD) XXXXX-YYYY" id="telefone_prof" name="telefone" value="<?php echo htmlspecialchars($dados['tel']); ?>" required>
                            </div>
                    
                            <div class="form-group">
                                <label for="sexo_prof">Sexo</label>
                                <select id="sexo_prof" name="sexo" required>
                                    <option value="" disabled>Selecione</option>
                                    <option value="Masculino" <?php echo $dados['sexo'] === 'Masculino' ? 'selected' : ''; ?>>Masculino</option>
                                    <option value="Feminino" <?php echo $dados['sexo'] === 'Feminino' ? 'selected' : ''; ?>>Feminino</option>
                                    <option value="Outro" <?php echo $dados['sexo'] === 'Outro' ? 'selected' : ''; ?>>Outro</option>
                                </select>
                            </div>
                    
                            <div class="form-group">
                                <label for="cpf_prof">CPF</label>
                                <input type="text" placeholder="000.000.000-00" id="cpf_prof" name="cpf" value="<?php echo htmlspecialchars($dados['cpf']); ?>" required>
                            </div>
                    
                            <div class="form-group">
                                <label for="nascimento_prof">Nascimento</label>
                                <input type="date" id="nascimento_prof" name="nascimento" value="<?php echo htmlspecialchars($dados['nasc']); ?>" required>
                            </div>
                    
                            <div class="form-group">
                                <label for="cep_prof">CEP</label>
                                <input type="text" placeholder="XXXXX-YYY" id="cep_prof" name="cep" value="<?php echo htmlspecialchars($dados['cep']); ?>" required>
                            </div>
                    
                            <div class="form-group">
                                <label for="crm">CRM</label>
                                <input type="text" placeholder="CRM/UF 000000" id="crm" name="crm" value="<?php echo htmlspecialchars($dados['crm']); ?>" required>
                            </div>
                    
                            <div class="form-group">
                                <label for="especialidade">Especialidade</label>
                                <input type="text" id="especialidade" name="especialidade" required>
                            </div>
                    
                            <div class="form-group">
                                <label for="fotocert">Foto do Certificado</label>
                                <input type="file" id="fotocert" name="fotocert" accept="image/*" required>
                            </div>
                        </div>
                    
                        <p class="login-link">Já está cadastrado? <a onclick="mostrarFormulario('sign')">Faça login</a></p>
                        
                        <button type="submit" class="form-button">Cadastre-se</button>
                    </form>            
                </section>
            </main>
        </div>

        <div id="divLogin" class="divLogin">
            <div class="form-title">
                <h1>Login</h1>
            </div>
                <main class="container">
                    <section class="form-section sectionLogin">
                        <!-- Formulário de Login -->
                        <?php if (!empty($erro)): ?>
                            <div class="alert alert-error">
                                <i class="fas fa-exclamation-circle"></i>
                                <?php echo $erro; ?>
                            </div>
                        <?php endif; ?>

                        <form id="formLogin" action="login.php" method="POST" class="form">
                            <input type="hidden" name="acao" value="login">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="email_login">Email</label>
                                    <input type="text" id="email_login" placeholder="exemplo@gmail.com" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="senha_login">Senha</label>
                                    <input type="password" placeholder="Digite sua senha" id="senha_login" name="senha" required>
                                </div>
                            </div>
                        
                            <p class="login-link">Não possui cadastro? <a onclick="mostrarFormulario('login')">Cadastre-se</a></p>
                            
                            <button type="submit" class="form-button">Entrar</button>
                        </form>            
                    </section>
                <main class="container">
            </div>
        </div>
        <footer>
            <div class="footer-section"> 
                <h3><?php echo htmlspecialchars($config_valor[1]); ?></h3>
                <p>Endereço: <em>R. Octávio Rodrigues de Souza, 350 - <br>Parque Paduan, Taubaté - SP, 12070-790</em></p>
                <div class="social-icons">
                    <a href="#"><i class="fab fa-youtube"></i></a>
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-whatsapp"></i></a>
                </div>
            </div>
            <div class="footer-section">
                <h3>Link</h3>
                <a href="../../index.php">Home</a>
                <a href="../../servicos.php">Serviços</a>
                <a href="../../sobre.php">Sobre</a>
                <a href="../../contato.php">Contato</a>
            </div>
            <div class="footer-section">
                <h3>Contato</h3>
                <p><?php echo htmlspecialchars($config_valor[4]);?></p>
                <p><?php echo htmlspecialchars($config_valor[3]);?></p>
            </div>
            <div class="divider"></div>
            <div class="footer-section">
            <p>&copy; <?php echo date("Y")?> <?php echo htmlspecialchars($config_valor[1]); ?>. Todos os direitos reservados.</p>
            </div>
        </footer>
        
        <script src="../../script/javascript.js"></script>
        <script>
            function mostrarFormulario(tipo) {
                if (tipo === 'cliente') {
                    document.getElementById('formCliente').classList.remove('hidden');
                    document.getElementById('formProfissional').classList.add('hidden');
                    document.getElementById('clienteBtn').classList.add('active');
                    document.getElementById('profissionalBtn').classList.remove('active');
                    document.getElementById('tipo_cadastro').value = 'cliente';
                } else if (tipo === 'profissional') {
                    document.getElementById('formCliente').classList.add('hidden');
                    document.getElementById('formProfissional').classList.remove('hidden');
                    document.getElementById('clienteBtn').classList.remove('active');
                    document.getElementById('profissionalBtn').classList.add('active');
                } else if (tipo === 'sign') {
                    document.getElementById('divSign').classList.add('hidden');
                    document.getElementById('divLogin').classList.remove('hidden');
                } else if (tipo === 'login') {
                    document.getElementById('divSign').classList.remove('hidden');
                    document.getElementById('divLogin').classList.add('hidden');
                }
            }
            
            // Validação de CPF
            document.querySelectorAll('input[name="cpf"]').forEach(input => {
                input.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length > 11) value = value.slice(0, 11);
                    
                    if (value.length > 3 && value.length <= 6) {
                        value = value.slice(0, 3) + '.' + value.slice(3);
                    } else if (value.length > 6 && value.length <= 9) {
                        value = value.slice(0, 3) + '.' + value.slice(3, 6) + '.' + value.slice(6);
                    } else if (value.length > 9) {
                        value = value.slice(0, 3) + '.' + value.slice(3, 6) + '.' + value.slice(6, 9) + '-' + value.slice(9);
                    }
                    
                    e.target.value = value;
                });
            });
            
            // Validação de telefone
            document.querySelectorAll('input[name="telefone"]').forEach(input => {
                input.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length > 11) value = value.slice(0, 11);
                    
                    if (value.length > 2 && value.length <= 6) {
                        value = `(${value.slice(0, 2)}) ${value.slice(2)}`;
                    } else if (value.length > 6) {
                        value = `(${value.slice(0, 2)}) ${value.slice(2, 7)}-${value.slice(7)}`;
                    }
                    
                    e.target.value = value;
                });
            });