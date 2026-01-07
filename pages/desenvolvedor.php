<?php
// ===== pages/desenvolvedor.php =====
require_once '../config/config.php';
require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();

$slug = $_GET['slug'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;

if (empty($slug)) {
    header('Location: ' . SITE_URL . '/pages/home.php');
    exit;
}

// Buscar desenvolvedor
$stmt = $pdo->prepare("
    SELECT d.*, u.nome_usuario, u.email
    FROM desenvolvedor d
    LEFT JOIN usuario u ON d.usuario_id = u.id
    WHERE d.slug = ? AND d.status = 'ativo'
");
$stmt->execute([$slug]);
$dev = $stmt->fetch();

if (!$dev) {
    header('Location: ' . SITE_URL .'/pages/home.php');
    exit;
}

// Verificar se usuário segue
$is_following = false;
if ($user_id) {
    $stmt = $pdo->prepare("SELECT id FROM seguidor WHERE usuario_id = ? AND desenvolvedor_id = ?");
    $stmt->execute([$user_id, $dev['id']]);
    $is_following = $stmt->fetch() !== false;
}

// Contar seguidores
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM seguidor WHERE desenvolvedor_id = ?");
$stmt->execute([$dev['id']]);
$total_seguidores = $stmt->fetch()['total'];

// Buscar jogos
$stmt = $pdo->prepare("
    SELECT j.*, GROUP_CONCAT(DISTINCT t.nome) as tags
    FROM jogo j
    LEFT JOIN jogo_tag jt ON j.id = jt.jogo_id
    LEFT JOIN tag t ON jt.tag_id = t.id
    WHERE j.desenvolvedor_id = ? AND j.status = 'publicado'
    GROUP BY j.id
    ORDER BY j.destaque DESC, j.total_vendas DESC
");
$stmt->execute([$dev['id']]);
$jogos = $stmt->fetchAll();

// Jogo em destaque
$jogo_destaque = null;
foreach ($jogos as $jogo) {
    if ($jogo['destaque']) {
        $jogo_destaque = $jogo;
        break;
    }
}
if (!$jogo_destaque && count($jogos) > 0) {
    $jogo_destaque = $jogos[0];
}

// Estatísticas
$total_jogos = count($jogos);
$total_vendas = array_sum(array_column($jogos, 'total_vendas'));

$page_title = $dev['nome_estudio'] . ' - ' . SITE_NAME;
require_once '../components/game-card.php';
require_once '../includes/header.php';
?>

<style>
.dev-header {
    position: relative;
    min-height: 400px;
    background: var(--bg-secondary);
    margin-bottom: 30px;
    overflow: hidden;
}

.dev-banner {
    width: 100%;
    height: 400px;
    object-fit: cover;
    opacity: 0.3;
}

.dev-header-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(to top, var(--bg-primary) 0%, transparent 100%);
    padding: 60px 0 30px;
}

.dev-header-content {
    display: flex;
    gap: 30px;
    align-items: end;
}

.dev-logo {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    border: 4px solid var(--accent);
    overflow: hidden;
    background: var(--bg-secondary);
    flex-shrink: 0;
}

.dev-logo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.dev-info h1 {
    font-size: 36px;
    margin-bottom: 10px;
}

.dev-verified {
    color: var(--accent);
    margin-left: 10px;
}

.dev-description {
    color: var(--text-secondary);
    margin-bottom: 20px;
    max-width: 600px;
}

.dev-stats {
    display: flex;
    gap: 30px;
    margin-bottom: 20px;
}

.dev-stat {
    display: flex;
    align-items: center;
    gap: 8px;
}

.dev-stat i {
    color: var(--accent);
}

.dev-actions {
    display: flex;
    gap: 15px;
}

.dev-social {
    display: flex;
    gap: 10px;
}

.social-link {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-primary);
    border-radius: 50%;
    color: var(--text-primary);
    transition: all 0.3s;
}

.social-link:hover {
    background: var(--accent);
    color: white;
    transform: translateY(-2px);
}

.featured-game-section {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 40px;
}

.featured-game-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
    align-items: center;
}

