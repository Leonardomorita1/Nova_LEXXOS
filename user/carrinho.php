<?php
$pageTitle = "Carrinho";
require_once '../config/config.php';
require_once '../config/database.php';

if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/pages/login.php');
    exit;
}

$database = new Database();
$pdo = $database->getConnection();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// ============================================
// APLICAR CUPOM
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['aplicar_cupom'])) {
    $codigo_cupom = strtoupper(trim($_POST['codigo_cupom']));
    
    try {
        // Buscar cupom válido
        $stmt = $pdo->prepare("
            SELECT dc.*, d.id as dev_id
            FROM dev_cupom dc
            JOIN desenvolvedor d ON dc.desenvolvedor_id = d.id
            WHERE dc.codigo = ? 
            AND dc.ativo = 1
            AND (dc.validade IS NULL OR dc.validade >= CURDATE())
            AND (dc.usos_maximos IS NULL OR dc.usos_atuais < dc.usos_maximos)
        ");
        $stmt->execute([$codigo_cupom]);
        $cupom_encontrado = $stmt->fetch();
        
        if (!$cupom_encontrado) {
            throw new Exception('Cupom inválido ou expirado');
        }
        
        // Verificar se cupom se aplica aos jogos no carrinho
        $stmt = $pdo->prepare("
            SELECT c.*, j.preco_centavos, j.preco_promocional_centavos, j.em_promocao,
                   j.desenvolvedor_id, j.titulo, j.id as jogo_id
            FROM carrinho c
            JOIN jogo j ON c.jogo_id = j.id
            WHERE c.usuario_id = ?
        ");
        $stmt->execute([$user_id]);
        $itens_carrinho = $stmt->fetchAll();
        
        $cupom_aplicavel = false;
        foreach ($itens_carrinho as $item) {
            if ($item['desenvolvedor_id'] == $cupom_encontrado['dev_id'] && 
                ($cupom_encontrado['jogo_id'] === null || $cupom_encontrado['jogo_id'] == $item['jogo_id'])) {
                $cupom_aplicavel = true;
                break;
            }
        }
        
        if (!$cupom_aplicavel) {
            throw new Exception('Este cupom não se aplica aos jogos no seu carrinho');
        }
        
        // Salvar cupom na sessão
        $_SESSION['cupom_aplicado'] = $cupom_encontrado;
        $message = 'Cupom aplicado com sucesso!';
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// ============================================
// REMOVER CUPOM
// ============================================
if (isset($_GET['remover_cupom'])) {
    unset($_SESSION['cupom_aplicado']);
    header('Location: carrinho.php');
    exit;
}

// ============================================
// REMOVER ITEM DO CARRINHO
// ============================================
if (isset($_GET['remover']) && is_numeric($_GET['remover'])) {
    $stmt = $pdo->prepare("DELETE FROM carrinho WHERE jogo_id = ? AND usuario_id = ?");
    $stmt->execute([$_GET['remover'], $user_id]);
    
    // Verificar se cupom ainda é válido após remover item
    if (isset($_SESSION['cupom_aplicado'])) {
        $cupom_sessao = $_SESSION['cupom_aplicado'];
        $stmt = $pdo->prepare("
            SELECT c.*, j.desenvolvedor_id, j.id as jogo_id
            FROM carrinho c
            JOIN jogo j ON c.jogo_id = j.id
            WHERE c.usuario_id = ?
        ");
        $stmt->execute([$user_id]);
        $itens_restantes = $stmt->fetchAll();
        
        $cupom_ainda_valido = false;
        foreach ($itens_restantes as $item) {
            if ($item['desenvolvedor_id'] == $cupom_sessao['dev_id'] && 
                ($cupom_sessao['jogo_id'] === null || $cupom_sessao['jogo_id'] == $item['jogo_id'])) {
                $cupom_ainda_valido = true;
                break;
            }
        }
        
        if (!$cupom_ainda_valido) {
            unset($_SESSION['cupom_aplicado']);
        }
    }
    
    header('Location: carrinho.php');
    exit;
}

// ============================================
// LIMPAR CARRINHO
// ============================================
if (isset($_GET['limpar'])) {
    $stmt = $pdo->prepare("DELETE FROM carrinho WHERE usuario_id = ?");
    $stmt->execute([$user_id]);
    unset($_SESSION['cupom_aplicado']);
    header('Location: carrinho.php');
    exit;
}

// ============================================
// BUSCAR ITENS DO CARRINHO
// ============================================
$stmt = $pdo->prepare("
    SELECT j.*, c.id as carrinho_id, c.adicionado_em, d.id as desenvolvedor_id,
           CASE WHEN j.em_promocao = 1 AND j.preco_promocional_centavos IS NOT NULL 
                THEN j.preco_promocional_centavos 
                ELSE j.preco_centavos 
           END as preco_final
    FROM carrinho c
    INNER JOIN jogo j ON c.jogo_id = j.id
    INNER JOIN desenvolvedor d ON j.desenvolvedor_id = d.id
    WHERE c.usuario_id = ?
    ORDER BY c.adicionado_em DESC
");
$stmt->execute([$user_id]);
$itens = $stmt->fetchAll();

// ============================================
// CALCULAR TOTAIS
// ============================================
$subtotal = 0;
$economia = 0;
$desconto_cupom = 0;

foreach ($itens as $item) {
    $subtotal += $item['preco_final'];
    if ($item['em_promocao'] && $item['preco_promocional_centavos']) {
        $economia += ($item['preco_centavos'] - $item['preco_promocional_centavos']);
    }
}

// ============================================
// APLICAR DESCONTO DO CUPOM
// ============================================
$cupom = $_SESSION['cupom_aplicado'] ?? null;

if ($cupom) {
    foreach ($itens as $item) {
        // Verificar se cupom se aplica a este item
        if ($item['desenvolvedor_id'] == $cupom['dev_id'] && 
            ($cupom['jogo_id'] === null || $cupom['jogo_id'] == $item['id'])) {
            
            $preco_item = $item['preco_final'];
            
            if ($cupom['tipo_desconto'] == 'percentual') {
                $desconto_item = ($preco_item * $cupom['valor_desconto']) / 100;
            } else {
                // Desconto fixo: aplicar no máximo o valor do item
                $desconto_item = min($cupom['valor_desconto'], $preco_item);
            }
            
            $desconto_cupom += $desconto_item;
        }
    }
    
    // Atualizar sessão com o desconto calculado
    $_SESSION['cupom_aplicado']['desconto_calculado'] = $desconto_cupom;
}

$total = max(0, $subtotal - $desconto_cupom);

require_once '../includes/header.php';
?>

<main class="cart-page">
    <div class="container">
        <div class="cart-header">
            <div class="cart-header-left">
                <h1><i class="fas fa-shopping-cart"></i> Meu Carrinho</h1>
                <span class="cart-count-label"><?= count($itens) ?> item(ns)</span>
            </div>
            
            <?php if (count($itens) > 0): ?>
            <div class="cart-header-actions">
                <a href="?limpar=1" class="btn btn-ghost btn-sm" onclick="return confirm('Tem certeza que deseja limpar o carrinho?')">
                    <i class="fas fa-trash-alt"></i> Limpar Carrinho
                </a>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if ($message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?= sanitize($message) ?>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?= sanitize($error) ?>
        </div>
        <?php endif; ?>
        
        <?php if (count($itens) > 0): ?>
        <div class="cart-layout">
            <!-- Lista de Itens -->
            <div class="cart-items">
                <?php foreach ($itens as $item): 
                    $em_promocao = ($item['em_promocao'] && $item['preco_promocional_centavos'] && $item['preco_promocional_centavos'] < $item['preco_centavos']);
                    $desconto = $em_promocao ? round((1 - $item['preco_promocional_centavos'] / $item['preco_centavos']) * 100) : 0;
                    $imagem = (!empty($item['imagem_capa']) && file_exists(BASE_PATH . $item['imagem_capa'])) 
                        ? SITE_URL . $item['imagem_capa'] 
                        : SITE_URL . '/assets/images/no-image.png';
                ?>
                <article class="cart-item" data-cart-item data-jogo-id="<?= $item['id'] ?>">
                    <a href="<?= SITE_URL ?>/pages/jogo.php?slug=<?= $item['slug'] ?>" class="cart-item-image">
                        <img src="<?= $imagem ?>" alt="<?= sanitize($item['titulo']) ?>">
                        <?php if ($em_promocao): ?>
                        <span class="cart-item-discount">-<?= $desconto ?>%</span>
                        <?php endif; ?>
                    </a>
                    
                    <div class="cart-item-info">
                        <a href="<?= SITE_URL ?>/pages/jogo.php?slug=<?= $item['slug'] ?>" class="cart-item-title">
                            <?= sanitize($item['titulo']) ?>
                        </a>
                        <div class="cart-item-meta">
                            <span class="age-badge age-<?= strtolower($item['classificacao_etaria'] ?? 'L') ?>">
                                <?= $item['classificacao_etaria'] ?? 'L' ?>
                            </span>
                            <span class="cart-item-date">
                                Adicionado em <?= date('d/m/Y', strtotime($item['adicionado_em'])) ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="cart-item-price">
                        <?php if ($em_promocao): ?>
                        <span class="price-old"><?= formatPrice($item['preco_centavos']) ?></span>
                        <?php endif; ?>
                        <span class="price-current <?= $em_promocao ? 'sale' : '' ?>">
                            <?= $item['preco_final'] == 0 ? 'Grátis' : formatPrice($item['preco_final']) ?>
                        </span>
                    </div>
                    
                    <a href="?remover=<?= $item['id'] ?>" 
                       class="cart-item-remove" 
                       onclick="return confirm('Remover este item do carrinho?')"
                       data-tooltip="Remover">
                        <i class="fas fa-times"></i>
                    </a>
                </article>
                <?php endforeach; ?>
            </div>
            
            <!-- Resumo -->
            <aside class="cart-summary">
                <h2 class="cart-summary-title">Resumo do Pedido</h2>
                
                <div class="cart-summary-rows">
                    <div class="cart-summary-row">
                        <span>Subtotal (<?= count($itens) ?> item<?= count($itens) > 1 ? 's' : '' ?>)</span>
                        <span id="subtotal-value"><?= formatPrice($subtotal) ?></span>
                    </div>
                    
                    <?php if ($economia > 0): ?>
                    <div class="cart-summary-row cart-summary-economy">
                        <span><i class="fas fa-tag"></i> Economia em promoções</span>
                        <span>-<?= formatPrice($economia) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($cupom && $desconto_cupom > 0): ?>
                    <div class="cart-summary-row cart-summary-coupon">
                        <span>
                            <i class="fas fa-ticket-alt"></i> Cupom: <?= sanitize($cupom['codigo']) ?>
                            <a href="?remover_cupom=1" class="coupon-remove-btn" title="Remover cupom">
                                <i class="fas fa-times"></i>
                            </a>
                        </span>
                        <span>-<?= formatPrice($desconto_cupom) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="cart-summary-total">
                    <span>Total</span>
                    <span id="total-value"><?= formatPrice($total) ?></span>
                </div>
                
                <?php if (!$cupom): ?>
                <form method="POST" class="cart-coupon-form">
                    <input type="text" 
                           name="codigo_cupom"
                           id="coupon-input" 
                           class="form-input" 
                           placeholder="Código do cupom"
                           required
                           style="text-transform: uppercase;">
                    <button type="submit" name="aplicar_cupom" class="btn btn-secondary">
                        Aplicar
                    </button>
                </form>
                <?php else: ?>
                <div class="coupon-applied-box">
                    <i class="fas fa-check-circle"></i>
                    <span>
                        <?php if ($cupom['tipo_desconto'] == 'percentual'): ?>
                            <?= $cupom['valor_desconto'] ?>% de desconto
                        <?php else: ?>
                            <?= formatPrice($cupom['valor_desconto']) ?> de desconto
                        <?php endif; ?>
                    </span>
                </div>
                <?php endif; ?>
                
                <a href="<?= SITE_URL ?>/pages/checkout.php" class="btn btn-primary btn-block btn-lg cart-checkout-btn">
                    <i class="fas fa-lock"></i> Finalizar Compra
                </a>
                
                <a href="<?= SITE_URL ?>/pages/home.php" class="cart-continue-link">
                    <i class="fas fa-arrow-left"></i> Continuar Comprando
                </a>
            </aside>
        </div>
        
        <?php else: ?>
        <!-- Carrinho Vazio -->
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <h2>Seu carrinho está vazio</h2>
            <p>Explore nossa loja e encontre jogos incríveis para adicionar ao seu carrinho!</p>
            <a href="<?= SITE_URL ?>/pages/home.php" class="btn btn-primary btn-lg">
                <i class="fas fa-gamepad"></i> Explorar Loja
            </a>
        </div>
        <?php endif; ?>
    </div>
</main>

<style>
/* ============================================
   CART PAGE STYLES
   ============================================ */
.cart-page {
    padding: 40px 0;
    min-height: 70vh;
}

.cart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 40px;
    flex-wrap: wrap;
    gap: 20px;
}

.cart-header-left {
    display: flex;
    align-items: center;
    gap: 15px;
}

.cart-header h1 {
    font-size: 2rem;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 15px;
}

.cart-header h1 i {
    color: var(--accent);
}

.cart-count-label {
    padding: 6px 14px;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 20px;
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.cart-layout {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 40px;
    align-items: start;
}

/* Alerts */
.alert {
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 500;
}

.alert-success {
    background: rgba(46, 204, 113, 0.15);
    color: var(--success);
    border: 1px solid rgba(46, 204, 113, 0.3);
}

.alert-error {
    background: rgba(220, 53, 69, 0.15);
    color: var(--danger);
    border: 1px solid rgba(220, 53, 69, 0.3);
}

/* Cart Items */
.cart-items {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.cart-item {
    display: grid;
    grid-template-columns: 120px 1fr auto auto;
    gap: 20px;
    padding: 20px;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 16px;
    align-items: center;
    transition: all 0.3s ease;
}

.cart-item:hover {
    border-color: var(--accent);
}

.cart-item-image {
    position: relative;
    aspect-ratio: 3/4;
    border-radius: 12px;
    overflow: hidden;
}

.cart-item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.cart-item-discount {
    position: absolute;
    top: 8px;
    left: 8px;
    background: var(--success);
    color: #fff;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 700;
}

.cart-item-info {
    display: flex;
    flex-direction: column;
    gap: 10px;
    min-width: 0;
}

.cart-item-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-primary);
    text-decoration: none;
    transition: color 0.2s;
}

.cart-item-title:hover {
    color: var(--accent);
}

.cart-item-meta {
    display: flex;
    align-items: center;
    gap: 12px;
}

.cart-item-date {
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.cart-item-price {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 4px;
    min-width: 120px;
}

.cart-item-price .price-old {
    font-size: 0.85rem;
    color: var(--text-secondary);
    text-decoration: line-through;
}

.cart-item-price .price-current {
    font-size: 1.3rem;
    font-weight: 800;
    color: var(--accent);
}

.cart-item-price .price-current.sale {
    color: var(--success);
}

.cart-item-remove {
    width: 40px;
    height: 40px;
    border: none;
    background: transparent;
    color: var(--text-secondary);
    cursor: pointer;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
    text-decoration: none;
}

.cart-item-remove:hover {
    background: rgba(220, 53, 69, 0.15);
    color: var(--danger);
}

/* Cart Summary */
.cart-summary {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 30px;
    position: sticky;
    top: 100px;
}

.cart-summary-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border);
}

.cart-summary-rows {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin-bottom: 20px;
}

.cart-summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.95rem;
    color: var(--text-secondary);
}

.cart-summary-row i {
    margin-right: 8px;
}

.cart-summary-economy {
    color: var(--success);
}

.cart-summary-coupon {
    color: var(--accent);
}

.coupon-remove-btn {
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    padding: 2px 6px;
    margin-left: 8px;
    border-radius: 4px;
    transition: all 0.2s;
    text-decoration: none;
}

.coupon-remove-btn:hover {
    background: rgba(220, 53, 69, 0.15);
    color: var(--danger);
}

.cart-summary-total {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 0;
    border-top: 2px solid var(--border);
    margin-bottom: 25px;
}

.cart-summary-total span:first-child {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
}

.cart-summary-total span:last-child {
    font-size: 1.8rem;
    font-weight: 800;
    color: var(--accent);
}

.cart-coupon-form {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.cart-coupon-form .form-input {
    flex: 1;
}

.coupon-applied-box {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    background: rgba(46, 204, 113, 0.1);
    border: 1px solid rgba(46, 204, 113, 0.3);
    border-radius: 10px;
    margin-bottom: 20px;
    color: var(--success);
    font-size: 0.9rem;
    font-weight: 500;
}

.coupon-applied-box i {
    font-size: 1.1rem;
}

.cart-checkout-btn {
    margin-bottom: 15px;
}

.cart-continue-link {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    color: var(--text-secondary);
    text-decoration: none;
    font-size: 0.9rem;
    transition: color 0.2s;
}

.cart-continue-link:hover {
    color: var(--accent);
}

/* ============================================
   RESPONSIVE
   ============================================ */
@media (max-width: 1024px) {
    .cart-layout {
        grid-template-columns: 1fr;
        gap: 30px;
    }
    
    .cart-summary {
        position: static;
    }
}

@media (max-width: 768px) {
    .cart-page {
        padding: 25px 0;
    }
    
    .cart-header {
        margin-bottom: 25px;
    }
    
    .cart-header h1 {
        font-size: 1.5rem;
    }
    
    .cart-item {
        grid-template-columns: 80px 1fr auto;
        gap: 15px;
        padding: 15px;
    }
    
    .cart-item-price {
        grid-column: 2 / 3;
        grid-row: 2;
        align-items: flex-start;
        min-width: auto;
    }
    
    .cart-item-remove {
        grid-column: 3;
        grid-row: 1 / 3;
    }
    
    .cart-item-title {
        font-size: 1rem;
    }
    
    .cart-item-price .price-current {
        font-size: 1.1rem;
    }
    
    .cart-summary {
        padding: 20px;
        border-radius: 16px;
    }
    
    .cart-summary-total span:last-child {
        font-size: 1.5rem;
    }
}

@media (max-width: 480px) {
    .cart-item {
        grid-template-columns: 70px 1fr;
        gap: 12px;
    }
    
    .cart-item-remove {
        position: absolute;
        top: 10px;
        right: 10px;
    }
    
    .cart-item {
        position: relative;
    }
    
    .cart-coupon-form {
        flex-direction: column;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>