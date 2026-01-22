<?php
// pages/evento.php - Versão Clean
require_once '../config/config.php';
require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();
$user_id = isLoggedIn() ? $_SESSION['user_id'] : null;

if (!isset($_GET['slug'])) {
    header('Location: ' . SITE_URL . '/pages/home.php');
    exit;
}

$slug = $_GET['slug'];

$stmt = $pdo->prepare("SELECT * FROM evento WHERE slug = ? AND ativo = 1");
$stmt->execute([$slug]);
$evento = $stmt->fetch();

if (!$evento) {
    header('Location: ' . SITE_URL . '/pages/home.php');
    exit;
}

// Status do evento
$agora = date('Y-m-d H:i:s');
$evento_ativo = ($agora >= $evento['data_inicio'] && $agora <= $evento['data_fim']);
$evento_futuro = ($agora < $evento['data_inicio']);
$evento_encerrado = ($agora > $evento['data_fim']);

// Pre-load user data
$meus_jogos = [];
$minha_wishlist = [];
$meu_carrinho = [];

if ($user_id) {
    $stmt = $pdo->prepare("SELECT jogo_id FROM biblioteca WHERE usuario_id = ?");
    $stmt->execute([$user_id]);
    $meus_jogos = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->prepare("SELECT jogo_id FROM lista_desejos WHERE usuario_id = ?");
    $stmt->execute([$user_id]);
    $minha_wishlist = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $stmt = $pdo->prepare("SELECT jogo_id FROM carrinho WHERE usuario_id = ?");
    $stmt->execute([$user_id]);
    $meu_carrinho = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Buscar jogos em promoção
$stmt = $pdo->query("
    SELECT j.*, d.nome_estudio,
           CASE 
               WHEN j.preco_promocional_centavos IS NOT NULL 
               THEN ROUND(((j.preco_centavos - j.preco_promocional_centavos) / j.preco_centavos) * 100)
               ELSE 0
           END as percentual_desconto
    FROM jogo j
    JOIN desenvolvedor d ON j.desenvolvedor_id = d.id
    WHERE j.status = 'publicado' 
    AND j.em_promocao = 1
    AND j.preco_promocional_centavos IS NOT NULL
    ORDER BY percentual_desconto DESC, j.nota_media DESC
    LIMIT 60
");
$jogos_evento = $stmt->fetchAll();

// Destaques (top 6)
$jogos_destaque = array_slice($jogos_evento, 0, 6);

// Estatísticas
$total_jogos = count($jogos_evento);
$desconto_medio = 0;
$economia_total = 0;
$maior_desconto = 0;

if ($total_jogos > 0) {
    $soma_descontos = array_sum(array_column($jogos_evento, 'percentual_desconto'));
    $desconto_medio = round($soma_descontos / $total_jogos);
    $maior_desconto = max(array_column($jogos_evento, 'percentual_desconto'));
    
    foreach ($jogos_evento as $jogo) {
        if ($jogo['preco_promocional_centavos']) {
            $economia_total += ($jogo['preco_centavos'] - $jogo['preco_promocional_centavos']);
        }
    }
}

$page_title = htmlspecialchars($evento['nome']) . ' - ' . SITE_NAME;
require_once '../includes/header.php';
require_once '../components/game-card.php';
?>

<style>
/* ============================================
   EVENTO PAGE - CLEAN VERSION
   ============================================ */

/* Hero Section */
.evento-hero {
    position: relative;
    min-height: 70vh;
    display: flex;
    align-items: flex-end;
    overflow: hidden;
}

.hero-bg {
    position: absolute;
    inset: 0;
    background-size: cover;
    background-position: center;
}

.hero-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, 
        rgba(10, 10, 15, 0.4) 0%, 
        rgba(10, 10, 15, 0.7) 50%,
        var(--bg-primary) 100%
    );
}

