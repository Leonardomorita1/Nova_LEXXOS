<?php
// pages/checkout.php - Checkout Redesenhado
require_once '../config/config.php';
require_once '../config/database.php';

requireLogin();

$database = new Database();
$pdo = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Buscar dados do usuário
$stmt = $pdo->prepare("SELECT * FROM usuario WHERE id = ?");
$stmt->execute([$user_id]);
$usuario = $stmt->fetch();

// Buscar itens do carrinho
$stmt = $pdo->prepare("
    SELECT c.*, j.*, d.nome_estudio
    FROM carrinho c
    INNER JOIN jogo j ON c.jogo_id = j.id
    LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id
    WHERE c.usuario_id = ? AND j.status = 'publicado'
");
$stmt->execute([$user_id]);
$itens = $stmt->fetchAll();

if (count($itens) == 0) {
    header('Location: ' . SITE_URL . '/user/carrinho.php');
    exit;
}

// Calcular totais
$subtotal = 0;
$desconto = 0;
foreach ($itens as $item) {
    $original = $item['preco_centavos'];
    $preco = $item['em_promocao'] && $item['preco_promocional_centavos'] 
        ? $item['preco_promocional_centavos'] 
        : $item['preco_centavos'];
    $subtotal += $preco;
    if ($original > $preco) {
        $desconto += ($original - $preco);
    }
}

// Cupom
$cupom = $_SESSION['cupom_aplicado'] ?? null;
$desconto_cupom = 0;
if ($cupom) {
    if ($cupom['tipo_desconto'] == 'percentual') {
        $desconto_cupom = ($subtotal * $cupom['valor_desconto']) / 100;
    } else {
        $desconto_cupom = $cupom['valor_desconto'];
    }
}

$total = max(0, $subtotal - $desconto_cupom);

$page_title = 'Finalizar Compra - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<style>
/* ===========================================
   CHECKOUT - ESTILOS PRINCIPAIS
   =========================================== */
:root {
    --checkout-green: #10b981;
    --checkout-blue: #3b82f6;
    --checkout-purple: #8b5cf6;
}

.checkout-page {
    padding: 40px 0 100px;
    min-height: 100vh;
}

/* ===========================================
   PROGRESS STEPS
   =========================================== */
.checkout-progress {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0;
    margin-bottom: 50px;
    padding: 0 20px;
}

.progress-step {
    display: flex;
    align-items: center;
    gap: 12px;
}

.step-circle {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1rem;
    transition: all 0.3s;
}

.step-circle.completed {
    background: var(--checkout-green);
    color: #fff;
}

.step-circle.active {
    background: var(--accent);
    color: #000;
    box-shadow: 0 0 0 4px rgba(185, 255, 102, 0.2);
}

.step-circle.pending {
    background: var(--bg-secondary);
    color: var(--text-secondary);
    border: 2px solid var(--border);
}

.step-label {
    font-weight: 600;
    font-size: 0.9rem;
}

.step-label.active {
    color: var(--accent);
}

.step-label.completed {
    color: var(--checkout-green);
}

.step-label.pending {
    color: var(--text-secondary);
}

.step-connector {
    width: 80px;
    height: 3px;
    background: var(--border);
    margin: 0 15px;
}

.step-connector.completed {
    background: var(--checkout-green);
}

/* ===========================================
   CHECKOUT LAYOUT
   =========================================== */
.checkout-layout {
    display: grid;
    grid-template-columns: 1fr 420px;
    gap: 40px;
    align-items: start;
}

/* ===========================================
   PAYMENT SECTION
   =========================================== */
.payment-section {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 20px;
    overflow: hidden;
}

.section-header {
    padding: 25px 30px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 12px;
}

.section-header i {
    font-size: 1.3rem;
    color: var(--accent);
}

.section-header h2 {
    font-size: 1.2rem;
    font-weight: 700;
    margin: 0;
}

.section-content {
    padding: 30px;
}

/* Payment Methods */
.payment-methods {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin-bottom: 30px;
}

.payment-method {
    position: relative;
}

.payment-method input[type="radio"] {
    position: absolute;
    opacity: 0;
}

.payment-method label {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 25px 20px;
    background: var(--bg-primary);
    border: 2px solid var(--border);
    border-radius: 16px;
    cursor: pointer;
    transition: all 0.3s;
    text-align: center;
}

.payment-method label:hover {
    border-color: var(--accent);
    background: var(--bg-secondary);
}

.payment-method input[type="radio"]:checked + label {
    border-color: var(--accent);
    background: var(--bg-secondary);
}

.payment-method input[type="radio"]:checked + label::before {
    content: '';
    position: absolute;
    top: 12px;
    right: 12px;
    width: 20px;
    height: 20px;
    background: var(--accent);
    border-radius: 50%;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='20 6 9 17 4 12'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: center;
}

.method-icon {
    width: 60px;
    height: 60px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    margin-bottom: 15px;
    transition: all 0.3s;
}

.payment-method input[type="radio"]:checked + label .method-icon {
    background: var(--bg-primary);
}

.method-icon.pix {
    color: var(--checkout-blue);
}

.method-icon.card {
    color: var(--checkout-blue);
}

.method-name {
    font-weight: 700;
    font-size: 1rem;
    margin-bottom: 6px;
}

.method-desc {
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.method-badge {
    display: inline-block;
    margin-top: 10px;
    padding: 4px 10px;
    background: rgba(16, 185, 129, 0.1);
    color: var(--checkout-green);
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
}



/* Submit Button */
.btn-proceed {
    width: 100%;
    padding: 18px 30px;
    background: var(--accent);
    color: #000;
    border: none;
    border-radius: 14px;
    font-size: 1.1rem;
    font-weight: 800;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    margin-top: 25px;
    transition: all 0.3s;
    box-shadow: 0 8px 25px var(--accent-hover);
}

.btn-proceed:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 40px var(--accent-hover);
}

/* ===========================================
   ORDER SUMMARY
   =========================================== */
.order-summary {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 20px;
    position: sticky;
    top: 100px;
    overflow: hidden;
}

.summary-header {
    padding: 25px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 12px;
}

.summary-header i {
    color: var(--accent);
    font-size: 1.2rem;
}

.summary-header h2 {
    font-size: 1.1rem;
    font-weight: 700;
    margin: 0;
}

.summary-items {
    max-height: 350px;
    overflow-y: auto;
    padding: 15px 25px;
}

.summary-item {
    display: flex;
    gap: 15px;
    padding: 15px 0;
    border-bottom: 1px solid var(--border);
}

.summary-item:last-child {
    border-bottom: none;
}

.item-thumb {
    width: 60px;
    height: 80px;
    border-radius: 10px;
    overflow: hidden;
    flex-shrink: 0;
}

.item-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.item-details {
    flex: 1;
    min-width: 0;
}

.item-details h4 {
    font-size: 0.9rem;
    font-weight: 600;
    margin: 0 0 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.item-details .dev {
    font-size: 0.75rem;
    color: var(--text-secondary);
    margin-bottom: 8px;
}

.item-details .price {
    font-weight: 700;
    color: var(--accent);
    font-size: 0.95rem;
}

.item-details .price-old {
    font-size: 0.8rem;
    color: var(--text-secondary);
    text-decoration: line-through;
    margin-right: 8px;
}

/* Summary Totals */
.summary-totals {
    padding: 20px 25px;
    background: rgba(0, 0, 0, 0.2);
}

.total-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    font-size: 0.9rem;
}

.total-row .label {
    color: var(--text-secondary);
}

.total-row.discount .value {
    color: var(--checkout-green);
}

.total-row.final {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 2px solid var(--border);
    font-size: 1.2rem;
    font-weight: 800;
}

.total-row.final .value {
    color: var(--accent);
}

/* Security Footer */
.summary-security {
    padding: 20px 25px;
    text-align: center;
    border-top: 1px solid var(--border);
}

.security-badges {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-bottom: 12px;
}

.security-badges i {
    font-size: 1.5rem;
    color: var(--text-secondary);
    opacity: 0.5;
}

.security-text {
    font-size: 0.8rem;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.security-text i {
    color: var(--checkout-green);
}

/* ===========================================
   RESPONSIVE
   =========================================== */
@media (max-width: 1024px) {
    .checkout-layout {
        grid-template-columns: 1fr;
    }
    
    .order-summary {
        position: static;
        order: -1;
    }
    
    .checkout-progress {
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .step-connector {
        display: none;
    }
}

@media (max-width: 768px) {
    .payment-methods {
        grid-template-columns: 1fr;
    }
    
    .checkout-progress {
        justify-content: flex-start;
    }
    
    .progress-step {
        flex: 1;
        justify-content: center;
    }
    
    .step-label {
        display: none;
    }
}
</style>

<div class="container">
    <div class="checkout-page">
        
        <!-- Progress Steps -->
        <nav class="checkout-progress">
            <div class="progress-step">
                <div class="step-circle completed">
                    <i class="fas fa-check"></i>
                </div>
                <span class="step-label completed">Carrinho</span>
            </div>
            
            <div class="step-connector completed"></div>
            
            <div class="progress-step">
                <div class="step-circle active">2</div>
                <span class="step-label active">Pagamento</span>
            </div>
            
            <div class="step-connector"></div>
            
            <div class="progress-step">
                <div class="step-circle pending">3</div>
                <span class="step-label pending">Confirmação</span>
            </div>
        </nav>
        
        <div class="checkout-layout">
            
            <!-- Payment Section -->
            <section class="payment-section">
                <div class="section-header">
                    <i class="fas fa-wallet"></i>
                    <h2>Forma de Pagamento</h2>
                </div>
                
                <form method="POST" action="<?php echo SITE_URL; ?>/pages/pagamento.php" class="section-content">
                    <div class="payment-methods">
                        <div class="payment-method">
                            <input type="radio" name="metodo_pagamento" value="pix" id="pix" checked>
                            <label for="pix">
                                <div class="method-icon pix">
                                    <i class="fas fa-qrcode"></i>
                                </div>
                                <span class="method-name">PIX</span>
                                <span class="method-desc">Pagamento instantâneo</span>
                                <span class="method-badge">Aprovação imediata</span>
                            </label>
                        </div>
                        
                        <div class="payment-method">
                            <input type="radio" name="metodo_pagamento" value="cartao_credito" id="cartao">
                            <label for="cartao">
                                <div class="method-icon card">
                                    <i class="fas fa-credit-card"></i>
                                </div>
                                <span class="method-name">Cartão de Crédito</span>
                                <span class="method-desc">Parcele em até 3x sem juros</span>
                            </label>
                        </div>
                    </div>
                    
                    
                    
                    <button type="submit" class="btn-proceed">
                        <i class="fas fa-arrow-right"></i>
                        Continuar para Pagamento
                    </button>
                </form>
            </section>
            
            <!-- Order Summary -->
            <aside class="order-summary">
                <div class="summary-header">
                    <i class="fas fa-shopping-bag"></i>
                    <h2>Seu Pedido (<?php echo count($itens); ?>)</h2>
                </div>
                
                <div class="summary-items">
                    <?php foreach ($itens as $item): ?>
                    <?php
                        $preco_original = $item['preco_centavos'];
                        $preco = $item['em_promocao'] && $item['preco_promocional_centavos'] 
                            ? $item['preco_promocional_centavos'] 
                            : $item['preco_centavos'];
                    ?>
                    <div class="summary-item">
                        <div class="item-thumb">
                            <img src="<?php echo SITE_URL . ($item['imagem_capa'] ?: '/assets/images/no-image.png'); ?>" 
                                 alt="<?php echo sanitize($item['titulo']); ?>">
                        </div>
                        <div class="item-details">
                            <h4><?php echo sanitize($item['titulo']); ?></h4>
                            <p class="dev"><?php echo sanitize($item['nome_estudio'] ?? 'Indie Dev'); ?></p>
                            <p class="price">
                                <?php if ($preco_original > $preco): ?>
                                <span class="price-old"><?php echo formatPrice($preco_original); ?></span>
                                <?php endif; ?>
                                <?php echo $preco == 0 ? 'GRÁTIS' : formatPrice($preco); ?>
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="summary-totals">
                    <div class="total-row">
                        <span class="label">Subtotal</span>
                        <span class="value"><?php echo formatPrice($subtotal + $desconto); ?></span>
                    </div>
                    
                    <?php if ($desconto > 0): ?>
                    <div class="total-row discount">
                        <span class="label">Promoções</span>
                        <span class="value">-<?php echo formatPrice($desconto); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($desconto_cupom > 0): ?>
                    <div class="total-row discount">
                        <span class="label">Cupom (<?php echo $cupom['codigo']; ?>)</span>
                        <span class="value">-<?php echo formatPrice($desconto_cupom); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="total-row final">
                        <span class="label">Total</span>
                        <span class="value"><?php echo formatPrice($total); ?></span>
                    </div>
                </div>
                
                <div class="summary-security">
                    <div class="security-badges">
                        <i class="fas fa-shield-alt"></i>
                        <i class="fas fa-lock"></i>
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <p class="security-text">
                        <i class="fas fa-lock"></i>
                        Pagamento 100% seguro e criptografado
                    </p>
                </div>
            </aside>
            
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>