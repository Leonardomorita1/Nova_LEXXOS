<?php
// pages/evento.php
require_once '../config/config.php';
require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();
$user_id = isLoggedIn() ? $_SESSION['user_id'] : null;

// Buscar evento pelo slug
if (!isset($_GET['slug'])) {
    header('Location: ' . SITE_URL . '/pages/home.php');
    exit;
}

$slug = $_GET['slug'];

$stmt = $pdo->prepare("
    SELECT * FROM evento 
    WHERE slug = ? AND ativo = 1
");
$stmt->execute([$slug]);
$evento = $stmt->fetch();

if (!$evento) {
    header('Location: ' . SITE_URL . '/pages/home.php');
    exit;
}

// Verificar se evento está no período válido
$agora = date('Y-m-d H:i:s');
$evento_ativo = ($agora >= $evento['data_inicio'] && $agora <= $evento['data_fim']);

// Buscar jogos em promoção durante o evento
$stmt = $pdo->query("
    SELECT j.*, d.nome_estudio,
           j.preco_promocional_centavos,
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
    ORDER BY percentual_desconto DESC, j.criado_em DESC
    LIMIT 50
");
$jogos_evento = $stmt->fetchAll();

// Estatísticas do evento
$total_jogos = count($jogos_evento);
$desconto_medio = 0;
if ($total_jogos > 0) {
    $soma_descontos = array_sum(array_column($jogos_evento, 'percentual_desconto'));
    $desconto_medio = round($soma_descontos / $total_jogos);
}

// Calcular economia total possível
$economia_total = 0;
foreach ($jogos_evento as $jogo) {
    if ($jogo['preco_promocional_centavos']) {
        $economia_total += ($jogo['preco_centavos'] - $jogo['preco_promocional_centavos']);
    }
}

// Maior desconto
$maior_desconto = !empty($jogos_evento) ? max(array_column($jogos_evento, 'percentual_desconto')) : 0;

$page_title = htmlspecialchars($evento['nome']) . ' - ' . SITE_NAME;
require_once '../includes/header.php';
require_once '../components/game-card.php';
?>

<style>
    /* Hero Section */
    .evento-hero {
        position: relative;
        height: 500px;
        background-size: cover;
        background-position: center;
        border-radius: 20px;
        overflow: hidden;
        margin-bottom: 50px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    }

    .evento-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, rgba(0,0,0,0.3) 0%, rgba(0,0,0,0.7) 50%, rgba(0,0,0,0.95) 100%);
        z-index: 1;
    }

    .evento-hero::after {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at 30% 50%, rgba(76, 139, 245, 0.15), transparent 60%);
        z-index: 1;
    }

    .evento-hero-particles {
        position: absolute;
        inset: 0;
        z-index: 1;
        overflow: hidden;
        pointer-events: none;
    }

    .particle {
        position: absolute;
        width: 4px;
        height: 4px;
        background: rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        animation: float 6s infinite ease-in-out;
    }

    @keyframes float {
        0%, 100% { transform: translateY(0) translateX(0); opacity: 0; }
        10% { opacity: 1; }
        90% { opacity: 1; }
        100% { transform: translateY(-100vh) translateX(50px); opacity: 0; }
    }

    .evento-hero-content {
        position: relative;
        z-index: 2;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        padding: 50px;
        max-width: 900px;
    }

    .evento-badge {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        background: linear-gradient(135deg, #ff6b6b, #ff8787);
        color: white;
        padding: 10px 20px;
        border-radius: 25px;
        font-size: 14px;
        font-weight: 700;
        letter-spacing: 0.5px;
        margin-bottom: 20px;
        width: fit-content;
        box-shadow: 0 8px 24px rgba(255, 107, 107, 0.3);
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }

    .evento-title {
        font-size: 56px;
        font-weight: 900;
        color: white;
        margin-bottom: 16px;
        text-shadow: 0 4px 20px rgba(0,0,0,0.5);
        line-height: 1.1;
        letter-spacing: -1px;
    }

    .evento-description {
        font-size: 20px;
        color: rgba(255,255,255,0.95);
        line-height: 1.7;
        text-shadow: 0 2px 10px rgba(0,0,0,0.3);
    }

    /* Countdown */
    .countdown {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 20px;
        padding: 40px;
        text-align: center;
        color: white;
        margin: 50px 0;
        position: relative;
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(102, 126, 234, 0.3);
    }

    .countdown::before {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at 20% 50%, rgba(255,255,255,0.1), transparent 50%);
        pointer-events: none;
    }

    .countdown-title {
        font-size: 22px;
        font-weight: 700;
        margin-bottom: 30px;
        text-transform: uppercase;
        letter-spacing: 1px;
        position: relative;
    }

    .countdown-timer {
        display: flex;
        justify-content: center;
        gap: 30px;
        position: relative;
    }

    .countdown-item {
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(10px);
        border-radius: 16px;
        padding: 24px 32px;
        min-width: 120px;
        border: 2px solid rgba(255, 255, 255, 0.2);
        transition: transform 0.3s;
    }

    .countdown-item:hover {
        transform: translateY(-5px);
        background: rgba(255, 255, 255, 0.2);
    }

    .countdown-number {
        font-size: 56px;
        font-weight: 900;
        display: block;
        line-height: 1;
        font-family: 'Segoe UI', system-ui, sans-serif;
    }

    .countdown-label {
        font-size: 14px;
        opacity: 0.95;
        margin-top: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    /* Stats Cards */
    .evento-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 24px;
        margin: 50px 0;
    }

    .stat-card {
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 32px;
        text-align: center;
        transition: all 0.3s;
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, transparent, rgba(76, 139, 245, 0.05));
        opacity: 0;
        transition: opacity 0.3s;
    }

    .stat-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        border-color: var(--accent);
    }

    .stat-card:hover::before {
        opacity: 1;
    }

    .stat-icon {
        width: 70px;
        height: 70px;
        background: linear-gradient(135deg, var(--accent), #6366f1);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        color: white;
        margin: 0 auto 20px;
        box-shadow: 0 8px 24px rgba(76, 139, 245, 0.3);
    }

    .stat-value {
        font-size: 40px;
        font-weight: 900;
        color: var(--text-primary);
        margin-bottom: 8px;
        line-height: 1;
    }

    .stat-label {
        font-size: 15px;
        color: var(--text-secondary);
        font-weight: 600;
    }

    /* Section Header */
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin: 70px 0 35px;
        flex-wrap: wrap;
        gap: 20px;
    }

    .section-title {
        font-size: 32px;
        font-weight: 800;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .section-title i {
        color: var(--accent);
    }

    /* Filter Pills */
    .filter-container {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        align-items: center;
    }

    .filter-label {
        font-size: 14px;
        color: var(--text-secondary);
        font-weight: 600;
        margin-right: 8px;
    }

    .filter-pill {
        padding: 10px 20px;
        background: var(--bg-secondary);
        border: 2px solid var(--border);
        border-radius: 25px;
        font-size: 14px;
        font-weight: 700;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.3s;
        white-space: nowrap;
    }

    .filter-pill:hover {
        background: rgba(76, 139, 245, 0.1);
        border-color: var(--accent);
        transform: translateY(-2px);
    }

    .filter-pill.active {
        background: linear-gradient(135deg, var(--accent), #6366f1);
        color: white;
        border-color: var(--accent);
        box-shadow: 0 6px 20px rgba(76, 139, 245, 0.4);
    }

    /* Games Grid */
    .cards-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 24px;
        margin-bottom: 70px;
    }

    .grid-loading {
        grid-column: 1/-1;
        text-align: center;
        padding: 80px 20px;
        color: var(--text-secondary);
    }

    .loading-spinner {
        width: 60px;
        height: 60px;
        border: 4px solid var(--border);
        border-top-color: var(--accent);
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin: 0 auto 20px;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    .empty-state {
        grid-column: 1/-1;
        text-align: center;
        padding: 80px 20px;
        background: var(--bg-secondary);
        border-radius: 20px;
        border: 2px dashed var(--border);
    }

    .empty-state i {
        font-size: 80px;
        margin-bottom: 24px;
        opacity: 0.3;
        color: var(--text-secondary);
    }

    .empty-state h3 {
        font-size: 24px;
        margin-bottom: 12px;
        color: var(--text-primary);
    }

    .empty-state p {
        color: var(--text-secondary);
        font-size: 16px;
    }

    /* Evento Encerrado */
    .evento-ended {
        background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));
        border: 2px solid var(--danger);
        border-radius: 20px;
        padding: 50px;
        text-align: center;
        margin: 50px 0;
    }

    .evento-ended i {
        font-size: 64px;
        color: var(--danger);
        margin-bottom: 20px;
        opacity: 0.8;
    }

    .evento-ended h3 {
        color: var(--danger);
        font-size: 28px;
        margin-bottom: 12px;
        font-weight: 800;
    }

    .evento-ended p {
        color: var(--text-secondary);
        font-size: 16px;
    }

    /* Results Counter */
    .results-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 24px;
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 12px;
        margin-bottom: 24px;
        font-size: 14px;
    }

    .results-count {
        color: var(--text-primary);
        font-weight: 700;
    }

    .results-filter {
        color: var(--accent);
        font-weight: 600;
    }

    .clear-filter {
        color: var(--text-secondary);
        cursor: pointer;
        text-decoration: underline;
        transition: color 0.2s;
    }

    .clear-filter:hover {
        color: var(--accent);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .evento-hero {
            height: 350px;
        }
        
        .evento-hero-content {
            padding: 30px;
        }
        
        .evento-title {
            font-size: 36px;
        }
        
        .evento-description {
            font-size: 16px;
        }

        .countdown {
            padding: 30px 20px;
        }
        
        .countdown-timer {
            gap: 12px;
        }

        .countdown-item {
            padding: 16px 12px;
            min-width: auto;
            flex: 1;
        }
        
        .countdown-number {
            font-size: 36px;
        }

        .countdown-label {
            font-size: 11px;
        }

        .section-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .section-title {
            font-size: 24px;
        }

        .filter-container {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 8px;
        }

        .filter-container::-webkit-scrollbar {
            height: 4px;
        }

        .filter-container::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 4px;
        }

        .cards-grid {
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 16px;
        }

        .stat-value {
            font-size: 32px;
        }
    }

    /* Animações de entrada */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .animate-in {
        animation: fadeInUp 0.6s ease-out;
    }

    .animate-in-delay-1 { animation-delay: 0.1s; }
    .animate-in-delay-2 { animation-delay: 0.2s; }
    .animate-in-delay-3 { animation-delay: 0.3s; }
    .animate-in-delay-4 { animation-delay: 0.4s; }