.hero-content {
    position: relative;
    z-index: 10;
    width: 100%;
    max-width: 1400px;
    margin: 0 auto;
    padding: 60px 24px 80px;
}

.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: var(--accent);
    color: white;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 20px;
}

.hero-badge.inactive {
    background: var(--bg-secondary);
    color: var(--text-secondary);
    border: 1px solid var(--border);
}

.hero-badge i {
    font-size: 10px;
}

.hero-title {
    font-size: clamp(2.5rem, 6vw, 4rem);
    font-weight: 800;
    color: white;
    margin-bottom: 16px;
    line-height: 1.1;
    letter-spacing: -1px;
}

.hero-description {
    font-size: 1.1rem;
    color: rgba(255, 255, 255, 0.8);
    max-width: 600px;
    line-height: 1.7;
    margin-bottom: 24px;
}

.hero-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 24px;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.95rem;
}

.hero-meta span {
    display: flex;
    align-items: center;
    gap: 8px;
}

.hero-meta i {
    color: var(--accent);
}

/* Countdown */
.countdown-section {
    padding: 0 24px;
    margin-top: -30px;
    position: relative;
    z-index: 20;
}

.countdown-card {
    max-width: 800px;
    margin: 0 auto;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 32px;
    text-align: center;
}

.countdown-label {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.countdown-label i {
    color: var(--accent);
}

.countdown-timer {
    display: flex;
    justify-content: center;
    gap: 16px;
}

.countdown-item {
    text-align: center;
}

.countdown-number {
    display: block;
    font-size: 3rem;
    font-weight: 800;
    color: var(--text-primary);
    line-height: 1;
    min-width: 80px;
    padding: 16px;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: 12px;
    margin-bottom: 8px;
}

.countdown-unit {
    font-size: 0.8rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* Stats */
.stats-section {
    padding: 60px 24px;
    max-width: 1400px;
    margin: 0 auto;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}

.stat-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 24px;
    text-align: center;
    transition: all 0.2s;
}

.stat-card:hover {
    border-color: var(--accent);
    transform: translateY(-4px);
}

.stat-icon {
    width: 48px;
    height: 48px;
    background: rgba(var(--accent-rgb), 0.1);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    color: var(--accent);
    font-size: 20px;
}

.stat-value {
    font-size: 2rem;
    font-weight: 800;
    color: var(--text-primary);
    line-height: 1;
    margin-bottom: 4px;
}

.stat-label {
    font-size: 0.85rem;
    color: var(--text-secondary);
}

/* Section Header */
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
    gap: 16px;
    flex-wrap: wrap;
}

.section-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 12px;
}

.section-title i {
    color: var(--accent);
    font-size: 20px;
}

/* Featured Section */
.featured-section {
    padding: 0 24px 60px;
    max-width: 1400px;
    margin: 0 auto;
}

.featured-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

.featured-main {
    grid-row: span 2;
}

.featured-card {
    position: relative;
    border-radius: 16px;
    overflow: hidden;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    text-decoration: none;
    display: block;
    height: 100%;
    min-height: 300px;
    transition: all 0.3s;
}

.featured-card:hover {
    border-color: var(--accent);
    transform: translateY(-4px);
}

.featured-card-bg {
    position: absolute;
    inset: 0;
    background-size: cover;
    background-position: center;
    transition: transform 0.4s;
}

.featured-card:hover .featured-card-bg {
    transform: scale(1.05);
}

.featured-card-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, transparent 40%, rgba(0,0,0,0.9) 100%);
}

.featured-card-discount {
    position: absolute;
    top: 16px;
    right: 16px;
    background: var(--accent);
    color: white;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 800;
}

.featured-card-content {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 24px;
}

.featured-card-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: white;
    margin-bottom: 8px;
}

.featured-card-dev {
    font-size: 0.9rem;
    color: rgba(255,255,255,0.7);
    margin-bottom: 12px;
}

.featured-card-prices {
    display: flex;
    align-items: center;
    gap: 12px;
}

