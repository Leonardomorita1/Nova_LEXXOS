<?php
// pages/pagamento.php
require_once '../config/config.php';
require_once '../config/database.php';

requireLogin();

$database = new Database();
$pdo = $database->getConnection();
$user_id = $_SESSION['user_id'];

$metodo = $_POST['metodo_pagamento'] ?? 'pix';

// Buscar itens do carrinho
$stmt = $pdo->prepare("
    SELECT c.*, j.*, d.id as dev_id
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

$page_title = 'Pagamento - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<style>
.payment-page {
    padding: 30px 0;
    max-width: 600px;
    margin: 0 auto;
}

.payment-box {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 15px;
    padding: 40px;
    text-align: center;
}

.qrcode-placeholder {
    width: 300px;
    height: 300px;
    background: white;
    margin: 20px auto;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 100px;
}

.pix-code {
    background: var(--bg-primary);
    padding: 15px;
    border-radius: 8px;
    font-family: monospace;
    word-break: break-all;
    margin: 20px 0;
}

.simulate-btn {
    background: var(--success);
    color: white;
    border: none;
    padding: 15px 40px;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    margin-top: 20px;
}

.simulate-btn:hover {
    background: #218838;
}
</style>

<div class="container">
    <div class="payment-page">
        <div class="payment-box">
            <?php if ($metodo == 'pix'): ?>
                <h1 style="margin-bottom: 10px;">
                    <i class="fas fa-qrcode" style="color: var(--accent);"></i>
                    Pagamento via PIX
                </h1>
                <p style="color: var(--text-secondary); margin-bottom: 30px;">
                    Escaneie o QR Code ou copie o código abaixo
                </p>
                
                <div class="qrcode-placeholder">
                    <i class="fas fa-qrcode" style="color: var(--text-secondary);"></i>
                </div>
                
                <div class="pix-code">
                    00020126580014BR.GOV.BCB.PIX0136<?php echo md5(uniqid()); ?>5204000053039865802BR
                </div>
                
                <button class="btn btn-secondary btn-sm" onclick="navigator.clipboard.writeText(this.previousElementSibling.textContent)">
                    <i class="fas fa-copy"></i> Copiar Código PIX
                </button>
                
                <div style="margin: 30px 0; padding: 20px; background: rgba(76,139,245,0.1); border-radius: 8px;">
                    <p style="font-size: 24px; font-weight: 700; color: var(--accent);">
                        <?php echo formatPrice($subtotal); ?>
                    </p>
                    <p style="font-size: 13px; color: var(--text-secondary); margin-top: 5px;">
                        Valor total a pagar
                    </p>
                </div>
                
            <?php else: ?>
                <h1 style="margin-bottom: 10px;">
                    <i class="fas fa-credit-card" style="color: var(--accent);"></i>
                    Pagamento com Cartão
                </h1>
                <p style="color: var(--text-secondary); margin-bottom: 30px;">
                    Preencha os dados do cartão
                </p>
                
                <div style="text-align: left; max-width: 400px; margin: 0 auto;">
                    <div class="form-group">
                        <label class="form-label">Número do Cartão</label>
                        <input type="text" class="form-control" placeholder="0000 0000 0000 0000" maxlength="19">
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">Validade</label>
                            <input type="text" class="form-control" placeholder="MM/AA" maxlength="5">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">CVV</label>
                            <input type="text" class="form-control" placeholder="123" maxlength="3">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Nome no Cartão</label>
                        <input type="text" class="form-control" placeholder="NOME SOBRENOME">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Parcelas</label>
                        <select class="form-control">
                            <option>1x de <?php echo formatPrice($subtotal); ?> sem juros</option>
                            <option>2x de <?php echo formatPrice($subtotal / 2); ?> sem juros</option>
                            <option>3x de <?php echo formatPrice($subtotal / 3); ?> sem juros</option>
                        </select>
                    </div>
                </div>
            <?php endif; ?>
            
            <div style="background: rgba(255,193,7,0.1); border: 1px solid var(--warning); border-radius: 8px; padding: 15px; margin: 30px 0; text-align: left;">
                <p style="font-size: 13px; margin: 0;">
                    <i class="fas fa-flask"></i> <strong>Ambiente de Teste</strong><br>
                    Este é um ambiente de testes. Clique no botão abaixo para simular o pagamento aprovado.
                    Nenhuma cobrança real será realizada.
                </p>
            </div>
            
            <button class="simulate-btn" onclick="simularPagamento()">
                <i class="fas fa-check-circle"></i> Simular Pagamento Aprovado
            </button>
            
            <p style="margin-top: 20px; font-size: 13px; color: var(--text-secondary);">
                Após a confirmação, os jogos serão adicionados à sua biblioteca automaticamente
            </p>
        </div>
    </div>
</div>

<script>
function simularPagamento() {
    if (!confirm('Deseja simular a aprovação do pagamento?')) return;
    
    const btn = event.target;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
    
    fetch('<?php echo SITE_URL; ?>/api/process-payment.php', {
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
            showNotification('Pagamento aprovado! Redirecionando...', 'success');
            setTimeout(() => {
                window.location.href = '<?php echo SITE_URL; ?>/user/biblioteca.php';
            }, 2000);
        } else {
            showNotification(data.message || 'Erro ao processar pagamento', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-circle"></i> Simular Pagamento Aprovado';
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showNotification('Erro ao processar pagamento', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check-circle"></i> Simular Pagamento Aprovado';
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>