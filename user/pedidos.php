<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireLogin();

$database = new Database();
$pdo = $database->getConnection();
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT p.*, 
           (SELECT COUNT(*) FROM item_pedido WHERE pedido_id = p.id) as total_itens
    FROM pedido p
    WHERE p.usuario_id = ?
    ORDER BY p.criado_em DESC
");
$stmt->execute([$user_id]);
$pedidos = $stmt->fetchAll();

$page_title = 'Meus Pedidos - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<div class="container" style="padding: 30px 0;">
    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-receipt"></i> Meus Pedidos</h1>
        <p class="page-subtitle"><?php echo count($pedidos); ?> pedido<?php echo count($pedidos) != 1 ? 's' : ''; ?></p>
    </div>
    
    <?php if (count($pedidos) > 0): ?>
        <div style="display: flex; flex-direction: column; gap: 20px;">
            <?php foreach ($pedidos as $pedido): ?>
            <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; padding: 25px;">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px;">
                    <div>
                        <h3 style="font-size: 18px; margin-bottom: 5px;">Pedido #<?php echo $pedido['numero']; ?></h3>
                        <p style="color: var(--text-secondary); font-size: 14px;">
                            <?php echo date('d/m/Y H:i', strtotime($pedido['criado_em'])); ?>
                        </p>
                    </div>
                    <div style="text-align: right;">
                        <span style="display: inline-block; padding: 6px 12px; border-radius: 4px; font-size: 13px; font-weight: 600; 
                            <?php
                            echo match($pedido['status']) {
                                'pago' => 'background: rgba(40,167,69,0.1); color: var(--success);',
                                'pendente' => 'background: rgba(255,193,7,0.1); color: var(--warning);',
                                'cancelado' => 'background: rgba(220,53,69,0.1); color: var(--danger);',
                                default => 'background: var(--bg-primary); color: var(--text-secondary);'
                            };
                            ?>">
                            <?php echo ucfirst($pedido['status']); ?>
                        </span>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; padding: 15px; background: var(--bg-primary); border-radius: 8px;">
                    <div>
                        <p style="font-size: 12px; color: var(--text-secondary); margin-bottom: 5px;">Total de Itens</p>
                        <p style="font-size: 18px; font-weight: 600;"><?php echo $pedido['total_itens']; ?></p>
                    </div>
                    <div>
                        <p style="font-size: 12px; color: var(--text-secondary); margin-bottom: 5px;">Valor Total</p>
                        <p style="font-size: 18px; font-weight: 600; color: var(--accent);">
                            <?php echo formatPrice($pedido['total_centavos']); ?>
                        </p>
                    </div>
                    <div>
                        <p style="font-size: 12px; color: var(--text-secondary); margin-bottom: 5px;">Pagamento</p>
                        <p style="font-size: 14px; font-weight: 600;">
                            <?php echo ucfirst(str_replace('_', ' ', $pedido['metodo_pagamento'] ?? 'N/A')); ?>
                        </p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 60px 20px; background: var(--bg-secondary); border-radius: 15px;">
            <i class="fas fa-receipt" style="font-size: 64px; color: var(--text-secondary); margin-bottom: 20px;"></i>
            <h2>Nenhum pedido realizado</h2>
            <p style="color: var(--text-secondary); margin-bottom: 30px;">Comece a comprar jogos incríveis!</p>
            <a href="<?php echo SITE_URL; ?>/pages/busca.php" class="btn btn-primary">
                <i class="fas fa-search"></i> Explorar Jogos
            </a>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>