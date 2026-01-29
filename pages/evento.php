<?php
// pages/evento.php - Sidebar Toggle + Slider Estrelas
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

// Banner
$stmt = $pdo->prepare("
    SELECT imagem_overlay FROM banner 
    WHERE ativo = 1 
    AND (data_inicio IS NULL OR data_inicio <= CURDATE())
    AND (data_fim IS NULL OR data_fim >= CURDATE())
    ORDER BY ordem ASC LIMIT 1
");
$stmt->execute();
$banner = $stmt->fetch();
$imagem_overlay = $banner['imagem_overlay'] ?? null;

// Status
$agora = date('Y-m-d H:i:s');
$evento_ativo = ($agora >= $evento['data_inicio'] && $agora <= $evento['data_fim']);
$evento_futuro = ($agora < $evento['data_inicio']);
$evento_encerrado = ($agora > $evento['data_fim']);

// User data
$meus_jogos = $minha_wishlist = $meu_carrinho = [];
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

// Filtros
$categoria = $_GET['categoria'] ?? '';
$plataforma = $_GET['plataforma'] ?? '';
$preco_min = isset($_GET['preco_min']) && $_GET['preco_min'] !== '' ? (float)$_GET['preco_min'] : null;
$preco_max = isset($_GET['preco_max']) && $_GET['preco_max'] !== '' ? (float)$_GET['preco_max'] : null;
$nota_min = isset($_GET['nota_min']) ? (float)$_GET['nota_min'] : 0;
$ordem = $_GET['ordem'] ?? 'desconto';
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$por_pagina = 24;
$offset = ($pagina - 1) * $por_pagina;

// Query
$where = ["j.status = 'publicado'", "j.em_promocao = 1", "j.preco_promocional_centavos IS NOT NULL"];
$params = [];
$joins = [];

if (!empty($categoria)) {
    $joins[] = "INNER JOIN jogo_categoria jc ON j.id = jc.jogo_id INNER JOIN categoria cat ON jc.categoria_id = cat.id AND cat.slug = ?";
    $params[] = $categoria;
}
if (!empty($plataforma)) {
    $joins[] = "INNER JOIN jogo_plataforma jp ON j.id = jp.jogo_id INNER JOIN plataforma plat ON jp.plataforma_id = plat.id AND plat.slug = ?";
    $params[] = $plataforma;
}
if ($preco_min !== null) {
    $where[] = "j.preco_promocional_centavos >= ?";
    $params[] = $preco_min * 100;
}
if ($preco_max !== null) {
    $where[] = "j.preco_promocional_centavos <= ?";
    $params[] = $preco_max * 100;
}
if ($nota_min > 0) {
    $where[] = "j.nota_media >= ?";
    $params[] = $nota_min;
}

$where_clause = implode(' AND ', $where);
$joins_clause = implode(' ', $joins);

$order_by = match($ordem) {
    'preco_asc' => 'j.preco_promocional_centavos ASC',
    'preco_desc' => 'j.preco_promocional_centavos DESC',
    'nota' => 'j.nota_media DESC',
    'titulo' => 'j.titulo ASC',
    default => '(j.preco_centavos - j.preco_promocional_centavos) DESC'
};

$sql = "
    SELECT DISTINCT j.*, d.nome_estudio,
           ROUND(((j.preco_centavos - j.preco_promocional_centavos) / j.preco_centavos) * 100) as percentual_desconto
    FROM jogo j
    LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id
    $joins_clause
    WHERE $where_clause
    ORDER BY $order_by
    LIMIT $por_pagina OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$jogos_evento = $stmt->fetchAll();

$count_sql = "SELECT COUNT(DISTINCT j.id) as total FROM jogo j $joins_clause WHERE $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_jogos = $stmt->fetch()['total'];
$total_paginas = ceil($total_jogos / $por_pagina);

// Stats
$stats = $pdo->query("
    SELECT MAX(ROUND(((preco_centavos - preco_promocional_centavos) / preco_centavos) * 100)) as maior_desconto
    FROM jogo WHERE status = 'publicado' AND em_promocao = 1
")->fetch();
$maior_desconto = $stats['maior_desconto'] ?? 0;

// Dados filtros
$categorias = $pdo->query("
    SELECT c.*, COUNT(DISTINCT jc.jogo_id) as total_jogos
    FROM categoria c
    INNER JOIN jogo_categoria jc ON c.id = jc.categoria_id
    INNER JOIN jogo j ON jc.jogo_id = j.id AND j.status = 'publicado' AND j.em_promocao = 1
    WHERE c.ativa = 1 GROUP BY c.id HAVING total_jogos > 0 ORDER BY c.nome
")->fetchAll();

$plataformas = $pdo->query("
    SELECT p.*, COUNT(DISTINCT jp.jogo_id) as total_jogos
    FROM plataforma p
    INNER JOIN jogo_plataforma jp ON p.id = jp.plataforma_id
    INNER JOIN jogo j ON jp.jogo_id = j.id AND j.status = 'publicado' AND j.em_promocao = 1
    WHERE p.ativa = 1 GROUP BY p.id HAVING total_jogos > 0 ORDER BY p.ordem
")->fetchAll();

// Filtros ativos
$filtros_ativos = [];
if (!empty($categoria)) {
    $cat_nome = array_filter($categorias, fn($c) => $c['slug'] === $categoria);
    $filtros_ativos[] = ['tipo' => 'categoria', 'label' => reset($cat_nome)['nome'] ?? $categoria];
}
if (!empty($plataforma)) {
    $plat_nome = array_filter($plataformas, fn($p) => $p['slug'] === $plataforma);
    $filtros_ativos[] = ['tipo' => 'plataforma', 'label' => reset($plat_nome)['nome'] ?? $plataforma];
}
if ($preco_min !== null) $filtros_ativos[] = ['tipo' => 'preco_min', 'label' => 'Min R$' . number_format($preco_min, 0)];
if ($preco_max !== null) $filtros_ativos[] = ['tipo' => 'preco_max', 'label' => 'Max R$' . number_format($preco_max, 0)];
if ($nota_min > 0) $filtros_ativos[] = ['tipo' => 'nota_min', 'label' => $nota_min . '+ ★'];

$page_title = htmlspecialchars($evento['nome']) . ' - ' . SITE_NAME;
require_once '../includes/header.php';
require_once '../components/game-card.php';
?>

<style>
:root {
    --sidebar-width: 280px;
}

.evento-page {
    min-height: 100vh;
    background: var(--bg-primary);
}

/* ============================================
   HERO COMPACTO
   ============================================ */
.evento-hero {
    background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-primary) 100%);
    border-bottom: 1px solid var(--border);
}

.hero-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 32px 24px;
    display: flex;
    align-items: center;
    gap: 32px;
}

.hero-image-wrapper {
    width: 120px;
    height: 120px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.hero-image {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    filter: drop-shadow(0 8px 24px rgba(0,0,0,0.4));
    animation: float 6s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-6px); }
}

