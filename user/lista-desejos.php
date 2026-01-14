<?php
// user/lista-desejos.php - Padronizada
require_once '../config/config.php';
require_once '../config/database.php';

requireLogin();

$database = new Database();
$pdo = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Filtros
$ordem = $_GET['ordem'] ?? 'recente';
$filtro_preco = $_GET['preco'] ?? '';

// Construir query
$orderBy = match($ordem) {
    'nome' => 'j.titulo ASC',
    'preco_asc' => 'COALESCE(j.preco_promocional_centavos, j.preco_centavos) ASC',
    'preco_desc' => 'COALESCE(j.preco_promocional_centavos, j.preco_centavos) DESC',
    'promocao' => 'j.em_promocao DESC, ld.adicionado_em DESC',
    default => 'ld.adicionado_em DESC'
};

$where = "WHERE ld.usuario_id = ? AND j.status = 'publicado'";
$params = [$user_id];

if ($filtro_preco === 'gratis') {
    $where .= " AND j.preco_centavos = 0";
} elseif ($filtro_preco === 'promocao') {
    $where .= " AND j.em_promocao = 1";
}

$stmt = $pdo->prepare("
    SELECT ld.*, j.*, d.nome_estudio, d.slug as dev_slug,
           GROUP_CONCAT(DISTINCT c.nome) as categorias,
           (SELECT id FROM carrinho WHERE usuario_id = ? AND jogo_id = j.id) as in_cart
    FROM lista_desejos ld
    INNER JOIN jogo j ON ld.jogo_id = j.id
    LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id
    LEFT JOIN jogo_categoria jc ON j.id = jc.jogo_id
    LEFT JOIN categoria c ON jc.categoria_id = c.id
    {$where}
    GROUP BY ld.id
    ORDER BY {$orderBy}
");

// Adiciona user_id no início para o subselect do carrinho
array_unshift($params, $user_id);
$stmt->execute($params);
$jogos = $stmt->fetchAll();

// Estatísticas
$total_jogos = count($jogos);
$valor_total = 0;
$valor_promocional = 0;
$jogos_promocao = 0;

foreach ($jogos as $jogo) {
    $preco_original = $jogo['preco_centavos'] ?? 0;
    $preco_atual = ($jogo['em_promocao'] && $jogo['preco_promocional_centavos']) 
        ? $jogo['preco_promocional_centavos'] 
        : $preco_original;
    
    $valor_total += $preco_original;
    $valor_promocional += $preco_atual;
    
    if ($jogo['em_promocao'] && $jogo['preco_promocional_centavos']) {
        $jogos_promocao++;
    }
}

$economia_potencial = $valor_total - $valor_promocional;

$pageTitle = 'Lista de Desejos - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<style>
/* ===========================================
   LISTA DE DESEJOS - ESTILOS PADRONIZADOS
   =========================================== */
.wishlist-page {
    padding: 0 0 80px;
    min-height: 100vh;
}

/* Hero Section */
.wishlist-hero {
    background: linear-gradient(180deg, var(--bg-secondary) 0%, var(--bg-primary) 100%);
    padding: 60px 0 80px;
    position: relative;
    border-bottom: 1px solid var(--border);
    margin-bottom: 40px;
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
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--text-primary);
    margin: 0 0 10px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.hero-title-section h1 i {
    color: var(--accent);
}

.hero-subtitle {
    color: var(--text-secondary);
    font-size: 1.1rem;
}

.hero-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}

.stat-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 25px;
    position: relative;
    overflow: hidden;
    transition: all 0.3s;
}

.stat-card:hover {
    border-color: var(--accent);
    transform: translateY(-5px);
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    margin-bottom: 15px;
    background: rgba(0, 174, 255, 0.1);
    color: var(--accent);
}

.stat-value {
    font-size: 1.8rem;
    font-weight: 800;
    color: var(--text-primary);
    line-height: 1;
    margin-bottom: 5px;
}

.stat-label {
    color: var(--text-secondary);
    font-size: 0.85rem;
    font-weight: 600;
}

