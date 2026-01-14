<?php
require_once '../config/config.php';
require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();
$user_id = isLoggedIn() ? $_SESSION['user_id'] : null;

// --- DADOS DO HERO (Mantive sua lógica original aqui) ---
$stmt = $pdo->query("SELECT * FROM banner WHERE ativo = 1 ORDER BY ordem LIMIT 3");
$banners = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT j.*, 'jogo' as tipo_origem FROM jogo j WHERE j.status = 'publicado' AND j.destaque = 1 ORDER BY j.criado_em DESC LIMIT 3");
$stmt->execute();
$jogos_carousel = $stmt->fetchAll(PDO::FETCH_ASSOC);

$hero_items = [];
foreach ($banners as $b) {
    $hero_items[] = ['titulo' => $b['titulo'], 'subtitulo' => $b['subtitulo'], 'imagem' => $b['imagem_desktop'], 'url' => $b['url_destino'], 'tipo' => 'banner', 'badge' => 'Novidade'];
}
foreach ($jogos_carousel as $j) {
    $img = strpos($j['imagem_banner'], 'http') === 0 ? $j['imagem_banner'] : SITE_URL . $j['imagem_banner'];
    $hero_items[] = ['titulo' => $j['titulo'], 'subtitulo' => $j['descricao_curta'], 'imagem' => $img, 'url' => SITE_URL . '/pages/jogo.php?slug=' . $j['slug'], 'tipo' => 'jogo', 'badge' => 'Destaque'];
}
if (empty($hero_items)) {
    $hero_items[] = ['titulo' => 'Bem-vindo', 'subtitulo' => 'Explore', 'imagem' => 'https://via.placeholder.com/1200x600', 'url' => '#', 'tipo' => 'banner'];
}

// --- LISTAS DE JOGOS ---
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