.hero-content {
    flex: 1;
}

.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #22c55e;
    margin-bottom: 8px;
}

.hero-badge::before {
    content: '';
    width: 6px;
    height: 6px;
    background: #22c55e;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.4; }
}

.hero-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 6px;
}

.hero-subtitle {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin: 0;
}

.hero-stats {
    display: flex;
    align-items: center;
    gap: 32px;
    margin-left: auto;
}

.hero-stat {
    text-align: center;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--accent);
}

.stat-label {
    font-size: 11px;
    color: var(--text-secondary);
    text-transform: uppercase;
}

.hero-countdown {
    text-align: right;
    padding-left: 32px;
    border-left: 1px solid var(--border);
}

.countdown-label {
    font-size: 10px;
    color: var(--text-secondary);
    text-transform: uppercase;
    margin-bottom: 4px;
}

.countdown-timer {
    display: flex;
    gap: 12px;
}

.countdown-block {
    text-align: center;
}

.countdown-num {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    font-variant-numeric: tabular-nums;
}

.countdown-unit {
    font-size: 9px;
    color: var(--text-secondary);
    text-transform: uppercase;
}

/* ============================================
   TOOLBAR
   ============================================ */
.evento-toolbar {
    background: var(--bg-secondary);
    border-bottom: 1px solid var(--border);
    position: sticky;
    top: 60px;
    z-index: 90;
}

