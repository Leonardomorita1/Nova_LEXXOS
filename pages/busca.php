<?php
// pages/busca.php - Versão Redesenhada
require_once '../config/config.php';
require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();

$user_id = $_SESSION['user_id'] ?? null;

// ============================================
// PRE-LOAD USER DATA (PERFORMANCE)
// ============================================
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

// ============================================
// PARÂMETROS DE BUSCA
// ============================================
$q = trim($_GET['q'] ?? '');
$categoria = $_GET['categoria'] ?? '';
$plataforma = $_GET['plataforma'] ?? '';
$preco_min = isset($_GET['preco_min']) && $_GET['preco_min'] !== '' ? (float)$_GET['preco_min'] : null;
$preco_max = isset($_GET['preco_max']) && $_GET['preco_max'] !== '' ? (float)$_GET['preco_max'] : null;
$promocao = isset($_GET['promocao']) ? 1 : 0;
$gratuito = isset($_GET['gratuito']) ? 1 : 0;
$nota_min = isset($_GET['nota_min']) ? (float)$_GET['nota_min'] : 0;
$ordem = $_GET['ordem'] ?? 'relevancia';
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$por_pagina = 24;
$offset = ($pagina - 1) * $por_pagina;

// ============================================
// CONSTRUIR QUERY DE BUSCA
// ============================================
$where = ["j.status = 'publicado'"];
$params = [];
$joins = [];

if (!empty($q)) {
    $where[] = "(
        j.titulo LIKE ? OR 
        j.titulo LIKE ? OR
        j.descricao_curta LIKE ? OR 
        d.nome_estudio LIKE ? OR
        EXISTS (SELECT 1 FROM jogo_tag jt2 INNER JOIN tag t2 ON jt2.tag_id = t2.id WHERE jt2.jogo_id = j.id AND t2.nome LIKE ?)
    )";
    $search_start = "$q%";
    $search_any = "%$q%";
    $params[] = $search_start;
    $params[] = $search_any;
    $params[] = $search_any;
    $params[] = $search_any;
    $params[] = $search_any;
}

if (!empty($categoria)) {
    $joins[] = "INNER JOIN jogo_categoria jc ON j.id = jc.jogo_id INNER JOIN categoria cat ON jc.categoria_id = cat.id AND cat.slug = ?";
    $params[] = $categoria;
}

if (!empty($plataforma)) {
    $joins[] = "INNER JOIN jogo_plataforma jp ON j.id = jp.jogo_id INNER JOIN plataforma plat ON jp.plataforma_id = plat.id AND plat.slug = ?";
    $params[] = $plataforma;
}

if ($gratuito) {
    $where[] = "j.preco_centavos = 0";
} else {
    if ($preco_min !== null) {
        $where[] = "j.preco_centavos >= ?";
        $params[] = $preco_min * 100;
    }
    if ($preco_max !== null) {
        $where[] = "j.preco_centavos <= ?";
        $params[] = $preco_max * 100;
    }
}

if ($promocao) {
    $where[] = "j.em_promocao = 1 AND j.preco_promocional_centavos IS NOT NULL";
}

if ($nota_min > 0) {
    $where[] = "j.nota_media >= ?";
    $params[] = $nota_min;
}

$where_clause = implode(' AND ', $where);
$joins_clause = implode(' ', $joins);

// ============================================
// ORDENAÇÃO - CORRIGIDA
// ============================================
$order_by = match($ordem) {
    'preco_asc' => 'COALESCE(j.preco_promocional_centavos, j.preco_centavos) ASC, j.titulo ASC',
    'preco_desc' => 'COALESCE(j.preco_promocional_centavos, j.preco_centavos) DESC, j.titulo ASC',
    'nota' => 'j.nota_media DESC, j.total_avaliacoes DESC, j.titulo ASC',
    'vendas' => 'j.total_vendas DESC, j.nota_media DESC',
    // CORREÇÃO: Usar COALESCE para garantir ordenação correta e adicionar fallback
    'recente' => 'COALESCE(j.publicado_em, j.criado_em) DESC, j.id DESC',
    'antigo' => 'COALESCE(j.publicado_em, j.criado_em) ASC, j.id ASC',
    'titulo' => 'j.titulo ASC',
    'titulo_desc' => 'j.titulo DESC',
    'desconto' => '(j.preco_centavos - COALESCE(j.preco_promocional_centavos, j.preco_centavos)) DESC, j.titulo ASC',
    default => !empty($q) 
        ? "CASE WHEN j.titulo LIKE '$q%' THEN 1 WHEN j.titulo LIKE '%$q%' THEN 2 ELSE 3 END, j.total_vendas DESC" 
        : 'j.total_vendas DESC, j.nota_media DESC'
};

// ============================================
// EXECUTAR BUSCA
// ============================================
$sql = "
    SELECT DISTINCT j.*, 
           d.nome_estudio, 
           d.slug as dev_slug,
           d.verificado as dev_verificado,
           GROUP_CONCAT(DISTINCT t.nome ORDER BY t.nome SEPARATOR ', ') as tags
    FROM jogo j
    LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id
    LEFT JOIN jogo_tag jt ON j.id = jt.jogo_id
    LEFT JOIN tag t ON jt.tag_id = t.id
    $joins_clause
    WHERE $where_clause
    GROUP BY j.id
    ORDER BY $order_by
    LIMIT $por_pagina OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$jogos = $stmt->fetchAll();

// ============================================
// TOTAL DE RESULTADOS
// ============================================
$count_sql = "
    SELECT COUNT(DISTINCT j.id) as total 
    FROM jogo j
    LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id
    $joins_clause
    WHERE $where_clause
";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_jogos = $stmt->fetch()['total'];
$total_paginas = ceil($total_jogos / $por_pagina);

