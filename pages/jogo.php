<?php
// pages/jogo.php
require_once '../config/config.php';
require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();

$slug = $_GET['slug'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;

if (empty($slug)) {
    header('Location: ' . SITE_URL . '/pages/home.php');
    exit;
}

// Buscar jogo
$stmt = $pdo->prepare("
    SELECT j.*, d.nome_estudio, d.slug as dev_slug, d.logo_url, d.id as dev_id, d.banner_url as dev_banner
    FROM jogo j
    LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id
    WHERE j.slug = ? AND j.status = 'publicado'
");
$stmt->execute([$slug]);
$jogo = $stmt->fetch();

if (!$jogo) {
    header('Location: ' . SITE_URL . '/pages/home.php');
    exit;
}

// Incrementar visualizações
$stmt = $pdo->prepare("UPDATE jogo SET total_visualizacoes = total_visualizacoes + 1 WHERE id = ?");
$stmt->execute([$jogo['id']]);

// Buscar categorias, tags, plataformas
$stmt = $pdo->prepare("SELECT c.* FROM categoria c INNER JOIN jogo_categoria jc ON c.id = jc.categoria_id WHERE jc.jogo_id = ?");
$stmt->execute([$jogo['id']]);
$categorias = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT t.* FROM tag t INNER JOIN jogo_tag jt ON t.id = jt.tag_id WHERE jt.jogo_id = ?");
$stmt->execute([$jogo['id']]);
$tags = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT p.* FROM plataforma p INNER JOIN jogo_plataforma jp ON p.id = jp.plataforma_id WHERE jp.jogo_id = ?");
$stmt->execute([$jogo['id']]);
$plataformas = $stmt->fetchAll();

// Buscar avaliações
$stmt = $pdo->prepare("
    SELECT a.*, u.nome_usuario, u.avatar_url
    FROM avaliacao a
    LEFT JOIN usuario u ON a.usuario_id = u.id
    WHERE a.jogo_id = ? AND a.status = 'ativo'
    ORDER BY a.criado_em DESC
    LIMIT 10
");
$stmt->execute([$jogo['id']]);
$avaliacoes = $stmt->fetchAll();

$usuario_avaliou = false;
if ($user_id) {
    $stmt = $pdo->prepare("SELECT id FROM avaliacao WHERE usuario_id = ? AND jogo_id = ?");
    $stmt->execute([$user_id, $jogo['id']]);
    $usuario_avaliou = $stmt->fetch() !== false;
}

$in_library = $user_id ? isInLibrary($user_id, $jogo['id'], $pdo) : false;
$in_cart = $user_id ? isInCart($user_id, $jogo['id'], $pdo) : false;
$in_wishlist = $user_id ? isInWishlist($user_id, $jogo['id'], $pdo) : false;

// Jogos relacionados
$stmt = $pdo->prepare("
    SELECT DISTINCT j.*, d.nome_estudio
    FROM jogo j
    LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id
    INNER JOIN jogo_categoria jc ON j.id = jc.jogo_id
    WHERE jc.categoria_id IN (SELECT categoria_id FROM jogo_categoria WHERE jogo_id = ?)
    AND j.id != ? AND j.status = 'publicado'
    ORDER BY j.nota_media DESC, j.total_vendas DESC
    LIMIT 4
");
$stmt->execute([$jogo['id'], $jogo['id']]);
$jogos_relacionados = $stmt->fetchAll();

$preco_final = $jogo['em_promocao'] && $jogo['preco_promocional_centavos']
    ? $jogo['preco_promocional_centavos']
    : $jogo['preco_centavos'];

$desconto = calculateDiscount($jogo['preco_centavos'], $jogo['preco_promocional_centavos'], $jogo['em_promocao']);

$page_title = $jogo['titulo'] . ' - ' . SITE_NAME;
$page_description = $jogo['descricao_curta'];

require_once '../components/game-card.php';
require_once '../includes/header.php';

// Mensagens de feedback
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<style>
    .game-hero {
        position: relative;
        width: 100%;
        min-height: 600px;
        background: var(--bg-primary);
        margin-bottom: 30px;
    }

    .game-hero-bg {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        overflow: hidden;
    }

    .game-hero-bg img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        filter: blur(10px);
        transform: scale(1.1);
        opacity: 0.3;
    }

    .game-hero-gradient {
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 60%;
        background: linear-gradient(to top, var(--bg-primary) 0%, transparent 100%);
    }

    .game-hero-content {
        position: relative;
        z-index: 1;
        padding: 60px 0 40px;
    }

    .game-hero-main {
        display: grid;
        grid-template-columns: 400px 1fr 350px;
        gap: 40px;
        align-items: start;
    }

    .game-cover {
        width: 100%;
        border-radius: 15px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        transition: transform 0.3s;
    }

    .game-cover:hover {
        transform: translateY(-10px);
    }

    .game-info-header h1 {
        font-size: 42px;
        margin-bottom: 15px;
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
    }

    .game-developer-link {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        background: rgba(0, 0, 0, 0.3);
        backdrop-filter: blur(10px);
        border-radius: 10px;
        margin-bottom: 20px;
        transition: all 0.3s;
        width: fit-content;
    }

    .game-developer-link:hover {
        background: rgba(76, 139, 245, 0.2);
        transform: translateX(5px);
    }

    .dev-avatar-small {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        overflow: hidden;
        border: 2px solid var(--accent);
    }

    .dev-avatar-small img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .game-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin: 20px 0;
    }

    .meta-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        background: rgba(0, 0, 0, 0.3);
        backdrop-filter: blur(10px);
        padding: 8px 14px;
        border-radius: 8px;
    }

    .meta-item i {
        color: var(--accent);
    }

    .game-purchase-box {
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 15px;
        padding: 25px;
        position: sticky;
        top: 90px;
    }

    .price-section {
        text-align: center;
        padding: 20px;
        background: var(--bg-primary);
        border-radius: 10px;
        margin-bottom: 20px;
    }

    .price-old {
        font-size: 16px;
        color: var(--text-secondary);
        text-decoration: line-through;
        display: block;
        margin-bottom: 5px;
    }

    .price-current {
        font-size: 36px;
        font-weight: 700;
        color: var(--accent);
    }

    .discount-badge {
        display: inline-block;
        background: var(--success);
        color: white;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 16px;
        font-weight: 600;
        margin-left: 10px;
    }

    .purchase-actions {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .platforms-box {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid var(--border);
        text-align: center;
    }

    .platforms-list {
        display: flex;
        justify-content: center;
        gap: 15px;
        margin-top: 10px;
    }

    .platform-icon {
        font-size: 28px;
        color: var(--text-primary);
    }


    /* Seções de conteúdo */
    .game-content {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 30px;
    }

    .content-section {
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 15px;
        padding: 30px;
        margin-bottom: 30px;
    }

    .content-section h2 {
        font-size: 24px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .content-section h2 i {
        color: var(--accent);
    }

    .game-description {
        line-height: 1.8;
        color: var(--text-secondary);
    }

    .requirements-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .req-box h3 {
        font-size: 16px;
        margin-bottom: 15px;
        color: var(--accent);
    }

    .req-box pre {
        background: var(--bg-primary);
        padding: 15px;
        border-radius: 8px;
        font-size: 13px;
        line-height: 1.6;
        white-space: pre-wrap;
    }

    .tags-grid,
    .categories-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .tag-badge,
    .category-badge {
        background: var(--bg-primary);
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 14px;
        transition: all 0.3s;
    }

    .tag-badge:hover,
    .category-badge:hover {
        background: var(--accent);
        color: white;
    }

    .category-badge {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    /* Reviews */
    .review-form {
        background: var(--bg-primary);
        padding: 25px;
        border-radius: 10px;
        margin-bottom: 30px;
    }

    .rating-input {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        font-size: 32px;
    }

    .rating-input i {
        cursor: pointer;
        color: var(--text-secondary);
        transition: all 0.3s;
    }

    .rating-input i.active,
    .rating-input i:hover {
        color: #ffc107;
        transform: scale(1.1);
    }

    .review-item {
        background: var(--bg-primary);
        padding: 25px;
        border-radius: 10px;
        margin-bottom: 20px;
    }

    .review-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 15px;
    }

    .review-user {
        display: flex;
        gap: 15px;
    }

    .review-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        overflow: hidden;
    }

    .review-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .review-stars {
        color: #ffc107;
        font-size: 14px;
    }

    .review-text {
        line-height: 1.6;
        margin-bottom: 15px;
    }

    .review-response {
        background: var(--bg-secondary);
        padding: 20px;
        border-radius: 8px;
        border-left: 3px solid var(--accent);
        margin-top: 15px;
    }
    .carousel{ 
        margin-bottom: 30px;
        
    }
    .carousel-item{
        aspect-ratio: 16/9;
        border-radius: var(--border);
        overflow: hidden;
        img{border-radius: var(--border); overflow: hidden;}
    }

    @media (max-width: 1200px) {
        .game-hero-main {
            grid-template-columns: 300px 1fr 300px;
        }
    }

    @media (max-width: 992px) {
        .game-hero-main {
            grid-template-columns: 1fr;
        }

        .game-cover {
            max-width: 400px;
            margin: 0 auto;
        }

        .game-purchase-box {
            position: static;
        }

        .game-content {
            grid-template-columns: 1fr;
        }

        .requirements-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<!-- Hero Section -->
<div class="game-hero">
    <div class="game-hero-bg">
        <img src="<?php echo SITE_URL . ($jogo['imagem_banner'] ?: $jogo['imagem_capa']); ?>"
            alt="<?php echo sanitize($jogo['titulo']); ?>">
    </div>
    <div class="game-hero-gradient"></div>

    <div class="container">
        <div class="game-hero-content">
            <div class="game-hero-main">
                <!-- Capa -->
                <div>
                    <img src="<?php echo SITE_URL . ($jogo['imagem_capa'] ?: '/assets/images/no-image.png'); ?>"
                        alt="<?php echo sanitize($jogo['titulo']); ?>"
                        class="game-cover">
                </div>

                <!-- Info Principal -->
                <div class="game-info-header">
                    <h1><?php echo sanitize($jogo['titulo']); ?></h1>

                    <a href="<?php echo SITE_URL; ?>/pages/desenvolvedor.php?slug=<?php echo $jogo['dev_slug']; ?>"
                        class="game-developer-link">
                        <div class="dev-avatar-small">
                            <img src="<?php echo SITE_URL . ($jogo['logo_url'] ?: '/assets/images/default-dev.png'); ?>"
                                alt="<?php echo sanitize($jogo['nome_estudio']); ?>">
                        </div>
                        <div>
                            <strong><?php echo sanitize($jogo['nome_estudio']); ?></strong>
                            <p style="font-size: 12px; color: var(--text-secondary); margin: 0;">Desenvolvedor</p>
                        </div>
                    </a>

                    <p style="font-size: 18px; line-height: 1.6; margin-bottom: 20px;">
                        <?php echo sanitize($jogo['descricao_curta']); ?>
                    </p>

                    <div class="game-meta">
                        <?php if ($jogo['nota_media'] > 0): ?>
                            <div class="meta-item">
                                <i class="fas fa-star"></i>
                                <span><?php echo number_format($jogo['nota_media'], 1); ?> (<?php echo $jogo['total_avaliacoes']; ?>)</span>
                            </div>
                        <?php endif; ?>

                        <div class="meta-item">
                            <i class="fas fa-download"></i>
                            <span><?php echo $jogo['total_vendas']; ?> vendas</span>
                        </div>

                        <div class="meta-item">
                            <i class="fas fa-eye"></i>
                            <span><?php echo $jogo['total_visualizacoes']; ?> views</span>
                        </div>

                        <?php if ($jogo['data_lancamento']): ?>
                            <div class="meta-item">
                                <i class="fas fa-calendar"></i>
                                <span><?php echo date('d/m/Y', strtotime($jogo['data_lancamento'])); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Box de Compra -->
                <div class="game-purchase-box">
                    <div class="price-section">
                        <?php if ($preco_final == 0): ?>
                            <span class="price-current" style="color: var(--success);">GRÁTIS</span>
                        <?php else: ?>
                            <?php if ($desconto > 0): ?>
                                <span class="price-old"><?php echo formatPrice($jogo['preco_centavos']); ?></span>
                            <?php endif; ?>
                            <div>
                                <span class="price-current"><?php echo formatPrice($preco_final); ?></span>
                                <?php if ($desconto > 0): ?>
                                    <span class="discount-badge">-<?php echo $desconto; ?>%</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="purchase-actions">
                        <?php if ($in_library): ?>
                            <button class="btn btn-success btn-block" disabled>
                                <i class="fas fa-check"></i> Na Biblioteca
                            </button>
                            <a href="#download" class="btn btn-primary btn-block">
                                <i class="fas fa-download"></i> Baixar Jogo
                            </a>
                        <?php elseif ($user_id): ?>
                            <?php if ($in_cart): ?>
                                <button class="btn btn-secondary btn-block" disabled>
                                    <i class="fas fa-check"></i> No Carrinho
                                </button>
                                <a href="<?php echo SITE_URL; ?>/user/carrinho.php" class="btn btn-primary btn-block">
                                    <i class="fas fa-shopping-cart"></i> Ir para Carrinho
                                </a>
                            <?php else: ?>
                                <button class="btn btn-primary btn-block" onclick="toggleCart(<?php echo $jogo['id']; ?>, this)">
                                    <i class="fas fa-shopping-cart"></i> Adicionar ao Carrinho
                                </button>
                            <?php endif; ?>

                            <button class="btn btn-secondary btn-block" onclick="toggleWishlist(<?php echo $jogo['id']; ?>, this)">
                                <i class="fas fa-heart"></i> <?php echo $in_wishlist ? 'Na Lista' : 'Lista de Desejos'; ?>
                            </button>
                        <?php else: ?>
                            <a href="<?php echo SITE_URL; ?>/auth/login.php" class="btn btn-primary btn-block">
                                <i class="fas fa-sign-in-alt"></i> Entre para Comprar
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="platforms-box">
                        <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 10px;">
                            Disponível para:
                        </p>
                        <div class="platforms-list">
                            <?php foreach ($plataformas as $plat): ?>
                                <i class="<?php echo $plat['icone']; ?> platform-icon"
                                    title="<?php echo $plat['nome']; ?>"></i>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <?php if ($success): ?>
        <div style="background: rgba(40,167,69,0.1); border: 1px solid var(--success); color: var(--success); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div style="background: rgba(220,53,69,0.1); border: 1px solid var(--danger); color: var(--danger); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <!-- Carrossel de Mídia -->
    <?php
    // Buscar imagens do jogo
    $stmt = $pdo->prepare("SELECT * FROM jogo_imagens WHERE jogo_id = ? ORDER BY ordem ASC");
    $stmt->execute([$jogo['id']]);
    $imagens = $stmt->fetchAll();

    $has_media = !empty($jogo['video_trailer']) || count($imagens) > 0;
    ?>

    <?php
    // Preparar os dados para facilitar o loop
    $has_trailer = !empty($jogo['video_trailer']);
    $has_images = (count($imagens) > 0);
    $has_any_media = $has_trailer || $has_images;

    if ($has_any_media):
    ?>
        <div id="gameCarousel" class="carousel slide carousel-fade" data-bs-ride="false">
            <div class="carousel-indicators">
                <?php
                $indicator_index = 0;
                if ($has_trailer): ?>
                    <button type="button" data-bs-target="#gameCarousel" data-bs-slide-to="0" class="active"></button>
                <?php $indicator_index++;
                endif;

                foreach ($imagens as $img): ?>
                    <button type="button" data-bs-target="#gameCarousel" data-bs-slide-to="<?php echo $indicator_index; ?>" class="<?php echo ($indicator_index === 0) ? 'active' : ''; ?>"></button>
                <?php $indicator_index++;
                endforeach; ?>
            </div>

            <div class="carousel-inner">
                <?php
                $active_set = false;

                // 1. Renderizar Trailer
                if ($has_trailer):
                    $active_set = true;
                ?>
                    <div class="carousel-item active">
                            <iframe src="<?php echo $jogo['video_trailer']; ?>" allowfullscreen ></iframe>
                        
                    </div>
                <?php endif; ?>

                // 2. Renderizar Imagens
                <?php foreach ($imagens as $img): ?>
                    <div class="carousel-item <?php echo (!$active_set) ? 'active' : ''; ?>">
                        <img src="<?php echo SITE_URL . $img['imagem']; ?>" class="d-block w-100" style="object-fit: cover;" alt="Screenshot">
                    </div>
                    <?php $active_set = true; ?>
                <?php endforeach; ?>
            </div>

            <button class="carousel-control-prev" type="button" data-bs-target="#gameCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#gameCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                
            </button>
        </div>
    <?php endif; ?>

    <!-- Conteúdo Principal -->
    <div class="game-content">
        <div>
            <!-- Descrição -->
            <div class="content-section">
                <h2><i class="fas fa-info-circle"></i> Sobre o Jogo</h2>
                <div class="game-description">
                    <?php echo nl2br(sanitize($jogo['descricao_completa'])); ?>
                </div>
            </div>

            <!-- Requisitos -->
            <?php if ($jogo['requisitos_minimos'] || $jogo['requisitos_recomendados']): ?>
                <div class="content-section">
                    <h2><i class="fas fa-desktop"></i> Requisitos do Sistema</h2>
                    <div class="requirements-grid">
                        <?php if ($jogo['requisitos_minimos']): ?>
                            <div class="req-box">
                                <h3>Mínimos</h3>
                                <pre><?php echo sanitize($jogo['requisitos_minimos']); ?></pre>
                            </div>
                        <?php endif; ?>

                        <?php if ($jogo['requisitos_recomendados']): ?>
                            <div class="req-box">
                                <h3>Recomendados</h3>
                                <pre><?php echo sanitize($jogo['requisitos_recomendados']); ?></pre>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Avaliações -->
            <div class="content-section" id="avaliacoes">
                <h2><i class="fas fa-comments"></i> Avaliações (<?php echo count($avaliacoes); ?>)</h2>

                <?php if ($user_id && $in_library && !$usuario_avaliou): ?>
                    <div class="review-form">
                        <h3 style="margin-bottom: 20px;">Avaliar este jogo</h3>
                        <form method="POST" action="<?php echo SITE_URL; ?>/api/add-review.php">
                            <input type="hidden" name="jogo_id" value="<?php echo $jogo['id']; ?>">

                            <div class="rating-input" id="ratingInput">
                                <i class="far fa-star" data-rating="1"></i>
                                <i class="far fa-star" data-rating="2"></i>
                                <i class="far fa-star" data-rating="3"></i>
                                <i class="far fa-star" data-rating="4"></i>
                                <i class="far fa-star" data-rating="5"></i>
                                <input type="hidden" name="nota" id="ratingValue" required>
                            </div>

                            <div class="form-group">
                                <textarea name="comentario"
                                    class="form-control"
                                    placeholder="Escreva sua avaliação..."
                                    rows="4"
                                    required></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Enviar Avaliação
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

                <?php foreach ($avaliacoes as $review): ?>
                    <div class="review-item">
                        <div class="review-header">
                            <div class="review-user">
                                <div class="review-avatar">
                                    <img src="<?php echo getAvatar($review['avatar_url']); ?>"
                                        alt="<?php echo sanitize($review['nome_usuario']); ?>">
                                </div>
                                <div>
                                    <strong><?php echo sanitize($review['nome_usuario']); ?></strong>
                                    <div class="review-stars">
                                        <?php
                                        for ($i = 1; $i <= 5; $i++) {
                                            echo $i <= $review['nota'] ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <span style="font-size: 13px; color: var(--text-secondary);">
                                <?php echo date('d/m/Y', strtotime($review['criado_em'])); ?>
                            </span>
                        </div>

                        <div class="review-text">
                            <?php echo nl2br(sanitize($review['comentario'])); ?>
                        </div>

                        <?php if ($review['resposta_dev']): ?>
                            <div class="review-response">
                                <strong><i class="fas fa-code"></i> Resposta do Desenvolvedor:</strong>
                                <p style="margin-top: 10px;"><?php echo nl2br(sanitize($review['resposta_dev'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Sidebar -->
        <div>
            <!-- Categorias -->
            <?php if (count($categorias) > 0): ?>
                <div class="content-section">
                    <h2><i class="fas fa-th"></i> Categorias</h2>
                    <div class="categories-grid">
                        <?php foreach ($categorias as $cat): ?>
                            <a href="<?php echo SITE_URL; ?>/pages/categoria.php?slug=<?php echo $cat['slug']; ?>"
                                class="category-badge">
                                <i class="fas fa-<?php echo $cat['icone']; ?>"></i>
                                <?php echo sanitize($cat['nome']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Tags -->
            <?php if (count($tags) > 0): ?>
                <div class="content-section">
                    <h2><i class="fas fa-tags"></i> Tags</h2>
                    <div class="tags-grid">
                        <?php foreach ($tags as $tag): ?>
                            <span class="tag-badge"><?php echo sanitize($tag['nome']); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Jogos Relacionados -->
    <?php if (count($jogos_relacionados) > 0): ?>
        <div style="margin-top: 40px;">
            <h2 style="font-size: 28px; margin-bottom: 25px;">
                <i class="fas fa-gamepad"></i> Jogos Relacionados
            </h2>
            <div class="grid grid-4">
                <?php foreach ($jogos_relacionados as $related): ?>
                    <?php renderGameCard($related, $pdo, $user_id); ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    // Rating stars
    document.querySelectorAll('#ratingInput i').forEach(star => {
        star.addEventListener('click', function() {
            const rating = this.dataset.rating;
            document.getElementById('ratingValue').value = rating;

            document.querySelectorAll('#ratingInput i').forEach((s, index) => {
                if (index < rating) {
                    s.classList.remove('far');
                    s.classList.add('fas', 'active');
                } else {
                    s.classList.remove('fas', 'active');
                    s.classList.add('far');
                }
            });
        });

        star.addEventListener('mouseenter', function() {
            const rating = this.dataset.rating;
            document.querySelectorAll('#ratingInput i').forEach((s, index) => {
                if (index < rating) {
                    s.classList.add('active');
                } else {
                    s.classList.remove('active');
                }
            });
        });
    });

    document.getElementById('ratingInput')?.addEventListener('mouseleave', function() {
        const currentRating = document.getElementById('ratingValue').value;
        if (currentRating) {
            document.querySelectorAll('#ratingInput i').forEach((s, index) => {
                if (index < currentRating) {
                    s.classList.remove('far');
                    s.classList.add('fas', 'active');
                } else {
                    s.classList.remove('fas', 'active');
                    s.classList.add('far');
                }
            });
        }
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
<?php require_once '../includes/footer.php'; ?>