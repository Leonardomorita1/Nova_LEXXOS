<?php
// user/biblioteca.php - Biblioteca com PS Cards
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../components/game-card.php';

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
           b.adicionado_em as data_biblioteca
    FROM biblioteca b
    INNER JOIN jogo j ON b.jogo_id = j.id
    LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id
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

foreach ($jogos as $jogo) {
    $preco = $jogo['preco_centavos'] ?? 0;
    $valor_total += $preco;
    if ($preco == 0) $jogos_gratis++;
}

$page_title = 'Minha Biblioteca - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<style>
/* ===========================================
   BIBLIOTECA - HERO E STATS
   =========================================== */
.biblioteca-page {
    padding: 0 0 80px;
    min-height: 100vh;
}

.biblioteca-hero {
    background: linear-gradient(135deg, var(--bg-primary) 0%, #0d1f3c 50%, var(--bg-secondary) 100%);
    padding: 60px 0 100px;
    position: relative;
    overflow: hidden;
    margin-top: -20px;
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
    font-size: 2.5rem;
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

/* Stats */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}

.stat-card {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 25px;
    transition: all 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    border-color: rgba(255, 255, 255, 0.2);
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    margin-bottom: 15px;
    background: rgba(0, 174, 255, 0.2);
    color: var(--accent);
}

.stat-value {
    font-size: 1.8rem;
    font-weight: 800;
    color: #fff;
    margin-bottom: 5px;
}

.stat-label {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.85rem;
}

/* ===========================================
   CONTENT
   =========================================== */
.biblioteca-content {
    margin-top: -30px;
    position: relative;
    z-index: 10;
}

.content-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding: 20px;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 16px;
    flex-wrap: wrap;
    gap: 15px;
}

.content-title {
    font-size: 1.2rem;
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
}

.toolbar-actions {
    display: flex;
    gap: 15px;
    align-items: center;
}

.search-box {
    position: relative;
}

.search-box input {
    padding: 10px 15px 10px 40px;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: 10px;
    color: var(--text-primary);
    font-size: 0.9rem;
    width: 250px;
    transition: all 0.3s;
}

.search-box input:focus {
    outline: none;
    border-color: var(--accent);
}

.search-box i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-secondary);
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

/* Empty State */
.empty-biblioteca {
    text-align: center;
    padding: 80px 40px;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 20px;
}

.empty-icon {
    width: 100px;
    height: 100px;
    margin: 0 auto 25px;
    background: rgba(0, 174, 255, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.empty-icon i {
    font-size: 40px;
    color: var(--accent);
    opacity: 0.5;
}

.empty-biblioteca h2 {
    font-size: 1.5rem;
    margin-bottom: 10px;
}

.empty-biblioteca p {
    color: var(--text-secondary);
    margin-bottom: 25px;
}

/* ===========================================
   RESPONSIVE
   =========================================== */
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
    
    .stat-card {
        padding: 18px;
    }
    
    .stat-value {
        font-size: 1.4rem;
    }
    
    .content-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .toolbar-actions {
        flex-direction: column;
    }
    
    .search-box input {
        width: 100%;
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
}
</style>

<div class="biblioteca-page">
    <!-- Hero Section -->
    <section class="biblioteca-hero">
        <div class="container">
            <div class="hero-content">
                <div class="hero-top">
                    <div class="hero-title-section">
                        <h1><i class="fas fa-book-open"></i> Minha Biblioteca</h1>
                        <p class="hero-subtitle">Sua coleção pessoal de jogos indies</p>
                    </div>
                    
                    <div class="hero-actions">
                        <a href="<?= SITE_URL ?>/pages/home.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Adicionar Jogos
                        </a>
                        <a href="<?= SITE_URL ?>/user/lista-desejos.php" class="btn btn-secondary">
                            <i class="fas fa-heart"></i> Lista de Desejos
                        </a>
                    </div>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-gamepad"></i></div>
                        <div class="stat-value"><?= $total_jogos ?></div>
                        <div class="stat-label">Jogos na Biblioteca</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-gift"></i></div>
                        <div class="stat-value"><?= $jogos_gratis ?></div>
                        <div class="stat-label">Jogos Gratuitos</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-wallet"></i></div>
                        <div class="stat-value"><?= formatPrice($valor_total) ?></div>
                        <div class="stat-label">Valor Investido</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                        <div class="stat-value">
                            <?php 
                            if ($total_jogos > 0) {
                                echo date('d/m', strtotime($jogos[0]['data_biblioteca']));
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

    <!-- Content -->
    <div class="container">
        <div class="biblioteca-content">
            <?php if ($total_jogos > 0): ?>
                
                <div class="content-header">
                    <h2 class="content-title">
                        Seus Jogos
                        <span class="count"><?= $total_jogos ?></span>
                    </h2>
                    
                    <div class="toolbar-actions">
                        <form method="GET" class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" 
                                   name="q" 
                                   placeholder="Buscar na biblioteca..."
                                   value="<?= htmlspecialchars($busca) ?>">
                        </form>
                        
                        <form method="GET">
                            <input type="hidden" name="q" value="<?= htmlspecialchars($busca) ?>">
                            <select name="ordem" class="sort-select" onchange="this.form.submit()">
                                <option value="recente" <?= $ordem == 'recente' ? 'selected' : '' ?>>Mais Recentes</option>
                                <option value="nome" <?= $ordem == 'nome' ? 'selected' : '' ?>>Nome (A-Z)</option>
                                <option value="nota" <?= $ordem == 'nota' ? 'selected' : '' ?>>Melhor Avaliados</option>
                            </select>
                        </form>
                    </div>
                </div>
                
                <div class="games-grid">
                    <?php foreach ($jogos as $jogo): ?>
                        <?php 
                        renderGameCard($jogo, $pdo, $user_id, 'library', [
                            'data_adicionado' => $jogo['data_biblioteca']
                        ]); 
                        ?>
                    <?php endforeach; ?>
                </div>
                
            <?php else: ?>
                <div class="empty-biblioteca">
                    <div class="empty-icon">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <h2>Sua biblioteca está vazia</h2>
                    <p>Comece a construir sua coleção de jogos indies incríveis!</p>
                    <a href="<?= SITE_URL ?>/pages/home.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-gamepad"></i> Explorar Jogos
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>