// ============================================
// BUSCAR DADOS PARA FILTROS
// ============================================
$categorias = $pdo->query("
    SELECT c.*, COUNT(DISTINCT jc.jogo_id) as total_jogos
    FROM categoria c
    LEFT JOIN jogo_categoria jc ON c.id = jc.categoria_id
    LEFT JOIN jogo j ON jc.jogo_id = j.id AND j.status = 'publicado'
    WHERE c.ativa = 1
    GROUP BY c.id
    HAVING total_jogos > 0
    ORDER BY c.ordem, c.nome
")->fetchAll();

$plataformas = $pdo->query("
    SELECT p.*, COUNT(DISTINCT jp.jogo_id) as total_jogos
    FROM plataforma p
    LEFT JOIN jogo_plataforma jp ON p.id = jp.plataforma_id
    LEFT JOIN jogo j ON jp.jogo_id = j.id AND j.status = 'publicado'
    WHERE p.ativa = 1
    GROUP BY p.id
    HAVING total_jogos > 0
    ORDER BY p.ordem, p.nome
")->fetchAll();

$tags_populares = $pdo->query("
    SELECT t.*, COUNT(jt.jogo_id) as uso
    FROM tag t
    INNER JOIN jogo_tag jt ON t.id = jt.tag_id
    INNER JOIN jogo j ON jt.jogo_id = j.id AND j.status = 'publicado'
    GROUP BY t.id
    ORDER BY uso DESC
    LIMIT 12
")->fetchAll();

// ============================================
// VERIFICAR FILTROS ATIVOS
// ============================================
$filtros_ativos = [];
if (!empty($categoria)) {
    $cat_nome = array_filter($categorias, fn($c) => $c['slug'] === $categoria);
    $cat_nome = reset($cat_nome);
    $filtros_ativos[] = ['tipo' => 'categoria', 'valor' => $categoria, 'label' => $cat_nome['nome'] ?? $categoria, 'icon' => 'folder'];
}
if (!empty($plataforma)) {
    $plat_nome = array_filter($plataformas, fn($p) => $p['slug'] === $plataforma);
    $plat_nome = reset($plat_nome);
    $filtros_ativos[] = ['tipo' => 'plataforma', 'valor' => $plataforma, 'label' => $plat_nome['nome'] ?? $plataforma, 'icon' => 'desktop'];
}
if ($promocao) $filtros_ativos[] = ['tipo' => 'promocao', 'valor' => 1, 'label' => 'Em Promoção', 'icon' => 'bolt'];
if ($gratuito) $filtros_ativos[] = ['tipo' => 'gratuito', 'valor' => 1, 'label' => 'Gratuitos', 'icon' => 'gift'];
if ($preco_min !== null) $filtros_ativos[] = ['tipo' => 'preco_min', 'valor' => $preco_min, 'label' => 'Min: R$ ' . number_format($preco_min, 2, ',', '.'), 'icon' => 'coins'];
if ($preco_max !== null) $filtros_ativos[] = ['tipo' => 'preco_max', 'valor' => $preco_max, 'label' => 'Max: R$ ' . number_format($preco_max, 2, ',', '.'), 'icon' => 'coins'];
if ($nota_min > 0) $filtros_ativos[] = ['tipo' => 'nota_min', 'valor' => $nota_min, 'label' => $nota_min . '+ estrelas', 'icon' => 'star'];

$page_title = !empty($q) ? "Busca: $q - " . SITE_NAME : "Explorar Jogos - " . SITE_NAME;
$page_description = "Encontre os melhores jogos indies. " . $total_jogos . " jogos disponíveis.";

require_once '../components/game-card.php';
require_once '../includes/header.php';
?>

<style>
/* ============================================
   SEARCH PAGE - REDESIGNED
   ============================================ */
.search-page {
    min-height: 100vh;
    background: var(--bg-primary);
    padding-bottom: 100px;
}

/* ============================================
   SEARCH HERO - MODERN DESIGN
   ============================================ */
.search-hero {
    position: relative;
    background: var(--bg-secondary);
    padding: 48px 24px 40px;
    border-bottom: 1px solid var(--border);
    overflow: hidden;
}

.search-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: 
        radial-gradient(ellipse at 20% 0%, rgba(0, 174, 255, 0.08) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 100%, rgba(145, 71, 255, 0.06) 0%, transparent 50%);
    pointer-events: none;
}

.search-hero .container {
    position: relative;
    max-width: 1400px;
    margin: 0 auto;
    z-index: 1;
}

.search-hero-content {
    max-width: 720px;
    margin: 0 auto;
    text-align: center;
}

.search-title {
    font-size: 2.2rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 28px;
    line-height: 1.2;
}

.search-title .highlight {
    background: linear-gradient(135deg, var(--accent), #9147ff);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.search-label {
    display: block;
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--text-secondary);
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* Search Form - Glass Effect */
.search-main-form {
    display: flex;
    gap: 12px;
    margin-bottom: 24px;
}

.search-input-wrapper {
    flex: 1;
    position: relative;
    display: flex;
    align-items: center;
    background: var(--bg-primary);
    border: 2px solid var(--border);
    border-radius: 16px;
    padding: 0 20px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.search-input-wrapper:focus-within {
    border-color: var(--accent);
    box-shadow: 0 4px 20px rgba(0, 174, 255, 0.15), 0 0 0 4px rgba(0, 174, 255, 0.1);
}

.search-input-wrapper > i {
    color: var(--text-secondary);
    font-size: 18px;
    transition: color 0.3s;
}

.search-input-wrapper:focus-within > i {
    color: var(--accent);
}

.search-input-wrapper input {
    flex: 1;
    background: none;
    border: none;
    padding: 18px 15px;
    font-size: 1rem;
    color: var(--text-primary);
    outline: none;
}

.search-input-wrapper input::placeholder {
    color: var(--text-secondary);
}

.search-clear {
    background: var(--bg-secondary);
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    padding: 8px;
    border-radius: 8px;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.search-clear:hover {
    color: var(--text-primary);
    background: var(--border);
}

.search-submit-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    background: linear-gradient(135deg, var(--accent), #0095d9);
    color: #fff;
    border: none;
    padding: 18px 32px;
    border-radius: 16px;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 4px 15px rgba(0, 174, 255, 0.3);
}

.search-submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 25px rgba(0, 174, 255, 0.4);
}

/* Quick Filters */
.quick-filters {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.quick-filter-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--bg-primary);
    color: var(--text-secondary);
    padding: 10px 18px;
    border-radius: 25px;
    font-size: 0.9rem;
    font-weight: 500;
    text-decoration: none;
    border: 1px solid var(--border);
    transition: all 0.2s;
}

.quick-filter-btn:hover {
    border-color: var(--accent);
    color: var(--accent);
    background: rgba(0, 174, 255, 0.05);
}

.quick-filter-btn.active {
    background: var(--accent);
    color: #fff;
    border-color: var(--accent);
}

.quick-filter-btn i {
    font-size: 14px;
}

.quick-filter-btn.sale { color: var(--success); border-color: rgba(40, 167, 69, 0.3); }
.quick-filter-btn.sale:hover { background: rgba(40, 167, 69, 0.1); border-color: var(--success); }
.quick-filter-btn.sale.active { background: var(--success); color: #fff; }

.quick-filter-btn.free { color: #f9ca24; border-color: rgba(249, 202, 36, 0.3); }
.quick-filter-btn.free:hover { background: rgba(249, 202, 36, 0.1); border-color: #f9ca24; }
.quick-filter-btn.free.active { background: #f9ca24; color: #000; }

/* Search Suggestions */
.search-suggestions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid var(--border);
}

.suggestions-label {
    color: var(--text-secondary);
    font-size: 0.85rem;
}

.suggestion-tag {
    background: transparent;
    color: var(--text-secondary);
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.85rem;
    text-decoration: none;
    transition: all 0.2s;
    border: 1px solid var(--border);
}

.suggestion-tag:hover {
    background: var(--accent);
    color: #fff;
    border-color: var(--accent);
}

/* ============================================
   MAIN CONTENT LAYOUT
   ============================================ */
.search-content {
    display: grid;
    grid-template-columns: 260px 1fr;
    gap: 32px;
    max-width: 1400px;
    margin: 0 auto;
    padding: 32px 24px;
}

/* ============================================
   SIDEBAR FILTERS - REDESIGNED
   ============================================ */
.search-sidebar {
    position: sticky;
    top: 90px;
    height: fit-content;
    max-height: calc(100vh - 110px);
    overflow-y: auto;
    background: var(--bg-secondary);
    border-radius: 20px;
    border: 1px solid var(--border);
}

.search-sidebar::-webkit-scrollbar {
    width: 6px;
}

.search-sidebar::-webkit-scrollbar-track {
    background: transparent;
}

.search-sidebar::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 3px;
}

.sidebar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid var(--border);
    background: rgba(0, 174, 255, 0.03);
}

.sidebar-header h2 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
}

.sidebar-header h2 i {
    color: var(--accent);
    font-size: 16px;
}

.clear-filters {
    color: var(--danger);
    font-size: 0.8rem;
    text-decoration: none;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    border-radius: 6px;
    transition: all 0.2s;
}

.clear-filters:hover {
    background: rgba(255, 107, 107, 0.1);
}

/* Filter Groups */
.filter-group {
    border-bottom: 1px solid var(--border);
}

.filter-group:last-child {
    border-bottom: none;
}

.filter-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    background: none;
    border: none;
    color: var(--text-primary);
    font-size: 0.9rem;
    font-weight: 600;
    padding: 16px 20px;
    cursor: pointer;
    transition: all 0.2s;
}

.filter-header:hover {
    background: rgba(255,255,255,0.02);
}

.filter-header span {
    display: flex;
    align-items: center;
    gap: 10px;
}

.filter-header span i {
    color: var(--text-secondary);
    width: 16px;
    font-size: 14px;
}

.filter-header > i {
    color: var(--text-secondary);
    font-size: 11px;
    transition: transform 0.3s;
}

.filter-group.collapsed .filter-header > i {
    transform: rotate(-90deg);
}

.filter-group.collapsed .filter-content {
    display: none;
}

.filter-content {
    padding: 0 16px 16px;
}

/* Filter Items */
.filter-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    margin: 0 -4px;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
}

.filter-item:hover {
    background: rgba(255,255,255,0.03);
}

.filter-item.selected {
    background: rgba(0, 174, 255, 0.08);
}

.filter-item input {
    display: none;
}

/* Custom Radio/Checkbox */
.filter-check {
    width: 18px;
    height: 18px;
    border: 2px solid var(--border);
    border-radius: 5px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    flex-shrink: 0;
    background: var(--bg-primary);
}

.filter-item input[type="radio"] + .filter-check {
    border-radius: 50%;
}

.filter-item input:checked + .filter-check {
    background: var(--accent);
    border-color: var(--accent);
}

.filter-item input:checked + .filter-check::after {
    content: '';
    width: 8px;
    height: 8px;
    background: #fff;
    border-radius: 2px;
}

.filter-item input[type="radio"]:checked + .filter-check::after {
    border-radius: 50%;
    width: 6px;
    height: 6px;
}

.filter-label {
    flex: 1;
    font-size: 0.88rem;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-item.selected .filter-label {
    color: var(--accent);
    font-weight: 500;
}

.filter-label i {
    font-size: 13px;
    color: var(--text-secondary);
    width: 16px;
}

.filter-count {
    font-size: 0.75rem;
    color: var(--text-secondary);
    background: var(--bg-primary);
    padding: 2px 8px;
    border-radius: 10px;
    min-width: 28px;
    text-align: center;
}

/* Special Filter Items */
.filter-item.promo .filter-label i { color: var(--success); }
.filter-item.free .filter-label i { color: #f9ca24; }

/* Stars Rating Filter */
.filter-rating .stars {
    display: flex;
    gap: 2px;
}

.filter-rating .stars i {
    font-size: 11px;
    color: var(--border);
}

.filter-rating .stars i.filled {
    color: #f9ca24;
}

/* Price Range */
.price-range {
    padding: 8px 0;
}

.price-inputs {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
}

.price-field {
    flex: 1;
    display: flex;
    align-items: center;
    background: var(--bg-primary);
    border-radius: 10px;
    padding: 0 12px;
    border: 1px solid var(--border);
    transition: border-color 0.2s;
}

.price-field:focus-within {
    border-color: var(--accent);
}

.price-field span {
    color: var(--text-secondary);
    font-size: 0.85rem;
    font-weight: 500;
}

.price-field input {
    flex: 1;
    background: none;
    border: none;
    padding: 12px 8px;
    font-size: 0.9rem;
    color: var(--text-primary);
    width: 100%;
    outline: none;
}

.price-separator {
    color: var(--text-secondary);
    font-size: 12px;
}

.price-apply {
    width: 100%;
    background: var(--bg-primary);
    color: var(--accent);
    border: 1px solid var(--accent);
    padding: 12px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.2s;
}

.price-apply:hover {
    background: var(--accent);
    color: #fff;
}

/* ============================================
   RESULTS AREA - IMPROVED
   ============================================ */
.search-results {
    min-width: 0;
}

/* Results Header */
.results-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}

.results-info {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.results-count {
    font-size: 1rem;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 8px;
}

.results-count strong {
    color: var(--text-primary);
    font-weight: 700;
    font-size: 1.1rem;
}

.results-count i {
    color: var(--accent);
}

/* Active Filters Chips */
.active-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.filter-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, rgba(0, 174, 255, 0.1), rgba(145, 71, 255, 0.1));
    color: var(--accent);
    padding: 8px 14px;
    border-radius: 25px;
    font-size: 0.85rem;
    font-weight: 500;
    border: 1px solid rgba(0, 174, 255, 0.2);
    animation: chipIn 0.3s ease;
}

@keyframes chipIn {
    from { opacity: 0; transform: scale(0.8); }
    to { opacity: 1; transform: scale(1); }
}

.filter-chip i {
    font-size: 12px;
}

.chip-remove {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
    color: var(--accent);
    font-size: 10px;
    text-decoration: none;
    transition: all 0.2s;
    margin-left: 4px;
}

.chip-remove:hover {
    background: var(--danger);
    color: white;
    transform: rotate(90deg);
}

/* Results Actions */
.results-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

.filter-toggle-btn {
    display: none;
    align-items: center;
    gap: 8px;
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border);
    padding: 12px 18px;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    position: relative;
    transition: all 0.2s;
}

