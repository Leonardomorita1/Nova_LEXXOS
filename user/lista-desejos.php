<?php
// user/lista-desejos.php
require_once '../config/config.php';
require_once '../config/database.php';

requireLogin();

$database = new Database();
$pdo = $database->getConnection();
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT ld.*, j.*, d.nome_estudio, d.slug as dev_slug,
           GROUP_CONCAT(DISTINCT t.nome) as tags
    FROM lista_desejos ld
    INNER JOIN jogo j ON ld.jogo_id = j.id
    LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id
    LEFT JOIN jogo_tag jt ON j.id = jt.jogo_id
    LEFT JOIN tag t ON jt.tag_id = t.id
    WHERE ld.usuario_id = ? AND j.status = 'publicado'
    GROUP BY ld.id
    ORDER BY ld.adicionado_em DESC
");
$stmt->execute([$user_id]);
$jogos = $stmt->fetchAll();

$page_title = 'Lista de Desejos - ' . SITE_NAME;
require_once '../components/game-card.php';
require_once '../includes/header.php';
?>

<div class="container">
    <div style="padding: 30px 0;">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-heart"></i> Lista de Desejos
            </h1>
            <p class="page-subtitle"><?php echo count($jogos); ?> jogo<?php echo count($jogos) != 1 ? 's' : ''; ?> na lista</p>
        </div>
        
        <?php if (count($jogos) > 0): ?>
            <div class="grid grid-4">
                <?php foreach ($jogos as $jogo): ?>
                    <?php renderGameCard($jogo, $pdo, $user_id); ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 60px 20px; background: var(--bg-secondary); border-radius: 15px;">
                <i class="fas fa-heart" style="font-size: 64px; color: var(--text-secondary); margin-bottom: 20px;"></i>
                <h2>Sua lista de desejos está vazia</h2>
                <p style="color: var(--text-secondary); margin-bottom: 30px;">
                    Adicione jogos que você quer comprar no futuro!
                </p>
                <a href="<?php echo SITE_URL; ?>/pages/busca.php" class="btn btn-primary">
                    <i class="fas fa-search"></i> Explorar Jogos
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>