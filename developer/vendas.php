<?php
// developer/vendas.php
require_once '../config/config.php';
require_once '../config/database.php';

requireLogin();

$database = new Database();
$pdo = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Buscar desenvolvedor
$stmt = $pdo->prepare("SELECT * FROM desenvolvedor WHERE usuario_id = ?");
$stmt->execute([$user_id]);
$dev = $stmt->fetch();

if (!$dev || $dev['status'] != 'ativo') {
    header('Location: ' . SITE_URL . '/developer/dashboard.php');
    exit;
}

// Período de análise
$periodo = $_GET['periodo'] ?? '30';
$data_inicio = date('Y-m-d', strtotime("-$periodo days"));

// Total de vendas no período
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT ip.pedido_id) as total_pedidos,
        SUM(ip.valor_desenvolvedor_centavos) as receita_total,
        SUM(CASE WHEN j.id IS NOT NULL THEN 1 ELSE 0 END) as total_itens
    FROM item_pedido ip
    INNER JOIN pedido p ON ip.pedido_id = p.id
    INNER JOIN jogo j ON ip.jogo_id = j.id
    WHERE j.desenvolvedor_id = ? AND p.status = 'pago' AND p.pago_em >= ?
");
$stmt->execute([$dev['id'], $data_inicio]);
$stats = $stmt->fetch();

