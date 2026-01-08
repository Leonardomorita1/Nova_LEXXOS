<?php
// admin/dashboard.php
require_once '../config/config.php';
require_once '../config/database.php';

requireLogin();

if (getUserType() !== 'admin') {
    header('Location: ' . SITE_URL . '/pages/home.php');
    exit;
}

$database = new Database();
$pdo = $database->getConnection();

// Estatísticas gerais
$stmt = $pdo->query("SELECT COUNT(*) as total FROM usuario");
$total_usuarios = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM jogo WHERE status = 'publicado'");
$total_jogos = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM pedido WHERE status = 'pago'");
$total_vendas = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT SUM(total_centavos) as total FROM pedido WHERE status = 'pago'");
$receita_total = $stmt->fetch()['total'] ?? 0;

// Jogos pendentes
$stmt = $pdo->query("SELECT COUNT(*) as total FROM jogo WHERE status = 'em_revisao'");
$jogos_pendentes = $stmt->fetch()['total'];

// Desenvolvedores pendentes
$stmt = $pdo->query("SELECT COUNT(*) as total FROM desenvolvedor WHERE status = 'pendente'");
$devs_pendentes = $stmt->fetch()['total'];

// Tickets abertos
$stmt = $pdo->query("SELECT COUNT(*) as total FROM ticket WHERE status = 'aberto'");
$tickets_abertos = $stmt->fetch()['total'];