.filter-toggle-btn:hover {
    border-color: var(--accent);
}

.filter-badge {
    position: absolute;
    top: -6px;
    right: -6px;
    background: var(--accent);
    color: #fff;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    font-size: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    box-shadow: 0 2px 8px rgba(0, 174, 255, 0.4);
}

/* Sort Select - Modern */
.sort-wrapper {
    position: relative;
}

.sort-select {
    appearance: none;
    background: var(--bg-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border);
    padding: 14px 50px 14px 18px;
    border-radius: 12px;
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    min-width: 200px;
    transition: all 0.2s;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%2300aeff' viewBox='0 0 24 24'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 14px center;
    background-size: 20px;
}

.sort-select:hover {
    border-color: var(--accent);
}

.sort-select:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(0, 174, 255, 0.1);
}

/* View Toggle (optional) */
.view-toggle {
    display: flex;
    background: var(--bg-secondary);
    border-radius: 10px;
    padding: 4px;
    border: 1px solid var(--border);
}

.view-btn {
    padding: 10px 14px;
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    border-radius: 8px;
    transition: all 0.2s;
}

.view-btn:hover {
    color: var(--text-primary);
}

.view-btn.active {
    background: var(--accent);
    color: #fff;
}

/* ============================================
   RESULTS GRID - RESPONSIVE
   ============================================ */
