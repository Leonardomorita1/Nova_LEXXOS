<?php
// pages/home.php
require_once '../config/config.php';
require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();

$page_title = 'Home - ' . SITE_NAME;
$page_description = 'Descubra e jogue os melhores jogos indies do Brasil';

$user_id = $_SESSION['user_id'] ?? null;

// Buscar jogo em destaque
$stmt = $pdo->query("
    SELECT j.*, d.nome_estudio, d.slug as dev_slug,
           GROUP_CONCAT(DISTINCT t.nome) as tags
    FROM jogo j
    LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id
    LEFT JOIN jogo_tag jt ON j.id = jt.jogo_id
    LEFT JOIN tag t ON jt.tag_id = t.id
    WHERE j.status = 'publicado' AND j.destaque = 1
    GROUP BY j.id
    ORDER BY j.total_vendas DESC, j.nota_media DESC
    LIMIT 1
");
$jogo_destaque = $stmt->fetch();

// Buscar jogos em promoção
$stmt = $pdo->query("
    SELECT j.*, d.nome_estudio, d.slug as dev_slug,
           GROUP_CONCAT(DISTINCT t.nome) as tags
    FROM jogo j
    LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id
    LEFT JOIN jogo_tag jt ON j.id = jt.jogo_id
    LEFT JOIN tag t ON jt.tag_id = t.id
    WHERE j.status = 'publicado' AND j.em_promocao = 1
    GROUP BY j.id
    ORDER BY j.preco_promocional_centavos ASC
    LIMIT 8
");
$jogos_promocao = $stmt->fetchAll();

// Buscar lançamentos recentes
$stmt = $pdo->query("
    SELECT j.*, d.nome_estudio, d.slug as dev_slug,
           GROUP_CONCAT(DISTINCT t.nome) as tags
    FROM jogo j
    LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id
    LEFT JOIN jogo_tag jt ON j.id = jt.jogo_id
    LEFT JOIN tag t ON jt.tag_id = t.id
    WHERE j.status = 'publicado'
    GROUP BY j.id
    ORDER BY j.publicado_em DESC
    LIMIT 8
");
$jogos_lancamentos = $stmt->fetchAll();

// Buscar jogos mais vendidos
$stmt = $pdo->query("
    SELECT j.*, d.nome_estudio, d.slug as dev_slug,
           GROUP_CONCAT(DISTINCT t.nome) as tags
    FROM jogo j
    LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id
    LEFT JOIN jogo_tag jt ON j.id = jt.jogo_id
    LEFT JOIN tag t ON jt.tag_id = t.id
    WHERE j.status = 'publicado'
    GROUP BY j.id
    ORDER BY j.total_vendas DESC
    LIMIT 8
");
$jogos_vendidos = $stmt->fetchAll();

// Buscar melhores avaliados
$stmt = $pdo->query("
    SELECT j.*, d.nome_estudio, d.slug as dev_slug,
           GROUP_CONCAT(DISTINCT t.nome) as tags
    FROM jogo j
    LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id
    LEFT JOIN jogo_tag jt ON j.id = jt.jogo_id
    LEFT JOIN tag t ON jt.tag_id = t.id
    WHERE j.status = 'publicado' AND j.total_avaliacoes >= 2
    GROUP BY j.id
    ORDER BY j.nota_media DESC, j.total_avaliacoes DESC
    LIMIT 8
");
$jogos_avaliados = $stmt->fetchAll();

// Buscar categorias populares
$stmt = $pdo->query("
    SELECT c.*, COUNT(jc.jogo_id) as total_jogos
    FROM categoria c
    LEFT JOIN jogo_categoria jc ON c.id = jc.categoria_id
    LEFT JOIN jogo j ON jc.jogo_id = j.id AND j.status = 'publicado'
    WHERE c.ativa = 1
    GROUP BY c.id
    ORDER BY total_jogos DESC
    LIMIT 6
");
$categorias_populares = $stmt->fetchAll();

require_once '../components/game-card.php';
require_once '../includes/header.php';
?>

<style>
    /* Hero Banner */
    .hero-banner {
        background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-primary) 100%);
        padding: 60px 0;
        margin-bottom: 40px;
        border-bottom: 1px solid var(--border);
    }
    
    .hero-content {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 40px;
        align-items: center;
    }
    
    .hero-text h1 {
        font-size: 48px;
        margin-bottom: 20px;
        background: linear-gradient(135deg, var(--accent) 0%, #6ba3ff 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    .hero-text p {
        font-size: 18px;
        color: var(--text-secondary);
        margin-bottom: 30px;
        line-height: 1.6;
    }
    
    .hero-stats {
        display: flex;
        gap: 30px;
        margin-top: 30px;
    }
    
    .hero-stat {
        text-align: center;
    }
    
    .hero-stat-value {
        font-size: 32px;
        font-weight: 700;
        color: var(--accent);
        display: block;
    }
    
    .hero-stat-label {
        font-size: 14px;
        color: var(--text-secondary);
    }
    
    .hero-image {
        position: relative;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 10px 40px rgba(76, 139, 245, 0.3);
    }
    
    .hero-image img {
        width: 100%;
        display: block;
    }
    
    /* Featured Game */
    .featured-game {
        margin-bottom: 60px;
    }
    
    .featured-game-card {
        background: var(--bg-secondary);
        border-radius: 15px;
        overflow: hidden;
        border: 1px solid var(--border);
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 40px;
        padding: 40px;
    }
    
    .featured-game-image {
        border-radius: 10px;
        overflow: hidden;
    }
    
    .featured-game-image img {
        width: 100%;
        height: 400px;
        object-fit: cover;
    }
    
    .featured-game-info h2 {
        font-size: 36px;
        margin-bottom: 15px;
    }
    
    .featured-game-dev {
        color: var(--text-secondary);
        margin-bottom: 20px;
    }
    
    .featured-game-description {
        color: var(--text-secondary);
        line-height: 1.6;
        margin-bottom: 20px;
    }
    
    .featured-game-price {
        font-size: 28px;
        font-weight: 700;
        color: var(--accent);
        margin: 20px 0;
    }
    
    /* Section */
    .section {
        margin-bottom: 50px;
    }
    
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }
    
    .section-title {
        font-size: 28px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .section-title i {
        color: var(--accent);
    }
    
    .section-link {
        color: var(--accent);
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .section-link:hover {
        gap: 10px;
    }
    
    /* Categories Grid */
    .categories-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
    }
    
    .category-card {
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 30px;
        text-align: center;
        transition: all 0.3s;
        cursor: pointer;
    }
    
    .category-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(76, 139, 245, 0.2);
        border-color: var(--accent);
    }
    
    .category-icon {
        font-size: 48px;
        color: var(--accent);
        margin-bottom: 15px;
    }
    
    .category-name {
        font-size: 20px;
        font-weight: 600;
        margin-bottom: 8px;
    }
    
    .category-count {
        font-size: 14px;
        color: var(--text-secondary);
    }
    
    /* Developer CTA Banner */
    .dev-cta-banner {
        background: linear-gradient(135deg, #4C8BF5 0%, #6ba3ff 100%);
        border-radius: 15px;
        padding: 60px;
        text-align: center;
        margin: 60px 0;
        position: relative;
        overflow: hidden;
    }
    
    .dev-cta-banner::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 400px;
        height: 400px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
    }
    
    .dev-cta-banner::after {
        content: '';
        position: absolute;
        bottom: -50%;
        left: -10%;
        width: 400px;
        height: 400px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
    }
    
    .dev-cta-content {
        position: relative;
        z-index: 1;
    }
    
    .dev-cta-icon {
        font-size: 64px;
        color: white;
        margin-bottom: 20px;
    }
    
    .dev-cta-title {
        font-size: 36px;
        font-weight: 700;
        color: white;
        margin-bottom: 15px;
    }
    
    .dev-cta-description {
        font-size: 18px;
        color: rgba(255, 255, 255, 0.9);
        margin-bottom: 30px;
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
    }
    
    .dev-cta-features {
        display: flex;
        justify-content: center;
        gap: 40px;
        margin: 30px 0;
        flex-wrap: wrap;
    }
    
    .dev-cta-feature {
        display: flex;
        align-items: center;
        gap: 10px;
        color: white;
        font-size: 16px;
    }
    
    .dev-cta-feature i {
        font-size: 24px;
    }
    
    .btn-cta {
        background: white;
        color: #4C8BF5;
        padding: 15px 40px;
        font-size: 18px;
        font-weight: 600;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-block;
    }
    
    .btn-cta:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        color: #4C8BF5;
    }
    
    /* Responsive */
    @media (max-width: 992px) {
        .hero-content {
            grid-template-columns: 1fr;
        }
        
        .featured-game-card {
            grid-template-columns: 1fr;
        }
        
        .categories-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 768px) {
        .hero-text h1 {
            font-size: 36px;
        }
        
        .hero-stats {
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .section-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
        }
        
        .categories-grid {
            grid-template-columns: 1fr;
        }
        
        .dev-cta-banner {
            padding: 40px 20px;
        }
        
        .dev-cta-title {
            font-size: 28px;
        }
        
        .dev-cta-features {
            flex-direction: column;
            gap: 15px;
        }
    }
</style>

<!-- Hero Banner -->
<section class="hero-banner">
    <div class="container">
        <div class="hero-content">
            <div class="hero-text">
                <h1>Descubra Jogos Indies Incríveis</h1>
                <p>A maior plataforma de jogos independentes do Brasil. Apoie desenvolvedores brasileiros e descubra experiências únicas.</p>
                
                <div>
                    <a href="<?php echo SITE_URL; ?>/pages/busca.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-search"></i> Explorar Jogos
                    </a>
                </div>
                
                <div class="hero-stats">
                    <div class="hero-stat">
                        <span class="hero-stat-value">
                            <?php
                            $stmt = $pdo->query("SELECT COUNT(*) as total FROM jogo WHERE status = 'publicado'");
                            echo $stmt->fetch()['total'];
                            ?>+
                        </span>
                        <span class="hero-stat-label">Jogos</span>
                    </div>
                    <div class="hero-stat">
                        <span class="hero-stat-value">
                            <?php
                            $stmt = $pdo->query("SELECT COUNT(*) as total FROM desenvolvedor WHERE status = 'ativo'");
                            echo $stmt->fetch()['total'];
                            ?>+
                        </span>
                        <span class="hero-stat-label">Desenvolvedores</span>
                    </div>
                    <div class="hero-stat">
                        <span class="hero-stat-value">
                            <?php
                            $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuario WHERE tipo = 'cliente'");
                            echo $stmt->fetch()['total'];
                            ?>+
                        </span>
                        <span class="hero-stat-label">Jogadores</span>
                    </div>
                </div>
            </div>
            
            <div class="hero-image">
                <img src="<?php echo SITE_URL; ?>/assets/images/hero-gaming.jpg" 
                     alt="Gaming"
                     onerror="this.src='https://via.placeholder.com/600x400/1E1F20/4C8BF5?text=Lexxos+Gaming'">
            </div>
        </div>
    </div>
</section>

<div class="container">
    <!-- Jogo em Destaque -->
    <?php if ($jogo_destaque): ?>
    <section class="featured-game">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-star"></i> Jogo em Destaque
            </h2>
        </div>
        
        <div class="featured-game-card">
            <div class="featured-game-image">
                <img src="<?php echo SITE_URL . ($jogo_destaque['imagem_banner'] ?: $jogo_destaque['imagem_capa']); ?>" 
                     alt="<?php echo sanitize($jogo_destaque['titulo']); ?>">
            </div>
            
            <div class="featured-game-info">
                <h2><?php echo sanitize($jogo_destaque['titulo']); ?></h2>
                
                <div class="featured-game-dev">
                    Por <a href="<?php echo SITE_URL; ?>/pages/desenvolvedor.php?slug=<?php echo $jogo_destaque['dev_slug']; ?>">
                        <?php echo sanitize($jogo_destaque['nome_estudio']); ?>
                    </a>
                </div>
                
                <?php if ($jogo_destaque['nota_media'] > 0): ?>
                <div class="game-card-rating">
                    <span class="rating-stars">
                        <?php
                        $stars = round($jogo_destaque['nota_media']);
                        for ($i = 1; $i <= 5; $i++) {
                            echo $i <= $stars ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                        }
                        ?>
                    </span>
                    <span>(<?php echo $jogo_destaque['total_avaliacoes']; ?> avaliações)</span>
                </div>
                <?php endif; ?>
                
                <p class="featured-game-description">
                    <?php echo sanitize($jogo_destaque['descricao_curta']); ?>
                </p>
                
                <?php if ($jogo_destaque['tags']): ?>
                <div class="game-card-tags">
                    <?php
                    $tags = explode(',', $jogo_destaque['tags']);
                    $tags = array_slice($tags, 0, 5);
                    foreach ($tags as $tag):
                    ?>
                        <span class="game-tag"><?php echo sanitize($tag); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <div class="featured-game-price">
                    <?php
                    $preco_final = $jogo_destaque['em_promocao'] && $jogo_destaque['preco_promocional_centavos']
                        ? $jogo_destaque['preco_promocional_centavos']
                        : $jogo_destaque['preco_centavos'];
                    
                    if ($preco_final == 0) {
                        echo 'GRÁTIS';
                    } else {
                        if ($jogo_destaque['em_promocao'] && $jogo_destaque['preco_promocional_centavos']) {
                            echo '<span style="text-decoration: line-through; font-size: 18px; color: var(--text-secondary); margin-right: 10px;">' 
                                . formatPrice($jogo_destaque['preco_centavos']) . '</span>';
                        }
                        echo formatPrice($preco_final);
                    }
                    ?>
                </div>
                
                <a href="<?php echo SITE_URL; ?>/pages/jogo.php?slug=<?php echo $jogo_destaque['slug']; ?>" 
                   class="btn btn-primary btn-lg">
                    <i class="fas fa-gamepad"></i> Ver Detalhes
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>
    
    <!-- Jogos em Promoção -->
    <?php if (count($jogos_promocao) > 0): ?>
    <section class="section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-fire"></i> Em Promoção
            </h2>
            <a href="<?php echo SITE_URL; ?>/pages/busca.php?promocao=1" class="section-link">
                Ver todas <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        
        <div class="grid grid-4">
            <?php foreach ($jogos_promocao as $jogo): ?>
                <?php renderGameCard($jogo, $pdo, $user_id); ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
    
    <!-- Lançamentos Recentes -->
    <?php if (count($jogos_lancamentos) > 0): ?>
    <section class="section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-rocket"></i> Lançamentos Recentes
            </h2>
            <a href="<?php echo SITE_URL; ?>/pages/busca.php?ordem=recente" class="section-link">
                Ver todos <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        
        <div class="grid grid-4">
            <?php foreach ($jogos_lancamentos as $jogo): ?>
                <?php renderGameCard($jogo, $pdo, $user_id); ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
    
    <!-- Mais Vendidos -->
    <?php if (count($jogos_vendidos) > 0): ?>
    <section class="section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-trophy"></i> Mais Vendidos
            </h2>
            <a href="<?php echo SITE_URL; ?>/pages/busca.php?ordem=vendas" class="section-link">
                Ver todos <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        
        <div class="grid grid-4">
            <?php foreach ($jogos_vendidos as $jogo): ?>
                <?php renderGameCard($jogo, $pdo, $user_id); ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
    
    <!-- Melhores Avaliados -->
    <?php if (count($jogos_avaliados) > 0): ?>
    <section class="section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-star"></i> Melhores Avaliados
            </h2>
            <a href="<?php echo SITE_URL; ?>/pages/busca.php?ordem=nota" class="section-link">
                Ver todos <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        
        <div class="grid grid-4">
            <?php foreach ($jogos_avaliados as $jogo): ?>
                <?php renderGameCard($jogo, $pdo, $user_id); ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
    
    <!-- Categorias Populares -->
    <?php if (count($categorias_populares) > 0): ?>
    <section class="section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-th"></i> Categorias Populares
            </h2>
        </div>
        
        <div class="categories-grid">
            <?php foreach ($categorias_populares as $cat): ?>
            <a href="<?php echo SITE_URL; ?>/pages/categoria.php?slug=<?php echo $cat['slug']; ?>" 
               class="category-card">
                <div class="category-icon">
                    <i class="fas fa-<?php echo $cat['icone']; ?>"></i>
                </div>
                <div class="category-name"><?php echo sanitize($cat['nome']); ?></div>
                <div class="category-count"><?php echo $cat['total_jogos']; ?> jogos</div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
    
    <!-- Developer CTA Banner -->
    <section class="dev-cta-banner">
        <div class="dev-cta-content">
            <div class="dev-cta-icon">
                <i class="fas fa-code"></i>
            </div>
            
            <h2 class="dev-cta-title">Você é um Desenvolvedor?</h2>
            
            <p class="dev-cta-description">
                Publique seus jogos na maior plataforma indie do Brasil! 
                Alcance milhares de jogadores e transforme sua paixão em negócio.
            </p>
            
            <div class="dev-cta-features">
                <div class="dev-cta-feature">
                    <i class="fas fa-percentage"></i>
                    <span>Apenas <?php echo getConfig('taxa_plataforma', $pdo); ?>% de taxa</span>
                </div>
                <div class="dev-cta-feature">
                    <i class="fas fa-bolt"></i>
                    <span>Publicação rápida</span>
                </div>
                <div class="dev-cta-feature">
                    <i class="fas fa-users"></i>
                    <span>Grande audiência</span>
                </div>
                <div class="dev-cta-feature">
                    <i class="fas fa-chart-line"></i>
                    <span>Analytics completo</span>
                </div>
            </div>
            
            <a href="<?php echo SITE_URL; ?>/user/seja-dev.php" class="btn-cta">
                <i class="fas fa-rocket"></i> Seja um Desenvolvedor
            </a>
        </div>
    </section>
</div>

<?php require_once '../includes/footer.php'; ?>