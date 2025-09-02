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

// Período para relatórios
$periodo = isset($_GET['periodo']) ? $_GET['periodo'] : 'todos';
$data_inicio = null;
$data_fim = date('Y-m-d');

switch ($periodo) {
    case 'hoje':
        $data_inicio = date('Y-m-d');
        break;
    case 'semana':
        $data_inicio = date('Y-m-d', strtotime('-7 days'));
        break;
    case 'mes':
        $data_inicio = date('Y-m-d', strtotime('-30 days'));
        break;
    case 'ano':
        $data_inicio = date('Y-m-d', strtotime('-1 year'));
        break;
    case 'personalizado':
        $data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : null;
        $data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d');
        break;
}

// Construir condição de data para consultas
$data_condition = "";
$data_params = [];
$data_types = "";

if ($data_inicio) {
    $data_condition = " WHERE ga4_3_data BETWEEN ? AND ?";
    $data_params = [$data_inicio, $data_fim];
    $data_types = "ss";
}

// Inicializar variáveis para relatórios
$relatorio_clientes = [];
$relatorio_profissionais = [];
$relatorio_consultas = [];

// Obter relatórios de clientes
$sql_clientes = "SELECT COUNT(*) as total_clientes, 
                        SUM(CASE WHEN ga4_1_status = 'Ativo' THEN 1 ELSE 0 END) as clientes_ativos,
                        SUM(CASE WHEN ga4_1_status = 'Inativo' THEN 1 ELSE 0 END) as clientes_inativos
                 FROM ga4_1_clientes";
$stmt = $conn->prepare($sql_clientes);
$stmt->execute();
$stmt->bind_result($total_clientes, $clientes_ativos, $clientes_inativos);
$stmt->fetch();
$stmt->close();

$relatorio_clientes = [
    'total' => $total_clientes,
    'ativos' => $clientes_ativos,
    'inativos' => $clientes_inativos
];

// Obter relatórios de profissionais
$sql_profissionais = "SELECT COUNT(*) as total_profissionais, 
                             SUM(CASE WHEN ga4_2_status = 'Ativo' THEN 1 ELSE 0 END) as profissionais_ativos,
                             SUM(CASE WHEN ga4_2_status = 'Inativo' THEN 1 ELSE 0 END) as profissionais_inativos,
                             SUM(CASE WHEN ga4_2_verificado = 'Sim' THEN 1 ELSE 0 END) as profissionais_verificados,
                             COUNT(DISTINCT ga4_2_especialidade) as total_especialidades
                      FROM ga4_2_profissionais";
$stmt = $conn->prepare($sql_profissionais);
$stmt->execute();
$stmt->bind_result($total_profissionais, $profissionais_ativos, $profissionais_inativos, $profissionais_verificados, $total_especialidades);
$stmt->fetch();
$stmt->close();

$relatorio_profissionais = [
    'total' => $total_profissionais,
    'ativos' => $profissionais_ativos,
    'inativos' => $profissionais_inativos,
    'verificados' => $profissionais_verificados,
    'especialidades' => $total_especialidades
];

// Obter relatórios de consultas
$sql_consultas = "SELECT COUNT(*) as total_consultas, 
                          SUM(CASE WHEN ga4_3_status = 'Pendente' THEN 1 ELSE 0 END) as consultas_pendentes,
                          SUM(CASE WHEN ga4_3_status = 'Concluída' THEN 1 ELSE 0 END) as consultas_concluidas,
                          SUM(CASE WHEN ga4_3_status = 'Recusada' THEN 1 ELSE 0 END) as consultas_recusadas,
                          SUM(CASE WHEN ga4_3_status = 'Cancelado' THEN 1 ELSE 0 END) as consultas_canceladas,
                          SUM(CASE WHEN ga4_3_status = 'Aceita' THEN 1 ELSE 0 END) as consultas_aceitas,
                          SUM(CASE WHEN ga4_3_status = 'Aguardando confirmação' THEN 1 ELSE 0 END) as consultas_aguardando,
                          SUM(CASE WHEN ga4_3_status = 'Arquivada' THEN 1 ELSE 0 END) as consultas_arquivadas
                   FROM ga4_3_consultas" . $data_condition;

