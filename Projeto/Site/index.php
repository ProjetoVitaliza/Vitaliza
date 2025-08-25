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
        
        <main>
            <button></button>
            <section class="vitaliza-section">
                <div class="text-content">
                    <h2 class="title">BEM-ESTAR NA PALMA DA SUA MÃO!!!!!!!</h2>
                    <p class="description">Agende consultas, encontre especialistas e cuide da sua saúde com facilidade.</p>
                    <p class="description">Não tem cadastro?</p>
                    <a href="php/conexoes/login.php" onclick="mostrarFormulario('sign')" class="cta-button">Cadastre-se já!</a>
                </div>
                <div class="image-content">
                    <div class="circle"></div>
                    <img src="midia/estetoscopio.png" alt="Estetoscópio" class="stethoscope" width="250" height="250" loading="lazy">
                </div>
            </section>

            <section class="services-section">
                <h2 class="services-title">Serviços Oferecidos</h2>
                <div class="services-container">
                    <article class="service-card">
                        <div class="service-icon" aria-hidden="true">💙</div>
                        <h3 class="service-title">Agendamento <br> de consultas</h3>
                        <p class="service-description">Marque consultas de forma rápida, escolhendo profissional, data e horário, seja presencial ou online.</p>
                    </article>
                    <article class="service-card">
                        <div class="service-icon" aria-hidden="true">🔍</div>
                        <h3 class="service-title">Busca de profissionais<br> de saúde</h3>
                        <p class="service-description">Encontre médicos, psicólogos e outros especialistas por nome, especialidade ou localização.</p>
                    </article>
                    <article class="service-card">
                        <div class="service-icon" aria-hidden="true">📖</div>
                        <h3 class="service-title">Biblioteca de<br> saúde</h3>
                        <p class="service-description">Acesse nossos artigos e dicas confiáveis para cuidar melhor da sua saúde e bem-estar.</p>
                    </article>
                </div>
            </section>

            <section class="about-section">
                <div class="about-image">
                    <img src="midia/foto_etec.jpg" alt="Imagem da escola ETEC Doutor Geraldo José Rodrigues Alckmin" width="500" height="300" loading="lazy">
                </div>
                <div class="about-text">
                    <h2 class="about-title">Sobre nós</h2>
                    <p class="about-description">O Vitaliza é um projeto desenvolvido por alunos da ETEC para o CICTED, focado em facilitar o acesso à saúde e bem-estar. Nossa plataforma oferece agendamento de consultas e uma biblioteca de informações sobre saúde, promovendo um atendimento médico acessível e eficiente através da tecnologia.</p>
                    <p class="about-description"><strong>Nossa equipe é composta pelos alunos:</strong> Bruno Vinícius Perez de Carvalho, Daniel Rodrigues Mendonça, Heitor Otávio da Silva, Jean Gabriel Galvão e Kauan Albissu França.</p>
                </div>
            </section>
        </main>
        
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
                <p><?php echo htmlspecialchars($config_valor[3]);?></p>
            </div>
            <div class="divider"></div>
            <div class="footer-section">
            <p>&copy; <?php echo date("Y")?> <?php echo htmlspecialchars($config_valor[1]); ?>. Todos os direitos reservados.</p>
            </div>
        </footer>
        
        <script src="script/javascript.js"></script>
    </body>
</html>