</style>

<div class="container" style="padding: 30px 20px;">
    
    <!-- Hero -->
    <div class="evento-hero animate-in" style="background-image: url('<?= SITE_URL . $evento['imagem_banner'] ?>');">
        <div class="evento-hero-particles" id="particles"></div>
        <div class="evento-hero-content">
            <div class="evento-badge">
                <i class="fas fa-fire"></i>
                <span>Evento Especial</span>
            </div>
            <h1 class="evento-title"><?= htmlspecialchars($evento['nome']) ?></h1>
            <p class="evento-description"><?= htmlspecialchars($evento['descricao']) ?></p>
        </div>
    </div>
    
    <!-- Countdown ou Evento Encerrado -->
    <?php if ($evento_ativo): ?>
        <div class="countdown animate-in animate-in-delay-1">
            <div class="countdown-title">
                <i class="fas fa-hourglass-half"></i> O evento termina em:
            </div>
            <div class="countdown-timer" id="countdown">
                <div class="countdown-item">
                    <span class="countdown-number" id="days">00</span>
                    <span class="countdown-label">Dias</span>
                </div>
                <div class="countdown-item">
                    <span class="countdown-number" id="hours">00</span>
                    <span class="countdown-label">Horas</span>
                </div>
                <div class="countdown-item">
                    <span class="countdown-number" id="minutes">00</span>
                    <span class="countdown-label">Minutos</span>
                </div>
                <div class="countdown-item">
                    <span class="countdown-number" id="seconds">00</span>
                    <span class="countdown-label">Segundos</span>
                </div>
            </div>
        </div>
    <?php elseif ($agora < $evento['data_inicio']): ?>
        <div class="countdown animate-in animate-in-delay-1">
            <div class="countdown-title">
                <i class="fas fa-hourglass-start"></i> O evento começa em:
            </div>
            <div class="countdown-timer" id="countdown">
                <div class="countdown-item">
                    <span class="countdown-number" id="days">00</span>
                    <span class="countdown-label">Dias</span>
                </div>
                <div class="countdown-item">
                    <span class="countdown-number" id="hours">00</span>
                    <span class="countdown-label">Horas</span>
                </div>
                <div class="countdown-item">
                    <span class="countdown-number" id="minutes">00</span>
                    <span class="countdown-label">Minutos</span>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="evento-ended animate-in animate-in-delay-1">
            <i class="fas fa-calendar-times"></i>
            <h3>Evento Encerrado</h3>
            <p>Este evento terminou em <?= date('d/m/Y \à\s H:i', strtotime($evento['data_fim'])) ?></p>
        </div>
    <?php endif; ?>
    
    <!-- Estatísticas -->
    <div class="evento-stats">
        <div class="stat-card animate-in animate-in-delay-1">
            <div class="stat-icon">
                <i class="fas fa-gamepad"></i>
            </div>
            <div class="stat-value"><?= $total_jogos ?></div>
            <div class="stat-label">Jogos em Oferta</div>
        </div>
        
        <div class="stat-card animate-in animate-in-delay-2">
            <div class="stat-icon">
                <i class="fas fa-percent"></i>
            </div>
            <div class="stat-value"><?= $desconto_medio ?>%</div>
            <div class="stat-label">Desconto Médio</div>
        </div>
        
        <div class="stat-card animate-in animate-in-delay-3">
            <div class="stat-icon">
                <i class="fas fa-piggy-bank"></i>
            </div>
            <div class="stat-value"><?= formatPrice($economia_total) ?></div>
            <div class="stat-label">Economia Total Possível</div>
        </div>
        
        <div class="stat-card animate-in animate-in-delay-4">
            <div class="stat-icon">
                <i class="fas fa-fire"></i>
            </div>
            <div class="stat-value"><?= $maior_desconto ?>%</div>
            <div class="stat-label">Maior Desconto</div>
        </div>
    </div>
    
    <!-- Filtros e Jogos -->
    <div class="section-header animate-in">
        <h2 class="section-title">
            <i class="fas fa-tags"></i> 
            <span>Ofertas do Evento</span>
        </h2>
        <div class="filter-container">
            <span class="filter-label">Filtrar:</span>
            <button class="filter-pill active" data-filter="all">
                <i class="fas fa-th"></i> Todos
            </button>
            <button class="filter-pill" data-filter="50">
                <i class="fas fa-fire"></i> 50%+ OFF
            </button>
            <button class="filter-pill" data-filter="75">
                <i class="fas fa-fire-alt"></i> 75%+ OFF
            </button>
            <button class="filter-pill" data-filter="price-low">
                <i class="fas fa-arrow-down"></i> Menor Preço
            </button>
            <button class="filter-pill" data-filter="discount-high">
                <i class="fas fa-arrow-up"></i> Maior Desconto
            </button>
        </div>
    </div>

    <!-- Results Info -->
    <div class="results-info" id="resultsInfo" style="display: none;">
        <div>
            <span class="results-count" id="resultsCount">0 jogos</span>
            <span class="results-filter" id="activeFilter"></span>
        </div>
        <span class="clear-filter" onclick="clearFilter()">
            <i class="fas fa-times"></i> Limpar filtro
        </span>
    </div>
    
    <div class="cards-grid" id="gamesGrid">
        <?php if (!empty($jogos_evento)): ?>
            <?php foreach ($jogos_evento as $jogo): ?>
                <div class="game-card-wrapper" 
                     data-discount="<?= $jogo['percentual_desconto'] ?>" 
                     data-price="<?= $jogo['preco_promocional_centavos'] ?>">
                    <?php renderGameCard($jogo, $pdo, $user_id); ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-gamepad"></i>
                <h3>Nenhuma Oferta Disponível</h3>
                <p>Volte em breve para conferir as ofertas deste evento!</p>
            </div>
        <?php endif; ?>
    </div>
    
