<?php
// user/carrinho.php
require_once '../config/config.php';
require_once '../config/database.php';

requireLogin();

$database = new Database();
$pdo = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Buscar itens do carrinho
$stmt = $pdo->prepare("
    SELECT c.*, j.*, d.nome_estudio
    FROM carrinho c
    INNER JOIN jogo j ON c.jogo_id = j.id
    LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id
    WHERE c.usuario_id = ? AND j.status = 'publicado'
    ORDER BY c.adicionado_em DESC
");
$stmt->execute([$user_id]);
$itens = $stmt->fetchAll();

// Calcular totais
$subtotal = 0;
$desconto = 0;

foreach ($itens as $item) {
    $preco = $item['em_promocao'] && $item['preco_promocional_centavos'] 
        ? $item['preco_promocional_centavos'] 
        : $item['preco_centavos'];
    
    $subtotal += $preco;
    
    if ($item['em_promocao'] && $item['preco_promocional_centavos']) {
        $desconto += ($item['preco_centavos'] - $item['preco_promocional_centavos']);
    }
}

$total = $subtotal;

$page_title = 'Carrinho - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<style>
.cart-page {
    padding: 30px 0;
}

.cart-layout {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 30px;
}

.cart-items {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.cart-item {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 20px;
    display: grid;
    grid-template-columns: 150px 1fr auto;
    gap: 20px;
    align-items: center;
}

.cart-item-image {
    border-radius: 8px;
    overflow: hidden;
}

.cart-item-image img {
    width: 100%;
    display: block;
}

.cart-item-info h3 {
    font-size: 18px;
    margin-bottom: 8px;
}

.cart-item-dev {
    font-size: 13px;
    color: var(--text-secondary);
    margin-bottom: 12px;
}

.cart-item-price {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

.cart-item-price-old {
    font-size: 14px;
    color: var(--text-secondary);
    text-decoration: line-through;
}

.cart-item-price-current {
    font-size: 22px;
    font-weight: 700;
    color: var(--accent);
}

.cart-item-remove {
    background: var(--danger);
    color: white;
    border: none;
    padding: 8px 12px;
    border-radius: 6px;
    cursor: pointer;
    margin-top: 10px;
    transition: all 0.3s;
}

.cart-item-remove:hover {
    background: #c82333;
}

.cart-summary {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 25px;
    height: fit-content;
    position: sticky;
    top: 90px;
}

.cart-summary h2 {
    font-size: 22px;
    margin-bottom: 20px;
}

.summary-line {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid var(--border);
}

.summary-line:last-child {
    border-bottom: none;
    padding-top: 20px;
    margin-top: 10px;
    font-size: 20px;
    font-weight: 700;
}

.summary-line.discount {
    color: var(--success);
}

.empty-cart {
    text-align: center;
    padding: 60px 20px;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 15px;
}

.empty-cart i {
    font-size: 64px;
    color: var(--text-secondary);
    margin-bottom: 20px;
}

@media (max-width: 992px) {
    .cart-layout {
        grid-template-columns: 1fr;
    }
    
    .cart-item {
        grid-template-columns: 100px 1fr;
    }
    
    .cart-item-price {
        grid-column: 2;
        align-items: flex-start;
        margin-top: 10px;
    }
    
    .cart-summary {
        position: static;
    }
}
</style>

<div class="container">
    <div class="cart-page">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-shopping-cart"></i> Carrinho de Compras
            </h1>
            <p class="page-subtitle"><?php echo count($itens); ?> item<?php echo count($itens) != 1 ? 's' : ''; ?> no carrinho</p>
        </div>
        
        <?php if (count($itens) > 0): ?>
            <div class="cart-layout">
                <div class="cart-items">
                    <?php foreach ($itens as $item): ?>
                    <div class="cart-item" data-game-id="<?php echo $item['id']; ?>">
                        <div class="cart-item-image">
                            <img src="<?php echo SITE_URL . ($item['imagem_capa'] ?: '/assets/images/no-image.png'); ?>" 
                                 alt="<?php echo sanitize($item['titulo']); ?>">
                        </div>
                        
                        <div class="cart-item-info">
                            <h3>
                                <a href="<?php echo SITE_URL; ?>/pages/jogo.php?slug=<?php echo $item['slug']; ?>">
                                    <?php echo sanitize($item['titulo']); ?>
                                </a>
                            </h3>
                            <div class="cart-item-dev">
                                Por <?php echo sanitize($item['nome_estudio']); ?>
                            </div>
                            
                            <?php
                            $plats = $pdo->prepare("
                                SELECT p.* FROM plataforma p
                                INNER JOIN jogo_plataforma jp ON p.id = jp.plataforma_id
                                WHERE jp.jogo_id = ?
                            ");
                            $plats->execute([$item['id']]);
                            $plataformas = $plats->fetchAll();
                            ?>
                            <div style="display: flex; gap: 10px; margin-top: 8px;">
                                <?php foreach ($plataformas as $plat): ?>
                                    <i class="<?php echo $plat['icone']; ?>" title="<?php echo $plat['nome']; ?>"></i>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="cart-item-price">
                            <?php
                            $preco = $item['em_promocao'] && $item['preco_promocional_centavos'] 
                                ? $item['preco_promocional_centavos'] 
                                : $item['preco_centavos'];
                            
                            if ($preco == 0):
                            ?>
                                <span class="cart-item-price-current" style="color: var(--success);">GRÁTIS</span>
                            <?php else: ?>
                                <?php if ($item['em_promocao'] && $item['preco_promocional_centavos']): ?>
                                    <span class="cart-item-price-old"><?php echo formatPrice($item['preco_centavos']); ?></span>
                                <?php endif; ?>
                                <span class="cart-item-price-current"><?php echo formatPrice($preco); ?></span>
                            <?php endif; ?>
                            
                            <button class="cart-item-remove" onclick="removeFromCart(<?php echo $item['id']; ?>)">
                                <i class="fas fa-trash"></i> Remover
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="cart-summary">
                    <h2>Resumo do Pedido</h2>
                    
                    <div class="summary-line">
                        <span>Subtotal (<?php echo count($itens); ?> itens)</span>
                        <span id="subtotal"><?php echo formatPrice($subtotal); ?></span>
                    </div>
                    
                    <?php if ($desconto > 0): ?>
                    <div class="summary-line discount">
                        <span>Desconto</span>
                        <span>-<?php echo formatPrice($desconto); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="summary-line">
                        <span>Total</span>
                        <span id="cartTotal"><?php echo formatPrice($total); ?></span>
                    </div>
                    
                    <a href="<?php echo SITE_URL; ?>/pages/checkout.php" class="btn btn-primary btn-block" style="margin-top: 20px;">
                        <i class="fas fa-credit-card"></i> Finalizar Compra
                    </a>
                    
                    <a href="<?php echo SITE_URL; ?>/pages/busca.php" class="btn btn-secondary btn-block" style="margin-top: 10px;">
                        <i class="fas fa-arrow-left"></i> Continuar Comprando
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-cart">
                <i class="fas fa-shopping-cart"></i>
                <h2>Seu carrinho está vazio</h2>
                <p style="color: var(--text-secondary); margin-bottom: 30px;">
                    Explore nosso catálogo e adicione jogos incríveis ao carrinho!
                </p>
                <a href="<?php echo SITE_URL; ?>/pages/busca.php" class="btn btn-primary">
                    <i class="fas fa-search"></i> Explorar Jogos
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>