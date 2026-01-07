<?php
// user/biblioteca.php
require_once '../config/config.php';
require_once '../config/database.php';

requireLogin();

$database = new Database();
$pdo = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Buscar jogos da biblioteca
$stmt = $pdo->prepare("
    SELECT b.*, j.*, d.nome_estudio, d.slug as dev_slug,
           GROUP_CONCAT(DISTINCT t.nome) as tags
    FROM biblioteca b
    INNER JOIN jogo j ON b.jogo_id = j.id
    LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id
    LEFT JOIN jogo_tag jt ON j.id = jt.jogo_id
    LEFT JOIN tag t ON jt.tag_id = t.id
    WHERE b.usuario_id = ?
    GROUP BY b.id
    ORDER BY b.adicionado_em DESC
");
$stmt->execute([$user_id]);
$jogos = $stmt->fetchAll();

$page_title = 'Biblioteca - ' . SITE_NAME;
require_once '../components/game-card.php';
require_once '../includes/header.php';
?>

<style>
.biblioteca-page {
    padding: 30px 0;
}

.biblioteca-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 40px;
}

.stat-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 25px;
    text-align: center;
}

.stat-icon {
    font-size: 36px;
    color: var(--accent);
    margin-bottom: 15px;
}

.stat-value {
    font-size: 32px;
    font-weight: 700;
    color: var(--text-primary);
    display: block;
    margin-bottom: 5px;
}

.stat-label {
    color: var(--text-secondary);
    font-size: 14px;
}

@media (max-width: 768px) {
    .biblioteca-stats {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="container">
    <div class="biblioteca-page">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-book"></i> Minha Biblioteca
            </h1>
            <p class="page-subtitle">Seus jogos adquiridos</p>
        </div>
        
        <div class="biblioteca-stats">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-gamepad"></i></div>
                <span class="stat-value"><?php echo count($jogos); ?></span>
                <span class="stat-label">Jogos na Biblioteca</span>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <span class="stat-value">
                    <?php
                    $total_minutos = array_sum(array_column($jogos, 'tempo_jogado_minutos'));
                    echo floor($total_minutos / 60);
                    ?>h
                </span>
                <span class="stat-label">Tempo Jogado</span>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-calendar"></i></div>
                <span class="stat-value">
                    <?php
                    if (count($jogos) > 0) {
                        echo date('d/m/Y', strtotime($jogos[0]['adicionado_em']));
                    } else {
                        echo '-';
                    }
                    ?>
                </span>
                <span class="stat-label">Último Jogo Adquirido</span>
            </div>
        </div>
        
        <?php if (count($jogos) > 0): ?>
            <div class="grid grid-4">
                <?php foreach ($jogos as $jogo): ?>
                    <?php renderGameCard($jogo, $pdo, $user_id); ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 60px 20px; background: var(--bg-secondary); border-radius: 15px;">
                <i class="fas fa-book" style="font-size: 64px; color: var(--text-secondary); margin-bottom: 20px;"></i>
                <h2>Sua biblioteca está vazia</h2>
                <p style="color: var(--text-secondary); margin-bottom: 30px;">
                    Comece a construir sua coleção de jogos indies!
                </p>
                <a href="<?php echo SITE_URL; ?>/pages/busca.php" class="btn btn-primary">
                    <i class="fas fa-search"></i> Explorar Jogos
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>