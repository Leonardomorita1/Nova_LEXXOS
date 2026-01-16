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
// Priorizamos jogos com promoções ativas dos desenvolvedores
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

$page_title = htmlspecialchars($evento['nome']) . ' - ' . SITE_NAME;
require_once '../includes/header.php';
require_once '../components/game-card.php';
?>

<style>
.evento-hero {
    position: relative;
    height: 400px;
    background-size: cover;
    background-position: center;
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 40px;
}

.evento-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(0,0,0,0.95), transparent 60%);
}

.evento-hero-content {
    position: relative;
    z-index: 2;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    padding: 40px;
}

.evento-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--accent);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 16px;
    width: fit-content;
}

.evento-title {
    font-size: 48px;
    font-weight: 800;
    color: white;
    margin-bottom: 12px;
    text-shadow: 0 4px 12px rgba(0,0,0,0.5);
}

.evento-description {
    font-size: 18px;
    color: rgba(255,255,255,0.9);
    max-width: 700px;
    line-height: 1.6;
}

.evento-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 40px 0;
}

.stat-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 24px;
    text-align: center;
}

.stat-icon {
    width: 56px;
    height: 56px;
    background: rgba(76, 139, 245, 0.2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: var(--accent);
    margin: 0 auto 16px;
}

.stat-value {
    font-size: 32px;
    font-weight: 800;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.stat-label {
    font-size: 14px;
    color: var(--text-secondary);
}

.countdown {
    background: linear-gradient(135deg, var(--accent), #6366f1);
    border-radius: 16px;
    padding: 32px;
    text-align: center;
    color: white;
    margin: 40px 0;
}

.countdown-title {
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 20px;
}

.countdown-timer {
    display: flex;
    justify-content: center;
    gap: 24px;
}

.countdown-item {
    text-align: center;
}

.countdown-number {
    font-size: 48px;
    font-weight: 800;
    display: block;
    line-height: 1;
}

.countdown-label {
    font-size: 14px;
    opacity: 0.9;
    margin-top: 8px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 60px 0 30px;
}

.section-title {
    font-size: 28px;
    font-weight: 700;
    color: var(--text-primary);
}

.filter-pills {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.filter-pill {
    padding: 8px 16px;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.2s;
}

.filter-pill:hover,
.filter-pill.active {
    background: var(--accent);
    color: white;
    border-color: var(--accent);
}

.cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 20px;
    margin-bottom: 60px;
}

.evento-ended {
    background: rgba(220, 53, 69, 0.1);
    border: 1px solid var(--danger);
    border-radius: 12px;
    padding: 24px;
    text-align: center;
    margin: 40px 0;
}

.evento-ended h3 {
    color: var(--danger);
    font-size: 24px;
    margin-bottom: 8px;
}

@media (max-width: 768px) {
    .evento-hero {
        height: 300px;
    }
    
    .evento-hero-content {
        padding: 24px;
    }
    
    .evento-title {
        font-size: 32px;
    }
    
    .evento-description {
        font-size: 16px;
    }
    
    .countdown-timer {
        gap: 16px;
    }
    
    .countdown-number {
        font-size: 36px;
    }
}
</style>

<div class="container" style="padding: 30px 20px;">
    
    <!-- Hero -->
    <div class="evento-hero" style="background-image: url('<?= SITE_URL . $evento['imagem_banner'] ?>');">
        <div class="evento-hero-content">
            <div class="evento-badge">
                <i class="fas fa-fire"></i>
                Evento Especial
            </div>
            <h1 class="evento-title"><?= htmlspecialchars($evento['nome']) ?></h1>
            <p class="evento-description"><?= htmlspecialchars($evento['descricao']) ?></p>
        </div>
    </div>
    
    <!-- Countdown ou Evento Encerrado -->
    <?php if ($evento_ativo): ?>
        <div class="countdown">
            <div class="countdown-title">
                <i class="fas fa-clock"></i> O evento termina em:
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
        <div class="countdown">
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
        <div class="evento-ended">
            <i class="fas fa-calendar-times" style="font-size: 48px; color: var(--danger); margin-bottom: 16px;"></i>
            <h3>Evento Encerrado</h3>
            <p style="color: var(--text-secondary); margin: 0;">
                Este evento terminou em <?= date('d/m/Y \à\s H:i', strtotime($evento['data_fim'])) ?>
            </p>
        </div>
    <?php endif; ?>
    
    <!-- Estatísticas -->
    <div class="evento-stats">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-gamepad"></i>
            </div>
            <div class="stat-value"><?= $total_jogos ?></div>
            <div class="stat-label">Jogos em Oferta</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-percent"></i>
            </div>
            <div class="stat-value"><?= $desconto_medio ?>%</div>
            <div class="stat-label">Desconto Médio</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-piggy-bank"></i>
            </div>
            <div class="stat-value"><?= formatPrice($economia_total) ?></div>
            <div class="stat-label">Economia Total Possível</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-fire"></i>
            </div>
            <div class="stat-value">ATÉ <?= max(array_column($jogos_evento, 'percentual_desconto')) ?>%</div>
            <div class="stat-label">Maior Desconto</div>
        </div>
    </div>
    
    <!-- Filtros e Jogos -->
    <div class="section-header">
        <h2 class="section-title">
            <i class="fas fa-tags"></i> Ofertas do Evento
        </h2>
        <div class="filter-pills">
            <button class="filter-pill active" onclick="filterGames('all')">
                Todos
            </button>
            <button class="filter-pill" onclick="filterGames('50')">
                50%+ OFF
            </button>
            <button class="filter-pill" onclick="filterGames('75')">
                75%+ OFF
            </button>
            <button class="filter-pill" onclick="filterGames('price-low')">
                Menor Preço
            </button>
            <button class="filter-pill" onclick="filterGames('discount-high')">
                Maior Desconto
            </button>
        </div>
    </div>
    
    <div class="cards-grid" id="gamesGrid">
        <?php if (!empty($jogos_evento)): ?>
            <?php foreach ($jogos_evento as $jogo): ?>
                <?php renderGameCard($jogo, $pdo, $user_id); ?>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="grid-column: 1/-1; text-align: center; padding: 60px 20px; color: var(--text-secondary);">
                <i class="fas fa-gamepad" style="font-size: 64px; margin-bottom: 20px; opacity: 0.3;"></i>
                <h3 style="margin-bottom: 8px;">Nenhuma Oferta Disponível</h3>
                <p>Volte em breve para conferir as ofertas deste evento!</p>
            </div>
        <?php endif; ?>
    </div>
    
</div>

<script>
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
    
    document.getElementById('days').textContent = String(days).padStart(2, '0');
    document.getElementById('hours').textContent = String(hours).padStart(2, '0');
    document.getElementById('minutes').textContent = String(minutes).padStart(2, '0');
    document.getElementById('seconds').textContent = String(seconds).padStart(2, '0');
}

updateCountdown();
setInterval(updateCountdown, 1000);
<?php endif; ?>

// Filtros
let allGames = Array.from(document.querySelectorAll('#gamesGrid > div'));

function filterGames(filter) {
    // Atualizar pills
    document.querySelectorAll('.filter-pill').forEach(pill => {
        pill.classList.remove('active');
    });
    event.target.classList.add('active');
    
    const grid = document.getElementById('gamesGrid');
    
    // Filtrar
    let filtered = [...allGames];
    
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
        filtered.sort((a, b) => {
            const priceA = parseInt(a.dataset.price || 0);
            const priceB = parseInt(b.dataset.price || 0);
            return priceA - priceB;
        });
    } else if (filter === 'discount-high') {
        filtered.sort((a, b) => {
            const discA = parseInt(a.dataset.discount || 0);
            const discB = parseInt(b.dataset.discount || 0);
            return discB - discA;
        });
    }
    
    // Limpar e renderizar
    grid.innerHTML = '';
    
    if (filtered.length === 0) {
        grid.innerHTML = `
            <div style="grid-column: 1/-1; text-align: center; padding: 60px 20px; color: var(--text-secondary);">
                <i class="fas fa-filter" style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;"></i>
                <p>Nenhum jogo encontrado com esse filtro</p>
            </div>
        `;
    } else {
        filtered.forEach(card => grid.appendChild(card));
    }
}

// Adicionar data attributes aos cards para filtragem
document.addEventListener('DOMContentLoaded', function() {
    <?php foreach ($jogos_evento as $jogo): ?>
    {
        const card = Array.from(document.querySelectorAll('#gamesGrid > div')).find(div => 
            div.innerHTML.includes('<?= addslashes($jogo['titulo']) ?>')
        );
        if (card) {
            card.dataset.discount = '<?= $jogo['percentual_desconto'] ?>';
            card.dataset.price = '<?= $jogo['preco_promocional_centavos'] ?>';
        }
    }
    <?php endforeach; ?>
});
</script>

<?php require_once '../includes/footer.php'; ?>