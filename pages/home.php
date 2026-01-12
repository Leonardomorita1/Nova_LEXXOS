<?php
require_once '../config/config.php';
require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();
$user_id = isLoggedIn() ? $_SESSION['user_id'] : null;

// Buscar banners ativos
$stmt = $pdo->query("SELECT * FROM banner WHERE ativo = 1 ORDER BY ordem LIMIT 5");
$banners = $stmt->fetchAll();

// Jogos em destaque
$stmt = $pdo->prepare("
    SELECT j.* 
    FROM jogo j
    WHERE j.status = 'publicado' AND j.destaque = 1
    ORDER BY j.criado_em DESC
    LIMIT 10
");
$stmt->execute();
$jogos_destaque = $stmt->fetchAll();

// Lançamentos recentes
$stmt = $pdo->prepare("
    SELECT j.* 
    FROM jogo j
    WHERE j.status = 'publicado'
    ORDER BY j.publicado_em DESC
    LIMIT 10
");
$stmt->execute();
$lancamentos = $stmt->fetchAll();

// Jogos em promoção
$stmt = $pdo->prepare("
    SELECT j.* 
    FROM jogo j
    WHERE j.status = 'publicado' AND j.em_promocao = 1
    ORDER BY j.criado_em DESC
    LIMIT 10
");
$stmt->execute();
$promocoes = $stmt->fetchAll();

$page_title = 'Home - ' . SITE_NAME;
require_once '../includes/header.php';
require_once '../components/game-card.php';
?>

<style>
.carousel {
    width: 100%;
    max-width: 1400px;
    margin: 20px auto;
    border-radius: 12px;
    overflow: hidden;
}
.carousel-inner {
    position: relative;
    width: 100%;
    overflow: hidden;
}
.carousel-item {
    position: relative;
    display: none;
    float: left;
    width: 100%;
    margin-right: -100%;
    backface-visibility: hidden;
    transition: transform 0.6s ease-in-out;
}
.carousel-item.active {
    display: block;
}
.carousel-item img {
    width: 100%;
    height: 400px;
    object-fit: cover;
}
.carousel-caption {
    position: absolute;
    bottom: 40px;
    left: 50px;
    right: 50px;
    text-align: left;
    color: white;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.8);
}
.carousel-caption h2 {
    font-size: 36px;
    font-weight: 700;
    margin-bottom: 10px;
}
.carousel-caption p {
    font-size: 18px;
    margin-bottom: 20px;
}
.carousel-control-prev,
.carousel-control-next {
    position: absolute;
    top: 0;
    bottom: 0;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 15%;
    padding: 0;
    color: #fff;
    text-align: center;
    background: none;
    border: 0;
    opacity: 0.5;
    transition: opacity 0.3s ease;
    cursor: pointer;
}
.carousel-control-prev:hover,
.carousel-control-next:hover {
    opacity: 0.9;
}
.carousel-control-prev {
    left: 0;
}
.carousel-control-next {
    right: 0;
}
.carousel-control-prev-icon,
.carousel-control-next-icon {
    display: inline-block;
    width: 40px;
    height: 40px;
    background-size: 100% 100%;
}
.carousel-control-prev-icon {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23fff'%3e%3cpath d='M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0'/%3e%3c/svg%3e");
}
.carousel-control-next-icon {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23fff'%3e%3cpath d='M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708'/%3e%3c/svg%3e");
}
.carousel-indicators {
    position: absolute;
    right: 0;
    bottom: 20px;
    left: 0;
    z-index: 2;
    display: flex;
    justify-content: center;
    padding: 0;
    margin: 0;
    list-style: none;
}
.carousel-indicators button {
    box-sizing: content-box;
    flex: 0 1 auto;
    width: 30px;
    height: 3px;
    padding: 0;
    margin-right: 3px;
    margin-left: 3px;
    text-indent: -999px;
    cursor: pointer;
    background-color: #fff;
    background-clip: padding-box;
    border: 0;
    border-top: 10px solid transparent;
    border-bottom: 10px solid transparent;
    opacity: 0.5;
    transition: opacity 0.6s ease;
}
.carousel-indicators button.active {
    opacity: 1;
}
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 40px 0 20px;
}
.section-title {
    font-size: 24px;
    font-weight: 700;
}
@media (max-width: 768px) {
    .carousel-item img {
        height: 250px;
    }
    .carousel-caption {
        bottom: 20px;
        left: 20px;
        right: 20px;
    }
    .carousel-caption h2 {
        font-size: 20px;
    }
    .carousel-caption p {
        font-size: 14px;
    }
}
</style>

<div class="container" style="padding: 20px 0;">
    
    <?php if (!empty($banners)): ?>
    <!-- Banner Carousel -->
    <div id="mainCarousel" class="carousel">
        <div class="carousel-indicators">
            <?php foreach ($banners as $i => $banner): ?>
            <button type="button" data-bs-target="#mainCarousel" data-bs-slide-to="<?= $i ?>" 
                    class="<?= $i == 0 ? 'active' : '' ?>" aria-label="Slide <?= $i + 1 ?>"></button>
            <?php endforeach; ?>
        </div>
        
        <div class="carousel-inner">
            <?php foreach ($banners as $i => $banner): ?>
            <div class="carousel-item <?= $i == 0 ? 'active' : '' ?>">
                <img src="<?= $banner['imagem_desktop'] ?>" alt="<?= sanitize($banner['titulo']) ?>">
                <?php if ($banner['titulo']): ?>
                <div class="carousel-caption">
                    <h2><?= sanitize($banner['titulo']) ?></h2>
                    <?php if ($banner['subtitulo']): ?>
                    <p><?= sanitize($banner['subtitulo']) ?></p>
                    <?php endif; ?>
                    <?php if ($banner['url_destino']): ?>
                    <a href="<?= $banner['url_destino'] ?>" class="btn btn-primary btn-lg">
                        Saiba Mais
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <button class="carousel-control-prev" type="button" data-bs-target="#mainCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Anterior</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#mainCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Próximo</span>
        </button>
    </div>
    <?php endif; ?>

    <!-- Jogos em Destaque -->
    <?php if (!empty($jogos_destaque)): ?>
    <div class="section-header">
        <h2 class="section-title"><i class="fas fa-star"></i> Em Destaque</h2>
        <a href="<?= SITE_URL ?>/pages/busca.php?destaque=1" style="color: var(--accent);">Ver todos</a>
    </div>
    <div class="grid grid-5">
        <?php foreach ($jogos_destaque as $jogo): ?>
            <?php renderGameCard($jogo, $pdo, $user_id); ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Lançamentos -->
    <?php if (!empty($lancamentos)): ?>
    <div class="section-header">
        <h2 class="section-title"><i class="fas fa-fire"></i> Lançamentos Recentes</h2>
        <a href="<?= SITE_URL ?>/pages/busca.php?ordem=recentes" style="color: var(--accent);">Ver todos</a>
    </div>
    <div class="grid grid-5">
        <?php foreach ($lancamentos as $jogo): ?>
            <?php renderGameCard($jogo, $pdo, $user_id); ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Promoções -->
    <?php if (!empty($promocoes)): ?>
    <div class="section-header">
        <h2 class="section-title"><i class="fas fa-tags"></i> Promoções</h2>
        <a href="<?= SITE_URL ?>/pages/busca.php?promocao=1" style="color: var(--accent);">Ver todos</a>
    </div>
    <div class="grid grid-5">
        <?php foreach ($promocoes as $jogo): ?>
            <?php renderGameCard($jogo, $pdo, $user_id); ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<script>
// Carousel manual sem Bootstrap
document.addEventListener('DOMContentLoaded', function() {
    const carousel = document.getElementById('mainCarousel');
    if (!carousel) return;
    
    const items = carousel.querySelectorAll('.carousel-item');
    const indicators = carousel.querySelectorAll('.carousel-indicators button');
    const prevBtn = carousel.querySelector('.carousel-control-prev');
    const nextBtn = carousel.querySelector('.carousel-control-next');
    
    let currentIndex = 0;
    
    function showSlide(index) {
        items.forEach(item => item.classList.remove('active'));
        indicators.forEach(ind => ind.classList.remove('active'));
        
        if (index >= items.length) currentIndex = 0;
        else if (index < 0) currentIndex = items.length - 1;
        else currentIndex = index;
        
        items[currentIndex].classList.add('active');
        indicators[currentIndex].classList.add('active');
    }
    
    function nextSlide() {
        showSlide(currentIndex + 1);
    }
    
    function prevSlide() {
        showSlide(currentIndex - 1);
    }
    
    nextBtn?.addEventListener('click', nextSlide);
    prevBtn?.addEventListener('click', prevSlide);
    
    indicators.forEach((indicator, index) => {
        indicator.addEventListener('click', () => showSlide(index));
    });
    
    // Auto-play
    setInterval(nextSlide, 5000);
});
</script>

<?php require_once '../includes/footer.php'; ?>