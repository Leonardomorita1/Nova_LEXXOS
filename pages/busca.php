<?php
// pages/busca.php
require_once '../config/config.php';
require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();

$user_id = $_SESSION['user_id'] ?? null;

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

// Busca por texto (melhorada)
if (!empty($q)) {
    // Busca em múltiplos campos com relevância
    $where[] = "(
        j.titulo LIKE ? OR 
        j.titulo LIKE ? OR
        j.descricao_curta LIKE ? OR 
        d.nome_estudio LIKE ? OR
        EXISTS (SELECT 1 FROM jogo_tag jt2 INNER JOIN tag t2 ON jt2.tag_id = t2.id WHERE jt2.jogo_id = j.id AND t2.nome LIKE ?)
    )";
    $search_start = "$q%";
    $search_any = "%$q%";
    $params[] = $search_start; // Título começa com
    $params[] = $search_any;   // Título contém
    $params[] = $search_any;   // Descrição
    $params[] = $search_any;   // Estúdio
    $params[] = $search_any;   // Tags
}

// Filtro por categoria
if (!empty($categoria)) {
    $joins[] = "INNER JOIN jogo_categoria jc ON j.id = jc.jogo_id INNER JOIN categoria cat ON jc.categoria_id = cat.id AND cat.slug = ?";
    $params[] = $categoria;
}

// Filtro por plataforma
if (!empty($plataforma)) {
    $joins[] = "INNER JOIN jogo_plataforma jp ON j.id = jp.jogo_id INNER JOIN plataforma plat ON jp.plataforma_id = plat.id AND plat.slug = ?";
    $params[] = $plataforma;
}

// Filtro de preço
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

// Filtro de promoção
if ($promocao) {
    $where[] = "j.em_promocao = 1 AND j.preco_promocional_centavos IS NOT NULL";
}

// Filtro de nota mínima
if ($nota_min > 0) {
    $where[] = "j.nota_media >= ?";
    $params[] = $nota_min;
}

$where_clause = implode(' AND ', $where);
$joins_clause = implode(' ', $joins);

