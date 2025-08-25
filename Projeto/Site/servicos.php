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
    <title>Serviços - <?php echo htmlspecialchars($config_valor[1]); ?></title>
    
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
    
    <style>
        /* Estilos específicos para a página de serviços */
        .page-header {
            text-align: center;
            padding: 50px 20px;
            background-color: #f7faff;
        }
        
        .page-title {
            font-size: 2.5rem;
            color: var(--primary-color);
            font-weight: bold;
            margin-bottom: 20px;
        }
        
        .page-description {
            color: #555;
            max-width: 800px;
            margin: 0 auto 30px;
            line-height: 1.6;
        }
        
        .services-nav {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin: 30px 0;
        }
        
        .services-nav a {
            text-decoration: none;
            color: var(--text-color);
            font-weight: 500;
            padding: 8px 12px;
            border-radius: 5px;
            transition: var(--transition-normal);
            position: relative;
        }
        
        .services-nav a:hover {
            color: var(--primary-color);
        }
        
        .services-nav a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background: var(--gradient);
            transition: width 0.3s ease;
        }
        
        .services-nav a:hover::after {
            width: 100%;
        }
        
        .services-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            justify-content: center;
            padding: 20px 50px 80px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .service-blog-card {
            width: 100%;
            max-width: 350px;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition-slow);
            background-color: white;
        }
        
        .service-blog-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        }
        
        .card-image {
            height: 200px;
            background-color: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .card-content {
            padding: 20px;
        }
        
        .card-category {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        
        .card-title {
            font-size: 1.3rem;
            font-weight: bold;
            color: var(--text-color);
            margin-bottom: 15px;
        }
        
        .card-description {
            color: #555;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        
        .read-more {
            display: inline-block;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition-normal);
        }
        
        .read-more:hover {
            color: var(--secondary-color);
        }
        
        @media (max-width: 768px) {
            .services-grid {
                padding: 20px 20px 60px;
            }
            
            .services-nav {
                flex-direction: column;
                gap: 10px;
                align-items: center;
            }
        }

        /* Estilos para a página de serviços */
        /* Cabeçalho da página de serviços */
            .page-header {
            text-align: center;
            padding: 50px 20px;
            background-color: #f7faff;
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
            transform: translateX(-50%);
        }
        .page-description {
            color: #555;
            max-width: 800px;
            margin: 0 auto 30px;
            line-height: 1.6;
            font-size: 1.1rem;
            text-align: center;
        }

        /* Navegação interna da página de serviços */
        .services-nav {
            display: flex;
            justify-content: center;
            text-align: center;
            gap: 30px;
            margin: 30px 0;
            max-width: 100%;
        }
        .services-nav a {
            text-decoration: none;
            color: var(--text-color);
            font-weight: 500;
            padding: 8px 12px;
            border-radius: 5px;
            transition: var(--transition-normal);
            position: relative;
        }
        .services-nav a:hover {
            color: var(--primary-color);
        }
        .services-nav a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background: var(--gradient);
            transition: width 0.3s ease;
        }
        .services-nav a:hover::after {
            width: 100%;
        }
        /* Grid de cards de serviços/blog */
        .services-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            justify-content: center;
            padding: 20px 50px 80px;
            max-width: 1400px;
            margin: 0 auto;
        }
        .service-blog-card {
            width: 100%;
            max-width: 350px;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition-slow);
            background-color: white;
        }
        .service-blog-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        }
        .card-image {
            height: 200px;
            background-color: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .card-content {
            padding: 20px;
        }
        .card-category {
            color: var(--secondary-color);
            font-size: 0.9rem;
            margin-bottom: 10px;
            font-weight: 500;
        }
        .card-title {
            font-size: 1.3rem;
            font-weight: bold;
            color: var(--text-color);
            margin-bottom: 15px;
        }
        .card-description {
            color: #555;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .read-more {
            display: inline-block;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition-normal);
            position: relative;
        }
        .read-more:hover {
            color: var(--secondary-color);
        }
        .read-more::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -2px;
            left: 0;
            background: var(--gradient);
            transition: width 0.3s ease;
        }
        .read-more:hover::after {
            width: 100%;
        }
        /* Responsividade */
        @media (max-width: 768px) {
            .services-grid {
                padding: 20px 20px 60px;
            }
            .service-blog-card {
                max-width: 100%;
            }

            .services-nav {
                flex-wrap: wrap;
                gap: 15px;
            }

            .page-title {
                font-size: 2rem;
            }
        }
        @media (max-width: 480px) {
            .page-header {
            padding: 30px 15px;
            }
            .services-nav {
                flex-direction: column;
                gap: 10px;
            }

            .page-description {
                font-size: 1rem;
            }
        }
    </style>
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
                <li><a href="servicos.php" aria-current="page">Serviços</a></li>
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
            <a href="servicos.php" class="mobile-menu-a">Serviços</a>
            <a href="sobre.php" class="mobile-menu-a">Sobre</a>
            <a href="contato.php">Contato</a>
            <a href='php/conexoes/login.php' class="mobile-menu-a">Login</a>
        </div>
    </header>
    
    <main>
        <section class="page-header">
            <h1 class="page-title">Serviços</h1>
            <p class="page-description">Nós oferecemos uma variedade de serviços para simplificar e facilitar o seu dia a dia!</p>
            
            <nav class="services-nav">
                <a href="#consultas">Consultas</a>
                <a href="#exames">Exames</a>
                <a href="#biblioteca">Biblioteca de Saúde</a>
            </nav>
        </section>
        
        <section class="services-grid">
            <article class="service-blog-card animate-on-scroll">
                <div class="card-image">
                    <img src="midia/consulta.jpg" alt="Agendamento de consultas">
                </div>
                <div class="card-content">
                    <p class="card-category">Consultas Online</p>
                    <h2 class="card-title">Agendamento simplificado de consultas médicas</h2>
                    <p class="card-description">Marque consultas médicas de forma rápida e fácil, escolhendo o profissional, data e horário que melhor se adapte à sua rotina.</p>
                    <a href="#" class="read-more">Saiba mais</a>
                </div>
            </article>
            
            <article class="service-blog-card animate-on-scroll">
                <div class="card-image">
                    <img src="midia/documento.png" alt="Exames médicos">
                </div>
                <div class="card-content">
                    <p class="card-category">Exames</p>
                    <h2 class="card-title">Acesso rápido aos resultados de exames</h2>
                    <p class="card-description">Consulte seus resultados de exames de forma digital e segura, com histórico completo e possibilidade de compartilhamento com seu médico.</p>
                    <a href="#" class="read-more">Saiba mais</a>
                </div>
            </article>
            
            <article class="service-blog-card animate-on-scroll">
                <div class="card-image">
                    <img src="midia/banco_dados.jpg" alt="Biblioteca de saúde">
                </div>
                <div class="card-content">
                    <p class="card-category">Biblioteca</p>
                    <h2 class="card-title">Conteúdo educativo sobre saúde e bem-estar</h2>
                    <p class="card-description">Acesse nossa biblioteca digital com artigos, vídeos e dicas sobre saúde, escritos por profissionais qualificados e revisados.</p>
                    <a href="#" class="read-more">Saiba mais</a>
                </div>
            </article>
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
            <p><?php echo htmlspecialchars($config_valor[3]) ?></p>
        </div>
        <div class="divider"></div>
        <div class="footer-section">
        <p>&copy; <?php echo date("Y")?> <?php echo htmlspecialchars($config_valor[1]); ?>. Todos os direitos reservados.</p>
        </div>
    </footer>
    
    <script src="script/javascript.js"></script>
    <script>
        // Código JavaScript para ativar animações de scroll
        document.addEventListener('DOMContentLoaded', function() {
            const animateElements = document.querySelectorAll('.animate-on-scroll');
            
            function checkIfInView() {
                animateElements.forEach(element => {
                    const elementTop = element.getBoundingClientRect().top;
                    const elementVisible = 150;
                    
                    if (elementTop < window.innerHeight - elementVisible) {
                        element.classList.add('visible');
                    }
                });
            }
            
            window.addEventListener('scroll', checkIfInView);
            checkIfInView(); // Verificar elementos visíveis no carregamento inicial
        });
    </script>
</body>
</html>