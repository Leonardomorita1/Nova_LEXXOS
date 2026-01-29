<?php
// pages/pagamento.php - Pagamento Redesenhado Completo
require_once '../config/config.php';
require_once '../config/database.php';

requireLogin();

$database = new Database();
$pdo = $database->getConnection();
$user_id = $_SESSION['user_id'];

$metodo = $_POST['metodo_pagamento'] ?? $_GET['metodo'] ?? 'pix';

// Buscar itens do carrinho
$stmt = $pdo->prepare("
    SELECT c.*, j.*, d.id as dev_id, d.nome_estudio
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

// Calcular total
$subtotal = 0;
foreach ($itens as $item) {
    $preco = $item['em_promocao'] && $item['preco_promocional_centavos'] 
        ? $item['preco_promocional_centavos'] 
        : $item['preco_centavos'];
    $subtotal += $preco;
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

// Gerar código PIX fake
$pix_code = '00020126580014BR.GOV.BCB.PIX0136' . bin2hex(random_bytes(16)) . '5204000053039865802BR';

$page_title = 'Pagamento - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<style>
/* ===========================================
   PAGAMENTO - ESTILOS PRINCIPAIS
   =========================================== */
:root {
    --pay-green: #10b981;
    --pay-blue: #3b82f6;
    --pay-purple: #8b5cf6;
    --pay-teal: #14b8a6;
    --pay-orange: #f59e0b;
}

.payment-page {
    padding: 40px 0 100px;
    min-height: 100vh;
}

/* ===========================================
   PROGRESS STEPS
   =========================================== */
.payment-progress {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0;
    margin-bottom: 50px;
}

.prog-step {
    display: flex;
    align-items: center;
    gap: 12px;
}

.prog-circle {
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

.prog-circle.done {
    background: var(--pay-green);
    color: #fff;
}

.prog-circle.current {
    background: var(--accent);
    color: #000;
    box-shadow: 0 0 0 4px rgba(185, 255, 102, 0.2);
}

.prog-circle.waiting {
    background: var(--bg-secondary);
    color: var(--text-secondary);
    border: 2px solid var(--border);
}

.prog-label {
    font-weight: 600;
    font-size: 0.9rem;
}

.prog-label.done { color: var(--pay-green); }
.prog-label.current { color: var(--accent); }
.prog-label.waiting { color: var(--text-secondary); }

.prog-line {
    width: 80px;
    height: 3px;
    background: var(--border);
    margin: 0 15px;
}

.prog-line.done {
    background: var(--pay-green);
}

/* ===========================================
   PAYMENT LAYOUT
   =========================================== */
.payment-layout {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 35px;
    align-items: start;
}

/* ===========================================
   PAYMENT CARD
   =========================================== */
.payment-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 24px;
    overflow: hidden;
}

.payment-header {
    padding: 30px;
    text-align: center;
    border-bottom: 1px solid var(--border);
}

.payment-header h1 {
    font-size: 1.8rem;
    font-weight: 800;
    margin: 0 0 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
}

.payment-header h1 i {
    color: var(--pay-teal);
}

.payment-header p {
    color: var(--text-secondary);
    margin: 0;
}

.payment-body {
    padding: 35px;
}

/* ===========================================
   PIX SECTION
   =========================================== */
.pix-section {
    text-align: center;
}

.qr-container {
    background: #fff;
    width: 260px;
    height: 260px;
    margin: 0 auto 25px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
}

.qr-placeholder {
    font-size: 100px;
    color: #e0e0e0;
}

.qr-badge {
    position: absolute;
    bottom: -12px;
    left: 50%;
    transform: translateX(-50%);
    background: var(--pay-teal);
    color: #fff;
    padding: 8px 20px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
}

.pix-code-box {
    background: var(--bg-primary);
    border: 2px dashed var(--border);
    border-radius: 14px;
    padding: 18px;
    margin-bottom: 15px;
    position: relative;
}

.pix-code {
    font-family: 'Courier New', monospace;
    font-size: 0.7rem;
    color: var(--text-secondary);
    word-break: break-all;
    line-height: 1.5;
    display: block;
}

.btn-copy {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: rgba(20, 184, 166, 0.1);
    border: 2px solid var(--pay-teal);
    border-radius: 12px;
    color: var(--pay-teal);
    font-weight: 700;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s;
    margin-bottom: 30px;
}

.btn-copy:hover {
    background: var(--pay-teal);
    color: #fff;
}

.btn-copy.copied {
    background: var(--pay-green);
    border-color: var(--pay-green);
    color: #fff;
}

.pix-instructions {
    background: rgba(20, 184, 166, 0.05);
    border: 1px solid rgba(20, 184, 166, 0.2);
    border-radius: 14px;
    padding: 20px;
    text-align: left;
    margin-bottom: 25px;
}

.pix-instructions h4 {
    font-size: 0.95rem;
    margin: 0 0 15px;
    color: var(--pay-teal);
    display: flex;
    align-items: center;
    gap: 8px;
}

.pix-instructions ol {
    margin: 0;
    padding-left: 20px;
}

.pix-instructions li {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin-bottom: 8px;
    line-height: 1.5;
}

.pix-instructions li:last-child {
    margin-bottom: 0;
}

/* ===========================================
   CARD SECTION
   =========================================== */
.card-section {
    max-width: 100%;
}

.card-preview {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border-radius: 16px;
    padding: 25px;
    margin-bottom: 30px;
    aspect-ratio: 1.7/1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    position: relative;
    overflow: hidden;
    max-width: 380px;
    margin-left: auto;
    margin-right: auto;
}

.card-preview::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle, rgba(139, 92, 246, 0.2) 0%, transparent 70%);
}

