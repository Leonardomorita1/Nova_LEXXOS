<?php
// user/biblioteca.php - Biblioteca Redesenhada
require_once '../config/config.php';
require_once '../config/database.php';

requireLogin();

$database = new Database();
$pdo = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Filtros
$filtro = $_GET['filtro'] ?? 'todos';
$ordem = $_GET['ordem'] ?? 'recente';
$busca = $_GET['q'] ?? '';

// Query base
$where = "WHERE b.usuario_id = ?";
$params = [$user_id];

if (!empty($busca)) {
    $where .= " AND j.titulo LIKE ?";
    $params[] = "%{$busca}%";
}

// Ordenação
$orderBy = match($ordem) {
    'nome' => 'j.titulo ASC',
    'nota' => 'j.nota_media DESC',
    default => 'b.adicionado_em DESC'
};

// Buscar jogos da biblioteca
$stmt = $pdo->prepare("
    SELECT b.*, j.*, d.nome_estudio, d.slug as dev_slug,
           GROUP_CONCAT(DISTINCT c.nome) as categorias,
           GROUP_CONCAT(DISTINCT t.nome) as tags
    FROM biblioteca b
    INNER JOIN jogo j ON b.jogo_id = j.id
    LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id
    LEFT JOIN jogo_categoria jc ON j.id = jc.jogo_id
    LEFT JOIN categoria c ON jc.categoria_id = c.id
    LEFT JOIN jogo_tag jt ON j.id = jt.jogo_id
    LEFT JOIN tag t ON jt.tag_id = t.id
    {$where}
    GROUP BY b.id
    ORDER BY {$orderBy}
");
$stmt->execute($params);
$jogos = $stmt->fetchAll();

// Estatísticas
$total_jogos = count($jogos);
$valor_total = 0;
$jogos_gratis = 0;
$categorias_unicas = [];

foreach ($jogos as $jogo) {
    $preco = $jogo['preco_centavos'] ?? 0;
    $valor_total += $preco;
    if ($preco == 0) $jogos_gratis++;
    
    if (!empty($jogo['categorias'])) {
        foreach (explode(',', $jogo['categorias']) as $cat) {
            $cat = trim($cat);
            $categorias_unicas[$cat] = ($categorias_unicas[$cat] ?? 0) + 1;
        }
    }
}

arsort($categorias_unicas);
$top_categorias = array_slice($categorias_unicas, 0, 5, true);

$page_title = 'Minha Biblioteca - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<style>
/* ===========================================
   BIBLIOTECA - ESTILOS PRINCIPAIS
   =========================================== */
.biblioteca-page {
    padding: 0 0 80px;
    min-height: 100vh;
}

/* ===========================================
   HERO SECTION
   =========================================== */
.biblioteca-hero {
    background: linear-gradient(135deg, var(--bg-primary) 0%, #0d1f3c 50%, var(--bg-secondary) 100%);
    padding: 60px 0 100px;
    position: relative;
    overflow: hidden;
    margin-top: -20px;
}

.biblioteca-hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%239C92AC' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    opacity: 0.5;
}

.biblioteca-hero::after {
    content: '';
    position: absolute;
    bottom: -50px;
    left: 0;
    right: 0;
    height: 100px;
    background: var(--bg-primary);
    clip-path: ellipse(70% 100% at 50% 100%);
}

.hero-content {
    position: relative;
    z-index: 2;
}

.hero-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 40px;
    flex-wrap: wrap;
    gap: 20px;
}

.hero-title-section h1 {
    font-size: 3rem;
    font-weight: 800;
    color: #fff;
    margin: 0 0 10px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.hero-title-section h1 i {
    color: var(--accent);
}

.hero-subtitle {
    color: rgba(255, 255, 255, 0.6);
    font-size: 1.1rem;
}

.hero-actions {
    display: flex;
    gap: 12px;
}

.hero-btn {
    padding: 12px 24px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    border: none;
}

.hero-btn-primary {
    background: var(--accent);
    color: #fff;
}

.hero-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(0, 174, 255, 0.4);
    filter: brightness(1.1);
}

.hero-btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    backdrop-filter: blur(10px);
}

.hero-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.2);
}

