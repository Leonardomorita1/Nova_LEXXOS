<?php
function renderGameCard($jogo, $pdo, $user_id = null) {
    $preco_original = $jogo['preco_centavos'];
    $preco_promocional = $jogo['preco_promocional_centavos'];
    $esta_em_promocao = ($jogo['em_promocao'] && $preco_promocional < $preco_original);
    
    $preco_final = $esta_em_promocao ? $preco_promocional : $preco_original;
    $desconto = calculateDiscount($preco_original, $preco_promocional, $jogo['em_promocao']);
    
    // Verifica se o usuário logado possui o jogo
    $possui_jogo = $user_id ? isInLibrary($user_id, $jogo['id'], $pdo) : false;

    $imagem = (!empty($jogo['imagem_capa']) && file_exists(BASE_PATH . '/' . $jogo['imagem_capa'])) 
        ? SITE_URL . $jogo['imagem_capa'] 
        : SITE_URL . '/assets/images/no-image.png';

    $classificacao = $jogo['classificacao_etaria'] ?? 'L';
?>

<div class="lex-card">
    <a href="<?php echo SITE_URL; ?>/pages/jogo.php?slug=<?php echo $jogo['slug']; ?>" class="lex-card-link-wrapper">
        <div class="lex-card-image">
            <img src="<?php echo $imagem; ?>" alt="<?php echo sanitize($jogo['titulo']); ?>" loading="lazy">
        </div>
    </a>

    <div class="lex-card-content">
        <div class="lex-card-top">
            <a href="<?php echo SITE_URL; ?>/pages/jogo.php?slug=<?php echo $jogo['slug']; ?>" style="text-decoration:none;">
                <h3 class="lex-card-title"><?php echo sanitize($jogo['titulo']); ?></h3>
            </a>
            <div class="lex-card-stars">
                <?php for($i=1; $i<=5; $i++): ?>
                    <i class="fas fa-star <?php echo $i <= round($jogo['nota_media'] ?? 0) ? 'active' : ''; ?>"></i>
                <?php endfor; ?>
            </div>
        </div>

        <div class="lex-card-mid">
            <?php if ($possui_jogo): ?>
                <a href="<?php echo SITE_URL; ?>/user/download-jogo.php?jogo_id=<?php echo $jogo['id']; ?>" class="lex-btn-download">
                    <i class="fas fa-download"></i> Baixar
                </a>
            <?php elseif ($esta_em_promocao): ?>
                <div class="lex-price-badge">-<?php echo $desconto; ?>%</div>
                <div class="lex-price-wrapper">
                    <span class="lex-old-price"><?php echo formatPrice($preco_original); ?></span>
                    <span class="lex-current-price"><?php echo formatPrice($preco_final); ?></span>
                </div>
            <?php else: ?>
                <span class="lex-current-price">
                    <?php echo ($preco_final == 0) ? 'Grátis' : formatPrice($preco_final); ?>
                </span>
            <?php endif; ?>
        </div>

        <div class="lex-card-bottom">
            <span class="lex-age age-<?php echo strtolower($classificacao); ?>">
                <?php echo $classificacao; ?>
            </span>
            <?php if ($possui_jogo): ?>
                <span class="lex-owned-status">Na sua conta</span>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php } ?>