.price-original {
    font-size: 0.9rem;
    color: rgba(255,255,255,0.5);
    text-decoration: line-through;
}

.price-current {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--accent);
}

/* Featured List Item */
.featured-list-item {
    display: flex;
    gap: 16px;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 12px;
    text-decoration: none;
    transition: all 0.2s;
}

.featured-list-item:hover {
    border-color: var(--accent);
    transform: translateX(4px);
}

.featured-list-image {
    width: 120px;
    height: 70px;
    border-radius: 8px;
    overflow: hidden;
    flex-shrink: 0;
    position: relative;
}

.featured-list-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.featured-list-discount {
    position: absolute;
    top: 4px;
    left: 4px;
    background: var(--accent);
    color: white;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 700;
}

.featured-list-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-width: 0;
}

.featured-list-title {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.featured-list-dev {
    font-size: 0.8rem;
    color: var(--text-secondary);
    margin-bottom: 8px;
}

.featured-list-prices {
    display: flex;
    align-items: center;
    gap: 8px;
}

.featured-list-prices .price-original {
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.featured-list-prices .price-current {
    font-size: 1rem;
    color: var(--accent);
}

/* Filters */
.filters-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 24px;
    padding: 16px 20px;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 12px;
}

.filters-left {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.filter-btn {
    padding: 10px 18px;
    background: transparent;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.2s;
}

.filter-btn:hover {
    border-color: var(--accent);
    color: var(--accent);
}

.filter-btn.active {
    background: var(--accent);
    border-color: var(--accent);
    color: white;
}

.sort-select {
    appearance: none;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 10px 36px 10px 14px;
    font-size: 0.9rem;
    color: var(--text-primary);
    cursor: pointer;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%23888' viewBox='0 0 24 24'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    background-size: 18px;
}

.sort-select:focus {
    outline: none;
    border-color: var(--accent);
}

/* Games Section */
.games-section {
    padding: 0 24px 80px;
    max-width: 1400px;
    margin: 0 auto;
}

.games-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 20px;
}

.games-count {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin-bottom: 20px;
}

.games-count strong {
    color: var(--text-primary);
}

/* Empty State */
.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 80px 20px;
    background: var(--bg-secondary);
    border: 1px dashed var(--border);
    border-radius: 16px;
}

.empty-state i {
    font-size: 48px;
    color: var(--text-secondary);
    opacity: 0.3;
    margin-bottom: 16px;
}

.empty-state h3 {
    font-size: 1.2rem;
    color: var(--text-primary);
    margin-bottom: 8px;
}

.empty-state p {
    color: var(--text-secondary);
}

/* Event Ended */
.event-ended {
    max-width: 600px;
    margin: 60px auto;
    padding: 0 24px;
}

.ended-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 48px;
    text-align: center;
}

.ended-icon {
    width: 72px;
    height: 72px;
    background: rgba(var(--accent-rgb), 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    color: var(--accent);
    font-size: 28px;
}

.ended-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 8px;
}

.ended-text {
    color: var(--text-secondary);
    margin-bottom: 24px;
}

.ended-date {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--bg-primary);
    padding: 10px 20px;
    border-radius: 8px;
    color: var(--text-secondary);
    font-size: 0.9rem;
    margin-bottom: 24px;
}

.ended-date i {
    color: var(--accent);
}

.ended-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: var(--accent);
    color: white;
    border-radius: 10px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
}

.ended-btn:hover {
    transform: translateY(-2px);
    opacity: 0.9;
}

/* Responsive */
@media (max-width: 1024px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .featured-grid {
        grid-template-columns: 1fr;
    }
    
    .featured-main {
        grid-row: auto;
    }
}

