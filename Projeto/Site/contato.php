<?php

include 'php/conexoes/conexao.php';

?>
<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="description" content="Vitaliza: Agende consultas, encontre especialistas e cuide da sua saúde com facilidade.">
        <meta name="theme-color" content="#3b82f6">
        <title>Contato</title>
        
        <!-- Preload de recursos críticos -->
        <link rel="stylesheet" href="style/main.css">
        <link rel="preload" href="script/javascript.js" as="script">
        
        <link rel="stylesheet" href="style/main.css">
        <link rel="icon" type="image/png" href="midia/logo.png">
        
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
    <meta name="description" content="Vitaliza: Agende consultas, encontre especialistas e cuide da sua saúde com facilidade.">
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
                        <a href="index.php">
                            <img class="img-logo" src="midia/logo.png" alt="Logo <?php echo htmlspecialchars($config_valor[1]); ?>" width="40" height="40"> 
                            <?php echo htmlspecialchars($config_valor[1]); ?>
                        </a>
                    </h1>
                </div>
                <ul class="nav-links">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="servicos.php">Serviços</a></li>
                    <li><a href="sobre.php">Sobre nós</a></li>
                    <li><a href="contato.php">Contato</a></li>
                </ul>

                <a href="php/conexoes/login.php" class="btn-cadastrar">Login</a>
                
                <button class="hamburger" onclick="toggleMenu()" aria-label="Menu de navegação" aria-expanded="false">
                    <div></div>
                    <div></div>
                    <div></div>
                </button>
            </nav>
            <div class="mobile-menu" id="mobileMenu">
                <a href="index.php" class="mobile-menu-a">Início</a>
                <a href="servicos.php">Serviços</a>
                <a href="sobre.php" class="mobile-menu-a">Sobre</a>
                <a href="contato.php">Contato</a>
                <a href='php/conexoes/login.php' class="mobile-menu-a">Login</a>
            </div>
        </header>

        <div id="divLogin" class="divLogin">
            <div class="form-title">
                <h1>Contato</h1>
            </div>
                <main class="containerContact">
                    <section class="form-section sectionLogin">

                        <form id="formLogin" action="login.php" method="POST" class="form">
                            <input type="hidden" name="acao" value="login">
                            
                            <div class="form-flex">
                                <div class="form-group">
                                    <label for="email_login">Email</label>
                                    <input type="text" id="email_login" placeholder="exemplo@gmail.com" name="email" required>
                                </div>

                                <div class="form-group">
                                    <label for="topic">Tópico (opcional)</label>
                                    <select name="topic" id="">
                                        <option selected disabled value="">Selecione</option>
                                        <option value="report">Denúncia</option>
                                        <option value="suggestion">Sugestão</option>
                                        <option value="doubt">Dúvida</option>
                                        <option value="other">Outro</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="descricao">Descrição</label>
                                    <textarea id="descricao" rows="6" name="senha" required></textarea>
                                </div>
                            </div>
                            
                            <button type="submit" class="form-button">Enviar</button>
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
                <a href="index.php">Home</a>
                <a href="servicos.php">Serviços</a>
                <a href="sobre.php">Sobre</a>
                <a href="contato.php">Contato</a>
            </div>
            <div class="footer-section">
                <h3>Contato</h3>
                <p><?php echo htmlspecialchars($config_valor[4]);?></p>
                <p><?php echo htmlspecialchars($config_valor[3]) ?></p>
            </div>
            <div class="divider"></div>
            <div class="footer-section">
            <p>&copy; <?php echo date("Y")?> <?php echo htmlspecialchars($config_valor[1]); ?>. Todos os direitos reservados.</p>
            </div>
        </footer>
        <script src="script/javascript.js"></script>
    </body>
</html>