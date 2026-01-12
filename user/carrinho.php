<?php
require_once '../config/config.php';
require_once '../config/database.php';

requireLogin();

$database = new Database();
$pdo = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Buscar itens
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
$subtotal = $desconto = 0;
foreach ($itens as $item) {
    $preco = ($item['em_promocao'] && $item['preco_promocional_centavos']) 
        ? $item['preco_promocional_centavos'] : $item['preco_centavos'];
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
.cart-page { padding: 40px 0 80px; min-height: 70vh; }

.page-header { margin-bottom: 30px; }
.page-title { font-size: 2rem; font-weight: 800; margin-bottom: 8px; }
.page-title i { color: var(--accent); margin-right: 10px; }
.page-subtitle { color: var(--text-secondary); }

.cart-layout {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 30px;
    align-items: start;
}

/* ITEMS */
.cart-section {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
}

.cart-item {
    display: grid;
    grid-template-columns: 100px 1fr auto;
    gap: 20px;
    padding: 20px;
    border-bottom: 1px solid var(--border);
    transition: all 0.3s;
}

.cart-item:last-child { border: none; }
.cart-item:hover { background: rgba(255,255,255,0.02); }
.cart-item.removing { opacity: 0.5; }

.item-image {
    border-radius: 8px;
    overflow: hidden;
    aspect-ratio: 3/4;
}

.item-image img { width: 100%; height: 100%; object-fit: cover; }

.item-info h3 {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 6px;
}

.item-info h3 a {
    color: var(--text-primary);
    text-decoration: none;
}

.item-info h3 a:hover { color: var(--accent); }

.item-dev {
    font-size: 13px;
    color: var(--text-secondary);
}

.item-right {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    justify-content: space-between;
}

.price-old {
    font-size: 13px;
    color: var(--text-secondary);
    text-decoration: line-through;
}

.price-current {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--accent);
}

.price-current.free { color: #2ecc71; }

.btn-remove {
    background: rgba(231, 76, 60, 0.1);
    color: #e74c3c;
    border: none;
    padding: 8px 14px;
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-remove:hover {
    background: #e74c3c;
    color: white;
}

/* SUMMARY */
.cart-summary {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 25px;
    position: sticky;
    top: 100px;
}

.summary-title {
    font-size: 1.2rem;
    font-weight: 700;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border);
}

.summary-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    font-size: 15px;
}

