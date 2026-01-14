<?php
/**
 * Componente Global de Game Card - Estilo PlayStation (Clean)
 * Imagem 1:1, Título, Preço. Ações apenas no Hover (Desktop).
 */

function renderGameCard($jogo, $pdo = null, $user_id = null) {
    // Dados básicos
    $id = $jogo['id'];
    $titulo = htmlspecialchars($jogo['titulo']);
    $slug = $jogo['slug'];
    
    // Preços
    $preco = $jogo['preco_centavos'] ?? 0;
    $preco_promo = $jogo['preco_promocional_centavos'] ?? null;
    $em_promocao = $jogo['em_promocao'] ?? false;
    
    // Lógica de Preço
    $preco_final = $em_promocao && $preco_promo ? $preco_promo : $preco;
    $preco_display = $preco_final > 0 ? 'R$ ' . number_format($preco_final / 100, 2, ',', '.') : 'Grátis';
    
    // Desconto (Badge simples)
    $desconto = 0;
    if ($em_promocao && $preco > 0 && $preco_promo) {
        $desconto = round((($preco - $preco_promo) / $preco) * 100);
    }
    
    // Imagem
    $imagem = !empty($jogo['imagem_capa']) 
        ? (strpos($jogo['imagem_capa'], 'http') === 0 ? $jogo['imagem_capa'] : SITE_URL . $jogo['imagem_capa'])
        : SITE_URL . '/assets/images/placeholder-game.jpg';
    
    // Link
    $url = SITE_URL . '/pages/jogo.php?slug=' . $slug;
    
    // Wishlist Check (apenas se usuário logado)
    $in_wishlist = false;
    if ($user_id && $pdo) {
        $stmt = $pdo->prepare("SELECT 1 FROM lista_desejos WHERE usuario_id = ? AND jogo_id = ?");
        $stmt->execute([$user_id, $id]);
        $in_wishlist = $stmt->fetch() ? true : false;
    }
    ?>
    
    <article class="ps-card">
        <div class="ps-card-image-wrapper">
            <a href="<?= $url ?>" class="ps-card-link" aria-label="<?= $titulo ?>">
                <img src="<?= $imagem ?>" alt="<?= $titulo ?>" loading="lazy" class="ps-card-img">
                
                <?php if ($desconto > 0): ?>
                    <span class="ps-badge">-<?= $desconto ?>%</span>
                <?php endif; ?>
            </a>

            <!-- Ações (Só aparecem no Hover em Desktop) -->
            <div class="ps-card-actions">
                <button class="ps-action-btn wishlist-btn <?= $in_wishlist ? 'active' : '' ?>" 
                        onclick="toggleWishlist(<?= $id ?>, this)"
                        title="Lista de Desejos">
                    <i class="<?= $in_wishlist ? 'fas' : 'far' ?> fa-heart"></i>
                </button>
                <button class="ps-action-btn cart-btn" 
                        onclick="addToCart(<?= $id ?>)"
                        title="Adicionar ao Carrinho">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
        </div>

        <div class="ps-card-info">
            <a href="<?= $url ?>" class="ps-card-title"><?= $titulo ?></a>
            <div class="ps-card-price-row">
                <span class="ps-card-price <?= $preco_final == 0 ? 'free' : '' ?>">
                    <?= $preco_display ?>
                </span>
                <?php if ($em_promocao && $preco > 0 && $preco_promo): ?>
                    <span class="ps-card-old-price">
                        R$ <?= number_format($preco / 100, 2, ',', '.') ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </article>

    <?php
}

// Mantivemos a função renderGameCardMobile apenas como alias para não quebrar códigos antigos,
// mas ela chama a mesma função renderGameCard pois o CSS agora resolve tudo.
function renderGameCardMobile($jogo, $pdo = null, $user_id = null) {
    renderGameCard($jogo, $pdo, $user_id);
}
?>