// Vendas por jogo
$stmt = $pdo->prepare("
    SELECT 
        j.titulo,
        j.slug,
        COUNT(ip.id) as total_vendas,
        SUM(ip.valor_desenvolvedor_centavos) as receita
    FROM jogo j
    LEFT JOIN item_pedido ip ON j.id = ip.jogo_id
    LEFT JOIN pedido p ON ip.pedido_id = p.id AND p.status = 'pago' AND p.pago_em >= ?
    WHERE j.desenvolvedor_id = ?
    GROUP BY j.id
    ORDER BY receita DESC
");
$stmt->execute([$data_inicio, $dev['id']]);
$vendas_por_jogo = $stmt->fetchAll();

// Vendas por dia (últimos 30 dias para gráfico)
$stmt = $pdo->prepare("
    SELECT 
        DATE(p.pago_em) as data,
        COUNT(DISTINCT p.id) as vendas,
        SUM(ip.valor_desenvolvedor_centavos) as receita
    FROM pedido p
    INNER JOIN item_pedido ip ON p.id = ip.pedido_id
    INNER JOIN jogo j ON ip.jogo_id = j.id
    WHERE j.desenvolvedor_id = ? AND p.status = 'pago' AND p.pago_em >= ?
    GROUP BY DATE(p.pago_em)
    ORDER BY data ASC
");
$stmt->execute([$dev['id'], date('Y-m-d', strtotime('-30 days'))]);
$vendas_diarias = $stmt->fetchAll();

// Preparar dados para o gráfico
$chart_labels = [];
$chart_vendas = [];
$chart_receita = [];

foreach ($vendas_diarias as $dia) {
    $chart_labels[] = date('d/m', strtotime($dia['data']));
    $chart_vendas[] = $dia['vendas'];
    $chart_receita[] = $dia['receita'] / 100;
}

$page_title = 'Relatório de Vendas - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container">
    <div class="dev-layout">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <div class="dev-content">
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-chart-bar"></i> Relatório de Vendas
                </h1>
            </div>
            
            <!-- Filtro de Período -->
            <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; padding: 20px; margin-bottom: 30px;">
                <form method="GET" style="display: flex; gap: 15px; align-items: end;">
                    <div class="form-group" style="margin: 0; flex: 1;">
                        <label class="form-label">Período</label>
                        <select name="periodo" class="form-control">
                            <option value="7" <?php echo $periodo == '7' ? 'selected' : ''; ?>>Últimos 7 dias</option>
                            <option value="30" <?php echo $periodo == '30' ? 'selected' : ''; ?>>Últimos 30 dias</option>
                            <option value="90" <?php echo $periodo == '90' ? 'selected' : ''; ?>>Últimos 90 dias</option>
                            <option value="365" <?php echo $periodo == '365' ? 'selected' : ''; ?>>Último ano</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                </form>
            </div>
            
            <!-- Cards de Estatísticas -->
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px;">
                <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; padding: 25px; text-align: center;">
                    <div style="font-size: 36px; font-weight: 700; color: var(--accent);">
                        <?php echo formatPrice($stats['receita_total'] ?? 0); ?>
                    </div>
                    <div style="color: var(--text-secondary); font-size: 14px; margin-top: 8px;">Receita Total</div>
                </div>
                
                <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; padding: 25px; text-align: center;">
                    <div style="font-size: 36px; font-weight: 700; color: var(--success);">
                        <?php echo $stats['total_pedidos'] ?? 0; ?>
                    </div>
                    <div style="color: var(--text-secondary); font-size: 14px; margin-top: 8px;">Pedidos</div>
                </div>
                
                <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; padding: 25px; text-align: center;">
                    <div style="font-size: 36px; font-weight: 700; color: var(--warning);">
                        <?php echo $stats['total_itens'] ?? 0; ?>
                    </div>
                    <div style="color: var(--text-secondary); font-size: 14px; margin-top: 8px;">Jogos Vendidos</div>
                </div>
            </div>
            
            <!-- Gráfico de Vendas -->
            <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 15px; padding: 30px; margin-bottom: 30px;">
                <h2 style="margin-bottom: 20px;"><i class="fas fa-chart-line"></i> Vendas Diárias</h2>
                <canvas id="vendasChart" style="max-height: 400px;"></canvas>
            </div>
            
            <!-- Vendas por Jogo -->
            <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 15px; padding: 30px;">
                <h2 style="margin-bottom: 20px;"><i class="fas fa-gamepad"></i> Desempenho por Jogo</h2>
                
                <?php if (count($vendas_por_jogo) > 0): ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="border-bottom: 2px solid var(--border);">
                                    <th style="padding: 12px; text-align: left;">Jogo</th>
                                    <th style="padding: 12px; text-align: center;">Vendas</th>
                                    <th style="padding: 12px; text-align: right;">Receita</th>
                                    <th style="padding: 12px; text-align: center;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vendas_por_jogo as $venda): ?>
                                <tr style="border-bottom: 1px solid var(--border);">
                                    <td style="padding: 15px;">
                                        <strong><?php echo sanitize($venda['titulo']); ?></strong>
                                    </td>
                                    <td style="padding: 15px; text-align: center;">
                                        <span style="background: rgba(40,167,69,0.1); color: var(--success); padding: 4px 12px; border-radius: 4px; font-weight: 600;">
                                            <?php echo $venda['total_vendas'] ?? 0; ?>
                                        </span>
                                    </td>
                                    <td style="padding: 15px; text-align: right; font-weight: 600; color: var(--accent);">
                                        <?php echo formatPrice($venda['receita'] ?? 0); ?>
                                    </td>
                                    <td style="padding: 15px; text-align: center;">
                                        <a href="<?php echo SITE_URL; ?>/pages/jogo.php?slug=<?php echo $venda['slug']; ?>" 
                                           class="btn btn-sm btn-secondary" target="_blank">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-chart-bar" style="font-size: 48px; color: var(--text-secondary); margin-bottom: 15px;"></i>
                        <p style="color: var(--text-secondary);">Nenhuma venda neste período</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
const ctx = document.getElementById('vendasChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($chart_labels); ?>,
        datasets: [
            {
                label: 'Vendas',
                data: <?php echo json_encode($chart_vendas); ?>,
                borderColor: '#4C8BF5',
                backgroundColor: 'rgba(76, 139, 245, 0.1)',
                tension: 0.4,
                yAxisID: 'y'
            },
            {
                label: 'Receita (R$)',
                data: <?php echo json_encode($chart_receita); ?>,
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                tension: 0.4,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        plugins: {
            legend: {
                labels: {
                    color: '#E3E3E3'
                }
            }
        },
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                ticks: {
                    color: '#E3E3E3',
                    stepSize: 1
                },
                grid: {
                    color: 'rgba(255, 255, 255, 0.1)'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                ticks: {
                    color: '#E3E3E3'
                },
                grid: {
                    drawOnChartArea: false
                }
            },
            x: {
                ticks: {
                    color: '#E3E3E3'
                },
                grid: {
                    color: 'rgba(255, 255, 255, 0.1)'
                }
            }
        }
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>