.card-chip {
    width: 45px;
    height: 35px;
    background: linear-gradient(135deg, #ffd700, #ffaa00);
    border-radius: 6px;
    position: relative;
}

.card-chip::after {
    content: '';
    position: absolute;
    inset: 4px;
    background: linear-gradient(135deg, #ffcc00, #ff9900);
    border-radius: 3px;
}

.card-number-preview {
    font-size: 1.3rem;
    letter-spacing: 3px;
    font-family: 'Courier New', monospace;
    color: #fff;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

.card-bottom {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
}

.card-name-preview {
    font-size: 0.85rem;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.8);
    letter-spacing: 1px;
}

.card-expiry-preview {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.8);
}

.card-brand {
    position: absolute;
    bottom: 20px;
    right: 25px;
    font-size: 2.2rem;
    color: rgba(255, 255, 255, 0.3);
}

/* Form Fields */
.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 8px;
}

.form-input {
    width: 100%;
    padding: 14px 16px;
    background: var(--bg-primary);
    border: 2px solid var(--border);
    border-radius: 12px;
    color: var(--text-primary);
    font-size: 1rem;
    transition: all 0.3s;
}

.form-input:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(185, 255, 102, 0.1);
}

.form-input::placeholder {
    color: var(--text-secondary);
    opacity: 0.6;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.form-select {
    width: 100%;
    padding: 14px 16px;
    background: var(--bg-primary);
    border: 2px solid var(--border);
    border-radius: 12px;
    color: var(--text-primary);
    font-size: 1rem;
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23888' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 15px center;
}

.form-select:focus {
    outline: none;
    border-color: var(--accent);
}

/* ===========================================
   TOTAL BOX
   =========================================== */
.total-box {
    background: var(--bg-primary);
    border: 2px solid var(--accent);
    border-radius: 16px;
    padding: 25px;
    text-align: center;
    margin-bottom: 25px;
}

.total-label {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin-bottom: 5px;
}

.total-value {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--accent);
}

.total-items {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin-top: 5px;
}



/* ===========================================
   SIMULATE BUTTON
   =========================================== */
.btn-simulate {
    width: 100%;
    padding: 20px 30px;
    background: linear-gradient(135deg, var(--pay-green), #34d399);
    color: #fff;
    border: none;
    border-radius: 14px;
    font-size: 1.15rem;
    font-weight: 800;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    transition: all 0.3s;
    box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
}

.btn-simulate:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 40px rgba(16, 185, 129, 0.4);
}

.btn-simulate:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

