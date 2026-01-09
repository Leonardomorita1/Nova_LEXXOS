<?php
function renderGameCard($jogo, $pdo, $user_id = null) {
    $preco_original = $jogo['preco_centavos'];
    $preco_promocional = $jogo['preco_promocional_centavos'];
    $esta_em_promocao = ($jogo['em_promocao'] && $preco_promocional < $preco_original);
    
    $preco_final = $esta_em_promocao ? $preco_promocional : $preco_original;
    $desconto = $esta_em_promocao ? round((1 - $preco_promocional / $preco_original) * 100) : 0;
    
    // Verifica se usuário possui o jogo
    $possui_jogo = false;
    $in_cart = false;
    $in_wishlist = false;
    
    if ($user_id) {
        $stmt = $pdo->prepare("SELECT id FROM biblioteca WHERE usuario_id = ? AND jogo_id = ?");
        $stmt->execute([$user_id, $jogo['id']]);
        $possui_jogo = $stmt->fetch() ? true : false;
        
        $stmt = $pdo->prepare("SELECT id FROM carrinho WHERE usuario_id = ? AND jogo_id = ?");
        $stmt->execute([$user_id, $jogo['id']]);
        $in_cart = $stmt->fetch() ? true : false;
        
        $stmt = $pdo->prepare("SELECT id FROM lista_desejos WHERE usuario_id = ? AND jogo_id = ?");
        $stmt->execute([$user_id, $jogo['id']]);
        $in_wishlist = $stmt->fetch() ? true : false;
    }

    $imagem = (!empty($jogo['imagem_capa']) && file_exists(BASE_PATH . $jogo['imagem_capa'])) 
        ? SITE_URL . $jogo['imagem_capa'] 
        : SITE_URL . '/assets/images/no-image.png';

    $classificacao = $jogo['classificacao_etaria'] ?? 'L';
?>

<div class="lex-card">
    <a href="<?= SITE_URL ?>/pages/jogo.php?slug=<?= $jogo['slug'] ?>" class="lex-card-link-wrapper">
        <div class="lex-card-image">
            <img src="<?= $imagem ?>" alt="<?= sanitize($jogo['titulo']) ?>" loading="lazy">
            
            <!-- Botões de ação no hover -->
            <?php if ($user_id && !$possui_jogo): ?>
            <div class="lex-card-actions">
                <button onclick="event.preventDefault(); toggleCart(<?= $jogo['id'] ?>, this);" 
                        class="lex-action-btn <?= $in_cart ? 'active' : '' ?>" 
                        title="<?= $in_cart ? 'Remover do Carrinho' : 'Adicionar ao Carrinho' ?>">
                    <i class="fas fa-shopping-cart"></i>
                </button>
                <button onclick="event.preventDefault(); toggleWishlist(<?= $jogo['id'] ?>, this);" 
                        class="lex-action-btn <?= $in_wishlist ? 'active' : '' ?>" 
                        title="<?= $in_wishlist ? 'Remover da Lista de Desejos' : 'Adicionar à Lista de Desejos' ?>">
                    <i class="fas fa-heart"></i>
                </button>
            </div>
            <?php endif; ?>
        </div>
    </a>

    <div class="lex-card-content">
        <div class="lex-card-top">
            <a href="<?= SITE_URL ?>/pages/jogo.php?slug=<?= $jogo['slug'] ?>" style="text-decoration:none;">
                <h3 class="lex-card-title"><?= sanitize($jogo['titulo']) ?></h3>
            </a>
            <div class="lex-card-stars">
                <?php for($i=1; $i<=5; $i++): ?>
                    <i class="fas fa-star <?= $i <= round($jogo['nota_media'] ?? 0) ? 'active' : '' ?>"></i>
                <?php endfor; ?>
                <?php if ($jogo['total_avaliacoes'] > 0): ?>
                <span style="margin-left: 5px; font-size: 11px;">(<?= $jogo['total_avaliacoes'] ?>)</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="lex-card-mid">
            <?php if ($possui_jogo): ?>
                <a href="<?= SITE_URL ?>/user/download-jogo.php?jogo_id=<?= $jogo['id'] ?>" class="lex-btn-download">
                    <i class="fas fa-download"></i> Baixar
                </a>
            <?php elseif ($esta_em_promocao): ?>
                <div class="lex-price-badge">-<?= $desconto ?>%</div>
                <div class="lex-price-wrapper">
                    <span class="lex-old-price"><?= formatPrice($preco_original) ?></span>
                    <span class="lex-current-price"><?= formatPrice($preco_final) ?></span>
                </div>
            <?php else: ?>
                <span class="lex-current-price">
                    <?= ($preco_final == 0) ? 'Grátis' : formatPrice($preco_final) ?>
                </span>
            <?php endif; ?>
        </div>

        <div class="lex-card-bottom">
            <span class="lex-age age-<?= strtolower($classificacao) ?>">
                <?= $classificacao ?>
            </span>
            <?php if ($possui_jogo): ?>
                <span class="lex-owned-status">Na sua conta</span>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php } ?>

<style>
/* Botões de ação no hover do card */
.lex-card-link-wrapper {
    position: relative;
    text-decoration: none !important;
}

.lex-card-actions {
    position: absolute;
    top: 10px;
    right: 10px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    opacity: 0;
    transform: translateX(10px);
    transition: all 0.3s ease;
    z-index: 10;
}

.lex-card:hover .lex-card-actions {
    opacity: 1;
    transform: translateX(0);
}

.lex-action-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: none;
    background: rgba(19, 19, 20, 0.95);
    color: var(--text-primary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}

.lex-action-btn:hover {
    background: var(--accent);
    color: white;
    transform: scale(1.1);
}

.lex-action-btn.active {
    background: var(--accent);
    color: white;
}

.lex-action-btn i {
    font-size: 16px;
}

/* Mobile: botões sempre visíveis */
@media (max-width: 768px) {
    .lex-card-actions {
        opacity: 1;
        transform: translateX(0);
        top: 5px;
        right: 5px;
        gap: 5px;
    }
    
    .lex-action-btn {
        width: 36px;
        height: 36px;
    }
    
    .lex-action-btn i {
        font-size: 14px;
    }
}
</style>

<script>
function toggleCart(jogoId, button) {
    fetch('<?= SITE_URL ?>/api/toggle-cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ jogo_id: jogoId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.action === 'added') {
                button.classList.add('active');
                button.title = 'Remover do Carrinho';
            } else {
                button.classList.remove('active');
                button.title = 'Adicionar ao Carrinho';
            }
            showNotification(data.message, 'success');
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showNotification('Erro ao atualizar carrinho', 'error');
    });
}

function toggleWishlist(jogoId, button) {
    fetch('<?= SITE_URL ?>/api/toggle-wishlist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ jogo_id: jogoId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.action === 'added') {
                button.classList.add('active');
                button.title = 'Remover da Lista de Desejos';
            } else {
                button.classList.remove('active');
                button.title = 'Adicionar à Lista de Desejos';
            }
            showNotification(data.message, 'success');
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showNotification('Erro ao atualizar lista de desejos', 'error');
    });
}
</script>