/* Toolbar */
.wishlist-toolbar {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.filter-tabs {
    display: flex;
    gap: 10px;
}

.filter-tab {
    padding: 8px 16px;
    border-radius: 8px;
    color: var(--text-secondary);
    font-weight: 600;
    font-size: 0.9rem;
    text-decoration: none;
    transition: all 0.2s;
    border: 1px solid transparent;
}

.filter-tab:hover {
    background: var(--bg-primary);
    color: var(--text-primary);
}

.filter-tab.active {
    background: var(--accent);
    color: #fff;
}

/* Wishlist Item */
.wishlist-item {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 20px;
    display: grid;
    grid-template-columns: 100px 1fr auto;
    gap: 25px;
    transition: all 0.3s;
    position: relative;
    margin-bottom: 15px;
}

.wishlist-item:hover {
    border-color: var(--accent);
    transform: translateX(5px);
}

.item-image {
    border-radius: 10px;
    overflow: hidden;
    aspect-ratio: 3/4;
    position: relative;
}

.item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.sale-badge {
    position: absolute;
    top: 5px;
    right: 5px;
    background: var(--success);
    color: #fff;
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 4px;
    font-weight: 800;
}

.item-info {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 8px;
}

.item-info h3 {
    margin: 0;
    font-size: 1.2rem;
}

.item-info h3 a {
    color: var(--text-primary);
    text-decoration: none;
    transition: color 0.2s;
}

.item-info h3 a:hover {
    color: var(--accent);
}

.item-meta {
    display: flex;
    gap: 10px;
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.item-actions {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    justify-content: center;
    gap: 15px;
    min-width: 200px;
    text-align: right;
}

.price-container {
    display: flex;
    flex-direction: column;
}

.price-old {
    font-size: 0.9rem;
    text-decoration: line-through;
    color: var(--text-secondary);
}

.price-current {
    font-size: 1.4rem;
    font-weight: 800;
    color: var(--accent);
}

.price-current.sale {
    color: var(--success);
}

.item-buttons {
    display: flex;
    gap: 10px;
}

/* Responsive */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .wishlist-item {
        grid-template-columns: 80px 1fr;
        gap: 15px;
    }
    
    .item-actions {
        grid-column: 1 / -1;
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
        border-top: 1px solid var(--border);
        padding-top: 15px;
        min-width: auto;
    }
    
    .price-container {
        align-items: flex-start;
        text-align: left;
    }
}

@media (max-width: 480px) {
    .hero-actions {
        flex-direction: column;
        width: 100%;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .item-buttons {
        flex-direction: column;
        width: 100%;
    }
    
    .item-buttons .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<div class="wishlist-page">
    <!-- Hero Section -->
    <section class="wishlist-hero">
        <div class="container">
            <div class="hero-top">
                <div class="hero-title-section">
                    <h1><i class="fas fa-heart"></i> Lista de Desejos</h1>
                    <p class="hero-subtitle">Jogos que você está de olho</p>
                </div>
                
                <div class="hero-actions">
                    <a href="<?php echo SITE_URL; ?>/pages/loja.php" class="btn btn-secondary">
                        <i class="fas fa-search"></i> Explorar Loja
                    </a>
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-gamepad"></i></div>
                    <div class="stat-value"><?php echo $total_jogos; ?></div>
                    <div class="stat-label">Jogos na Lista</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-tag"></i></div>
                    <div class="stat-value"><?php echo $jogos_promocao; ?></div>
                    <div class="stat-label">Em Promoção</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-wallet"></i></div>
                    <div class="stat-value"><?php echo formatPrice($valor_promocional); ?></div>
                    <div class="stat-label">Valor Total</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-piggy-bank"></i></div>
                    <div class="stat-value"><?php echo formatPrice($economia_potencial); ?></div>
                    <div class="stat-label">Economia Potencial</div>
                </div>
            </div>
        </div>
    </section>

    <div class="container">
        <?php if ($total_jogos > 0): ?>
        
        <!-- Toolbar -->
        <div class="wishlist-toolbar">
            <div class="filter-tabs">
                <a href="?ordem=<?php echo $ordem; ?>" class="filter-tab <?php echo empty($filtro_preco) ? 'active' : ''; ?>">
                    Todos
                </a>
                <?php if ($jogos_promocao > 0): ?>
                <a href="?preco=promocao&ordem=<?php echo $ordem; ?>" class="filter-tab <?php echo $filtro_preco == 'promocao' ? 'active' : ''; ?>">
                    Em Promoção
                </a>
                <?php endif; ?>
            </div>
            
            <div class="toolbar-right">
                <form method="GET" style="display:inline-block;">
                    <?php if ($filtro_preco): ?>
                    <input type="hidden" name="preco" value="<?php echo $filtro_preco; ?>">
                    <?php endif; ?>
                    <select name="ordem" class="form-select" onchange="this.form.submit()" style="width: auto; padding-top: 8px; padding-bottom: 8px;">
                        <option value="recente" <?php echo $ordem == 'recente' ? 'selected' : ''; ?>>Recentes</option>
                        <option value="nome" <?php echo $ordem == 'nome' ? 'selected' : ''; ?>>Nome (A-Z)</option>
                        <option value="preco_asc" <?php echo $ordem == 'preco_asc' ? 'selected' : ''; ?>>Menor Preço</option>
                        <option value="promocao" <?php echo $ordem == 'promocao' ? 'selected' : ''; ?>>Promoções</option>
                    </select>
                </form>
            </div>
        </div>
        
        <!-- Games List -->
        <div class="wishlist-games">
            <?php foreach ($jogos as $jogo): ?>
            <?php
                $preco_original = $jogo['preco_centavos'] ?? 0;
                $preco_atual = ($jogo['em_promocao'] && $jogo['preco_promocional_centavos']) 
                    ? $jogo['preco_promocional_centavos'] 
                    : $preco_original;
                $tem_promocao = $jogo['em_promocao'] && $jogo['preco_promocional_centavos'];
                $percentual = $tem_promocao ? round((($preco_original - $preco_atual) / $preco_original) * 100) : 0;
                $in_cart = !empty($jogo['in_cart']);
            ?>
            <article class="wishlist-item" data-wishlist-item data-jogo-id="<?php echo $jogo['jogo_id']; ?>">
                <div class="item-image">
                    <?php if ($tem_promocao): ?>
                    <span class="sale-badge">-<?php echo $percentual; ?>%</span>
                    <?php endif; ?>
                    <a href="<?php echo SITE_URL; ?>/pages/jogo.php?slug=<?php echo $jogo['slug']; ?>">
                        <img src="<?php echo SITE_URL . ($jogo['imagem_capa'] ?: '/assets/images/no-image.png'); ?>" 
                             alt="<?php echo sanitize($jogo['titulo']); ?>"
                             loading="lazy">
                    </a>
                </div>
                
                <div class="item-info">
                    <h3>
                        <a href="<?php echo SITE_URL; ?>/pages/jogo.php?slug=<?php echo $jogo['slug']; ?>">
                            <?php echo sanitize($jogo['titulo']); ?>
                        </a>
                    </h3>
                    
                    <div class="item-meta">
                        <span><i class="fas fa-building"></i> <?php echo sanitize($jogo['nome_estudio'] ?? 'Indie'); ?></span>
                        <span><i class="far fa-clock"></i> <?php echo date('d/m/Y', strtotime($jogo['adicionado_em'])); ?></span>
                    </div>
                </div>
                
                <div class="item-actions">
                    <div class="price-container">
                        <?php if ($tem_promocao): ?>
                        <span class="price-old"><?php echo formatPrice($preco_original); ?></span>
                        <span class="price-current sale"><?php echo formatPrice($preco_atual); ?></span>
                        <?php else: ?>
                        <span class="price-current"><?php echo $preco_atual == 0 ? 'Grátis' : formatPrice($preco_atual); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="item-buttons">
                        <button type="button" 
                                class="btn <?php echo $in_cart ? 'btn-success' : 'btn-primary'; ?> btn-sm"
                                data-action="toggle-cart" 
                                data-jogo-id="<?php echo $jogo['jogo_id']; ?>">
                            <i class="fas <?php echo $in_cart ? 'fa-check' : 'fa-cart-plus'; ?>"></i>
                            <?php echo $in_cart ? 'No Carrinho' : 'Comprar'; ?>
                        </button>
                        
                        <button type="button" 
                                class="btn btn-danger btn-sm" 
                                onclick="Wishlist.remove(<?php echo $jogo['jogo_id']; ?>, this)"
                                data-tooltip="Remover">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="fas fa-heart-broken"></i>
            </div>
            <h2>Sua lista de desejos está vazia</h2>
            <p>Encontre jogos incríveis e adicione-os à sua lista para acompanhar promoções!</p>
            <a href="<?php echo SITE_URL; ?>/pages/loja.php" class="btn btn-primary btn-lg">
                <i class="fas fa-gamepad"></i> Explorar Loja
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>