// Vendas recentes
$stmt = $pdo->query("
    SELECT p.*, u.nome_usuario, u.email 
    FROM pedido p
    JOIN usuario u ON p.usuario_id = u.id
    WHERE p.status = 'pago'
    ORDER BY p.pago_em DESC
    LIMIT 10
");
$vendas_recentes = $stmt->fetchAll();

// Jogos recentes
$stmt = $pdo->query("
    SELECT j.*, d.nome_estudio 
    FROM jogo j
    JOIN desenvolvedor d ON j.desenvolvedor_id = d.id
    WHERE j.status = 'em_revisao'
    ORDER BY j.criado_em DESC
    LIMIT 5
");
$jogos_revisao = $stmt->fetchAll();

// Dados para gráfico de vendas (últimos 7 dias)
$stmt = $pdo->query("
    SELECT DATE(pago_em) as data, COUNT(*) as vendas, SUM(total_centavos) as valor
    FROM pedido
    WHERE status = 'pago' AND pago_em >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(pago_em)
    ORDER BY data
");
$vendas_grafico = $stmt->fetchAll();

$page_title = 'Dashboard - Admin - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo SITE_URL; ?>/admin/assets/css/admin.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container">
    <div class="admin-layout">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="admin-header">
                <h1 class="admin-title">
                    <i class="fas fa-chart-line"></i> Dashboard Administrativo
                </h1>
                <div>
                    <span style="color: var(--text-secondary);">
                        Última atualização: <?php echo date('d/m/Y H:i'); ?>
                    </span>
                </div>
            </div>

            <!-- Estatísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total de Usuários</h3>
                        <div class="stat-value"><?php echo $total_usuarios; ?></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-gamepad"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Jogos Publicados</h3>
                        <div class="stat-value"><?php echo $total_jogos; ?></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon yellow">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total de Vendas</h3>
                        <div class="stat-value"><?php echo $total_vendas; ?></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Receita Total</h3>
                        <div class="stat-value"><?php echo formatPrice($receita_total); ?></div>
                    </div>
                </div>
            </div>

            <!-- Alertas -->
            <?php if ($jogos_pendentes > 0 || $devs_pendentes > 0 || $tickets_abertos > 0): ?>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 30px;">
                <?php if ($jogos_pendentes > 0): ?>
                <a href="<?php echo SITE_URL; ?>/admin/jogos.php?status=em_revisao" 
                   style="background: rgba(255,193,7,0.1); border: 1px solid var(--warning); padding: 15px; border-radius: 8px; display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 24px; color: var(--warning);"></i>
                    <div>
                        <strong style="color: var(--warning);"><?php echo $jogos_pendentes; ?> jogo(s)</strong>
                        <div style="font-size: 13px; color: var(--text-secondary);">aguardando revisão</div>
                    </div>
                </a>
                <?php endif; ?>

                <?php if ($devs_pendentes > 0): ?>
                <a href="<?php echo SITE_URL; ?>/admin/desenvolvedores.php?status=pendente" 
                   style="background: rgba(76,139,245,0.1); border: 1px solid var(--accent); padding: 15px; border-radius: 8px; display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-user-clock" style="font-size: 24px; color: var(--accent);"></i>
                    <div>
                        <strong style="color: var(--accent);"><?php echo $devs_pendentes; ?> desenvolvedor(es)</strong>
                        <div style="font-size: 13px; color: var(--text-secondary);">aguardando aprovação</div>
                    </div>
                </a>
                <?php endif; ?>

                <?php if ($tickets_abertos > 0): ?>
                <a href="<?php echo SITE_URL; ?>/admin/tickets.php?status=aberto" 
                   style="background: rgba(220,53,69,0.1); border: 1px solid var(--danger); padding: 15px; border-radius: 8px; display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-life-ring" style="font-size: 24px; color: var(--danger);"></i>
                    <div>
                        <strong style="color: var(--danger);"><?php echo $tickets_abertos; ?> ticket(s)</strong>
                        <div style="font-size: 13px; color: var(--text-secondary);">aguardando resposta</div>
                    </div>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Gráfico de Vendas -->
            <div class="chart-container" style="margin-bottom: 30px;">
                <h3>Vendas dos Últimos 7 Dias</h3>
                <canvas id="vendasChart" style="max-height: 300px;"></canvas>
            </div>

            <!-- Conteúdo em Grid -->
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
                <!-- Vendas Recentes -->
                <div class="data-table-wrapper">
                    <div class="table-header">
                        <h2>Vendas Recentes</h2>
                        <a href="<?php echo SITE_URL; ?>/admin/pedidos.php" class="btn btn-sm btn-primary">
                            Ver Todas
                        </a>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Pedido</th>
                                <th>Cliente</th>
                                <th>Valor</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vendas_recentes as $venda): ?>
                            <tr>
                                <td><strong>#<?php echo $venda['numero']; ?></strong></td>
                                <td><?php echo sanitize($venda['nome_usuario']); ?></td>
                                <td><strong style="color: var(--success);"><?php echo formatPrice($venda['total_centavos']); ?></strong></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($venda['pago_em'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Jogos em Revisão -->
                <div class="data-table-wrapper">
                    <div class="table-header">
                        <h2>Jogos Aguardando Revisão</h2>
                    </div>
                    <div style="padding: 20px;">
                        <?php if (count($jogos_revisao) > 0): ?>
                            <?php foreach ($jogos_revisao as $jogo): ?>
                            <div style="padding: 15px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <strong style="display: block; margin-bottom: 5px;">
                                        <?php echo sanitize($jogo['titulo']); ?>
                                    </strong>
                                    <small style="color: var(--text-secondary);">
                                        por <?php echo sanitize($jogo['nome_estudio']); ?>
                                    </small>
                                </div>
                                <a href="<?php echo SITE_URL; ?>/admin/jogos.php?id=<?php echo $jogo['id']; ?>" 
                                   class="btn btn-sm btn-primary">
                                    Revisar
                                </a>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 40px 20px;">
                                <i class="fas fa-check-circle" style="font-size: 48px; color: var(--success); margin-bottom: 10px;"></i>
                                <p style="color: var(--text-secondary);">Nenhum jogo aguardando revisão</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Gráfico de Vendas
const ctx = document.getElementById('vendasChart').getContext('2d');
const vendasData = <?php echo json_encode($vendas_grafico); ?>;

new Chart(ctx, {
    type: 'line',
    data: {
        labels: vendasData.map(v => {
            const date = new Date(v.data);
            return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
        }),
        datasets: [{
            label: 'Vendas',
            data: vendasData.map(v => v.vendas),
            borderColor: '#4C8BF5',
            backgroundColor: 'rgba(76, 139, 245, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>