.btn-simulate.processing {
    background: linear-gradient(135deg, var(--pay-blue), #60a5fa);
    box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
}

/* ===========================================
   ORDER SUMMARY SIDEBAR
   =========================================== */
.order-sidebar {
    position: sticky;
    top: 100px;
}

.sidebar-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 20px;
    overflow: hidden;
    margin-bottom: 20px;
}

.sidebar-header {
    padding: 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 10px;
}

.sidebar-header i {
    color: var(--accent);
}

.sidebar-header h3 {
    font-size: 1rem;
    font-weight: 700;
    margin: 0;
}

.sidebar-items {
    padding: 15px 20px;
    max-height: 250px;
    overflow-y: auto;
}

.sidebar-item {
    display: flex;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid var(--border);
}

.sidebar-item:last-child {
    border-bottom: none;
}

.sidebar-item-img {
    width: 50px;
    height: 65px;
    border-radius: 8px;
    overflow: hidden;
    flex-shrink: 0;
}

.sidebar-item-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.sidebar-item-info {
    flex: 1;
    min-width: 0;
}

.sidebar-item-info h4 {
    font-size: 0.85rem;
    font-weight: 600;
    margin: 0 0 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.sidebar-item-info .price {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--accent);
}

.sidebar-totals {
    padding: 20px;
    background: rgba(0, 0, 0, 0.2);
}

.sidebar-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    font-size: 0.9rem;
}

.sidebar-row .label {
    color: var(--text-secondary);
}

.sidebar-row.discount .value {
    color: var(--pay-green);
}

.sidebar-row.total {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 2px solid var(--border);
    font-size: 1.1rem;
    font-weight: 800;
}

.sidebar-row.total .value {
    color: var(--accent);
}

/* Security Card */
.security-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 20px;
    text-align: center;
}

.security-icons {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-bottom: 12px;
}

.security-icons i {
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
    color: var(--pay-green);
}

/* ===========================================
   SUCCESS OVERLAY
   =========================================== */
.success-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.95);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    backdrop-filter: blur(10px);
}

.success-overlay.active {
    display: flex;
}

.success-content {
    text-align: center;
    padding: 40px;
    max-width: 500px;
    animation: successPop 0.5s ease;
}

@keyframes successPop {
    0% {
        opacity: 0;
        transform: scale(0.8);
    }
    100% {
        opacity: 1;
        transform: scale(1);
    }
}

.success-icon {
    width: 120px;
    height: 120px;
    background: var(--pay-green);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 30px;
    animation: pulse 1.5s ease infinite;
}

@keyframes pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); }
    50% { box-shadow: 0 0 0 20px rgba(16, 185, 129, 0); }
}

.success-icon i {
    font-size: 60px;
    color: #fff;
}

.success-content h2 {
    font-size: 2rem;
    color: #fff;
    margin-bottom: 10px;
}

.success-content p {
    color: rgba(255, 255, 255, 0.7);
    font-size: 1.1rem;
    margin-bottom: 30px;
}

.success-games {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.success-game-thumb {
    width: 60px;
    height: 80px;
    border-radius: 8px;
    overflow: hidden;
    border: 2px solid rgba(255, 255, 255, 0.2);
}

.success-game-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.success-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 16px 32px;
    background: var(--accent);
    color: #000;
    border-radius: 14px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.3s;
}

.success-btn:hover {
    background: #fff;
    transform: scale(1.05);
}

/* ===========================================
   RESPONSIVE
   =========================================== */
@media (max-width: 1024px) {
    .payment-layout {
        grid-template-columns: 1fr;
    }
    
    .order-sidebar {
        position: static;
        order: -1;
    }
}

