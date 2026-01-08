<?php
// pages/home.php
require_once '../config/config.php';
require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();

$page_title = 'Home - ' . SITE_NAME;
$page_description = 'Descubra e jogue os melhores jogos indies do Brasil';

$user_id = $_SESSION['user_id'] ?? null;

// Buscar banners ativos
$stmt = $pdo->query("
    SELECT * FROM banner 
    WHERE ativo = 1 
    AND (data_inicio IS NULL OR data_inicio <= CURDATE())
    AND (data_fim IS NULL OR data_fim >= CURDATE())
    ORDER BY ordem, id DESC
");
$banners = $stmt->fetchAll();

// Buscar jogos em promoção
$stmt = $pdo->query("
    SELECT j.*, d.nome_estudio, d.slug as dev_slug,
           GROUP_CONCAT(DISTINCT t.nome) as tags
    FROM jogo j
    LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id
    LEFT JOIN jogo_tag jt ON j.id = jt.jogo_id
    LEFT JOIN tag t ON jt.tag_id = t.id
    WHERE j.status = 'publicado' AND j.em_promocao = 1
    GROUP BY j.id
    ORDER BY j.preco_promocional_centavos ASC
    LIMIT 8
");
$jogos_promocao = $stmt->fetchAll();

// Buscar lançamentos recentes
$stmt = $pdo->query("
    SELECT j.*, d.nome_estudio, d.slug as dev_slug,
           GROUP_CONCAT(DISTINCT t.nome) as tags
    FROM jogo j
    LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id
    LEFT JOIN jogo_tag jt ON j.id = jt.jogo_id
    LEFT JOIN tag t ON jt.tag_id = t.id
    WHERE j.status = 'publicado'
    GROUP BY j.id
    ORDER BY j.publicado_em DESC
    LIMIT 8
");
$jogos_lancamentos = $stmt->fetchAll();

// Buscar jogos mais vendidos
$stmt = $pdo->query("
    SELECT j.*, d.nome_estudio, d.slug as dev_slug,
           GROUP_CONCAT(DISTINCT t.nome) as tags
    FROM jogo j
    LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id
    LEFT JOIN jogo_tag jt ON j.id = jt.jogo_id
    LEFT JOIN tag t ON jt.tag_id = t.id
    WHERE j.status = 'publicado'
    GROUP BY j.id
    ORDER BY j.total_vendas DESC
    LIMIT 8
");
$jogos_vendidos = $stmt->fetchAll();

// Buscar melhores avaliados
$stmt = $pdo->query("
    SELECT j.*, d.nome_estudio, d.slug as dev_slug,
           GROUP_CONCAT(DISTINCT t.nome) as tags
    FROM jogo j
    LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id
    LEFT JOIN jogo_tag jt ON j.id = jt.jogo_id
    LEFT JOIN tag t ON jt.tag_id = t.id
    WHERE j.status = 'publicado' AND j.total_avaliacoes >= 2
    GROUP BY j.id
    ORDER BY j.nota_media DESC, j.total_avaliacoes DESC
    LIMIT 8
");
$jogos_avaliados = $stmt->fetchAll();

// Buscar categorias populares
$stmt = $pdo->query("
    SELECT c.*, COUNT(jc.jogo_id) as total_jogos
    FROM categoria c
    LEFT JOIN jogo_categoria jc ON c.id = jc.categoria_id
    LEFT JOIN jogo j ON jc.jogo_id = j.id AND j.status = 'publicado'
    WHERE c.ativa = 1
    GROUP BY c.id
    ORDER BY total_jogos DESC
    LIMIT 6
");



$categorias_populares = $stmt->fetchAll();

require_once '../components/game-card.php';
require_once '../includes/header.php';
?>

<style>
    /* Banner Carousel Styles */
    .banner-carousel { margin-bottom: 40px; }
    .carousel { width: 100%; aspect-ratio: 16 / 6; background-color: #000; position: relative; border-radius: 12px; overflow: hidden; }
    .carousel-inner { position: relative; height: 100%; width: 100%; overflow: hidden; }
    .carousel-item { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; transition: opacity 0.6s ease-in-out; display: flex; align-items: center; justify-content: center; }
    .carousel-item.active { opacity: 1; z-index: 1; }
    .carousel-item img { width: 100%; height: 100%; object-fit: cover; }
    .carousel-caption { position: absolute; bottom: 0; left: 0; right: 0; padding: 40px; background: linear-gradient(to top, rgba(0,0,0,0.8) 0%, transparent 100%); z-index: 2; }
    .carousel-caption h3 { font-size: 32px; font-weight: 700; margin-bottom: 10px; color: white; }
    .carousel-caption p { font-size: 16px; color: rgba(255,255,255,0.9); }
    .carousel-control { position: absolute; top: 50%; transform: translateY(-50%); z-index: 3; background: rgba(0,0,0,0.5); color: white; border: none; width: 50px; height: 50px; border-radius: 50%; cursor: pointer; font-size: 20px; transition: all 0.3s; }
    .carousel-control:hover { background: var(--accent); }
    .carousel-control-prev { left: 20px; }
    .carousel-control-next { right: 20px; }
    .carousel-indicators { position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); z-index: 3; display: flex; gap: 10px; }
    .carousel-indicators button { width: 12px; height: 12px; border-radius: 50%; border: none; background: rgba(255,255,255,0.5); cursor: pointer; transition: 0.3s; }
    .carousel-indicators button.active { background: var(--accent); transform: scale(1.2); }

    /* Stats */
    .hero-stats { display: flex; gap: 30px; margin: 30px 0; flex-wrap: wrap; justify-content: center; }
    .hero-stat { text-align: center; padding: 20px; background: var(--bg-secondary); border-radius: 10px; min-width: 150px; border: 1px solid var(--border); }
    .hero-stat-value { font-size: 32px; font-weight: 700; color: var(--accent); display: block; }
    .hero-stat-label { font-size: 14px; color: var(--text-secondary); }

    /* Sections */
    .section { margin-bottom: 50px; }
    .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
    .section-title { font-size: 28px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
    .section-title i { color: var(--accent); }
    .section-link { color: var(--accent); font-weight: 500; display: flex; align-items: center; gap: 5px; transition: gap 0.3s; }
    .section-link:hover { gap: 10px; }

    /* Categories */
    .categories-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
    .category-card { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; padding: 30px; text-align: center; transition: all 0.3s; cursor: pointer; }
    .category-card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(76, 139, 245, 0.2); border-color: var(--accent); }
    .category-icon { font-size: 48px; color: var(--accent); margin-bottom: 15px; }
    .category-name { font-size: 20px; font-weight: 600; margin-bottom: 8px; }
    .category-count { font-size: 14px; color: var(--text-secondary); }

    /* Dev CTA */
    .dev-cta-banner { background: linear-gradient(135deg, #4C8BF5 0%, #6ba3ff 100%); border-radius: 15px; padding: 60px; text-align: center; margin: 60px 0; position: relative; overflow: hidden; }
    .dev-cta-banner::before, .dev-cta-banner::after { content: ''; position: absolute; width: 400px; height: 400px; background: rgba(255, 255, 255, 0.1); border-radius: 50%; }
    .dev-cta-banner::before { top: -50%; right: -10%; }
    .dev-cta-banner::after { bottom: -50%; left: -10%; }
    .dev-cta-content { position: relative; z-index: 1; }
    .dev-cta-icon { font-size: 64px; color: white; margin-bottom: 20px; }
    .dev-cta-title { font-size: 36px; font-weight: 700; color: white; margin-bottom: 15px; }
    .dev-cta-description { font-size: 18px; color: rgba(255, 255, 255, 0.9); margin-bottom: 30px; max-width: 600px; margin-left: auto; margin-right: auto; }
    .btn-cta { background: white; color: #4C8BF5; padding: 15px 40px; font-size: 18px; font-weight: 600; border-radius: 8px; border: none; cursor: pointer; transition: all 0.3s; display: inline-block; }
    .btn-cta:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2); color: #4C8BF5; }

    @media (max-width: 992px) { .categories-grid { grid-template-columns: repeat(2, 1fr); } .carousel { aspect-ratio: 16 / 9; } }
    @media (max-width: 768px) { .categories-grid { grid-template-columns: 1fr; } .dev-cta-banner { padding: 40px 20px; } .dev-cta-title { font-size: 28px; } .carousel-caption h3 { font-size: 24px; } }
</style>

<!-- Banner Carousel -->
<?php if (count($banners) > 0): ?>
<div class="container">
    <div class="banner-carousel">
        <div class="carousel">
            <div class="carousel-inner">
                <?php foreach ($banners as $index => $banner): ?>
                <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                    <img src="<?php echo SITE_URL . $banner['imagem_desktop']; ?>" 
                         alt="<?php echo sanitize($banner['titulo']); ?>">
                    <?php if ($banner['titulo'] || $banner['subtitulo']): ?>
                    <div class="carousel-caption">
                        <?php if ($banner['titulo']): ?>
                            <h3><?php echo sanitize($banner['titulo']); ?></h3>
                        <?php endif; ?>
                        <?php if ($banner['subtitulo']): ?>
                            <p><?php echo sanitize($banner['subtitulo']); ?></p>
                        <?php endif; ?>
                        <?php if ($banner['url_destino']): ?>
                            <a href="<?php echo $banner['url_destino']; ?>" class="btn btn-primary" style="margin-top: 15px;">
                                Ver Mais <i class="fas fa-arrow-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (count($banners) > 1): ?>
            <button class="carousel-control carousel-control-prev" onclick="moveSlide(-1)">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button class="carousel-control carousel-control-next" onclick="moveSlide(1)">
                <i class="fas fa-chevron-right"></i>
            </button>
            
            <div class="carousel-indicators">
                <?php foreach ($banners as $index => $banner): ?>
                <button onclick="goToSlide(<?php echo $index; ?>)" class="<?php echo $index === 0 ? 'active' : ''; ?>"></button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
let currentSlide = 0;
const totalSlides = <?php echo count($banners); ?>;

function showSlide(n) {
    const slides = document.querySelectorAll('.carousel-item');
    const indicators = document.querySelectorAll('.carousel-indicators button');
    
    if (n >= totalSlides) currentSlide = 0;
    if (n < 0) currentSlide = totalSlides - 1;
    
    slides.forEach(slide => slide.classList.remove('active'));
    indicators.forEach(ind => ind.classList.remove('active'));
    
    slides[currentSlide].classList.add('active');
    indicators[currentSlide].classList.add('active');
}

function moveSlide(n) {
    currentSlide += n;
    showSlide(currentSlide);
}

function goToSlide(n) {
    currentSlide = n;
    showSlide(currentSlide);
}

// Auto slide
<?php if (count($banners) > 1): ?>
setInterval(() => {
    currentSlide++;
    showSlide(currentSlide);
}, 5000);
<?php endif; ?>
</script>
<?php endif; ?>

<!-- Stats -->
<div class="container">
    <div class="hero-stats">
        <div class="hero-stat">
            <span class="hero-stat-value">
                <?php
                $stmt = $pdo->query("SELECT COUNT(*) as total FROM jogo WHERE status = 'publicado'");
                echo $stmt->fetch()['total'];
                ?>+
            </span>
            <span class="hero-stat-label">Jogos</span>
        </div>
        <div class="hero-stat">
            <span class="hero-stat-value">
                <?php
                $stmt = $pdo->query("SELECT COUNT(*) as total FROM desenvolvedor WHERE status = 'ativo'");
                echo $stmt->fetch()['total'];
                ?>+
            </span>
            <span class="hero-stat-label">Desenvolvedores</span>
        </div>
        <div class="hero-stat">
            <span class="hero-stat-value">
                <?php
                $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuario WHERE tipo = 'cliente'");
                echo $stmt->fetch()['total'];
                ?>+
            </span>
            <span class="hero-stat-label">Jogadores</span>
        </div>
    </div>
    
    <!-- Jogos em Promoção -->
    <?php if (count($jogos_promocao) > 0): ?>
    <section class="section">
        <div class="section-header">
            <h2 class="section-title"><i class="fas fa-fire"></i> Em Promoção</h2>
            <a href="<?php echo SITE_URL; ?>/pages/busca.php?promocao=1" class="section-link">
                Ver todas <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="grid grid-4">
            <?php foreach ($jogos_promocao as $jogo): renderGameCard($jogo, $pdo, $user_id); endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
    
    <!-- Lançamentos Recentes -->
    <?php if (count($jogos_lancamentos) > 0): ?>
    <section class="section">
        <div class="section-header">
            <h2 class="section-title"><i class="fas fa-rocket"></i> Lançamentos Recentes</h2>
            <a href="<?php echo SITE_URL; ?>/pages/busca.php?ordem=recente" class="section-link">
                Ver todos <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="grid grid-4">
            <?php foreach ($jogos_lancamentos as $jogo): renderGameCard($jogo, $pdo, $user_id); endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
    
    <!-- Mais Vendidos -->
    <?php if (count($jogos_vendidos) > 0): ?>
    <section class="section">
        <div class="section-header">
            <h2 class="section-title"><i class="fas fa-trophy"></i> Mais Vendidos</h2>
            <a href="<?php echo SITE_URL; ?>/pages/busca.php?ordem=vendas" class="section-link">
                Ver todos <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="grid grid-4">
            <?php foreach ($jogos_vendidos as $jogo): renderGameCard($jogo, $pdo, $user_id); endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
    
    <!-- Melhores Avaliados -->
    <?php if (count($jogos_avaliados) > 0): ?>
    <section class="section">
        <div class="section-header">
            <h2 class="section-title"><i class="fas fa-star"></i> Melhores Avaliados</h2>
            <a href="<?php echo SITE_URL; ?>/pages/busca.php?ordem=nota" class="section-link">
                Ver todos <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="grid grid-4">
            <?php foreach ($jogos_avaliados as $jogo): renderGameCard($jogo, $pdo, $user_id); endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
    
    <!-- Categorias Populares -->
    <?php if (count($categorias_populares) > 0): ?>
    <section class="section">
        <div class="section-header">
            <h2 class="section-title"><i class="fas fa-th"></i> Categorias Populares</h2>
        </div>
        <div class="categories-grid">
            <?php foreach ($categorias_populares as $cat): ?>
            <a href="<?php echo SITE_URL; ?>/pages/categoria.php?slug=<?php echo $cat['slug']; ?>" class="category-card">
                <div class="category-icon"><i class="fas fa-<?php echo $cat['icone']; ?>"></i></div>
                <div class="category-name"><?php echo sanitize($cat['nome']); ?></div>
                <div class="category-count"><?php echo $cat['total_jogos']; ?> jogos</div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
    
    <!-- Developer CTA Banner -->
    <section class="dev-cta-banner">
        <div class="dev-cta-content">
            <div class="dev-cta-icon"><i class="fas fa-code"></i></div>
            <h2 class="dev-cta-title">Você é um Desenvolvedor?</h2>
            <p class="dev-cta-description">
                Publique seus jogos na maior plataforma indie do Brasil! 
                Alcance milhares de jogadores e transforme sua paixão em negócio.
            </p>
            <a href="<?php echo SITE_URL; ?>/user/seja-dev.php" class="btn-cta">
                <i class="fas fa-rocket"></i> Seja um Desenvolvedor
            </a>
        </div>
    </section>
</div>

<?php require_once '../includes/footer.php'; ?>