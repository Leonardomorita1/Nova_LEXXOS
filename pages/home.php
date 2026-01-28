<?php
// pages/home.php - Versão com Personalidade Gaming
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

// Preenche imagens para categorias
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
   HOME PAGE - GAMING IDENTITY
   ============================================ */
.home-wrapper {
    background: var(--bg-primary);
    min-height: 100vh;
    position: relative;
}

.home-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 24px 80px;
}

/* ============================================
   BANNER PRINCIPAL - ESTILO GAMING
   ============================================ */
.promo-banner-section {
    position: relative;
    width: 100%;
    margin-bottom: 48px;
}

.promo-banner-slider {
    position: relative;
    width: 100%;
    height: 480px;
    overflow: hidden;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
}

.promo-banner-track {
    display: flex;
    transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    height: 100%;
}

.promo-banner-slide {
    min-width: 100%;
    height: 100%;
    position: relative;
    overflow: hidden;
}

/* Background Layer */
.banner-bg-layer {
    position: absolute;
    inset: 0;
    overflow: hidden;
}

.banner-bg-layer img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transform: scale(1.1);
}

/* Gaming Gradient Overlay */
.banner-gradient-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(
        135deg,
        rgba(var(--banner-rgb, 19, 19, 20), 0.98) 0%,
        rgba(var(--banner-rgb, 19, 19, 20), 0.85) 30%,
        rgba(var(--banner-rgb, 19, 19, 20), 0.6) 60%,
        rgba(var(--banner-rgb, 19, 19, 20), 0.3) 100%
    );
}

/* Decorative Gaming Elements */
.banner-decor {
    position: absolute;
    inset: 0;
    pointer-events: none;
    overflow: hidden;
}

