<?php
// ===== user/download-jogo.php =====
require_once '../config/config.php';
require_once '../config/database.php';

requireLogin();

$database = new Database();
$pdo = $database->getConnection();
$user_id = $_SESSION['user_id'];

$jogo_id = $_GET['jogo_id'] ?? null;
$plataforma_id = $_GET['plataforma'] ?? null;

if (!$jogo_id) {
    header('Location: ' . SITE_URL . '/user/biblioteca.php');
    exit;
}

// Verificar se o usuário possui o jogo
$stmt = $pdo->prepare("SELECT id FROM biblioteca WHERE usuario_id = ? AND jogo_id = ?");
$stmt->execute([$user_id, $jogo_id]);
if (!$stmt->fetch()) {
    $_SESSION['error'] = 'Você não possui este jogo';
    header('Location: ' . SITE_URL . '/user/biblioteca.php');
    exit;
}

// Buscar jogo
$stmt = $pdo->prepare("SELECT * FROM jogo WHERE id = ?");
$stmt->execute([$jogo_id]);
$jogo = $stmt->fetch();

if (!$jogo) {
    header('Location: ' . SITE_URL . '/user/biblioteca.php');
    exit;
}

// Buscar plataformas disponíveis
$stmt = $pdo->prepare("
    SELECT p.* FROM plataforma p
    INNER JOIN jogo_plataforma jp ON p.id = jp.plataforma_id
    WHERE jp.jogo_id = ?
");
$stmt->execute([$jogo_id]);
$plataformas = $stmt->fetchAll();

// Buscar arquivos disponíveis
$arquivos = [];
if ($plataforma_id) {
    $stmt = $pdo->prepare("
        SELECT * FROM arquivo_jogo 
        WHERE jogo_id = ? AND plataforma_id = ? AND ativo = 1
        ORDER BY criado_em DESC
    ");
    $stmt->execute([$jogo_id, $plataforma_id]);
    $arquivos = $stmt->fetchAll();
}

$page_title = 'Download: ' . $jogo['titulo'] . ' - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<style>
.download-page {
    padding: 30px 0;
    max-width: 800px;
    margin: 0 auto;
}

.game-download-header {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 30px;
    display: flex;
    gap: 25px;
    align-items: center;
}

.game-download-cover {
    width: 120px;
    border-radius: 8px;
}

.game-download-info h1 {
    font-size: 28px;
    margin-bottom: 10px;
}

.platform-selector {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 30px;
}

.platform-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-top: 20px;
}

.platform-card {
    background: var(--bg-primary);
    border: 2px solid var(--border);
    border-radius: 10px;
    padding: 25px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
}

.platform-card:hover {
    border-color: var(--accent);
    transform: translateY(-5px);
}

.platform-card.selected {
    border-color: var(--accent);
    background: rgba(76,139,245,0.1);
}

.platform-icon {
    font-size: 48px;
    margin-bottom: 10px;
    display: block;
}

.download-files {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 15px;
    padding: 30px;
}

.file-item {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.file-info h3 {
    font-size: 16px;
    margin-bottom: 8px;
}

.file-meta {
    font-size: 13px;
    color: var(--text-secondary);
}

.file-meta span {
    margin-right: 15px;
}

@media (max-width: 768px) {
    .platform-grid {
        grid-template-columns: 1fr;
    }
    
    .game-download-header {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<div class="container">
    <div class="download-page">
        <!-- Header -->
        <div class="game-download-header">
            <img src="<?php echo SITE_URL . ($jogo['imagem_capa'] ?: '/assets/images/no-image.png'); ?>" 
                 class="game-download-cover" 
                 alt="<?php echo sanitize($jogo['titulo']); ?>">
            <div class="game-download-info">
                <h1><?php echo sanitize($jogo['titulo']); ?></h1>
                <p style="color: var(--text-secondary);">
                    Versão: <?php echo $jogo['versao_atual']; ?>
                    <?php if ($jogo['tamanho_mb']): ?>
                        | Tamanho: <?php echo $jogo['tamanho_mb']; ?> MB
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <!-- Seletor de Plataforma -->
        <div class="platform-selector">
            <h2><i class="fas fa-laptop"></i> Selecione a Plataforma</h2>
            <div class="platform-grid">
                <?php foreach ($plataformas as $plat): ?>
                <a href="?jogo_id=<?php echo $jogo_id; ?>&plataforma=<?php echo $plat['id']; ?>" 
                   class="platform-card <?php echo $plataforma_id == $plat['id'] ? 'selected' : ''; ?>">
                    <i class="<?php echo $plat['icone']; ?> platform-icon"></i>
                    <strong><?php echo $plat['nome']; ?></strong>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Arquivos para Download -->
        <?php if ($plataforma_id && count($arquivos) > 0): ?>
        <div class="download-files">
            <h2><i class="fas fa-download"></i> Arquivos Disponíveis</h2>
            <p style="color: var(--text-secondary); margin-bottom: 20px;">
                Escolha a versão que deseja baixar
            </p>
            
            <?php foreach ($arquivos as $arquivo): ?>
            <div class="file-item">
                <div class="file-info">
                    <h3><?php echo sanitize($arquivo['nome_arquivo']); ?></h3>
                    <div class="file-meta">
                        <span><i class="fas fa-code-branch"></i> v<?php echo $arquivo['versao']; ?></span>
                        <span><i class="fas fa-hdd"></i> <?php echo round($arquivo['tamanho_bytes'] / 1024 / 1024, 2); ?> MB</span>
                        <span><i class="fas fa-download"></i> <?php echo $arquivo['downloads']; ?> downloads</span>
                    </div>
                    <?php if ($arquivo['changelog']): ?>
                        <p style="margin-top: 10px; font-size: 13px; color: var(--text-secondary);">
                            <i class="fas fa-list"></i> <?php echo sanitize($arquivo['changelog']); ?>
                        </p>
                    <?php endif; ?>
                </div>
                
                <a href="<?php echo SITE_URL . $arquivo['caminho']; ?>" 
                   class="btn btn-primary"
                   download>
                    <i class="fas fa-download"></i> Baixar
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php elseif ($plataforma_id): ?>
            <div style="text-align: center; padding: 60px 20px; background: var(--bg-secondary); border-radius: 15px;">
                <i class="fas fa-exclamation-circle" style="font-size: 64px; color: var(--warning); margin-bottom: 20px;"></i>
                <h3>Nenhum arquivo disponível</h3>
                <p style="color: var(--text-secondary);">
                    Ainda não há arquivos para esta plataforma.
                </p>
            </div>
        <?php endif; ?>
        
        <div style="margin-top: 30px; text-align: center;">
            <a href="<?php echo SITE_URL; ?>/user/biblioteca.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Voltar para Biblioteca
            </a>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>