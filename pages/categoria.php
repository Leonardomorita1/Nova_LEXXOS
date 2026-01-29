<?php
// ===========================================
// pages/categoria.php - FILTROS CORRIGIDOS
// ===========================================

require_once '../config/config.php';
require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();
$user_id = isLoggedIn() ? $_SESSION['user_id'] : null;

$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    header('Location: ' . SITE_URL . '/pages/busca.php');
    exit;
}

// Buscar categoria
$stmt = $pdo->prepare("SELECT * FROM categoria WHERE slug = ? AND ativa = 1");
$stmt->execute([$slug]);
$categoria = $stmt->fetch();

if (!$categoria) {
    header('Location: ' . SITE_URL . '/pages/busca.php');
    exit;
}

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
$plataforma = $_GET['plataforma'] ?? '';
$preco_min = isset($_GET['preco_min']) && $_GET['preco_min'] !== '' ? (float)$_GET['preco_min'] : null;
$preco_max = isset($_GET['preco_max']) && $_GET['preco_max'] !== '' ? (float)$_GET['preco_max'] : null;
$nota_min = isset($_GET['nota_min']) ? (float)$_GET['nota_min'] : 0;
$promocao = isset($_GET['promocao']) ? (int)$_GET['promocao'] : 0;
$ordem = $_GET['ordem'] ?? 'popular';
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$por_pagina = 24;
$offset = ($pagina - 1) * $por_pagina;

// ============================================
// CONSTRUÇÃO DA QUERY CORRIGIDA
// ============================================

// Arrays para construir a query
$joins = ["INNER JOIN jogo_categoria jc ON j.id = jc.jogo_id"];
$where = ["j.status = 'publicado'", "jc.categoria_id = :categoria_id"];
$params = [':categoria_id' => $categoria['id']];

// Filtro de plataforma
if (!empty($plataforma)) {
    $joins[] = "INNER JOIN jogo_plataforma jp ON j.id = jp.jogo_id";
    $joins[] = "INNER JOIN plataforma plat ON jp.plataforma_id = plat.id";
    $where[] = "plat.slug = :plataforma";
    $params[':plataforma'] = $plataforma;
}

// Filtro de preço mínimo
if ($preco_min !== null) {
    $where[] = "COALESCE(CASE WHEN j.em_promocao = 1 THEN j.preco_promocional_centavos END, j.preco_centavos) >= :preco_min";
    $params[':preco_min'] = $preco_min * 100;
}

// Filtro de preço máximo
if ($preco_max !== null) {
    $where[] = "COALESCE(CASE WHEN j.em_promocao = 1 THEN j.preco_promocional_centavos END, j.preco_centavos) <= :preco_max";
    $params[':preco_max'] = $preco_max * 100;
}

// Filtro de nota mínima
if ($nota_min > 0) {
    $where[] = "j.nota_media >= :nota_min";
    $params[':nota_min'] = $nota_min;
}

// Filtro de promoção
if ($promocao === 1) {
    $where[] = "j.em_promocao = 1 AND j.preco_promocional_centavos IS NOT NULL";
}

// Montar cláusulas
$joins_clause = implode(' ', $joins);
$where_clause = implode(' AND ', $where);

// Ordenação
$order_by = match($ordem) {
    'preco_asc' => 'preco_efetivo ASC',
    'preco_desc' => 'preco_efetivo DESC',
    'nota' => 'j.nota_media DESC, j.titulo ASC',
    'novos' => 'COALESCE(j.publicado_em, j.criado_em) DESC, j.id DESC',
    'titulo' => 'j.titulo ASC',
    default => 'j.total_vendas DESC, j.nota_media DESC'
};

// Query principal
$sql = "
    SELECT DISTINCT j.*, d.nome_estudio,
           COALESCE(CASE WHEN j.em_promocao = 1 THEN j.preco_promocional_centavos END, j.preco_centavos) as preco_efetivo
    FROM jogo j
    LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id
    $joins_clause
    WHERE $where_clause
    ORDER BY $order_by
    LIMIT $por_pagina OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$jogos = $stmt->fetchAll();

