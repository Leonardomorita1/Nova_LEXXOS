<?php
// developer/dashboard.php
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

if (!$dev) {
    header('Location: ' . SITE_URL . '/user/seja-dev.php');
    exit;
}

// Verificar status
if ($dev['status'] == 'pendente') {
    $page_title = 'Aguardando Aprovação - ' . SITE_NAME;
    require_once '../includes/header.php';
    ?>
    <div class="container" style="padding: 60px 0; text-align: center;">
        <i class="fas fa-clock" style="font-size: 64px; color: var(--warning); margin-bottom: 20px;"></i>
        <h1>Aguardando Aprovação</h1>
        <p style="color: var(--text-secondary); max-width: 500px; margin: 20px auto;">
            Sua solicitação para se tornar desenvolvedor está em análise. 
            Você receberá uma notificação quando for aprovado.
        </p>
        <a href="<?php echo SITE_URL; ?>/pages/home.php" class="btn btn-primary" style="margin-top: 20px;">
            Voltar para Home
        </a>
    </div>
    <?php
    require_once '../includes/footer.php';
    exit;
}

// Estatísticas
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM jogo WHERE desenvolvedor_id = ?");
$stmt->execute([$dev['id']]);
$total_jogos = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT SUM(total_vendas) as total FROM jogo WHERE desenvolvedor_id = ?");
$stmt->execute([$dev['id']]);
$total_vendas = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->prepare("SELECT SUM(total_visualizacoes) as total FROM jogo WHERE desenvolvedor_id = ?");
$stmt->execute([$dev['id']]);
$total_views = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM seguidor WHERE desenvolvedor_id = ?");
$stmt->execute([$dev['id']]);
$total_seguidores = $stmt->fetch()['total'];

// Buscar saldo
$stmt = $pdo->prepare("SELECT * FROM desenvolvedor_saldo WHERE desenvolvedor_id = ?");
$stmt->execute([$dev['id']]);
$saldo = $stmt->fetch();

// Jogos recentes
$stmt = $pdo->prepare("
    SELECT * FROM jogo 
    WHERE desenvolvedor_id = ? 
    ORDER BY criado_em DESC 
    LIMIT 5
");
$stmt->execute([$dev['id']]);
$jogos_recentes = $stmt->fetchAll();

// Vendas recentes
$stmt = $pdo->prepare("
    SELECT dm.*, j.titulo 
    FROM desenvolvedor_movimentacao dm
    LEFT JOIN jogo j ON dm.referencia_id = j.id AND dm.referencia_tipo = 'jogo'
    WHERE dm.desenvolvedor_id = ? AND dm.tipo = 'venda'
    ORDER BY dm.criado_em DESC
    LIMIT 10
");
$stmt->execute([$dev['id']]);
$vendas_recentes = $stmt->fetchAll();

$page_title = 'Dashboard - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<style>
.dev-dashboard {
    padding: 30px 0;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 40px;
}

.stat-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 25px;
    text-align: center;
}

.stat-icon {
    font-size: 36px;
    color: var(--accent);
    margin-bottom: 15px;
}

.stat-value {
    font-size: 32px;
    font-weight: 700;
    display: block;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 14px;
    color: var(--text-secondary);
}

.balance-card {
    background: linear-gradient(135deg, var(--accent) 0%, #6ba3ff 100%);
    border-radius: 15px;
    padding: 30px;
    color: white;
    margin-bottom: 40px;
}

.balance-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
    margin-top: 20px;
}

.balance-item h3 {
    font-size: 14px;
    opacity: 0.9;
    margin-bottom: 10px;
}

.balance-item .value {
    font-size: 28px;
    font-weight: 700;
}

.quick-actions {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 40px;
}

.action-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 25px;
    text-align: center;
    transition: all 0.3s;
    cursor: pointer;
}

.action-card:hover {
    transform: translateY(-5px);
    border-color: var(--accent);
}