// ============================================
// ORDENAÇÃO
// ============================================
$order_by = match($ordem) {
    'preco_asc' => 'COALESCE(j.preco_promocional_centavos, j.preco_centavos) ASC',
    'preco_desc' => 'COALESCE(j.preco_promocional_centavos, j.preco_centavos) DESC',
    'nota' => 'j.nota_media DESC, j.total_avaliacoes DESC',
    'vendas' => 'j.total_vendas DESC',
    'recente' => 'j.publicado_em DESC',
    'titulo' => 'j.titulo ASC',
    'desconto' => 'j.porcentagem_desconto DESC',
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

// Categorias com contagem
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

// Plataformas com contagem
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

// Tags populares
$tags_populares = $pdo->query("
    SELECT t.*, COUNT(jt.jogo_id) as uso
    FROM tag t
    INNER JOIN jogo_tag jt ON t.id = jt.tag_id
    INNER JOIN jogo j ON jt.jogo_id = j.id AND j.status = 'publicado'
    GROUP BY t.id
    ORDER BY uso DESC
    LIMIT 12
")->fetchAll();

// Faixa de preços
$preco_stats = $pdo->query("
    SELECT 
        MIN(preco_centavos) as min_preco,
        MAX(preco_centavos) as max_preco
    FROM jogo 
    WHERE status = 'publicado' AND preco_centavos > 0
")->fetch();

// ============================================
// VERIFICAR FILTROS ATIVOS
// ============================================
$filtros_ativos = [];
if (!empty($categoria)) {
    $cat_nome = array_filter($categorias, fn($c) => $c['slug'] === $categoria);
    $cat_nome = reset($cat_nome);
    $filtros_ativos[] = ['tipo' => 'categoria', 'valor' => $categoria, 'label' => $cat_nome['nome'] ?? $categoria];
}
if (!empty($plataforma)) {
    $plat_nome = array_filter($plataformas, fn($p) => $p['slug'] === $plataforma);
    $plat_nome = reset($plat_nome);
    $filtros_ativos[] = ['tipo' => 'plataforma', 'valor' => $plataforma, 'label' => $plat_nome['nome'] ?? $plataforma];
}
if ($promocao) $filtros_ativos[] = ['tipo' => 'promocao', 'valor' => 1, 'label' => 'Em Promoção'];
if ($gratuito) $filtros_ativos[] = ['tipo' => 'gratuito', 'valor' => 1, 'label' => 'Gratuitos'];
if ($preco_min !== null) $filtros_ativos[] = ['tipo' => 'preco_min', 'valor' => $preco_min, 'label' => 'Min: R$ ' . number_format($preco_min, 2, ',', '.')];
if ($preco_max !== null) $filtros_ativos[] = ['tipo' => 'preco_max', 'valor' => $preco_max, 'label' => 'Max: R$ ' . number_format($preco_max, 2, ',', '.')];
if ($nota_min > 0) $filtros_ativos[] = ['tipo' => 'nota_min', 'valor' => $nota_min, 'label' => $nota_min . '+ estrelas'];

$page_title = !empty($q) ? "Busca: $q - " . SITE_NAME : "Explorar Jogos - " . SITE_NAME;
$page_description = "Encontre os melhores jogos indies. " . $total_jogos . " jogos disponíveis.";

require_once '../components/game-card.php';
require_once '../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/busca.css">

<div class="search-page">
    <!-- ============================================
         SEARCH HERO
         ============================================ -->
    <section class="search-hero">
        <div class="container">
            <div class="search-hero-content">
                <h1 class="search-title">
                    <?php if (!empty($q)): ?>
                        <span class="search-label">Resultados para</span>
                        "<?php echo sanitize($q); ?>"
                    <?php else: ?>
                        Explorar Jogos
                    <?php endif; ?>
                </h1>
                
                <!-- Search Bar Principal -->
                <form class="search-main-form" action="" method="GET" id="searchForm">
                    <div class="search-input-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" 
                               name="q" 
                               id="searchInput"
                               placeholder="Buscar por título, desenvolvedor ou tag..." 
                               value="<?php echo sanitize($q); ?>"
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

                <!-- Tags Populares -->
                <?php if (empty($q) && !empty($tags_populares)): ?>
                <div class="search-suggestions">
                    <span class="suggestions-label">Popular:</span>
                    <?php foreach (array_slice($tags_populares, 0, 6) as $tag): ?>
                        <a href="?q=<?php echo urlencode($tag['nome']); ?>" class="suggestion-tag">
                            <?php echo sanitize($tag['nome']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- ============================================
         MAIN CONTENT
         ============================================ -->
    <div class="container">
        <div class="search-content">
            <!-- Sidebar Filters (Desktop) -->
            <aside class="search-sidebar" id="filtersSidebar">
                <div class="sidebar-header">
                    <h2><i class="fas fa-sliders-h"></i> Filtros</h2>
                    <?php if (!empty($filtros_ativos)): ?>
                        <a href="<?php echo SITE_URL; ?>/pages/busca.php<?php echo !empty($q) ? '?q=' . urlencode($q) : ''; ?>" class="clear-filters">
                            Limpar
                        </a>
                    <?php endif; ?>
                </div>

                <form method="GET" action="" id="filtersForm">
                    <?php if (!empty($q)): ?>
                        <input type="hidden" name="q" value="<?php echo sanitize($q); ?>">
                    <?php endif; ?>

                    <!-- Plataformas -->
                    <div class="filter-group">
                        <button type="button" class="filter-header" onclick="toggleFilter(this)">
                            <span><i class="fas fa-desktop"></i> Plataforma</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="filter-content">
                            <?php foreach ($plataformas as $plat): ?>
                            <label class="filter-checkbox">
                                <input type="radio" 
                                       name="plataforma" 
                                       value="<?php echo $plat['slug']; ?>"
                                       <?php echo $plataforma === $plat['slug'] ? 'checked' : ''; ?>
                                       onchange="this.form.submit()">
                                <span class="checkbox-custom"></span>
                                <span class="filter-label">
                                    <i class="<?php echo $plat['icone'] ?? 'fas fa-gamepad'; ?>"></i>
                                    <?php echo sanitize($plat['nome']); ?>
                                </span>
                                <span class="filter-count"><?php echo $plat['total_jogos']; ?></span>
                            </label>
                            <?php endforeach; ?>
                            <?php if (!empty($plataforma)): ?>
                            <label class="filter-checkbox">
                                <input type="radio" name="plataforma" value="" onchange="this.form.submit()">
                                <span class="checkbox-custom"></span>
                                <span class="filter-label">Todas as plataformas</span>
                            </label>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Categorias -->
                    <div class="filter-group">
                        <button type="button" class="filter-header" onclick="toggleFilter(this)">
                            <span><i class="fas fa-folder"></i> Categoria</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="filter-content">
                            <?php foreach ($categorias as $cat): ?>
                            <label class="filter-checkbox">
                                <input type="radio" 
                                       name="categoria" 
                                       value="<?php echo $cat['slug']; ?>"
                                       <?php echo $categoria === $cat['slug'] ? 'checked' : ''; ?>
                                       onchange="this.form.submit()">
                                <span class="checkbox-custom"></span>
                                <span class="filter-label"><?php echo sanitize($cat['nome']); ?></span>
                                <span class="filter-count"><?php echo $cat['total_jogos']; ?></span>
                            </label>
                            <?php endforeach; ?>
                            <?php if (!empty($categoria)): ?>
                            <label class="filter-checkbox">
                                <input type="radio" name="categoria" value="" onchange="this.form.submit()">
                                <span class="checkbox-custom"></span>
                                <span class="filter-label">Todas as categorias</span>
                            </label>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Preço -->
                    <div class="filter-group">
                        <button type="button" class="filter-header" onclick="toggleFilter(this)">
                            <span><i class="fas fa-tag"></i> Preço</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="filter-content">
                            <label class="filter-checkbox">
                                <input type="checkbox" 
                                       name="gratuito" 
                                       value="1"
                                       <?php echo $gratuito ? 'checked' : ''; ?>
                                       onchange="this.form.submit()">
                                <span class="checkbox-custom"></span>
                                <span class="filter-label">Gratuitos</span>
                            </label>
                            
                            <label class="filter-checkbox">
                                <input type="checkbox" 
                                       name="promocao" 
                                       value="1"
                                       <?php echo $promocao ? 'checked' : ''; ?>
                                       onchange="this.form.submit()">
                                <span class="checkbox-custom"></span>
                                <span class="filter-label">
                                    <i class="fas fa-fire" style="color: var(--accent);"></i>
                                    Em Promoção
                                </span>
                            </label>

                            <div class="price-range">
                                <div class="price-inputs">
                                    <div class="price-field">
                                        <span>R$</span>
                                        <input type="number" 
                                               name="preco_min" 
                                               placeholder="Min"
                                               value="<?php echo $preco_min; ?>"
                                               min="0"
                                               step="0.01">
                                    </div>
                                    <span class="price-separator">—</span>
                                    <div class="price-field">
                                        <span>R$</span>
                                        <input type="number" 
                                               name="preco_max" 
                                               placeholder="Max"
                                               value="<?php echo $preco_max; ?>"
                                               min="0"
                                               step="0.01">
                                    </div>
                                </div>
                                <button type="submit" class="price-apply">Aplicar</button>
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
                            <?php foreach ([4, 3, 2, 1] as $nota): ?>
                            <label class="filter-checkbox filter-rating">
                                <input type="radio" 
                                       name="nota_min" 
                                       value="<?php echo $nota; ?>"
                                       <?php echo $nota_min == $nota ? 'checked' : ''; ?>
                                       onchange="this.form.submit()">
                                <span class="checkbox-custom"></span>
                                <span class="filter-label">
                                    <span class="stars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?php echo $i <= $nota ? 'filled' : ''; ?>"></i>
                                        <?php endfor; ?>
                                    </span>
                                    <span>ou mais</span>
                                </span>
                            </label>
                            <?php endforeach; ?>
                            <?php if ($nota_min > 0): ?>
                            <label class="filter-checkbox">
                                <input type="radio" name="nota_min" value="0" onchange="this.form.submit()">
                                <span class="checkbox-custom"></span>
                                <span class="filter-label">Todas as avaliações</span>
                            </label>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </aside>

            <!-- Results Area -->
            <main class="search-results">
                <!-- Results Header -->
                <div class="results-header">
                    <div class="results-info">
                        <span class="results-count">
                            <strong><?php echo number_format($total_jogos, 0, ',', '.'); ?></strong> 
                            jogo<?php echo $total_jogos != 1 ? 's' : ''; ?> encontrado<?php echo $total_jogos != 1 ? 's' : ''; ?>
                        </span>
                        
                        <!-- Filtros Ativos -->
                        <?php if (!empty($filtros_ativos)): ?>
                        <div class="active-filters">
                            <?php foreach ($filtros_ativos as $filtro): ?>
                                <span class="filter-chip">
                                    <?php echo sanitize($filtro['label']); ?>
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
                        <!-- Mobile Filter Toggle -->
                        <button class="filter-toggle-btn" onclick="openFiltersSheet()">
                            <i class="fas fa-sliders-h"></i>
                            Filtros
                            <?php if (!empty($filtros_ativos)): ?>
                                <span class="filter-badge"><?php echo count($filtros_ativos); ?></span>
                            <?php endif; ?>
                        </button>

                        <!-- Sort -->
                        <div class="sort-wrapper">
                            <select class="sort-select" onchange="updateSort(this.value)">
                                <option value="relevancia" <?php echo $ordem === 'relevancia' ? 'selected' : ''; ?>>Mais Relevantes</option>
                                <option value="recente" <?php echo $ordem === 'recente' ? 'selected' : ''; ?>>Mais Recentes</option>
                                <option value="vendas" <?php echo $ordem === 'vendas' ? 'selected' : ''; ?>>Mais Vendidos</option>
                                <option value="nota" <?php echo $ordem === 'nota' ? 'selected' : ''; ?>>Melhor Avaliados</option>
                                <option value="preco_asc" <?php echo $ordem === 'preco_asc' ? 'selected' : ''; ?>>Menor Preço</option>
                                <option value="preco_desc" <?php echo $ordem === 'preco_desc' ? 'selected' : ''; ?>>Maior Preço</option>
                                <?php if ($promocao): ?>
                                <option value="desconto" <?php echo $ordem === 'desconto' ? 'selected' : ''; ?>>Maior Desconto</option>
                                <?php endif; ?>
                                <option value="titulo" <?php echo $ordem === 'titulo' ? 'selected' : ''; ?>>A-Z</option>
                            </select>
                        </div>

                        <!-- View Toggle -->
                        <div class="view-toggle">
                            <button class="view-btn active" data-view="grid" onclick="setView('grid')">
                                <i class="fas fa-th-large"></i>
                            </button>
                            <button class="view-btn" data-view="list" onclick="setView('list')">
                                <i class="fas fa-list"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Results Grid -->
                <?php if (count($jogos) > 0): ?>
                    <div class="results-grid" id="resultsGrid">
                        <?php foreach ($jogos as $jogo): ?>
                            <?php renderGameCard($jogo, $pdo, $user_id); ?>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_paginas > 1): ?>
                    <nav class="pagination" aria-label="Navegação de páginas">
                        <?php
                        $params_base = $_GET;
                        unset($params_base['pagina']);
                        $query_base = http_build_query($params_base);
                        $query_prefix = !empty($query_base) ? $query_base . '&' : '';
                        ?>
                        
                        <!-- Previous -->
                        <?php if ($pagina > 1): ?>
                            <a href="?<?php echo $query_prefix; ?>pagina=<?php echo $pagina - 1; ?>" class="page-btn page-prev">
                                <i class="fas fa-chevron-left"></i>
                                <span>Anterior</span>
                            </a>
                        <?php endif; ?>

                        <!-- Page Numbers -->
                        <div class="page-numbers">
                            <?php
                            $range = 2;
                            $start = max(1, $pagina - $range);
                            $end = min($total_paginas, $pagina + $range);

                            if ($start > 1): ?>
                                <a href="?<?php echo $query_prefix; ?>pagina=1" class="page-num">1</a>
                                <?php if ($start > 2): ?>
                                    <span class="page-ellipsis">...</span>
                                <?php endif; ?>
                            <?php endif;

                            for ($i = $start; $i <= $end; $i++): ?>
                                <?php if ($i == $pagina): ?>
                                    <span class="page-num active"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?<?php echo $query_prefix; ?>pagina=<?php echo $i; ?>" class="page-num"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor;

                            if ($end < $total_paginas): ?>
                                <?php if ($end < $total_paginas - 1): ?>
                                    <span class="page-ellipsis">...</span>
                                <?php endif; ?>
                                <a href="?<?php echo $query_prefix; ?>pagina=<?php echo $total_paginas; ?>" class="page-num"><?php echo $total_paginas; ?></a>
                            <?php endif; ?>
                        </div>

                        <!-- Next -->
                        <?php if ($pagina < $total_paginas): ?>
                            <a href="?<?php echo $query_prefix; ?>pagina=<?php echo $pagina + 1; ?>" class="page-btn page-next">
                                <span>Próximo</span>
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </nav>

                    <div class="pagination-info">
                        Mostrando <?php echo $offset + 1; ?>-<?php echo min($offset + $por_pagina, $total_jogos); ?> de <?php echo $total_jogos; ?> jogos
                    </div>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- Empty State -->
                    <div class="empty-results">
                        <div class="empty-icon">
                            <i class="fas fa-ghost"></i>
                        </div>
                        <h2>Nenhum jogo encontrado</h2>
                        <p>
                            <?php if (!empty($q)): ?>
                                Não encontramos jogos para "<?php echo sanitize($q); ?>" com os filtros selecionados.
                            <?php else: ?>
                                Não encontramos jogos com os filtros selecionados.
                            <?php endif; ?>
                        </p>
                        <div class="empty-actions">
                            <?php if (!empty($filtros_ativos)): ?>
                                <a href="<?php echo SITE_URL; ?>/pages/busca.php<?php echo !empty($q) ? '?q=' . urlencode($q) : ''; ?>" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Limpar Filtros
                                </a>
                            <?php endif; ?>
                            <a href="<?php echo SITE_URL; ?>/pages/busca.php" class="btn btn-outline">
                                <i class="fas fa-compass"></i> Explorar Tudo
                            </a>
                        </div>

                        <!-- Sugestões -->
                        <?php if (!empty($tags_populares)): ?>
                        <div class="empty-suggestions">
                            <p>Tente buscar por:</p>
                            <div class="suggestion-tags">
                                <?php foreach (array_slice($tags_populares, 0, 8) as $tag): ?>
                                    <a href="?q=<?php echo urlencode($tag['nome']); ?>" class="suggestion-tag">
                                        <?php echo sanitize($tag['nome']); ?>
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

<!-- Mobile Filters Bottom Sheet -->
<div class="filters-sheet-overlay" id="filtersSheetOverlay" onclick="closeFiltersSheet()"></div>
<div class="filters-sheet" id="filtersSheet">
    <div class="filters-sheet-header">
        <h3>Filtros</h3>
        <button onclick="closeFiltersSheet()">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="filters-sheet-content" id="filtersSheetContent">
        <!-- Conteúdo será copiado via JS -->
    </div>
    <div class="filters-sheet-footer">
        <a href="<?php echo SITE_URL; ?>/pages/busca.php<?php echo !empty($q) ? '?q=' . urlencode($q) : ''; ?>" class="btn btn-outline">
            Limpar Tudo
        </a>
        <button class="btn btn-primary" onclick="closeFiltersSheet()">
            Ver <?php echo $total_jogos; ?> Resultados
        </button>
    </div>
</div>

<script>
// ============================================
// SEARCH PAGE JAVASCRIPT
// ============================================

// Toggle filter sections
function toggleFilter(header) {
    const group = header.closest('.filter-group');
    group.classList.toggle('collapsed');
}

// Update sort
function updateSort(value) {
    const url = new URL(window.location);
    url.searchParams.set('ordem', value);
    url.searchParams.delete('pagina');
    window.location = url;
}

// Clear search
function clearSearch() {
    document.getElementById('searchInput').value = '';
    document.getElementById('searchForm').submit();
}

// View toggle
function setView(view) {
    const grid = document.getElementById('resultsGrid');
    const buttons = document.querySelectorAll('.view-btn');
    
    buttons.forEach(btn => btn.classList.remove('active'));
    document.querySelector(`[data-view="${view}"]`).classList.add('active');
    
    if (view === 'list') {
        grid.classList.add('list-view');
    } else {
        grid.classList.remove('list-view');
    }
    
    localStorage.setItem('searchView', view);
}

// Restore view preference
document.addEventListener('DOMContentLoaded', () => {
    const savedView = localStorage.getItem('searchView');
    if (savedView) {
        setView(savedView);
    }
});

// Mobile Filters Sheet
function openFiltersSheet() {
    const sidebar = document.getElementById('filtersSidebar');
    const sheetContent = document.getElementById('filtersSheetContent');
    
    // Clone filters content
    sheetContent.innerHTML = sidebar.querySelector('form').outerHTML;
    
    document.getElementById('filtersSheetOverlay').classList.add('active');
    document.getElementById('filtersSheet').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeFiltersSheet() {
    document.getElementById('filtersSheetOverlay').classList.remove('active');
    document.getElementById('filtersSheet').classList.remove('active');
    document.body.style.overflow = '';
}

// Search suggestions (optional enhancement)
let searchTimeout;
const searchInput = document.getElementById('searchInput');

if (searchInput) {
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const value = this.value.trim();
        
        if (value.length >= 2) {
            searchTimeout = setTimeout(() => {
                // Could implement live search suggestions here
            }, 300);
        }
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>