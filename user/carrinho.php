<?php
// pages/carrinho.php (Atualizado com sistema de cupons)
require_once '../config/config.php';
require_once '../config/database.php';

requireLogin();

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
        // Buscar cupom
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
        $cupom = $stmt->fetch();
        
        if (!$cupom) {
            throw new Exception('Cupom inválido ou expirado');
        }
        
        // Verificar se cupom se aplica aos jogos no carrinho
        $stmt = $pdo->prepare("
            SELECT c.*, j.preco_centavos, j.desenvolvedor_id, j.titulo
            FROM carrinho c
            JOIN jogo j ON c.jogo_id = j.id
            WHERE c.usuario_id = ?
        ");
        $stmt->execute([$user_id]);
        $itens_carrinho = $stmt->fetchAll();
        
        $cupom_aplicavel = false;
        foreach ($itens_carrinho as $item) {
            // Cupom é válido se:
            // 1. É do desenvolvedor do jogo E
            // 2. Não tem jogo específico (vale para todos) OU é para este jogo
            if ($item['desenvolvedor_id'] == $cupom['dev_id'] && 
                ($cupom['jogo_id'] === null || $cupom['jogo_id'] == $item['jogo_id'])) {
                $cupom_aplicavel = true;
                break;
            }
        }
        
        if (!$cupom_aplicavel) {
            throw new Exception('Este cupom não se aplica aos jogos no seu carrinho');
        }
        
        // Salvar cupom na sessão
        $_SESSION['cupom_aplicado'] = $cupom;
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
    $message = 'Cupom removido';
}