.toolbar-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 12px 24px;
    display: flex;
    align-items: center;
    gap: 16px;
}

.btn-filter-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-primary);
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-filter-toggle:hover {
    border-color: var(--accent);
    color: var(--accent);
}

.btn-filter-toggle.active {
    background: var(--accent);
    border-color: var(--accent);
    color: #fff;
}

.btn-filter-toggle .filter-count-badge {
    background: var(--accent);
    color: #fff;
    font-size: 11px;
    padding: 2px 6px;
    border-radius: 10px;
    font-weight: 600;
}

.btn-filter-toggle.active .filter-count-badge {
    background: #fff;
    color: var(--accent);
}

/* Estilo base do Checkbox */
.filter-checkbox input {
    appearance: none; /* Esconde o padrão do navegador */
    -webkit-appearance: none;
    width: 18px;
    height: 18px;
    background: rgba(255, 255, 255, 0.05);
    border: 2px solid rgba(255, 255, 255, 0.2);
    border-radius: 4px;
    cursor: pointer;
    position: relative;
    transition: all 0.2s ease;
    margin-right: 10px; /* Espaço entre o quadrado e o texto */
    display: inline-block;
    vertical-align: middle;
}

/* Quando o mouse passa por cima (Hover) */
.filter-checkbox:hover input {
    border-color: rgba(255, 255, 255, 0.5);
}

/* =========================================
   ESTADO SELECIONADO (O FIX)
   ========================================= */
.filter-checkbox input:checked {
    background-color: #66c0f4; /* Azul Steam */
    border-color: #66c0f4;
}

/* Desenha o "V" (check) usando pseudo-elemento */
.filter-checkbox input:checked::after {
    content: '';
    position: absolute;
    top: 1px;
    left: 5px;
    width: 4px;
    height: 9px;
    border: solid #000; /* Cor do Vzinho */
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}

/* Muda a cor do texto do label quando selecionado */
.filter-checkbox input:checked + span {
    color: #fff;
    font-weight: 500;
}
/* Tags de filtros ativos */
.active-filters {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
    overflow-x: auto;
}

.filter-tag {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: rgba(0, 174, 255, 0.1);
    border: 1px solid rgba(0, 174, 255, 0.3);
    border-radius: 20px;
    font-size: 12px;
    color: var(--accent);
    white-space: nowrap;
}

.filter-tag-remove {
    width: 16px;
    height: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 174, 255, 0.2);
    border-radius: 50%;
    cursor: pointer;
    transition: background 0.2s;
}

.filter-tag-remove:hover {
    background: var(--accent);
    color: #fff;
}

.toolbar-results {
    font-size: 13px;
    color: var(--text-secondary);
    white-space: nowrap;
}

.toolbar-sort {
    display: flex;
    align-items: center;
    gap: 8px;
}

.toolbar-sort label {
    font-size: 12px;
    color: var(--text-secondary);
}

.toolbar-sort select {
    padding: 8px 12px;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text-primary);
    font-size: 13px;
    cursor: pointer;
}

/* ============================================
   MAIN LAYOUT
   ============================================ */
.evento-main {
    max-width: 1400px;
    margin: 0 auto;
    padding: 24px;
    display: flex;
    gap: 24px;
    position: relative;
}

/* ============================================
   SIDEBAR DE FILTROS
   ============================================ */
.filters-sidebar {
    width: var(--sidebar-width);
    flex-shrink: 0;
    position: sticky;
    top: 130px;
    max-height: calc(100vh - 150px);
    overflow-y: auto;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    transform: translateX(calc(-1 * var(--sidebar-width) - 24px));
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.filters-sidebar.open {
    transform: translateX(0);
    opacity: 1;
    visibility: visible;
}

.filters-sidebar::-webkit-scrollbar {
    width: 4px;
}

.filters-sidebar::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 2px;
}