$stmt = $conn->prepare($sql_consultas);
if (!empty($data_params)) {
    $stmt->bind_param($data_types, ...$data_params);
}
$stmt->execute();
$stmt->bind_result(
    $total_consultas, 
    $consultas_pendentes, 
    $consultas_concluidas, 
    $consultas_recusadas,
    $consultas_canceladas,
    $consultas_aceitas,
    $consultas_aguardando,
    $consultas_arquivadas
);
$stmt->fetch();
$stmt->close();

$relatorio_consultas = [
    'total' => $total_consultas,
    'pendentes' => $consultas_pendentes,
    'concluidas' => $consultas_concluidas,
    'recusadas' => $consultas_recusadas,
    'canceladas' => $consultas_canceladas,
    'aceitas' => $consultas_aceitas,
    'aguardando' => $consultas_aguardando,
    'arquivadas' => $consultas_arquivadas
];

// Obter estatísticas de atividade mensal (últimos 6 meses)
$sql_atividade_mensal = "SELECT 
    DATE_FORMAT(ga4_3_data, '%Y-%m') AS mes,
    COUNT(*) as total_consultas,
    SUM(CASE WHEN ga4_3_status = 'Concluída' THEN 1 ELSE 0 END) as consultas_concluidas
FROM ga4_3_consultas
WHERE ga4_3_data >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
GROUP BY DATE_FORMAT(ga4_3_data, '%Y-%m')
ORDER BY mes ASC";

$result_atividade = $conn->query($sql_atividade_mensal);
$atividade_mensal = [];

if ($result_atividade) {
    while ($row = $result_atividade->fetch_assoc()) {
        $atividade_mensal[] = $row;
    }
}

// Obter top 5 especialidades mais procuradas
$sql_especialidades = "SELECT 
    p.ga4_2_especialidade as especialidade,
    COUNT(*) as total_consultas
FROM ga4_3_consultas c
JOIN ga4_2_profissionais p ON c.id_profissional = p.ga4_2_id
GROUP BY p.ga4_2_especialidade
ORDER BY total_consultas DESC
LIMIT 5";

$result_especialidades = $conn->query($sql_especialidades);
$top_especialidades = [];