/* Glowing Orbs */
.banner-decor::before {
    content: '';
    position: absolute;
    width: 600px;
    height: 600px;
    background: radial-gradient(circle, var(--banner-accent, #4C8BF5) 0%, transparent 70%);
    opacity: 0.15;
    top: -200px;
    right: -100px;
    animation: pulse-glow 4s ease-in-out infinite;
}

.banner-decor::after {
    content: '';
    position: absolute;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, var(--banner-accent, #4C8BF5) 0%, transparent 70%);
    opacity: 0.1;
    bottom: -100px;
    left: 20%;
    animation: pulse-glow 4s ease-in-out infinite 2s;
}

@keyframes pulse-glow {
    0%, 100% { transform: scale(1); opacity: 0.15; }
    50% { transform: scale(1.1); opacity: 0.2; }
}

/* Geometric Lines */
.banner-lines {
    position: absolute;
    inset: 0;
    overflow: hidden;
    opacity: 0.1;
}

.banner-lines::before,
.banner-lines::after {
    content: '';
    position: absolute;
    background: linear-gradient(90deg, transparent, var(--banner-accent, #4C8BF5), transparent);
    height: 1px;
}

.banner-lines::before {
    width: 60%;
    top: 30%;
    right: 0;
    transform: rotate(-5deg);
}

.banner-lines::after {
    width: 40%;
    bottom: 25%;
    left: 0;
    transform: rotate(3deg);
}

/* ============================================
   BANNER CONTENT LAYOUT
   ============================================ */
.banner-content-wrapper {
    position: relative;
    z-index: 10;
    height: 100%;
    display: grid;
    grid-template-columns: 1fr 1fr;
    align-items: center;
    padding: 50px 60px;
    max-width: 1400px;
    margin: 0 auto;
    gap: 40px;
}

.banner-text-content {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

/* Event Tag */
.banner-event-tag {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    padding: 8px 16px;
    border-radius: 50px;
    border: 1px solid rgba(255, 255, 255, 0.15);
    width: fit-content;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--banner-text, #fff);
}

.banner-event-tag i {
    color: var(--banner-accent, #4C8BF5);
}

/* Main Title - Big Impact */
.banner-main-title {
    font-size: clamp(36px, 5vw, 64px);
    font-weight: 900;
    line-height: 1;
    color: var(--banner-text, #fff);
    text-transform: uppercase;
    letter-spacing: -2px;
    margin: 0;
    text-shadow: 0 4px 30px rgba(0, 0, 0, 0.5);
}

.banner-main-title .highlight {
    color: var(--banner-accent, #4C8BF5);
    display: block;
    text-shadow: 0 0 40px var(--banner-accent);
}

/* Countdown/Date Info */
.banner-date-info {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 14px;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.8);
}

.banner-date-badge {
    background: var(--banner-accent, #4C8BF5);
    color: #fff;
    padding: 6px 14px;
    border-radius: 6px;
    font-weight: 700;
    font-size: 13px;
}

/* Discount Display - Gaming Style */
.banner-discount-display {
    display: flex;
    align-items: flex-end;
    gap: 12px;
    margin: 8px 0;
}

.discount-label {
    font-size: 14px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 2px;
    color: rgba(255, 255, 255, 0.7);
    writing-mode: vertical-lr;
    transform: rotate(180deg);
    padding-bottom: 4px;
}

.discount-value-wrapper {
    position: relative;
}

.discount-value {
    font-size: clamp(60px, 8vw, 100px);
    font-weight: 900;
    color: var(--banner-accent, #4C8BF5);
    line-height: 1;
    text-shadow: 0 0 60px var(--banner-accent);
}

.discount-percent {
    font-size: clamp(24px, 3vw, 36px);
    font-weight: 800;
    color: var(--banner-accent, #4C8BF5);
    vertical-align: super;
}

/* CTA Button - Gaming Style */
.banner-cta-btn {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    background: var(--banner-accent, #4C8BF5);
    color: #fff;
    padding: 16px 32px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 15px;
    text-transform: uppercase;
    letter-spacing: 1px;
    text-decoration: none;
    width: fit-content;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 
        0 8px 32px rgba(76, 139, 245, 0.4),
        inset 0 1px 0 rgba(255, 255, 255, 0.2);
    position: relative;
    overflow: hidden;
}

.banner-cta-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.banner-cta-btn:hover::before {
    left: 100%;
}

.banner-cta-btn:hover {
    transform: translateY(-3px) scale(1.02);
    box-shadow: 
        0 16px 48px rgba(76, 139, 245, 0.5),
        inset 0 1px 0 rgba(255, 255, 255, 0.3);
}

.banner-cta-btn i {
    transition: transform 0.3s;
}

.banner-cta-btn:hover i {
    transform: translateX(4px);
}

/* Featured Image Side */
.banner-featured-side {
    position: relative;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.banner-featured-image {
    max-width: 100%;
    max-height: 400px;
    object-fit: contain;
    filter: drop-shadow(0 30px 60px rgba(0, 0, 0, 0.6));
    animation: float-gaming 5s ease-in-out infinite;
    position: relative;
    z-index: 2;
}

@keyframes float-gaming {
    0%, 100% {
        transform: translateY(0) rotate(-1deg);
    }
    50% {
        transform: translateY(-20px) rotate(1deg);
    }
}

/* Glow behind image */
.banner-image-glow {
    position: absolute;
    width: 80%;
    height: 80%;
    background: radial-gradient(ellipse, var(--banner-accent, #4C8BF5) 0%, transparent 70%);
    opacity: 0.3;
    filter: blur(60px);
    animation: glow-pulse 3s ease-in-out infinite;
}

@keyframes glow-pulse {
    0%, 100% { opacity: 0.3; transform: scale(1); }
    50% { opacity: 0.4; transform: scale(1.1); }
}

/* Simple Banner Style */
.banner-simple-content {
    position: relative;
    z-index: 10;
    max-width: 650px;
    padding: 60px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.banner-simple-title {
    font-size: 42px;
    font-weight: 800;
    color: #fff;
    margin: 0;
    line-height: 1.15;
}

.banner-simple-desc {
    font-size: 17px;
    color: rgba(255, 255, 255, 0.7);
    margin: 0;
    line-height: 1.6;
}

/* Navigation Arrows */
.banner-nav-arrow {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: #fff;
    width: 52px;
    height: 52px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s;
    z-index: 20;
    font-size: 18px;
}

.banner-nav-arrow:hover {
    background: var(--accent);
    border-color: var(--accent);
    transform: translateY(-50%) scale(1.1);
}

.banner-nav-arrow.prev { left: 24px; }
.banner-nav-arrow.next { right: 24px; }

/* Navigation Dots */
.banner-dots {
    display: flex;
    justify-content: center;
    gap: 12px;
    margin-top: 20px;
}

.banner-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    cursor: pointer;
    transition: all 0.3s;
    position: relative;
}

.banner-dot::after {
    content: '';
    position: absolute;
    inset: -4px;
    border-radius: 50%;
    border: 2px solid transparent;
    transition: border-color 0.3s;
}

.banner-dot.active {
    background: var(--accent);
    width: 36px;
    border-radius: 6px;
}

.banner-dot.active::after {
    border-color: var(--accent);
    border-radius: 10px;
}

.banner-dot:hover:not(.active) {
    background: rgba(255, 255, 255, 0.4);
}

/* ============================================
   HERO - FEATURED GAMES
   ============================================ */
.hero-section {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 20px;
    margin-bottom: 56px;
}

.hero-main {
    position: relative;
    border-radius: 16px;
    overflow: hidden;
    aspect-ratio: 16 / 9;
    background: var(--bg-secondary);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
}

.hero-slide {
    position: absolute;
    inset: 0;
    opacity: 0;
    visibility: hidden;
    transition: all 0.6s ease;
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
    background: linear-gradient(
        to top,
        rgba(0, 0, 0, 0.95) 0%,
        rgba(0, 0, 0, 0.5) 40%,
        rgba(0, 0, 0, 0.2) 70%,
        transparent 100%
    );
}

.hero-content {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 36px;
    z-index: 2;
}

.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--accent);
    color: #000;
    padding: 8px 14px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 14px;
}

.hero-badge.promo {
    background: linear-gradient(135deg, #00d26a, #00a854);
    color: #fff;
}

.hero-title {
    font-size: 2.2rem;
    font-weight: 800;
    color: #fff;
    margin: 0 0 10px;
    line-height: 1.15;
    text-shadow: 0 2px 20px rgba(0,0,0,0.5);
}

.hero-dev {
    font-size: 14px;
    color: rgba(255, 255, 255, 0.6);
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.hero-dev::before {
    content: '';
    width: 20px;
    height: 2px;
    background: var(--accent);
    border-radius: 2px;
}

.hero-price-row {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 22px;
}

.hero-discount {
    background: linear-gradient(135deg, #00d26a, #00a854);
    color: #fff;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 15px;
    font-weight: 800;
}

.hero-old-price {
    color: rgba(255, 255, 255, 0.4);
    text-decoration: line-through;
    font-size: 15px;
}

.hero-price {
    font-size: 26px;
    font-weight: 800;
    color: #fff;
}

.hero-price.free {
    color: #00d26a;
}

.hero-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: var(--accent);
    color: #000;
    padding: 14px 28px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: all 0.3s;
}

.hero-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(76, 139, 245, 0.4);
}

/* Hero Sidebar */
.hero-sidebar {
    display: flex;
    flex-direction: column;
    gap: 10px;
    background: var(--bg-secondary);
    border-radius: 16px;
    padding: 14px;
    border: 1px solid var(--border);
}

.hero-sidebar-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.25s;
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
    width: 60px;
    height: 60px;
    border-radius: 8px;
    overflow: hidden;
    flex-shrink: 0;
}

.hero-sidebar-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.hero-sidebar-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 4px;
}

.hero-sidebar-price {
    font-size: 13px;
    color: var(--text-secondary);
}

.hero-sidebar-price .discount {
    color: #00d26a;
    font-weight: 700;
}

/* Hero Arrows */
.hero-arrows {
    position: absolute;
    top: 50%;
    left: 20px;
    right: 20px;
    transform: translateY(-50%);
    display: flex;
    justify-content: space-between;
    pointer-events: none;
    z-index: 10;
}

.hero-arrow {
    width: 48px;
    height: 48px;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    pointer-events: auto;
    transition: all 0.25s;
}

.hero-arrow:hover {
    background: var(--accent);
    border-color: var(--accent);
    transform: scale(1.1);
}

/* ============================================
   SECTION HEADERS - GAMING STYLE
   ============================================ */
.section {
    margin-bottom: 56px;
    position: relative;
}

.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    position: relative;
}

.section-title {
    display: flex;
    align-items: center;
    gap: 14px;
    font-size: 22px;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
    position: relative;
}

.section-title-icon {
    width: 42px;
    height: 42px;
    background: linear-gradient(135deg, var(--accent), #667eea);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    color: #fff;
    box-shadow: 0 4px 16px rgba(76, 139, 245, 0.3);
}

.section-title-icon.fire {
    background: linear-gradient(135deg, #ff6b6b, #ee5a5a);
    box-shadow: 0 4px 16px rgba(255, 107, 107, 0.3);
}

.section-title-icon.star {
    background: linear-gradient(135deg, #f9ca24, #f0932b);
    box-shadow: 0 4px 16px rgba(249, 202, 36, 0.3);
}

.section-title-icon.gift {
    background: linear-gradient(135deg, #00d26a, #00a854);
    box-shadow: 0 4px 16px rgba(0, 210, 106, 0.3);
}

.section-title-icon.dev {
    background: linear-gradient(135deg, #9147ff, #772ce8);
    box-shadow: 0 4px 16px rgba(145, 71, 255, 0.3);
}

.section-controls {
    display: flex;
    align-items: center;
    gap: 16px;
}

.section-link {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--accent);
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
    padding: 8px 16px;
    border-radius: 8px;
    background: rgba(76, 139, 245, 0.1);
}

.section-link:hover {
    background: rgba(76, 139, 245, 0.2);
}

.section-link i {
    transition: transform 0.2s;
}

.section-link:hover i {
    transform: translateX(4px);
}

.section-nav {
    display: flex;
    gap: 8px;
}

.section-nav-btn {
    width: 40px;
    height: 40px;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 10px;
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
    background: rgba(76, 139, 245, 0.1);
}

/* ============================================
   SHOWCASE CARDS - GAMING STYLE
   ============================================ */
.showcase-cards-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-bottom: 56px;
}

.showcase-card {
    position: relative;
    background: var(--bg-secondary);
    border-radius: 20px;
    overflow: hidden;
    border: 1px solid var(--border);
    text-decoration: none;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.showcase-card:hover {
    border-color: var(--accent);
    transform: translateY(-6px);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
}

.showcase-card.sale-card:hover {
    border-color: #00d26a;
    box-shadow: 0 20px 50px rgba(0, 210, 106, 0.15);
}

/* Showcase Images */
.showcase-images {
    position: relative;
    height: 200px;
    perspective: 1000px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, rgba(76, 139, 245, 0.1) 0%, transparent 100%);
}

.showcase-card.sale-card .showcase-images {
    background: linear-gradient(135deg, rgba(0, 210, 106, 0.15) 0%, rgba(0, 100, 50, 0.1) 100%);
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
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.4);
    transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

.showcase-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

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
    box-shadow: 0 16px 48px rgba(76, 139, 245, 0.3);
}

.showcase-card.sale-card .showcase-img:nth-child(3) {
    box-shadow: 0 16px 48px rgba(0, 210, 106, 0.3);
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

.showcase-card:hover .showcase-img:nth-child(1) {
    transform: translateX(-155px) rotateY(30deg) scale(0.7);
}

.showcase-card:hover .showcase-img:nth-child(2) {
    transform: translateX(-78px) rotateY(18deg) scale(0.82);
}

.showcase-card:hover .showcase-img:nth-child(3) {
    transform: translateX(0) rotateY(0deg) scale(1.08);
}

.showcase-card:hover .showcase-img:nth-child(4) {
    transform: translateX(78px) rotateY(-18deg) scale(0.82);
}

.showcase-card:hover .showcase-img:nth-child(5) {
    transform: translateX(155px) rotateY(-30deg) scale(0.7);
}

/* Showcase Info */
.showcase-info {
    padding: 24px 28px;
    border-top: 1px solid var(--border);
}

.showcase-tag {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 6px 12px;
    border-radius: 6px;
    margin-bottom: 12px;
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
    font-size: 20px;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 8px;
}

.showcase-desc {
    font-size: 14px;
    color: var(--text-secondary);
    margin: 0;
    line-height: 1.5;
}

/* ============================================
   CATEGORY CARDS
   ============================================ */
.category-scroll {
    display: flex;
    gap: 16px;
    overflow-x: auto;
    padding-bottom: 20px;
    scrollbar-width: none;
}
.category-scroll::-webkit-scrollbar { display: none; }

.cat-tile {
    flex: 0 0 200px;
    height: 120px;
    background: var(--bg-secondary);
    border-radius: 12px;
    position: relative;
    overflow: hidden;
    text-decoration: none;
    border: 1px solid var(--border);
    transition: 0.3s;
}

/* Imagens de fundo em colagem */
.cat-bg-collage {
    position: absolute;
    inset: 0;
    opacity: 0.3;
    display: flex;
    filter: blur(2px) grayscale(100%);
    transition: 0.3s;
}

.cat-bg-collage img {
    width: 33.33%;
    height: 100%;
    object-fit: cover;
}

.cat-content {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    z-index: 2;
    background: linear-gradient(to bottom, rgba(0,0,0,0.2), rgba(0,0,0,0.8));
}

.cat-icon { font-size: 24px; color: #fff; margin-bottom: 8px; }
.cat-name { font-size: 16px; font-weight: 700; color: #fff; text-shadow: 0 2px 4px rgba(0,0,0,0.5); }
.cat-count { font-size: 11px; color: rgba(255,255,255,0.7); background: rgba(0,0,0,0.5); padding: 2px 8px; border-radius: 10px; margin-top: 4px; }

.cat-tile:hover {
    border-color: var(--accent);
    transform: translateY(-4px);
}
.cat-tile:hover .cat-bg-collage {
    opacity: 0.6;
    filter: blur(0) grayscale(0%);
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
   FREE GAMES SECTION
   ============================================ */
.free-games-section {
    background: linear-gradient(135deg, rgba(0, 210, 106, 0.08) 0%, rgba(0, 168, 84, 0.03) 100%);
    border: 1px solid rgba(0, 210, 106, 0.2);
    border-radius: 20px;
    padding: 32px;
    margin-bottom: 56px;
    position: relative;
    overflow: hidden;
}

.free-games-section::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(0, 210, 106, 0.15) 0%, transparent 70%);
    pointer-events: none;
}

.free-games-section .section-title {
    color: #00d26a;
}

.free-games-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-top: 24px;
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
    border-radius: 16px;
    padding: 28px 20px;
    text-align: center;
    text-decoration: none;
    transition: all 0.3s;
}

.dev-card:hover {
    border-color: #9147ff;
    transform: translateY(-6px);
    box-shadow: 0 12px 40px rgba(145, 71, 255, 0.15);
}

.dev-logo {
    width: 64px;
    height: 64px;
    border-radius: 16px;
    object-fit: cover;
    margin: 0 auto 14px;
    background: var(--bg-primary);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
}

.dev-name {
    font-size: 15px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.dev-name i {
    color: #9147ff;
    font-size: 12px;
}

.dev-count {
    font-size: 13px;
    color: var(--text-secondary);
}

/* ============================================
   RESPONSIVE
   ============================================ */
@media (max-width: 1200px) {
    .hero-section {
        grid-template-columns: 1fr 300px;
    }

    .showcase-cards-row {
        grid-template-columns: 1fr;
    }

    .banner-content-wrapper {
        padding: 40px;
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

    .banner-content-wrapper {
        grid-template-columns: 1fr;
        text-align: center;
    }

    .banner-text-content {
        align-items: center;
    }

    .banner-featured-side {
        display: none;
    }

    .free-games-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .home-container {
        padding: 0 16px 60px;
    }

    .promo-banner-slider {
        height: 400px;
        border-radius: 16px;
    }

    .banner-content-wrapper {
        padding: 30px 24px;
    }

    .banner-main-title {
        font-size: 32px;
    }

    .discount-value {
        font-size: 56px;
    }

    .banner-nav-arrow {
        width: 40px;
        height: 40px;
    }

    .hero-main {
        aspect-ratio: 4 / 3;
        border-radius: 12px;
    }

    .hero-content {
        padding: 24px;
    }

    .hero-title {
        font-size: 1.5rem;
    }

    .hero-arrows {
        display: none;
    }

    .section-title {
        font-size: 18px;
    }

    .section-title-icon {
        width: 36px;
        height: 36px;
        font-size: 16px;
    }

    .section-nav {
        display: none;
    }

    .category-card {
        flex: 0 0 250px;
        min-width: 250px;
    }

    .showcase-images {
        height: 160px;
    }

    .showcase-img {
        width: 85px;
        height: 115px;
    }

    .games-carousel-track .ps-card {
        flex: 0 0 155px;
        min-width: 155px;
    }

    .dev-card {
        flex: 0 0 180px;
        min-width: 180px;
    }

    .free-games-section {
        padding: 24px;
    }

    .free-games-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
}

@media (max-width: 480px) {
    .promo-banner-slider {
        height: 360px;
        border-radius: 12px;
    }

    .banner-main-title {
        font-size: 26px;
    }

    .discount-value {
        font-size: 48px;
    }

    .banner-cta-btn {
        padding: 14px 24px;
        font-size: 13px;
    }

    .hero-main {
        aspect-ratio: 1 / 1;
    }

    .hero-title {
        font-size: 1.2rem;
    }

    .category-card {
        flex: 0 0 220px;
        min-width: 220px;
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
                        $hasOverlay = !empty($banner['imagem_overlay']);
                        $isPromocional = ($banner['estilo_banner'] ?? 'simples') === 'promocional';
                        
                        $corFundo = $banner['cor_fundo'] ?? '#131314';
                        $corTexto = $banner['cor_texto'] ?? '#ffffff';
                        $corDestaque = $banner['cor_destaque'] ?? '#4C8BF5';
                        
                        // Convert hex to RGB for gradients
                        $rgb = sscanf($corFundo, "#%02x%02x%02x");
                        $rgbString = $rgb ? implode(', ', $rgb) : '19, 19, 20';
                    ?>
                    <div class="promo-banner-slide" 
                         style="--banner-rgb: <?= $rgbString ?>; --banner-text: <?= $corTexto ?>; --banner-accent: <?= $corDestaque ?>;">
                        
                        <!-- Background Layer -->
                        <div class="banner-bg-layer">
                            <?php if ($hasImage): ?>
                                <img src="<?= htmlspecialchars($banner['imagem_desktop']) ?>" 
                                     alt="" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>">
                            <?php endif; ?>
                            <div class="banner-gradient-overlay"></div>
                        </div>
                        
                        <!-- Decorative Elements -->
                        <div class="banner-decor"></div>
                        <div class="banner-lines"></div>

                        <?php if ($isPromocional): ?>
                        <!-- Promotional Layout -->
                        <div class="banner-content-wrapper">
                            <div class="banner-text-content">
                                <!-- Event Tag -->
                                <div class="banner-event-tag">
                                    <i class="fas fa-bolt"></i>
                                    <?= !empty($banner['texto_principal']) ? htmlspecialchars($banner['texto_principal']) : 'Evento Especial' ?>
                                </div>
                                
                                <!-- Main Title -->
                                <h2 class="banner-main-title">
                                    <?php 
                                    $titulo = htmlspecialchars($banner['titulo'] ?? 'OFERTA ESPECIAL');
                                    $palavras = explode(' ', $titulo);
                                    if (count($palavras) > 1) {
                                        $ultima = array_pop($palavras);
                                        echo implode(' ', $palavras);
                                        echo '<span class="highlight">' . $ultima . '</span>';
                                    } else {
                                        echo $titulo;
                                    }
                                    ?>
                                </h2>
                                
                                <!-- Date Info -->
                                <?php if (!empty($banner['data_fim'])): ?>
                                <div class="banner-date-info">
                                    <span>Termina em</span>
                                    <span class="banner-date-badge">
                                        <?= date('d/m', strtotime($banner['data_fim'])) ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Discount Display -->
                                <?php if (!empty($banner['texto_destaque'])): ?>
                                <div class="banner-discount-display">
                                    <?php if (!empty($banner['texto_secundario'])): ?>
                                    <span class="discount-label"><?= htmlspecialchars($banner['texto_secundario']) ?></span>
                                    <?php endif; ?>
                                    <div class="discount-value-wrapper">
                                        <span class="discount-value"><?= preg_replace('/[^0-9]/', '', $banner['texto_destaque']) ?></span>
                                        <span class="discount-percent">%</span>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- CTA Button -->
                                <a href="<?= htmlspecialchars($banner['url_destino'] ?? '#') ?>" class="banner-cta-btn">
                                    Ver Ofertas
                                    <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>

                            <!-- Featured Image -->
                            <?php if ($hasOverlay): ?>
                            <div class="banner-featured-side">
                                <div class="banner-image-glow"></div>
                                <img src="<?= htmlspecialchars($banner['imagem_overlay']) ?>" 
                                     alt="" class="banner-featured-image"
                                     loading="<?= $i === 0 ? 'eager' : 'lazy' ?>">
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php else: ?>
                        <!-- Simple Layout -->
                        <div class="banner-simple-content">
                            <h2 class="banner-simple-title">
                                <?= htmlspecialchars($banner['titulo'] ?? 'Oferta Especial') ?>
                            </h2>
                            <p class="banner-simple-desc">
                                <?= htmlspecialchars($banner['subtitulo'] ?? 'Confira as melhores ofertas') ?>
                            </p>
                            <a href="<?= htmlspecialchars($banner['url_destino'] ?? '#') ?>" class="banner-cta-btn">
                                Ver mais <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if (count($banners) > 1): ?>
                <button class="banner-nav-arrow prev" onclick="promoBanner.prev()">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="banner-nav-arrow next" onclick="promoBanner.next()">
                    <i class="fas fa-chevron-right"></i>
                </button>
                <?php endif; ?>
            </div>

            <?php if (count($banners) > 1): ?>
            <div class="banner-dots">
                <?php for ($i = 0; $i < count($banners); $i++): ?>
                <div class="banner-dot <?= $i === 0 ? 'active' : '' ?>" onclick="promoBanner.goTo(<?= $i ?>)"></div>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <!-- HERO - Featured Games -->
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
                            <?= $em_promocao ? 'Em Oferta' : 'Destaque' ?>
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

        <!-- SHOWCASE CARDS -->
        <section class="showcase-cards-row">
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
                    <p class="showcase-desc">Descubra os jogos mais recentes da plataforma</p>
                </div>
            </a>

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
                            <img src="<?= SITE_URL . $jogo['imagem_capa'] ?>" alt="" loading="lazy">
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

        <!-- CATEGORIAS -->
        <?php if (!empty($categorias)): ?>
        <section class="mb-5">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-th-large"></i> Navegar por Gênero</h2>
                <a href="<?= SITE_URL ?>/pages/categorias.php" class="section-link">Ver todas <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="category-scroll">
                <?php foreach ($categorias as $cat): ?>
                <a href="<?= SITE_URL ?>/pages/categoria.php?slug=<?= $cat['slug'] ?>" class="cat-tile">
                    <div class="cat-bg-collage">
                        <?php for($k=0; $k<3; $k++): 
                            $img = $cat['covers'][$k] ?? null;
                            if($img): ?>
                                <img src="<?= SITE_URL . $img ?>">
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
        </section>
        <?php endif; ?>

        <!-- PROMOÇÕES -->
        <?php if (!empty($promocoes)): ?>
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">
                    <span class="section-title-icon gift"><i class="fas fa-bolt"></i></span>
                    Ofertas Imperdíveis
                </h2>
                <div class="section-controls">
                    <a href="<?= SITE_URL ?>/pages/busca.php?promocao=1" class="section-link">
                        Ver Todas <i class="fas fa-arrow-right"></i>
                    </a>
                    <div class="section-nav">
                        <button class="section-nav-btn" onclick="scrollCarousel('promoCarousel', -1)">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button class="section-nav-btn" onclick="scrollCarousel('promoCarousel', 1)">
                            <i class="fas fa-chevron-right"></i>
                        </button>
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
                <h2 class="section-title">
                    <span class="section-title-icon"><i class="fas fa-sparkles"></i></span>
                    Novidades
                </h2>
                <div class="section-controls">
                    <a href="<?= SITE_URL ?>/pages/busca.php?ordem=recente" class="section-link">
                        Ver Todos <i class="fas fa-arrow-right"></i>
                    </a>
                    <div class="section-nav">
                        <button class="section-nav-btn" onclick="scrollCarousel('newCarousel', -1)">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button class="section-nav-btn" onclick="scrollCarousel('newCarousel', 1)">
                            <i class="fas fa-chevron-right"></i>
                        </button>
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
                <h2 class="section-title">
                    <span class="section-title-icon gift"><i class="fas fa-gift"></i></span>
                    Jogos Gratuitos
                </h2>
                <a href="<?= SITE_URL ?>/pages/busca.php?gratuito=1" class="section-link">
                    Ver Todos <i class="fas fa-arrow-right"></i>
                </a>
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
                <h2 class="section-title">
                    <span class="section-title-icon fire"><i class="fas fa-fire"></i></span>
                    Mais Populares
                </h2>
                <div class="section-controls">
                    <a href="<?= SITE_URL ?>/pages/busca.php?ordem=vendas" class="section-link">
                        Ver Todos <i class="fas fa-arrow-right"></i>
                    </a>
                    <div class="section-nav">
                        <button class="section-nav-btn" onclick="scrollCarousel('popularCarousel', -1)">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button class="section-nav-btn" onclick="scrollCarousel('popularCarousel', 1)">
                            <i class="fas fa-chevron-right"></i>
                        </button>
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
                <h2 class="section-title">
                    <span class="section-title-icon star"><i class="fas fa-star"></i></span>
                    Melhor Avaliados
                </h2>
                <div class="section-controls">
                    <div class="section-nav">
                        <button class="section-nav-btn" onclick="scrollCarousel('ratedCarousel', -1)">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button class="section-nav-btn" onclick="scrollCarousel('ratedCarousel', 1)">
                            <i class="fas fa-chevron-right"></i>
                        </button>
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

        <!-- DESENVOLVEDORES -->
        <?php if (!empty($desenvolvedores)): ?>
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">
                    <span class="section-title-icon dev"><i class="fas fa-building"></i></span>
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
// Banner Carousel
const promoBanner = {
    track: null,
    slides: [],
    dots: [],
    currentIndex: 0,
    interval: null,

    init() {
        this.track = document.getElementById('promoBannerTrack');
        if (!this.track) return;

        this.slides = this.track.querySelectorAll('.promo-banner-slide');
        this.dots = document.querySelectorAll('.banner-dot');

        if (this.slides.length > 1) {
            this.startAutoSlide();
        }
    },

    goTo(index) {
        this.currentIndex = index;
        this.updateSlide();
    },

    next() {
        this.currentIndex = (this.currentIndex + 1) % this.slides.length;
        this.updateSlide();
        this.resetAutoSlide();
    },

    prev() {
        this.currentIndex = (this.currentIndex - 1 + this.slides.length) % this.slides.length;
        this.updateSlide();
        this.resetAutoSlide();
    },

    updateSlide() {
        const offset = -this.currentIndex * 100;
        this.track.style.transform = `translateX(${offset}%)`;

        this.dots.forEach((dot, i) => {
            dot.classList.toggle('active', i === this.currentIndex);
        });
    },

    startAutoSlide() {
        this.interval = setInterval(() => this.next(), 6000);
    },

    resetAutoSlide() {
        clearInterval(this.interval);
        this.startAutoSlide();
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
    next() { this.goTo(this.current + 1); },
    prev() { this.goTo(this.current - 1); },
    startAuto() { this.interval = setInterval(() => this.next(), 6000); },
    resetAuto() {
        clearInterval(this.interval);
        this.startAuto();
    },
    init() {
        if (this.slides.length > 1) this.startAuto();
    }
};

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    promoBanner.init();
    heroSlider.init();
});

// Carousel Scroll
function scrollCarousel(id, dir) {
    const el = document.getElementById(id);
    if (!el) return;

    const firstItem = el.firstElementChild;
    const itemWidth = firstItem ? firstItem.offsetWidth + 16 : 200;
    const scrollAmount = itemWidth * 3;

    el.scrollBy({
        left: scrollAmount * dir,
        behavior: 'smooth'
    });
}

// Drag to scroll
document.querySelectorAll('.games-carousel-track, .categories-track, .devs-track').forEach(el => {
    let isDown = false, startX, scrollLeft;

    el.addEventListener('mousedown', e => {
        isDown = true;
        el.style.cursor = 'grabbing';
        startX = e.pageX - el.offsetLeft;
        scrollLeft = el.scrollLeft;
    });

    el.addEventListener('mouseleave', () => { isDown = false; el.style.cursor = 'grab'; });
    el.addEventListener('mouseup', () => { isDown = false; el.style.cursor = 'grab'; });

    el.addEventListener('mousemove', e => {
        if (!isDown) return;
        e.preventDefault();
        el.scrollLeft = scrollLeft - (e.pageX - el.offsetLeft - startX) * 2;
    });

    el.style.cursor = 'grab';
});
</script>

<?php require_once '../includes/footer.php'; ?>