/* ===========================================
   STATS CARDS
   =========================================== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}

.stat-card {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    padding: 25px;
    position: relative;
    overflow: hidden;
    transition: all 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    border-color: rgba(255, 255, 255, 0.2);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 100px;
    height: 100px;
    border-radius: 50%;
    opacity: 0.1;
    transform: translate(30%, -30%);
}

.stat-card:nth-child(1)::before { background: var(--accent); }
.stat-card:nth-child(2)::before { background: var(--success); }
.stat-card:nth-child(3)::before { background: var(--warning); }
.stat-card:nth-child(4)::before { background: var(--danger); }

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    margin-bottom: 15px;
}

.stat-card:nth-child(1) .stat-icon { background: rgba(0, 174, 255, 0.2); color: var(--accent); }
.stat-card:nth-child(2) .stat-icon { background: rgba(40, 167, 69, 0.2); color: var(--success); }
.stat-card:nth-child(3) .stat-icon { background: rgba(255, 193, 7, 0.2); color: var(--warning); }
.stat-card:nth-child(4) .stat-icon { background: rgba(220, 53, 69, 0.2); color: var(--danger); }

.stat-value {
    font-size: 2rem;
    font-weight: 800;
    color: #fff;
    line-height: 1;
    margin-bottom: 5px;
}

.stat-label {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.85rem;
}

/* ===========================================
   CONTENT SECTION
   =========================================== */
.biblioteca-content {
    margin-top: -30px;
    position: relative;
    z-index: 10;
}

.content-layout {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 30px;
}

/* ===========================================
   SIDEBAR
   =========================================== */
.biblioteca-sidebar {
    position: sticky;
    top: 100px;
    height: fit-content;
}

.sidebar-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 20px;
}

.sidebar-title {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.sidebar-title i {
    color: var(--accent);
}

/* Search */
.search-box {
    position: relative;
}

.search-box input {
    width: 100%;
    padding: 14px 18px 14px 45px;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: 12px;
    color: var(--text-primary);
    font-size: 0.9rem;
    transition: all 0.3s;
}

.search-box input:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(0, 174, 255, 0.1);
}

.search-box i {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-secondary);
}

/* Filter Links */
.filter-links {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.filter-link {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 15px;
    border-radius: 10px;
    color: var(--text-secondary);
    text-decoration: none;
    transition: all 0.2s;
    font-size: 0.9rem;
}

.filter-link:hover {
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-primary);
}

.filter-link.active {
    background: rgba(0, 174, 255, 0.1);
    color: var(--accent);
}

.filter-link i {
    width: 20px;
}

.filter-count {
    background: rgba(255, 255, 255, 0.1);
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.75rem;
}

/* Categories Cloud */
.categories-cloud {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.category-tag {
    padding: 8px 14px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 20px;
    font-size: 0.8rem;
    color: var(--text-secondary);
    text-decoration: none;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.category-tag:hover {
    background: rgba(0, 174, 255, 0.1);
    border-color: var(--accent);
    color: var(--accent);
}

.category-tag span {
    opacity: 0.5;
    font-size: 0.7rem;
}

/* ===========================================
   MAIN CONTENT
   =========================================== */
.biblioteca-main {
    min-height: 500px;
}

.content-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border);
    flex-wrap: wrap;
    gap: 15px;
}

.content-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 10px;
}

.content-title .count {
    background: var(--accent);
    color: #fff;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 700;
}

.sort-controls {
    display: flex;
    gap: 10px;
    align-items: center;
}

.sort-select {
    padding: 10px 35px 10px 15px;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 10px;
    color: var(--text-primary);
    font-size: 0.85rem;
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%237e7e7e' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
}

.sort-select:focus {
    outline: none;
    border-color: var(--accent);
}

.view-modes {
    display: flex;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 10px;
    overflow: hidden;
}

.view-mode {
    padding: 10px 14px;
    background: transparent;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.2s;
}

.view-mode:hover {
    color: var(--text-primary);
}

.view-mode.active {
    background: var(--accent);
    color: #fff;
}

/* ===========================================
   GAMES GRID
   =========================================== */
.games-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 20px;
}

.games-grid.list-view {
    grid-template-columns: 1fr;
    gap: 15px;
}

/* ===========================================
   GAME CARD BIBLIOTECA
   =========================================== */
.lib-game-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.3s;
    position: relative;
}

.lib-game-card:hover {
    transform: translateY(-8px);
    border-color: var(--accent);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
}

.lib-game-card .card-image {
    position: relative;
    aspect-ratio: 3/4;
    overflow: hidden;
}

.lib-game-card .card-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s;
}