</div>

<script>
// Particles no Hero
function createParticles() {
    const container = document.getElementById('particles');
    if (!container) return;
    
    for (let i = 0; i < 20; i++) {
        const particle = document.createElement('div');
        particle.className = 'particle';
        particle.style.left = Math.random() * 100 + '%';
        particle.style.animationDelay = Math.random() * 6 + 's';
        particle.style.animationDuration = (Math.random() * 3 + 3) + 's';
        container.appendChild(particle);
    }
}

createParticles();

// Countdown Timer
<?php if ($evento_ativo || $agora < $evento['data_inicio']): ?>
const targetDate = new Date('<?= $evento_ativo ? $evento['data_fim'] : $evento['data_inicio'] ?>').getTime();

function updateCountdown() {
    const now = new Date().getTime();
    const distance = targetDate - now;
    
    if (distance < 0) {
        location.reload();
        return;
    }
    
    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((distance % (1000 * 60)) / 1000);
    
    const daysEl = document.getElementById('days');
    const hoursEl = document.getElementById('hours');
    const minutesEl = document.getElementById('minutes');
    const secondsEl = document.getElementById('seconds');
    
    if (daysEl) daysEl.textContent = String(days).padStart(2, '0');
    if (hoursEl) hoursEl.textContent = String(hours).padStart(2, '0');
    if (minutesEl) minutesEl.textContent = String(minutes).padStart(2, '0');
    if (secondsEl) secondsEl.textContent = String(seconds).padStart(2, '0');
}