.filters-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--border);
}

.filters-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 8px;
}

.filters-title i {
    color: var(--accent);
}

.btn-clear-all {
    font-size: 12px;
    color: var(--danger);
    background: none;
    border: none;
    cursor: pointer;
    transition: opacity 0.2s;
}

.btn-clear-all:hover {
    opacity: 0.7;
}

/* Filter Groups */
.filter-group {
    margin-bottom: 24px;
}

.filter-group:last-child {
    margin-bottom: 0;
}

.filter-group-title {
    font-size: 11px;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 12px;
}

.filter-options {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.filter-option {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.15s;
}

.filter-option:hover {
    background: rgba(255,255,255,0.03);
}

.filter-option.active {
    background: rgba(0, 174, 255, 0.1);
}

.filter-option input {
    display: none;
}

.filter-radio {
    width: 16px;
    height: 16px;
    border: 2px solid var(--border);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all 0.15s;
}

.filter-option.active .filter-radio {
    border-color: var(--accent);
    background: var(--accent);
}

.filter-option.active .filter-radio::after {
    content: '';
    width: 6px;
    height: 6px;
    background: #fff;
    border-radius: 50%;
}

.filter-name {
    flex: 1;
    font-size: 13px;
    color: var(--text-primary);
}

.filter-option.active .filter-name {
    color: var(--accent);
    font-weight: 500;
}

.filter-count {
    font-size: 11px;
    color: var(--text-secondary);
}

/* Price Range */
.price-range {
    display: flex;
    gap: 12px;
}

.price-input-group {
    flex: 1;
}

.price-input-group label {
    display: block;
    font-size: 11px;
    color: var(--text-secondary);
    margin-bottom: 6px;
}

.price-input {
    width: 100%;
    padding: 10px 12px;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-primary);
    font-size: 13px;
}

.price-input:focus {
    outline: none;
    border-color: var(--accent);
}

/* Rating Slider */
.rating-slider-container {
    padding: 8px 0;
}

.rating-slider-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.rating-value {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    font-weight: 600;
    color: var(--accent);
}

.rating-value i {
    color: #fbbf24;
}

.rating-slider {
    width: 100%;
    height: 6px;
    -webkit-appearance: none;
    background: var(--bg-primary);
    border-radius: 3px;
    outline: none;
}

.rating-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 18px;
    height: 18px;
    background: var(--accent);
    border-radius: 50%;
    cursor: pointer;
    transition: transform 0.15s;
}

.rating-slider::-webkit-slider-thumb:hover {
    transform: scale(1.1);
}

.rating-labels {
    display: flex;
    justify-content: space-between;
    margin-top: 8px;
}

.rating-labels span {
    font-size: 10px;
    color: var(--text-secondary);
}

/* Apply Button */
.btn-apply-filters {
    width: 100%;
    padding: 12px;
    background: var(--accent);
    border: none;
    border-radius: 8px;
    color: #fff;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    margin-top: 20px;
    transition: opacity 0.2s;
}

.btn-apply-filters:hover {
    opacity: 0.9;
}

/* ============================================
   CONTEÚDO PRINCIPAL
   ============================================ */
.content-area {
    flex: 1;
    min-width: 0;
    transition: margin-left 0.3s ease;
}

.content-area.shifted {
    margin-left: 0;
}

/* Games Grid */
.games-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 20px;
}

/* Empty State */
.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 80px 20px;
}

.empty-icon {
    font-size: 48px;
    color: var(--text-secondary);
    margin-bottom: 16px;
    opacity: 0.5;
}

.empty-title {
    font-size: 1.1rem;
    color: var(--text-primary);
    margin-bottom: 8px;
}

.empty-text {
    font-size: 14px;
    color: var(--text-secondary);
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 40px;
    padding-top: 24px;
    border-top: 1px solid var(--border);
}

.pagination a,
.pagination span {
    padding: 10px 16px;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-secondary);
    font-size: 13px;
    text-decoration: none;
    transition: all 0.2s;
}

