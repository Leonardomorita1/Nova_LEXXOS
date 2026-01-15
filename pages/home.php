<?php
// pages/home.php - Otimizada e Integrada
require_once '../config/config.php';
require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();
$user_id = isLoggedIn() ? $_SESSION['user_id'] : null;

// --- 1. PRE-LOAD USER DATA (PERFORMANCE) ---
// Carrega quais jogos o usuário tem e quais estão na wishlist/carrinho de uma vez
$meus_jogos = [];
$minha_wishlist = [];
$meu_carrinho = [];

if ($user_id) {
    // Jogos na Biblioteca
    $stmt = $pdo->prepare("SELECT jogo_id FROM biblioteca WHERE usuario_id = ?");
    $stmt->execute([$user_id]);
    $meus_jogos = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Jogos na Wishlist
    $stmt = $pdo->prepare("SELECT jogo_id FROM lista_desejos WHERE usuario_id = ?");
    $stmt->execute([$user_id]);
    $minha_wishlist = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Jogos no Carrinho
    $stmt = $pdo->prepare("SELECT jogo_id FROM carrinho WHERE usuario_id = ?");
    $stmt->execute([$user_id]);
    $meu_carrinho = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// --- 2. DADOS DO HERO ---
$stmt = $pdo->query("SELECT * FROM banner WHERE ativo = 1 ORDER BY ordem LIMIT 3");
$banners = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT j.*, 'jogo' as tipo_origem FROM jogo j WHERE j.status = 'publicado' AND j.destaque = 1 ORDER BY j.criado_em DESC LIMIT 3");
$stmt->execute();
$jogos_carousel = $stmt->fetchAll(PDO::FETCH_ASSOC);

$hero_items = [];
foreach ($banners as $b) {
    $hero_items[] = [
        'titulo' => $b['titulo'], 
        'subtitulo' => $b['subtitulo'], 
        'imagem' => $b['imagem_desktop'], 
        'url' => $b['url_destino'], 
        'tipo' => 'banner', 
        'badge' => 'Novidade'
    ];
}
foreach ($jogos_carousel as $j) {
    $img = strpos($j['imagem_banner'], 'http') === 0 ? $j['imagem_banner'] : SITE_URL . $j['imagem_banner'];
    $hero_items[] = [
        'titulo' => $j['titulo'], 
        'subtitulo' => $j['descricao_curta'], 
        'imagem' => $img, 
        'url' => SITE_URL . '/pages/jogo.php?slug=' . $j['slug'], 
        'tipo' => 'jogo', 
        'badge' => 'Destaque'
    ];
}
if (empty($hero_items)) {
    $hero_items[] = ['titulo' => 'Bem-vindo', 'subtitulo' => 'Explore', 'imagem' => 'https://via.placeholder.com/1200x600', 'url' => '#', 'tipo' => 'banner', 'badge' => 'Info'];
}

// --- 3. LISTAS DE JOGOS ---
function getGames($pdo, $type) {
    $sql = "SELECT j.*, d.nome_estudio FROM jogo j LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id WHERE j.status = 'publicado' ";
    if ($type == 'promocao') $sql .= "AND j.em_promocao = 1 ";
    if ($type == 'lancamento') $sql .= "ORDER BY j.publicado_em DESC ";
    else $sql .= "ORDER BY j.criado_em DESC ";
    $sql .= "LIMIT 10";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

$destaques = getGames($pdo, 'padrao');
$lancamentos = getGames($pdo, 'lancamento');
$promocoes = getGames($pdo, 'promocao');

$page_title = 'Home - ' . SITE_NAME;
require_once '../includes/header.php';
require_once '../components/game-card.php'; 
?>

<!-- CSS HOME (Compacto) -->
<style>
    .home-container { max-width: 1400px; margin: 0 auto; padding: 20px; padding-bottom: 80px; }
    
    /* Hero */
    .epic-wrapper { display: flex; gap: 20px; height: 520px; margin-bottom: 60px; }
    .epic-main { flex: 1; position: relative; border-radius: 16px; overflow: hidden; background: #000; box-shadow: 0 20px 40px rgba(0,0,0,0.3); }
    .epic-slide { position: absolute; inset: 0; opacity: 0; transition: opacity 0.5s; z-index: 1; }
    .epic-slide.active { opacity: 1; z-index: 2; }
    .epic-slide img { width: 100%; height: 100%; object-fit: cover; }
    .epic-content { position: absolute; bottom: 0; left: 0; width: 100%; padding: 60px 40px; background: linear-gradient(to top, rgba(0,0,0,0.95), transparent); z-index: 3; }
    .epic-badge { background: var(--accent); color: #fff; padding: 4px 10px; border-radius: 4px; font-weight: 700; font-size: 0.8rem; text-transform: uppercase; margin-bottom: 10px; display: inline-block; }
    .epic-title { font-size: 3rem; font-weight: 800; color: #fff; line-height: 1.1; margin-bottom: 10px; text-shadow: 0 2px 10px rgba(0,0,0,0.5); }
    .epic-subtitle { color: rgba(255,255,255,0.8); font-size: 1.1rem; margin-bottom: 25px; max-width: 600px; }
    .epic-btn { display: inline-flex; align-items: center; gap: 10px; background: #fff; color: #000; padding: 14px 30px; border-radius: 8px; font-weight: 700; text-decoration: none; transition: transform 0.2s; }
    .epic-btn:hover { transform: translateY(-2px); }
    
    /* Nav Lateral */
    .epic-nav { width: 300px; display: flex; flex-direction: column; gap: 12px; overflow-y: auto; }
    .nav-item { display: flex; align-items: center; gap: 15px; padding: 15px; border-radius: 12px; cursor: pointer; background: rgba(255,255,255,0.05); transition: all 0.2s; border: 1px solid transparent; }
    .nav-item:hover { background: rgba(255,255,255,0.1); }
    .nav-item.active { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.2); }
    .nav-item.active .nav-text { color: #fff; }
    .nav-thumb { width: 60px; height: 60px; border-radius: 8px; object-fit: cover; }
    .nav-text { font-size: 1rem; color: rgba(255,255,255,0.7); font-weight: 600; line-height: 1.3; }
    
    /* Seções */
    .section-header { display: flex; justify-content: space-between; align-items: center; margin-top: 60px; margin-bottom: 25px; }
    .section-title { font-size: 1.8rem; color: #fff; font-weight: 700; display: flex; align-items: center; gap: 10px; }
    .see-all { color: rgba(255,255,255,0.6); text-decoration: none; font-size: 0.95rem; display: flex; align-items: center; gap: 5px; transition: color 0.2s; padding: 8px 16px; border-radius: 20px; background: rgba(255,255,255,0.05); }
    .see-all:hover { color: #fff; background: rgba(255,255,255,0.1); }

    /* Categorias */
    .genre-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 15px; margin-bottom: 40px; }
    .genre-card { background: rgba(255,255,255,0.05); padding: 25px; border-radius: 16px; text-align: center; color: rgba(255,255,255,0.8); text-decoration: none; font-weight: 600; transition: 0.3s; border: 1px solid rgba(255,255,255,0.05); display: flex; flex-direction: column; align-items: center; gap: 10px; }
    .genre-card i { font-size: 24px; color: var(--accent); margin-bottom: 5px; }
    .genre-card:hover { background: rgba(255,255,255,0.1); transform: translateY(-5px); color: #fff; border-color: var(--accent); }

    /* Promo Banner */
    .promo-banner { background: linear-gradient(135deg, #FF416C 0%, #FF4B2B 100%); border-radius: 20px; padding: 50px; margin: 80px 0; color: #fff; text-align: center; position: relative; overflow: hidden; box-shadow: 0 20px 50px rgba(255, 75, 43, 0.3); }
    .promo-banner::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E"); }
    .promo-content { position: relative; z-index: 2; }
    .promo-banner h3 { font-size: 2.5rem; font-weight: 800; margin-bottom: 15px; }
    .promo-banner p { margin-bottom: 30px; opacity: 0.9; font-size: 1.2rem; max-width: 600px; margin-left: auto; margin-right: auto; }
    .promo-btn { background: #fff; color: #FF4B2B; padding: 15px 35px; border-radius: 30px; text-decoration: none; font-weight: 800; display: inline-block; transition: transform 0.2s; box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
    .promo-btn:hover { transform: scale(1.05); }

    /* Responsivo */
    @media (max-width: 1024px) {
        .epic-wrapper { flex-direction: column; height: auto; }
        .epic-main { height: 450px; }
        .epic-nav { width: 100%; flex-direction: row; overflow-x: auto; padding-bottom: 10px; }
        .nav-item { min-width: 220px; }
    }
    @media (max-width: 768px) {
        .epic-main { height: 400px; }
        .epic-title { font-size: 2rem; }
        .genre-grid { grid-template-columns: repeat(2, 1fr); }
    }
</style>

<div class="home-container">

    <!-- HERO SECTION -->
    <?php if (!empty($hero_items)): ?>
    <div class="epic-wrapper">
        <div class="epic-main">
            <?php foreach ($hero_items as $i => $item): ?>
                <div class="epic-slide <?= $i===0?'active':'' ?>" id="slide-<?= $i ?>">
                    <img src="<?= $item['imagem'] ?>" alt="<?= sanitize($item['titulo']) ?>">
                    <div class="epic-content">
                        <span class="epic-badge"><?= $item['badge'] ?></span>
                        <h2 class="epic-title"><?= sanitize($item['titulo']) ?></h2>
                        <?php if ($item['subtitulo']): ?>
                            <p class="epic-subtitle"><?= sanitize($item['subtitulo']) ?></p>
                        <?php endif; ?>
                        <a href="<?= $item['url'] ?>" class="epic-btn">
                            CONFIRA AGORA <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="epic-nav">
            <?php foreach ($hero_items as $i => $item): ?>
                <div class="nav-item <?= $i===0?'active':'' ?>" onclick="changeSlide(<?= $i ?>)">
                    <img src="<?= $item['imagem'] ?>" class="nav-thumb" alt="Thumb">
                    <span class="nav-text"><?= sanitize($item['titulo']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- CATEGORIAS -->
    <div class="section-header">
        <h2 class="section-title">Navegar por Gênero</h2>
    </div>
    <div class="genre-grid">
        <a href="/pages/categoria.php?slug=acao" class="genre-card"><i class="fas fa-fist-raised"></i> Ação</a>
        <a href="/pages/categoria.php?slug=rpg" class="genre-card"><i class="fas fa-dungeon"></i> RPG</a>
        <a href="/pages/categoria.php?slug=aventura" class="genre-card"><i class="fas fa-compass"></i> Aventura</a>
        <a href="/pages/categoria.php?slug=estrategia" class="genre-card"><i class="fas fa-chess"></i> Estratégia</a>
        <a href="/pages/categoria.php?slug=indie" class="genre-card"><i class="fas fa-gamepad"></i> Indie</a>
        <a href="/pages/categoria.php?slug=terror" class="genre-card"><i class="fas fa-ghost"></i> Terror</a>
    </div>

    <!-- DESTAQUES -->
    <?php if (!empty($destaques)): ?>
    <div class="section-header">
        <h2 class="section-title"><i class="fas fa-star" style="color:#FFD700"></i> Em Destaque</h2>
        <a href="busca.php?destaque=1" class="see-all">Ver todos <i class="fas fa-chevron-right"></i></a>
    </div>
    <div class="cards-grid">
        <?php foreach ($destaques as $jogo): ?>
            <?php 
            // Passamos os estados pré-carregados
            renderGameCard($jogo, $pdo, $user_id, 'store', [
                'is_owned' => in_array($jogo['id'], $meus_jogos),
                'in_wishlist' => in_array($jogo['id'], $minha_wishlist),
                'in_cart' => in_array($jogo['id'], $meu_carrinho)
            ]); 
            ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- BANNER PROMO -->
    <div class="promo-banner">
        <div class="promo-content">
            <h3>Ofertas Imperdíveis</h3>
            <p>Jogos incríveis com até 75% de desconto por tempo limitado.</p>
            <a href="busca.php?promocao=1" class="promo-btn">Ver Ofertas</a>
        </div>
    </div>

    <!-- LANÇAMENTOS -->
    <?php if (!empty($lancamentos)): ?>
    <div class="section-header">
        <h2 class="section-title"><i class="fas fa-rocket" style="color:#ff4757"></i> Lançamentos</h2>
        <a href="busca.php?ordem=recente" class="see-all">Ver todos <i class="fas fa-chevron-right"></i></a>
    </div>
    <div class="cards-grid">
        <?php foreach ($lancamentos as $jogo): ?>
            <?php 
            renderGameCard($jogo, $pdo, $user_id, 'store', [
                'is_owned' => in_array($jogo['id'], $meus_jogos),
                'in_wishlist' => in_array($jogo['id'], $minha_wishlist),
                'in_cart' => in_array($jogo['id'], $meu_carrinho)
            ]); 
            ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- PROMOÇÕES -->
    <?php if (!empty($promocoes)): ?>
    <div class="section-header">
        <h2 class="section-title"><i class="fas fa-percent" style="color:#2ed573"></i> Melhores Ofertas</h2>
        <a href="busca.php?promocao=1" class="see-all">Ver todos <i class="fas fa-chevron-right"></i></a>
    </div>
    <div class="cards-grid">
        <?php foreach ($promocoes as $jogo): ?>
            <?php 
            renderGameCard($jogo, $pdo, $user_id, 'store', [
                'is_owned' => in_array($jogo['id'], $meus_jogos),
                'in_wishlist' => in_array($jogo['id'], $minha_wishlist),
                'in_cart' => in_array($jogo['id'], $meu_carrinho)
            ]); 
            ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<script>
    // Hero Slider
    let current = 0;
    const slides = document.querySelectorAll('.epic-slide');
    const navs = document.querySelectorAll('.nav-item');
    let interval;
    
    function changeSlide(index) {
        if(index >= slides.length) index = 0;
        
        slides.forEach(s => s.classList.remove('active'));
        navs.forEach(n => n.classList.remove('active'));
        
        current = index;
        
        if(slides[current]) slides[current].classList.add('active');
        if(navs[current]) navs[current].classList.add('active');
        
        // Reset timer
        clearInterval(interval);
        startTimer();
    }

    function startTimer() {
        interval = setInterval(() => {
            changeSlide(current + 1);
        }, 6000);
    }

    // Iniciar apenas se houver slides
    if (slides.length > 0) {
        startTimer();
    }
</script>

<?php require_once '../includes/footer.php'; ?>