updateCountdown();
setInterval(updateCountdown, 1000);
<?php endif; ?>

// Sistema de Filtros Corrigido
let allGames = [];
let currentFilter = 'all';

document.addEventListener('DOMContentLoaded', function() {
    // Capturar todos os wrappers de jogos
    allGames = Array.from(document.querySelectorAll('.game-card-wrapper'));
    
    // Event listeners para os filtros
    document.querySelectorAll('.filter-pill').forEach(pill => {
        pill.addEventListener('click', function() {
            const filter = this.dataset.filter;
            filterGames(filter);
            
            // Atualizar pills
            document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
            this.classList.add('active');
        });
    });
});

function filterGames(filter) {
    currentFilter = filter;
    const grid = document.getElementById('gamesGrid');
    const resultsInfo = document.getElementById('resultsInfo');
    
    // Mostrar loading
    grid.innerHTML = '<div class="grid-loading"><div class="loading-spinner"></div><p>Filtrando jogos...</p></div>';
    
    setTimeout(() => {
        let filtered = [...allGames];
        
        // Aplicar filtros
        if (filter === '50') {
            filtered = allGames.filter(card => {
                const discount = parseInt(card.dataset.discount || 0);
                return discount >= 50;
            });
        } else if (filter === '75') {
            filtered = allGames.filter(card => {
                const discount = parseInt(card.dataset.discount || 0);
                return discount >= 75;
            });
        } else if (filter === 'price-low') {
            filtered = [...allGames].sort((a, b) => {
                const priceA = parseInt(a.dataset.price || 0);
                const priceB = parseInt(b.dataset.price || 0);
                return priceA - priceB;
            });
        } else if (filter === 'discount-high') {
            filtered = [...allGames].sort((a, b) => {
                const discA = parseInt(a.dataset.discount || 0);
                const discB = parseInt(b.dataset.discount || 0);
                return discB - discA;
            });
        }
        
        // Limpar grid
        grid.innerHTML = '';
        
        // Renderizar resultados
        if (filtered.length === 0) {
            grid.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <h3>Nenhum jogo encontrado</h3>
                    <p>Nenhum jogo corresponde ao filtro "${getFilterName(filter)}"</p>
                    <button class="filter-pill" onclick="clearFilter()" style="margin-top: 20px; display: inline-block;">
                        <i class="fas fa-undo"></i> Ver todos os jogos
                    </button>
                </div>
            `;
            resultsInfo.style.display = 'none';
        } else {
            // Adicionar cards com animação
            filtered.forEach((card, index) => {
                const clone = card.cloneNode(true);
                clone.style.opacity = '0';
                clone.style.transform = 'translateY(20px)';
                grid.appendChild(clone);
                
                setTimeout(() => {
                    clone.style.transition = 'all 0.3s ease-out';
                    clone.style.opacity = '1';
                    clone.style.transform = 'translateY(0)';
                }, index * 30);
            });
            
            // Atualizar info de resultados
            updateResultsInfo(filtered.length, filter);
        }
        
        // Smooth scroll para o grid
        grid.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }, 300);
}

function updateResultsInfo(count, filter) {
    const resultsInfo = document.getElementById('resultsInfo');
    const resultsCount = document.getElementById('resultsCount');
    const activeFilter = document.getElementById('activeFilter');
    
    resultsCount.textContent = `${count} jogo${count !== 1 ? 's' : ''}`;
    
    if (filter !== 'all') {
        activeFilter.textContent = ` • ${getFilterName(filter)}`;
        resultsInfo.style.display = 'flex';
    } else {
        resultsInfo.style.display = 'none';
    }
}

function getFilterName(filter) {
    const names = {
        'all': 'Todos',
        '50': 'Desconto 50%+',
        '75': 'Desconto 75%+',
        'price-low': 'Menor Preço',
        'discount-high': 'Maior Desconto'
    };
    return names[filter] || filter;
}

function clearFilter() {
    const allButton = document.querySelector('.filter-pill[data-filter="all"]');
    if (allButton) {
        allButton.click();
    }
}

// Scroll reveal para animações
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -100px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
        }
    });
}, observerOptions);

// Observar elementos para animação
document.querySelectorAll('.game-card-wrapper').forEach(card => {
    card.style.opacity = '0';
    card.style.transform = 'translateY(20px)';
    card.style.transition = 'all 0.5s ease-out';
    observer.observe(card);
});

// Salvar filtro no localStorage
window.addEventListener('beforeunload', function() {
    localStorage.setItem('eventoFilter', currentFilter);
});

// Restaurar filtro salvo
const savedFilter = localStorage.getItem('eventoFilter');
if (savedFilter && savedFilter !== 'all') {
    setTimeout(() => {
        const filterButton = document.querySelector(`.filter-pill[data-filter="${savedFilter}"]`);
        if (filterButton) {
            filterButton.click();
        }
    }, 500);
}

// Atalhos de teclado
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey || e.metaKey) {
        switch(e.key) {
            case '1':
                e.preventDefault();
                document.querySelector('.filter-pill[data-filter="all"]')?.click();
                break;
            case '2':
                e.preventDefault();
                document.querySelector('.filter-pill[data-filter="50"]')?.click();
                break;
            case '3':
                e.preventDefault();
                document.querySelector('.filter-pill[data-filter="75"]')?.click();
                break;
        }
    }
});

console.log('%c🎮 Página de Evento Carregada! ', 'background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 10px 20px; border-radius: 8px; font-weight: bold; font-size: 14px;');
console.log('%cAtalhos: Ctrl+1 (Todos) | Ctrl+2 (50%+) | Ctrl+3 (75%+)', 'color: #667eea; font-size: 12px;');
</script>

<?php require_once '../includes/footer.php'; ?>