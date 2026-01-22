<?php
// pages/home.php - Versão Final Otimizada
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../components/game-card.php';

$database = new Database();
$pdo = $database->getConnection();
$user_id = isLoggedIn() ? $_SESSION['user_id'] : null;

// --- HELPER ---
if (!function_exists('formatPrice')) {
    function formatPrice($centavos)
    {
        return 'R$ ' . number_format($centavos / 100, 2, ',', '.');
    }
}

// --- PRE-LOAD USER DATA ---
$meus_jogos = $minha_wishlist = $meu_carrinho = [];
if ($user_id) {
    try {
        $meus_jogos = $pdo->prepare("SELECT jogo_id FROM biblioteca WHERE usuario_id = ?");
        $meus_jogos->execute([$user_id]);
        $meus_jogos = $meus_jogos->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $minha_wishlist = $pdo->prepare("SELECT jogo_id FROM lista_desejos WHERE usuario_id = ?");
        $minha_wishlist->execute([$user_id]);
        $minha_wishlist = $minha_wishlist->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $meu_carrinho = $pdo->prepare("SELECT jogo_id FROM carrinho WHERE usuario_id = ?");
        $meu_carrinho->execute([$user_id]);
        $meu_carrinho = $meu_carrinho->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Exception $e) {
    }
}