@media (max-width: 768px) {
    .evento-hero {
        min-height: 60vh;
    }
    
    .hero-content {
        padding: 40px 20px 60px;
    }
    
    .hero-title {
        font-size: 2rem;
    }
    
    .countdown-timer {
        gap: 8px;
    }
    
    .countdown-number {
        font-size: 2rem;
        min-width: 60px;
        padding: 12px;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    
    .stat-card {
        padding: 20px 16px;
    }
    
    .stat-value {
        font-size: 1.5rem;
    }
    
    .filters-bar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filters-left {
        overflow-x: auto;
        flex-wrap: nowrap;
        padding-bottom: 8px;
    }
    
    .filter-btn {
        white-space: nowrap;
        flex-shrink: 0;
    }
    
    .games-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    
    .featured-list-item {
        flex-direction: column;
    }
    
    .featured-list-image {
        width: 100%;
        height: 100px;
    }
}
</style>

<div class="evento-page">
    
    <!-- Hero -->
    <section class="evento-hero">
        <div class="hero-bg" style="background-image: url('<?= SITE_URL . $evento['imagem_banner'] ?>');"></div>
        <div class="hero-overlay"></div>
        
        <div class="hero-content">
            <?php if ($evento_ativo): ?>
                <span class="hero-badge">
                    <i class="fas fa-circle"></i>
                    Evento Ativo
                </span>
            <?php elseif ($evento_futuro): ?>
                <span class="hero-badge inactive">
                    <i class="fas fa-clock"></i>
                    Em Breve
                </span>
            <?php else: ?>
                <span class="hero-badge inactive">
                    <i class="fas fa-calendar-times"></i>
                    Encerrado
                </span>
            <?php endif; ?>
            
            <h1 class="hero-title"><?= htmlspecialchars($evento['nome']) ?></h1>
            <p class="hero-description"><?= htmlspecialchars($evento['descricao']) ?></p>
            
            <div class="hero-meta">
                <span>
                    <i class="fas fa-calendar"></i>
                    <?= date('d/m', strtotime($evento['data_inicio'])) ?> - <?= date('d/m/Y', strtotime($evento['data_fim'])) ?>
                </span>
                <span>
                    <i class="fas fa-gamepad"></i>
                    <?= $total_jogos ?> jogos
                </span>
                <span>
                    <i class="fas fa-percent"></i>
                    Até <?= $maior_desconto ?>% OFF
                </span>
            </div>
        </div>
    </section>
    
    <!-- Countdown -->
    <?php if ($evento_ativo || $evento_futuro): ?>
    <section class="countdown-section">
        <div class="countdown-card">
            <div class="countdown-label">
                <i class="fas fa-hourglass-half"></i>
                <?= $evento_ativo ? 'Termina em' : 'Começa em' ?>
            </div>
            
            <div class="countdown-timer" id="countdown">
                <div class="countdown-item">
                    <span class="countdown-number" id="days">00</span>
                    <span class="countdown-unit">Dias</span>
                </div>
                <div class="countdown-item">
                    <span class="countdown-number" id="hours">00</span>
                    <span class="countdown-unit">Horas</span>
                </div>
                <div class="countdown-item">
                    <span class="countdown-number" id="minutes">00</span>
                    <span class="countdown-unit">Min</span>
                </div>
                <div class="countdown-item">
                    <span class="countdown-number" id="seconds">00</span>
                    <span class="countdown-unit">Seg</span>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>
    
    <!-- Stats -->
    <section class="stats-section">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-gamepad"></i></div>
                <div class="stat-value"><?= $total_jogos ?></div>
                <div class="stat-label">Jogos em Oferta</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-percent"></i></div>
                <div class="stat-value"><?= $desconto_medio ?>%</div>
                <div class="stat-label">Desconto Médio</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-piggy-bank"></i></div>
                <div class="stat-value"><?= formatPrice($economia_total) ?></div>
                <div class="stat-label">Economia Possível</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-fire"></i></div>
                <div class="stat-value"><?= $maior_desconto ?>%</div>
                <div class="stat-label">Maior Desconto</div>
            </div>
        </div>
    </section>
    
    <?php if ($evento_encerrado): ?>
    
    <!-- Event Ended -->
    <section class="event-ended">
        <div class="ended-card">
            <div class="ended-icon">
                <i class="fas fa-calendar-times"></i>
            </div>
            <h2 class="ended-title">Evento Encerrado</h2>
            <p class="ended-text">Este evento já terminou, mas você pode conferir outras promoções disponíveis.</p>
            <div class="ended-date">
                <i class="fas fa-clock"></i>
                Encerrado em <?= date('d/m/Y \à\s H:i', strtotime($evento['data_fim'])) ?>
            </div>
            <br>
            <a href="<?= SITE_URL ?>/pages/busca.php?promocao=1" class="ended-btn">
                <i class="fas fa-tags"></i>
                Ver Promoções Atuais
            </a>
        </div>
    </section>
    
    <?php else: ?>
    
    <!-- Featured -->
    <?php if (!empty($jogos_destaque)): ?>
    <section class="featured-section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-star"></i>
                Destaques
            </h2>
        </div>
        
        <div class="featured-grid">
            <!-- Main Featured -->
            <?php $main = $jogos_destaque[0]; ?>
            <a href="<?= SITE_URL ?>/pages/jogo.php?slug=<?= $main['slug'] ?>" class="featured-card featured-main">
                <div class="featured-card-bg" style="background-image: url('<?= SITE_URL . $main['imagem_banner'] ?>');"></div>
                <div class="featured-card-overlay"></div>
                <span class="featured-card-discount">-<?= $main['percentual_desconto'] ?>%</span>
                <div class="featured-card-content">
                    <h3 class="featured-card-title"><?= htmlspecialchars($main['titulo']) ?></h3>
                    <p class="featured-card-dev"><?= htmlspecialchars($main['nome_estudio']) ?></p>
                    <div class="featured-card-prices">
                        <span class="price-original"><?= formatPrice($main['preco_centavos']) ?></span>
                        <span class="price-current"><?= formatPrice($main['preco_promocional_centavos']) ?></span>
                    </div>
                </div>
            </a>
            
            <!-- List Items -->
            <?php foreach (array_slice($jogos_destaque, 1, 4) as $jogo): ?>
            <a href="<?= SITE_URL ?>/pages/jogo.php?slug=<?= $jogo['slug'] ?>" class="featured-list-item">
                <div class="featured-list-image">
                    <img src="<?= SITE_URL . $jogo['imagem_capa'] ?>" alt="<?= htmlspecialchars($jogo['titulo']) ?>">
                    <span class="featured-list-discount">-<?= $jogo['percentual_desconto'] ?>%</span>
                </div>
                <div class="featured-list-info">
                    <h4 class="featured-list-title"><?= htmlspecialchars($jogo['titulo']) ?></h4>
                    <span class="featured-list-dev"><?= htmlspecialchars($jogo['nome_estudio']) ?></span>
                    <div class="featured-list-prices">
                        <span class="price-original"><?= formatPrice($jogo['preco_centavos']) ?></span>
                        <span class="price-current"><?= formatPrice($jogo['preco_promocional_centavos']) ?></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
    
    <!-- All Games -->
    <section class="games-section" id="ofertas">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-tags"></i>
                Todas as Ofertas
            </h2>
        </div>
        
        <div class="filters-bar">
            <div class="filters-left">
                <button class="filter-btn active" data-filter="all">Todos</button>
                <button class="filter-btn" data-filter="50">50%+ OFF</button>
                <button class="filter-btn" data-filter="75">75%+ OFF</button>
            </div>
            <select class="sort-select" id="sortSelect">
                <option value="discount">Maior Desconto</option>
                <option value="price-low">Menor Preço</option>
                <option value="price-high">Maior Preço</option>
                <option value="name">Nome A-Z</option>
            </select>
        </div>
        
        <p class="games-count">
            Mostrando <strong id="visibleCount"><?= $total_jogos ?></strong> jogos
        </p>
        
        <div class="games-grid" id="gamesGrid">
            <?php if (!empty($jogos_evento)): ?>
                <?php foreach ($jogos_evento as $jogo): ?>
                    <div class="game-card-wrapper" 
                         data-discount="<?= $jogo['percentual_desconto'] ?>" 
                         data-price="<?= $jogo['preco_promocional_centavos'] ?>"
                         data-name="<?= htmlspecialchars($jogo['titulo']) ?>">
                        <?php 
                        renderGameCard($jogo, $pdo, $user_id, 'store', [
                            'is_owned' => in_array($jogo['id'], $meus_jogos),
                            'in_wishlist' => in_array($jogo['id'], $minha_wishlist),
                            'in_cart' => in_array($jogo['id'], $meu_carrinho)
                        ]); 
                        ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h3>Nenhuma oferta disponível</h3>
                    <p>As ofertas serão adicionadas em breve.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>
    
    <?php endif; ?>
    
</div>

<script>
// Countdown
<?php if ($evento_ativo || $evento_futuro): ?>
const targetDate = new Date('<?= $evento_ativo ? $evento['data_fim'] : $evento['data_inicio'] ?>').getTime();

function updateCountdown() {
    const now = Date.now();
    const distance = targetDate - now;
    
    if (distance < 0) {
        location.reload();
        return;
    }
    
    const d = Math.floor(distance / (1000 * 60 * 60 * 24));
    const h = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const m = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
    const s = Math.floor((distance % (1000 * 60)) / 1000);
    
    document.getElementById('days').textContent = String(d).padStart(2, '0');
    document.getElementById('hours').textContent = String(h).padStart(2, '0');
    document.getElementById('minutes').textContent = String(m).padStart(2, '0');
    document.getElementById('seconds').textContent = String(s).padStart(2, '0');
}

updateCountdown();
setInterval(updateCountdown, 1000);
<?php endif; ?>

// Filter & Sort
const grid = document.getElementById('gamesGrid');
const filterBtns = document.querySelectorAll('.filter-btn');
const sortSelect = document.getElementById('sortSelect');
let allCards = Array.from(document.querySelectorAll('.game-card-wrapper'));

filterBtns.forEach(btn => {
    btn.addEventListener('click', function() {
        filterBtns.forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        filterGames(this.dataset.filter);
    });
});

sortSelect?.addEventListener('change', function() {
    sortGames(this.value);
});

function filterGames(filter) {
    let visible = 0;
    
    allCards.forEach(card => {
        const discount = parseInt(card.dataset.discount) || 0;
        let show = true;
        
        if (filter === '50') show = discount >= 50;
        else if (filter === '75') show = discount >= 75;
        
        card.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    
    document.getElementById('visibleCount').textContent = visible;
}

function sortGames(sortBy) {
    const cards = Array.from(grid.querySelectorAll('.game-card-wrapper'));
    
    cards.sort((a, b) => {
        const dA = parseInt(a.dataset.discount) || 0;
        const dB = parseInt(b.dataset.discount) || 0;
        const pA = parseInt(a.dataset.price) || 0;
        const pB = parseInt(b.dataset.price) || 0;
        const nA = a.dataset.name || '';
        const nB = b.dataset.name || '';
        
        switch (sortBy) {
            case 'discount': return dB - dA;
            case 'price-low': return pA - pB;
            case 'price-high': return pB - pA;
            case 'name': return nA.localeCompare(nB);
            default: return 0;
        }
    });
    
    cards.forEach(card => grid.appendChild(card));
}

// Simple fade-in animation
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
        }
    });
}, { threshold: 0.1 });

document.querySelectorAll('.game-card-wrapper, .stat-card').forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(20px)';
    el.style.transition = 'opacity 0.4s, transform 0.4s';
    observer.observe(el);
});
</script>

<?php require_once '../includes/footer.php'; ?>