.pagination a:hover {
    border-color: var(--accent);
    color: var(--accent);
}

.pagination .current {
    background: var(--accent);
    border-color: var(--accent);
    color: #fff;
}

/* ============================================
   OVERLAY MOBILE
   ============================================ */
.sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 99;
    opacity: 0;
    transition: opacity 0.3s;
}

.sidebar-overlay.active {
    opacity: 1;
}

/* ============================================
   RESPONSIVE
   ============================================ */
@media (max-width: 1024px) {
    .hero-container {
        flex-wrap: wrap;
    }
    
    .hero-stats {
        width: 100%;
        margin-left: 0;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid var(--border);
    }
    
    .hero-countdown {
        border-left: none;
        padding-left: 0;
    }
}

@media (max-width: 768px) {
    .filters-sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        max-height: 100vh;
        border-radius: 0;
        z-index: 100;
        transform: translateX(-100%);
    }
    
    .filters-sidebar.open {
        transform: translateX(0);
    }
    
    .sidebar-overlay {
        display: block;
    }
    
    .hero-image-wrapper {
        display: none;
    }
    
    .games-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
}
</style>

<!-- HERO -->
<div class="evento-page">
    <section class="evento-hero">
        <div class="hero-container">
            <?php if ($imagem_overlay): ?>
            <div class="hero-image-wrapper">
                <img src="<?= $imagem_overlay ?>" alt="" class="hero-image">
            </div>
            <?php endif; ?>
            
            <div class="hero-content">
                <?php if ($evento_ativo): ?>
                    <div class="hero-badge">Evento em andamento</div>
                <?php endif; ?>
                <h1 class="hero-title"><?= htmlspecialchars($evento['nome']) ?></h1>
                <p class="hero-subtitle"><?= htmlspecialchars($evento['descricao'] ?? 'Ofertas imperdíveis por tempo limitado') ?></p>
            </div>
            
            <div class="hero-stats">
                <div class="hero-stat">
                    <div class="stat-value"><?= $total_jogos ?></div>
                    <div class="stat-label">Jogos</div>
                </div>
                <div class="hero-stat">
                    <div class="stat-value">-<?= $maior_desconto ?>%</div>
                    <div class="stat-label">Maior desconto</div>
                </div>
            </div>
            
            <?php if ($evento_ativo): ?>
            <div class="hero-countdown">
                <div class="countdown-label">Termina em</div>
                <div class="countdown-timer" id="countdown" data-end="<?= $evento['data_fim'] ?>">
                    <div class="countdown-block">
                        <div class="countdown-num" id="days">--</div>
                        <div class="countdown-unit">Dias</div>
                    </div>
                    <div class="countdown-block">
                        <div class="countdown-num" id="hours">--</div>
                        <div class="countdown-unit">Hrs</div>
                    </div>
                    <div class="countdown-block">
                        <div class="countdown-num" id="mins">--</div>
                        <div class="countdown-unit">Min</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>
    
    <!-- TOOLBAR -->
    <div class="evento-toolbar">
        <div class="toolbar-container">
            <button class="btn-filter-toggle active" id="btnFilterToggle">
                <i class="fas fa-sliders-h"></i>
                <span>Filtros</span>
                <?php if (count($filtros_ativos) > 0): ?>
                    <span class="filter-count-badge"><?= count($filtros_ativos) ?></span>
                <?php endif; ?>
            </button>
            
            <div class="active-filters">
                <?php foreach ($filtros_ativos as $filtro): ?>
                    <div class="filter-tag">
                        <span><?= $filtro['label'] ?></span>
                        <a href="<?= removeFilterParam($filtro['tipo']) ?>" class="filter-tag-remove">
                            <i class="fas fa-times" style="font-size: 10px;"></i>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="toolbar-results">
                <strong><?= $total_jogos ?></strong> jogos
            </div>
            
            <div class="toolbar-sort">
                <label>Ordenar:</label>
                <select id="sortSelect" onchange="applySort(this.value)">
                    <option value="desconto" <?= $ordem === 'desconto' ? 'selected' : '' ?>>Maior desconto</option>
                    <option value="preco_asc" <?= $ordem === 'preco_asc' ? 'selected' : '' ?>>Menor preço</option>
                    <option value="preco_desc" <?= $ordem === 'preco_desc' ? 'selected' : '' ?>>Maior preço</option>
                    <option value="nota" <?= $ordem === 'nota' ? 'selected' : '' ?>>Melhor avaliados</option>
                    <option value="titulo" <?= $ordem === 'titulo' ? 'selected' : '' ?>>A-Z</option>
                </select>
            </div>
        </div>
    </div>
    
    <!-- MAIN -->
    <main class="evento-main">
        <!-- Overlay -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        
        <!-- SIDEBAR FILTROS -->
        <aside class="filters-sidebar open" id="filtersSidebar">
            <div class="filters-header">
                <div class="filters-title">
                    <i class="fas fa-filter"></i>
                    Filtros
                </div>
                <?php if (count($filtros_ativos) > 0): ?>
                    <a href="?slug=<?= $slug ?>" class="btn-clear-all">Limpar tudo</a>
                <?php endif; ?>
            </div>
            
            <form id="filterForm" method="GET">
                <input type="hidden" name="slug" value="<?= $slug ?>">
                <input type="hidden" name="ordem" value="<?= $ordem ?>">
                
                <!-- Categorias -->
                <div class="filter-group">
                    <div class="filter-group-title">Categoria</div>
                    <div class="filter-options">
                        <label class="filter-option <?= empty($categoria) ? 'active' : '' ?>">
                            <input type="radio" name="categoria" value="" <?= empty($categoria) ? 'checked' : '' ?>>
                            <span class="filter-radio"></span>
                            <span class="filter-name">Todas</span>
                        </label>
                        <?php foreach ($categorias as $cat): ?>
                        <label class="filter-option <?= $categoria === $cat['slug'] ? 'active' : '' ?>">
                            <input type="radio" name="categoria" value="<?= $cat['slug'] ?>" <?= $categoria === $cat['slug'] ? 'checked' : '' ?>>
                            <span class="filter-radio"></span>
                            <span class="filter-name"><?= htmlspecialchars($cat['nome']) ?></span>
                            <span class="filter-count"><?= $cat['total_jogos'] ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Plataformas -->
                <div class="filter-group">
                    <div class="filter-group-title">Plataforma</div>
                    <div class="filter-options">
                        <label class="filter-option <?= empty($plataforma) ? 'active' : '' ?>">
                            <input type="radio" name="plataforma" value="" <?= empty($plataforma) ? 'checked' : '' ?>>
                            <span class="filter-radio"></span>
                            <span class="filter-name">Todas</span>
                        </label>
                        <?php foreach ($plataformas as $plat): ?>
                        <label class="filter-option <?= $plataforma === $plat['slug'] ? 'active' : '' ?>">
                            <input type="radio" name="plataforma" value="<?= $plat['slug'] ?>" <?= $plataforma === $plat['slug'] ? 'checked' : '' ?>>
                            <span class="filter-radio"></span>
                            <span class="filter-name"><?= htmlspecialchars($plat['nome']) ?></span>
                            <span class="filter-count"><?= $plat['total_jogos'] ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Preço -->
                <div class="filter-group">
                    <div class="filter-group-title">Faixa de Preço</div>
                    <div class="price-range">
                        <div class="price-input-group">
                            <label>Mínimo</label>
                            <input type="number" name="preco_min" class="price-input" placeholder="R$ 0" value="<?= $preco_min ?? '' ?>" min="0" step="1">
                        </div>
                        <div class="price-input-group">
                            <label>Máximo</label>
                            <input type="number" name="preco_max" class="price-input" placeholder="R$ 500" value="<?= $preco_max ?? '' ?>" min="0" step="1">
                        </div>
                    </div>
                </div>
                
                <!-- Rating Slider -->
                <div class="filter-group">
                    <div class="filter-group-title">Avaliação Mínima</div>
                    <div class="rating-slider-container">
                        <div class="rating-slider-header">
                            <span class="rating-value">
                                <i class="fas fa-star"></i>
                                <span id="ratingDisplay"><?= $nota_min ?: 'Qualquer' ?></span>
                            </span>
                        </div>
                        <input type="range" class="rating-slider" name="nota_min" id="ratingSlider" min="0" max="5" step="0.5" value="<?= $nota_min ?>">
                        <div class="rating-labels">
                            <span>Qualquer</span>
                            <span>5.0</span>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn-apply-filters">
                    <i class="fas fa-check"></i> Aplicar Filtros
                </button>
            </form>
        </aside>
        
        <!-- CONTEÚDO -->
        <div class="content-area" id="contentArea">
            <div class="games-grid">
                <?php if (empty($jogos_evento)): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-search"></i></div>
                        <h3 class="empty-title">Nenhum jogo encontrado</h3>
                        <p class="empty-text">Tente ajustar os filtros para ver mais resultados</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($jogos_evento as $jogo): ?>
                        <?php
                        $possui = in_array($jogo['id'], $meus_jogos);
                        $na_wishlist = in_array($jogo['id'], $minha_wishlist);
                        $no_carrinho = in_array($jogo['id'], $meu_carrinho);
                            renderGameCard($jogo, $possui, $na_wishlist, $no_carrinho);
                        ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <?php if ($total_paginas > 1): ?>
            <div class="pagination">
                <?php if ($pagina > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])) ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>
                
                <?php
                $inicio = max(1, $pagina - 2);
                $fim = min($total_paginas, $pagina + 2);
                
                for ($i = $inicio; $i <= $fim; $i++):
                ?>
                    <?php if ($i === $pagina): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => $i])) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($pagina < $total_paginas): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])) ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