.summary-row .label { color: var(--text-secondary); }
.summary-row.discount .value { color: #2ecc71; }

.summary-row.total {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid var(--border);
    font-size: 1.2rem;
    font-weight: 700;
}

.summary-row.total .value { color: var(--accent); }

.btn-checkout {
    width: 100%;
    padding: 16px;
    background: linear-gradient(135deg, var(--accent), #6ba3ff);
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    margin-top: 20px;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    text-decoration: none;
}

.btn-checkout:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(79, 134, 247, 0.4);
    color: white;
}

.btn-continue {
    width: 100%;
    padding: 12px;
    background: transparent;
    color: var(--text-secondary);
    border: 1px solid var(--border);
    border-radius: 10px;
    font-size: 14px;
    cursor: pointer;
    margin-top: 10px;
    text-decoration: none;
    display: block;
    text-align: center;
    transition: all 0.3s;
}

.btn-continue:hover {
    border-color: var(--accent);
    color: var(--accent);
}

.secure-badge {
    text-align: center;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid var(--border);
    font-size: 13px;
    color: var(--text-secondary);
}

.secure-badge i { color: #2ecc71; margin-right: 6px; }

/* EMPTY */
.empty-cart {
    text-align: center;
    padding: 80px 30px;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 12px;
}

.empty-cart i {
    font-size: 60px;
    color: var(--text-secondary);
    opacity: 0.3;
    margin-bottom: 20px;
}

.empty-cart h2 { margin-bottom: 10px; }
.empty-cart p { color: var(--text-secondary); margin-bottom: 25px; }

/* RESPONSIVE */
@media (max-width: 900px) {
    .cart-layout { grid-template-columns: 1fr; }
    .cart-summary { position: static; order: -1; }
}

@media (max-width: 600px) {
    .cart-item {
        grid-template-columns: 80px 1fr;
        gap: 15px;
    }
    
    .item-right {
        grid-column: 1 / -1;
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
        padding-top: 15px;
        border-top: 1px solid var(--border);
    }
}

/* TOAST */
.toast {
    position: fixed;
    bottom: 30px;
    left: 50%;
    transform: translateX(-50%);
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    padding: 12px 25px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
    z-index: 9999;
    animation: fadeIn 0.3s;
}

.toast.success { border-color: #2ecc71; }
.toast.success i { color: #2ecc71; }

@keyframes fadeIn {
    from { opacity: 0; transform: translateX(-50%) translateY(10px); }
    to { opacity: 1; transform: translateX(-50%) translateY(0); }
}
</style>

<div class="container">
    <div class="cart-page">
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-shopping-cart"></i>Carrinho</h1>
            <p class="page-subtitle"><?php echo count($itens); ?> item<?php echo count($itens) != 1 ? 's' : ''; ?></p>
        </div>

        <?php if (count($itens) > 0): ?>
            <div class="cart-layout">
                <div class="cart-section" id="cartItems">
                    <?php foreach ($itens as $item): 
                        $preco = ($item['em_promocao'] && $item['preco_promocional_centavos']) 
                            ? $item['preco_promocional_centavos'] : $item['preco_centavos'];
                        $tem_desconto = $item['em_promocao'] && $item['preco_promocional_centavos'];
                    ?>
                        <div class="cart-item" id="item-<?php echo $item['id']; ?>" 
                             data-price="<?php echo $preco; ?>"
                             data-discount="<?php echo $tem_desconto ? ($item['preco_centavos'] - $preco) : 0; ?>">
                            <div class="item-image">
                                <a href="<?php echo SITE_URL; ?>/pages/jogo.php?slug=<?php echo $item['slug']; ?>">
                                    <img src="<?php echo SITE_URL . ($item['imagem_capa'] ?: '/assets/images/no-image.png'); ?>" alt="">
                                </a>
                            </div>
                            <div class="item-info">
                                <h3><a href="<?php echo SITE_URL; ?>/pages/jogo.php?slug=<?php echo $item['slug']; ?>">
                                    <?php echo sanitize($item['titulo']); ?>
                                </a></h3>
                                <p class="item-dev"><?php echo sanitize($item['nome_estudio']); ?></p>
                            </div>
                            <div class="item-right">
                                <div class="item-price">
                                    <?php if ($tem_desconto): ?>
                                        <div class="price-old"><?php echo formatPrice($item['preco_centavos']); ?></div>
                                    <?php endif; ?>
                                    <div class="price-current <?php echo $preco == 0 ? 'free' : ''; ?>">
                                        <?php echo $preco == 0 ? 'GRÁTIS' : formatPrice($preco); ?>
                                    </div>
                                </div>
                                <button class="btn-remove" onclick="removeItem(<?php echo $item['id']; ?>, this)">
                                    <i class="fas fa-trash"></i> Remover
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="cart-summary">
                    <h2 class="summary-title">Resumo</h2>
                    
                    <div class="summary-row">
                        <span class="label">Itens (<span id="itemCount"><?php echo count($itens); ?></span>)</span>
                        <span class="value" id="subtotalVal"><?php echo formatPrice($subtotal + $desconto); ?></span>
                    </div>
                    
                    <?php if ($desconto > 0): ?>
                        <div class="summary-row discount" id="discountRow">
                            <span class="label">Descontos</span>
                            <span class="value" id="discountVal">-<?php echo formatPrice($desconto); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="summary-row total">
                        <span class="label">Total</span>
                        <span class="value" id="totalVal"><?php echo formatPrice($total); ?></span>
                    </div>
                    
                    <a href="<?php echo SITE_URL; ?>/pages/checkout.php" class="btn-checkout">
                        <i class="fas fa-lock"></i> Finalizar Compra
                    </a>
                    
                    <a href="<?php echo SITE_URL; ?>/pages/busca.php" class="btn-continue">
                        <i class="fas fa-arrow-left"></i> Continuar Comprando
                    </a>
                    
                    <div class="secure-badge">
                        <i class="fas fa-shield-alt"></i> Pagamento seguro
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-cart">
                <i class="fas fa-shopping-cart"></i>
                <h2>Carrinho vazio</h2>
                <p>Adicione jogos para continuar</p>
                <a href="<?php echo SITE_URL; ?>/pages/busca.php" class="btn btn-primary">
                    <i class="fas fa-search"></i> Explorar Jogos
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
async function removeItem(gameId, btn) {
    const item = document.getElementById(`item-${gameId}`);
    item.classList.add('removing');
    btn.disabled = true;
    
    try {
        const res = await fetch('<?php echo SITE_URL; ?>/api/toggle-cart.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({jogo_id: gameId})
        });
        const data = await res.json();
        
        if (data.success && data.action === 'removed') {
            item.style.animation = 'fadeOut 0.3s forwards';
            setTimeout(() => {
                item.remove();
                updateTotals();
                updateBadge();
                checkEmpty();
            }, 300);
            showToast('Removido do carrinho');
        }
    } catch(e) {
        item.classList.remove('removing');
        btn.disabled = false;
    }
}

function updateTotals() {
    const items = document.querySelectorAll('.cart-item');
    let sub = 0, disc = 0;
    
    items.forEach(item => {
        sub += parseInt(item.dataset.price) || 0;
        disc += parseInt(item.dataset.discount) || 0;
    });
    
    document.getElementById('itemCount').textContent = items.length;
    document.getElementById('subtotalVal').textContent = formatPrice(sub + disc);
    document.getElementById('totalVal').textContent = formatPrice(sub);
    
    const discRow = document.getElementById('discountRow');
    if (discRow) {
        if (disc > 0) {
            document.getElementById('discountVal').textContent = '-' + formatPrice(disc);
            discRow.style.display = 'flex';
        } else {
            discRow.style.display = 'none';
        }
    }
}

function checkEmpty() {
    if (document.querySelectorAll('.cart-item').length === 0) {
        location.reload();
    }
}

function updateBadge() {
    fetch('<?php echo SITE_URL; ?>/api/get-cart-count.php')
        .then(r => r.json())
        .then(d => {
            document.querySelectorAll('.cart-count').forEach(el => {
                el.textContent = d.count;
                el.style.display = d.count > 0 ? 'flex' : 'none';
            });
        });
}

function formatPrice(cents) {
    if (cents === 0) return 'Grátis';
    return 'R$ ' + (cents / 100).toFixed(2).replace('.', ',');
}

function showToast(msg) {
    const t = document.createElement('div');
    t.className = 'toast success';
    t.innerHTML = `<i class="fas fa-check-circle"></i><span>${msg}</span>`;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3000);
}
</script>

<style>
@keyframes fadeOut {
    to { opacity: 0; transform: translateX(-20px); height: 0; padding: 0; overflow: hidden; }
}
</style>

<?php require_once '../includes/footer.php'; ?>