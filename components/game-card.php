<?php
/**
 * Componente Global de Game Card - Estilo PlayStation (Clean)
 * Suporta contextos: store (padrão), library, wishlist
 */

function renderGameCard($jogo, $pdo = null, $user_id = null, $context = 'store', $extra = []) {
    // Dados básicos
    $id = $jogo['jogo_id'] ?? $jogo['id']; 
    $titulo = htmlspecialchars($jogo['titulo']);
    $slug = $jogo['slug'];
    
    // Verificações de estado
    $is_owned = $extra['is_owned'] ?? false; // Passado via array para performance
    $in_cart = $extra['in_cart'] ?? false;
    
    // Se não foi passado via extra, mas temos PDO e User, verifica (fallback, menos performático)
    if (!isset($extra['is_owned']) && $context === 'store' && $user_id && $pdo) {
        $stmt = $pdo->prepare("SELECT 1 FROM biblioteca WHERE usuario_id = ? AND jogo_id = ?");
        $stmt->execute([$user_id, $id]);
        $is_owned = $stmt->fetchColumn();
    }

    // Se o jogo é possuído, forçamos o comportamento visual de "library" parcialmente
    // mas mantemos o contexto original se não for library para links corretos
    
    // Preços
    $preco = $jogo['preco_centavos'] ?? 0;
    $preco_promo = $jogo['preco_promocional_centavos'] ?? null;
    $em_promocao = $jogo['em_promocao'] ?? false;
    
    // Lógica de Preço
    $preco_final = $em_promocao && $preco_promo ? $preco_promo : $preco;
    $preco_display = $preco_final > 0 ? 'R$ ' . number_format($preco_final / 100, 2, ',', '.') : 'Grátis';
    
    // Desconto
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
    
    // Wishlist Check (apenas se não tiver o jogo)
    $in_wishlist = false;
    if (!$is_owned && $context === 'store' && $user_id && $pdo) {
        // Tenta pegar do extra primeiro para performance
        if (isset($extra['in_wishlist'])) {
            $in_wishlist = $extra['in_wishlist'];
        } else {
            $stmt = $pdo->prepare("SELECT 1 FROM lista_desejos WHERE usuario_id = ? AND jogo_id = ?");
            $stmt->execute([$user_id, $id]);
            $in_wishlist = $stmt->fetch() ? true : false;
        }
    }
    
    $data_adicionado = $extra['data_adicionado'] ?? null;
    ?>
    
    <article class="ps-card" data-jogo-id="<?= $id ?>">
        <div class="ps-card-image-wrapper">
            <a href="<?= $url ?>" class="ps-card-link" aria-label="<?= $titulo ?>">
                <img src="<?= $imagem ?>" alt="<?= $titulo ?>" loading="lazy" class="ps-card-img">
            </a>
            
            <?php // BADGES ?>
            <?php if ($context === 'library' || $is_owned): ?>
                <span class="ps-badge ps-badge-owned">
                    <i class="fas fa-check"></i> Na Biblioteca
                </span>
            <?php elseif ($desconto > 0): ?>
                <span class="ps-badge">-<?= $desconto ?>%</span>
            <?php endif; ?>

            <?php // AÇÕES ?>
            <div class="ps-card-actions">
                
                <?php if ($is_owned || $context === 'library'): ?>
                    <!-- JOGO JÁ POSSUÍDO: Apenas ver/jogar -->
                    <a href="<?= $url ?>" class="ps-action-btn" title="Ver Detalhes">
                        <i class="fas fa-play"></i>
                    </a>

                <?php elseif ($context === 'store'): ?>
                    <!-- LOJA: Wishlist + Carrinho -->
                    <button class="ps-action-btn wishlist-btn <?= $in_wishlist ? 'active' : '' ?>" 
                            onclick="toggleWishlist(<?= $id ?>, this)"
                            title="Lista de Desejos">
                        <i class="<?= $in_wishlist ? 'fas' : 'far' ?> fa-heart"></i>
                    </button>
                    
                    <?php if ($in_cart): ?>
                        <a href="<?= SITE_URL ?>/pages/carrinho.php" class="ps-action-btn cart-btn active" title="Ver no Carrinho">
                            <i class="fas fa-check"></i>
                        </a>
                    <?php else: ?>
                        <button class="ps-action-btn cart-btn" 
                                onclick="addToCart(<?= $id ?>)"
                                title="Adicionar ao Carrinho">
                            <i class="fas fa-plus"></i>
                        </button>
                    <?php endif; ?>
                    
                <?php elseif ($context === 'wishlist'): ?>
                    <!-- WISHLIST -->
                    <button class="ps-action-btn cart-btn <?= $in_cart ? 'active' : '' ?>" 
                            onclick="addToCart(<?= $id ?>)"
                            title="<?= $in_cart ? 'No Carrinho' : 'Adicionar ao Carrinho' ?>">
                        <i class="fas <?= $in_cart ? 'fa-check' : 'fa-cart-plus' ?>"></i>
                    </button>
                    <button class="ps-action-btn remove-btn" 
                            onclick="Wishlist.remove(<?= $id ?>, this)"
                            title="Remover da Lista">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="ps-card-info">
            <a href="<?= $url ?>" class="ps-card-title"><?= $titulo ?></a>
            
            <?php if ($context === 'library' && $data_adicionado): ?>
                <div class="ps-card-meta">
                    <i class="fas fa-calendar-plus"></i>
                    <?= date('d/m/Y', strtotime($data_adicionado)) ?>
                </div>
            <?php else: ?>
                <!-- Preço (se tiver o jogo, mostra apenas "Adquirido" ou esconde preço) -->
                <div class="ps-card-price-row">
                    <?php if ($is_owned): ?>
                        <span class="ps-card-price" style="color: var(--success, #28a745); font-size: 13px;">
                            <i class="fas fa-check-circle"></i> Adquirido
                        </span>
                    <?php else: ?>
                        <span class="ps-card-price <?= $preco_final == 0 ? 'free' : '' ?>">
                            <?= $preco_display ?>
                        </span>
                        <?php if ($em_promocao && $preco > 0 && $preco_promo): ?>
                            <span class="ps-card-old-price">
                                R$ <?= number_format($preco / 100, 2, ',', '.') ?>
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </article>
    <?php
}

// Função auxiliar para renderizar mobile
function renderGameCardMobile($jogo, $pdo = null, $user_id = null) {
    renderGameCard($jogo, $pdo, $user_id);
}
?>