if ($result_especialidades) {
    while ($row = $result_especialidades->fetch_assoc()) {
        $top_especialidades[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - <?php echo htmlspecialchars($config_valor[1]); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.css">
    <link rel="stylesheet" href="../../style/profissionais.css">
    <link rel="stylesheet" href="../../style/admin.css">
    <link rel="icon" type="image/png" href="../../midia/logo.png">
    <style>
        .dashboard-widgets {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .widget {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .widget:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .widget-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .widget-header i {
            font-size: 1.5rem;
            margin-right: 10px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: white;
        }
        
        .widget-title {
            margin: 0;
            font-size: 1.1rem;
            color: #333;
        }
        
        .widget-content {
            text-align: center;
        }
        
        .widget-number {
            font-size: 2rem;
            font-weight: 700;
            margin: 10px 0;
            color: #333;
        }
        
        .widget-stats {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 15px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: #666;
        }
        
        .charts-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .chart-widget {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .chart-title {
            margin: 0;
            font-size: 1.1rem;
            color: #333;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        .filter-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .filter-tabs {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .filter-tab {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            background-color: #f5f5f5;
            color: #333;
        }
        
        .filter-tab.active {
            background-color: #4f46e5;
            color: white;
        }
        
        .custom-date-filter {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .custom-date-filter input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .custom-date-filter button {
            padding: 8px 15px;
            background-color: #4f46e5;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        
        /* Cores para status */
        .status-pendente { color: #f59e0b; }
        .status-aceita { color: #10b981; }
        .status-concluida { color: #3b82f6; }
        .status-recusada { color: #ef4444; }
        .status-cancelada { color: #6b7280; }
        .status-aguardando { color: #8b5cf6; }
        .status-arquivada { color: #1f2937; }
        
        /* Cores para os ícones dos widgets */
        .icon-clientes { background-color: #3b82f6; }
        .icon-profissionais { background-color: #10b981; }
        .icon-consultas { background-color: #8b5cf6; }
        
        @media (max-width: 768px) {
            .dashboard-widgets {
                grid-template-columns: 1fr;
            }
            
            .charts-container {
                grid-template-columns: 1fr;
            }

            .chart-widget {
                overflow-x: scroll;
            }

            .custom-date-filter {
                flex-wrap: wrap;
            }
        }
        
        /* Impressão */
        @media print {
            * {
                background: transparent;
                text-shadow: none;
                filter: none;
                -ms-filter: none;
            }

            body {
                margin: 0;
                padding: 0;
                line-height: 1.4em;
                font: 12pt Georgia, "Times New Roman", Times, serif;
                color: #000;
            }

            @page {
                margin: 0.5cm;
            }

            header, nav, footer, video, audio, object, embed {
                display: none;
            }

            .print {
                display: block;
            }

            .flex {
                display: flex;
                align-items: stretch;
            }

            .no-print {
                display: none;
            }

            .page-break {
                page-break-after: always;
            }
        }
        
    </style>
</head>
<body>
<header class="header no-print">
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
</header> 

    <main class="container">
        

        <section class="content-header">
            <h1><i class="fas fa-chart-bar"></i> Relatórios</h1>
            <p class="no-print" >Visualize estatísticas e relatórios do sistema</p>
        </section>
        
        <section class="filter-container no-print">
            <div class="filter-tabs">
                <a href="?periodo=todos" class="filter-tab <?php echo $periodo === 'todos' ? 'active' : ''; ?>">
                    <i class="fas fa-infinity"></i> Todos os períodos
                </a>
                <a href="?periodo=hoje" class="filter-tab <?php echo $periodo === 'hoje' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-day"></i> Hoje
                </a>
                <a href="?periodo=semana" class="filter-tab <?php echo $periodo === 'semana' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-week"></i> Últimos 7 dias
                </a>
                <a href="?periodo=mes" class="filter-tab <?php echo $periodo === 'mes' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt"></i> Últimos 30 dias
                </a>
                <a href="?periodo=ano" class="filter-tab <?php echo $periodo === 'ano' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar"></i> Último ano
                </a>
            </div>
            
            <form class="custom-date-filter" method="GET" action="">
                <input type="hidden" name="periodo" value="personalizado">
                <input type="date" name="data_inicio" value="<?php echo $data_inicio ?? ''; ?>" required>
                <span>até</span>
                <input type="date" name="data_fim" value="<?php echo $data_fim ?? ''; ?>" required>
                <button type="submit"><i class="fas fa-filter"></i> Filtrar</button>
            </form>
        </section>

        <section class="dashboard-widgets flex">
            <article class="widget">
                <div class="widget-header">
                    <i class="fas fa-users icon-clientes"></i>
                    <h3 class="widget-title">Clientes</h3>
                </div>
                <div class="widget-content">
                    <div class="widget-number"><?php echo $relatorio_clientes['total']; ?></div>
                    <p>Total de clientes cadastrados</p>
                    <div class="widget-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $relatorio_clientes['ativos']; ?></div>
                            <div class="stat-label">Ativos</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $relatorio_clientes['inativos']; ?></div>
                            <div class="stat-label">Inativos</div>
                        </div>
                    </div>
                </div>
            </article>
            
            <article class="widget print">
                <div class="widget-header">
                    <i class="fas fa-user-md icon-profissionais"></i>
                    <h3 class="widget-title">Profissionais</h3>
                </div>
                <div class="widget-content">
                    <div class="widget-number"><?php echo $relatorio_profissionais['total']; ?></div>
                    <p>Total de profissionais cadastrados</p>
                    <div class="widget-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $relatorio_profissionais['ativos']; ?></div>
                            <div class="stat-label">Ativos</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $relatorio_profissionais['verificados']; ?></div>
                            <div class="stat-label">Verificados</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $relatorio_profissionais['especialidades']; ?></div>
                            <div class="stat-label">Especialidades</div>
                        </div>
                    </div>
                </div>
            </article>
            
            <article class="widget print">
                <div class="widget-header">
                    <i class="fas fa-calendar-check icon-consultas"></i>
                    <h3 class="widget-title">Consultas</h3>
                </div>
                <div class="widget-content">
                    <div class="widget-number"><?php echo $relatorio_consultas['total']; ?></div>
                    <p><?php echo $periodo !== 'todos' ? 'Consultas no período selecionado' : 'Total de consultas'; ?></p>
                    <div class="widget-stats">
                        <div class="stat-item">
                            <div class="stat-value status-pendente"><?php echo $relatorio_consultas['pendentes']; ?></div>
                            <div class="stat-label">Pendentes</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value status-aceita"><?php echo $relatorio_consultas['aceitas']; ?></div>
                            <div class="stat-label">Aceitas</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value status-concluida"><?php echo $relatorio_consultas['concluidas']; ?></div>
                            <div class="stat-label">Concluídas</div>
                        </div>
                    </div>
                </div>
            </article>
        </section>
        
        <section class="charts-container print">
            <article class="chart-widget page-break">
                <div class="chart-header">
                    <h3 class="chart-title"><i class="fas fa-chart-line"></i> Atividade Mensal</h3>
                </div>
                <div class="chart-container">
                    <canvas id="activityChart"></canvas>
                </div>
            </article>
            
            <article class="chart-widget">
                <div class="chart-header">
                    <h3 class="chart-title"><i class="fas fa-chart-pie"></i> Status das Consultas</h3>
                </div>
                <div class="chart-container">
                    <canvas id="statusChart"></canvas>
                </div>
            </article>
        </section>
        
        <section class="chart-widget print page-break">
            <div class="chart-header">
                <h3 class="chart-title"><i class="fas fa-chart-bar"></i> Top 5 Especialidades Mais Procuradas</h3>
            </div>
            <div class="chart-container">
                <canvas id="especialidadesChart"></canvas>
            </div>
        </section>
        
        <section class="admin-reports print">
            <h2>Status Detalhado das Consultas</h2>
            <article class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Quantidade</th>
                            <th>Porcentagem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="status-pendente"><i class="fas fa-clock"></i> Pendentes</span></td>
                            <td><?php echo $relatorio_consultas['pendentes']; ?></td>
                            <td><?php echo $relatorio_consultas['total'] > 0 ? round(($relatorio_consultas['pendentes'] / $relatorio_consultas['total']) * 100, 1) : 0; ?>%</td>
                        </tr>
                        <tr>
                            <td><span class="status-aceita"><i class="fas fa-check"></i> Aceitas</span></td>
                            <td><?php echo $relatorio_consultas['aceitas']; ?></td>
                            <td><?php echo $relatorio_consultas['total'] > 0 ? round(($relatorio_consultas['aceitas'] / $relatorio_consultas['total']) * 100, 1) : 0; ?>%</td>
                        </tr>
                        <tr>
                            <td><span class="status-concluida"><i class="fas fa-check-double"></i> Concluídas</span></td>
                            <td><?php echo $relatorio_consultas['concluidas']; ?></td>
                            <td><?php echo $relatorio_consultas['total'] > 0 ? round(($relatorio_consultas['concluidas'] / $relatorio_consultas['total']) * 100, 1) : 0; ?>%</td>
                        </tr>
                        <tr>
                            <td><span class="status-recusada"><i class="fas fa-times"></i> Recusadas</span></td>
                            <td><?php echo $relatorio_consultas['recusadas']; ?></td>
                            <td><?php echo $relatorio_consultas['total'] > 0 ? round(($relatorio_consultas['recusadas'] / $relatorio_consultas['total']) * 100, 1) : 0; ?>%</td>
                        </tr>
                        <tr>
                            <td><span class="status-cancelada"><i class="fas fa-ban"></i> Canceladas</span></td>
                            <td><?php echo $relatorio_consultas['canceladas']; ?></td>
                            <td><?php echo $relatorio_consultas['total'] > 0 ? round(($relatorio_consultas['canceladas'] / $relatorio_consultas['total']) * 100, 1) : 0; ?>%</td>
                        </tr>
                        <tr>
                            <td><span class="status-aguardando"><i class="fas fa-hourglass-half"></i> Aguardando Confirmação</span></td>
                            <td><?php echo $relatorio_consultas['aguardando']; ?></td>
                            <td><?php echo $relatorio_consultas['total'] > 0 ? round(($relatorio_consultas['aguardando'] / $relatorio_consultas['total']) * 100, 1) : 0; ?>%</td>
                        </tr>
                        <tr>
                            <td><span class="status-arquivada"><i class="fas fa-archive"></i> Arquivadas</span></td>
                            <td><?php echo $relatorio_consultas['arquivadas']; ?></td>
                            <td><?php echo $relatorio_consultas['total'] > 0 ? round(($relatorio_consultas['arquivadas'] / $relatorio_consultas['total']) * 100, 1) : 0; ?>%</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th>Total</th>
                            <th><?php echo $relatorio_consultas['total']; ?></th>
                            <th>100%</th>
                        </tr>
                    </tfoot>
                </table>
            </article>
        </section>

        <section class="admin-actions no-print" style="margin-top: 20px;">
            <a href="admin_home.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
            <a href="#" class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print"></i> Imprimir Relatório
            </a>
            <a href="#" class="btn btn-outline" id="exportarCSV">
                <i class="fas fa-file-csv"></i> Exportar CSV
            </a>
        </section>

        <footer class="footer-home">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($config_valor[1]); ?> - Todos os direitos reservados</p>
        </footer>
    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.js"></script>
    <script>
        // Configurar gráfico de atividade mensal
        const activityCtx = document.getElementById('activityChart').getContext('2d');
        const activityChart = new Chart(activityCtx, {
            type: 'line',
            data: {
                labels: [
                    <?php 
                    foreach ($atividade_mensal as $item) {
                        $data = explode('-', $item['mes']);
                        echo "'" . $data[0] . '-' . $data[1] . "',";
                    }
                    ?>
                ],
                datasets: [{
                    label: 'Total de Consultas',
                    data: [
                        <?php 
                        foreach ($atividade_mensal as $item) {
                            echo $item['total_consultas'] . ',';
                        }
                        ?>
                    ],
                    backgroundColor: 'rgba(79, 70, 229, 0.2)',
                    borderColor: 'rgba(79, 70, 229, 1)',
                    borderWidth: 2,
                    tension: 0.4
                }, {
                    label: 'Consultas Concluídas',
                    data: [
                        <?php 
                        foreach ($atividade_mensal as $item) {
                            echo $item['consultas_concluidas'] . ',';
                        }
                        ?>
                    ],
                    backgroundColor: 'rgba(16, 185, 129, 0.2)',
                    borderColor: 'rgba(16, 185, 129, 1)',
                    borderWidth: 2,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Configurar gráfico de status das consultas
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Pendentes', 'Aceitas', 'Concluídas', 'Recusadas', 'Canceladas', 'Aguardando', 'Arquivadas'],
                datasets: [{
                    data: [
                        <?php echo $relatorio_consultas['pendentes']; ?>,
                        <?php echo $relatorio_consultas['aceitas']; ?>,
                        <?php echo $relatorio_consultas['concluidas']; ?>,
                        <?php echo $relatorio_consultas['recusadas']; ?>,
                        <?php echo $relatorio_consultas['canceladas']; ?>,
                        <?php echo $relatorio_consultas['aguardando']; ?>,
                        <?php echo $relatorio_consultas['arquivadas']; ?>
                    ],
                    backgroundColor: [
                        '#f59e0b', // pendentes
                        '#10b981', // aceitas
                        '#3b82f6', // concluídas
                        '#ef4444', // recusadas
                        '#6b7280', // canceladas
                        '#8b5cf6', // aguardando
                        '#1f2937'  // arquivadas
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: {
                    position: 'right'
                }
            }
        });
        
        // Configurar gráfico das top especialidades
        const especialidadesCtx = document.getElementById('especialidadesChart').getContext('2d');
        const especialidadesChart = new Chart(especialidadesCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php 
                    foreach ($top_especialidades as $item) {
                        echo "'" . $item['especialidade'] . "',";
                    }
                    ?>
                ],
                datasets: [{
                    label: 'Número de Consultas',
                    data: [
                        <?php 
                        foreach ($top_especialidades as $item) {
                            echo $item['total_consultas'] . ',';
                        }
                        ?>
                    ],
                    backgroundColor: [
                        'rgba(79, 70, 229, 0.7)',
                        'rgba(16, 185, 129, 0.7)',
                        'rgba(245, 158, 11, 0.7)',
                        'rgba(139, 92, 246, 0.7)',
                        'rgba(59, 130, 246, 0.7)'
                    ],
                    borderColor: [
                        'rgba(79, 70, 229, 1)',
                        'rgba(16, 185, 129, 1)',
                        'rgba(245, 158, 11, 1)',
                        'rgba(139, 92, 246, 1)',
                        'rgba(59, 130, 246, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Função para exportar dados para CSV
        document.getElementById('exportarCSV').addEventListener('click', function(e) {
            e.preventDefault();
            
            // Criar conteúdo CSV
            let csvContent = "data:text/csv;charset=utf-8,";
            
            // Adicionar cabeçalho
            csvContent += "Relatório de Consultas - <?php echo htmlspecialchars($config_valor[1]); ?>\n";
            csvContent += "Período: <?php echo $periodo === 'todos' ? 'Todos os períodos' : ($periodo === 'personalizado' ? 'De ' . $data_inicio . ' até ' . $data_fim : $periodo); ?>\n\n";
            
            // Dados de clientes
            csvContent += "CLIENTES\n";
            csvContent += "Total,Ativos,Inativos\n";
            csvContent += `${<?php echo $relatorio_clientes['total']; ?>},${<?php echo $relatorio_clientes['ativos']; ?>},${<?php echo $relatorio_clientes['inativos']; ?>}\n\n`;
            
            // Dados de profissionais
            csvContent += "PROFISSIONAIS\n";
            csvContent += "Total,Ativos,Inativos,Verificados,Especialidades\n";
            csvContent += `${<?php echo $relatorio_profissionais['total']; ?>},${<?php echo $relatorio_profissionais['ativos']; ?>},${<?php echo $relatorio_profissionais['inativos']; ?>},${<?php echo $relatorio_profissionais['verificados']; ?>},${<?php echo $relatorio_profissionais['especialidades']; ?>}\n\n`;
            
            // Dados de consultas
            csvContent += "CONSULTAS\n";
            csvContent += "Status,Quantidade,Porcentagem\n";
            csvContent += `Pendentes,${<?php echo $relatorio_consultas['pendentes']; ?>},${<?php echo $relatorio_consultas['total'] > 0 ? round(($relatorio_consultas['pendentes'] / $relatorio_consultas['total']) * 100, 1) : 0; ?>}%\n`;
            csvContent += `Aceitas,${<?php echo $relatorio_consultas['aceitas']; ?>},${<?php echo $relatorio_consultas['total'] > 0 ? round(($relatorio_consultas['aceitas'] / $relatorio_consultas['total']) * 100, 1) : 0; ?>}%\n`;
            csvContent += `Concluídas,${<?php echo $relatorio_consultas['concluidas']; ?>},${<?php echo $relatorio_consultas['total'] > 0 ? round(($relatorio_consultas['concluidas'] / $relatorio_consultas['total']) * 100, 1) : 0; ?>}%\n`;
            csvContent += `Recusadas,${<?php echo $relatorio_consultas['recusadas']; ?>},${<?php echo $relatorio_consultas['total'] > 0 ? round(($relatorio_consultas['recusadas'] / $relatorio_consultas['total']) * 100, 1) : 0; ?>}%\n`;
            csvContent += `Canceladas,${<?php echo $relatorio_consultas['canceladas']; ?>},${<?php echo $relatorio_consultas['total'] > 0 ? round(($relatorio_consultas['canceladas'] / $relatorio_consultas['total']) * 100, 1) : 0; ?>}%\n`;
            csvContent += `Aguardando,${<?php echo $relatorio_consultas['aguardando']; ?>},${<?php echo $relatorio_consultas['total'] > 0 ? round(($relatorio_consultas['aguardando'] / $relatorio_consultas['total']) * 100, 1) : 0; ?>}%\n`;
            csvContent += `Arquivadas,${<?php echo $relatorio_consultas['arquivadas']; ?>},${<?php echo $relatorio_consultas['total'] > 0 ? round(($relatorio_consultas['arquivadas'] / $relatorio_consultas['total']) * 100, 1) : 0; ?>}%\n`;
            csvContent += `Total,${<?php echo $relatorio_consultas['total']; ?>},100%\n`;
            
            // Criar link de download
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "relatorio_vitaliza_<?php echo date('Y-m-d'); ?>.csv");
            document.body.appendChild(link);
            
            // Simular clique e remover o link
            link.click();
            document.body.removeChild(link);
        });
    </script>
    <script src="../../script/javascript.js"></script>
</body>
</html>