// --- QUERIES ---
// Banners Promocionais
$banners = $pdo->query("SELECT * FROM banner WHERE ativo = 1 ORDER BY ordem LIMIT 5")->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Jogos Destaque (Hero)
$jogos_destaque = $pdo->query("
    SELECT j.*, d.nome_estudio FROM jogo j 
    LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id 
    WHERE j.status = 'publicado' AND j.destaque = 1 
    ORDER BY j.criado_em DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Categorias com Top 3 jogos
$categorias = $pdo->query("
    SELECT c.*, (SELECT COUNT(*) FROM jogo_categoria jc 
    JOIN jogo j ON jc.jogo_id = j.id WHERE jc.categoria_id = c.id AND j.status = 'publicado') as total_jogos 
    FROM categoria c WHERE c.ativa = 1 ORDER BY c.ordem, c.nome LIMIT 12
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

foreach ($categorias as &$cat) {
    $stmt = $pdo->prepare("
        SELECT j.imagem_capa, j.titulo FROM jogo j
        JOIN jogo_categoria jc ON j.id = jc.jogo_id
        WHERE jc.categoria_id = ? AND j.status = 'publicado'
        ORDER BY j.total_vendas DESC, j.nota_media DESC LIMIT 3
    ");
    $stmt->execute([$cat['id']]);
    $cat['top_jogos'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
unset($cat);

// Lançamentos (5 para o showcase)
$lancamentos_showcase = $pdo->query("
    SELECT j.imagem_capa, j.titulo, j.slug FROM jogo j 
    WHERE j.status = 'publicado' 
    ORDER BY j.publicado_em DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Outras seções
$promocoes = $pdo->query("
    SELECT j.*, d.nome_estudio FROM jogo j 
    LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id 
    WHERE j.status = 'publicado' AND j.em_promocao = 1 AND j.preco_promocional_centavos IS NOT NULL
    ORDER BY (j.preco_centavos - j.preco_promocional_centavos) DESC LIMIT 12
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$lancamentos = $pdo->query("
    SELECT j.*, d.nome_estudio FROM jogo j 
    LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id 
    WHERE j.status = 'publicado' ORDER BY j.publicado_em DESC LIMIT 12
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$populares = $pdo->query("
    SELECT j.*, d.nome_estudio FROM jogo j 
    LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id 
    WHERE j.status = 'publicado' ORDER BY j.total_vendas DESC, j.nota_media DESC LIMIT 12
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$gratuitos = $pdo->query("
    SELECT j.*, d.nome_estudio FROM jogo j 
    LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id 
    WHERE j.status = 'publicado' AND j.preco_centavos = 0 ORDER BY j.total_vendas DESC LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$melhores = $pdo->query("
    SELECT j.*, d.nome_estudio FROM jogo j 
    LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id 
    WHERE j.status = 'publicado' AND j.nota_media >= 4
    ORDER BY j.nota_media DESC LIMIT 12
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$desenvolvedores = $pdo->query("
    SELECT d.*, COUNT(j.id) as total_jogos FROM desenvolvedor d 
    JOIN jogo j ON d.id = j.desenvolvedor_id AND j.status = 'publicado'
    WHERE d.verificado = 1 AND d.status = 'ativo'
    GROUP BY d.id ORDER BY total_jogos DESC LIMIT 12
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$page_title = 'Home - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<style>
    /* ============================================
   HOME PAGE STYLES
   ============================================ */
    .home-wrapper {
        background: var(--bg-primary);
        min-height: 100vh;
    }

    .home-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 24px 80px;
    }

    /* ============================================
   PROMO BANNER - PLAYSTATION STYLE (10:3)
   ============================================ */
    .promo-banner-section {
        width: 100%;
        margin-bottom: 32px;
    }

    .promo-banner-slider {
        position: relative;
        overflow: hidden;
    }

    .promo-banner-track {
        display: flex;
        transition: transform 0.5s ease;
    }

    .promo-banner-slide {
        min-width: 100%;
        flex-shrink: 0;
    }

    .promo-banner-image {
        position: relative;
        width: 100%;
        aspect-ratio: 10 / 3;
        overflow: hidden;
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
    }

    .promo-banner-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    

    /* Fallback background patterns */
    .promo-banner-image.no-image {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .promo-banner-image.no-image::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(circle at 20% 50%, rgba(0, 174, 255, 0.15) 0%, transparent 50%),
            radial-gradient(circle at 80% 50%, rgba(255, 107, 107, 0.15) 0%, transparent 50%),
            radial-gradient(circle at 50% 80%, rgba(145, 71, 255, 0.1) 0%, transparent 50%);
    }

    .promo-banner-image.no-image .promo-fallback-icon {
        font-size: 80px;
        color: rgba(255, 255, 255, 0.1);
        z-index: 1;
    }

    .promo-banner-info {
        padding: 20px 32px 32px;
        background: var(--bg-primary);
    }

    .promo-banner-tag {
        display: inline-block;
        background: var(--accent);
        color: #000;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        padding: 4px 10px;
        border-radius: 4px;
        margin-bottom: 12px;
    }

    .promo-banner-title {
        font-size: 28px;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0 0 8px;
    }

    .promo-banner-desc {
        font-size: 14px;
        color: var(--text-secondary);
        margin: 0 0 20px;
        max-width: 600px;
    }

    .promo-banner-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: var(--accent);
        color: #000;
        padding: 12px 24px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s;
    }

    .promo-banner-btn:hover {
        filter: brightness(1.1);
        transform: translateY(-2px);
    }

    /* Banner Navigation */
    .promo-banner-nav {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 48px;
        height: 48px;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(8px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 10;
        transition: all 0.2s;
    }

    .promo-banner-nav:hover {
        background: rgba(0, 0, 0, 0.8);
        border-color: var(--accent);
    }

    .promo-banner-nav.prev {
        left: 24px;
    }

    .promo-banner-nav.next {
        right: 24px;
    }

    .promo-banner-dots {
        display: flex;
        justify-content: center;
        gap: 8px;
        padding: 16px 0;
    }

    .promo-banner-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--border);
        cursor: pointer;
        transition: all 0.2s;
    }

    .promo-banner-dot.active {
        background: var(--accent);
        width: 24px;
        border-radius: 4px;
    }

    /* ============================================
   HERO - EPIC GAMES STYLE
   ============================================ */
    .hero-section {
        display: grid;
        grid-template-columns: 1fr 320px;
        gap: 16px;
        margin-bottom: 48px;
    }

    .hero-main {
        position: relative;
        border-radius: 12px;
        overflow: hidden;
        aspect-ratio: 16 / 9;
        background: var(--bg-secondary);
    }

    .hero-slide {
        position: absolute;
        inset: 0;
        opacity: 0;
        visibility: hidden;
        transition: all 0.5s ease;
    }

    .hero-slide.active {
        opacity: 1;
        visibility: visible;
    }

    .hero-bg {
        position: absolute;
        inset: 0;
    }

    .hero-bg img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .hero-bg::after {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(to top, rgba(0, 0, 0, 0.95) 0%, rgba(0, 0, 0, 0.3) 50%, transparent 100%);
    }

    .hero-content {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        padding: 32px;
        z-index: 2;
    }

    .hero-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: var(--accent);
        color: #000;
        padding: 6px 12px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        margin-bottom: 12px;
    }

    .hero-badge.promo {
        background: var(--success);
    }

    .hero-title {
        font-size: 2rem;
        font-weight: 700;
        color: #fff;
        margin: 0 0 8px;
        line-height: 1.2;
    }

    .hero-dev {
        font-size: 13px;
        color: rgba(255, 255, 255, 0.6);
        margin-bottom: 16px;
    }

    .hero-price-row {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
    }

    .hero-discount {
        background: var(--success);
        color: #fff;
        padding: 6px 10px;
        border-radius: 4px;
        font-size: 14px;
        font-weight: 700;
    }

    .hero-old-price {
        color: rgba(255, 255, 255, 0.5);
        text-decoration: line-through;
        font-size: 14px;
    }

    .hero-price {
        font-size: 24px;
        font-weight: 700;
        color: #fff;
    }

    .hero-price.free {
        color: var(--success);
    }

    .hero-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: var(--accent);
        color: #000;
        padding: 12px 24px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s;
    }

    .hero-btn:hover {
        filter: brightness(1.1);
    }

    /* Hero Sidebar */
    .hero-sidebar {
        display: flex;
        flex-direction: column;
        gap: 8px;
        background: var(--bg-secondary);
        border-radius: 12px;
        padding: 12px;
        overflow-y: auto;
    }

    .hero-sidebar-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
        border: 2px solid transparent;
    }

    .hero-sidebar-item:hover {
        background: var(--bg-primary);
    }

    .hero-sidebar-item.active {
        background: var(--bg-primary);
        border-color: var(--accent);
    }

    .hero-sidebar-thumb {
        width: 56px;
        height: 56px;
        border-radius: 6px;
        overflow: hidden;
        flex-shrink: 0;
    }

    .hero-sidebar-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .hero-sidebar-title {
        font-size: 13px;
        font-weight: 600;
        color: var(--text-primary);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .hero-sidebar-price {
        font-size: 12px;
        color: var(--text-secondary);
        margin-top: 4px;
    }

    .hero-sidebar-price .discount {
        color: var(--success);
        font-weight: 600;
    }

    /* Hero Arrows */
    .hero-arrows {
        position: absolute;
        top: 50%;
        left: 16px;
        right: 16px;
        transform: translateY(-50%);
        display: flex;
        justify-content: space-between;
        pointer-events: none;
        z-index: 10;
    }

    .hero-arrow {
        width: 44px;
        height: 44px;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(8px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        pointer-events: auto;
        transition: all 0.2s;
    }

    .hero-arrow:hover {
        background: rgba(0, 0, 0, 0.8);
        border-color: var(--accent);
    }

    /* ============================================
   SECTION HEADERS
   ============================================ */
    .section {
        margin-bottom: 48px;
    }

    .section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 20px;
    }

    .section-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 20px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    .section-controls {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .section-link {
        display: flex;
        align-items: center;
        gap: 6px;
        color: var(--accent);
        font-size: 13px;
        font-weight: 500;
        text-decoration: none;
        transition: opacity 0.2s;
    }

    .section-link:hover {
        opacity: 0.8;
    }

    .section-nav {
        display: flex;
        gap: 8px;
    }

    .section-nav-btn {
        width: 36px;
        height: 36px;
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 50%;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
    }

    .section-nav-btn:hover {
        border-color: var(--accent);
        color: var(--accent);
    }

    /* ============================================
   CATEGORY CARDS - OVERLAP EFFECT (CAROUSEL)
   ============================================ */
    .categories-carousel {
        position: relative;
    }

    .categories-track {
        display: flex;
        gap: 16px;
        overflow-x: auto;
        scroll-behavior: smooth;
        scrollbar-width: none;
        padding-bottom: 8px;
    }

    .categories-track::-webkit-scrollbar {
        display: none;
    }

    .category-card {
        flex: 0 0 280px;
        min-width: 280px;
        position: relative;
        background: var(--bg-secondary);
        border-radius: 12px;
        overflow: hidden;
        text-decoration: none;
        transition: all 0.3s ease;
        border: 1px solid var(--border);
    }

    .category-card:hover {
        transform: translateY(-4px);
        border-color: var(--accent);
        box-shadow: 0 8px 32px rgba(0, 174, 255, 0.15);
    }

    /* Overlap Images Container */
    .category-images {
        position: relative;
        height: 140px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        overflow: hidden;
    }

    /* Side Images (Left & Right) */
    .cat-img-side {
        position: absolute;
        width: 70px;
        height: 90px;
        border-radius: 8px;
        overflow: hidden;
        filter: blur(2px) brightness(0.6);
        transition: all 0.3s ease;
        z-index: 1;
    }

    .cat-img-side.left {
        left: 25px;
        transform: rotate(-8deg) scale(0.9);
    }

    .cat-img-side.right {
        right: 25px;
        transform: rotate(8deg) scale(0.9);
    }

    .cat-img-side img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    /* Center Image (Main) */
    .cat-img-center {
        position: relative;
        width: 90px;
        height: 120px;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5);
        z-index: 2;
        transition: all 0.3s ease;
    }

    .cat-img-center img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .category-card:hover .cat-img-center {
        transform: scale(1.05);
        box-shadow: 0 12px 32px rgba(0, 174, 255, 0.3);
    }

    .category-card:hover .cat-img-side {
        filter: blur(1px) brightness(0.7);
    }

    .category-card:hover .cat-img-side.left {
        transform: rotate(-10deg) scale(0.95) translateX(-5px);
    }

    .category-card:hover .cat-img-side.right {
        transform: rotate(10deg) scale(0.95) translateX(5px);
    }

    /* Placeholder for missing images */
    .cat-img-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--bg-primary);
        color: var(--text-secondary);
        font-size: 24px;
    }

    /* Category Info */
    .category-info {
        padding: 16px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-top: 1px solid var(--border);
    }

    .category-name {
        font-size: 15px;
        font-weight: 600;
        color: var(--accent);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .category-name i {
        font-size: 14px;
        opacity: 0.8;
    }

    .category-count {
        font-size: 12px;
        color: var(--text-secondary);
        background: var(--bg-primary);
        padding: 4px 10px;
        border-radius: 12px;
    }

    /* ============================================
   SHOWCASE CARD - STEAM PERSPECTIVE STYLE
   ============================================ */
    .showcase-cards-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 48px;
    }

    .showcase-card {
        position: relative;
        background: var(--bg-secondary);
        border-radius: 16px;
        overflow: hidden;
        border: 1px solid var(--border);
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .showcase-card:hover {
        border-color: var(--accent);
        transform: translateY(-4px);
        box-shadow: 0 12px 40px rgba(0, 174, 255, 0.15);
    }

    /* Steam Perspective Images */
    .showcase-images {
        position: relative;
        height: 200px;
        perspective: 1000px;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .showcase-stack {
        position: relative;
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .showcase-img {
        position: absolute;
        width: 100px;
        height: 140px;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
        transition: all 0.4s ease;
    }

    .showcase-img img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    /* 5 cards in perspective */
    .showcase-img:nth-child(1) {
        transform: translateX(-140px) rotateY(25deg) scale(0.75);
        z-index: 1;
        filter: brightness(0.5);
    }

    .showcase-img:nth-child(2) {
        transform: translateX(-70px) rotateY(15deg) scale(0.85);
        z-index: 2;
        filter: brightness(0.7);
    }

    .showcase-img:nth-child(3) {
        transform: translateX(0) rotateY(0deg) scale(1);
        z-index: 3;
        box-shadow: 0 15px 40px rgba(0, 174, 255, 0.3);
    }

    .showcase-img:nth-child(4) {
        transform: translateX(70px) rotateY(-15deg) scale(0.85);
        z-index: 2;
        filter: brightness(0.7);
    }

    .showcase-img:nth-child(5) {
        transform: translateX(140px) rotateY(-25deg) scale(0.75);
        z-index: 1;
        filter: brightness(0.5);
    }

    /* Hover effect */
    .showcase-card:hover .showcase-img:nth-child(1) {
        transform: translateX(-150px) rotateY(30deg) scale(0.7);
    }

    .showcase-card:hover .showcase-img:nth-child(2) {
        transform: translateX(-75px) rotateY(18deg) scale(0.82);
    }

    .showcase-card:hover .showcase-img:nth-child(3) {
        transform: translateX(0) rotateY(0deg) scale(1.05);
    }

    .showcase-card:hover .showcase-img:nth-child(4) {
        transform: translateX(75px) rotateY(-18deg) scale(0.82);
    }

    .showcase-card:hover .showcase-img:nth-child(5) {
        transform: translateX(150px) rotateY(-30deg) scale(0.7);
    }

    /* Showcase Info */
    .showcase-info {
        padding: 20px 24px;
        border-top: 1px solid var(--border);
    }

    .showcase-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        padding: 5px 10px;
        border-radius: 4px;
        margin-bottom: 10px;
    }

    .showcase-tag.new {
        background: var(--accent);
        color: #000;
    }

    .showcase-tag.sale {
        background: var(--success);
        color: #fff;
    }

    .showcase-title {
        font-size: 18px;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0 0 6px;
    }

    .showcase-desc {
        font-size: 13px;
        color: var(--text-secondary);
        margin: 0;
    }

    /* Showcase Sale - Different background when no images */
    .showcase-card.sale-card .showcase-images {
        background: linear-gradient(135deg, #1a472a 0%, #0d2818 50%, #1a472a 100%);
    }

    .showcase-card.sale-card .showcase-images::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(circle at 30% 40%, rgba(40, 167, 69, 0.2) 0%, transparent 50%),
            radial-gradient(circle at 70% 60%, rgba(40, 167, 69, 0.15) 0%, transparent 50%);
        z-index: 0;
    }

    .showcase-card.sale-card:hover {
        border-color: var(--success);
        box-shadow: 0 12px 40px rgba(40, 167, 69, 0.2);
    }

    /* ============================================
   GAMES CAROUSEL
   ============================================ */
    .games-carousel {
        position: relative;
    }

    .games-carousel-track {
        display: flex;
        gap: 16px;
        overflow-x: auto;
        scroll-behavior: smooth;
        scrollbar-width: none;
        padding-bottom: 8px;
    }

    .games-carousel-track::-webkit-scrollbar {
        display: none;
    }

    .games-carousel-track .ps-card {
        flex: 0 0 180px;
        min-width: 180px;
    }

    /* ============================================
   DEVELOPERS CAROUSEL
   ============================================ */
    .devs-carousel {
        position: relative;
    }

    .devs-track {
        display: flex;
        gap: 16px;
        overflow-x: auto;
        scroll-behavior: smooth;
        scrollbar-width: none;
        padding-bottom: 8px;
    }

    .devs-track::-webkit-scrollbar {
        display: none;
    }

    .dev-card {
        flex: 0 0 200px;
        min-width: 200px;
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 24px 16px;
        text-align: center;
        text-decoration: none;
        transition: all 0.2s;
    }

    .dev-card:hover {
        border-color: var(--accent);
        transform: translateY(-4px);
    }

    .dev-logo {
        width: 60px;
        height: 60px;
        border-radius: 14px;
        object-fit: cover;
        margin: 0 auto 12px;
        background: var(--bg-primary);
    }

    .dev-name {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }

    .dev-name i {
        color: var(--accent);
        font-size: 11px;
    }

    .dev-count {
        font-size: 12px;
        color: var(--text-secondary);
    }

    /* ============================================
   FREE GAMES
   ============================================ */
    .free-games-section {
        background: linear-gradient(135deg, rgba(0, 174, 255, 0.1), transparent);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 28px;
        margin-bottom: 48px;
    }

    .free-games-section .section-title {
        color: var(--accent);
    }

    .free-games-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-top: 20px;
    }

    /* ============================================
   RESPONSIVE
   ============================================ */
    @media (max-width: 1200px) {
        .hero-section {
            grid-template-columns: 1fr 280px;
        }

        .showcase-cards-row {
            grid-template-columns: 1fr;
        }

        .free-games-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (max-width: 1024px) {
        .hero-section {
            grid-template-columns: 1fr;
        }

        .hero-sidebar {
            display: none;
        }

        .hero-title {
            font-size: 1.6rem;
        }

        .free-games-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .home-container {
            padding: 0 16px 60px;
        }

        .promo-banner-info {
            padding: 16px 20px 24px;
        }

        .promo-banner-title {
            font-size: 20px;
        }

        .promo-banner-nav {
            display: none;
        }

        .hero-main {
            aspect-ratio: 4 / 3;
            border-radius: 10px;
        }

        .hero-content {
            padding: 20px;
        }

        .hero-title {
            font-size: 1.3rem;
        }

        .hero-dev {
            display: none;
        }

        .hero-arrows {
            display: none;
        }

        .section-title {
            font-size: 17px;
        }

        .section-nav {
            display: none;
        }

        .category-card {
            flex: 0 0 240px;
            min-width: 240px;
        }

        .category-images {
            height: 120px;
        }

        .showcase-images {
            height: 160px;
        }

        .showcase-img {
            width: 80px;
            height: 110px;
        }

        .showcase-img:nth-child(1) {
            transform: translateX(-100px) rotateY(25deg) scale(0.75);
        }

        .showcase-img:nth-child(2) {
            transform: translateX(-50px) rotateY(15deg) scale(0.85);
        }

        .showcase-img:nth-child(4) {
            transform: translateX(50px) rotateY(-15deg) scale(0.85);
        }

        .showcase-img:nth-child(5) {
            transform: translateX(100px) rotateY(-25deg) scale(0.75);
        }

        .games-carousel-track .ps-card {
            flex: 0 0 150px;
            min-width: 150px;
        }

        .dev-card {
            flex: 0 0 170px;
            min-width: 170px;
        }

        .free-games-section {
            padding: 20px;
        }

        .free-games-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
    }

    @media (max-width: 480px) {
        .promo-banner-title {
            font-size: 18px;
        }

        .promo-banner-btn {
            padding: 10px 18px;
            font-size: 13px;
        }

        .hero-main {
            aspect-ratio: 1 / 1;
        }

        .hero-title {
            font-size: 1.1rem;
        }

        .hero-price {
            font-size: 20px;
        }

        .category-card {
            flex: 0 0 220px;
            min-width: 220px;
        }

        .showcase-images {
            height: 140px;
        }

        .showcase-img {
            width: 65px;
            height: 90px;
        }

        .showcase-img:nth-child(1) {
            transform: translateX(-80px) rotateY(25deg) scale(0.75);
        }

        .showcase-img:nth-child(2) {
            transform: translateX(-40px) rotateY(15deg) scale(0.85);
        }

        .showcase-img:nth-child(4) {
            transform: translateX(40px) rotateY(-15deg) scale(0.85);
        }

        .showcase-img:nth-child(5) {
            transform: translateX(80px) rotateY(-25deg) scale(0.75);
        }
    }
</style>

<div class="home-wrapper">



    <div class="home-container">

        <?php if (!empty($banners)): ?>
            <section class="promo-banner-section">
                <div class="promo-banner-slider">
                    <div class="promo-banner-track" id="promoBannerTrack">
                        <?php foreach ($banners as $i => $banner):
                            $hasImage = !empty($banner['imagem_desktop']);
                        ?>
                            <div class="promo-banner-slide">
                                <div class="promo-banner-image <?= !$hasImage ? 'no-image' : '' ?>">
                                    <?php if ($hasImage): ?>
                                        <img src="<?= htmlspecialchars($banner['imagem_desktop']) ?>"
                                            alt="<?= htmlspecialchars($banner['titulo'] ?? '') ?>"
                                            loading="<?= $i === 0 ? 'eager' : 'lazy' ?>" object-fit="cover">
                                    <?php else: ?>
                                        <i class="fas fa-tags promo-fallback-icon"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="promo-banner-info">
                                    
                                    <h2 class="promo-banner-title"><?= htmlspecialchars($banner['titulo'] ?? 'Oferta Especial') ?></h2>
                                    <p class="promo-banner-desc"><?= htmlspecialchars($banner['subtitulo'] ?? 'Confira as melhores ofertas') ?></p>
                                    <a href="<?= htmlspecialchars($banner['url_destino'] ?? '#') ?>" class="promo-banner-btn">
                                        Ver mais <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (count($banners) > 1): ?>
                        <button class="promo-banner-nav prev" onclick="promoBanner.prev()">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button class="promo-banner-nav next" onclick="promoBanner.next()">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    <?php endif; ?>
                </div>

                <?php if (count($banners) > 1): ?>
                    <div class="promo-banner-dots">
                        <?php for ($i = 0; $i < count($banners); $i++): ?>
                            <div class="promo-banner-dot <?= $i === 0 ? 'active' : '' ?>" onclick="promoBanner.goTo(<?= $i ?>)"></div>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <!-- HERO - Epic Games Style -->
        <?php if (!empty($jogos_destaque)): ?>
            <section class="hero-section">
                <div class="hero-main">
                    <?php foreach ($jogos_destaque as $i => $jogo):
                        $img = $jogo['imagem_banner'] ?: $jogo['imagem_capa'];
                        $preco = $jogo['preco_centavos'] ?? 0;
                        $preco_promo = $jogo['preco_promocional_centavos'] ?? null;
                        $em_promocao = $jogo['em_promocao'] ?? false;
                        $preco_final = $em_promocao && $preco_promo ? $preco_promo : $preco;
                        $desconto = ($preco > 0 && $preco_promo) ? round((($preco - $preco_promo) / $preco) * 100) : 0;
                    ?>
                        <div class="hero-slide <?= $i === 0 ? 'active' : '' ?>" data-index="<?= $i ?>">
                            <div class="hero-bg">
                                <img src="<?= SITE_URL . $img ?>" alt="<?= htmlspecialchars($jogo['titulo']) ?>">
                            </div>
                            <div class="hero-content">
                                <span class="hero-badge <?= $em_promocao ? 'promo' : '' ?>">
                                    <i class="fas fa-<?= $em_promocao ? 'bolt' : 'star' ?>"></i>
                                    <?= $em_promocao ? 'Oferta' : 'Destaque' ?>
                                </span>
                                <h2 class="hero-title"><?= htmlspecialchars($jogo['titulo']) ?></h2>
                                <?php if (!empty($jogo['nome_estudio'])): ?>
                                    <p class="hero-dev"><?= htmlspecialchars($jogo['nome_estudio']) ?></p>
                                <?php endif; ?>

                                <div class="hero-price-row">
                                    <?php if ($preco_final == 0): ?>
                                        <span class="hero-price free">Gratuito</span>
                                    <?php else: ?>
                                        <?php if ($desconto > 0): ?>
                                            <span class="hero-discount">-<?= $desconto ?>%</span>
                                            <span class="hero-old-price"><?= formatPrice($preco) ?></span>
                                        <?php endif; ?>
                                        <span class="hero-price"><?= formatPrice($preco_final) ?></span>
                                    <?php endif; ?>
                                </div>

                                <a href="<?= SITE_URL ?>/pages/jogo.php?slug=<?= $jogo['slug'] ?>" class="hero-btn">
                                    <i class="fas fa-gamepad"></i> Ver Jogo
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (count($jogos_destaque) > 1): ?>
                        <div class="hero-arrows">
                            <button class="hero-arrow" onclick="heroSlider.prev()"><i class="fas fa-chevron-left"></i></button>
                            <button class="hero-arrow" onclick="heroSlider.next()"><i class="fas fa-chevron-right"></i></button>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="hero-sidebar">
                    <?php foreach ($jogos_destaque as $i => $jogo):
                        $preco = $jogo['preco_centavos'] ?? 0;
                        $preco_promo = $jogo['preco_promocional_centavos'] ?? null;
                        $em_promocao = $jogo['em_promocao'] ?? false;
                        $preco_final = $em_promocao && $preco_promo ? $preco_promo : $preco;
                        $desconto = ($preco > 0 && $preco_promo) ? round((($preco - $preco_promo) / $preco) * 100) : 0;
                    ?>
                        <div class="hero-sidebar-item <?= $i === 0 ? 'active' : '' ?>" onclick="heroSlider.goTo(<?= $i ?>)">
                            <div class="hero-sidebar-thumb">
                                <img src="<?= SITE_URL . $jogo['imagem_capa'] ?>" alt="">
                            </div>
                            <div>
                                <div class="hero-sidebar-title"><?= htmlspecialchars($jogo['titulo']) ?></div>
                                <div class="hero-sidebar-price">
                                    <?php if ($preco_final == 0): ?>
                                        <span class="discount">Gratuito</span>
                                    <?php elseif ($desconto > 0): ?>
                                        <span class="discount">-<?= $desconto ?>%</span> <?= formatPrice($preco_final) ?>
                                    <?php else: ?>
                                        <?= formatPrice($preco_final) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- SHOWCASE CARDS - LANÇAMENTOS & PROMOÇÕES -->
        <section class="showcase-cards-row">
            <!-- LANÇAMENTOS - Steam Perspective -->
            <a href="<?= SITE_URL ?>/pages/busca.php?ordem=recente" class="showcase-card">
                <div class="showcase-images">
                    <div class="showcase-stack">
                        <?php
                        // Garantir 5 imagens (repetir se necessário)
                        $imgs = $lancamentos_showcase;
                        while (count($imgs) < 5 && !empty($lancamentos_showcase)) {
                            $imgs = array_merge($imgs, $lancamentos_showcase);
                        }
                        $imgs = array_slice($imgs, 0, 5);
                        foreach ($imgs as $jogo):
                        ?>
                            <div class="showcase-img">
                                <img src="<?= SITE_URL . $jogo['imagem_capa'] ?>" alt="<?= htmlspecialchars($jogo['titulo']) ?>" loading="lazy">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="showcase-info">
                    <span class="showcase-tag new"><i class="fas fa-sparkles"></i> Novos</span>
                    <h3 class="showcase-title">Lançamentos</h3>
                    <p class="showcase-desc">Descubra os jogos mais recentes da plataforma</p>
                </div>
            </a>

            <!-- PROMOÇÕES - Different background -->
            <a href="<?= SITE_URL ?>/pages/busca.php?promocao=1" class="showcase-card sale-card">
                <div class="showcase-images">
                    <div class="showcase-stack">
                        <?php
                        $promo_imgs = array_slice($promocoes, 0, 5);
                        while (count($promo_imgs) < 5 && !empty($promocoes)) {
                            $promo_imgs = array_merge($promo_imgs, $promocoes);
                        }
                        $promo_imgs = array_slice($promo_imgs, 0, 5);
                        foreach ($promo_imgs as $jogo):
                        ?>
                            <div class="showcase-img">
                                <img src="<?= SITE_URL . $jogo['imagem_capa'] ?>" alt="<?= htmlspecialchars($jogo['titulo']) ?>" loading="lazy">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="showcase-info">
                    <span class="showcase-tag sale"><i class="fas fa-bolt"></i> Até 80% OFF</span>
                    <h3 class="showcase-title">Ofertas Especiais</h3>
                    <p class="showcase-desc">Os melhores descontos da semana</p>
                </div>
            </a>
        </section>

        <!-- CATEGORIAS - CARROSSEL -->
        <?php if (!empty($categorias)): ?>
            <section class="section">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-compass"></i> Explorar Categorias</h2>
                    <div class="section-controls">
                        <a href="<?= SITE_URL ?>/pages/categorias.php" class="section-link">Ver Todas <i class="fas fa-arrow-right"></i></a>
                        <div class="section-nav">
                            <button class="section-nav-btn" onclick="scrollCarousel('categoriesTrack', -1)"><i class="fas fa-chevron-left"></i></button>
                            <button class="section-nav-btn" onclick="scrollCarousel('categoriesTrack', 1)"><i class="fas fa-chevron-right"></i></button>
                        </div>
                    </div>
                </div>
                <div class="categories-carousel">
                    <div class="categories-track" id="categoriesTrack">
                        <?php foreach ($categorias as $cat): ?>
                            <a href="<?= SITE_URL ?>/pages/categoria.php?slug=<?= $cat['slug'] ?>" class="category-card">
                                <div class="category-images">
                                    <!-- Left Image -->
                                    <div class="cat-img-side left">
                                        <?php if (isset($cat['top_jogos'][0])): ?>
                                            <img src="<?= SITE_URL . $cat['top_jogos'][0]['imagem_capa'] ?>" alt="" loading="lazy">
                                        <?php else: ?>
                                            <div class="cat-img-placeholder"><i class="fas fa-gamepad"></i></div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Center Image (Main) -->
                                    <div class="cat-img-center">
                                        <?php if (isset($cat['top_jogos'][1])): ?>
                                            <img src="<?= SITE_URL . $cat['top_jogos'][1]['imagem_capa'] ?>" alt="" loading="lazy">
                                        <?php elseif (isset($cat['top_jogos'][0])): ?>
                                            <img src="<?= SITE_URL . $cat['top_jogos'][0]['imagem_capa'] ?>" alt="" loading="lazy">
                                        <?php else: ?>
                                            <div class="cat-img-placeholder"><i class="fas fa-<?= $cat['icone'] ?: 'gamepad' ?>"></i></div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Right Image -->
                                    <div class="cat-img-side right">
                                        <?php if (isset($cat['top_jogos'][2])): ?>
                                            <img src="<?= SITE_URL . $cat['top_jogos'][2]['imagem_capa'] ?>" alt="" loading="lazy">
                                        <?php elseif (isset($cat['top_jogos'][0])): ?>
                                            <img src="<?= SITE_URL . $cat['top_jogos'][0]['imagem_capa'] ?>" alt="" loading="lazy">
                                        <?php else: ?>
                                            <div class="cat-img-placeholder"><i class="fas fa-gamepad"></i></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="category-info">
                                    <span class="category-name">
                                        <i class="fas fa-<?= htmlspecialchars($cat['icone'] ?: 'gamepad') ?>"></i>
                                        <?= htmlspecialchars($cat['nome']) ?>
                                    </span>
                                    <span class="category-count"><?= (int)$cat['total_jogos'] ?> jogos</span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <!-- PROMOÇÕES -->
        <?php if (!empty($promocoes)): ?>
            <section class="section">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-bolt" style="color: var(--success)"></i> Ofertas</h2>
                    <div class="section-controls">
                        <a href="<?= SITE_URL ?>/pages/busca.php?promocao=1" class="section-link">Ver Todas <i class="fas fa-arrow-right"></i></a>
                        <div class="section-nav">
                            <button class="section-nav-btn" onclick="scrollCarousel('promoCarousel', -1)"><i class="fas fa-chevron-left"></i></button>
                            <button class="section-nav-btn" onclick="scrollCarousel('promoCarousel', 1)"><i class="fas fa-chevron-right"></i></button>
                        </div>
                    </div>
                </div>
                <div class="games-carousel">
                    <div class="games-carousel-track" id="promoCarousel">
                        <?php foreach ($promocoes as $jogo):
                            renderGameCard($jogo, $pdo, $user_id, 'store', [
                                'is_owned' => in_array($jogo['id'], $meus_jogos),
                                'in_wishlist' => in_array($jogo['id'], $minha_wishlist),
                                'in_cart' => in_array($jogo['id'], $meu_carrinho)
                            ]);
                        endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <!-- LANÇAMENTOS -->
        <?php if (!empty($lancamentos)): ?>
            <section class="section">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-sparkles"></i> Novidades</h2>
                    <div class="section-controls">
                        <a href="<?= SITE_URL ?>/pages/busca.php?ordem=recente" class="section-link">Ver Todos <i class="fas fa-arrow-right"></i></a>
                        <div class="section-nav">
                            <button class="section-nav-btn" onclick="scrollCarousel('newCarousel', -1)"><i class="fas fa-chevron-left"></i></button>
                            <button class="section-nav-btn" onclick="scrollCarousel('newCarousel', 1)"><i class="fas fa-chevron-right"></i></button>
                        </div>
                    </div>
                </div>
                <div class="games-carousel">
                    <div class="games-carousel-track" id="newCarousel">
                        <?php foreach ($lancamentos as $jogo):
                            renderGameCard($jogo, $pdo, $user_id, 'store', [
                                'is_owned' => in_array($jogo['id'], $meus_jogos),
                                'in_wishlist' => in_array($jogo['id'], $minha_wishlist),
                                'in_cart' => in_array($jogo['id'], $meu_carrinho)
                            ]);
                        endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <!-- JOGOS GRATUITOS -->
        <?php if (!empty($gratuitos)): ?>
            <section class="free-games-section">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-gift"></i> Jogos Gratuitos</h2>
                    <a href="<?= SITE_URL ?>/pages/busca.php?gratuito=1" class="section-link">Ver Todos <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="free-games-grid">
                    <?php foreach (array_slice($gratuitos, 0, 4) as $jogo):
                        renderGameCard($jogo, $pdo, $user_id, 'store', [
                            'is_owned' => in_array($jogo['id'], $meus_jogos),
                            'in_wishlist' => in_array($jogo['id'], $minha_wishlist),
                            'in_cart' => in_array($jogo['id'], $meu_carrinho)
                        ]);
                    endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- POPULARES -->
        <?php if (!empty($populares)): ?>
            <section class="section">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-fire" style="color: #ff6b6b"></i> Mais Populares</h2>
                    <div class="section-controls">
                        <a href="<?= SITE_URL ?>/pages/busca.php?ordem=vendas" class="section-link">Ver Todos <i class="fas fa-arrow-right"></i></a>
                        <div class="section-nav">
                            <button class="section-nav-btn" onclick="scrollCarousel('popularCarousel', -1)"><i class="fas fa-chevron-left"></i></button>
                            <button class="section-nav-btn" onclick="scrollCarousel('popularCarousel', 1)"><i class="fas fa-chevron-right"></i></button>
                        </div>
                    </div>
                </div>
                <div class="games-carousel">
                    <div class="games-carousel-track" id="popularCarousel">
                        <?php foreach ($populares as $jogo):
                            renderGameCard($jogo, $pdo, $user_id, 'store', [
                                'is_owned' => in_array($jogo['id'], $meus_jogos),
                                'in_wishlist' => in_array($jogo['id'], $minha_wishlist),
                                'in_cart' => in_array($jogo['id'], $meu_carrinho)
                            ]);
                        endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <!-- MELHORES AVALIADOS -->
        <?php if (!empty($melhores)): ?>
            <section class="section">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-star" style="color: #f9ca24"></i> Melhor Avaliados</h2>
                    <div class="section-controls">
                        <div class="section-nav">
                            <button class="section-nav-btn" onclick="scrollCarousel('ratedCarousel', -1)"><i class="fas fa-chevron-left"></i></button>
                            <button class="section-nav-btn" onclick="scrollCarousel('ratedCarousel', 1)"><i class="fas fa-chevron-right"></i></button>
                        </div>
                    </div>
                </div>
                <div class="games-carousel">
                    <div class="games-carousel-track" id="ratedCarousel">
                        <?php foreach ($melhores as $jogo):
                            renderGameCard($jogo, $pdo, $user_id, 'store', [
                                'is_owned' => in_array($jogo['id'], $meus_jogos),
                                'in_wishlist' => in_array($jogo['id'], $minha_wishlist),
                                'in_cart' => in_array($jogo['id'], $meu_carrinho)
                            ]);
                        endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <!-- DESENVOLVEDORES - CARROSSEL -->
        <?php if (!empty($desenvolvedores)): ?>
            <section class="section">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-building" style="color: #9147ff"></i> Desenvolvedores</h2>
                    <div class="section-controls">
                        <div class="section-nav">
                            <button class="section-nav-btn" onclick="scrollCarousel('devsTrack', -1)"><i class="fas fa-chevron-left"></i></button>
                            <button class="section-nav-btn" onclick="scrollCarousel('devsTrack', 1)"><i class="fas fa-chevron-right"></i></button>
                        </div>
                    </div>
                </div>
                <div class="devs-carousel">
                    <div class="devs-track" id="devsTrack">
                        <?php foreach ($desenvolvedores as $dev): ?>
                            <a href="<?= SITE_URL ?>/pages/desenvolvedor.php?slug=<?= $dev['slug'] ?>" class="dev-card">
                                <img src="<?= SITE_URL . ($dev['logo_url'] ?: '/assets/images/default-dev.png') ?>"
                                    alt="" class="dev-logo"
                                    onerror="this.src='<?= SITE_URL ?>/assets/images/default-dev.png'">
                                <div class="dev-name">
                                    <?= htmlspecialchars($dev['nome_estudio']) ?>
                                    <?php if ($dev['verificado']): ?><i class="fas fa-check-circle"></i><?php endif; ?>
                                </div>
                                <div class="dev-count"><?= (int)$dev['total_jogos'] ?> jogos</div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

    </div>
</div>

<script>
    // Promo Banner Slider
    const promoBanner = {
        track: document.getElementById('promoBannerTrack'),
        dots: document.querySelectorAll('.promo-banner-dot'),
        current: 0,
        total: <?= count($banners) ?>,
        interval: null,

        goTo(index) {
            if (index >= this.total) index = 0;
            if (index < 0) index = this.total - 1;
            this.current = index;
            if (this.track) this.track.style.transform = `translateX(-${index * 100}%)`;
            this.dots.forEach((d, i) => d.classList.toggle('active', i === index));
            this.resetAuto();
        },
        next() {
            this.goTo(this.current + 1);
        },
        prev() {
            this.goTo(this.current - 1);
        },
        startAuto() {
            this.interval = setInterval(() => this.next(), 5000);
        },
        resetAuto() {
            clearInterval(this.interval);
            this.startAuto();
        },
        init() {
            if (this.total > 1) this.startAuto();
        }
    };
    promoBanner.init();

    // Hero Slider
    const heroSlider = {
        slides: document.querySelectorAll('.hero-slide'),
        items: document.querySelectorAll('.hero-sidebar-item'),
        current: 0,
        interval: null,

        goTo(index) {
            if (index >= this.slides.length) index = 0;
            if (index < 0) index = this.slides.length - 1;
            this.slides.forEach(s => s.classList.remove('active'));
            this.items.forEach(s => s.classList.remove('active'));
            this.current = index;
            this.slides[index]?.classList.add('active');
            this.items[index]?.classList.add('active');
            this.resetAuto();
        },
        next() {
            this.goTo(this.current + 1);
        },
        prev() {
            this.goTo(this.current - 1);
        },
        startAuto() {
            this.interval = setInterval(() => this.next(), 6000);
        },
        resetAuto() {
            clearInterval(this.interval);
            this.startAuto();
        },
        init() {
            if (this.slides.length > 1) this.startAuto();
        }
    };
    heroSlider.init();

    // Generic Carousel Scroll
    function scrollCarousel(id, dir) {
        const el = document.getElementById(id);
        if (!el) return;

        // Detectar largura do primeiro item
        const firstItem = el.firstElementChild;
        const itemWidth = firstItem ? firstItem.offsetWidth + 16 : 200;
        const scrollAmount = itemWidth * 3;

        el.scrollBy({
            left: scrollAmount * dir,
            behavior: 'smooth'
        });
    }

    // Touch/Mouse drag for all carousels
    document.querySelectorAll('.games-carousel-track, .categories-track, .devs-track').forEach(el => {
        let isDown = false,
            startX, scrollLeft;

        el.addEventListener('mousedown', e => {
            isDown = true;
            el.style.cursor = 'grabbing';
            startX = e.pageX - el.offsetLeft;
            scrollLeft = el.scrollLeft;
        });

        el.addEventListener('mouseleave', () => {
            isDown = false;
            el.style.cursor = 'grab';
        });

        el.addEventListener('mouseup', () => {
            isDown = false;
            el.style.cursor = 'grab';
        });

        el.addEventListener('mousemove', e => {
            if (!isDown) return;
            e.preventDefault();
            el.scrollLeft = scrollLeft - (e.pageX - el.offsetLeft - startX) * 2;
        });

        // Set initial cursor
        el.style.cursor = 'grab';
    });
</script>

<?php require_once '../includes/footer.php'; ?>