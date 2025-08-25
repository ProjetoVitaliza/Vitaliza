<?php

include 'php/conexoes/conexao.php';

?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <!-- Estilo para destaque de trecho -->
    <style>
        .featured{
            color: #6F3AFA;
        }
        .page-title {
            font-size: 2.5rem;
            color: var(--primary-color);
            font-weight: bold;
            margin-bottom: 20px;
            position: relative;
            display: inline-block;
        }
        .page-title::after {
            content: '';
            position: absolute;
            width: 50px;
            height: 3px;
            background: var(--gradient);
            bottom: -10px;
            left: 50%;
            transform: translateX(-185%);
        }
    </style>

    <!-- Metadados -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="Vitaliza: Agende consultas, encontre especialistas e cuide da sua saúde com facilidade.">
    <meta name="theme-color" content="#3b82f6">
    <title><?php echo htmlspecialchars($config_valor[1]); ?> - Sobre</title>

    <!-- Preload de recursos críticos -->
    <link rel="stylesheet" href="style/main.css">
    <link rel="preload" href="script/javascript.js" as="script">

    <link rel="stylesheet" href="style/main.css">
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
        <section class="about-section">
            <div class="about-image">
                <img src="midia/foto_etec.jpg" alt="Imagem da escola ETEC Doutor Geraldo José Rodrigues Alckmin." width="500"
                    height="300" loading="lazy">
            </div>
            <div class="about-text">
                <h2 class="page-title">Sobre nós</h2>
                <p class="about-description">
                    Esse site é um projeto de TCC e será usado exclusivamente para esse propósito. Ele
                    faz parte de um grupo de cinco pessoas que cursam Informática para Internet integrado ao ensino
                    médio da ETEC. Estão no último ano e precisam concluir o TCC para receber o diploma.
                </p>
                <p class="about-description">
                    O nosso grupo decidiu refazer do zero um projeto antigo chamado 'Vitaliza', que foi
                    desenvolvido no ano anterior, utilizando-o como modelo, e manterá esse nome no TCC. <strong>O projeto
                    Vitaliza é uma plataforma digital criada para facilitar o acesso à saúde e ao bem-estar</strong>. Ele oferece
                    serviços como <strong>consultas online</strong>, <strong>busca de profissionais de saúde</strong>, <strong>agendamento de consultas</strong> e uma
                    <strong>biblioteca de saúde</strong>. O objetivo principal é <strong class="featured">melhorar a qualidade de vida dos usuários e promover um
                    cuidado mais acessível, alinhando-se aos Objetivos de Desenvolvimento Sustentável (ODS) relacionados
                    à saúde e bem-estar</strong>. 
                </p>
                <p class="about-description">
                    A plataforma busca reduzir barreiras geográficas, financeiras e temporais no
                    acesso à saúde, especialmente em comunidades com serviços limitados. Além disso, a Biblioteca de
                    Saúde tem a finalidade de empoderar os usuários com informações para tomarem decisões mais
                    informadas sobre sua saúde. O projeto Vitaliza é uma clínica de saúde com o design do site suave, delicado e leve.
                </p>
                <p class="about-description"><strong>A nossa equipe é composta pelos alunos:</strong> Bruno Vinícius Perez
                    de Carvalho, Daniel Rodrigues Mendonça, Heitor Otávio da Silva, Jean Gabriel Galvão e Kauan Albissu
                    França.</p>
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