.results-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 20px;
}

.results-grid.list-view {
    grid-template-columns: 1fr;
}

/* Loading State */
.results-loading {
    display: none;
    justify-content: center;
    padding: 60px 0;
}

.results-loading.active {
    display: flex;
}

.loading-spinner {
    width: 48px;
    height: 48px;
    border: 3px solid var(--border);
    border-top-color: var(--accent);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* ============================================
   PAGINATION - MODERN
   ============================================ */
.pagination-wrapper {
    margin-top: 48px;
    padding-top: 32px;
    border-top: 1px solid var(--border);
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.page-btn, .page-num {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 20px;
    background: var(--bg-secondary);
    color: var(--text-secondary);
    border: 1px solid var(--border);
    border-radius: 12px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.2s;
}

.page-num {
    width: 46px;
    height: 46px;
    padding: 0;
}

.page-btn:hover, .page-num:hover {
    background: var(--bg-primary);
    color: var(--accent);
    border-color: var(--accent);
}

.page-num.active {
    background: linear-gradient(135deg, var(--accent), #0095d9);
    color: #fff;
    border-color: var(--accent);
    font-weight: 700;
    box-shadow: 0 4px 15px rgba(0, 174, 255, 0.3);
}

.page-ellipsis {
    color: var(--text-secondary);
    padding: 0 8px;
    font-weight: 600;
}

.page-numbers {
    display: flex;
    gap: 8px;
}

.pagination-info {
    text-align: center;
    color: var(--text-secondary);
    font-size: 0.85rem;
    margin-top: 16px;
}

/* ============================================
   EMPTY STATE - IMPROVED
   ============================================ */
.empty-results {
    text-align: center;
    padding: 80px 30px;
    background: var(--bg-secondary);
    border-radius: 24px;
    border: 1px solid var(--border);
    position: relative;
    overflow: hidden;
}

.empty-results::before {
    content: '';
    position: absolute;
    inset: 0;
    background: 
        radial-gradient(circle at 30% 20%, rgba(0, 174, 255, 0.05) 0%, transparent 50%),
        radial-gradient(circle at 70% 80%, rgba(145, 71, 255, 0.05) 0%, transparent 50%);
    pointer-events: none;
}

.empty-icon {
    position: relative;
    font-size: 80px;
    margin-bottom: 24px;
    background: linear-gradient(135deg, var(--text-secondary), var(--border));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    opacity: 0.5;
}

.empty-results h2 {
    position: relative;
    font-size: 1.6rem;
    color: var(--text-primary);
    margin-bottom: 12px;
}

.empty-results > p {
    position: relative;
    color: var(--text-secondary);
    margin-bottom: 32px;
    max-width: 420px;
    margin-left: auto;
    margin-right: auto;
    line-height: 1.6;
}

.empty-actions {
    position: relative;
    display: flex;
    gap: 14px;
    justify-content: center;
    flex-wrap: wrap;
}

.empty-suggestions {
    position: relative;
    margin-top: 48px;
    padding-top: 32px;
    border-top: 1px solid var(--border);
}

.empty-suggestions p {
    color: var(--text-secondary);
    margin-bottom: 16px;
    font-size: 0.9rem;
}

.suggestion-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: center;
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 14px 28px;
    border-radius: 12px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s;
    cursor: pointer;
    border: none;
    font-size: 0.95rem;
}

.btn-primary {
    background: linear-gradient(135deg, var(--accent), #0095d9);
    color: #fff;
    box-shadow: 0 4px 15px rgba(0, 174, 255, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 25px rgba(0, 174, 255, 0.4);
}

.btn-secondary {
    background: var(--bg-primary);
    color: var(--text-primary);
    border: 1px solid var(--border);
}

.btn-secondary:hover {
    border-color: var(--accent);
    color: var(--accent);
}

/* ============================================
   MOBILE FILTERS SHEET - IMPROVED
   ============================================ */
.filters-sheet-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.7);
    backdrop-filter: blur(4px);
    z-index: 1000;
    opacity: 0;
    transition: opacity 0.3s;
}

.filters-sheet-overlay.active {
    display: block;
    opacity: 1;
}

.filters-sheet {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: var(--bg-secondary);
    border-radius: 24px 24px 0 0;
    z-index: 1001;
    transform: translateY(100%);
    transition: transform 0.3s ease-out;
    max-height: 85vh;
    display: flex;
    flex-direction: column;
}

.filters-sheet.active {
    transform: translateY(0);
}

.filters-sheet-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
}

.filters-sheet-header h3 {
    font-size: 1.15rem;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
}

.filters-sheet-header h3 i {
    color: var(--accent);
}

.filters-sheet-close {
    background: var(--bg-primary);
    border: none;
    color: var(--text-secondary);
    font-size: 18px;
    cursor: pointer;
    padding: 10px;
    border-radius: 10px;
    transition: all 0.2s;
}

.filters-sheet-close:hover {
    color: var(--text-primary);
    background: var(--border);
}

.filters-sheet-content {
    flex: 1;
    overflow-y: auto;
    padding: 0;
}

.filters-sheet-footer {
    display: flex;
    gap: 12px;
    padding: 20px 24px;
    border-top: 1px solid var(--border);
    background: var(--bg-primary);
}

.filters-sheet-footer .btn {
    flex: 1;
    justify-content: center;
}

/* ============================================
   RESPONSIVE - TABLET
   ============================================ */
@media (max-width: 1024px) {
    .search-content {
        grid-template-columns: 240px 1fr;
        gap: 24px;
    }
    
    .results-grid {
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 16px;
    }
    
    .sort-select {
        min-width: 170px;
    }
}

/* ============================================
   RESPONSIVE - MOBILE
   ============================================ */
@media (max-width: 768px) {
    .search-hero {
        padding: 32px 16px 28px;
    }
    
    .search-title {
        font-size: 1.6rem;
        margin-bottom: 24px;
    }
    
    .search-main-form {
        flex-direction: column;
        gap: 12px;
    }
    
    .search-input-wrapper {
        padding: 0 16px;
        border-radius: 14px;
    }
    
    .search-input-wrapper input {
        padding: 16px 12px;
    }
    
    .search-submit-btn {
        width: 100%;
        justify-content: center;
        padding: 16px;
        border-radius: 14px;
    }
    
    .quick-filters {
        gap: 8px;
    }
    
    .quick-filter-btn {
        padding: 8px 14px;
        font-size: 0.85rem;
    }
    
    .search-content {
        grid-template-columns: 1fr;
        padding: 20px 16px;
    }
    
    .search-sidebar {
        display: none;
    }
    
    .filter-toggle-btn {
        display: flex;
    }
    
    .results-header {
        flex-direction: column;
        gap: 16px;
    }
    
    .results-actions {
        width: 100%;
        justify-content: space-between;
    }
    
    .sort-select {
        min-width: 150px;
        padding: 12px 40px 12px 14px;
        font-size: 0.85rem;
    }
    
    .results-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    
    .pagination-wrapper {
        margin-top: 32px;
        padding-top: 24px;
    }
    
    .pagination {
        gap: 6px;
    }
    
    .page-btn span {
        display: none;
    }
    
    .page-btn {
        padding: 12px 14px;
    }
    
    .page-num {
        width: 42px;
        height: 42px;
        font-size: 0.9rem;
    }
    
    .empty-results {
        padding: 50px 20px;
        border-radius: 16px;
    }
    
    .empty-icon {
        font-size: 60px;
    }
    
    .empty-results h2 {
        font-size: 1.3rem;
    }
}

/* ============================================
   RESPONSIVE - SMALL MOBILE
   ============================================ */
@media (max-width: 400px) {
    .search-title {
        font-size: 1.4rem;
    }
    
    .results-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
    
    .active-filters {
        gap: 6px;
    }
    
    .filter-chip {
        font-size: 0.8rem;
        padding: 6px 12px;
    }
    
    .btn {
        padding: 12px 20px;
        font-size: 0.9rem;
    }
    
    .quick-filter-btn span {
        display: none;
    }
}
</style>

<div class="search-page">
    <!-- SEARCH HERO -->
    <section class="search-hero">
        <div class="container">
            <div class="search-hero-content">
                <h1 class="search-title">
                    <?php if (!empty($q)): ?>
                        <span class="search-label">Resultados para</span>
                        "<span class="highlight"><?= htmlspecialchars($q) ?></span>"
                    <?php else: ?>
                        <span class="highlight">Explorar</span> Jogos
                    <?php endif; ?>
                </h1>
                
                <form class="search-main-form" action="" method="GET" id="searchForm">
                    <div class="search-input-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" 
                               name="q" 
                               id="searchInput"
                               placeholder="Buscar por título, desenvolvedor ou tag..." 
                               value="<?= htmlspecialchars($q) ?>"
                               autocomplete="off">
                        <?php if (!empty($q)): ?>
                            <button type="button" class="search-clear" onclick="clearSearch()">
                                <i class="fas fa-times"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="search-submit-btn">
                        <i class="fas fa-search"></i>
                        <span>Buscar</span>
                    </button>
                </form>

                <!-- Quick Filters -->
                <div class="quick-filters">
                    <?php
                    $base_url = SITE_URL . '/pages/busca.php';
                    $current_params = $_GET;
                    ?>
                    
                    <a href="<?= $base_url ?>?<?= http_build_query(array_merge($current_params, ['promocao' => $promocao ? null : 1])) ?>" 
                       class="quick-filter-btn sale <?= $promocao ? 'active' : '' ?>">
                        <i class="fas fa-bolt"></i>
                        <span>Em Promoção</span>
                    </a>
                    
                    <a href="<?= $base_url ?>?<?= http_build_query(array_merge($current_params, ['gratuito' => $gratuito ? null : 1])) ?>" 
                       class="quick-filter-btn free <?= $gratuito ? 'active' : '' ?>">
                        <i class="fas fa-gift"></i>
                        <span>Gratuitos</span>
                    </a>
                    
                    <a href="<?= $base_url ?>?<?= http_build_query(array_merge($current_params, ['ordem' => 'recente'])) ?>" 
                       class="quick-filter-btn <?= $ordem === 'recente' ? 'active' : '' ?>">
                        <i class="fas fa-sparkles"></i>
                        <span>Lançamentos</span>
                    </a>
                    
                    <a href="<?= $base_url ?>?<?= http_build_query(array_merge($current_params, ['ordem' => 'nota'])) ?>" 
                       class="quick-filter-btn <?= $ordem === 'nota' ? 'active' : '' ?>">
                        <i class="fas fa-star"></i>
                        <span>Mais Avaliados</span>
                    </a>
                </div>

                <?php if (empty($q) && !empty($tags_populares)): ?>
                <div class="search-suggestions">
                    <span class="suggestions-label">Populares:</span>
                    <?php foreach (array_slice($tags_populares, 0, 6) as $tag): ?>
                        <a href="?q=<?= urlencode($tag['nome']) ?>" class="suggestion-tag">
                            <?= htmlspecialchars($tag['nome']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- MAIN CONTENT -->
    <div class="container">
        <div class="search-content">
            <!-- SIDEBAR FILTERS -->
            <aside class="search-sidebar" id="filtersSidebar">
                <div class="sidebar-header">
                    <h2><i class="fas fa-sliders-h"></i> Filtros</h2>
                    <?php if (!empty($filtros_ativos)): ?>
                        <a href="<?= SITE_URL ?>/pages/busca.php<?= !empty($q) ? '?q=' . urlencode($q) : '' ?>" class="clear-filters">
                            <i class="fas fa-times"></i> Limpar
                        </a>
                    <?php endif; ?>
                </div>

                <form method="GET" action="" id="filtersForm">
                    <?php if (!empty($q)): ?>
                        <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>">
                    <?php endif; ?>

                    <!-- Plataformas -->
                    <?php if (!empty($plataformas)): ?>
                    <div class="filter-group">
                        <button type="button" class="filter-header" onclick="toggleFilter(this)">
                            <span><i class="fas fa-desktop"></i> Plataforma</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="filter-content">
                            <?php foreach ($plataformas as $plat): 
                                $isSelected = $plataforma === $plat['slug'];
                            ?>
                            <label class="filter-item <?= $isSelected ? 'selected' : '' ?>">
                                <input type="radio" 
                                       name="plataforma" 
                                       value="<?= $plat['slug'] ?>"
                                       <?= $isSelected ? 'checked' : '' ?>
                                       onchange="this.form.submit()">
                                <span class="filter-check"></span>
                                <span class="filter-label">
                                    <i class="<?= $plat['icone'] ?? 'fas fa-gamepad' ?>"></i>
                                    <?= htmlspecialchars($plat['nome']) ?>
                                </span>
                                <span class="filter-count"><?= $plat['total_jogos'] ?></span>
                            </label>
                            <?php endforeach; ?>
                            <?php if (!empty($plataforma)): ?>
                            <label class="filter-item">
                                <input type="radio" name="plataforma" value="" onchange="this.form.submit()">
                                <span class="filter-check"></span>
                                <span class="filter-label">Todas as plataformas</span>
                            </label>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Categorias -->
                    <?php if (!empty($categorias)): ?>
                    <div class="filter-group">
                        <button type="button" class="filter-header" onclick="toggleFilter(this)">
                            <span><i class="fas fa-folder"></i> Categoria</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="filter-content">
                            <?php foreach ($categorias as $cat): 
                                $isSelected = $categoria === $cat['slug'];
                            ?>
                            <label class="filter-item <?= $isSelected ? 'selected' : '' ?>">
                                <input type="radio" 
                                       name="categoria" 
                                       value="<?= $cat['slug'] ?>"
                                       <?= $isSelected ? 'checked' : '' ?>
                                       onchange="this.form.submit()">
                                <span class="filter-check"></span>
                                <span class="filter-label"><?= htmlspecialchars($cat['nome']) ?></span>
                                <span class="filter-count"><?= $cat['total_jogos'] ?></span>
                            </label>
                            <?php endforeach; ?>
                            <?php if (!empty($categoria)): ?>
                            <label class="filter-item">
                                <input type="radio" name="categoria" value="" onchange="this.form.submit()">
                                <span class="filter-check"></span>
                                <span class="filter-label">Todas as categorias</span>
                            </label>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Preço -->
                    <div class="filter-group">
                        <button type="button" class="filter-header" onclick="toggleFilter(this)">
                            <span><i class="fas fa-tag"></i> Preço</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="filter-content">
                            <label class="filter-item free <?= $gratuito ? 'selected' : '' ?>">
                                <input type="checkbox" 
                                       name="gratuito" 
                                       value="1"
                                       <?= $gratuito ? 'checked' : '' ?>
                                       onchange="this.form.submit()">
                                <span class="filter-check"></span>
                                <span class="filter-label">
                                    <i class="fas fa-gift"></i> Gratuitos
                                </span>
                            </label>
                            
                            <label class="filter-item promo <?= $promocao ? 'selected' : '' ?>">
                                <input type="checkbox" 
                                       name="promocao" 
                                       value="1"
                                       <?= $promocao ? 'checked' : '' ?>
                                       onchange="this.form.submit()">
                                <span class="filter-check"></span>
                                <span class="filter-label">
                                    <i class="fas fa-bolt"></i> Em Promoção
                                </span>
                            </label>

                            <div class="price-range">
                                <div class="price-inputs">
                                    <div class="price-field">
                                        <span>R$</span>
                                        <input type="number" 
                                               name="preco_min" 
                                               placeholder="Min"
                                               value="<?= $preco_min ?>"
                                               min="0"
                                               step="0.01">
                                    </div>
                                    <span class="price-separator">até</span>
                                    <div class="price-field">
                                        <span>R$</span>
                                        <input type="number" 
                                               name="preco_max" 
                                               placeholder="Max"
                                               value="<?= $preco_max ?>"
                                               min="0"
                                               step="0.01">
                                    </div>
                                </div>
                                <button type="submit" class="price-apply">
                                    <i class="fas fa-check"></i> Aplicar
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Avaliação -->
                    <div class="filter-group">
                        <button type="button" class="filter-header" onclick="toggleFilter(this)">
                            <span><i class="fas fa-star"></i> Avaliação</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="filter-content">
                            <?php foreach ([4, 3, 2, 1] as $nota): 
                                $isSelected = $nota_min == $nota;
                            ?>
                            <label class="filter-item filter-rating <?= $isSelected ? 'selected' : '' ?>">
                                <input type="radio" 
                                       name="nota_min" 
                                       value="<?= $nota ?>"
                                       <?= $isSelected ? 'checked' : '' ?>
                                       onchange="this.form.submit()">
                                <span class="filter-check"></span>
                                <span class="filter-label">
                                    <span class="stars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?= $i <= $nota ? 'filled' : '' ?>"></i>
                                        <?php endfor; ?>
                                    </span>
                                    ou mais
                                </span>
                            </label>
                            <?php endforeach; ?>
                            <?php if ($nota_min > 0): ?>
                            <label class="filter-item">
                                <input type="radio" name="nota_min" value="0" onchange="this.form.submit()">
                                <span class="filter-check"></span>
                                <span class="filter-label">Qualquer avaliação</span>
                            </label>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </aside>

            <!-- RESULTS -->
            <main class="search-results">
                <div class="results-header">
                    <div class="results-info">
                        <span class="results-count">
                            <i class="fas fa-gamepad"></i>
                            <strong><?= number_format($total_jogos, 0, ',', '.') ?></strong> 
                            jogo<?= $total_jogos != 1 ? 's' : '' ?> encontrado<?= $total_jogos != 1 ? 's' : '' ?>
                        </span>
                        
                        <?php if (!empty($filtros_ativos)): ?>
                        <div class="active-filters">
                            <?php foreach ($filtros_ativos as $filtro): ?>
                                <span class="filter-chip">
                                    <i class="fas fa-<?= $filtro['icon'] ?>"></i>
                                    <?= htmlspecialchars($filtro['label']) ?>
                                    <a href="?<?php 
                                        $params_url = $_GET;
                                        unset($params_url[$filtro['tipo']]);
                                        echo http_build_query($params_url);
                                    ?>" class="chip-remove">
                                        <i class="fas fa-times"></i>
                                    </a>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="results-actions">
                        <button class="filter-toggle-btn" onclick="openFiltersSheet()">
                            <i class="fas fa-sliders-h"></i>
                            Filtros
                            <?php if (!empty($filtros_ativos)): ?>
                                <span class="filter-badge"><?= count($filtros_ativos) ?></span>
                            <?php endif; ?>
                        </button>

                        <div class="sort-wrapper">
                            <select class="sort-select" onchange="updateSort(this.value)">
                                <option value="relevancia" <?= $ordem === 'relevancia' ? 'selected' : '' ?>>Mais Relevantes</option>
                                <option value="recente" <?= $ordem === 'recente' ? 'selected' : '' ?>>Mais Recentes</option>
                                <option value="antigo" <?= $ordem === 'antigo' ? 'selected' : '' ?>>Mais Antigos</option>
                                <option value="vendas" <?= $ordem === 'vendas' ? 'selected' : '' ?>>Mais Vendidos</option>
                                <option value="nota" <?= $ordem === 'nota' ? 'selected' : '' ?>>Melhor Avaliados</option>
                                <option value="preco_asc" <?= $ordem === 'preco_asc' ? 'selected' : '' ?>>Menor Preço</option>
                                <option value="preco_desc" <?= $ordem === 'preco_desc' ? 'selected' : '' ?>>Maior Preço</option>
                                <?php if ($promocao): ?>
                                <option value="desconto" <?= $ordem === 'desconto' ? 'selected' : '' ?>>Maior Desconto</option>
                                <?php endif; ?>
                                <option value="titulo" <?= $ordem === 'titulo' ? 'selected' : '' ?>>A-Z</option>
                                <option value="titulo_desc" <?= $ordem === 'titulo_desc' ? 'selected' : '' ?>>Z-A</option>
                            </select>
                        </div>
                    </div>
                </div>

                <?php if (count($jogos) > 0): ?>
                    <div class="results-grid" id="resultsGrid">
                        <?php foreach ($jogos as $jogo): ?>
                            <?php 
                            renderGameCard($jogo, $pdo, $user_id, 'store', [
                                'is_owned' => in_array($jogo['id'], $meus_jogos),
                                'in_wishlist' => in_array($jogo['id'], $minha_wishlist),
                                'in_cart' => in_array($jogo['id'], $meu_carrinho)
                            ]); 
                            ?>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($total_paginas > 1): ?>
                    <div class="pagination-wrapper">
                        <nav class="pagination">
                            <?php
                            $params_base = $_GET;
                            unset($params_base['pagina']);
                            $query_base = http_build_query($params_base);
                            $query_prefix = !empty($query_base) ? $query_base . '&' : '';
                            ?>
                            
                            <?php if ($pagina > 1): ?>
                                <a href="?<?= $query_prefix ?>pagina=<?= $pagina - 1 ?>" class="page-btn page-prev">
                                    <i class="fas fa-chevron-left"></i>
                                    <span>Anterior</span>
                                </a>
                            <?php endif; ?>

                            <div class="page-numbers">
                                <?php
                                $range = 2;
                                $start = max(1, $pagina - $range);
                                $end = min($total_paginas, $pagina + $range);

                                if ($start > 1): ?>
                                    <a href="?<?= $query_prefix ?>pagina=1" class="page-num">1</a>
                                    <?php if ($start > 2): ?>
                                        <span class="page-ellipsis">...</span>
                                    <?php endif; ?>
                                <?php endif;

                                for ($i = $start; $i <= $end; $i++): ?>
                                    <?php if ($i == $pagina): ?>
                                        <span class="page-num active"><?= $i ?></span>
                                    <?php else: ?>
                                        <a href="?<?= $query_prefix ?>pagina=<?= $i ?>" class="page-num"><?= $i ?></a>
                                    <?php endif; ?>
                                <?php endfor;

                                if ($end < $total_paginas): ?>
                                    <?php if ($end < $total_paginas - 1): ?>
                                        <span class="page-ellipsis">...</span>
                                    <?php endif; ?>
                                    <a href="?<?= $query_prefix ?>pagina=<?= $total_paginas ?>" class="page-num"><?= $total_paginas ?></a>
                                <?php endif; ?>
                            </div>

                            <?php if ($pagina < $total_paginas): ?>
                                <a href="?<?= $query_prefix ?>pagina=<?= $pagina + 1 ?>" class="page-btn page-next">
                                    <span>Próximo</span>
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </nav>

                        <div class="pagination-info">
                            Mostrando <?= $offset + 1 ?>-<?= min($offset + $por_pagina, $total_jogos) ?> de <?= number_format($total_jogos, 0, ',', '.') ?> jogos
                        </div>
                    </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="empty-results">
                        <div class="empty-icon">
                            <i class="fas fa-ghost"></i>
                        </div>
                        <h2>Nenhum jogo encontrado</h2>
                        <p>
                            <?php if (!empty($q)): ?>
                                Não encontramos jogos para "<strong><?= htmlspecialchars($q) ?></strong>" com os filtros selecionados. Tente ajustar sua busca.
                            <?php else: ?>
                                Não encontramos jogos com os filtros selecionados. Tente remover alguns filtros.
                            <?php endif; ?>
                        </p>
                        <div class="empty-actions">
                            <?php if (!empty($filtros_ativos)): ?>
                                <a href="<?= SITE_URL ?>/pages/busca.php<?= !empty($q) ? '?q=' . urlencode($q) : '' ?>" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Limpar Filtros
                                </a>
                            <?php endif; ?>
                            <a href="<?= SITE_URL ?>/pages/busca.php" class="btn btn-secondary">
                                <i class="fas fa-compass"></i> Explorar Todos
                            </a>
                        </div>

                        <?php if (!empty($tags_populares)): ?>
                        <div class="empty-suggestions">
                            <p>Tente buscar por:</p>
                            <div class="suggestion-tags">
                                <?php foreach (array_slice($tags_populares, 0, 8) as $tag): ?>
                                    <a href="?q=<?= urlencode($tag['nome']) ?>" class="suggestion-tag">
                                        <?= htmlspecialchars($tag['nome']) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
</div>

<!-- MOBILE FILTERS SHEET -->
<div class="filters-sheet-overlay" id="filtersSheetOverlay" onclick="closeFiltersSheet()"></div>
<div class="filters-sheet" id="filtersSheet">
    <div class="filters-sheet-header">
        <h3><i class="fas fa-sliders-h"></i> Filtros</h3>
        <button class="filters-sheet-close" onclick="closeFiltersSheet()">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="filters-sheet-content" id="filtersSheetContent"></div>
    <div class="filters-sheet-footer">
        <a href="<?= SITE_URL ?>/pages/busca.php<?= !empty($q) ? '?q=' . urlencode($q) : '' ?>" class="btn btn-secondary">
            <i class="fas fa-times"></i> Limpar
        </a>
        <button class="btn btn-primary" onclick="closeFiltersSheet()">
            <i class="fas fa-check"></i> Ver <?= number_format($total_jogos, 0, ',', '.') ?> Resultados
        </button>
    </div>
</div>

<script>
function toggleFilter(header) {
    const group = header.closest('.filter-group');
    group.classList.toggle('collapsed');
}

function updateSort(value) {
    const url = new URL(window.location);
    url.searchParams.set('ordem', value);
    url.searchParams.delete('pagina');
    window.location = url;
}

function clearSearch() {
    document.getElementById('searchInput').value = '';
    document.getElementById('searchForm').submit();
}

function openFiltersSheet() {
    const sidebar = document.getElementById('filtersSidebar');
    const sheetContent = document.getElementById('filtersSheetContent');
    sheetContent.innerHTML = sidebar.innerHTML;
    
    document.getElementById('filtersSheetOverlay').classList.add('active');
    document.getElementById('filtersSheet').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeFiltersSheet() {
    document.getElementById('filtersSheetOverlay').classList.remove('active');
    document.getElementById('filtersSheet').classList.remove('active');
    document.body.style.overflow = '';
}

// Close sheet on escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeFiltersSheet();
});

// Search input focus effect
document.getElementById('searchInput')?.addEventListener('focus', function() {
    this.closest('.search-input-wrapper').classList.add('focused');
});

document.getElementById('searchInput')?.addEventListener('blur', function() {
    this.closest('.search-input-wrapper').classList.remove('focused');
});
</script>

<?php require_once '../includes/footer.php'; ?>