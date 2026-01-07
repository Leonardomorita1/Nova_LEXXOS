<?php
// pages/checkout.php
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
");
$stmt->execute([$user_id]);
$itens = $stmt->fetchAll();

if (count($itens) == 0) {
    header('Location: ' . SITE_URL . '/user/carrinho.php');
    exit;
}

// Calcular totais
$subtotal = 0;
foreach ($itens as $item) {
    $preco = $item['em_promocao'] && $item['preco_promocional_centavos'] 
        ? $item['preco_promocional_centavos'] 
        : $item['preco_centavos'];
    $subtotal += $preco;
}

$total = $subtotal;

$page_title = 'Finalizar Compra - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<style>
.checkout-page {
    padding: 30px 0;
}

.checkout-layout {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 30px;
}

.payment-methods {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.payment-method {
    background: var(--bg-primary);
    border: 2px solid var(--border);
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
}

.payment-method input[type="radio"] {
    display: none;
}

.payment-method:hover {
    border-color: var(--accent);
}

.payment-method input[type="radio"]:checked + label {
    border-color: var(--accent);
}

.payment-method label {
    cursor: pointer;
    width: 100%;
}

.payment-method i {
    font-size: 32px;
    color: var(--accent);
    margin-bottom: 10px;
}

.payment-method span {
    display: block;
    font-weight: 600;
}

@media (max-width: 992px) {
    .checkout-layout {
        grid-template-columns: 1fr;
    }
    
    .payment-methods {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="container">
    <div class="checkout-page">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-credit-card"></i> Finalizar Compra
            </h1>
        </div>
        
        <div class="checkout-layout">
            <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 15px; padding: 30px;">
                <h2 style="margin-bottom: 25px;">
                    <i class="fas fa-wallet"></i> Forma de Pagamento
                </h2>
                
                <form method="POST" action="<?php echo SITE_URL; ?>/pages/pagamento.php">
                    <div class="payment-methods">
                        <div class="payment-method">
                            <input type="radio" name="metodo_pagamento" value="pix" id="pix" checked>
                            <label for="pix">
                                <i class="fas fa-qrcode"></i>
                                <span>PIX</span>
                                <p style="font-size: 12px; color: var(--text-secondary); margin-top: 8px;">
                                    Pagamento instantâneo
                                </p>
                            </label>
                        </div>
                        
                        <div class="payment-method">
                            <input type="radio" name="metodo_pagamento" value="cartao_credito" id="cartao">
                            <label for="cartao">
                                <i class="fas fa-credit-card"></i>
                                <span>Cartão de Crédito</span>
                                <p style="font-size: 12px; color: var(--text-secondary); margin-top: 8px;">
                                    Em até 3x sem juros
                                </p>
                            </label>
                        </div>
                    </div>
                    
                    <div style="background: rgba(76,139,245,0.1); border: 1px solid var(--accent); border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                        <i class="fas fa-info-circle"></i>
                        <strong>Modo de Teste Ativo</strong>
                        <p style="font-size: 13px; margin-top: 5px;">
                            Este é um ambiente de testes. Você poderá simular o pagamento na próxima etapa.
                        </p>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block btn-lg">
                        <i class="fas fa-arrow-right"></i> Ir para Pagamento
                    </button>
                </form>
            </div>
            
            <!-- Resumo -->
            <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 15px; padding: 25px; height: fit-content; position: sticky; top: 90px;">
                <h2 style="font-size: 20px; margin-bottom: 20px;">Resumo da Compra</h2>
                
                <div style="max-height: 300px; overflow-y: auto; margin-bottom: 20px;">
                    <?php foreach ($itens as $item): ?>
                    <div style="display: flex; gap: 12px; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid var(--border);">
                        <img src="<?php echo SITE_URL . ($item['imagem_capa'] ?: '/assets/images/no-image.png'); ?>" 
                             style="width: 60px; height: 80px; object-fit: cover; border-radius: 6px;"
                             alt="<?php echo sanitize($item['titulo']); ?>">
                        <div style="flex: 1;">
                            <p style="font-size: 14px; font-weight: 600; margin-bottom: 4px;">
                                <?php echo sanitize($item['titulo']); ?>
                            </p>
                            <p style="font-size: 13px; color: var(--accent); font-weight: 600;">
                                <?php
                                $preco = $item['em_promocao'] && $item['preco_promocional_centavos'] 
                                    ? $item['preco_promocional_centavos'] 
                                    : $item['preco_centavos'];
                                echo $preco == 0 ? 'GRÁTIS' : formatPrice($preco);
                                ?>
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div style="border-top: 2px solid var(--border); padding-top: 20px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span>Subtotal</span>
                        <span><?php echo formatPrice($subtotal); ?></span>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; font-size: 20px; font-weight: 700; margin-top: 15px;">
                        <span>Total</span>
                        <span style="color: var(--accent);"><?php echo formatPrice($total); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>