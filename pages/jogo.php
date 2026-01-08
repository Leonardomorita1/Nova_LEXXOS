<?php
// pages/jogo.php
require_once '../config/config.php';
require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();
$slug = $_GET['slug'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;

if (empty($slug)) {
    header('Location: ' . SITE_URL);
    exit;
}

$in_library = $user_id ? isInLibrary($user_id, $jogo['id'], $pdo) : false;
$in_cart = $user_id ? isInCart($user_id, $jogo['id'], $pdo) : false;
$in_wishlist = $user_id ? isInWishlist($user_id, $jogo['id'], $pdo) : false;

// 1. Fetch Game Data
$stmt = $pdo->prepare("SELECT j.*, d.nome_estudio, d.slug as dev_slug, d.logo_url, d.id as dev_id FROM jogo j LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id WHERE j.slug = ? AND j.status = 'publicado'");
$stmt->execute([$slug]);
$jogo = $stmt->fetch();

if (!$jogo) {
    header('Location: ' . SITE_URL);
    exit;
}

// Increment Views
$pdo->prepare("UPDATE jogo SET total_visualizacoes = total_visualizacoes + 1 WHERE id = ?")->execute([$jogo['id']]);

// Data Fetching Helpers
$stmt_cat = $pdo->prepare("
    SELECT c.nome, c.slug, c.icone 
    FROM categoria c 
    INNER JOIN jogo_categoria jc ON c.id = jc.categoria_id 
    WHERE jc.jogo_id = ?
");
$stmt_cat->execute([$jogo['id']]);
$categorias_do_jogo = $stmt_cat->fetchAll();
$tags = $pdo->prepare("SELECT t.* FROM tag t JOIN jogo_tag jt ON t.id = jt.tag_id WHERE jt.jogo_id = ?");
$tags->execute([$jogo['id']]);
$tags_data = $tags->fetchAll();
$plats = $pdo->prepare("SELECT p.* FROM plataforma p JOIN jogo_plataforma jp ON p.id = jp.plataforma_id WHERE jp.jogo_id = ?");
$plats->execute([$jogo['id']]);
$plataformas = $plats->fetchAll();

// Reviews & Stats
$reviews_query = $pdo->prepare("SELECT a.*, u.nome_usuario, u.avatar_url FROM avaliacao a LEFT JOIN usuario u ON a.usuario_id = u.id WHERE a.jogo_id = ? ORDER BY a.criado_em DESC");
$reviews_query->execute([$jogo['id']]);
$avaliacoes = $reviews_query->fetchAll();

// Rating Distribution Logic (For the Graph)
$rating_dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
foreach ($avaliacoes as $av) {
    $rating_dist[$av['nota']]++;
}
$total_reviews = count($avaliacoes);

// Check User Status
$my_review = null;
if ($user_id) {
    foreach ($avaliacoes as $av) {
        if ($av['usuario_id'] == $user_id) {
            $my_review = $av;
            break;
        }
    }
}


// Discount Logic
$preco_final = ($jogo['em_promocao'] && $jogo['preco_promocional_centavos']) ? $jogo['preco_promocional_centavos'] : $jogo['preco_centavos'];
$desconto = calculateDiscount($jogo['preco_centavos'], $jogo['preco_promocional_centavos'], $jogo['em_promocao']);

$page_title = $jogo['titulo'] . ' - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<style>
    /* VARS & UTILS */
    :root {
        --glass: rgba(22, 27, 34, 0.7);
        --glass-border: rgba(255, 255, 255, 0.1);
    }

    /* HERO SECTION - THE WOW FACTOR */
    .game-hero {
        position: relative;
        min-height: 550px;
        overflow: hidden;
        margin-bottom: -60px;
        z-index: 1;
    }

    .hero-bg {
        position: absolute;
        inset: 0;
        background: url('<?= SITE_URL . ($jogo['imagem_banner'] ?: $jogo['imagem_capa']) ?>') center/cover no-repeat;
        filter: blur(20px) brightness(0.4);
        transform: scale(1.1);
        z-index: -2;
    }

    .hero-gradient {
        position: absolute;
        inset: 0;
        background: linear-gradient(to bottom, transparent 0%, var(--bg-primary) 100%);
        z-index: -1;
    }

    .hero-content {
        display: grid;
        grid-template-columns: 350px 1fr 320px;
        gap: 40px;
        padding-top: 60px;
        position: relative;
    }

    /* GAME COVER & MEDIA */
    .cover-art {
        width: 100%;
        border-radius: 12px;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
        transition: transform 0.3s;
        aspect-ratio: 3/4;
        object-fit: cover;
    }

    .cover-art:hover {
        transform: translateY(-5px) scale(1.02);
    }

    /* MAIN INFO */
    .game-title {
        font-size: 3rem;
        font-weight: 800;
        line-height: 1.1;
        margin-bottom: 15px;
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
    }

    .dev-badge {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        background: rgba(255, 255, 255, 0.05);
        padding: 8px 16px;
        border-radius: 50px;
        border: 1px solid var(--glass-border);
        transition: 0.3s;
        text-decoration: none;
        color: var(--text-primary);
        backdrop-filter: blur(5px);
    }

    .dev-badge:hover {
        background: rgba(255, 255, 255, 0.1);
        transform: translateX(5px);
    }

    .dev-logo {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        object-fit: cover;
    }

    /* STICKY PURCHASE CARD */
    .buy-card {
        background: var(--glass);
        backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: 16px;
        padding: 25px;
        position: sticky;
        top: 100px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    }

    .price-tag {
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--accent);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .price-old {
        font-size: 1rem;
        text-decoration: line-through;
        color: var(--text-secondary);
        opacity: 0.7;
    }

    .discount-pill {
        background: #2ecc71;
        color: #000;
        font-weight: 800;
        font-size: 0.8rem;
        padding: 4px 8px;
        border-radius: 4px;
    }

    /* CONTENT GRID */
    .main-grid {
        display: grid;
        grid-template-columns: 1fr 320px;
        gap: 40px;
        margin-top: 80px;
    }

    .section-box {
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 30px;
        margin-bottom: 30px;
    }

    .section-title {
        font-size: 1.4rem;
        font-weight: 600;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--text-primary);
        border-left: 4px solid var(--accent);
        padding-left: 15px;
    }

    /* CAROUSEL */
    .media-carousel {
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
        margin-bottom: 30px;
        aspect-ratio: 16/9;
    }

    .carousel-item,
    .carousel-item img,
    .carousel-item iframe {
        width: 100%;
        height: 100%;
        object-fit: cover;
        aspect-ratio: 16/9;
    }

    /* REVIEWS & CHART */
    .rating-overview {
        display: flex;
        gap: 30px;
        align-items: center;
        margin-bottom: 30px;
        background: var(--bg-primary);
        padding: 20px;
        border-radius: 12px;
    }

    .big-score {
        font-size: 4rem;
        font-weight: 800;
        line-height: 1;
        color: var(--accent);
    }

    .rating-bars {
        flex: 1;
    }

    .bar-row {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 12px;
        margin-bottom: 4px;
    }

    .bar-track {
        flex: 1;
        height: 8px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 4px;
        overflow: hidden;
    }

    .bar-fill {
        height: 100%;
        background: var(--accent);
        border-radius: 4px;
    }

    .review-card {
        background: var(--bg-primary);
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 15px;
        border: 1px solid var(--border);
    }

    .user-review-actions {
        position: absolute;
        top: 15px;
        right: 15px;
        display: flex;
        gap: 10px;
    }

    /* TAGS */
    .tag-cloud {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .game-tag {
        background: rgba(255, 255, 255, 0.05);
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.85rem;
        color: var(--text-secondary);
        transition: 0.2s;
        text-decoration: none;
    }

    .game-tag:hover {
        background: var(--accent);
        color: white;
    }

    /* RESPONSIVE */
    @media (max-width: 1200px) {
        .hero-content {
            grid-template-columns: 300px 1fr;
        }

        .buy-card {
            display: none;
        }

        /* Mobile Buy bar handled below */
    }

    @media (max-width: 992px) {
        .hero-content {
            display: flex;
            flex-direction: column;
        }

        .cover-art {
            width: 200px;
            margin: 0 auto;
        }

        .game-title {
            text-align: center;
            font-size: 2.2rem;
        }

        .main-grid {
            grid-template-columns: 1fr;
        }

        .rating-overview {
            flex-direction: column;
            text-align: center;
        }
    }

    /* MOBILE STICKY BUY BAR */
    .mobile-buy {
        display: none;
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: var(--bg-secondary);
        padding: 15px;
        z-index: 1000;
        border-top: 1px solid var(--border);
        box-shadow: 0 -5px 20px rgba(0, 0, 0, 0.5);
    }

    @media (max-width: 1200px) {
        .mobile-buy {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
    }
</style>

<div class="game-hero">
    <div class="hero-bg"></div>
    <div class="hero-gradient"></div>

    <div class="container">
        <div class="hero-content">
            <div>
                <img src="<?= SITE_URL . ($jogo['imagem_capa'] ?: '/assets/images/no-image.png') ?>" class="cover-art" alt="Cover">
            </div>

            <div style="padding-top: 20px;">
                <h1 class="game-title"><?= sanitize($jogo['titulo']) ?></h1>

                <a href="<?= SITE_URL ?>/pages/desenvolvedor.php?slug=<?= $jogo['dev_slug'] ?>" class="dev-badge">
                    <img src="<?= SITE_URL . ($jogo['logo_url'] ?: '/assets/images/default-dev.png') ?>" class="dev-logo">
                    <span><?= sanitize($jogo['nome_estudio']) ?></span>
                </a>

                <div style="margin: 25px 0; font-size: 1.1rem; line-height: 1.6; opacity: 0.9;">
                    <?= sanitize($jogo['descricao_curta']) ?>
                </div>

                <div class="tag-cloud">
                    <?php if (!empty($categorias_do_jogo)): ?>
                        <?php foreach ($categorias_do_jogo as $cat): ?>
                            <a href="<?= SITE_URL ?>/pages/categoria.php?slug=<?= $cat['slug'] ?>" class="game-tag">
                                <i class="fas fa-<?= htmlspecialchars($cat['icone']) ?>"></i>
                                <?= htmlspecialchars($cat['nome']) ?>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="text-muted" style="font-size: 0.8rem;">Sem categorias</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-none d-xl-block">
                <div class="buy-card">
                    <div style="text-align: center; margin-bottom: 20px;">
                        <?php if ($preco_final == 0): ?>
                            <div class="price-tag" style="justify-content: center; color: #2ecc71;">GRÁTIS</div>
                        <?php else: ?>
                            <div class="price-tag" style="justify-content: center;">
                                <?= formatPrice($preco_final) ?>
                                <?php if ($desconto > 0): ?><span class="discount-pill">-<?= $desconto ?>%</span><?php endif; ?>
                            </div>
                            <?php if ($desconto > 0): ?><div class="price-old"><?= formatPrice($jogo['preco_centavos']) ?></div><?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <?php if ($in_library): ?>
                        <a href="<?= SITE_URL ?>/user/biblioteca.php" class="btn btn-success btn-lg w-100 mb-2" disabled><i class="fas fa-check"></i> Na Biblioteca</a>
                        <a href="<?= SITE_URL ?>/user/download-jogo.php?jogo_id=<?= $jogo['id'] ?>" class="btn btn-primary btn-lg w-100"><i class="fas fa-download"></i> Instalar</a>
                    <?php elseif ($user_id): ?>
                        <button class="btn btn-primary btn-block" onclick="toggleCart(<?php echo $jogo['id']; ?>, this)">
                            <i class="fas fa-shopping-cart"></i> Adicionar ao Carrinho
                        </button>
                        <button class="btn btn-secondary btn-block" onclick="toggleWishlist(<?php echo $jogo['id']; ?>, this)">
                            <i class="fas fa-heart"></i> <?php echo $in_wishlist ? 'Na Lista' : 'Lista de Desejos'; ?>
                        </button>
                    <?php else: ?>
                        <a href="<?= SITE_URL ?>/auth/login.php" class="btn btn-primary btn-lg w-100">Login para Comprar</a>
                    <?php endif; ?>

                    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border); text-align: center; color: var(--text-secondary);">
                        <small>Plataformas Compatíveis</small>
                        <div style="font-size: 1.5rem; margin-top: 5px; display: flex; justify-content: center; gap: 15px;">
                            <?php foreach ($plataformas as $p): ?><i class="<?= $p['icone'] ?>" title="<?= $p['nome'] ?>"></i><?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <div class="main-grid">
        <div>
            <?php
            $media = $pdo->prepare("SELECT * FROM jogo_imagens WHERE jogo_id = ? ORDER BY ordem");
            $media->execute([$jogo['id']]);
            $imgs = $media->fetchAll();
            if ($jogo['video_trailer'] || count($imgs) > 0):
            ?>
                <div id="gameCarousel" class="carousel slide media-carousel" data-bs-ride="false">
                    <div class="carousel-inner h-100">
                        <?php $active = true; ?>
                        <?php if ($jogo['video_trailer']): ?>
                            <div class="carousel-item active">
                                <iframe src="<?= $jogo['video_trailer'] ?>" allowfullscreen style="border:0"></iframe>
                            </div>
                            <?php $active = false; ?>
                        <?php endif; ?>

                        <?php foreach ($imgs as $img): ?>
                            <div class="carousel-item <?= $active ? 'active' : '' ?>">
                                <img src="<?= SITE_URL . $img['imagem'] ?>">
                            </div>
                            <?php $active = false; ?>
                        <?php endforeach; ?>
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#gameCarousel" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span></button>
                    <button class="carousel-control-next" type="button" data-bs-target="#gameCarousel" data-bs-slide="next"><span class="carousel-control-next-icon"></span></button>
                </div>
            <?php endif; ?>

            <div class="section-box">
                <h2 class="section-title"><i class="fas fa-align-left"></i> Sobre o Jogo</h2>
                <div style="line-height: 1.8; color: var(--text-secondary); font-size: 1.05rem;">
                    <?= nl2br(sanitize($jogo['descricao_completa'])) ?>
                </div>
            </div>

            <div class="section-box" id="reviews">
                <h2 class="section-title"><i class="fas fa-star"></i> Avaliações da Comunidade</h2>

                <div class="rating-overview">
                    <div style="text-align: center; min-width: 120px;">
                        <div class="big-score"><?= number_format($jogo['nota_media'], 1) ?></div>
                        <div style="color: #f1c40f; margin-bottom: 5px;">
                            <?php for ($i = 1; $i <= 5; $i++) echo ($i <= round($jogo['nota_media'])) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>'; ?>
                        </div>
                        <small><?= $total_reviews ?> avaliações</small>
                    </div>
                    <div class="rating-bars">
                        <?php for ($i = 5; $i >= 1; $i--):
                            $pct = $total_reviews > 0 ? ($rating_dist[$i] / $total_reviews) * 100 : 0;
                        ?>
                            <div class="bar-row">
                                <span style="width: 10px;"><?= $i ?></span> <i class="fas fa-star" style="font-size: 8px; color: #888; margin-right: 5px;"></i>
                                <div class="bar-track">
                                    <div class="bar-fill" style="width: <?= $pct ?>%"></div>
                                </div>
                                <span style="width: 30px; text-align: right; color: #888;"><?= $rating_dist[$i] ?></span>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <?php if ($in_library): ?>
                    <?php if ($my_review): ?>
                        <div class="user-review-highlight" style="background: rgba(var(--accent-rgb), 0.08); border: 1px solid var(--accent); border-radius: 16px; padding: 25px; margin-bottom: 30px; position: relative; backdrop-filter: blur(10px);">

                            <div style="position: absolute; top: -12px; left: 20px; background: var(--accent); color: #000; padding: 2px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase;">
                                Sua Análise
                            </div>

                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="d-flex align-items-center gap-3">
                                    <img src="<?= getAvatar($my_review['avatar_url']) ?>" style="width: 50px; height: 50px; border-radius: 50%; border: 2px solid var(--accent);">
                                    <div>
                                        <h4 style="margin: 0; font-size: 1.1rem; color: var(--text-primary);">Você</h4>
                                        <div style="color: #f1c40f; font-size: 0.9rem;">
                                            <?php for ($i = 1; $i <= 5; $i++) echo ($i <= $my_review['nota']) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>'; ?>
                                            <span style="color: var(--text-secondary); margin-left: 8px; font-size: 0.8rem;">
                                                <?= date('d/m/Y', strtotime($my_review['criado_em'])) ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="review-actions">
                                    <button class="btn btn-sm btn-outline-light" style="border-radius: 8px; border-color: rgba(255,255,255,0.1);"
                                        onclick="openReviewModal(<?= $my_review['nota'] ?>, `<?= addslashes($my_review['comentario']) ?>`)">
                                        <i class="fas fa-edit"></i> Editar
                                    </button>
                                    <form action="<?= SITE_URL ?>/api/avaliar.php" method="POST" style="display:inline" onsubmit="return confirm('Tem certeza que deseja remover sua avaliação?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="jogo_id" value="<?= $jogo['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" style="border-radius: 8px; margin-left: 5px;">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <div style="background: rgba(255,255,255,0.03); padding: 15px; border-radius: 12px; color: var(--text-secondary); line-height: 1.6; border-left: 3px solid var(--accent);">
                                <?= nl2br(htmlspecialchars($my_review['comentario'])) ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <button class="btn btn-primary w-100 mb-4" style="padding: 15px; border-radius: 12px; font-weight: 600;" onclick="openReviewModal()">
                            <i class="fas fa-pen-nib"></i> Escrever uma Análise para este jogo
                        </button>
                    <?php endif; ?>
                <?php endif; ?>

                <?php foreach ($avaliacoes as $rev): if ($rev['usuario_id'] == $user_id) continue; ?>
                    <div class="review-card">
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <img src="<?= getAvatar($rev['avatar_url']) ?>" style="width: 40px; height: 40px; border-radius: 50%;">
                            <div>
                                <div style="font-weight: 600;"><?= sanitize($rev['nome_usuario']) ?></div>
                                <div style="font-size: 0.8rem; color: var(--text-secondary);"><?= date('d/m/Y', strtotime($rev['criado_em'])) ?></div>
                            </div>
                        </div>
                        <div style="color: #f1c40f; margin-bottom: 10px;">
                            <?php for ($i = 1; $i <= 5; $i++) echo ($i <= $rev['nota']) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>'; ?>
                        </div>
                        <p style="color: #ccc;"><?= nl2br(sanitize($rev['comentario'])) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div>
            <div class="section-box">
                <h3 class="section-title" style="font-size: 1.1rem;"><i class="fas fa-microchip"></i> Requisitos</h3>
                <?php if ($jogo['requisitos_minimos']): ?>
                    <strong class="d-block mb-2 text-white">Mínimos:</strong>
                    <div style="font-size: 0.9rem; color: #aaa; margin-bottom: 15px; white-space: pre-wrap;"><?= sanitize($jogo['requisitos_minimos']) ?></div>
                <?php endif; ?>
                <?php if ($jogo['requisitos_recomendados']): ?>
                    <strong class="d-block mb-2 text-white">Recomendados:</strong>
                    <div style="font-size: 0.9rem; color: #aaa; white-space: pre-wrap;"><?= sanitize($jogo['requisitos_recomendados']) ?></div>
                <?php endif; ?>
            </div>

            <div class="section-box">
                <h3 class="section-title" style="font-size: 1.1rem;"><i class="fas fa-tags"></i> Marcadores</h3>
                <div class="tag-cloud">
                    <?php foreach ($tags_data as $t): ?>
                        <span class="game-tag"><?= $t['nome'] ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="reviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--bg-secondary); border: 1px solid var(--border);">
            <div class="modal-header" style="border-bottom-color: var(--border);">
                <h5 class="modal-title" id="revModalTitle">Avaliar Jogo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= SITE_URL ?>/api/avaliar.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" id="revAction" value="add">
                    <input type="hidden" name="jogo_id" value="<?= $jogo['id'] ?>">

                    <div class="mb-3 text-center">
                        <label class="form-label d-block">Sua Nota</label>
                        <div class="rating-input" style="font-size: 2rem; color: #f1c40f; cursor: pointer;">
                            <i class="far fa-star" data-val="1"></i>
                            <i class="far fa-star" data-val="2"></i>
                            <i class="far fa-star" data-val="3"></i>
                            <i class="far fa-star" data-val="4"></i>
                            <i class="far fa-star" data-val="5"></i>
                        </div>
                        <input type="hidden" name="nota" id="notaInput" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Sua Análise</label>
                        <textarea name="comentario" id="comentarioInput" class="form-control" rows="4" style="background: var(--bg-primary); border-color: var(--border); color: white;" required></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="border-top-color: var(--border);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Publicar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="mobile-buy d-xl-none">
    <div style="font-weight: 700; color: var(--accent);"><?= $preco_final == 0 ? 'GRÁTIS' : formatPrice($preco_final) ?></div>
    <?php if ($in_library): ?>
        <button class="btn btn-success btn-sm">Instalar</button>
    <?php else: ?>
        <button class="btn btn-primary btn-sm" onclick="addToCart(<?= $jogo['id'] ?>)">Comprar</button>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Rating Star Logic for Modal
    const stars = document.querySelectorAll('.rating-input i');
    const noteInput = document.getElementById('notaInput');

    stars.forEach(s => {
        s.onclick = function() {
            const val = this.dataset.val;
            noteInput.value = val;
            updateStars(val);
        }
        s.onmouseenter = function() {
            updateStars(this.dataset.val);
        }
    });

    document.querySelector('.rating-input').onmouseleave = function() {
        updateStars(noteInput.value || 0);
    }

    function updateStars(val) {
        stars.forEach(s => {
            if (s.dataset.val <= val) {
                s.classList.remove('far');
                s.classList.add('fas');
            } else {
                s.classList.remove('fas');
                s.classList.add('far');
            }
        });
    }

    // Modal Trigger
    function openReviewModal(nota = 0, text = '') {
        const modal = new bootstrap.Modal(document.getElementById('reviewModal'));
        document.getElementById('revAction').value = nota > 0 ? 'update' : 'add';
        document.getElementById('revModalTitle').innerText = nota > 0 ? 'Editar Avaliação' : 'Escrever Análise';
        document.getElementById('notaInput').value = nota;
        document.getElementById('comentarioInput').value = text;
        updateStars(nota);
        modal.show();
    }
</script>

<?php require_once '../includes/footer.php'; ?>