.action-icon {
    font-size: 48px;
    color: var(--accent);
    margin-bottom: 15px;
}

.content-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
}

.content-section {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 15px;
    padding: 30px;
}

.content-section h2 {
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.content-section h2 i {
    color: var(--accent);
}

.game-item {
    display: flex;
    gap: 15px;
    padding: 15px;
    background: var(--bg-primary);
    border-radius: 8px;
    margin-bottom: 15px;
}

.game-item img {
    width: 80px;
    height: 106px;
    object-fit: cover;
    border-radius: 6px;
}

.game-info {
    flex: 1;
}

.game-info h3 {
    font-size: 16px;
    margin-bottom: 5px;
}

.game-status {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.status-publicado {
    background: rgba(40,167,69,0.1);
    color: var(--success);
}

.status-rascunho {
    background: rgba(108,117,125,0.1);
    color: var(--text-secondary);
}

.status-em_revisao {
    background: rgba(255,193,7,0.1);
    color: var(--warning);
}

.sale-item {
    padding: 15px;
    border-bottom: 1px solid var(--border);
}

.sale-item:last-child {
    border-bottom: none;
}

.sale-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
}

.sale-value {
    color: var(--success);
    font-weight: 600;
}

.sale-date {
    font-size: 12px;
    color: var(--text-secondary);
}

@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .content-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .stats-grid,
    .quick-actions,
    .balance-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="container">
    <div class="dev-layout">
        <?php require_once 'includes/sidebar.php'; ?>
        <!-- Header -->
        <div style="margin-bottom: 30px;">
            <h1 style="font-size: 32px; margin-bottom: 10px;">
                <i class="fas fa-chart-line"></i> Dashboard
            </h1>
            <p style="color: var(--text-secondary);">
                Bem-vindo, <?php echo sanitize($dev['nome_estudio']); ?>!
            </p>
        </div>
        
        <!-- Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-gamepad"></i></div>
                <span class="stat-value"><?php echo $total_jogos; ?></span>
                <span class="stat-label">Jogos</span>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-download"></i></div>
                <span class="stat-value"><?php echo $total_vendas; ?></span>
                <span class="stat-label">Vendas</span>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-eye"></i></div>
                <span class="stat-value"><?php echo $total_views; ?></span>
                <span class="stat-label">Visualizações</span>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <span class="stat-value"><?php echo $total_seguidores; ?></span>
                <span class="stat-label">Seguidores</span>
            </div>
        </div>
        
        <!-- Saldo -->
        <div class="balance-card">
            <h2 style="font-size: 24px; margin-bottom: 5px;">Saldo e Receita</h2>
            <p style="opacity: 0.9; font-size: 14px;">Acompanhe seus ganhos</p>
            
            <div class="balance-grid">
                <div class="balance-item">
                    <h3>Disponível para Saque</h3>
                    <div class="value"><?php echo formatPrice($saldo['saldo_disponivel_centavos'] ?? 0); ?></div>
                </div>
                
                <div class="balance-item">
                    <h3>Pendente</h3>
                    <div class="value"><?php echo formatPrice($saldo['saldo_pendente_centavos'] ?? 0); ?></div>
                </div>
                
                <div class="balance-item">
                    <h3>Total de Vendas</h3>
                    <div class="value"><?php echo formatPrice($saldo['total_vendas_centavos'] ?? 0); ?></div>
                </div>
            </div>
            
            <?php if (($saldo['saldo_disponivel_centavos'] ?? 0) >= getConfig('valor_minimo_saque', $pdo)): ?>
            <a href="<?php echo SITE_URL; ?>/developer/saldo.php" 
               class="btn btn-success" 
               style="margin-top: 20px; background: white; color: var(--accent);">
                <i class="fas fa-money-bill-wave"></i> Solicitar Saque
            </a>
            <?php endif; ?>
        </div>
        
        <!-- Ações Rápidas -->
        <div class="quick-actions">
            <a href="<?php echo SITE_URL; ?>/developer/publicar-jogo.php" class="action-card">
                <div class="action-icon"><i class="fas fa-plus-circle"></i></div>
                <h3>Publicar Novo Jogo</h3>
            </a>
            
            <a href="<?php echo SITE_URL; ?>/developer/jogos.php" class="action-card">
                <div class="action-icon"><i class="fas fa-gamepad"></i></div>
                <h3>Gerenciar Jogos</h3>
            </a>
            
            <a href="<?php echo SITE_URL; ?>/developer/vendas.php" class="action-card">
                <div class="action-icon"><i class="fas fa-chart-bar"></i></div>
                <h3>Relatórios</h3>
            </a>
        </div>
        
        <!-- Conteúdo -->
        <div class="content-grid">
            <!-- Jogos Recentes -->
            <div class="content-section">
                <h2><i class="fas fa-gamepad"></i> Jogos Recentes</h2>
                
                <?php if (count($jogos_recentes) > 0): ?>
                    <?php foreach ($jogos_recentes as $jogo): ?>
                    <div class="game-item">
                        <img src="<?php echo SITE_URL . ($jogo['imagem_capa'] ?: '/assets/images/no-image.png'); ?>" 
                             alt="<?php echo sanitize($jogo['titulo']); ?>">
                        <div class="game-info">
                            <h3><?php echo sanitize($jogo['titulo']); ?></h3>
                            <span class="game-status status-<?php echo $jogo['status']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $jogo['status'])); ?>
                            </span>
                            <p style="font-size: 13px; color: var(--text-secondary); margin-top: 8px;">
                                <i class="fas fa-download"></i> <?php echo $jogo['total_vendas']; ?> vendas
                                | <i class="fas fa-star"></i> <?php echo number_format($jogo['nota_media'], 1); ?>
                            </p>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <a href="<?php echo SITE_URL; ?>/developer/editar-jogo.php?id=<?php echo $jogo['id']; ?>" 
                               class="btn btn-sm btn-secondary">
                                <i class="fas fa-edit"></i> Editar
                            </a>
                            <a href="<?php echo SITE_URL; ?>/pages/jogo.php?slug=<?php echo $jogo['slug']; ?>" 
                               class="btn btn-sm btn-primary">
                                <i class="fas fa-eye"></i> Ver
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <a href="<?php echo SITE_URL; ?>/developer/jogos.php" 
                       style="display: block; text-align: center; margin-top: 15px;">
                        Ver todos os jogos <i class="fas fa-arrow-right"></i>
                    </a>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px 20px;">
                        <i class="fas fa-gamepad" style="font-size: 48px; color: var(--text-secondary); margin-bottom: 15px;"></i>
                        <p style="color: var(--text-secondary);">Nenhum jogo publicado ainda</p>
                        <a href="<?php echo SITE_URL; ?>/developer/publicar-jogo.php" 
                           class="btn btn-primary" 
                           style="margin-top: 15px;">
                            <i class="fas fa-plus"></i> Publicar Primeiro Jogo
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Vendas Recentes -->
            <div class="content-section">
                <h2><i class="fas fa-receipt"></i> Vendas Recentes</h2>
                
                <?php if (count($vendas_recentes) > 0): ?>
                    <?php foreach ($vendas_recentes as $venda): ?>
                    <div class="sale-item">
                        <div class="sale-header">
                            <strong><?php echo sanitize($venda['descricao']); ?></strong>
                            <span class="sale-value"><?php echo formatPrice($venda['valor_centavos']); ?></span>
                        </div>
                        <div class="sale-date">
                            <?php echo date('d/m/Y H:i', strtotime($venda['criado_em'])); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px 20px;">
                        <i class="fas fa-receipt" style="font-size: 48px; color: var(--text-secondary); margin-bottom: 15px;"></i>
                        <p style="color: var(--text-secondary);">Nenhuma venda ainda</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>