.featured-game-image {
    border-radius: 10px;
    overflow: hidden;
}

.featured-game-image img {
    width: 100%;
    display: block;
}

.featured-game-info h2 {
    font-size: 32px;
    margin-bottom: 15px;
}

.featured-price {
    font-size: 28px;
    font-weight: 700;
    color: var(--accent);
    margin: 20px 0;
}

@media (max-width: 992px) {
    .dev-header-content {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    
    .featured-game-content {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="dev-header">
    <?php if ($dev['banner_url']): ?>
        <img src="<?php echo SITE_URL . $dev['banner_url']; ?>" class="dev-banner" alt="Banner">
    <?php else: ?>
        <div style="width: 100%; height: 400px; background: linear-gradient(135deg, var(--accent) 0%, #6ba3ff 100%);"></div>
    <?php endif; ?>
    
    <div class="dev-header-overlay">
        <div class="container">
            <div class="dev-header-content">
                <div class="dev-logo">
                    <img src="<?php echo SITE_URL . ($dev['logo_url'] ?: '/assets/images/default-dev.png'); ?>" 
                         alt="<?php echo sanitize($dev['nome_estudio']); ?>">
                </div>
                
                <div class="dev-info">
                    <h1>
                        <?php echo sanitize($dev['nome_estudio']); ?>
                        <?php if ($dev['verificado']): ?>
                            <i class="fas fa-check-circle dev-verified" title="Verificado"></i>
                        <?php endif; ?>
                    </h1>
                    
                    <?php if ($dev['descricao_curta']): ?>
                        <p class="dev-description"><?php echo sanitize($dev['descricao_curta']); ?></p>
                    <?php endif; ?>
                    
                    <div class="dev-stats">
                        <div class="dev-stat">
                            <i class="fas fa-gamepad"></i>
                            <span><?php echo $total_jogos; ?> jogo<?php echo $total_jogos != 1 ? 's' : ''; ?></span>
                        </div>
                        
                        <div class="dev-stat">
                            <i class="fas fa-download"></i>
                            <span><?php echo $total_vendas; ?> vendas</span>
                        </div>
                        
                        <div class="dev-stat">
                            <i class="fas fa-users"></i>
                            <span><?php echo $total_seguidores; ?> seguidor<?php echo $total_seguidores != 1 ? 'es' : ''; ?></span>
                        </div>
                    </div>
                    
                    <div class="dev-actions">
                        <?php if ($user_id): ?>
                            <button class="btn <?php echo $is_following ? 'btn-secondary' : 'btn-primary'; ?>" 
                                    onclick="toggleFollow(<?php echo $dev['id']; ?>, this)">
                                <i class="fas fa-<?php echo $is_following ? 'check' : 'plus'; ?>"></i>
                                <?php echo $is_following ? 'Seguindo' : 'Seguir'; ?>
                            </button>
                        <?php endif; ?>
                        
                        <div class="dev-social">
                            <?php if ($dev['website']): ?>
                                <a href="<?php echo $dev['website']; ?>" target="_blank" class="social-link" title="Website">
                                    <i class="fas fa-globe"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($dev['twitter']): ?>
                                <a href="<?php echo $dev['twitter']; ?>" target="_blank" class="social-link" title="Twitter">
                                    <i class="fab fa-twitter"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($dev['instagram']): ?>
                                <a href="<?php echo $dev['instagram']; ?>" target="_blank" class="social-link" title="Instagram">
                                    <i class="fab fa-instagram"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($dev['youtube']): ?>
                                <a href="<?php echo $dev['youtube']; ?>" target="_blank" class="social-link" title="YouTube">
                                    <i class="fab fa-youtube"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($dev['discord']): ?>
                                <a href="<?php echo $dev['discord']; ?>" target="_blank" class="social-link" title="Discord">
                                    <i class="fab fa-discord"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <!-- Descrição Completa -->
    <?php if ($dev['descricao']): ?>
    <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 15px; padding: 30px; margin-bottom: 40px;">
        <h2 style="margin-bottom: 20px;"><i class="fas fa-info-circle"></i> Sobre o Estúdio</h2>
        <div style="line-height: 1.8; color: var(--text-secondary);">
            <?php echo nl2br(sanitize($dev['descricao'])); ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Jogo em Destaque -->
    <?php if ($jogo_destaque): ?>
    <div class="featured-game-section">
        <h2 style="margin-bottom: 30px;"><i class="fas fa-star"></i> Jogo em Destaque</h2>
        <div class="featured-game-content">
            <div class="featured-game-image">
                <img src="<?php echo SITE_URL . ($jogo_destaque['imagem_banner'] ?: $jogo_destaque['imagem_capa']); ?>" 
                     alt="<?php echo sanitize($jogo_destaque['titulo']); ?>">
            </div>
            
            <div class="featured-game-info">
                <h2><?php echo sanitize($jogo_destaque['titulo']); ?></h2>
                <p style="color: var(--text-secondary); margin-bottom: 20px;">
                    <?php echo sanitize($jogo_destaque['descricao_curta']); ?>
                </p>
                
                <?php if ($jogo_destaque['nota_media'] > 0): ?>
                <div style="margin-bottom: 15px;">
                    <span style="color: #ffc107;">
                        <?php
                        for ($i = 1; $i <= 5; $i++) {
                            echo $i <= round($jogo_destaque['nota_media']) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                        }
                        ?>
                    </span>
                    <span style="margin-left: 10px; color: var(--text-secondary);">
                        (<?php echo $jogo_destaque['total_avaliacoes']; ?> avaliações)
                    </span>
                </div>
                <?php endif; ?>
                
                <div class="featured-price">
                    <?php
                    $preco = $jogo_destaque['em_promocao'] && $jogo_destaque['preco_promocional_centavos']
                        ? $jogo_destaque['preco_promocional_centavos']
                        : $jogo_destaque['preco_centavos'];
                    echo $preco == 0 ? 'GRÁTIS' : formatPrice($preco);
                    ?>
                </div>
                
                <a href="<?php echo SITE_URL; ?>/pages/jogo.php?slug=<?php echo $jogo_destaque['slug']; ?>" 
                   class="btn btn-primary btn-lg">
                    <i class="fas fa-gamepad"></i> Ver Detalhes
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Todos os Jogos -->
    <div>
        <h2 style="font-size: 28px; margin-bottom: 25px;">
            <i class="fas fa-gamepad"></i> Todos os Jogos (<?php echo $total_jogos; ?>)
        </h2>
        
        <?php if (count($jogos) > 0): ?>
            <div class="grid grid-4">
                <?php foreach ($jogos as $jogo): ?>
                    <?php renderGameCard($jogo, $pdo, $user_id); ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 60px 20px; background: var(--bg-secondary); border-radius: 15px;">
                <i class="fas fa-gamepad" style="font-size: 64px; color: var(--text-secondary); margin-bottom: 20px;"></i>
                <h3>Nenhum jogo publicado ainda</h3>
                <p style="color: var(--text-secondary);">Este desenvolvedor ainda não publicou jogos.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleFollow(devId, button) {
    const isFollowing = button.classList.contains('btn-secondary');
    const action = isFollowing ? 'unfollow' : 'follow';
    
    fetch('<?php echo SITE_URL; ?>/api/follow-dev.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            desenvolvedor_id: devId,
            action: action
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.action == 'followed') {
                button.classList.remove('btn-primary');
                button.classList.add('btn-secondary');
                button.innerHTML = '<i class="fas fa-check"></i> Seguindo';
            } else {
                button.classList.remove('btn-secondary');
                button.classList.add('btn-primary');
                button.innerHTML = '<i class="fas fa-plus"></i> Seguir';
            }
            showNotification(data.message, 'success');
        } else {
            showNotification(data.message || 'Erro ao processar', 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showNotification('Erro ao processar', 'error');
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>