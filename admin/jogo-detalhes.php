<?php
// ============================================
// admin/jogo-detalhes.php (NOVA PÁGINA)
// ============================================
require_once '../config/config.php';
require_once '../config/database.php';
requireLogin();
if (getUserType() !== 'admin') { header('Location: ' . SITE_URL . '/pages/home.php'); exit; }

if (!isset($_GET['id'])) {
    header('Location: ' . SITE_URL . '/admin/jogos.php');
    exit;
}

$database = new Database();
$pdo = $database->getConnection();
$jogo_id = $_GET['id'];

// Buscar jogo
$stmt = $pdo->prepare("
    SELECT j.*, d.nome_estudio, d.slug as dev_slug
    FROM jogo j
    JOIN desenvolvedor d ON j.desenvolvedor_id = d.id
    WHERE j.id = ?
");
$stmt->execute([$jogo_id]);
$jogo = $stmt->fetch();

if (!$jogo) {
    header('Location: ' . SITE_URL . '/admin/jogos.php');
    exit;
}

// Buscar categorias
$stmt = $pdo->prepare("
    SELECT c.nome 
    FROM categoria c
    JOIN jogo_categoria jc ON c.id = jc.categoria_id
    WHERE jc.jogo_id = ?
");
$stmt->execute([$jogo_id]);
$categorias = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Buscar plataformas
$stmt = $pdo->prepare("
    SELECT p.nome 
    FROM plataforma p
    JOIN jogo_plataforma jp ON p.id = jp.plataforma_id
    WHERE jp.jogo_id = ?
");
$stmt->execute([$jogo_id]);
$plataformas = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Buscar tags
$stmt = $pdo->prepare("
    SELECT t.nome 
    FROM tag t
    JOIN jogo_tag jt ON t.id = jt.tag_id
    WHERE jt.jogo_id = ?
");
$stmt->execute([$jogo_id]);
$tags = $stmt->fetchAll(PDO::FETCH_COLUMN);

$page_title = 'Detalhes: ' . $jogo['titulo'] . ' - Admin - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo SITE_URL; ?>/admin/assets/css/admin.css">

<div class="container">
    <div class="admin-layout">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="admin-header">
                <div>
                    <a href="<?php echo SITE_URL; ?>/admin/jogos.php" style="color: var(--text-secondary); margin-bottom: 10px; display: inline-block;">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                    <h1 class="admin-title">
                        <i class="fas fa-gamepad"></i> <?php echo sanitize($jogo['titulo']); ?>
                    </h1>
                </div>
                <?php
                $status_colors = [
                    'rascunho' => 'secondary',
                    'em_revisao' => 'warning',
                    'publicado' => 'success',
                    'suspenso' => 'danger',
                    'removido' => 'danger'
                ];
                ?>
                <span class="badge badge-<?php echo $status_colors[$jogo['status']]; ?>" style="font-size: 16px; padding: 8px 16px;">
                    <?php echo ucfirst(str_replace('_', ' ', $jogo['status'])); ?>
                </span>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
                <!-- Informações Principais -->
                <div>
                    <div class="form-section">
                        <h2>Informações do Jogo</h2>
                        
                        <?php if ($jogo['imagem_capa']): ?>
                        <div style="margin-bottom: 20px;">
                            <img src="<?php echo SITE_URL . $jogo['imagem_capa']; ?>" 
                                 style="max-width: 300px; border-radius: 8px;">
                        </div>
                        <?php endif; ?>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="color: var(--text-secondary); font-size: 13px; display: block; margin-bottom: 5px;">Desenvolvedor</label>
                            <strong><?php echo sanitize($jogo['nome_estudio']); ?></strong>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="color: var(--text-secondary); font-size: 13px; display: block; margin-bottom: 5px;">Descrição Curta</label>
                            <p><?php echo sanitize($jogo['descricao_curta']); ?></p>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="color: var(--text-secondary); font-size: 13px; display: block; margin-bottom: 5px;">Descrição Completa</label>
                            <p style="white-space: pre-wrap;"><?php echo sanitize($jogo['descricao_completa']); ?></p>
                        </div>
                        
                        <?php if ($jogo['motivo_rejeicao']): ?>
                        <div style="background: rgba(220,53,69,0.1); border: 1px solid var(--danger); padding: 15px; border-radius: 8px; margin-top: 20px;">
                            <strong style="color: var(--danger);">Motivo da Rejeição:</strong>
                            <p style="margin-top: 10px;"><?php echo sanitize($jogo['motivo_rejeicao']); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Informações Técnicas -->
                <div>
                    <div class="form-section">
                        <h2>Detalhes</h2>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="color: var(--text-secondary); font-size: 13px;">Preço</label>
                            <div><strong><?php echo formatPrice($jogo['preco_centavos']); ?></strong></div>
                        </div>
                        
                        <?php if ($jogo['em_promocao']): ?>
                        <div style="margin-bottom: 15px;">
                            <label style="color: var(--text-secondary); font-size: 13px;">Preço Promocional</label>
                            <div><strong style="color: var(--success);"><?php echo formatPrice($jogo['preco_promocional_centavos']); ?></strong></div>
                        </div>
                        <?php endif; ?>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="color: var(--text-secondary); font-size: 13px;">Classificação</label>
                            <div><span class="badge badge-info"><?php echo $jogo['classificacao_etaria']; ?></span></div>
                        </div>
                        
                        <?php if (count($categorias) > 0): ?>
                        <div style="margin-bottom: 15px;">
                            <label style="color: var(--text-secondary); font-size: 13px;">Categorias</label>
                            <div style="display: flex; flex-wrap: wrap; gap: 5px; margin-top: 5px;">
                                <?php foreach ($categorias as $cat): ?>
                                    <span class="badge badge-info"><?php echo sanitize($cat); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (count($plataformas) > 0): ?>
                        <div style="margin-bottom: 15px;">
                            <label style="color: var(--text-secondary); font-size: 13px;">Plataformas</label>
                            <div style="display: flex; flex-wrap: wrap; gap: 5px; margin-top: 5px;">
                                <?php foreach ($plataformas as $plat): ?>
                                    <span class="badge badge-secondary"><?php echo sanitize($plat); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (count($tags) > 0): ?>
                        <div style="margin-bottom: 15px;">
                            <label style="color: var(--text-secondary); font-size: 13px;">Tags</label>
                            <div style="display: flex; flex-wrap: wrap; gap: 5px; margin-top: 5px;">
                                <?php foreach ($tags as $tag): ?>
                                    <span class="badge badge-secondary"><?php echo sanitize($tag); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <hr style="border-color: var(--border); margin: 20px 0;">
                        
                        <div class="stat-card" style="margin-bottom: 15px; padding: 15px;">
                            <div style="font-size: 13px; color: var(--text-secondary);">Vendas</div>
                            <div style="font-size: 24px; font-weight: 700;"><?php echo $jogo['total_vendas']; ?></div>
                        </div>
                        
                        <div class="stat-card" style="margin-bottom: 15px; padding: 15px;">
                            <div style="font-size: 13px; color: var(--text-secondary);">Avaliações</div>
                            <div style="font-size: 24px; font-weight: 700;">
                                <i class="fas fa-star" style="color: var(--warning);"></i>
                                <?php echo number_format($jogo['nota_media'], 1); ?>
                                <small style="font-size: 14px; color: var(--text-secondary);">(<?php echo $jogo['total_avaliacoes']; ?>)</small>
                            </div>
                        </div>
                        
                        <div style="margin-top: 20px;">
                            <a href="<?php echo SITE_URL; ?>/pages/jogo.php?slug=<?php echo $jogo['slug']; ?>" 
                               class="btn btn-primary btn-block" target="_blank">
                                <i class="fas fa-external-link-alt"></i> Ver na Loja
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>