.lib-game-card:hover .card-image img {
    transform: scale(1.1);
}

.card-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(0deg, rgba(0,0,0,0.9) 0%, transparent 50%);
    opacity: 0;
    transition: opacity 0.3s;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    padding: 20px;
}

.lib-game-card:hover .card-overlay {
    opacity: 1;
}

.card-actions {
    display: flex;
    gap: 10px;
}

.card-action-btn {
    flex: 1;
    padding: 12px;
    border-radius: 10px;
    border: none;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-decoration: none;
}

.card-action-btn.primary {
    background: var(--accent);
    color: #fff;
}

.card-action-btn.primary:hover {
    filter: brightness(1.15);
}

.card-action-btn.secondary {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    backdrop-filter: blur(10px);
}

.card-action-btn.secondary:hover {
    background: rgba(255, 255, 255, 0.2);
}

.owned-badge {
    position: absolute;
    top: 12px;
    left: 12px;
    background: var(--success);
    color: #fff;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 6px;
    z-index: 5;
}

.card-info {
    padding: 18px;
}

.card-info h3 {
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 6px;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.card-info h3 a {
    color: var(--text-primary);
    text-decoration: none;
    transition: color 0.2s;
}

.card-info h3 a:hover {
    color: var(--accent);
}

.card-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 10px;
}

.card-dev {
    font-size: 0.8rem;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 6px;
}

.card-rating {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 0.8rem;
}

.card-rating i {
    color: var(--warning);
    font-size: 0.7rem;
}

.card-rating span {
    color: var(--text-secondary);
}

.card-added {
    font-size: 0.75rem;
    color: var(--text-secondary);
    margin-top: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}

/* ===========================================
   LIST VIEW CARD
   =========================================== */
.games-grid.list-view .lib-game-card {
    display: grid;
    grid-template-columns: 120px 1fr auto;
    gap: 20px;
    padding: 15px;
}

.games-grid.list-view .card-image {
    aspect-ratio: 3/4;
    border-radius: 10px;
}

.games-grid.list-view .card-overlay {
    display: none;
}