<!-- CSS DO HERO (Mantido do seu código anterior, compactado) -->
<style>
    .home-container { max-width: 1400px; margin: 0 auto; padding: 20px; padding-bottom: 80px; }
    /* Hero Styles */
    .epic-wrapper { display: flex; gap: 20px; height: 520px; margin-bottom: 60px; }
    .epic-main { flex: 1; position: relative; border-radius: 16px; overflow: hidden; background: #000; }
    .epic-slide { position: absolute; inset: 0; opacity: 0; transition: opacity 0.5s; z-index: 1; }
    .epic-slide.active { opacity: 1; z-index: 2; }
    .epic-slide img { width: 100%; height: 100%; object-fit: cover; }
    .epic-content { position: absolute; bottom: 0; left: 0; width: 100%; padding: 40px; background: linear-gradient(to top, rgba(0,0,0,0.9), transparent); z-index: 3; }
    .epic-title { font-size: 32px; font-weight: 800; color: #fff; }
    .epic-btn { display: inline-block; background: #fff; color: #000; padding: 12px 25px; border-radius: 8px; font-weight: 700; text-decoration: none; margin-top: 15px; }
    /* Nav Lateral */
    .epic-nav { width: 280px; display: flex; flex-direction: column; gap: 10px; overflow-y: auto; }
    .nav-item { display: flex; align-items: center; gap: 15px; padding: 12px; border-radius: 12px; cursor: pointer; background: #222; position: relative; overflow: hidden; }
    .nav-item.active { background: #333; }
    .nav-thumb { width: 50px; height: 65px; border-radius: 6px; object-fit: cover; }
    .nav-text { font-size: 14px; color: #fff; font-weight: 600; }
    /* Responsivo Hero */
    @media (max-width: 1024px) {
        .epic-wrapper { flex-direction: column; height: auto; }
        .epic-main { height: 400px; }
        .epic-nav { width: 100%; flex-direction: row; overflow-x: auto; padding-bottom: 10px; }
        .nav-item { min-width: 200px; }
    }
    
    /* NOVAS SEÇÕES */
    .section-header { display: flex; justify-content: space-between; align-items: center; margin-top: 50px; margin-bottom: 20px; }
    .section-title { font-size: 24px; color: #fff; font-weight: 700; }
    .see-all { color: #888; text-decoration: none; font-size: 14px; transition: color 0.2s; }
    .see-all:hover { color: #fff; }

    /* Categorias Grid */
    .genre-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 15px; margin-bottom: 40px; }
    .genre-card { background: #2a2a2a; padding: 20px; border-radius: 12px; text-align: center; color: #fff; text-decoration: none; font-weight: 600; transition: 0.3s; border: 1px solid transparent; }
    .genre-card:hover { background: var(--accent, #007bff); transform: translateY(-3px); }

    /* Banner Promocional */
    .promo-banner { background: linear-gradient(45deg, #FF416C, #FF4B2B); border-radius: 16px; padding: 40px; margin: 60px 0; color: #fff; text-align: center; position: relative; overflow: hidden; }
    .promo-banner h3 { font-size: 28px; font-weight: 800; margin-bottom: 10px; }
    .promo-banner p { margin-bottom: 20px; opacity: 0.9; }
</style>

<div class="home-container">

    <!-- HERO SECTION -->
    <?php if (!empty($hero_items)): ?>
    <div class="epic-wrapper">
        <div class="epic-main">
            <?php foreach ($hero_items as $i => $item): ?>
                <div class="epic-slide <?= $i===0?'active':'' ?>" id="slide-<?= $i ?>">
                    <img src="<?= $item['imagem'] ?>" alt="">
                    <div class="epic-content">
                        <span class="badge" style="background:#007bff; padding:4px 8px; border-radius:4px; font-size:12px; font-weight:bold; color:#fff;"><?= $item['badge'] ?></span>
                        <h2 class="epic-title"><?= sanitize($item['titulo']) ?></h2>
                        <a href="<?= $item['url'] ?>" class="epic-btn">CONFIRA AGORA</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="epic-nav">
            <?php foreach ($hero_items as $i => $item): ?>
                <div class="nav-item <?= $i===0?'active':'' ?>" onclick="changeSlide(<?= $i ?>)">
                    <img src="<?= $item['imagem'] ?>" class="nav-thumb">
                    <span class="nav-text"><?= sanitize($item['titulo']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- CATEGORIAS (Preenchimento de conteúdo) -->
    <div class="section-header">
        <h2 class="section-title">Navegar por Gênero</h2>
    </div>
    <div class="genre-grid">
        <a href="busca.php?categoria=acao" class="genre-card"><i class="fas fa-fist-raised"></i> Ação</a>
        <a href="busca.php?categoria=rpg" class="genre-card"><i class="fas fa-dungeon"></i> RPG</a>
        <a href="busca.php?categoria=aventura" class="genre-card"><i class="fas fa-compass"></i> Aventura</a>
        <a href="busca.php?categoria=estrategia" class="genre-card"><i class="fas fa-chess"></i> Estratégia</a>
        <a href="busca.php?categoria=indie" class="genre-card"><i class="fas fa-gamepad"></i> Indie</a>
        <a href="busca.php?categoria=terror" class="genre-card"><i class="fas fa-ghost"></i> Terror</a>
    </div>

    <!-- DESTAQUES -->
    <?php if (!empty($destaques)): ?>
    <div class="section-header">
        <h2 class="section-title"><i class="fas fa-star" style="color:#FFD700"></i> Em Destaque</h2>
        <a href="busca.php?destaque=1" class="see-all">Ver todos <i class="fas fa-chevron-right"></i></a>
    </div>
    <div class="cards-grid">
        <?php foreach ($destaques as $jogo) renderGameCard($jogo, $pdo, $user_id); ?>
    </div>
    <?php endif; ?>

    <!-- BANNER INTERMEDIÁRIO -->
    <div class="promo-banner">
        <h3>Ofertas Imperdíveis</h3>
        <p>Jogos incríveis com até 75% de desconto por tempo limitado.</p>
        <a href="busca.php?promocao=1" style="background:#fff; color:#FF4B2B; padding:10px 20px; border-radius:20px; text-decoration:none; font-weight:bold;">Ver Ofertas</a>
    </div>

    <!-- LANÇAMENTOS -->
    <?php if (!empty($lancamentos)): ?>
    <div class="section-header">
        <h2 class="section-title"><i class="fas fa-rocket" style="color:#ff4757"></i> Lançamentos</h2>
        <a href="busca.php?ordem=recente" class="see-all">Ver todos <i class="fas fa-chevron-right"></i></a>
    </div>
    <div class="cards-grid">
        <?php foreach ($lancamentos as $jogo) renderGameCard($jogo, $pdo, $user_id); ?>
    </div>
    <?php endif; ?>

    <!-- PROMOÇÕES -->
    <?php if (!empty($promocoes)): ?>
    <div class="section-header">
        <h2 class="section-title"><i class="fas fa-percent" style="color:#2ed573"></i> Melhores Ofertas</h2>
        <a href="busca.php?promocao=1" class="see-all">Ver todos <i class="fas fa-chevron-right"></i></a>
    </div>
    <div class="cards-grid">
        <?php foreach ($promocoes as $jogo) renderGameCard($jogo, $pdo, $user_id); ?>
    </div>
    <?php endif; ?>

    

</div>

<script>
    // Script Simples do Hero
    let current = 0;
    const slides = document.querySelectorAll('.epic-slide');
    const navs = document.querySelectorAll('.nav-item');
    
    function changeSlide(index) {
        slides[current].classList.remove('active');
        navs[current].classList.remove('active');
        current = index;
        slides[current].classList.add('active');
        navs[current].classList.add('active');
    }

    // Auto play
    setInterval(() => {
        let next = (current + 1) % slides.length;
        changeSlide(next);
    }, 5000);
</script>

<?php require_once '../includes/footer.php'; ?>