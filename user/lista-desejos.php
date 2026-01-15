<?php
// user/lista-desejos.php - Com PS Cards
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../components/game-card.php';

requireLogin();

$database = new Database();
$pdo = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Filtros
$ordem = $_GET['ordem'] ?? 'recente';
$filtro_preco = $_GET['preco'] ?? '';

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
    SELECT ld.*, j.*, d.nome_estudio,
           (SELECT COUNT(*) FROM carrinho WHERE usuario_id = ? AND jogo_id = j.id) as in_cart
    FROM lista_desejos ld
    INNER JOIN jogo j ON ld.jogo_id = j.id
    LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id
    {$where}
    GROUP BY ld.id
    ORDER BY {$orderBy}
");

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
   WISHLIST - ESTILOS
   =========================================== */
.wishlist-page {
    padding: 0 0 80px;
    min-height: 100vh;
}

.wishlist-hero {
    background: linear-gradient(180deg, var(--bg-secondary) 0%, var(--bg-primary) 100%);
    padding: 60px 0 80px;
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
    color: #ff4757;
}

.hero-subtitle {
    color: var(--text-secondary);
    font-size: 1.1rem;
}

/* Stats */
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
    margin-bottom: 5px;
}

.stat-label {
    color: var(--text-secondary);
    font-size: 0.85rem;
}

/* Toolbar */
.wishlist-toolbar {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 30px;
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
}

.filter-tab:hover {
    background: var(--bg-primary);
    color: var(--text-primary);
}

.filter-tab.active {
    background: var(--accent);
    color: #fff;
}

.sort-select {
    padding: 10px 35px 10px 15px;
    background: var(--bg-primary);
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

/* Empty */
.empty-state {
    text-align: center;
    padding: 80px 40px;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 20px;
}

.empty-state-icon {
    width: 100px;
    height: 100px;
    margin: 0 auto 25px;
    background: rgba(255, 71, 87, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.empty-state-icon i {
    font-size: 40px;
    color: #ff4757;
    opacity: 0.5;
}

.empty-state h2 {
    font-size: 1.5rem;
    margin-bottom: 10px;
}

.empty-state p {
    color: var(--text-secondary);
    margin-bottom: 25px;
}

/* Responsive */
@media (max-width: 1024px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .hero-title-section h1 {
        font-size: 1.8rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    
    .wishlist-toolbar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-tabs {
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="wishlist-page">
    <!-- Hero -->
    <section class="wishlist-hero">
        <div class="container">
            <div class="hero-top">
                <div class="hero-title-section">
                    <h1><i class="fas fa-heart"></i> Lista de Desejos</h1>
                    <p class="hero-subtitle">Jogos que você está de olho</p>
                </div>
                
                <div class="hero-actions">
                    <a href="<?= SITE_URL ?>/pages/home.php" class="btn btn-secondary">
                        <i class="fas fa-search"></i> Explorar Loja
                    </a>
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-gamepad"></i></div>
                    <div class="stat-value"><?= $total_jogos ?></div>
                    <div class="stat-label">Jogos na Lista</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-tag"></i></div>
                    <div class="stat-value"><?= $jogos_promocao ?></div>
                    <div class="stat-label">Em Promoção</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-wallet"></i></div>
                    <div class="stat-value"><?= formatPrice($valor_promocional) ?></div>
                    <div class="stat-label">Valor Total</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-piggy-bank"></i></div>
                    <div class="stat-value"><?= formatPrice($economia_potencial) ?></div>
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
                <a href="?ordem=<?= $ordem ?>" class="filter-tab <?= empty($filtro_preco) ? 'active' : '' ?>">
                    Todos
                </a>
                <?php if ($jogos_promocao > 0): ?>
                <a href="?preco=promocao&ordem=<?= $ordem ?>" class="filter-tab <?= $filtro_preco == 'promocao' ? 'active' : '' ?>">
                    <i class="fas fa-fire"></i> Em Promoção
                </a>
                <?php endif; ?>
            </div>
            
            <form method="GET">
                <?php if ($filtro_preco): ?>
                <input type="hidden" name="preco" value="<?= $filtro_preco ?>">
                <?php endif; ?>
                <select name="ordem" class="sort-select" onchange="this.form.submit()">
                    <option value="recente" <?= $ordem == 'recente' ? 'selected' : '' ?>>Recentes</option>
                    <option value="nome" <?= $ordem == 'nome' ? 'selected' : '' ?>>Nome (A-Z)</option>
                    <option value="preco_asc" <?= $ordem == 'preco_asc' ? 'selected' : '' ?>>Menor Preço</option>
                    <option value="promocao" <?= $ordem == 'promocao' ? 'selected' : '' ?>>Promoções</option>
                </select>
            </form>
        </div>
        
        <!-- Grid de Jogos -->
        <div class="games-grid">
            <?php foreach ($jogos as $jogo): ?>
                <?php 
                renderGameCard($jogo, $pdo, $user_id, 'wishlist', [
                    'in_cart' => !empty($jogo['in_cart'])
                ]); 
                ?>
            <?php endforeach; ?>
        </div>
        
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="fas fa-heart-broken"></i>
            </div>
            <h2>Sua lista de desejos está vazia</h2>
            <p>Encontre jogos incríveis e adicione-os à sua lista!</p>
            <a href="<?= SITE_URL ?>/pages/home.php" class="btn btn-primary btn-lg">
                <i class="fas fa-gamepad"></i> Explorar Jogos
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>