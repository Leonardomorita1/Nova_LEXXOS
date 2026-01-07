<?php
// pages/categoria.php
require_once '../config/config.php';
require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();

$user_id = $_SESSION['user_id'] ?? null;
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

// Buscar jogos da categoria
$stmt = $pdo->prepare("
    SELECT j.*, d.nome_estudio, d.slug as dev_slug,
           GROUP_CONCAT(DISTINCT t.nome) as tags
    FROM jogo j
    LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id
    INNER JOIN jogo_categoria jc ON j.id = jc.jogo_id
    LEFT JOIN jogo_tag jt ON j.id = jt.jogo_id
    LEFT JOIN tag t ON jt.tag_id = t.id
    WHERE jc.categoria_id = ? AND j.status = 'publicado'
    GROUP BY j.id
    ORDER BY j.nota_media DESC, j.total_vendas DESC
    LIMIT 20
");
$stmt->execute([$categoria['id']]);
$jogos = $stmt->fetchAll();

$page_title = $categoria['nome'] . ' - ' . SITE_NAME;

require_once '../components/game-card.php';
require_once '../includes/header.php';
?>

<style>
.category-page {
    padding: 30px 0;
}

.category-header {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 15px;
    padding: 40px;
    margin-bottom: 40px;
    text-align: center;
}

.category-icon {
    font-size: 64px;
    color: var(--accent);
    margin-bottom: 20px;
}

.category-header h1 {
    font-size: 36px;
    margin-bottom: 15px;
}

.category-description {
    color: var(--text-secondary);
    font-size: 16px;
    max-width: 600px;
    margin: 0 auto 20px;
}

.category-stats {
    display: flex;
    justify-content: center;
    gap: 40px;
    margin-top: 30px;
}

.category-stat {
    text-align: center;
}

.category-stat-value {
    font-size: 32px;
    font-weight: 700;
    color: var(--accent);
    display: block;
}

.category-stat-label {
    font-size: 14px;
    color: var(--text-secondary);
}
</style>

<div class="container">
    <div class="category-page">
        <div class="category-header">
            <div class="category-icon">
                <i class="fas fa-<?php echo $categoria['icone']; ?>"></i>
            </div>
            <h1><?php echo sanitize($categoria['nome']); ?></h1>
            <?php if ($categoria['descricao']): ?>
                <p class="category-description"><?php echo sanitize($categoria['descricao']); ?></p>
            <?php endif; ?>
            
            <div class="category-stats">
                <div class="category-stat">
                    <span class="category-stat-value"><?php echo count($jogos); ?></span>
                    <span class="category-stat-label">Jogos</span>
                </div>
            </div>
        </div>
        
        <?php if (count($jogos) > 0): ?>
            <div class="grid grid-4">
                <?php foreach ($jogos as $jogo): ?>
                    <?php renderGameCard($jogo, $pdo, $user_id); ?>
                <?php endforeach; ?>
            </div>
            
            <div style="text-align: center; margin-top: 40px;">
                <a href="<?php echo SITE_URL; ?>/pages/busca.php?categoria=<?php echo $categoria['slug']; ?>" 
                   class="btn btn-primary">
                    <i class="fas fa-search"></i> Ver Todos os Jogos de <?php echo sanitize($categoria['nome']); ?>
                </a>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 60px 20px; background: var(--bg-secondary); border-radius: 15px;">
                <i class="fas fa-gamepad" style="font-size: 64px; color: var(--text-secondary); margin-bottom: 20px;"></i>
                <h2>Nenhum jogo nesta categoria ainda</h2>
                <p style="color: var(--text-secondary);">Em breve teremos jogos incríveis aqui!</p>
                <a href="<?php echo SITE_URL; ?>/pages/busca.php" class="btn btn-primary" style="margin-top: 20px;">
                    <i class="fas fa-search"></i> Explorar Outras Categorias
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>