// Query de contagem
$count_sql = "
    SELECT COUNT(DISTINCT j.id) as total
    FROM jogo j
    $joins_clause
    WHERE $where_clause
";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_jogos = $stmt->fetch()['total'];
$total_paginas = ceil($total_jogos / $por_pagina);

// ============================================
// DADOS PARA OS FILTROS (sidebar)
// ============================================

// Plataformas disponíveis na categoria
$stmt = $pdo->prepare("
    SELECT p.*, COUNT(DISTINCT j.id) as total_jogos
    FROM plataforma p
    INNER JOIN jogo_plataforma jp ON p.id = jp.plataforma_id
    INNER JOIN jogo j ON jp.jogo_id = j.id AND j.status = 'publicado'
    INNER JOIN jogo_categoria jc ON j.id = jc.jogo_id AND jc.categoria_id = ?
    WHERE p.ativa = 1 
    GROUP BY p.id 
    HAVING total_jogos > 0 
    ORDER BY p.ordem
");
$stmt->execute([$categoria['id']]);
$plataformas = $stmt->fetchAll();

// Contagem de promoções na categoria
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT j.id) as total
    FROM jogo j
    INNER JOIN jogo_categoria jc ON j.id = jc.jogo_id AND jc.categoria_id = ?
    WHERE j.status = 'publicado' AND j.em_promocao = 1 AND j.preco_promocional_centavos IS NOT NULL
");
$stmt->execute([$categoria['id']]);
$total_promocoes = $stmt->fetchColumn();

// ============================================
// FILTROS ATIVOS (tags na toolbar)
// ============================================
$filtros_ativos = [];

if (!empty($plataforma)) {
    $plat_nome = array_filter($plataformas, fn($p) => $p['slug'] === $plataforma);
    $plat_nome = reset($plat_nome);
    $filtros_ativos[] = ['tipo' => 'plataforma', 'label' => $plat_nome['nome'] ?? $plataforma];
}
if ($preco_min !== null) {
    $filtros_ativos[] = ['tipo' => 'preco_min', 'label' => 'Min R$' . number_format($preco_min, 0, ',', '.')];
}
if ($preco_max !== null) {
    $filtros_ativos[] = ['tipo' => 'preco_max', 'label' => 'Max R$' . number_format($preco_max, 0, ',', '.')];
}
if ($nota_min > 0) {
    $filtros_ativos[] = ['tipo' => 'nota_min', 'label' => $nota_min . '+ ★'];
}
if ($promocao === 1) {
    $filtros_ativos[] = ['tipo' => 'promocao', 'label' => 'Em promoção'];
}

// Helper para remover filtro da URL
function removeFilterParam($tipo) {
    $params = $_GET;
    unset($params[$tipo]);
    unset($params['pagina']);
    return '?' . http_build_query($params);
}

$page_title = htmlspecialchars($categoria['nome']) . ' - ' . SITE_NAME;
require_once '../includes/header.php';
require_once '../components/game-card.php';
?>

<style>
:root {
    --sidebar-width: 280px;
}

.categoria-page {
    min-height: 100vh;
    background: var(--bg-primary);
}

/* ============================================
   HERO COMPACTO
   ============================================ */
.categoria-hero {
    background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-primary) 100%);
    border-bottom: 1px solid var(--border);
}

.hero-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 32px 24px;
    display: flex;
    align-items: center;
    gap: 24px;
}

.hero-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, var(--accent), #22c55e);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: #000;
    flex-shrink: 0;
}

.hero-content {
    flex: 1;
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
    max-width: 600px;
}

