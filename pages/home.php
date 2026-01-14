<?php
require_once '../config/config.php';
require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();
$user_id = isLoggedIn() ? $_SESSION['user_id'] : null;

// --- LÓGICA DE DADOS ---

// 1. Buscar Banners (URL Externa)
// Removi restrições rigorosas de data para garantir que algo apareça para teste
$stmt = $pdo->query("SELECT * FROM banner WHERE ativo = 1 ORDER BY ordem LIMIT 3");
$banners = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Buscar Jogos Destaque para o Carrossel (Imagem Local)
// Prioriza jogos com imagem_banner definida
$stmt = $pdo->prepare("
    SELECT j.*, 'jogo' as tipo_origem 
    FROM jogo j 
    WHERE j.status = 'publicado' 
    AND j.destaque = 1 
    AND j.imagem_banner IS NOT NULL 
    AND j.imagem_banner != ''
    ORDER BY j.criado_em DESC 
    LIMIT 3
");
$stmt->execute();
$jogos_carousel = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Mesclar e Normalizar para o Carrossel
$hero_items = [];

// Adiciona Banners
foreach ($banners as $b) {
    $hero_items[] = [
        'titulo' => $b['titulo'],
        'subtitulo' => $b['subtitulo'],
        'imagem' => $b['imagem_desktop'], // URL externa
        'url' => $b['url_destino'],
        'tipo' => 'banner',
        'badge' => 'Novidade'
    ];
}

// Adiciona Jogos
foreach ($jogos_carousel as $j) {
    // Define caminho da imagem local
    $img_path = strpos($j['imagem_banner'], 'http') === 0 
        ? $j['imagem_banner'] 
        : SITE_URL . $j['imagem_banner'];

    $hero_items[] = [
        'titulo' => $j['titulo'],
        'subtitulo' => $j['descricao_curta'],
        'imagem' => $img_path,
        'url' => SITE_URL . '/pages/jogo.php?slug=' . $j['slug'],
        'tipo' => 'jogo',
        'badge' => $j['em_promocao'] ? 'Oferta' : 'Destaque',
        'preco' => $j['preco_centavos'],
        'preco_promo' => $j['preco_promocional_centavos'],
        'em_promocao' => $j['em_promocao']
    ];
}

// Se não tiver nada, adiciona um item fake para não quebrar o layout
if (empty($hero_items)) {
    $hero_items[] = [
        'titulo' => 'Bem-vindo à Loja',
        'subtitulo' => 'Explore os melhores jogos',
        'imagem' => 'https://via.placeholder.com/1200x600?text=Sem+Banners',
        'url' => '#',
        'tipo' => 'banner',
        'badge' => 'Info'
    ];
}

// 4. Buscar Listas de Jogos para os Cards
function getGames($pdo, $type) {
    $sql = "SELECT * FROM jogo WHERE status = 'publicado' ";
    if ($type == 'promocao') $sql .= "AND em_promocao = 1 ";
    if ($type == 'lancamento') $sql .= "ORDER BY publicado_em DESC ";
    else $sql .= "ORDER BY criado_em DESC ";
    $sql .= "LIMIT 10";
    
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

$destaques = getGames($pdo, 'padrao'); // Reutilizando logica simples
$lancamentos = getGames($pdo, 'lancamento');
$promocoes = getGames($pdo, 'promocao');

$page_title = 'Home - ' . SITE_NAME;
require_once '../includes/header.php';
// Importa o componente de card do usuário
require_once '../components/game-card.php'; 
?>

<!-- CSS ESPECÍFICO DA HOME -->
<style>
    :root {
        --epic-height: 520px;
        --epic-nav-width: 280px;
    }

    .home-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 20px;
        padding-bottom: 80px;
    }

    /* --- CAROUSEL WRAPPER --- */
    .epic-wrapper {
        display: flex;
        gap: 20px;
        height: var(--epic-height);
        margin-bottom: 60px;
        opacity: 0;
        animation: fadeIn 0.5s forwards;
    }
    @keyframes fadeIn { to { opacity: 1; } }

    /* --- LADO ESQUERDO (MAIN) --- */
    .epic-main {
        flex: 1;
        position: relative;
        border-radius: 16px;
        overflow: hidden;
        background: var(--bg-secondary);
        box-shadow: 0 20px 50px rgba(0,0,0,0.3);
        /* Importante: garante que a div tenha tamanho para renderizar a imagem absoluta */
        min-height: 100%; 
    }

    .epic-slide {
        position: absolute;
        top: 0; left: 0; width: 100%; height: 100%;
        opacity: 0;
        visibility: hidden; /* Garante que não clique em links invisíveis */
        transition: opacity 0.5s ease-in-out, visibility 0.5s;
        z-index: 1;
    }

    .epic-slide.active {
        opacity: 1;
        visibility: visible;
        z-index: 2;
    }

    .epic-slide img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .epic-content {
        position: absolute;
        bottom: 0; left: 0; width: 100%;
        padding: 40px;
        background: linear-gradient(to top, rgba(0,0,0,0.9), transparent);
        z-index: 3;
    }

    .epic-badge {
        display: inline-block;
        background: var(--bg-secondary);
        color: var(--text-primary);
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        margin-bottom: 15px;
    }

    .epic-title {
        font-size: 32px;
        font-weight: 800;
        color: #fff;
        margin-bottom: 10px;
        line-height: 1.1;
    }

    .epic-desc {
        font-size: 16px;
        color: #ddd;
        margin-bottom: 20px;
        max-width: 600px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .epic-btn {
        display: inline-flex;
        align-items: center;
        background: #fff;
        color: #000;
        padding: 12px 25px;
        border-radius: 8px;
        font-weight: 700;
        text-decoration: none;
        transition: transform 0.2s;
    }
    .epic-btn:hover { transform: scale(1.05); color: #fff; background: #000; }

    /* --- LADO DIREITO (NAV) --- */
    .epic-nav {
        width: var(--epic-nav-width);
        display: flex;
        flex-direction: column;
        gap: 10px;
        overflow-y: auto;
    }

    .nav-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 12px;
        border-radius: 12px;
        cursor: pointer;
        transition: background 0.3s;
        position: relative;
        overflow: hidden;
        background: var(--bg-secondary);
        border: 1px solid transparent; /* Evita pulo no layout */
    }

    .nav-item:hover { background: var(--bg-primary); }
    
    /* Quando ativo, fundo mais claro */
    .nav-item.active { background: var(--bg-secondary); }

    /* BARRA DE PROGRESSO */
    .nav-progress {
        position: absolute;
        left: 0; top: 0; bottom: 0;
        width: 0%; /* Começa zerado */
        background: var(--accent); /* Cor do progresso */
        opacity: 0.5; /* Transparência suave */
        z-index: 0;
        pointer-events: none;
    }

    /* Apenas o item ativo recebe a classe de animação via JS */
    .nav-item.active .nav-progress.animating {
        width: 100%;
        /* A transição será controlada pelo JS para podermos resetar */
    }

    .nav-thumb {
        width: 50px; height: 65px;
        border-radius: 6px;
        object-fit: cover; z-index: 1;
        background: #000;
    }

    .nav-text { z-index: 1; flex: 1; }
    
    .nav-title {
        color: var(--text-primary);
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 4px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    

    /* --- ESTILO SEÇÕES DE CARDS --- */
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 50px;
        margin-bottom: 25px;
    }

    .section-title {
        font-size: 22px;
        color: var(--text-primary);
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .cards-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 20px;
    }

    /* Mobile Responsive */
    @media (max-width: 1024px) {
        .epic-wrapper {
            flex-direction: column;
            height: auto; /* Altura automática para acomodar os dois blocos */
        }
        
        .epic-main {
            width: 100%;
            /* FIX CRÍTICO: Altura fixa no mobile para a imagem absolute aparecer */
            height: 450px; 
            min-height: 450px;
        }

        .epic-nav {
            width: 100%;
            flex-direction: row;
            overflow-x: auto;
            padding-bottom: 10px;
            /* Scroll suave horizontal */
            -webkit-overflow-scrolling: touch; 
        }

        .nav-item {
            min-width: 260px;
            flex-shrink: 0;
        }
    }

    @media (max-width: 600px) {
        .epic-main {
            height: 350px; /* Um pouco menor em celulares pequenos */
            min-height: 350px;
        }
        .epic-title { font-size: 24px; }
        .epic-content { padding: 20px; }
    }
</style>

<div class="home-container">

    <!-- CARROSEL -->
    <?php if (!empty($hero_items)): ?>
    <div class="epic-wrapper" id="epicCarousel">
        
        <!-- Imagens Grandes -->
        <div class="epic-main">
            <?php foreach ($hero_items as $index => $item): ?>
                <div class="epic-slide <?= $index === 0 ? 'active' : '' ?>">
                    <img src="<?= $item['imagem'] ?>" alt="<?= sanitize($item['titulo']) ?>">
                    
                    <div class="epic-content">
                        <?php if(isset($item['badge'])): ?>
                            <span class="epic-badge"><?= $item['badge'] ?></span>
                        <?php endif; ?>
                        
                        <h2 class="epic-title"><?= sanitize($item['titulo']) ?></h2>
                        <?php if($item['subtitulo']): ?>
                            <p class="epic-desc"><?= sanitize($item['subtitulo']) ?></p>
                        <?php endif; ?>
                        
                        <a href="<?= $item['url'] ?>" class="epic-btn">
                            <?= ($item['tipo'] == 'jogo') ? 'COMPRAR AGORA' : 'SAIBA MAIS' ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Navegação Lateral -->
        <div class="epic-nav">
            <?php foreach ($hero_items as $index => $item): ?>
                <!-- Adicionei onclick para controle JS -->
                <div class="nav-item <?= $index === 0 ? 'active' : '' ?>" onclick="manualSwitch(<?= $index ?>)">
                    <div class="nav-progress"></div>
                    <img src="<?= $item['imagem'] ?>" class="nav-thumb" alt="">
                    <div class="nav-text">
                        <div class="nav-title"><?= sanitize($item['titulo']) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
    <?php endif; ?>

    <!-- SEÇÃO: JOGOS EM DESTAQUE -->
    <?php if (!empty($destaques)): ?>
    <div class="section-header">
        <h2 class="section-title"><i class="fas fa-star" style="color:#FFD700"></i> Em Destaque</h2>
        <a href="<?= SITE_URL ?>/pages/busca.php?destaque=1" style="color: #bbb; text-decoration:none;">Ver todos</a>
    </div>
    <div class="cards-grid">
        <?php foreach ($destaques as $jogo): ?>
            <?php renderGameCard($jogo, $pdo, $user_id); ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- SEÇÃO: LANÇAMENTOS -->
    <?php if (!empty($lancamentos)): ?>
    <div class="section-header">
        <h2 class="section-title"><i class="fas fa-rocket" style="color:#ff4757"></i> Lançamentos Recentes</h2>
        <a href="<?= SITE_URL ?>/pages/busca.php?ordem=recente" style="color: #bbb; text-decoration:none;">Ver todos</a>
    </div>
    <div class="cards-grid">
        <?php foreach ($lancamentos as $jogo): ?>
            <?php renderGameCard($jogo, $pdo, $user_id); ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- SEÇÃO: PROMOÇÕES -->
    <?php if (!empty($promocoes)): ?>
    <div class="section-header">
        <h2 class="section-title"><i class="fas fa-percent" style="color:#2ed573"></i> Ofertas Especiais</h2>
        <a href="<?= SITE_URL ?>/pages/busca.php?promocao=1" style="color: #bbb; text-decoration:none;">Ver todos</a>
    </div>
    <div class="cards-grid">
        <?php foreach ($promocoes as $jogo): ?>
            <?php renderGameCard($jogo, $pdo, $user_id); ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<script>
    const INTERVAL_TIME = 7000; // 7 Segundos
    let currentIndex = 0;
    let timer = null;
    
    const slides = document.querySelectorAll('.epic-slide');
    const navItems = document.querySelectorAll('.nav-item');
    const totalSlides = slides.length;

    function startSlideTimer() {
        // Limpa qualquer timer anterior
        if(timer) clearInterval(timer);
        
        // Inicia a animação da barra no item atual
        animateProgressBar(currentIndex);
        
        // Define o intervalo para o próximo slide
        timer = setInterval(() => {
            let next = currentIndex + 1;
            if (next >= totalSlides) next = 0;
            switchSlide(next);
        }, INTERVAL_TIME);
    }

    function switchSlide(index) {
        currentIndex = index;

        // 1. Remove classes ativas de todos
        slides.forEach(s => s.classList.remove('active'));
        navItems.forEach(n => {
            n.classList.remove('active');
            // Reseta a barra de progresso forçadamente
            const bar = n.querySelector('.nav-progress');
            bar.style.transition = 'none';
            bar.style.width = '0%';
            bar.classList.remove('animating');
        });

        // 2. Ativa o novo slide e item de menu
        if(slides[index]) slides[index].classList.add('active');
        if(navItems[index]) navItems[index].classList.add('active');

        // 3. Reinicia o timer e a animação
        startSlideTimer();
    }

    // Chamada quando o usuário clica
    function manualSwitch(index) {
        // Se clicar no mesmo, não faz nada ou reinicia? Melhor reiniciar.
        switchSlide(index);
    }

    function animateProgressBar(index) {
        const item = navItems[index];
        if(!item) return;

        const bar = item.querySelector('.nav-progress');
        
        // Truque do CSS Reflow para reiniciar animação
        // Primeiro, garante que está zerado sem transição
        bar.style.transition = 'none';
        bar.style.width = '0%';
        
        // Força o navegador a recalcular o layout (Reflow)
        void bar.offsetWidth; 

        // Agora aplica a transição e a largura total
        bar.style.transition = `width ${INTERVAL_TIME}ms linear`;
        bar.style.width = '100%';
    }

    // Inicialização ao carregar a página
    document.addEventListener('DOMContentLoaded', () => {
        if(totalSlides > 0) {
            // Garante estado inicial correto
            switchSlide(0);
        }
    });
</script>


<?php require_once '../includes/footer.php'; ?>