.games-grid.list-view .card-info {
    padding: 0;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.games-grid.list-view .card-info h3 {
    font-size: 1.1rem;
    -webkit-line-clamp: 1;
}

.list-actions {
    display: none;
    align-items: center;
    gap: 10px;
}

.games-grid.list-view .list-actions {
    display: flex;
}

/* ===========================================
   EMPTY STATE
   =========================================== */
.empty-biblioteca {
    text-align: center;
    padding: 80px 40px;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 20px;
}

.empty-icon {
    width: 120px;
    height: 120px;
    margin: 0 auto 30px;
    background: rgba(0, 174, 255, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.empty-icon i {
    font-size: 50px;
    color: var(--accent);
    opacity: 0.5;
}

.empty-biblioteca h2 {
    font-size: 1.8rem;
    margin-bottom: 10px;
    color: var(--text-primary);
}

.empty-biblioteca p {
    color: var(--text-secondary);
    font-size: 1.1rem;
    margin-bottom: 30px;
    max-width: 400px;
    margin-left: auto;
    margin-right: auto;
}

.empty-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 16px 32px;
    background: var(--accent);
    color: #fff;
    border-radius: 14px;
    text-decoration: none;
    font-weight: 700;
    font-size: 1rem;
    transition: all 0.3s;
}

.empty-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 40px rgba(0, 174, 255, 0.4);
    filter: brightness(1.1);
}

/* ===========================================
   RESPONSIVE
   =========================================== */
@media (max-width: 1024px) {
    .content-layout {
        grid-template-columns: 1fr;
    }
    
    .biblioteca-sidebar {
        position: static;
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .hero-title-section h1 {
        font-size: 2rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .biblioteca-sidebar {
        grid-template-columns: 1fr;
    }
    
    .games-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
    
    .content-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .sort-controls {
        justify-content: space-between;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .hero-actions {
        flex-direction: column;
        width: 100%;
    }
    
    .hero-btn {
        justify-content: center;
    }
    
    .games-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- ===========================================
     HERO SECTION
     =========================================== -->
<section class="biblioteca-hero">
    <div class="container">
        <div class="hero-content">
            <div class="hero-top">
                <div class="hero-title-section">
                    <h1><i class="fas fa-book-open"></i> Minha Biblioteca</h1>
                    <p class="hero-subtitle">Sua coleção pessoal de jogos indies</p>
                </div>
                
                <div class="hero-actions">
                    <a href="<?php echo SITE_URL; ?>/pages/loja.php" class="hero-btn hero-btn-primary">
                        <i class="fas fa-plus"></i> Adicionar Jogos
                    </a>
                    <a href="<?php echo SITE_URL; ?>/user/lista-desejos.php" class="hero-btn hero-btn-secondary">
                        <i class="fas fa-heart"></i> Lista de Desejos
                    </a>
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-gamepad"></i></div>
                    <div class="stat-value"><?php echo $total_jogos; ?></div>
                    <div class="stat-label">Jogos na Biblioteca</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-gift"></i></div>
                    <div class="stat-value"><?php echo $jogos_gratis; ?></div>
                    <div class="stat-label">Jogos Gratuitos</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-wallet"></i></div>
                    <div class="stat-value"><?php echo formatPrice($valor_total); ?></div>
                    <div class="stat-label">Valor Total Investido</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-value">
                        <?php 
                        if ($total_jogos > 0) {
                            echo date('d/m', strtotime($jogos[0]['adicionado_em']));
                        } else {
                            echo '-';
                        }
                        ?>
                    </div>
                    <div class="stat-label">Última Aquisição</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===========================================
     CONTENT SECTION
     =========================================== -->
<div class="container">
    <div class="biblioteca-content">
        <div class="content-layout">
            
            <!-- Sidebar -->
            <aside class="biblioteca-sidebar">
                <!-- Search -->
                <div class="sidebar-card">
                    <div class="sidebar-title">
                        <i class="fas fa-search"></i> Buscar
                    </div>
                    <form method="GET" class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" 
                               name="q" 
                               placeholder="Buscar na biblioteca..."
                               value="<?php echo sanitize($busca); ?>">
                    </form>
                </div>
                
                <!-- Filters -->
                <div class="sidebar-card">
                    <div class="sidebar-title">
                        <i class="fas fa-filter"></i> Filtrar
                    </div>
                    <div class="filter-links">
                        <a href="?filtro=todos" class="filter-link <?php echo $filtro == 'todos' ? 'active' : ''; ?>">
                            <span><i class="fas fa-layer-group"></i> Todos os Jogos</span>
                            <span class="filter-count"><?php echo $total_jogos; ?></span>
                        </a>
                        <a href="?filtro=recentes" class="filter-link <?php echo $filtro == 'recentes' ? 'active' : ''; ?>">
                            <span><i class="fas fa-clock"></i> Adicionados Recentemente</span>
                        </a>
                        <a href="?filtro=gratuitos" class="filter-link <?php echo $filtro == 'gratuitos' ? 'active' : ''; ?>">
                            <span><i class="fas fa-gift"></i> Gratuitos</span>
                            <span class="filter-count"><?php echo $jogos_gratis; ?></span>
                        </a>
                    </div>
                </div>
                
                <!-- Categories -->
                <?php if (!empty($top_categorias)): ?>
                <div class="sidebar-card">
                    <div class="sidebar-title">
                        <i class="fas fa-tags"></i> Categorias
                    </div>
                    <div class="categories-cloud">
                        <?php foreach ($top_categorias as $cat => $count): ?>
                        <a href="<?php echo SITE_URL; ?>/pages/categoria.php?slug=<?php echo urlencode($cat); ?>" class="category-tag">
                            <?php echo sanitize($cat); ?>
                            <span><?php echo $count; ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </aside>
            
            <!-- Main Content -->
            <main class="biblioteca-main">
                <?php if ($total_jogos > 0): ?>
                    <div class="content-header">
                        <h2 class="content-title">
                            Seus Jogos
                            <span class="count"><?php echo $total_jogos; ?></span>
                        </h2>
                        
                        <div class="sort-controls">
                            <form method="GET" id="sortForm">
                                <input type="hidden" name="filtro" value="<?php echo $filtro; ?>">
                                <input type="hidden" name="q" value="<?php echo sanitize($busca); ?>">
                                <select name="ordem" class="sort-select" onchange="this.form.submit()">
                                    <option value="recente" <?php echo $ordem == 'recente' ? 'selected' : ''; ?>>
                                        Mais Recentes
                                    </option>
                                    <option value="nome" <?php echo $ordem == 'nome' ? 'selected' : ''; ?>>
                                        Nome (A-Z)
                                    </option>
                                    <option value="nota" <?php echo $ordem == 'nota' ? 'selected' : ''; ?>>
                                        Melhor Avaliados
                                    </option>
                                </select>
                            </form>
                            
                            <div class="view-modes">
                                <button class="view-mode active" onclick="setView('grid')" title="Grade">
                                    <i class="fas fa-th"></i>
                                </button>
                                <button class="view-mode" onclick="setView('list')" title="Lista">
                                    <i class="fas fa-list"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="games-grid" id="gamesGrid">
                        <?php foreach ($jogos as $jogo): ?>
                        <article class="lib-game-card">
                            <div class="card-image">
                                <span class="owned-badge">
                                    <i class="fas fa-check"></i> Na Biblioteca
                                </span>
                                <a href="<?php echo SITE_URL; ?>/pages/jogo.php?slug=<?php echo $jogo['slug']; ?>">
                                    <img src="<?php echo SITE_URL . ($jogo['imagem_capa'] ?: '/assets/images/no-image.png'); ?>" 
                                         alt="<?php echo sanitize($jogo['titulo']); ?>"
                                         loading="lazy">
                                </a>
                                <div class="card-overlay">
                                    <div class="card-actions">
                                        <a href="<?php echo SITE_URL; ?>/pages/jogo.php?slug=<?php echo $jogo['slug']; ?>" 
                                           class="card-action-btn primary">
                                            <i class="fas fa-play"></i> Ver Jogo
                                        </a>
                                        <a href="<?php echo SITE_URL; ?>/pages/jogo.php?slug=<?php echo $jogo['slug']; ?>#download" 
                                           class="card-action-btn secondary">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card-info">
                                <h3>
                                    <a href="<?php echo SITE_URL; ?>/pages/jogo.php?slug=<?php echo $jogo['slug']; ?>">
                                        <?php echo sanitize($jogo['titulo']); ?>
                                    </a>
                                </h3>
                                
                                <div class="card-meta">
                                    <span class="card-dev">
                                        <i class="fas fa-user"></i>
                                        <?php echo sanitize($jogo['nome_estudio'] ?? 'Desenvolvedor'); ?>
                                    </span>
                                    
                                    <?php if (($jogo['nota_media'] ?? 0) > 0): ?>
                                    <span class="card-rating">
                                        <i class="fas fa-star"></i>
                                        <span><?php echo number_format($jogo['nota_media'], 1); ?></span>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-added">
                                    <i class="fas fa-calendar-plus"></i>
                                    Adicionado em <?php echo date('d/m/Y', strtotime($jogo['adicionado_em'])); ?>
                                </div>
                            </div>
                            
                            <!-- List View Actions -->
                            <div class="list-actions">
                                <a href="<?php echo SITE_URL; ?>/pages/jogo.php?slug=<?php echo $jogo['slug']; ?>" 
                                   class="card-action-btn primary">
                                    <i class="fas fa-play"></i> Ver Jogo
                                </a>
                                <a href="<?php echo SITE_URL; ?>/pages/jogo.php?slug=<?php echo $jogo['slug']; ?>#download" 
                                   class="card-action-btn secondary">
                                    <i class="fas fa-download"></i> Download
                                </a>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </div>
                    
                <?php else: ?>
                    <div class="empty-biblioteca">
                        <div class="empty-icon">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <h2>Sua biblioteca está vazia</h2>
                        <p>Comece a construir sua coleção de jogos indies incríveis!</p>
                        <a href="<?php echo SITE_URL; ?>/pages/loja.php" class="empty-btn">
                            <i class="fas fa-gamepad"></i> Explorar Jogos
                        </a>
                    </div>
                <?php endif; ?>
            </main>
            
        </div>
    </div>
</div>

<script>
function setView(mode) {
    const grid = document.getElementById('gamesGrid');
    const buttons = document.querySelectorAll('.view-mode');
    
    if (mode === 'list') {
        grid.classList.add('list-view');
        buttons[0].classList.remove('active');
        buttons[1].classList.add('active');
    } else {
        grid.classList.remove('list-view');
        buttons[0].classList.add('active');
        buttons[1].classList.remove('active');
    }
    
    localStorage.setItem('bibliotecaView', mode);
}

// Restore view preference
document.addEventListener('DOMContentLoaded', () => {
    const savedView = localStorage.getItem('bibliotecaView');
    if (savedView) setView(savedView);
});
</script>

<?php require_once '../includes/footer.php'; ?>