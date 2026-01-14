<?php
// user/carrinho.php - Carrinho Completo com Cupons
require_once '../config/config.php';
require_once '../config/database.php';
requireLogin();

$database = new Database();
$pdo = $database->getConnection();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Aplicar cupom
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['aplicar_cupom'])) {
    $codigo = strtoupper(trim($_POST['codigo_cupom']));
    
    $stmt = $pdo->prepare("
        SELECT * FROM cupom 
        WHERE codigo = ? AND ativo = 1 
        AND (validade IS NULL OR validade >= CURDATE())
        AND (usos_maximos IS NULL OR usos_atuais < usos_maximos)
    ");
    $stmt->execute([$codigo]);
    $cupom = $stmt->fetch();
    
    if ($cupom) {
        $_SESSION['cupom_aplicado'] = $cupom;
        $message = 'Cupom aplicado com sucesso!';
    } else {
        $error = 'Cupom inválido, expirado ou já utilizado';
    }
}

// Remover cupom
if (isset($_GET['remover_cupom'])) {
    unset($_SESSION['cupom_aplicado']);
    header('Location: ' . SITE_URL . '/user/carrinho.php');
    exit;
}

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

// Calcular valores
$subtotal = 0;
$desconto_promocional = 0;

foreach ($itens as $item) {
    $preco = ($item['em_promocao'] && $item['preco_promocional_centavos']) 
        ? $item['preco_promocional_centavos'] : $item['preco_centavos'];
    $subtotal += $preco;
    
    if ($item['em_promocao'] && $item['preco_promocional_centavos']) {
        $desconto_promocional += ($item['preco_centavos'] - $item['preco_promocional_centavos']);
    }
}

// Aplicar cupom
$desconto_cupom = 0;
$cupom_aplicado = $_SESSION['cupom_aplicado'] ?? null;

if ($cupom_aplicado && $subtotal >= $cupom_aplicado['valor_minimo_centavos']) {
    if ($cupom_aplicado['tipo_desconto'] == 'percentual') {
        $desconto_cupom = ($subtotal * $cupom_aplicado['valor_desconto']) / 100;
    } else {
        $desconto_cupom = $cupom_aplicado['valor_desconto'];
    }
    
    // Não pode ser maior que o subtotal
    if ($desconto_cupom > $subtotal) {
        $desconto_cupom = $subtotal;
    }
}

$total = $subtotal - $desconto_cupom;

$page_title = 'Carrinho - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<style>
.cart-page {
    padding: 50px 0 100px;
    min-height: 80vh;
}

.page-header {
    margin-bottom: 40px;
}

.page-title {
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.page-title i {
    color: var(--accent);
}

.page-subtitle {
    color: var(--text-secondary);
    font-size: 1.1rem;
}

.cart-layout {
    display: grid;
    grid-template-columns: 1fr 420px;
    gap: 35px;
    align-items: start;
}

/* ITEMS */
.cart-section {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
}

.cart-item {
    display: grid;
    grid-template-columns: 120px 1fr auto;
    gap: 25px;
    padding: 25px;
    border-bottom: 1px solid var(--border);
    transition: all 0.3s;
}

.cart-item:last-child {
    border: none;
}

.cart-item:hover {
    background: rgba(255,255,255,0.02);
}

.item-image {
    border-radius: 10px;
    overflow: hidden;
    aspect-ratio: 3/4;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s;
}

.item-image:hover img {
    transform: scale(1.05);
}

.item-info h3 {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 8px;
    line-height: 1.3;
}

.item-info h3 a {
    color: var(--text-primary);
    text-decoration: none;
    transition: color 0.3s;
}

.item-info h3 a:hover {
    color: var(--accent);
}

.item-dev {
    font-size: 14px;
    color: var(--text-secondary);
    margin-bottom: 12px;
}

.item-tags {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.item-tag {
    padding: 4px 10px;
    background: rgba(76, 139, 245, 0.1);
    border: 1px solid rgba(76, 139, 245, 0.2);
    border-radius: 6px;
    font-size: 11px;
    color: var(--accent);
}

.item-right {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    justify-content: space-between;
    min-width: 140px;
}

.price-old {
    font-size: 14px;
    color: var(--text-secondary);
    text-decoration: line-through;
    margin-bottom: 4px;
}

.price-current {
    font-size: 1.6rem;
    font-weight: 800;
    color: var(--accent);
}

.discount-badge {
    display: inline-block;
    padding: 4px 10px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 700;
    margin-top: 6px;
}

.btn-remove {
    background: rgba(231, 76, 60, 0.1);
    color: #e74c3c;
    border: none;
    padding: 10px 18px;
    border-radius: 8px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s;
    font-weight: 600;
}

.btn-remove:hover {
    background: #e74c3c;
    color: white;
    transform: translateY(-2px);
}

/* SUMMARY */
.cart-summary {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 30px;
    position: sticky;
    top: 100px;
}

.summary-title {
    font-size: 1.4rem;
    font-weight: 800;
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 2px solid var(--border);
}

.coupon-section {
    margin-bottom: 25px;
    padding-bottom: 25px;
    border-bottom: 1px solid var(--border);
}

.coupon-section h3 {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 12px;
    color: var(--text-secondary);
}

.coupon-form {
    display: flex;
    gap: 10px;
}

.coupon-input {
    flex: 1;
    padding: 12px 15px;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-primary);
    font-size: 14px;
    text-transform: uppercase;
}

.coupon-input:focus {
    outline: none;
    border-color: var(--accent);
}

.btn-apply {
    padding: 12px 20px;
    background: var(--accent);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-apply:hover {
    background: var(--accent-hover);
    transform: translateY(-2px);
}

.coupon-applied {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 15px;
    background: rgba(46, 204, 113, 0.1);
    border: 1px solid #2ecc71;
    border-radius: 8px;
}

.coupon-applied-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.coupon-applied i {
    color: #2ecc71;
    font-size: 18px;
}

.coupon-code {
    font-weight: 700;
    color: #2ecc71;
}

.btn-remove-coupon {
    background: none;
    border: none;
    color: #e74c3c;
    cursor: pointer;
    padding: 5px;
    transition: all 0.3s;
}

.btn-remove-coupon:hover {
    transform: scale(1.1);
}

.summary-row {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    font-size: 15px;
}

.summary-row .label {
    color: var(--text-secondary);
}

.summary-row .value {
    font-weight: 600;
}

.summary-row.discount .value {
    color: #2ecc71;
}

.summary-row.total {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 2px solid var(--border);
    font-size: 1.4rem;
    font-weight: 800;
}

.summary-row.total .value {
    color: var(--accent);
}

.btn-checkout {
    width: 100%;
    padding: 18px;
    background: linear-gradient(135deg, var(--accent), #6ba3ff);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 17px;
    font-weight: 800;
    cursor: pointer;
    margin-top: 25px;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    text-decoration: none;
    box-shadow: 0 4px 15px rgba(76, 139, 245, 0.3);
}

.btn-checkout:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(76, 139, 245, 0.4);
    color: white;
}

.btn-continue {
    width: 100%;
    padding: 14px;
    background: transparent;
    color: var(--text-secondary);
    border: 1px solid var(--border);
    border-radius: 12px;
    font-size: 15px;
    cursor: pointer;
    margin-top: 12px;
    text-decoration: none;
    display: block;
    text-align: center;
    transition: all 0.3s;
}

.btn-continue:hover {
    border-color: var(--accent);
    color: var(--accent);
    background: rgba(76, 139, 245, 0.05);
}

.secure-badge {
    text-align: center;
    margin-top: 25px;
    padding-top: 25px;
    border-top: 1px solid var(--border);
    font-size: 13px;
    color: var(--text-secondary);
}

.secure-badge i {
    color: #2ecc71;
    margin-right: 8px;
}

/* EMPTY */
.empty-cart {
    text-align: center;
    padding: 100px 30px;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 16px;
}

.empty-cart i {
    font-size: 80px;
    color: var(--text-secondary);
    opacity: 0.2;
    margin-bottom: 25px;
}

.empty-cart h2 {
    font-size: 2rem;
    margin-bottom: 15px;
}

.empty-cart p {
    color: var(--text-secondary);
    margin-bottom: 30px;
    font-size: 1.1rem;
}

/* ALERTS */
.alert {
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.alert-success {
    background: rgba(46, 204, 113, 0.1);
    border: 1px solid #2ecc71;
    color: #2ecc71;
}

.alert-error {
    background: rgba(231, 76, 60, 0.1);
    border: 1px solid #e74c3c;
    color: #e74c3c;
}

/* RESPONSIVE */
@media (max-width: 1024px) {
    .cart-layout {
        grid-template-columns: 1fr;
    }
    
    .cart-summary {
        position: static;
        order: -1;
    }
}

@media (max-width: 768px) {
    .cart-item {
        grid-template-columns: 100px 1fr;
        gap: 20px;
    }
    
    .item-right {
        grid-column: 1 / -1;
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
        padding-top: 20px;
        border-top: 1px solid var(--border);
    }
    
    .page-title {
        font-size: 2rem;
    }
}
</style>

<div class="container">
    <div class="cart-page">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-shopping-cart"></i>
                Meu Carrinho
            </h1>
            <p class="page-subtitle">
                <?php echo count($itens); ?> <?php echo count($itens) == 1 ? 'item' : 'itens'; ?> no carrinho
            </p>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo $message; ?></span>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $error; ?></span>
        </div>
        <?php endif; ?>

        <?php if (count($itens) > 0): ?>
            <div class="cart-layout">
                <div class="cart-section">
                    <?php foreach ($itens as $item): 
                        $preco = ($item['em_promocao'] && $item['preco_promocional_centavos']) 
                            ? $item['preco_promocional_centavos'] : $item['preco_centavos'];
                        $tem_desconto = $item['em_promocao'] && $item['preco_promocional_centavos'];
                        
                        $percentual_desc = 0;
                        if ($tem_desconto) {
                            $percentual_desc = round((($item['preco_centavos'] - $preco) / $item['preco_centavos']) * 100);
                        }
                    ?>
                        <div class="cart-item">
                            <div class="item-image">
                                <a href="<?php echo SITE_URL; ?>/pages/jogo.php?slug=<?php echo $item['slug']; ?>">
                                    <img src="<?php echo SITE_URL . ($item['imagem_capa'] ?: '/assets/images/no-image.png'); ?>" 
                                         alt="<?php echo sanitize($item['titulo']); ?>">
                                </a>
                            </div>
                            
                            <div class="item-info">
                                <h3>
                                    <a href="<?php echo SITE_URL; ?>/pages/jogo.php?slug=<?php echo $item['slug']; ?>">
                                        <?php echo sanitize($item['titulo']); ?>
                                    </a>
                                </h3>
                                <p class="item-dev">
                                    <i class="fas fa-user"></i>
                                    <?php echo sanitize($item['nome_estudio']); ?>
                                </p>
                                <?php if ($tem_desconto): ?>
                                <div class="item-tags">
                                    <span class="item-tag">
                                        <i class="fas fa-tag"></i>
                                        <?php echo $percentual_desc; ?>% OFF
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="item-right">
                                <div class="item-price">
                                    <?php if ($tem_desconto): ?>
                                        <div class="price-old">
                                            <?php echo formatPrice($item['preco_centavos']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="price-current">
                                        <?php echo formatPrice($preco); ?>
                                    </div>
                                    <?php if ($tem_desconto): ?>
                                        <div class="discount-badge">
                                            Economize <?php echo formatPrice($item['preco_centavos'] - $preco); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <button class="btn-remove" onclick="removeItem(<?php echo $item['jogo_id']; ?>)">
                                    <i class="fas fa-trash-alt"></i> Remover
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="cart-summary">
                    <h2 class="summary-title">Resumo do Pedido</h2>
                    
                    <!-- Cupom -->
                    <div class="coupon-section">
                        <h3><i class="fas fa-ticket-alt"></i> Cupom de Desconto</h3>
                        
                        <?php if ($cupom_aplicado): ?>
                            <div class="coupon-applied">
                                <div class="coupon-applied-info">
                                    <i class="fas fa-check-circle"></i>
                                    <div>
                                        <div class="coupon-code"><?php echo $cupom_aplicado['codigo']; ?></div>
                                        <small style="color: var(--text-secondary);">
                                            <?php 
                                            if ($cupom_aplicado['tipo_desconto'] == 'percentual') {
                                                echo $cupom_aplicado['valor_desconto'] . '% de desconto';
                                            } else {
                                                echo formatPrice($cupom_aplicado['valor_desconto']) . ' de desconto';
                                            }
                                            ?>
                                        </small>
                                    </div>
                                </div>
                                <a href="?remover_cupom=1" class="btn-remove-coupon" title="Remover cupom">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        <?php else: ?>
                            <form method="POST" class="coupon-form">
                                <input type="text" 
                                       name="codigo_cupom" 
                                       class="coupon-input" 
                                       placeholder="Digite o código"
                                       maxlength="50">
                                <button type="submit" name="aplicar_cupom" class="btn-apply">
                                    Aplicar
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Valores -->
                    <div class="summary-row">
                        <span class="label">
                            Subtotal (<?php echo count($itens); ?> <?php echo count($itens) == 1 ? 'item' : 'itens'; ?>)
                        </span>
                        <span class="value">
                            <?php echo formatPrice($subtotal + $desconto_promocional); ?>
                        </span>
                    </div>
                    
                    <?php if ($desconto_promocional > 0): ?>
                    <div class="summary-row discount">
                        <span class="label">
                            <i class="fas fa-tags"></i> Desconto Promocional
                        </span>
                        <span class="value">
                            -<?php echo formatPrice($desconto_promocional); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($desconto_cupom > 0): ?>
                    <div class="summary-row discount">
                        <span class="label">
                            <i class="fas fa-ticket-alt"></i> Cupom (<?php echo $cupom_aplicado['codigo']; ?>)
                        </span>
                        <span class="value">
                            -<?php echo formatPrice($desconto_cupom); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="summary-row total">
                        <span class="label">Total</span>
                        <span class="value"><?php echo formatPrice($total); ?></span>
                    </div>
                    
                    <a href="<?php echo SITE_URL; ?>/pages/checkout.php" class="btn-checkout">
                        <i class="fas fa-lock"></i> Finalizar Compra
                    </a>
                    
                    <a href="<?php echo SITE_URL; ?>/pages/busca.php" class="btn-continue">
                        <i class="fas fa-arrow-left"></i> Continuar Comprando
                    </a>
                    
                    <div class="secure-badge">
                        <i class="fas fa-shield-alt"></i>
                        Compra 100% segura e protegida
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-cart">
                <i class="fas fa-shopping-cart"></i>
                <h2>Seu carrinho está vazio</h2>
                <p>Adicione jogos incríveis para começar sua jornada</p>
                <a href="<?php echo SITE_URL; ?>/pages/busca.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-gamepad"></i> Explorar Jogos
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
async function removeItem(jogoId) {
    if (!confirm('Remover este item do carrinho?')) return;
    
    try {
        const res = await fetch('<?php echo SITE_URL; ?>/api/toggle-cart.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({jogo_id: jogoId})
        });
        
        const data = await res.json();
        
        if (data.success) {
            location.reload();
        } else {
            alert('Erro ao remover item');
        }
    } catch(e) {
        alert('Erro de conexão');
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>