.hero-stats {
    display: flex;
    align-items: center;
    gap: 32px;
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

/* ============================================
   TOOLBAR
   ============================================ */
.categoria-toolbar {
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
    color: #000;
}

.btn-filter-toggle .filter-count-badge {
    background: var(--accent);
    color: #000;
    font-size: 11px;
    padding: 2px 6px;
    border-radius: 10px;
    font-weight: 600;
}

.btn-filter-toggle.active .filter-count-badge {
    background: #000;
    color: var(--accent);
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
.categoria-main {
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
    color: #000;
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
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid var(--border);
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
    
    .hero-icon {
        width: 60px;
        height: 60px;
        font-size: 1.5rem;
    }
    
    .hero-title {
        font-size: 1.4rem;
    }
    
    .games-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    
    .toolbar-sort label {
        display: none;
    }
}
</style>

<div class="categoria-page">
    <!-- HERO -->
    <section class="categoria-hero">
        <div class="hero-container">
            <div class="hero-icon">
                <i class="fas fa-<?= htmlspecialchars($categoria['icone'] ?? 'gamepad') ?>"></i>
            </div>
            
            <div class="hero-content">
                <h1 class="hero-title"><?= htmlspecialchars($categoria['nome']) ?></h1>
                <p class="hero-subtitle">
                    Explore os melhores jogos <?= htmlspecialchars(strtolower($categoria['nome'])) ?> indies do Brasil. 
                    Descubra títulos únicos e apoie desenvolvedores brasileiros.
                </p>
            </div>
            
            <div class="hero-stats">
                <div class="hero-stat">
                    <div class="stat-value"><?= $total_jogos ?></div>
                    <div class="stat-label">Jogos</div>
                </div>
                <?php if ($total_promocoes > 0): ?>
                <div class="hero-stat">
                    <div class="stat-value"><?= $total_promocoes ?></div>
                    <div class="stat-label">Em promoção</div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
    
    <!-- TOOLBAR -->
    <div class="categoria-toolbar">
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
                        <span><?= htmlspecialchars($filtro['label']) ?></span>
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
                    <option value="popular" <?= $ordem === 'popular' ? 'selected' : '' ?>>Mais populares</option>
                    <option value="novos" <?= $ordem === 'novos' ? 'selected' : '' ?>>Lançamentos</option>
                    <option value="nota" <?= $ordem === 'nota' ? 'selected' : '' ?>>Melhor avaliados</option>
                    <option value="preco_asc" <?= $ordem === 'preco_asc' ? 'selected' : '' ?>>Menor preço</option>
                    <option value="preco_desc" <?= $ordem === 'preco_desc' ? 'selected' : '' ?>>Maior preço</option>
                    <option value="titulo" <?= $ordem === 'titulo' ? 'selected' : '' ?>>A-Z</option>
                </select>
            </div>
        </div>
    </div>
    
    <!-- MAIN -->
    <main class="categoria-main">
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
                <input type="hidden" name="slug" value="<?= htmlspecialchars($slug) ?>">
                <input type="hidden" name="ordem" value="<?= htmlspecialchars($ordem) ?>">
                
                <!-- Plataformas -->
                <?php if (!empty($plataformas)): ?>
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
                            <input type="radio" name="plataforma" value="<?= htmlspecialchars($plat['slug']) ?>" <?= $plataforma === $plat['slug'] ? 'checked' : '' ?>>
                            <span class="filter-radio"></span>
                            <span class="filter-name"><?= htmlspecialchars($plat['nome']) ?></span>
                            <span class="filter-count"><?= $plat['total_jogos'] ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Promoção -->
                <div class="filter-group">
                    <div class="filter-group-title">Ofertas</div>
                    <label class="filter-checkbox">
                        <input type="checkbox" name="promocao" value="1" <?= $promocao === 1 ? 'checked' : '' ?>>
                        <span class="filter-name">Apenas em promoção</span>
                    </label>
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
                <?php if (empty($jogos)): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-search"></i></div>
                        <h3 class="empty-title">Nenhum jogo encontrado</h3>
                        <p class="empty-text">Tente ajustar os filtros para ver mais resultados</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($jogos as $jogo): ?>
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
                
                if ($inicio > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => 1])) ?>">1</a>
                    <?php if ($inicio > 2): ?><span>...</span><?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $inicio; $i <= $fim; $i++): ?>
                    <?php if ($i === $pagina): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => $i])) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($fim < $total_paginas): ?>
                    <?php if ($fim < $total_paginas - 1): ?><span>...</span><?php endif; ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => $total_paginas])) ?>"><?= $total_paginas ?></a>
                <?php endif; ?>
                
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

// Feedback visual imediato nos radio buttons
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
</script>

<?php require_once '../includes/footer.php'; ?>