// ============================================
// REMOVER ITEM DO CARRINHO
// ============================================
if (isset($_GET['remover']) && is_numeric($_GET['remover'])) {
    $stmt = $pdo->prepare("DELETE FROM carrinho WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$_GET['remover'], $user_id]);
    header('Location: carrinho.php');
    exit;
}

// ============================================
// BUSCAR ITENS DO CARRINHO
// ============================================
$stmt = $pdo->prepare("
    SELECT c.*, j.*, d.nome_estudio,
           COALESCE(j.preco_promocional_centavos, j.preco_centavos) as preco_atual
    FROM carrinho c
    JOIN jogo j ON c.jogo_id = j.id
    JOIN desenvolvedor d ON j.desenvolvedor_id = d.id
    WHERE c.usuario_id = ?
    ORDER BY c.adicionado_em DESC
");
$stmt->execute([$user_id]);
$itens = $stmt->fetchAll();

// ============================================
// CALCULAR TOTAIS
// ============================================
$subtotal = 0;
$desconto_promocao = 0;
$desconto_cupom = 0;

foreach ($itens as $item) {
    $subtotal += $item['preco_centavos'];
    
    // Desconto de promoção
    if ($item['em_promocao'] && $item['preco_promocional_centavos']) {
        $desconto_promocao += ($item['preco_centavos'] - $item['preco_promocional_centavos']);
    }
}

// Aplicar cupom se houver
$cupom_aplicado = $_SESSION['cupom_aplicado'] ?? null;
if ($cupom_aplicado) {
    foreach ($itens as $item) {
        // Verificar se cupom se aplica a este item
        if ($item['desenvolvedor_id'] == $cupom_aplicado['dev_id'] && 
            ($cupom_aplicado['jogo_id'] === null || $cupom_aplicado['jogo_id'] == $item['id'])) {
            
            $preco_item = $item['preco_atual'];
            
            if ($cupom_aplicado['tipo_desconto'] == 'percentual') {
                $desconto_item = ($preco_item * $cupom_aplicado['valor_desconto']) / 100;
            } else {
                $desconto_item = min($cupom_aplicado['valor_desconto'], $preco_item);
            }
            
            $desconto_cupom += $desconto_item;
        }
    }
}

$total = $subtotal - $desconto_promocao - $desconto_cupom;

$page_title = 'Carrinho - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<style>
.carrinho-container { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }
.carrinho-grid { display: grid; grid-template-columns: 1fr 400px; gap: 30px; }

.carrinho-items { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; padding: 24px; }
.cart-item { display: flex; gap: 20px; padding: 20px; background: var(--bg-primary); border-radius: 8px; margin-bottom: 16px; }
.cart-item-img { width: 120px; height: 160px; object-fit: cover; border-radius: 6px; }
.cart-item-info { flex: 1; }
.cart-item-title { font-size: 18px; font-weight: 700; color: var(--text-primary); margin-bottom: 4px; }
.cart-item-dev { font-size: 14px; color: var(--text-secondary); margin-bottom: 12px; }
.cart-item-price { display: flex; align-items: center; gap: 12px; }
.price-original { font-size: 14px; color: var(--text-secondary); text-decoration: line-through; }
.price-current { font-size: 20px; font-weight: 700; color: var(--success); }
.btn-remove { padding: 8px 16px; background: var(--danger); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }

.carrinho-summary { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; padding: 24px; height: fit-content; position: sticky; top: 90px; }
.summary-title { font-size: 20px; font-weight: 700; color: var(--text-primary); margin-bottom: 20px; }
.summary-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid var(--border); }
.summary-label { color: var(--text-secondary); }
.summary-value { font-weight: 600; color: var(--text-primary); }
.summary-value.discount { color: var(--success); }
.summary-total { display: flex; justify-content: space-between; padding: 20px 0; margin-top: 12px; font-size: 24px; font-weight: 800; color: var(--text-primary); }

.cupom-box { background: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; padding: 16px; margin: 20px 0; }
.cupom-input-group { display: flex; gap: 8px; }
.cupom-input { flex: 1; padding: 10px; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 6px; color: var(--text-primary); text-transform: uppercase; }
.btn-cupom { padding: 10px 20px; background: var(--accent); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }
.cupom-aplicado { display: flex; align-items: center; justify-content: space-between; padding: 12px; background: rgba(46, 204, 113, 0.1); border: 1px solid var(--success); border-radius: 6px; margin: 20px 0; }
.cupom-codigo { font-weight: 700; color: var(--success); }

.btn-checkout { width: 100%; padding: 16px; background: var(--accent); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 700; cursor: pointer; margin-top: 20px; }
.btn-checkout:hover { opacity: 0.9; }

.empty-cart { text-align: center; padding: 60px 20px; }

.alert { padding: 16px; border-radius: 8px; margin-bottom: 20px; }
.alert-success { background: rgba(46, 204, 113, 0.2); color: var(--success); border: 1px solid var(--success); }
.alert-error { background: rgba(220, 53, 69, 0.2); color: var(--danger); border: 1px solid var(--danger); }

@media (max-width: 968px) {
    .carrinho-grid { grid-template-columns: 1fr; }
    .carrinho-summary { position: static; }
}
</style>

<div class="carrinho-container">
    <h1 style="font-size: 32px; font-weight: 700; margin-bottom: 30px; color: var(--text-primary);">
        <i class="fas fa-shopping-cart"></i> Meu Carrinho
    </h1>
    
    <?php if ($message): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $message ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
    <?php endif; ?>
    
    <?php if (empty($itens)): ?>
        <div class="empty-cart">
            <i class="fas fa-shopping-cart" style="font-size: 64px; color: var(--text-secondary); opacity: 0.3; margin-bottom: 20px;"></i>
            <h2 style="color: var(--text-primary); margin-bottom: 12px;">Seu carrinho está vazio</h2>
            <p style="color: var(--text-secondary); margin-bottom: 24px;">Adicione jogos incríveis à sua coleção!</p>
            <a href="<?= SITE_URL ?>/pages/home.php" class="btn-checkout" style="max-width: 300px; margin: 0 auto; display: block;">
                Explorar Jogos
            </a>
        </div>
    <?php else: ?>
        <div class="carrinho-grid">
            <!-- Itens -->
            <div class="carrinho-items">
                <h2 style="margin-bottom: 20px; color: var(--text-primary);">
                    Itens no Carrinho (<?= count($itens) ?>)
                </h2>
                
                <?php foreach ($itens as $item): ?>
                    <div class="cart-item">
                        <img src="<?= SITE_URL . ($item['imagem_capa'] ?: '/assets/images/no-image.png') ?>" 
                             alt="<?= htmlspecialchars($item['titulo']) ?>" 
                             class="cart-item-img">
                        
                        <div class="cart-item-info">
                            <h3 class="cart-item-title"><?= htmlspecialchars($item['titulo']) ?></h3>
                            <p class="cart-item-dev">por <?= htmlspecialchars($item['nome_estudio']) ?></p>
                            
                            <div class="cart-item-price">
                                <?php if ($item['em_promocao']): ?>
                                    <span class="price-original"><?= formatPrice($item['preco_centavos']) ?></span>
                                    <span class="price-current"><?= formatPrice($item['preco_promocional_centavos']) ?></span>
                                    <span style="background: var(--success); color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 700;">
                                        -<?= round((($item['preco_centavos'] - $item['preco_promocional_centavos']) / $item['preco_centavos']) * 100) ?>%
                                    </span>
                                <?php else: ?>
                                    <span class="price-current"><?= formatPrice($item['preco_centavos']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <a href="?remover=<?= $item['id'] ?>" class="btn-remove">
                            <i class="fas fa-trash"></i> Remover
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Resumo -->
            <div class="carrinho-summary">
                <h2 class="summary-title">Resumo do Pedido</h2>
                
                <div class="summary-row">
                    <span class="summary-label">Subtotal (<?= count($itens) ?> itens):</span>
                    <span class="summary-value"><?= formatPrice($subtotal) ?></span>
                </div>
                
                <?php if ($desconto_promocao > 0): ?>
                    <div class="summary-row">
                        <span class="summary-label">Desconto de Promoção:</span>
                        <span class="summary-value discount">-<?= formatPrice($desconto_promocao) ?></span>
                    </div>
                <?php endif; ?>
                
                <!-- Sistema de Cupom -->
                <div class="cupom-box">
                    <label style="display: block; font-weight: 600; margin-bottom: 8px; color: var(--text-primary);">
                        <i class="fas fa-ticket-alt"></i> Cupom de Desconto
                    </label>
                    
                    <?php if ($cupom_aplicado): ?>
                        <div class="cupom-aplicado">
                            <div>
                                <div class="cupom-codigo"><?= $cupom_aplicado['codigo'] ?></div>
                                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">
                                    <?php if ($cupom_aplicado['tipo_desconto'] == 'percentual'): ?>
                                        <?= $cupom_aplicado['valor_desconto'] ?>% de desconto
                                    <?php else: ?>
                                        <?= formatPrice($cupom_aplicado['valor_desconto']) ?> de desconto
                                    <?php endif; ?>
                                </div>
                            </div>
                            <a href="?remover_cupom=1" style="color: var(--danger); font-size: 20px;">
                                <i class="fas fa-times-circle"></i>
                            </a>
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <div class="cupom-input-group">
                                <input type="text" name="codigo_cupom" class="cupom-input" 
                                       placeholder="Código do cupom" required>
                                <button type="submit" name="aplicar_cupom" class="btn-cupom">
                                    Aplicar
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
                
                <?php if ($desconto_cupom > 0): ?>
                    <div class="summary-row">
                        <span class="summary-label">Desconto do Cupom:</span>
                        <span class="summary-value discount">-<?= formatPrice($desconto_cupom) ?></span>
                    </div>
                <?php endif; ?>
                
                <div class="summary-total">
                    <span>Total:</span>
                    <span><?= formatPrice($total) ?></span>
                </div>
                
                <a href="<?= SITE_URL ?>/pages/checkout.php" class="btn-checkout">
                    <i class="fas fa-lock"></i> Finalizar Compra
                </a>
                
                <p style="text-align: center; font-size: 12px; color: var(--text-secondary); margin-top: 16px;">
                    <i class="fas fa-shield-alt"></i> Compra 100% segura
                </p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>