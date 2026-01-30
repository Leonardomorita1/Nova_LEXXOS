<?php
// pages/home.php - Banner PS Style Corrigido
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
$banners = $pdo->query("SELECT * FROM banner WHERE ativo = 1 ORDER BY ordem LIMIT 5")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$jogos_destaque = $pdo->query("
    SELECT j.*, d.nome_estudio FROM jogo j 
    LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id 
    WHERE j.status = 'publicado' AND j.destaque = 1 
    ORDER BY j.criado_em DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$categorias = $pdo->query("
    SELECT c.*, 
    (SELECT COUNT(*) FROM jogo_categoria jc JOIN jogo j ON jc.jogo_id = j.id WHERE jc.categoria_id = c.id AND j.status = 'publicado') as total_jogos 
    FROM categoria c WHERE c.ativa = 1 ORDER BY c.ordem LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

foreach ($categorias as &$cat) {
    $stmt = $pdo->prepare("
        SELECT j.imagem_capa FROM jogo j
        JOIN jogo_categoria jc ON j.id = jc.jogo_id
        WHERE jc.categoria_id = ? AND j.status = 'publicado'
        ORDER BY j.total_vendas DESC LIMIT 3
    ");
    $stmt->execute([$cat['id']]);
    $cat['covers'] = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}
unset($cat);

$lancamentos_showcase = $pdo->query("
    SELECT j.imagem_capa, j.titulo, j.slug FROM jogo j 
    WHERE j.status = 'publicado' 
    ORDER BY j.publicado_em DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

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
   HOME PAGE - PS STORE STYLE
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
   BANNER SECTION - COMPLETO
   ============================================ */
    .ps-banner-section {
        margin-bottom: 48px;
    }

    .ps-banner-wrapper {
        margin-bottom: 0;
    }

    /* Slider Container */
    .ps-banner-slider {
        position: relative;
        width: 100%;
        overflow: hidden;
    }

    .ps-banner-track {
        display: flex;
        transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .ps-banner-slide {
        min-width: 100%;
        position: relative;
    }

    /* ============================================
   BANNER SIMPLES
   ============================================ */
    .ps-simple-slide {
        height: 400px;
    }

    .ps-banner-bg {
        position: absolute;
        inset: 0;
        overflow: hidden;
    }

    .ps-banner-bg img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .ps-banner-bg::after {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg,
                transparent 0%,
                transparent 50%,
                rgba(0, 0, 0, 0.7) 100%);
    }

    /* ============================================
   BANNER PROMOCIONAL - PS STYLE
   ============================================ */
    .ps-promo-slide {
        height: 420px;
        display: flex;
    }

    /* Background com Gradient */
    .ps-promo-bg {
        position: absolute;
        inset: 0;
        overflow: hidden;
    }

    .ps-promo-bg img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    /* Content Grid: Texto à Esquerda, Imagem à Direita */
    .ps-promo-content {
        position: relative;
        z-index: 10;
        width: 100%;
        height: 100%;
        display: grid;
        grid-template-columns: 1fr 1fr;
        align-items: center;
        padding: 40px 20%;
    }

    /* Lado Esquerdo - Informações */
    .ps-promo-info {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;

        gap: 16px;
    }

    /* Título Principal - Quebrado */
    .ps-promo-title {
        font-style: italic;
        font-size: clamp(36px, 5vw, 56px);
        font-weight: 900;
        color: var(--banner-text);
        text-transform: uppercase;
        line-height: 1;
        letter-spacing: -1px;
        margin: 0;
        text-shadow: 0 4px 20px rgb(0, 0, 0);
    }

    .ps-promo-title span {
        display: block;
    }

    /* Badge Container */
    .ps-promo-badges {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        margin-top: 8px;
        font-style: italic;
    }

    /* Badge Termina Em - Formato Tag/Rampa */
    .ps-badge-date {
        display: inline-flex;
        align-items: center;
        background: var(--banner-accent);
        padding: 0;

        width: fit-content;
        -webkit-transform: skew(-15deg);
        -moz-transform: skew(-15deg);
        -o-transform: skew(-15deg);
    }

    .ps-badge-date-label {
        background: transparent;
        color: rgba(255, 255, 255, 0.9);
        padding: 4px 6px;
        font-size: 15px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .ps-badge-date-value {
        color: #fff;
        background: rgba(0, 0, 0, 0.38);
        padding: 5px 16px;
        font-size: 16px;
        font-weight: 800;
        position: relative;

    }


    /* Badge Economize - Formato PS Store */
    .ps-badge-discount {
        display: inline-flex;
        align-items: stretch;
        width: fit-content;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
    }

    .ps-badge-discount-label {
        background: var(--banner-accent);
        color: #fff;
        padding: 2px 3px;
        font-size: 13px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: flex;
        align-items: center;
        position: relative;
        -webkit-transform: skew(-15deg);
        -moz-transform: skew(-15deg);
        -o-transform: skew(-15deg);
    }



    .ps-badge-discount-value {
        display: flex;
        align-items: center;
        padding: 10px;
        -webkit-transform: skew(-15deg);
        -moz-transform: skew(-15deg);
        -o-transform: skew(-15deg);
        background: rgba(94, 94, 94, 0.31);
        box-shadow: 0 4px 30px rgba(0, 0, 0, 0.61);
        backdrop-filter: blur(9px);
        -webkit-backdrop-filter: blur(9px);
        border: 1px solid rgba(73, 69, 69, 0.2);
        color: var(--banner-accent);

    }

    .ps-badge-discount-number {
        font-size: clamp(32px, 5vw, 48px);
        font-weight: 900;
        line-height: 1;
    }

    .ps-badge-discount-percent {
        font-size: clamp(16px, 2vw, 24px);
        font-weight: 800;
    }

    /* Lado Direito - Imagem Overlay */
    .ps-promo-image {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
        position: relative;
    }

    .ps-promo-overlay-img {
        max-width: 100%;
        max-height: 380px;
        object-fit: contain;
        filter: drop-shadow(0 20px 50px rgba(0, 0, 0, 0.5));
        animation: float-ps 4s ease-in-out infinite;
        position: relative;
        z-index: 2;
    }

    @keyframes float-ps {

        0%,
        100% {
            transform: translateY(0);
        }

        50% {
            transform: translateY(-15px);
        }
    }

    /* Glow atrás da imagem */
    .ps-promo-glow {
        position: absolute;
        width: 70%;
        height: 70%;
        background: radial-gradient(ellipse, var(--banner-accent, #0066CC) 0%, transparent 70%);
        opacity: 0.25;
        filter: blur(40px);
        z-index: 1;
    }

    /* ============================================
   BANNER BOTTOM - SEPARADO
   ============================================ */
    .ps-banner-bottom {
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-top: none;
        padding: 20px 28px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
    }

    .ps-banner-bottom-info {
        flex: 1;
        min-width: 0;
    }

    .ps-banner-bottom-title {
        font-size: 18px;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0 0 4px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .ps-banner-bottom-subtitle {
        font-size: 14px;
        color: var(--text-secondary);
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .ps-banner-cta {
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        background: var(--accent);
        color: #fff;
        padding: 12px 24px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s;
    }

    .ps-banner-cta:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(76, 139, 245, 0.4);
    }

    .ps-banner-cta i {
        font-size: 12px;
        transition: transform 0.3s;
    }

    .ps-banner-cta:hover i {
        transform: translateX(4px);
    }

    /* Navigation Arrows */
    .ps-banner-nav {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 48px;
        height: 48px;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s;
        z-index: 20;
        opacity: 0;
    }

    .ps-banner-slider:hover .ps-banner-nav {
        opacity: 1;
    }

    .ps-banner-nav:hover {
        background: var(--accent);
        border-color: var(--accent);
        transform: translateY(-50%) scale(1.1);
    }

    .ps-banner-nav.prev {
        left: 20px;
    }

    .ps-banner-nav.next {
        right: 20px;
    }

    /* Dots */
    .ps-banner-dots {
        display: flex;
        justify-content: center;
        gap: 8px;
        margin-top: 16px;
    }

    .ps-banner-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.25);
        cursor: pointer;
        transition: all 0.3s;
    }

    .ps-banner-dot.active {
        background: var(--accent);
        width: 24px;
        border-radius: 4px;
    }

    .ps-banner-dot:hover:not(.active) {
        background: rgba(255, 255, 255, 0.4);
    }

    /* ============================================
   HERO FEATURED
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
        background: linear-gradient(180deg,
                transparent 0%,
                transparent 50%,
                rgba(0, 0, 0, 0.9) 100%);
    }

    .hero-content {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        padding: 28px;
        z-index: 2;
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 16px;
    }

    .hero-info {
        flex: 1;
        min-width: 0;
    }

    .hero-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: var(--accent);
        color: #fff;
        padding: 6px 12px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 12px;
    }

    .hero-badge.promo {
        background: linear-gradient(135deg, #00d26a, #00a854);
    }

    .hero-title {
        font-size: 1.75rem;
        font-weight: 700;
        color: #fff;
        margin: 0 0 8px;
        line-height: 1.2;
    }

    .hero-dev {
        font-size: 13px;
        color: rgba(255, 255, 255, 0.6);
        margin: 0 0 12px;
    }

    .hero-price-row {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .hero-discount {
        background: #00d26a;
        color: #fff;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 13px;
        font-weight: 700;
    }

    .hero-old-price {
        color: rgba(255, 255, 255, 0.4);
        text-decoration: line-through;
        font-size: 14px;
    }

    .hero-price {
        font-size: 22px;
        font-weight: 700;
        color: #fff;
    }

    .hero-price.free {
        color: #00d26a;
    }

    .hero-cta {
        flex-shrink: 0;
    }

    .hero-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: var(--accent);
        color: #fff;
        padding: 12px 20px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s;
    }

    .hero-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(76, 139, 245, 0.4);
    }

    /* Sidebar */
    .hero-sidebar {
        display: flex;
        flex-direction: column;
        gap: 8px;
        background: var(--bg-secondary);
        border-radius: 12px;
        padding: 12px;
        border: 1px solid var(--border);
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
        background: rgba(255, 255, 255, 0.03);
    }

    .hero-sidebar-item.active {
        background: rgba(76, 139, 245, 0.1);
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

    .hero-sidebar-info {
        flex: 1;
        min-width: 0;
    }

    .hero-sidebar-title {
        font-size: 13px;
        font-weight: 600;
        color: var(--text-primary);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-bottom: 4px;
    }

    .hero-sidebar-price {
        font-size: 12px;
        color: var(--text-secondary);
    }

    .hero-sidebar-price .discount {
        color: #00d26a;
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
        opacity: 0;
        transition: opacity 0.3s;
    }

    .hero-main:hover .hero-arrows {
        opacity: 1;
    }

    .hero-arrow {
        width: 40px;
        height: 40px;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(10px);
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
        background: var(--accent);
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
        gap: 12px;
        font-size: 20px;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
    }

    .section-icon {
        width: 36px;
        height: 36px;
        background: linear-gradient(135deg, var(--accent), #667eea);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        color: #fff;
    }

    .section-icon.fire {
        background: linear-gradient(135deg, #ff6b6b, #ee5a5a);
    }

    .section-icon.star {
        background: linear-gradient(135deg, #f9ca24, #f0932b);
    }

    .section-icon.gift {
        background: linear-gradient(135deg, #00d26a, #00a854);
    }

    .section-icon.dev {
        background: linear-gradient(135deg, #9147ff, #772ce8);
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
        font-weight: 600;
        text-decoration: none;
        padding: 8px 14px;
        border-radius: 6px;
        background: rgba(76, 139, 245, 0.1);
        transition: all 0.2s;
    }

    .section-link:hover {
        background: rgba(76, 139, 245, 0.2);
    }

    .section-link i {
        font-size: 11px;
        transition: transform 0.2s;
    }

    .section-link:hover i {
        transform: translateX(3px);
    }

    .section-nav {
        display: flex;
        gap: 6px;
    }

    .section-nav-btn {
        width: 36px;
        height: 36px;
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 8px;
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
   SHOWCASE CARDS
   ============================================ */
    .showcase-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 48px;
    }

    .showcase-card {
        position: relative;
        background: var(--bg-secondary);
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid var(--border);
        text-decoration: none;
        transition: all 0.3s;
    }

    .showcase-card:hover {
        border-color: var(--accent);
        transform: translateY(-4px);
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.2);
    }

    .showcase-card.sale:hover {
        border-color: #00d26a;
    }

    .showcase-images {
        height: 160px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, rgba(76, 139, 245, 0.08) 0%, transparent 100%);
        perspective: 800px;
    }

    .showcase-card.sale .showcase-images {
        background: linear-gradient(135deg, rgba(0, 210, 106, 0.1) 0%, transparent 100%);
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
        width: 80px;
        height: 110px;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .showcase-img img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .showcase-img:nth-child(1) {
        transform: translateX(-100px) rotateY(20deg) scale(0.8);
        opacity: 0.5;
        z-index: 1;
    }

    .showcase-img:nth-child(2) {
        transform: translateX(-50px) rotateY(10deg) scale(0.9);
        opacity: 0.7;
        z-index: 2;
    }

    .showcase-img:nth-child(3) {
        transform: scale(1);
        z-index: 3;
        box-shadow: 0 12px 32px rgba(76, 139, 245, 0.3);
    }

    .showcase-img:nth-child(4) {
        transform: translateX(50px) rotateY(-10deg) scale(0.9);
        opacity: 0.7;
        z-index: 2;
    }

    .showcase-img:nth-child(5) {
        transform: translateX(100px) rotateY(-20deg) scale(0.8);
        opacity: 0.5;
        z-index: 1;
    }

    .showcase-card.sale .showcase-img:nth-child(3) {
        box-shadow: 0 12px 32px rgba(0, 210, 106, 0.3);
    }

    .showcase-card:hover .showcase-img:nth-child(3) {
        transform: scale(1.05);
    }

    .showcase-info {
        padding: 20px;
        border-top: 1px solid var(--border);
    }

    .showcase-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 5px 10px;
        border-radius: 4px;
        margin-bottom: 10px;
    }

    .showcase-tag.new {
        background: linear-gradient(135deg, var(--accent), #667eea);
        color: #fff;
    }

    .showcase-tag.sale {
        background: linear-gradient(135deg, #00d26a, #00a854);
        color: #fff;
    }

    .showcase-title {
        font-size: 17px;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0 0 6px;
    }

    .showcase-desc {
        font-size: 13px;
        color: var(--text-secondary);
        margin: 0;
    }

    /* ============================================
   CATEGORIES CAROUSEL
   ============================================ */
    .categories-carousel {
        position: relative;
    }

    .categories-track {
        display: flex;
        gap: 12px;
        overflow-x: auto;
        scroll-behavior: smooth;
        scrollbar-width: none;
        padding: 4px 0;
    }

    .categories-track::-webkit-scrollbar {
        display: none;
    }

    .cat-card {
        flex: 0 0 180px;
        min-width: 180px;
        height: 100px;
        background: var(--bg-secondary);
        border-radius: 10px;
        position: relative;
        overflow: hidden;
        text-decoration: none;
        border: 1px solid var(--border);
        transition: all 0.3s;
    }

    .cat-card:hover {
        border-color: var(--accent);
        transform: translateY(-3px);
    }

    .cat-bg {
        position: absolute;
        inset: 0;
        display: flex;
        opacity: 0.25;
        filter: blur(1px) grayscale(100%);
        transition: all 0.3s;
    }

    .cat-bg img {
        width: 33.33%;
        height: 100%;
        object-fit: cover;
    }

    .cat-card:hover .cat-bg {
        opacity: 0.5;
        filter: blur(0) grayscale(0);
    }

    .cat-content {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        background: linear-gradient(180deg, rgba(0, 0, 0, 0.1) 0%, rgba(0, 0, 0, 0.7) 100%);
        z-index: 2;
    }

    .cat-icon {
        font-size: 20px;
        color: #fff;
        margin-bottom: 6px;
    }

    .cat-name {
        font-size: 14px;
        font-weight: 700;
        color: #fff;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
    }

    .cat-count {
        font-size: 11px;
        color: rgba(255, 255, 255, 0.7);
        background: rgba(0, 0, 0, 0.4);
        padding: 2px 8px;
        border-radius: 10px;
        margin-top: 4px;
    }

    /* ============================================
   GAMES CAROUSEL
   ============================================ */
    .games-carousel {
        position: relative;
    }

    .games-track {
        display: flex;
        gap: 14px;
        overflow-x: auto;
        scroll-behavior: smooth;
        scrollbar-width: none;
        padding: 4px 0;
    }

    .games-track::-webkit-scrollbar {
        display: none;
    }

    .games-track .ps-card {
        flex: 0 0 300px;
        min-width: 300px;
    }

    /* ============================================
   FREE GAMES SECTION
   ============================================ */
    .free-section {
        background: linear-gradient(135deg, rgba(0, 210, 106, 0.06) 0%, rgba(0, 168, 84, 0.02) 100%);
        border: 1px solid rgba(0, 210, 106, 0.15);
        border-radius: 16px;
        padding: 28px;
        margin-bottom: 48px;
        position: relative;
        overflow: hidden;
    }

    .free-section::before {
        content: '';
        position: absolute;
        top: -40%;
        right: -15%;
        width: 350px;
        height: 350px;
        background: radial-gradient(circle, rgba(0, 210, 106, 0.12) 0%, transparent 70%);
        pointer-events: none;
    }

    .free-section .section-title {
        color: #00d26a;
    }

    .free-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px;
        margin-top: 20px;
    }

    /* ============================================
   DEVELOPERS
   ============================================ */
    .devs-carousel {
        position: relative;
    }

    .devs-track {
        display: flex;
        gap: 14px;
        overflow-x: auto;
        scroll-behavior: smooth;
        scrollbar-width: none;
        padding: 4px 0;
    }

    .devs-track::-webkit-scrollbar {
        display: none;
    }

    .dev-card {
        flex: 0 0 180px;
        min-width: 180px;
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 24px 16px;
        text-align: center;
        text-decoration: none;
        transition: all 0.3s;
    }

    .dev-card:hover {
        border-color: #9147ff;
        transform: translateY(-4px);
        box-shadow: 0 10px 30px rgba(145, 71, 255, 0.12);
    }

    .dev-logo {
        width: 56px;
        height: 56px;
        border-radius: 12px;
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
        color: #9147ff;
        font-size: 11px;
    }

    .dev-count {
        font-size: 12px;
        color: var(--text-secondary);
    }

    /* ============================================
   RESPONSIVE
   ============================================ */
    @media (max-width: 1200px) {
        .hero-section {
            grid-template-columns: 1fr 280px;
        }

        .showcase-row {
            grid-template-columns: 1fr;
        }

        .free-grid {
            grid-template-columns: repeat(3, 1fr);
        }

        .ps-promo-content {
            padding: 30px 40px;
        }
    }

    @media (max-width: 1024px) {
        .hero-section {
            grid-template-columns: 1fr;
        }

        .hero-sidebar {
            display: none;
        }

        .free-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .ps-promo-content {
            grid-template-columns: 1fr;
            text-align: center;
        }

        .ps-promo-info {
            align-items: center;
        }

        .ps-promo-image {
            display: none;
        }
    }

    @media (max-width: 768px) {
        .home-container {
            padding: 0 16px 60px;
        }

        .ps-simple-slide {
            height: 300px;
        }

        .ps-promo-slide {
            height: 320px;
        }

        .ps-promo-title {
            font-size: 28px;
        }

        .ps-badge-discount-number {
            font-size: 36px;
        }

        .ps-banner-bottom {
            flex-direction: column;
            align-items: stretch;
            gap: 12px;
            padding: 16px 20px;
        }

        .ps-banner-cta {
            width: 100%;
            justify-content: center;
        }

        .hero-main {
            aspect-ratio: 4 / 3;
        }

        .hero-content {
            flex-direction: column;
            align-items: flex-start;
        }

        .hero-title {
            font-size: 1.4rem;
        }

        .hero-btn {
            width: 100%;
            justify-content: center;
        }

        .section-title {
            font-size: 17px;
        }

        .section-icon {
            width: 32px;
            height: 32px;
            font-size: 14px;
        }

        .section-nav {
            display: none;
        }

        .cat-card {
            flex: 0 0 150px;
            min-width: 150px;
            height: 85px;
        }

        .games-track .ps-card {
            flex: 0 0 150px;
            min-width: 150px;
        }

        .free-section {
            padding: 20px;
        }

        .free-grid {
            gap: 12px;
        }

        .dev-card {
            flex: 0 0 160px;
            min-width: 160px;
            padding: 20px 12px;
        }
    }

    @media (max-width: 480px) {
        .ps-simple-slide {
            height: 260px;
        }

        .ps-promo-slide {
            height: 280px;
        }

        .ps-promo-content {
            padding: 20px;
        }

        .ps-promo-title {
            font-size: 24px;
        }

        .ps-badge-discount-number {
            font-size: 32px;
        }

        .ps-badge-date-label,
        .ps-badge-discount-label {
            font-size: 11px;
            padding: 8px 12px;
        }

        .hero-main {
            aspect-ratio: 1 / 1;
        }

        .showcase-images {
            height: 130px;
        }

        .showcase-img {
            width: 65px;
            height: 90px;
        }

        .free-grid {
            grid-template-columns: 1fr 1fr;
        }
    }
</style>
<!-- ============================================
             BANNER PRINCIPAL - PS STYLE
             ============================================ -->
<?php if (!empty($banners)): ?>
    <section class="ps-banner-section">
        <div class="ps-banner-wrapper">
            <div class="ps-banner-slider">
                <div class="ps-banner-track" id="psBannerTrack">
                    <?php foreach ($banners as $i => $banner):
                        $hasImage = !empty($banner['imagem_desktop']);
                        $hasOverlay = !empty($banner['imagem_overlay']);
                        $isPromocional = ($banner['estilo_banner'] ?? 'simples') === 'promocional';

                        $corFundo = $banner['cor_fundo'] ?? '#131314';
                        $corTexto = $banner['cor_texto'] ?? '#ffffff';
                        $corDestaque = $banner['cor_destaque'] ?? '#0066CC';

                        $rgb = sscanf($corFundo, "#%02x%02x%02x");
                        $rgbString = $rgb ? implode(', ', $rgb) : '19, 19, 20';

                        $descontoNum = !empty($banner['texto_destaque'])
                            ? preg_replace('/[^0-9]/', '', $banner['texto_destaque'])
                            : '';

                        // Quebrar título
                        $tituloCompleto = $banner['titulo'] ?? 'OFERTAS ESPECIAIS';
                        $palavrasTitulo = explode(' ', $tituloCompleto);
                        $metade = ceil(count($palavrasTitulo) / 2);
                        $tituloLinha1 = implode(' ', array_slice($palavrasTitulo, 0, $metade));
                        $tituloLinha2 = implode(' ', array_slice($palavrasTitulo, $metade));
                    ?>

                        <?php if ($isPromocional): ?>
                            <!-- BANNER PROMOCIONAL -->
                            <div class="ps-banner-slide ps-promo-slide"
                                style="--banner-rgb: <?= $rgbString ?>; --banner-text: <?= $corTexto ?>; --banner-accent: <?= $corDestaque ?>;">

                                <!-- Background -->
                                <div class="ps-promo-bg">
                                    <?php if ($hasImage): ?>
                                        <img src="<?= htmlspecialchars($banner['imagem_desktop']) ?>"
                                            alt="" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>">
                                    <?php endif; ?>
                                </div>

                                <!-- Content Grid -->
                                <div class="ps-promo-content">
                                    <!-- Lado Esquerdo - Info -->
                                    <div class="ps-promo-info">
                                        <!-- Título Quebrado -->
                                        <h2 class="ps-promo-title">
                                            <span><?= htmlspecialchars($tituloLinha1) ?></span>
                                            <?php if ($tituloLinha2): ?>
                                                <span style="font-weight: bold;"><?= htmlspecialchars($tituloLinha2) ?></span>
                                            <?php endif; ?>
                                        </h2>

                                        <!-- Badges -->
                                        <div class="ps-promo-badges">
                                            <!-- Termina Em -->
                                            <?php if (!empty($banner['data_fim'])): ?>
                                                <div class="ps-badge-date">
                                                    <span class="ps-badge-date-label">Termina em</span>
                                                    <span class="ps-badge-date-value"><?= date('d/m', strtotime($banner['data_fim'])) ?></span>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Economize Até -->
                                            <?php if (!empty($descontoNum)): ?>
                                                <div class="ps-badge-discount">
                                                    <span class="ps-badge-discount-label">Economize até</span>

                                                </div>
                                                <span class="ps-badge-discount-value">
                                                    <span class="ps-badge-discount-number"><?= $descontoNum ?></span>
                                                    <span class="ps-badge-discount-percent">%</span>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Lado Direito - Imagem Overlay -->
                                    <?php if ($hasOverlay): ?>
                                        <div class="ps-promo-image">
                                            <div class="ps-promo-glow"></div>
                                            <img src="<?= htmlspecialchars($banner['imagem_overlay']) ?>"
                                                alt=""
                                                class="ps-promo-overlay-img"
                                                loading="<?= $i === 0 ? 'eager' : 'lazy' ?>">
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                        <?php else: ?>
                            <!-- BANNER SIMPLES -->
                            <div class="ps-banner-slide ps-simple-slide"
                                style="--banner-rgb: <?= $rgbString ?>; --banner-text: <?= $corTexto ?>; --banner-accent: <?= $corDestaque ?>;">
                                <div class="ps-banner-bg">
                                    <?php if ($hasImage): ?>
                                        <img src="<?= htmlspecialchars($banner['imagem_desktop']) ?>"
                                            alt="" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>">
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                    <?php endforeach; ?>
                </div>

                <?php if (count($banners) > 1): ?>
                    <button class="ps-banner-nav prev" onclick="psBanner.prev()">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="ps-banner-nav next" onclick="psBanner.next()">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                <?php endif; ?>
            </div>

            <!-- BOTTOM SEPARADO -->
            <div class="ps-banner-bottom" id="psBannerBottom">
                <?php
                $firstBanner = $banners[0] ?? [];
                $isFirstPromo = ($firstBanner['estilo_banner'] ?? 'simples') === 'promocional';
                $bottomTitle = $firstBanner['texto_principal'] ?? $firstBanner['titulo'] ?? 'Confira';
                $bottomSubtitle = $firstBanner['subtitulo'] ?? '';
                $bottomUrl = $firstBanner['url_destino'] ?? '#';
                $bottomBtnText = $isFirstPromo ? 'Ver ofertas' : 'Saiba mais';
                ?>
                <div class="ps-banner-bottom-info">
                    <h3 class="ps-banner-bottom-title"><?= htmlspecialchars($bottomTitle) ?></h3>
                    <p class="ps-banner-bottom-subtitle"><?= htmlspecialchars($bottomSubtitle) ?></p>
                </div>
                <a href="<?= htmlspecialchars($bottomUrl) ?>" class="ps-banner-cta">
                    <span class="ps-banner-cta-text"><?= $bottomBtnText ?></span> <i class="fas fa-chevron-right"></i>
                </a>
            </div>
        </div>

        <?php if (count($banners) > 1): ?>
            <div class="ps-banner-dots">
                <?php for ($i = 0; $i < count($banners); $i++): ?>
                    <div class="ps-banner-dot <?= $i === 0 ? 'active' : '' ?>" onclick="psBanner.goTo(<?= $i ?>)"></div>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
<div class="home-wrapper">
    <div class="home-container">



        <!-- ============================================
             HERO FEATURED
             ============================================ -->
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
                                <div class="hero-info">
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
                                </div>
                                <div class="hero-cta">
                                    <a href="<?= SITE_URL ?>/pages/jogo.php?slug=<?= $jogo['slug'] ?>" class="hero-btn">
                                        <i class="fas fa-gamepad"></i> Ver Jogo
                                    </a>
                                </div>
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
                            <div class="hero-sidebar-info">
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

        <!-- SHOWCASE CARDS -->
        <section class="showcase-row">
            <a href="<?= SITE_URL ?>/pages/busca.php?ordem=recente" class="showcase-card">
                <div class="showcase-images">
                    <div class="showcase-stack">
                        <?php
                        $imgs = $lancamentos_showcase;
                        while (count($imgs) < 5 && !empty($lancamentos_showcase)) {
                            $imgs = array_merge($imgs, $lancamentos_showcase);
                        }
                        $imgs = array_slice($imgs, 0, 5);
                        foreach ($imgs as $jogo):
                        ?>
                            <div class="showcase-img">
                                <img src="<?= SITE_URL . $jogo['imagem_capa'] ?>" alt="" loading="lazy">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="showcase-info">
                    <span class="showcase-tag new"><i class="fas fa-sparkles"></i> Novos</span>
                    <h3 class="showcase-title">Lançamentos</h3>
                    <p class="showcase-desc">Os jogos mais recentes da plataforma</p>
                </div>
            </a>

            <a href="<?= SITE_URL ?>/pages/busca.php?promocao=1" class="showcase-card sale">
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
                                <img src="<?= SITE_URL . $jogo['imagem_capa'] ?>" alt="" loading="lazy">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="showcase-info">
                    <span class="showcase-tag sale"><i class="fas fa-bolt"></i> Até 80% OFF</span>
                    <h3 class="showcase-title">Ofertas</h3>
                    <p class="showcase-desc">Os melhores descontos da semana</p>
                </div>
            </a>
        </section>

        <!-- CATEGORIAS -->
        <?php if (!empty($categorias)): ?>
            <section class="section">
                <div class="section-header">
                    <h2 class="section-title">
                        <span class="section-icon"><i class="fas fa-th-large"></i></span>
                        Gêneros
                    </h2>
                    <div class="section-controls">
                        <a href="<?= SITE_URL ?>/pages/categorias.php" class="section-link">
                            Ver todos <i class="fas fa-chevron-right"></i>
                        </a>
                        <div class="section-nav">
                            <button class="section-nav-btn" onclick="scrollCarousel('categoriesTrack', -1)">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button class="section-nav-btn" onclick="scrollCarousel('categoriesTrack', 1)">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="categories-carousel">
                    <div class="categories-track" id="categoriesTrack">
                        <?php foreach ($categorias as $cat): ?>
                            <a href="<?= SITE_URL ?>/pages/categoria.php?slug=<?= $cat['slug'] ?>" class="cat-card">
                                <div class="cat-bg">
                                    <?php for ($k = 0; $k < 3; $k++):
                                        $img = $cat['covers'][$k] ?? null;
                                        if ($img): ?>
                                            <img src="<?= SITE_URL . $img ?>" alt="">
                                    <?php endif;
                                    endfor; ?>
                                </div>
                                <div class="cat-content">
                                    <i class="cat-icon fas fa-<?= $cat['icone'] ?: 'gamepad' ?>"></i>
                                    <span class="cat-name"><?= $cat['nome'] ?></span>
                                    <span class="cat-count"><?= $cat['total_jogos'] ?> jogos</span>
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
                    <h2 class="section-title">
                        <span class="section-icon gift"><i class="fas fa-bolt"></i></span>
                        Ofertas
                    </h2>
                    <div class="section-controls">
                        <a href="<?= SITE_URL ?>/pages/busca.php?promocao=1" class="section-link">
                            Ver todas <i class="fas fa-chevron-right"></i>
                        </a>
                        <div class="section-nav">
                            <button class="section-nav-btn" onclick="scrollCarousel('promoTrack', -1)">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button class="section-nav-btn" onclick="scrollCarousel('promoTrack', 1)">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="games-carousel">
                    <div class="games-track" id="promoTrack">
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
                    <h2 class="section-title">
                        <span class="section-icon"><i class="fas fa-sparkles"></i></span>
                        Novidades
                    </h2>
                    <div class="section-controls">
                        <a href="<?= SITE_URL ?>/pages/busca.php?ordem=recente" class="section-link">
                            Ver todas <i class="fas fa-chevron-right"></i>
                        </a>
                        <div class="section-nav">
                            <button class="section-nav-btn" onclick="scrollCarousel('newTrack', -1)">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button class="section-nav-btn" onclick="scrollCarousel('newTrack', 1)">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="games-carousel">
                    <div class="games-track" id="newTrack">
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
            <section class="free-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <span class="section-icon gift"><i class="fas fa-gift"></i></span>
                        Gratuitos
                    </h2>
                    <a href="<?= SITE_URL ?>/pages/busca.php?gratuito=1" class="section-link">
                        Ver todos <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
                <div class="free-grid">
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
                    <h2 class="section-title">
                        <span class="section-icon fire"><i class="fas fa-fire"></i></span>
                        Populares
                    </h2>
                    <div class="section-controls">
                        <a href="<?= SITE_URL ?>/pages/busca.php?ordem=vendas" class="section-link">
                            Ver todos <i class="fas fa-chevron-right"></i>
                        </a>
                        <div class="section-nav">
                            <button class="section-nav-btn" onclick="scrollCarousel('popularTrack', -1)">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button class="section-nav-btn" onclick="scrollCarousel('popularTrack', 1)">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="games-carousel">
                    <div class="games-track" id="popularTrack">
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
                    <h2 class="section-title">
                        <span class="section-icon star"><i class="fas fa-star"></i></span>
                        Melhor Avaliados
                    </h2>
                    <div class="section-controls">
                        <div class="section-nav">
                            <button class="section-nav-btn" onclick="scrollCarousel('ratedTrack', -1)">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button class="section-nav-btn" onclick="scrollCarousel('ratedTrack', 1)">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="games-carousel">
                    <div class="games-track" id="ratedTrack">
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

        <!-- DESENVOLVEDORES -->
        <?php if (!empty($desenvolvedores)): ?>
            <section class="section">
                <div class="section-header">
                    <h2 class="section-title">
                        <span class="section-icon dev"><i class="fas fa-building"></i></span>
                        Desenvolvedores
                    </h2>
                    <div class="section-controls">
                        <div class="section-nav">
                            <button class="section-nav-btn" onclick="scrollCarousel('devsTrack', -1)">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button class="section-nav-btn" onclick="scrollCarousel('devsTrack', 1)">
                                <i class="fas fa-chevron-right"></i>
                            </button>
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
    // Dados dos banners para atualizar o bottom
    const bannersData = <?= json_encode(array_map(function ($b) {
                            $isPromo = ($b['estilo_banner'] ?? 'simples') === 'promocional';
                            return [
                                'titulo' => $b['texto_principal'] ?? $b['titulo'] ?? 'Confira',
                                'subtitulo' => $b['subtitulo'] ?? '',
                                'url' => $b['url_destino'] ?? '#',
                                'btnText' => $isPromo ? 'Ver ofertas' : 'Saiba mais'
                            ];
                        }, $banners)) ?>;

    // Banner Slider
    const psBanner = {
        track: null,
        slides: [],
        dots: [],
        bottom: null,
        current: 0,
        interval: null,
        autoDelay: 6000,

        init() {
            this.track = document.getElementById('psBannerTrack');
            this.bottom = document.getElementById('psBannerBottom');
            if (!this.track) return;

            this.slides = this.track.querySelectorAll('.ps-banner-slide');
            this.dots = document.querySelectorAll('.ps-banner-dot');

            if (this.slides.length > 1) {
                this.startAuto();

                const slider = this.track.closest('.ps-banner-slider');
                slider.addEventListener('mouseenter', () => clearInterval(this.interval));
                slider.addEventListener('mouseleave', () => this.startAuto());
            }
        },

        goTo(index) {
            if (index >= this.slides.length) index = 0;
            if (index < 0) index = this.slides.length - 1;

            this.current = index;
            this.track.style.transform = `translateX(-${index * 100}%)`;

            this.dots.forEach((dot, i) => dot.classList.toggle('active', i === index));

            // Atualizar bottom
            if (this.bottom && bannersData[index]) {
                const data = bannersData[index];
                this.bottom.querySelector('.ps-banner-bottom-title').textContent = data.titulo;
                this.bottom.querySelector('.ps-banner-bottom-subtitle').textContent = data.subtitulo;
                this.bottom.querySelector('.ps-banner-cta').href = data.url;
                this.bottom.querySelector('.ps-banner-cta-text').textContent = data.btnText;
            }
        },

        next() {
            this.goTo(this.current + 1);
        },
        prev() {
            this.goTo(this.current - 1);
        },
        startAuto() {
            this.interval = setInterval(() => this.next(), this.autoDelay);
        }
    };

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
            this.interval = setInterval(() => this.next(), 5000);
        },
        resetAuto() {
            clearInterval(this.interval);
            this.startAuto();
        },
        init() {
            if (this.slides.length > 1) this.startAuto();
        }
    };

    // Scroll Carousel
    function scrollCarousel(id, direction) {
        const track = document.getElementById(id);
        if (!track) return;
        const item = track.firstElementChild;
        const itemWidth = item ? item.offsetWidth + parseInt(getComputedStyle(track).gap) : 200;
        track.scrollBy({
            left: itemWidth * 3 * direction,
            behavior: 'smooth'
        });
    }

    // Drag to Scroll
    function initDragScroll(selector) {
        document.querySelectorAll(selector).forEach(el => {
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

            el.style.cursor = 'grab';
        });
    }

    // Touch Swipe
    function initTouchSwipe(element, slider) {
        if (!element) return;
        let touchStartX = 0;

        element.addEventListener('touchstart', e => {
            touchStartX = e.changedTouches[0].screenX;
        }, {
            passive: true
        });

        element.addEventListener('touchend', e => {
            const diff = touchStartX - e.changedTouches[0].screenX;
            if (Math.abs(diff) > 50) {
                diff > 0 ? slider.next() : slider.prev();
            }
        }, {
            passive: true
        });
    }

    // Init
    document.addEventListener('DOMContentLoaded', () => {
        psBanner.init();
        heroSlider.init();
        initDragScroll('.games-track, .categories-track, .devs-track');

        const bannerSlider = document.querySelector('.ps-banner-slider');
        if (bannerSlider) initTouchSwipe(bannerSlider, psBanner);

        const heroMain = document.querySelector('.hero-main');
        if (heroMain) initTouchSwipe(heroMain, heroSlider);
    });

    // Keyboard
    document.addEventListener('keydown', e => {
        if (e.key === 'ArrowLeft') psBanner.prev();
        else if (e.key === 'ArrowRight') psBanner.next();
    });
</script>

<?php require_once '../includes/footer.php'; ?>