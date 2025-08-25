// Variáveis globais
let mobileMenu;
let hamburger;

// Função para alternar o menu mobile
function toggleMenu() {
    if (!mobileMenu || !hamburger) return;
    
    const isExpanded = hamburger.getAttribute('aria-expanded') === 'true';
    
    // Atualiza o estado do botão hamburger
    hamburger.setAttribute('aria-expanded', !isExpanded);
    hamburger.classList.toggle('active');
    
    // Atualiza o estado do menu mobile
    if (!isExpanded) {
        mobileMenu.style.display = 'flex';
        // Força um reflow antes de adicionar a classe active
        mobileMenu.offsetHeight;
        mobileMenu.classList.add('active');
        // Previne rolagem do body quando o menu está aberto
        document.body.style.overflow = 'hidden';
    } else {
        mobileMenu.classList.remove('active');
        // Restaura rolagem do body
        document.body.style.overflow = '';
        setTimeout(() => {
            if (!mobileMenu.classList.contains('active')) {
                mobileMenu.style.display = 'none';
            }
        }, 300); // Tempo da transição
    }
}

// Função para mostrar formulário
function mostrarFormulario(tipo) {
    const formCliente = document.getElementById("formCliente");
    const formProfissional = document.getElementById("formProfissional");
    const clienteBtn = document.getElementById("clienteBtn");
    const profissionalBtn = document.getElementById("profissionalBtn");
    const signForm = document.getElementById("divSign");
    const loginForm = document.getElementById('divLogin');
    
    if (!formCliente || !formProfissional || !clienteBtn || !profissionalBtn || !signForm || !loginForm) return;

    if ((tipo == "cliente") | (tipo == "profissional")) {
        formCliente.classList.toggle("hidden", tipo !== "cliente");
        formProfissional.classList.toggle("hidden", tipo !== "profissional");

        clienteBtn.classList.toggle("active", tipo === "cliente");
        profissionalBtn.classList.toggle("active", tipo === "profissional");
    }
    if ((tipo == "sign") | (tipo == "login")) {
        signForm.classList.toggle("hidden", tipo == "sign");
        loginForm.classList.toggle("hidden", tipo == "login");
    }
}

// Função para inicializar event listeners
function initEventListeners() {
    // Fechar menu mobile ao clicar em um link
    document.querySelectorAll('.mobile-menu a').forEach(link => {
        link.addEventListener('click', () => {
            if (!mobileMenu || !hamburger) return;
            
            mobileMenu.classList.remove('active');
            hamburger.classList.remove('active');
            hamburger.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
            
            setTimeout(() => {
                mobileMenu.style.display = 'none';
            }, 300);
        });
    });

    // Adicionar animação de scroll suave para links internos
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            if (targetId === '#') return; // Ignora links vazios
            
            const targetElement = document.querySelector(targetId);
            
            if (targetElement) {
                e.preventDefault();
                const headerOffset = 80;
                const elementPosition = targetElement.getBoundingClientRect().top;
                const offsetPosition = elementPosition + window.pageYOffset - headerOffset;
                
                window.scrollTo({
                    top: offsetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });
}

// Configuração para observar elementos entrando na viewport para animações
function setupIntersectionObserver() {
    // Somente configurar se IntersectionObserver é suportado
    if ('IntersectionObserver' in window) {
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        // Observar elementos para animação
        document.querySelectorAll('.service-card, .about-image, .about-text').forEach(el => {
            el.classList.add('animate-on-scroll');
            observer.observe(el);
        });
    }
}

// Validação de formulário
function setupFormValidation() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            let hasErrors = false;
            
            // Validação básica de campos obrigatórios
            form.querySelectorAll('[required]').forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = 'red';
                    hasErrors = true;
                } else {
                    field.style.borderColor = '';
                }
                
                // Validação específica para email
                if (field.type === 'email' && field.value) {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(field.value)) {
                        field.style.borderColor = 'red';
                        hasErrors = true;
                    }
                }
                
                // Validação específica para CPF
                if (field.id === 'cpf' && field.value) {
                    // Simplificada - em produção use validação mais robusta
                    const cpfClean = field.value.replace(/[^\d]/g, '');
                    if (cpfClean.length !== 11) {
                        field.style.borderColor = 'red';
                        hasErrors = true;
                    }
                }
            });
            
            if (hasErrors) {
                e.preventDefault();
                alert('Por favor, preencha todos os campos corretamente.');
            }
        });
    });
}

// Inicialização quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    // Inicializa referências globais
    mobileMenu = document.getElementById('mobileMenu');
    hamburger = document.querySelector('.hamburger');
    
    if (mobileMenu) {
        // Configuração inicial do menu mobile
        mobileMenu.style.display = 'none';
    }
    
    // Destacar link da página atual no menu
    const currentPage = window.location.pathname.split('/').pop() || 'index.html';
    document.querySelectorAll('.nav-links a, .mobile-menu a').forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPage || 
            (currentPage === 'index.html' && (href === '/' || href === ''))) {
            link.setAttribute('aria-current', 'page');
        }
    });
    
    // Inicializa todos os event listeners
    initEventListeners();
    
    // Configura observador de interseção para animações
    setupIntersectionObserver();
    
    // Configura validação de formulário
    setupFormValidation();
});

// Garantir que tela esteja sempre no estado correto em redimensionamento
window.addEventListener('resize', () => {
    if (window.innerWidth > 768 && mobileMenu) {
        mobileMenu.style.display = 'none';
        mobileMenu.classList.remove('active');
        if (hamburger) {
            hamburger.classList.remove('active');
            hamburger.setAttribute('aria-expanded', 'false');
        }
        document.body.style.overflow = '';
    }
});