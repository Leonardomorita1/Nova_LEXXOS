<?php
// pages/busca.php
require_once '../config/config.php';
require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();

$user_id = $_SESSION['user_id'] ?? null;

// Filtros
$q = $_GET['q'] ?? '';
$categoria = $_GET['categoria'] ?? '';
$preco_min = $_GET['preco_min'] ?? 0;
$preco_max = $_GET['preco_max'] ?? 100000;
$promocao = $_GET['promocao'] ?? 0;
$ordem = $_GET['ordem'] ?? 'relevancia';
$pagina = max(1, $_GET['pagina'] ?? 1);
$por_pagina = 20;
$offset = ($pagina - 1) * $por_pagina;

// Construir query
$where = ["j.status = 'publicado'"];
$params = [];

if (!empty($q)) {
    $where[] = "(j.titulo LIKE ? OR j.descricao_curta LIKE ? OR j.descricao_completa LIKE ?)";
    $search = "%$q%";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

if (!empty($categoria)) {
    $where[] = "EXISTS (SELECT 1 FROM jogo_categoria jc INNER JOIN categoria c ON jc.categoria_id = c.id WHERE jc.jogo_id = j.id AND c.slug = ?)";
    $params[] = $categoria;
}

if ($promocao) {
    $where[] = "j.em_promocao = 1";
}

$where[] = "j.preco_centavos BETWEEN ? AND ?";
$params[] = $preco_min * 100;
$params[] = $preco_max * 100;

$where_clause = implode(' AND ', $where);

// Ordenação
$order_by = match($ordem) {
    'preco_asc' => 'j.preco_centavos ASC',
    'preco_desc' => 'j.preco_centavos DESC',
    'nota' => 'j.nota_media DESC',
    'vendas' => 'j.total_vendas DESC',
    'recente' => 'j.publicado_em DESC',
    'titulo' => 'j.titulo ASC',
    default => 'j.total_vendas DESC, j.nota_media DESC'
};

// Buscar jogos
$stmt = $pdo->prepare("
    SELECT j.*, d.nome_estudio, d.slug as dev_slug,
           GROUP_CONCAT(DISTINCT t.nome) as tags
    FROM jogo j
    LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id
    LEFT JOIN jogo_tag jt ON j.id = jt.jogo_id
    LEFT JOIN tag t ON jt.tag_id = t.id
    WHERE $where_clause
    GROUP BY j.id
    ORDER BY $order_by
    LIMIT $por_pagina OFFSET $offset
");
$stmt->execute($params);
$jogos = $stmt->fetchAll();

// Total de resultados
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT j.id) as total FROM jogo j WHERE $where_clause");
$stmt->execute($params);
$total_jogos = $stmt->fetch()['total'];
$total_paginas = ceil($total_jogos / $por_pagina);

// Buscar categorias para filtro
$categorias = $pdo->query("SELECT * FROM categoria WHERE ativa = 1 ORDER BY nome")->fetchAll();

$page_title = !empty($q) ? "Busca: $q - " . SITE_NAME : "Explorar Jogos - " . SITE_NAME;

require_once '../components/game-card.php';
require_once '../includes/header.php';
?>

<style>
.search-page {
    padding: 30px 0;
}

.search-header {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 30px;
}

.search-header h1 {
    font-size: 32px;
    margin-bottom: 10px;
}

.search-results-count {
    color: var(--text-secondary);
}

.search-layout {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 30px;
}

.search-filters {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 15px;
    padding: 25px;
    height: fit-content;
    position: sticky;
    top: 90px;
}

.filter-section {
    margin-bottom: 25px;
    padding-bottom: 25px;
    border-bottom: 1px solid var(--border);
}

.filter-section:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.filter-section h3 {
    font-size: 16px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-section h3 i {
    color: var(--accent);
}

.filter-option {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
    cursor: pointer;
}

.filter-option input[type="checkbox"],
.filter-option input[type="radio"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.filter-option label {
    cursor: pointer;
    flex: 1;
}

.price-inputs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.price-inputs input {
    width: 100%;
}

.filter-buttons {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

.search-results-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.sort-select {
    padding: 10px 15px;
    border: 1px solid var(--border);
    border-radius: 6px;
    background: var(--bg-secondary);
    color: var(--text-primary);
    cursor: pointer;
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 40px;
}

.pagination a,
.pagination span {
    padding: 10px 15px;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text-primary);
    transition: all 0.3s;
}

.pagination a:hover {
    background: var(--accent);
    color: white;
    border-color: var(--accent);
}

.pagination .active {
    background: var(--accent);
    color: white;
    border-color: var(--accent);
}

.no-results {
    text-align: center;
    padding: 60px 20px;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 15px;
}

.no-results i {
    font-size: 64px;
    color: var(--text-secondary);
    margin-bottom: 20px;
}

@media (max-width: 992px) {
    .search-layout {
        grid-template-columns: 1fr;
    }
    
    .search-filters {
        position: static;
    }
}
</style>

<div class="container">
    <div class="search-page">
        <div class="search-header">
            <h1>
                <?php if (!empty($q)): ?>
                    Resultados para "<?php echo sanitize($q); ?>"
                <?php else: ?>
                    Explorar Jogos
                <?php endif; ?>
            </h1>
            <p class="search-results-count">
                <?php echo $total_jogos; ?> jogo<?php echo $total_jogos != 1 ? 's' : ''; ?> encontrado<?php echo $total_jogos != 1 ? 's' : ''; ?>
            </p>
        </div>
        
        <div class="search-layout">
            <!-- Filtros -->
            <aside class="search-filters">
                <form method="GET" action="">
                    <?php if (!empty($q)): ?>
                        <input type="hidden" name="q" value="<?php echo sanitize($q); ?>">
                    <?php endif; ?>
                    
                    <!-- Categorias -->
                    <div class="filter-section">
                        <h3><i class="fas fa-th"></i> Categorias</h3>
                        <?php foreach ($categorias as $cat): ?>
                        <div class="filter-option">
                            <input type="radio" 
                                   name="categoria" 
                                   value="<?php echo $cat['slug']; ?>" 
                                   id="cat_<?php echo $cat['id']; ?>"
                                   <?php echo $categoria == $cat['slug'] ? 'checked' : ''; ?>>
                            <label for="cat_<?php echo $cat['id']; ?>">
                                <?php echo sanitize($cat['nome']); ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                        <?php if (!empty($categoria)): ?>
                        <div class="filter-option">
                            <input type="radio" name="categoria" value="" id="cat_all" checked>
                            <label for="cat_all">Todas</label>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Preço -->
                    <div class="filter-section">
                        <h3><i class="fas fa-tag"></i> Faixa de Preço</h3>
                        <div class="price-inputs">
                            <input type="number" 
                                   name="preco_min" 
                                   class="form-control" 
                                   placeholder="Mín"
                                   value="<?php echo $preco_min; ?>"
                                   min="0">
                            <input type="number" 
                                   name="preco_max" 
                                   class="form-control" 
                                   placeholder="Máx"
                                   value="<?php echo $preco_max; ?>"
                                   min="0">
                        </div>
                    </div>
                    
                    <!-- Promoção -->
                    <div class="filter-section">
                        <h3><i class="fas fa-fire"></i> Especiais</h3>
                        <div class="filter-option">
                            <input type="checkbox" 
                                   name="promocao" 
                                   value="1" 
                                   id="promocao"
                                   <?php echo $promocao ? 'checked' : ''; ?>>
                            <label for="promocao">Em Promoção</label>
                        </div>
                    </div>
                    
                    <div class="filter-buttons">
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                        <a href="<?php echo SITE_URL; ?>/pages/busca.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </form>
            </aside>
            
            <!-- Resultados -->
            <div class="search-results">
                <div class="search-results-header">
                    <span>
                        Página <?php echo $pagina; ?> de <?php echo max(1, $total_paginas); ?>
                    </span>
                    
                    <select class="sort-select" onchange="window.location.href='?<?php 
                        $params_url = $_GET;
                        unset($params_url['ordem']);
                        echo http_build_query($params_url);
                    ?>&ordem=' + this.value">
                        <option value="relevancia" <?php echo $ordem == 'relevancia' ? 'selected' : ''; ?>>Mais Relevantes</option>
                        <option value="recente" <?php echo $ordem == 'recente' ? 'selected' : ''; ?>>Mais Recentes</option>
                        <option value="vendas" <?php echo $ordem == 'vendas' ? 'selected' : ''; ?>>Mais Vendidos</option>
                        <option value="nota" <?php echo $ordem == 'nota' ? 'selected' : ''; ?>>Melhor Avaliados</option>
                        <option value="preco_asc" <?php echo $ordem == 'preco_asc' ? 'selected' : ''; ?>>Preço: Menor</option>
                        <option value="preco_desc" <?php echo $ordem == 'preco_desc' ? 'selected' : ''; ?>>Preço: Maior</option>
                        <option value="titulo" <?php echo $ordem == 'titulo' ? 'selected' : ''; ?>>A-Z</option>
                    </select>
                </div>
                
                <?php if (count($jogos) > 0): ?>
                    <div class="grid grid-4">
                        <?php foreach ($jogos as $jogo): ?>
                            <?php renderGameCard($jogo, $pdo, $user_id); ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Paginação -->
                    <?php if ($total_paginas > 1): ?>
                    <div class="pagination">
                        <?php if ($pagina > 1): ?>
                            <a href="?<?php 
                                $params_url = $_GET;
                                $params_url['pagina'] = $pagina - 1;
                                echo http_build_query($params_url);
                            ?>">
                                <i class="fas fa-chevron-left"></i> Anterior
                            </a>
                        <?php endif; ?>
                        
                        <?php
                        $start = max(1, $pagina - 2);
                        $end = min($total_paginas, $pagina + 2);
                        
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <?php if ($i == $pagina): ?>
                                <span class="active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?<?php 
                                    $params_url = $_GET;
                                    $params_url['pagina'] = $i;
                                    echo http_build_query($params_url);
                                ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($pagina < $total_paginas): ?>
                            <a href="?<?php 
                                $params_url = $_GET;
                                $params_url['pagina'] = $pagina + 1;
                                echo http_build_query($params_url);
                            ?>">
                                Próximo <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="no-results">
                        <i class="fas fa-search"></i>
                        <h2>Nenhum jogo encontrado</h2>
                        <p>Tente ajustar os filtros ou fazer uma nova busca.</p>
                        <a href="<?php echo SITE_URL; ?>/pages/busca.php" class="btn btn-primary" style="margin-top: 20px;">
                            <i class="fas fa-redo"></i> Limpar Filtros
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>