@media (max-width: 768px) {
    .payment-progress {
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .prog-line {
        display: none;
    }
    
    .prog-label {
        display: none;
    }
    
    .payment-body {
        padding: 25px;
    }
    
    .qr-container {
        width: 220px;
        height: 220px;
    }
    
    .qr-placeholder {
        font-size: 80px;
    }
    
    .total-value {
        font-size: 2rem;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .card-preview {
        padding: 20px;
    }
    
    .card-number-preview {
        font-size: 1.1rem;
    }
    
    
}
</style>

<div class="container">
    <div class="payment-page">
        
        <!-- Progress -->
        <nav class="payment-progress">
            <div class="prog-step">
                <div class="prog-circle done"><i class="fas fa-check"></i></div>
                <span class="prog-label done">Carrinho</span>
            </div>
            <div class="prog-line done"></div>
            <div class="prog-step">
                <div class="prog-circle done"><i class="fas fa-check"></i></div>
                <span class="prog-label done">Método</span>
            </div>
            <div class="prog-line done"></div>
            <div class="prog-step">
                <div class="prog-circle current">3</div>
                <span class="prog-label current">Pagamento</span>
            </div>
        </nav>
        
        <div class="payment-layout">
            
            <!-- Main Payment Card -->
            <div class="payment-card">
                <div class="payment-header">
                    <?php if ($metodo == 'pix'): ?>
                    <h1><i class="fas fa-qrcode"></i> Pagamento via PIX</h1>
                    <p>Escaneie o QR Code ou copie o código para pagar</p>
                    <?php else: ?>
                    <h1><i class="fas fa-credit-card"></i> Pagamento com Cartão</h1>
                    <p>Preencha os dados do seu cartão de crédito</p>
                    <?php endif; ?>
                </div>
                
                <div class="payment-body">
                    
                    <?php if ($metodo == 'pix'): ?>
                    <!-- =================== PIX PAYMENT =================== -->
                    <div class="pix-section">
                        <div class="qr-container">
                            <i class="fas fa-qrcode qr-placeholder"></i>
                            <span class="qr-badge">
                                <i class="fas fa-clock"></i> Válido por 30 min
                            </span>
                        </div>
                        
                        <div class="pix-code-box">
                            <code class="pix-code" id="pixCode"><?php echo $pix_code; ?></code>
                        </div>
                        
                        <button class="btn-copy" onclick="copyPixCode()" id="copyBtn">
                            <i class="fas fa-copy"></i>
                            <span id="copyText">Copiar Código PIX</span>
                        </button>
                        
                        <div class="pix-instructions">
                            <h4><i class="fas fa-info-circle"></i> Como pagar com PIX</h4>
                            <ol>
                                <li>Abra o app do seu banco ou carteira digital</li>
                                <li>Escolha a opção PIX e escaneie o QR Code</li>
                                <li>Ou copie o código e cole na opção "PIX Copia e Cola"</li>
                                <li>Confirme o pagamento e pronto!</li>
                            </ol>
                        </div>
                        
                        <div class="total-box">
                            <div class="total-label">Valor a pagar</div>
                            <div class="total-value"><?php echo formatPrice($total); ?></div>
                            <div class="total-items"><?php echo count($itens); ?> jogo<?php echo count($itens) > 1 ? 's' : ''; ?></div>
                        </div>
                        
                        
                        
                        <button class="btn-simulate" onclick="simularPagamento()" id="btnSimulate">
                            <i class="fas fa-check-circle"></i>
                            <span>Simular Pagamento Aprovado</span>
                        </button>
                    </div>
                    
                    <?php else: ?>
                    <!-- =================== CARD PAYMENT =================== -->
                    <div class="card-section">
                        <div class="card-preview" id="cardPreview">
                            <div class="card-chip"></div>
                            <div class="card-number-preview" id="cardNumberPreview">•••• •••• •••• ••••</div>
                            <div class="card-bottom">
                                <div class="card-name-preview" id="cardNamePreview">NOME DO TITULAR</div>
                                <div class="card-expiry-preview" id="cardExpiryPreview">MM/AA</div>
                            </div>
                            <i class="fab fa-cc-visa card-brand" id="cardBrand"></i>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Número do Cartão</label>
                            <input type="text" 
                                   class="form-input" 
                                   id="cardNumber" 
                                   placeholder="0000 0000 0000 0000" 
                                   maxlength="19"
                                   oninput="formatCardNumber(this); updateCardPreview()">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Nome no Cartão</label>
                            <input type="text" 
                                   class="form-input" 
                                   id="cardName" 
                                   placeholder="Nome como está no cartão"
                                   oninput="updateCardPreview()"
                                   style="text-transform: uppercase;">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Validade</label>
                                <input type="text" 
                                       class="form-input" 
                                       id="cardExpiry" 
                                       placeholder="MM/AA" 
                                       maxlength="5"
                                       oninput="formatExpiry(this); updateCardPreview()">
                            </div>
                            <div class="form-group">
                                <label class="form-label">CVV</label>
                                <input type="text" 
                                       class="form-input" 
                                       id="cardCvv" 
                                       placeholder="123" 
                                       maxlength="4"
                                       oninput="this.value = this.value.replace(/\D/g, '')">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Parcelas</label>
                            <select class="form-select" id="installments">
                                <option value="1">1x de <?php echo formatPrice($total); ?> sem juros</option>
                                <?php if ($total >= 2000): ?>
                                <option value="2">2x de <?php echo formatPrice($total / 2); ?> sem juros</option>
                                <?php endif; ?>
                                <?php if ($total >= 3000): ?>
                                <option value="3">3x de <?php echo formatPrice($total / 3); ?> sem juros</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="total-box">
                            <div class="total-label">Total a pagar</div>
                            <div class="total-value"><?php echo formatPrice($total); ?></div>
                            <div class="total-items"><?php echo count($itens); ?> jogo<?php echo count($itens) > 1 ? 's' : ''; ?></div>
                        </div>
                        
                        
                        
                        <button class="btn-simulate" onclick="simularPagamento()" id="btnSimulate">
                            <i class="fas fa-lock"></i>
                            <span>Pagar <?php echo formatPrice($total); ?></span>
                        </button>
                    </div>
                    <?php endif; ?>
                    
                </div>
            </div>
            
            <!-- Order Sidebar -->
            <aside class="order-sidebar">
                <div class="sidebar-card">
                    <div class="sidebar-header">
                        <i class="fas fa-shopping-bag"></i>
                        <h3>Seu Pedido (<?php echo count($itens); ?>)</h3>
                    </div>
                    
                    <div class="sidebar-items">
                        <?php foreach ($itens as $item): ?>
                        <?php
                            $preco = $item['em_promocao'] && $item['preco_promocional_centavos'] 
                                ? $item['preco_promocional_centavos'] 
                                : $item['preco_centavos'];
                        ?>
                        <div class="sidebar-item">
                            <div class="sidebar-item-img">
                                <img src="<?php echo SITE_URL . ($item['imagem_capa'] ?: '/assets/images/no-image.png'); ?>" 
                                     alt="<?php echo sanitize($item['titulo']); ?>">
                            </div>
                            <div class="sidebar-item-info">
                                <h4><?php echo sanitize($item['titulo']); ?></h4>
                                <div class="price">
                                    <?php echo $preco == 0 ? 'GRÁTIS' : formatPrice($preco); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="sidebar-totals">
                        <div class="sidebar-row">
                            <span class="label">Subtotal</span>
                            <span class="value"><?php echo formatPrice($subtotal); ?></span>
                        </div>
                        
                        <?php if ($desconto_cupom > 0): ?>
                        <div class="sidebar-row discount">
                            <span class="label">Cupom</span>
                            <span class="value">-<?php echo formatPrice($desconto_cupom); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="sidebar-row total">
                            <span class="label">Total</span>
                            <span class="value"><?php echo formatPrice($total); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="security-card">
                    <div class="security-icons">
                        <i class="fas fa-shield-alt"></i>
                        <i class="fas fa-lock"></i>
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <p class="security-text">
                        <i class="fas fa-lock"></i>
                        Pagamento 100% seguro
                    </p>
                </div>
            </aside>
            
        </div>
    </div>
</div>

<!-- Success Overlay -->
<div class="success-overlay" id="successOverlay">
    <div class="success-content">
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>
        <h2>Pagamento Aprovado!</h2>
        <p>Seus jogos foram adicionados à sua biblioteca</p>
        
        <div class="success-games">
            <?php foreach (array_slice($itens, 0, 5) as $item): ?>
            <div class="success-game-thumb">
                <img src="<?php echo SITE_URL . ($item['imagem_capa'] ?: '/assets/images/no-image.png'); ?>" 
                     alt="<?php echo sanitize($item['titulo']); ?>">
            </div>
            <?php endforeach; ?>
        </div>
        
        <a href="<?php echo SITE_URL; ?>/user/biblioteca.php" class="success-btn">
            <i class="fas fa-gamepad"></i>
            Ir para Biblioteca
        </a>
    </div>
</div>

<script>
const SITE_URL = '<?php echo SITE_URL; ?>';

// Copy PIX Code
function copyPixCode() {
    const code = document.getElementById('pixCode').textContent;
    const btn = document.getElementById('copyBtn');
    const text = document.getElementById('copyText');
    
    navigator.clipboard.writeText(code).then(() => {
        btn.classList.add('copied');
        text.textContent = 'Código Copiado!';
        btn.querySelector('i').className = 'fas fa-check';
        
        setTimeout(() => {
            btn.classList.remove('copied');
            text.textContent = 'Copiar Código PIX';
            btn.querySelector('i').className = 'fas fa-copy';
        }, 3000);
    });
}

// Format Card Number
function formatCardNumber(input) {
    let value = input.value.replace(/\D/g, '');
    value = value.replace(/(\d{4})(?=\d)/g, '$1 ');
    input.value = value.substring(0, 19);
}

// Format Expiry
function formatExpiry(input) {
    let value = input.value.replace(/\D/g, '');
    if (value.length >= 2) {
        value = value.substring(0, 2) + '/' + value.substring(2);
    }
    input.value = value.substring(0, 5);
}

// Update Card Preview
function updateCardPreview() {
    const number = document.getElementById('cardNumber')?.value || '';
    const name = document.getElementById('cardName')?.value || '';
    const expiry = document.getElementById('cardExpiry')?.value || '';
    
    // Number
    const numberPreview = document.getElementById('cardNumberPreview');
    if (numberPreview) {
        numberPreview.textContent = number || '•••• •••• •••• ••••';
    }
    
    // Name
    const namePreview = document.getElementById('cardNamePreview');
    if (namePreview) {
        namePreview.textContent = name.toUpperCase() || 'NOME DO TITULAR';
    }
    
    // Expiry
    const expiryPreview = document.getElementById('cardExpiryPreview');
    if (expiryPreview) {
        expiryPreview.textContent = expiry || 'MM/AA';
    }
    
    // Brand detection
    const brand = document.getElementById('cardBrand');
    if (brand && number) {
        const firstDigit = number.charAt(0);
        const firstTwo = number.substring(0, 2);
        
        if (firstDigit === '4') {
            brand.className = 'fab fa-cc-visa card-brand';
        } else if (['51', '52', '53', '54', '55'].includes(firstTwo)) {
            brand.className = 'fab fa-cc-mastercard card-brand';
        } else if (['34', '37'].includes(firstTwo)) {
            brand.className = 'fab fa-cc-amex card-brand';
        } else {
            brand.className = 'fab fa-cc-visa card-brand';
        }
    }
}

// Simulate Payment
function simularPagamento() {
    const btn = document.getElementById('btnSimulate');
    
    if (!confirm('Deseja simular a aprovação do pagamento?')) return;
    
    btn.disabled = true;
    btn.classList.add('processing');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Processando...</span>';
    
    fetch(SITE_URL + '/api/process-payment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            metodo_pagamento: '<?php echo $metodo; ?>',
            simular: true
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('successOverlay').classList.add('active');
            
            // Auto redirect after 5 seconds
            setTimeout(() => {
                window.location.href = SITE_URL + '/user/biblioteca.php';
            }, 5000);
        } else {
            alert(data.message || 'Erro ao processar pagamento');
            btn.disabled = false;
            btn.classList.remove('processing');
            btn.innerHTML = '<i class="fas fa-check-circle"></i> <span>Simular Pagamento Aprovado</span>';
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao processar pagamento');
        btn.disabled = false;
        btn.classList.remove('processing');
        btn.innerHTML = '<i class="fas fa-check-circle"></i> <span>Simular Pagamento Aprovado</span>';
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>