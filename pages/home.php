<?php
// pages/home.php - Estilo Minimalista PlayStation
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../components/game-card.php'; // Seu componente global

$database = new Database();
$pdo = $database->getConnection();
$user_id = isLoggedIn() ? $_SESSION['user_id'] : null;

// --- FUNÇÕES AUXILIARES ---
if (!function_exists('formatPrice')) {
    function formatPrice($centavos) {
        return 'R$ ' . number_format($centavos / 100, 2, ',', '.');
    }
}

// --- PRE-LOAD USER DATA (Performance) ---
$meus_jogos = [];
$minha_wishlist = [];
$meu_carrinho = [];

if ($user_id) {
    try {
        $stmt = $pdo->prepare("SELECT jogo_id FROM biblioteca WHERE usuario_id = ?");
        $stmt->execute([$user_id]);
        $meus_jogos = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $stmt = $pdo->prepare("SELECT jogo_id FROM lista_desejos WHERE usuario_id = ?");
        $stmt->execute([$user_id]);
        $minha_wishlist = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        
        $stmt = $pdo->prepare("SELECT jogo_id FROM carrinho WHERE usuario_id = ?");
        $stmt->execute([$user_id]);
        $meu_carrinho = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Exception $e) {}
}

// --- HERO BANNERS ---
try {
    $stmt = $pdo->query("SELECT * FROM banner WHERE ativo = 1 ORDER BY ordem LIMIT 5");
    $banners = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) { $banners = []; }