// Toggle Sidebar
const btnToggle = document.getElementById('btnFilterToggle');
const sidebar = document.getElementById('filtersSidebar');
const overlay = document.getElementById('sidebarOverlay');

btnToggle.addEventListener('click', () => {
    sidebar.classList.toggle('open');
    btnToggle.classList.toggle('active');
    overlay.classList.toggle('active');
});

overlay.addEventListener('click', () => {
    sidebar.classList.remove('open');
    btnToggle.classList.remove('active');
    overlay.classList.remove('active');
});

// ✅ Feedback visual imediato ao clicar nos filtros
document.querySelectorAll('.filter-option input[type="radio"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const group = this.closest('.filter-options');
        group.querySelectorAll('.filter-option').forEach(opt => {
            opt.classList.remove('active');
        });
        this.closest('.filter-option').classList.add('active');
    });
});

// Rating Slider
const ratingSlider = document.getElementById('ratingSlider');
const ratingDisplay = document.getElementById('ratingDisplay');

ratingSlider.addEventListener('input', function() {
    const val = parseFloat(this.value);
    ratingDisplay.textContent = val === 0 ? 'Qualquer' : val.toFixed(1);
});

// Sort
function applySort(value) {
    const url = new URL(window.location);
    url.searchParams.set('ordem', value);
    url.searchParams.delete('pagina');
    window.location = url;
}

// Countdown
const countdownEl = document.getElementById('countdown');
if (countdownEl) {
    const endDate = new Date(countdownEl.dataset.end).getTime();
    
    function updateCountdown() {
        const now = new Date().getTime();
        const diff = endDate - now;
        
        if (diff > 0) {
            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const mins = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            
            document.getElementById('days').textContent = String(days).padStart(2, '0');
            document.getElementById('hours').textContent = String(hours).padStart(2, '0');
            document.getElementById('mins').textContent = String(mins).padStart(2, '0');
        }
    }
    
    updateCountdown();
    setInterval(updateCountdown, 60000);
}
</script>

<?php
// Helper para remover filtro
function removeFilterParam($tipo) {
    $params = $_GET;
    unset($params[$tipo]);
    unset($params['pagina']);
    return '?' . http_build_query($params);
}

require_once '../includes/footer.php';
?>