<?php
// admin/pedido-detalhes.php
require_once '../config/config.php';
require_once '../config/database.php';
requireLogin();
if (getUserType() !== 'admin') { header('Location: ' . SITE_URL . '/pages/home.php'); exit; }

if (!isset($_GET['id'])) {
    header('Location: ' . SITE_URL . '/admin/pedidos.php');
    exit;
}

$database = new Database();
$pdo = $database->getConnection();
$pedido_id = $_GET['id'];

// Buscar pedido
$stmt = $pdo->prepare("
    SELECT p.*, u.nome_usuario, u.email, c.codigo as cupom_codigo
    FROM pedido p
    JOIN usuario u ON p.usuario_id = u.id
    LEFT JOIN dev_cupom c ON p.cupom_id = c.id
    WHERE p.id = ?
");
$stmt->execute([$pedido_id]);
$pedido = $stmt->fetch();

if (!$pedido) {
    header('Location: ' . SITE_URL . '/admin/pedidos.php');
    exit;
}

// Buscar itens do pedido
$stmt = $pdo->prepare("
    SELECT ip.*, j.titulo, j.imagem_capa 
    FROM item_pedido ip
    JOIN jogo j ON ip.jogo_id = j.id
    WHERE ip.pedido_id = ?
");
$stmt->execute([$pedido_id]);
$itens = $stmt->fetchAll();

$page_title = 'Pedido #' . $pedido['numero'] . ' - Admin - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo SITE_URL; ?>/admin/assets/css/admin.css">

<div class="container">
    <div class="admin-layout">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="admin-header">
                <div>
                    <a href="<?php echo SITE_URL; ?>/admin/pedidos.php" style="color: var(--text-secondary); margin-bottom: 10px; display: inline-block;">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                    <h1 class="admin-title">Pedido #<?php echo $pedido['numero']; ?></h1>
                </div>
                <?php
                $status_colors = [
                    'pendente' => 'warning',
                    'pago' => 'success',
                    'cancelado' => 'danger',
                    'reembolsado' => 'info'
                ];
                ?>
                <span class="badge badge-<?php echo $status_colors[$pedido['status']]; ?>" 
                      style="font-size: 16px; padding: 8px 16px;">
                    <?php echo ucfirst($pedido['status']); ?>
                </span>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
                <!-- Itens do Pedido -->
                <div>
                    <div class="form-section">
                        <h2>Itens do Pedido</h2>
                        <?php foreach ($itens as $item): ?>
                        <div style="display: flex; gap: 15px; padding: 15px; background: var(--bg-primary); border-radius: 8px; margin-bottom: 15px;">
                            <img src="<?php echo SITE_URL . ($item['imagem_capa'] ?: '/assets/images/no-image.png'); ?>" 
                                 style="width: 80px; height: 106px; object-fit: cover; border-radius: 6px;">
                            <div style="flex: 1;">
                                <strong style="font-size: 16px; display: block; margin-bottom: 5px;">
                                    <?php echo sanitize($item['titulo']); ?>
                                </strong>
                                <div style="color: var(--text-secondary); font-size: 14px;">
                                    Preço: <?php echo formatPrice($item['preco_centavos']); ?>
                                    <?php if ($item['desconto_centavos'] > 0): ?>
                                        <br>Desconto: -<?php echo formatPrice($item['desconto_centavos']); ?>
                                    <?php endif; ?>
                                    <br><strong style="color: var(--success);">
                                        Total: <?php echo formatPrice($item['valor_final_centavos']); ?>
                                    </strong>
                                </div>
                            </div>
                            <div style="text-align: right; font-size: 13px; color: var(--text-secondary);">
                                <div>Plataforma: <?php echo formatPrice($item['valor_plataforma_centavos']); ?></div>
                                <div>Desenvolvedor: <?php echo formatPrice($item['valor_desenvolvedor_centavos']); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <!-- Totais -->
                        <div style="border-top: 2px solid var(--border); padding-top: 20px; margin-top: 20px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                <span>Subtotal:</span>
                                <strong><?php echo formatPrice($pedido['subtotal_centavos']); ?></strong>
                            </div>
                            <?php if ($pedido['desconto_centavos'] > 0): ?>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 10px; color: var(--success);">
                                <span>Desconto <?php echo $pedido['cupom_codigo'] ? '(Cupom: ' . $pedido['cupom_codigo'] . ')' : ''; ?>:</span>
                                <strong>-<?php echo formatPrice($pedido['desconto_centavos']); ?></strong>
                            </div>
                            <?php endif; ?>
                            <div style="display: flex; justify-content: space-between; font-size: 20px; font-weight: 700; color: var(--accent);">
                                <span>Total:</span>
                                <span><?php echo formatPrice($pedido['total_centavos']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Informações do Pedido -->
                <div>
                    <div class="form-section">
                        <h2>Informações</h2>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="color: var(--text-secondary); font-size: 13px; display: block; margin-bottom: 5px;">
                                Cliente
                            </label>
                            <strong><?php echo sanitize($pedido['nome_usuario']); ?></strong>
                            <br><small style="color: var(--text-secondary);"><?php echo sanitize($pedido['email']); ?></small>
                            <br><a href="<?php echo SITE_URL; ?>/admin/usuario-detalhes.php?id=<?php echo $pedido['usuario_id']; ?>" 
                                   style="font-size: 13px; margin-top: 5px; display: inline-block;">
                                Ver perfil <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>

                        <div style="margin-bottom: 15px;">
                            <label style="color: var(--text-secondary); font-size: 13px; display: block; margin-bottom: 5px;">
                                Método de Pagamento
                            </label>
                            <?php 
                            $metodos = [
                                'pix' => 'PIX',
                                'cartao_credito' => 'Cartão de Crédito',
                                'boleto' => 'Boleto Bancário'
                            ];
                            ?>
                            <strong><?php echo $metodos[$pedido['metodo_pagamento']] ?? ucfirst($pedido['metodo_pagamento']); ?></strong>
                        </div>

                        <?php if ($pedido['gateway_id']): ?>
                        <div style="margin-bottom: 15px;">
                            <label style="color: var(--text-secondary); font-size: 13px; display: block; margin-bottom: 5px;">
                                ID Gateway
                            </label>
                            <code><?php echo sanitize($pedido['gateway_id']); ?></code>
                        </div>
                        <?php endif; ?>

                        <div style="margin-bottom: 15px;">
                            <label style="color: var(--text-secondary); font-size: 13px; display: block; margin-bottom: 5px;">
                                IP de Compra
                            </label>
                            <code><?php echo $pedido['ip_compra'] ?? '-'; ?></code>
                        </div>

                        <div style="margin-bottom: 15px;">
                            <label style="color: var(--text-secondary); font-size: 13px; display: block; margin-bottom: 5px;">
                                Data do Pedido
                            </label>
                            <strong><?php echo date('d/m/Y H:i:s', strtotime($pedido['criado_em'])); ?></strong>
                        </div>

                        <?php if ($pedido['pago_em']): ?>
                        <div style="margin-bottom: 15px;">
                            <label style="color: var(--text-secondary); font-size: 13px; display: block; margin-bottom: 5px;">
                                Data do Pagamento
                            </label>
                            <strong><?php echo date('d/m/Y H:i:s', strtotime($pedido['pago_em'])); ?></strong>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Estatísticas -->
                    <div class="form-section" style="margin-top: 20px;">
                        <h2>Distribuição</h2>
                        <?php
                        $total_plataforma = 0;
                        $total_devs = 0;
                        foreach ($itens as $item) {
                            $total_plataforma += $item['valor_plataforma_centavos'];
                            $total_devs += $item['valor_desenvolvedor_centavos'];
                        }
                        ?>
                        <div style="margin-bottom: 15px;">
                            <label style="color: var(--text-secondary); font-size: 13px; display: block; margin-bottom: 5px;">
                                Plataforma (Taxa)
                            </label>
                            <strong style="color: var(--accent);"><?php echo formatPrice($total_plataforma); ?></strong>
                        </div>
                        <div style="margin-bottom: 15px;">
                            <label style="color: var(--text-secondary); font-size: 13px; display: block; margin-bottom: 5px;">
                                Desenvolvedores
                            </label>
                            <strong style="color: var(--success);"><?php echo formatPrice($total_devs); ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>