// Jogos em destaque para hero
try {
    $stmt = $pdo->query("
        SELECT j.*, d.nome_estudio 
        FROM jogo j 
        LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id 
        WHERE j.status = 'publicado' AND j.destaque = 1 
        ORDER BY j.criado_em DESC LIMIT 5
    ");
    $jogos_destaque = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) { $jogos_destaque = []; }

// Combina para hero
$hero_items = [];
foreach ($banners as $b) {
    $hero_items[] = [
        'tipo' => 'banner',
        'titulo' => $b['titulo'] ?? 'Banner',
        'subtitulo' => $b['subtitulo'] ?? '',
        'imagem' => $b['imagem_desktop'] ?? '',
        'url' => $b['url_destino'] ?? '#',
        'badge' => 'Novidade'
    ];
}
foreach ($jogos_destaque as $j) {
    $img = $j['imagem_banner'] ?: $j['imagem_capa'];
    $hero_items[] = [
        'tipo' => 'jogo',
        'titulo' => $j['titulo'],
        'subtitulo' => $j['descricao_curta'],
        'imagem' => SITE_URL . $img,
        'url' => SITE_URL . '/pages/jogo.php?slug=' . $j['slug'],
        'badge' => $j['em_promocao'] ? 'Promoção' : 'Destaque',
        'preco' => $j['preco_centavos'],
        'preco_promo' => $j['preco_promocional_centavos'],
        'em_promocao' => $j['em_promocao']
    ];
}

if (empty($hero_items)) {
    $hero_items[] = [
        'tipo' => 'banner',
        'titulo' => 'Bem-vindo à ' . SITE_NAME,
        'subtitulo' => 'Descubra jogos incríveis',
        'imagem' => SITE_URL . '/assets/images/default-banner.jpg',
        'url' => SITE_URL . '/pages/busca.php',
        'badge' => 'Explore'
    ];
}

// --- CATEGORIAS ---
try {
    $stmt = $pdo->query("
        SELECT c.*, 
            (SELECT COUNT(*) FROM jogo_categoria jc 
             JOIN jogo j ON jc.jogo_id = j.id 
             WHERE jc.categoria_id = c.id AND j.status = 'publicado') as total_jogos 
        FROM categoria c WHERE c.ativa = 1 ORDER BY c.ordem, c.nome
    ");
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) { $categorias = []; }

// --- LANÇAMENTOS ---
try {
    $stmt = $pdo->query("
        SELECT j.*, d.nome_estudio 
        FROM jogo j 
        LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id 
        WHERE j.status = 'publicado' 
        ORDER BY j.publicado_em DESC, j.criado_em DESC LIMIT 12
    ");
    $lancamentos = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) { $lancamentos = []; }

// --- PROMOÇÕES ---
try {
    $stmt = $pdo->query("
        SELECT j.*, d.nome_estudio 
        FROM jogo j 
        LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id 
        WHERE j.status = 'publicado' AND j.em_promocao = 1 AND j.preco_promocional_centavos IS NOT NULL
        ORDER BY (j.preco_centavos - j.preco_promocional_centavos) DESC LIMIT 12
    ");
    $promocoes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) { $promocoes = []; }

// --- MAIS POPULARES ---
try {
    $stmt = $pdo->query("
        SELECT j.*, d.nome_estudio 
        FROM jogo j 
        LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id 
        WHERE j.status = 'publicado' 
        ORDER BY j.total_vendas DESC, j.nota_media DESC LIMIT 12
    ");
    $populares = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) { $populares = []; }

// --- MELHORES AVALIADOS ---
try {
    $stmt = $pdo->query("
        SELECT j.*, d.nome_estudio 
        FROM jogo j 
        LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id 
        WHERE j.status = 'publicado' AND j.nota_media >= 4
        ORDER BY j.nota_media DESC, j.total_avaliacoes DESC LIMIT 12
    ");
    $melhores = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) { $melhores = []; }

// --- DESENVOLVEDORES ---
try {
    $stmt = $pdo->query("
        SELECT d.*, COUNT(j.id) as total_jogos
        FROM desenvolvedor d 
        JOIN jogo j ON d.id = j.desenvolvedor_id AND j.status = 'publicado'
        WHERE d.verificado = 1 AND d.status = 'ativo'
        GROUP BY d.id ORDER BY total_jogos DESC LIMIT 6
    ");
    $desenvolvedores = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) { $desenvolvedores = []; }

$page_title = 'Home - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<style>
:root {
    --bg-primary: #0d0d0d;
    --bg-secondary: #161616;
    --bg-card: #1a1a1a;
    --accent: #0070d1;
    --accent-hover: #0058a3;
    --success: #00d26a;
    --text-primary: #fff;
    --text-secondary: #888;
    --border: #2a2a2a;
    --radius: 8px;
}

.home-page {
    background: var(--bg-primary);
    min-height: 100vh;
}

.container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 24px 80px;
}

/* ===== HERO ===== */
.hero {
    position: relative;
    height: 500px;
    margin-bottom: 48px;
    border-radius: 12px;
    overflow: hidden;
}

.hero-slide {
    position: absolute;
    inset: 0;
    opacity: 0;
    transition: opacity 0.5s ease;
}

.hero-slide.active {
    opacity: 1;
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
    background: linear-gradient(to right, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.4) 60%, transparent 100%);
}

.hero-content {
    position: absolute;
    bottom: 60px;
    left: 48px;
    max-width: 500px;
    z-index: 2;
}

.hero-badge {
    display: inline-block;
    background: var(--accent);
    color: #fff;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 16px;
}

.hero-badge.promo {
    background: var(--success);
}

.hero-title {
    font-size: 2.2rem;
    font-weight: 700;
    color: #fff;
    margin: 0 0 12px;
    line-height: 1.2;
}

.hero-subtitle {
    color: rgba(255,255,255,0.7);
    font-size: 14px;
    line-height: 1.6;
    margin-bottom: 24px;
}

.hero-price {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 24px;
}

.hero-price .discount {
    background: var(--success);
    color: #fff;
    padding: 6px 10px;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 700;
}

.hero-price .old {
    color: rgba(255,255,255,0.5);
    text-decoration: line-through;
    font-size: 14px;
}

.hero-price .current {
    font-size: 24px;
    font-weight: 700;
    color: #fff;
}

.hero-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #fff;
    color: #000;
    padding: 14px 28px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
}

.hero-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.3);
}

/* Hero Nav */
.hero-nav {
    position: absolute;
    bottom: 24px;
    right: 24px;
    display: flex;
    gap: 8px;
    z-index: 3;
}

.hero-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: rgba(255,255,255,0.3);
    cursor: pointer;
    transition: all 0.2s;
}

.hero-dot.active {
    background: #fff;
    transform: scale(1.2);
}

/* ===== SECTIONS ===== */
.section {
    margin-bottom: 48px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.section-title {
    font-size: 20px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.section-link {
    color: var(--text-secondary);
    font-size: 13px;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: color 0.2s;
}

.section-link:hover {
    color: var(--accent);
}

/* ===== CATEGORIAS ===== */
.categories-row {
    display: flex;
    gap: 12px;
    overflow-x: auto;
    padding-bottom: 8px;
    scrollbar-width: none;
}

.categories-row::-webkit-scrollbar {
    display: none;
}

.cat-chip {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    padding: 10px 18px;
    border-radius: 20px;
    color: var(--text-primary);
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s;
    white-space: nowrap;
}

.cat-chip:hover {
    background: var(--bg-card);
    border-color: var(--accent);
    color: var(--accent);
}

.cat-chip i {
    font-size: 14px;
    opacity: 0.7;
}

/* ===== GAMES GRID/CAROUSEL ===== */
.games-row {
    display: flex;
    gap: 16px;
    overflow-x: auto;
    padding-bottom: 8px;
    scrollbar-width: none;
}

.games-row::-webkit-scrollbar {
    display: none;
}

/* Card PS - Customização mínima */
.games-row .ps-card {
    flex: 0 0 200px;
}

/* ===== PROMO BANNER ===== */
.promo-section {
    background: linear-gradient(135deg, #1a1a2e 0%, #0f0f1a 100%);
    border-radius: 12px;
    padding: 40px;
    margin: 48px 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.promo-text h3 {
    font-size: 24px;
    font-weight: 700;
    color: #fff;
    margin: 0 0 8px;
}

.promo-text p {
    color: rgba(255,255,255,0.6);
    margin: 0;
    font-size: 14px;
}

.promo-btn {
    background: var(--accent);
    color: #fff;
    padding: 12px 24px;
    border-radius: 6px;
    font-weight: 600;
    text-decoration: none;
    font-size: 14px;
    transition: all 0.2s;
}

.promo-btn:hover {
    background: var(--accent-hover);
}

/* ===== DEVS ===== */
.devs-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 16px;
}

.dev-card {
    background: var(--bg-secondary);
    border-radius: var(--radius);
    padding: 20px;
    text-align: center;
    text-decoration: none;
    border: 1px solid var(--border);
    transition: all 0.2s;
}

.dev-card:hover {
    border-color: var(--accent);
    transform: translateY(-4px);
}

.dev-logo {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    object-fit: cover;
    margin: 0 auto 12px;
    background: var(--bg-card);
}

.dev-name {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.dev-name i {
    color: var(--accent);
    font-size: 10px;
    margin-left: 4px;
}

.dev-count {
    font-size: 12px;
    color: var(--text-secondary);
}

/* ===== RESPONSIVE ===== */
@media (max-width: 1024px) {
    .hero { height: 400px; }
    .hero-content { left: 32px; bottom: 40px; }
    .hero-title { font-size: 1.8rem; }
    .devs-grid { grid-template-columns: repeat(3, 1fr); }
}

@media (max-width: 768px) {
    .container { padding: 0 16px 60px; }
    .hero { height: 350px; border-radius: 8px; }
    .hero-content { left: 20px; bottom: 24px; max-width: 280px; }
    .hero-title { font-size: 1.4rem; }
    .hero-subtitle { display: none; }
    .hero-btn { padding: 12px 20px; font-size: 13px; }
    .section-title { font-size: 16px; }
    .games-row .ps-card { flex: 0 0 150px; }
    .promo-section { flex-direction: column; text-align: center; gap: 20px; padding: 24px; }
    .devs-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
    .dev-card { padding: 16px; }
}
</style>

<div class="home-page">
    <div class="container">
        
        <!-- HERO -->
        <section class="hero">
            <?php foreach ($hero_items as $i => $item): 
                $is_promo = ($item['badge'] ?? '') === 'Promoção';
                $preco_final = isset($item['em_promocao']) && $item['em_promocao'] && isset($item['preco_promo']) 
                    ? $item['preco_promo'] : ($item['preco'] ?? 0);
                $desconto = 0;
                if (isset($item['em_promocao']) && $item['em_promocao'] && isset($item['preco']) && $item['preco'] > 0 && isset($item['preco_promo'])) {
                    $desconto = round((($item['preco'] - $item['preco_promo']) / $item['preco']) * 100);
                }
            ?>
            <div class="hero-slide <?= $i === 0 ? 'active' : '' ?>" data-index="<?= $i ?>">
                <div class="hero-bg">
                    <img src="<?= htmlspecialchars($item['imagem']) ?>" alt="" onerror="this.src='<?= SITE_URL ?>/assets/images/default-banner.jpg'">
                </div>
                <div class="hero-content">
                    <span class="hero-badge <?= $is_promo ? 'promo' : '' ?>"><?= htmlspecialchars($item['badge'] ?? 'Destaque') ?></span>
                    <h2 class="hero-title"><?= htmlspecialchars($item['titulo']) ?></h2>
                    <?php if (!empty($item['subtitulo'])): ?>
                    <p class="hero-subtitle"><?= htmlspecialchars($item['subtitulo']) ?></p>
                    <?php endif; ?>
                    
                    <?php if ($item['tipo'] === 'jogo' && isset($item['preco'])): ?>
                    <div class="hero-price">
                        <?php if ($preco_final == 0): ?>
                            <span class="current" style="color: var(--success);">Gratuito</span>
                        <?php else: ?>
                            <?php if ($desconto > 0): ?>
                                <span class="discount">-<?= $desconto ?>%</span>
                                <span class="old"><?= formatPrice($item['preco']) ?></span>
                            <?php endif; ?>
                            <span class="current"><?= formatPrice($preco_final) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <a href="<?= htmlspecialchars($item['url']) ?>" class="hero-btn">
                        <?= $item['tipo'] === 'jogo' ? 'Ver Jogo' : 'Saiba Mais' ?>
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (count($hero_items) > 1): ?>
            <div class="hero-nav">
                <?php for ($i = 0; $i < count($hero_items); $i++): ?>
                <div class="hero-dot <?= $i === 0 ? 'active' : '' ?>" data-index="<?= $i ?>"></div>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </section>

        <!-- CATEGORIAS -->
        <?php if (!empty($categorias)): ?>
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Explorar</h2>
            </div>
            <div class="categories-row">
                <?php foreach ($categorias as $cat): ?>
                <a href="<?= SITE_URL ?>/pages/categoria.php?slug=<?= $cat['slug'] ?>" class="cat-chip">
                    <i class="fas fa-<?= htmlspecialchars($cat['icone'] ?: 'gamepad') ?>"></i>
                    <?= htmlspecialchars($cat['nome']) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- PROMOÇÕES -->
        <?php if (!empty($promocoes)): ?>
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">🔥 Ofertas</h2>
                <a href="<?= SITE_URL ?>/pages/busca.php?promocao=1" class="section-link">
                    Ver todas <i class="fas fa-chevron-right"></i>
                </a>
            </div>
            <div class="games-row">
                <?php foreach ($promocoes as $jogo): 
                    $extra = [
                        'is_owned' => in_array($jogo['id'], $meus_jogos),
                        'in_wishlist' => in_array($jogo['id'], $minha_wishlist),
                        'in_cart' => in_array($jogo['id'], $meu_carrinho)
                    ];
                    renderGameCard($jogo, $pdo, $user_id, 'store', $extra);
                endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- LANÇAMENTOS -->
        <?php if (!empty($lancamentos)): ?>
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Novidades</h2>
                <a href="<?= SITE_URL ?>/pages/busca.php?ordem=recente" class="section-link">
                    Ver todos <i class="fas fa-chevron-right"></i>
                </a>
            </div>
            <div class="games-row">
                <?php foreach ($lancamentos as $jogo): 
                    $extra = [
                        'is_owned' => in_array($jogo['id'], $meus_jogos),
                        'in_wishlist' => in_array($jogo['id'], $minha_wishlist),
                        'in_cart' => in_array($jogo['id'], $meu_carrinho)
                    ];
                    renderGameCard($jogo, $pdo, $user_id, 'store', $extra);
                endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- PROMO BANNER -->
        <section class="promo-section">
            <div class="promo-text">
                <h3>Descubra novos jogos</h3>
                <p>Milhares de títulos esperando por você</p>
            </div>
            <a href="<?= SITE_URL ?>/pages/busca.php" class="promo-btn">
                Explorar Catálogo <i class="fas fa-arrow-right"></i>
            </a>
        </section>

        <!-- POPULARES -->
        <?php if (!empty($populares)): ?>
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Mais Populares</h2>
                <a href="<?= SITE_URL ?>/pages/busca.php?ordem=popular" class="section-link">
                    Ver todos <i class="fas fa-chevron-right"></i>
                </a>
            </div>
            <div class="games-row">
                <?php foreach ($populares as $jogo): 
                    $extra = [
                        'is_owned' => in_array($jogo['id'], $meus_jogos),
                        'in_wishlist' => in_array($jogo['id'], $minha_wishlist),
                        'in_cart' => in_array($jogo['id'], $meu_carrinho)
                    ];
                    renderGameCard($jogo, $pdo, $user_id, 'store', $extra);
                endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- MELHORES AVALIADOS -->
        <?php if (!empty($melhores)): ?>
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">⭐ Melhor Avaliados</h2>
            </div>
            <div class="games-row">
                <?php foreach ($melhores as $jogo): 
                    $extra = [
                        'is_owned' => in_array($jogo['id'], $meus_jogos),
                        'in_wishlist' => in_array($jogo['id'], $minha_wishlist),
                        'in_cart' => in_array($jogo['id'], $meu_carrinho)
                    ];
                    renderGameCard($jogo, $pdo, $user_id, 'store', $extra);
                endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- DESENVOLVEDORES -->
        <?php if (!empty($desenvolvedores)): ?>
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Desenvolvedores</h2>
            </div>
            <div class="devs-grid">
                <?php foreach ($desenvolvedores as $dev): ?>
                <a href="<?= SITE_URL ?>/pages/desenvolvedor.php?slug=<?= $dev['slug'] ?>" class="dev-card">
                    <img src="<?= SITE_URL . ($dev['logo_url'] ?: '/assets/images/default-dev.png') ?>" alt="" class="dev-logo" onerror="this.src='<?= SITE_URL ?>/assets/images/default-dev.png'">
                    <div class="dev-name">
                        <?= htmlspecialchars($dev['nome_estudio']) ?>
                        <?php if ($dev['verificado']): ?><i class="fas fa-check-circle"></i><?php endif; ?>
                    </div>
                    <div class="dev-count"><?= (int)$dev['total_jogos'] ?> jogos</div>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

    </div>
</div>

<script>
// Hero Slider Simples
const slides = document.querySelectorAll('.hero-slide');
const dots = document.querySelectorAll('.hero-dot');
let current = 0;
let interval;

function goTo(index) {
    if (index >= slides.length) index = 0;
    if (index < 0) index = slides.length - 1;
    
    slides.forEach(s => s.classList.remove('active'));
    dots.forEach(d => d.classList.remove('active'));
    
    current = index;
    slides[current]?.classList.add('active');
    dots[current]?.classList.add('active');
    
    clearInterval(interval);
    startAuto();
}

function startAuto() {
    interval = setInterval(() => goTo(current + 1), 6000);
}

dots.forEach((dot, i) => dot.addEventListener('click', () => goTo(i)));

if (slides.length > 1) startAuto();
</script>

<?php require_once '../includes/footer.php'; ?>