<?php

require_once '../config/config.php';
require_once '../config/database.php';

requireLogin();

if (getUserType() !== 'admin') {
    header('Location: ' . SITE_URL . '/pages/home.php');
    exit;
}

$database = new Database();
$pdo = $database->getConnection();

$jogo_id = $_GET['id'] ?? $_GET['jogo_id'] ?? null;

if (!$jogo_id || !is_numeric($jogo_id)) {
    $_SESSION['admin_error'] = 'ID do jogo não informado ou inválido.';
    header('Location: ' . SITE_URL . '/admin/jogos.php');
    exit;
}

$success = '';
$error = '';

// Funções auxiliares
function formatFileSizeCustom($bytes) {
    if (!$bytes) return '0 bytes';
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' bytes';
}

if (!function_exists('formatPrice')) {
    function formatPrice($centavos) {
        if ($centavos == 0) return 'Gratuito';
        return 'R$ ' . number_format($centavos / 100, 2, ',', '.');
    }
}

if (!function_exists('sanitize')) {
    function sanitize($str) {
        return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// Processar ações POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        switch ($action) {
            case 'aprovar':
                $stmt = $pdo->prepare("UPDATE jogo SET status = 'publicado', atualizado_em = NOW(), publicado_em = NOW(), motivo_rejeicao = NULL WHERE id = ?");
                $stmt->execute([$jogo_id]);
                $success = 'Jogo aprovado e publicado com sucesso!';
                break;
                
            case 'rejeitar':
                $motivo = trim($_POST['motivo'] ?? '');
                if (empty($motivo)) {
                    $error = 'Informe o motivo da rejeição.';
                } else {
                    $stmt = $pdo->prepare("UPDATE jogo SET status = 'rascunho', atualizado_em = NOW(), motivo_rejeicao = ? WHERE id = ?");
                    $stmt->execute([$motivo, $jogo_id]);
                    $success = 'Jogo rejeitado. O desenvolvedor foi notificado.';
                }
                break;
                
            case 'suspender':
                $motivo = trim($_POST['motivo'] ?? '');
                $stmt = $pdo->prepare("UPDATE jogo SET status = 'suspenso', atualizado_em = NOW(), motivo_rejeicao = ? WHERE id = ?");
                $stmt->execute([$motivo, $jogo_id]);
                $success = 'Jogo suspenso com sucesso.';
                break;
                
            case 'reativar':
                $stmt = $pdo->prepare("UPDATE jogo SET status = 'publicado', atualizado_em = NOW(), motivo_rejeicao = NULL WHERE id = ?");
                $stmt->execute([$jogo_id]);
                $success = 'Jogo reativado com sucesso!';
                break;
                
            case 'remover':
                $stmt = $pdo->prepare("UPDATE jogo SET status = 'removido', atualizado_em = NOW() WHERE id = ?");
                $stmt->execute([$jogo_id]);
                $success = 'Jogo removido da plataforma.';
                break;
                
            case 'toggle_destaque':
                $stmt = $pdo->prepare("UPDATE jogo SET destaque = NOT destaque, atualizado_em = NOW() WHERE id = ?");
                $stmt->execute([$jogo_id]);
                $success = 'Destaque atualizado!';
                break;
        }
    } catch (Exception $e) {
        $error = 'Erro ao processar ação: ' . $e->getMessage();
    }
}

// Buscar jogo completo
try {
    $stmt = $pdo->prepare("
        SELECT j.*,
               d.nome_estudio, d.slug as dev_slug, d.id as dev_id,
               d.descricao_curta as dev_descricao, d.logo_url as dev_logo,
               d.website as dev_website, d.twitter as dev_twitter,
               d.instagram as dev_instagram, d.discord as dev_discord,
               d.youtube as dev_youtube, d.verificado as dev_verificado,
               d.status as dev_status
        FROM jogo j
        LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id
        WHERE j.id = ?
    ");
    $stmt->execute([$jogo_id]);
    $jogo = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro na query: " . $e->getMessage());
}

if (!$jogo) {
    $_SESSION['admin_error'] = 'Jogo não encontrado.';
    header('Location: ' . SITE_URL . '/admin/jogos.php');
    exit;
}

// Buscar email do desenvolvedor
$dev_email = '';
if ($jogo['dev_id']) {
    try {
        $stmt = $pdo->prepare("SELECT u.email FROM desenvolvedor d JOIN usuario u ON d.usuario_id = u.id WHERE d.id = ?");
        $stmt->execute([$jogo['dev_id']]);
        $dev = $stmt->fetch(PDO::FETCH_ASSOC);
        $dev_email = $dev['email'] ?? '';
    } catch (PDOException $e) {}
}

// Buscar categorias
$categorias = [];
try {
    $stmt = $pdo->prepare("SELECT c.id, c.nome FROM categoria c JOIN jogo_categoria jc ON c.id = jc.categoria_id WHERE jc.jogo_id = ? ORDER BY c.nome");
    $stmt->execute([$jogo_id]);
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Buscar plataformas
$plataformas = [];
try {
    $stmt = $pdo->prepare("SELECT p.id, p.nome FROM plataforma p JOIN jogo_plataforma jp ON p.id = jp.plataforma_id WHERE jp.jogo_id = ?");
    $stmt->execute([$jogo_id]);
    $plataformas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Buscar tags
$tags = [];
try {
    $stmt = $pdo->prepare("SELECT t.id, t.nome FROM tag t JOIN jogo_tag jt ON t.id = jt.tag_id WHERE jt.jogo_id = ? ORDER BY t.nome");
    $stmt->execute([$jogo_id]);
    $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Buscar screenshots
$screenshots = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM jogo_imagens WHERE jogo_id = ? ORDER BY id");
    $stmt->execute([$jogo_id]);
    $screenshots = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM jogo_screenshots WHERE jogo_id = ? ORDER BY id");
        $stmt->execute([$jogo_id]);
        $screenshots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {}
}

// Buscar arquivos
$arquivos = [];
try {
    $stmt = $pdo->prepare("SELECT ja.*, p.nome as plataforma_nome FROM arquivo_jogo ja LEFT JOIN plataforma p ON ja.plataforma_id = p.id WHERE ja.jogo_id = ? ORDER BY ja.id DESC");
    $stmt->execute([$jogo_id]);
    $arquivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Status config
$status_config = [
    'rascunho' => ['label' => 'Rascunho', 'color' => 'var(--text-secondary)', 'icon' => 'fa-file-alt'],
    'em_revisao' => ['label' => 'Em Revisão', 'color' => 'var(--warning)', 'icon' => 'fa-clock'],
    'publicado' => ['label' => 'Publicado', 'color' => 'var(--success)', 'icon' => 'fa-check-circle'],
    'suspenso' => ['label' => 'Suspenso', 'color' => 'var(--danger)', 'icon' => 'fa-ban'],
    'removido' => ['label' => 'Removido', 'color' => 'var(--danger)', 'icon' => 'fa-trash']
];
$current_status = $status_config[$jogo['status']] ?? $status_config['rascunho'];

$page_title = 'Detalhes: ' . ($jogo['titulo'] ?? 'Jogo') . ' - Admin';
require_once '../includes/header.php';
?>

<link rel="stylesheet" href="<?= SITE_URL; ?>/admin/assets/css/admin.css">
<style>
/* Layout Principal */
.detail-page { padding: 20px 0; }
.detail-grid { display: grid; grid-template-columns: 1fr 340px; gap: 20px; }

/* Cards */
.card {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 10px;
    margin-bottom: 16px;
}
.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 18px;
    border-bottom: 1px solid var(--border);
}
.card-header h3 {
    font-size: 14px;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-primary);
}
.card-header h3 i { color: var(--accent); font-size: 14px; }
.card-body { padding: 16px 18px; }
.card-compact .card-body { padding: 12px 16px; }

/* Header da página */
.page-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 20px;
    gap: 20px;
    flex-wrap: wrap;
}
.page-top-left h1 {
    font-size: 22px;
    font-weight: 700;
    margin: 0 0 8px 0;
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--text-primary);
}
.back-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--text-secondary);
    text-decoration: none;
    font-size: 13px;
    margin-bottom: 12px;
    transition: color 0.2s;
}
.back-link:hover { color: var(--accent); }

.side_section {
    display: flex;
    flex-direction: column;
    position: sticky;
    height: fit-content;
    top: 20px;
    gap: 16px;
}

/* Status Badge */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    background: var(--bg-primary);
    border: 1px solid var(--border);
}

/* Info Grid Compacto */
.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}
.info-grid.cols-3 { grid-template-columns: repeat(3, 1fr); }
.info-grid.cols-1 { grid-template-columns: 1fr; }
.info-item {
    background: var(--bg-primary);
    padding: 12px 14px;
    border-radius: 8px;
}
.info-item .label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-secondary);
    margin-bottom: 4px;
}
.info-item .value {
    font-size: 14px;
    color: var(--text-primary);
    font-weight: 500;
}
.info-item .value.large {
    font-size: 20px;
    font-weight: 700;
}
.info-item .value.mono {
    font-family: 'JetBrains Mono', monospace;
    font-size: 12px;
    color: var(--text-secondary);
}

/* Tags */
.tags-wrap { display: flex; flex-wrap: wrap; gap: 6px; }
.tag {
    background: var(--bg-primary);
    color: var(--text-secondary);
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    border: 1px solid var(--border);
}
.tag.accent { color: var(--accent); border-color: rgba(0, 174, 255, 0.3); }

/* Read More/Less */
.expandable-text {
    position: relative;
    overflow: hidden;
    transition: max-height 0.3s ease;
}
.expandable-text.collapsed { max-height: 100px; }
.expandable-text.expanded { max-height: 2000px; }
.expandable-text .gradient {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 40px;
    background: linear-gradient(transparent, var(--bg-secondary));
    pointer-events: none;
}
.expandable-text.expanded .gradient { display: none; }
.expand-btn {
    background: none;
    border: none;
    color: var(--accent);
    font-size: 13px;
    cursor: pointer;
    padding: 8px 0 0 0;
    display: flex;
    align-items: center;
    gap: 5px;
}
.expand-btn:hover { text-decoration: underline; }

/* Stats */
.stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
}
.stat-item {
    text-align: center;
    padding: 14px 8px;
    background: var(--bg-primary);
    border-radius: 8px;
}
.stat-item .num {
    font-size: 22px;
    font-weight: 700;
    color: var(--text-primary);
}
.stat-item .txt {
    font-size: 11px;
    color: var(--text-secondary);
    text-transform: uppercase;
    margin-top: 2px;
}

/* Developer Info */
.dev-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: var(--bg-primary);
    border-radius: 8px;
}
.dev-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: var(--accent);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 16px;
    flex-shrink: 0;
}
.dev-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
.dev-info { flex: 1; min-width: 0; }
.dev-info .name {
    font-weight: 600;
    font-size: 14px;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 6px;
}
.dev-info .name .verified { color: var(--accent); font-size: 12px; }
.dev-info .email { font-size: 12px; color: var(--text-secondary); }
.dev-socials { display: flex; gap: 8px; margin-top: 10px; }
.dev-socials a {
    width: 30px;
    height: 30px;
    border-radius: 6px;
    background: var(--bg-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-secondary);
    font-size: 13px;
    transition: all 0.2s;
}
.dev-socials a:hover { background: var(--accent); color: white; }

/* Media */
.cover-preview {
    width: 100%;
    aspect-ratio: 16/9;
    border-radius: 8px;
    overflow: hidden;
    background: var(--bg-primary);
    margin-bottom: 12px;
}
.cover-preview img { width: 100%; height: 100%; object-fit: cover; }
.screenshots-mini {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
}
.screenshot-thumb {
    aspect-ratio: 16/9;
    border-radius: 6px;
    overflow: hidden;
    cursor: pointer;
    border: 2px solid transparent;
    transition: border-color 0.2s;
}
.screenshot-thumb:hover { border-color: var(--accent); }
.screenshot-thumb img { width: 100%; height: 100%; object-fit: cover; }

/* Arquivos */
.file-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    background: var(--bg-primary);
    border-radius: 8px;
    margin-bottom: 8px;
}
.file-row:last-child { margin-bottom: 0; }
.file-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: var(--bg-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--accent);
}
.file-info { flex: 1; }
.file-info .name { font-size: 13px; font-weight: 500; color: var(--text-primary); }
.file-info .meta { font-size: 11px; color: var(--text-secondary); margin-top: 2px; }

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
    text-decoration: none;
}
.btn-block { width: 100%; }
.btn-sm { padding: 8px 14px; font-size: 12px; }
.btn-success { background: var(--success); color: white; }
.btn-success:hover { filter: brightness(1.1); }
.btn-danger { background: var(--danger); color: white; }
.btn-danger:hover { filter: brightness(1.1); }
.btn-warning { background: var(--warning); color: #000; }
.btn-warning:hover { filter: brightness(1.1); }
.btn-primary { background: var(--accent); color: white; }
.btn-primary:hover { filter: brightness(1.1); }
.btn-secondary { background: var(--bg-primary); color: var(--text-primary); border: 1px solid var(--border); }
.btn-secondary:hover { border-color: var(--accent); }
.btn-outline-danger { background: transparent; color: var(--danger); border: 1px solid var(--danger); }
.btn-outline-danger:hover { background: var(--danger); color: white; }
.btn-group { display: flex; flex-direction: column; gap: 8px; }

/* Alerts */
.alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
}
.alert-success { background: rgba(40, 167, 69, 0.15); border: 1px solid rgba(40, 167, 69, 0.3); color: var(--success); }
.alert-error { background: rgba(220, 53, 69, 0.15); border: 1px solid rgba(220, 53, 69, 0.3); color: var(--danger); }
.alert-warning { background: rgba(255, 193, 7, 0.15); border: 1px solid rgba(255, 193, 7, 0.3); color: var(--warning); }

/* Rejection Box */
.rejection-box {
    background: rgba(220, 53, 69, 0.1);
    border: 1px solid rgba(220, 53, 69, 0.3);
    border-radius: 8px;
    padding: 12px 14px;
    margin-top: 12px;
}
.rejection-box .title { color: var(--danger); font-size: 12px; font-weight: 600; margin-bottom: 6px; display: flex; align-items: center; gap: 6px; }
.rejection-box .content { color: var(--text-secondary); font-size: 13px; }

/* Timestamps */
.timestamps {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    font-size: 12px;
    color: var(--text-secondary);
    padding-top: 12px;
    border-top: 1px solid var(--border);
    margin-top: 12px;
}
.timestamps span { display: flex; align-items: center; gap: 5px; }

/* Modal Flutuante */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    opacity: 0;
    visibility: hidden;
    transition: all 0.25s ease;
    backdrop-filter: blur(4px);
}
.modal-overlay.active { opacity: 1; visibility: visible; }
.modal-container {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 14px;
    width: 90%;
    max-width: 440px;
    transform: scale(0.9) translateY(-20px);
    transition: transform 0.25s ease;
    box-shadow: 0 20px 60px rgba(0,0,0,0.5);
}
.modal-overlay.active .modal-container { transform: scale(1) translateY(0); }
.modal-top {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 14px;
}
.modal-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}
.modal-icon.success { background: rgba(40, 167, 69, 0.15); color: var(--success); }
.modal-icon.warning { background: rgba(255, 193, 7, 0.15); color: var(--warning); }
.modal-icon.danger { background: rgba(220, 53, 69, 0.15); color: var(--danger); }
.modal-title { font-size: 18px; font-weight: 700; color: var(--text-primary); }
.modal-subtitle { font-size: 13px; color: var(--text-secondary); margin-top: 2px; }
.modal-body { padding: 20px 24px; }
.modal-body p { color: var(--text-secondary); font-size: 14px; margin: 0 0 16px 0; line-height: 1.5; }
.modal-body textarea {
    width: 100%;
    padding: 12px 14px;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-primary);
    font-size: 14px;
    resize: vertical;
    min-height: 90px;
    font-family: inherit;
}
.modal-body textarea:focus { outline: none; border-color: var(--accent); }
.modal-body textarea::placeholder { color: var(--text-secondary); }
.modal-body label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 8px; color: var(--text-primary); }
.modal-footer {
    padding: 16px 24px;
    border-top: 1px solid var(--border);
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

/* Lightbox para Screenshots */
.lightbox {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.95);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s;
}
.lightbox.active { opacity: 1; visibility: visible; }
.lightbox img { max-width: 90%; max-height: 90%; border-radius: 8px; }
.lightbox-close {
    position: absolute;
    top: 20px;
    right: 20px;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--bg-secondary);
    border: none;
    color: var(--text-primary);
    font-size: 18px;
    cursor: pointer;
}

/* Requisitos Toggle */
.req-tabs { display: flex; gap: 8px; margin-bottom: 12px; }
.req-tab {
    padding: 8px 14px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    background: var(--bg-primary);
    color: var(--text-secondary);
    border: 1px solid var(--border);
    transition: all 0.2s;
}
.req-tab.active { background: var(--accent); color: white; border-color: var(--accent); }
.req-content { display: none; }
.req-content.active { display: block; }

@media (max-width: 992px) {
    .detail-grid { grid-template-columns: 1fr; }
    .stats-row { grid-template-columns: repeat(2, 1fr); }
    .info-grid.cols-3 { grid-template-columns: repeat(2, 1fr); }
}
</style>

<div class="container">
    <div class="admin-layout">
        <?php require_once 'includes/sidebar.php'; ?>

        <div class="admin-content">
            <div class="detail-page">
                <a href="<?= SITE_URL; ?>/admin/jogos.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Voltar para Jogos
                </a>

                <!-- Header -->
                <div class="page-top">
                    <div class="page-top-left">
                        <h1>
                            <?= sanitize($jogo['titulo'] ?? 'Sem título'); ?>
                            <?php if (!empty($jogo['destaque'])): ?>
                                <i class="fas fa-star" style="color: var(--warning); font-size: 18px;" title="Em Destaque"></i>
                            <?php endif; ?>
                        </h1>
                        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                            <span class="status-badge" style="color: <?= $current_status['color'] ?>;">
                                <i class="fas <?= $current_status['icon'] ?>"></i>
                                <?= $current_status['label'] ?>
                            </span>
                            <span style="color: var(--text-secondary); font-size: 12px;">
                                ID: #<?= $jogo['id'] ?>
                            </span>
                        </div>
                    </div>
                    <div class="page-top-right">
                        <?php if (!empty($jogo['slug'])): ?>
                        <a href="<?= SITE_URL; ?>/pages/jogo.php?slug=<?= $jogo['slug']; ?>" class="btn btn-secondary btn-sm" target="_blank">
                            <i class="fas fa-external-link-alt"></i> Ver na Loja
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Alerts -->
                <?php if ($success): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
                <?php endif; ?>
                <?php if (($jogo['status'] ?? '') === 'em_revisao'): ?>
                    <div class="alert alert-warning"><i class="fas fa-clock"></i> <strong>Aguardando revisão.</strong> Analise e aprove ou rejeite este jogo.</div>
                <?php endif; ?>

                <div class="detail-grid">
                    <!-- Coluna Principal -->
                    <div class="main-column">
                        <!-- Estatísticas -->
                        <div class="card">
                            <div class="card-body" style="padding: 16px;">
                                <div class="stats-row">
                                    <div class="stat-item">
                                        <div class="num"><?= number_format($jogo['total_vendas'] ?? 0); ?></div>
                                        <div class="txt">Vendas</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="num"><?= number_format($jogo['total_downloads'] ?? 0); ?></div>
                                        <div class="txt">Downloads</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="num"><?= number_format($jogo['total_visualizacoes'] ?? 0); ?></div>
                                        <div class="txt">Views</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="num" style="display: flex; align-items: center; justify-content: center; gap: 4px;">
                                            <i class="fas fa-star" style="color: var(--warning); font-size: 16px;"></i>
                                            <?= number_format($jogo['nota_media'] ?? 0, 1); ?>
                                        </div>
                                        <div class="txt"><?= $jogo['total_avaliacoes'] ?? 0 ?> avaliações</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Informações Básicas -->
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-info-circle"></i> Informações</h3>
                            </div>
                            <div class="card-body">
                                <div class="info-grid cols-3">
                                    <div class="info-item">
                                        <div class="label">Preço</div>
                                        <div class="value large"><?= formatPrice($jogo['preco_centavos'] ?? 0); ?></div>
                                    </div>
                                    <?php if (!empty($jogo['em_promocao']) && !empty($jogo['preco_promocional_centavos'])): ?>
                                    <div class="info-item">
                                        <div class="label">Preço Promocional</div>
                                        <div class="value large" style="color: var(--success);"><?= formatPrice($jogo['preco_promocional_centavos']); ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <div class="info-item">
                                        <div class="label">Versão</div>
                                        <div class="value"><?= sanitize($jogo['versao_atual'] ?? '1.0'); ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="label">Classificação</div>
                                        <div class="value"><?= sanitize($jogo['classificacao_etaria'] ?? 'L'); ?></div>
                                    </div>
                                    <?php if (!empty($jogo['tamanho_mb'])): ?>
                                    <div class="info-item">
                                        <div class="label">Tamanho</div>
                                        <div class="value"><?= number_format($jogo['tamanho_mb'], 0, ',', '.'); ?> MB</div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($jogo['data_lancamento'])): ?>
                                    <div class="info-item">
                                        <div class="label">Lançamento</div>
                                        <div class="value"><?= date('d/m/Y', strtotime($jogo['data_lancamento'])); ?></div>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <div class="info-grid cols-1" style="margin-top: 12px;">
                                    <div class="info-item">
                                        <div class="label">Slug</div>
                                        <div class="value mono"><?= sanitize($jogo['slug'] ?? ''); ?></div>
                                    </div>
                                </div>

                                <?php if (count($categorias) > 0 || count($plataformas) > 0 || count($tags) > 0): ?>
                                <div style="margin-top: 16px;">
                                    <?php if (count($categorias) > 0): ?>
                                    <div style="margin-bottom: 10px;">
                                        <div class="label" style="font-size: 11px; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 6px;">Categorias</div>
                                        <div class="tags-wrap">
                                            <?php foreach ($categorias as $cat): ?>
                                                <span class="tag accent"><?= sanitize($cat['nome']); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (count($plataformas) > 0): ?>
                                    <div style="margin-bottom: 10px;">
                                        <div class="label" style="font-size: 11px; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 6px;">Plataformas</div>
                                        <div class="tags-wrap">
                                            <?php foreach ($plataformas as $plat): ?>
                                                <span class="tag"><?= sanitize($plat['nome']); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (count($tags) > 0): ?>
                                    <div>
                                        <div class="label" style="font-size: 11px; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 6px;">Tags</div>
                                        <div class="tags-wrap">
                                            <?php foreach ($tags as $tag): ?>
                                                <span class="tag"><?= sanitize($tag['nome']); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Descrições -->
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-align-left"></i> Descrição</h3>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($jogo['descricao_curta'])): ?>
                                <div style="margin-bottom: 16px;">
                                    <div class="label" style="font-size: 11px; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 6px;">Descrição Curta</div>
                                    <p style="color: var(--text-primary); font-size: 14px; margin: 0; line-height: 1.6;"><?= sanitize($jogo['descricao_curta']); ?></p>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($jogo['descricao_completa'])): ?>
                                <div>
                                    <div class="label" style="font-size: 11px; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 6px;">Descrição Completa</div>
                                    <div class="expandable-text collapsed" id="descricaoCompleta">
                                        <div style="color: var(--text-secondary); font-size: 14px; line-height: 1.7; white-space: pre-wrap;"><?= sanitize($jogo['descricao_completa']); ?></div>
                                        <div class="gradient"></div>
                                    </div>
                                    <button type="button" class="expand-btn" onclick="toggleExpand('descricaoCompleta', this)">
                                        <i class="fas fa-chevron-down"></i> <span>Ler mais</span>
                                    </button>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($jogo['motivo_rejeicao'])): ?>
                                <div class="rejection-box">
                                    <div class="title"><i class="fas fa-exclamation-circle"></i> Motivo da Rejeição/Suspensão</div>
                                    <div class="content"><?= nl2br(sanitize($jogo['motivo_rejeicao'])); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Requisitos do Sistema -->
                        <?php if (!empty($jogo['requisitos_minimos']) || !empty($jogo['requisitos_recomendados'])): ?>
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-desktop"></i> Requisitos do Sistema</h3>
                            </div>
                            <div class="card-body">
                                <div class="req-tabs">
                                    <?php if (!empty($jogo['requisitos_minimos'])): ?>
                                    <button class="req-tab active" onclick="switchReqTab('minimos', this)">Mínimos</button>
                                    <?php endif; ?>
                                    <?php if (!empty($jogo['requisitos_recomendados'])): ?>
                                    <button class="req-tab <?= empty($jogo['requisitos_minimos']) ? 'active' : '' ?>" onclick="switchReqTab('recomendados', this)">Recomendados</button>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($jogo['requisitos_minimos'])): ?>
                                <div class="req-content active" id="req-minimos">
                                    <div style="color: var(--text-secondary); font-size: 13px; line-height: 1.7; white-space: pre-wrap;"><?= sanitize($jogo['requisitos_minimos']); ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($jogo['requisitos_recomendados'])): ?>
                                <div class="req-content <?= empty($jogo['requisitos_minimos']) ? 'active' : '' ?>" id="req-recomendados">
                                    <div style="color: var(--text-secondary); font-size: 13px; line-height: 1.7; white-space: pre-wrap;"><?= sanitize($jogo['requisitos_recomendados']); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Mídia -->
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-images"></i> Mídia</h3>
                            </div>
                            <div class="card-body">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                                    <?php if (!empty($jogo['imagem_capa'])): ?>
                                    <div>
                                        <div class="label" style="font-size: 11px; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 6px;">Capa</div>
                                        <div class="cover-preview" style="aspect-ratio: 1/1;">
                                            <img src="<?= SITE_URL . $jogo['imagem_capa']; ?>" alt="Capa" >
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($jogo['imagem_banner'])): ?>
                                    <div>
                                        <div class="label" style="font-size: 11px; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 6px;">Banner</div>
                                        <div class="cover-preview">
                                            <img src="<?= SITE_URL . $jogo['imagem_banner']; ?>" alt="Banner">
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($jogo['video_trailer'])): ?>
                                <div style="margin-bottom: 16px;">
                                    <div class="label" style="font-size: 11px; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 6px;">Trailer</div>
                                    <div class="info-item">
                                        <a href="<?= sanitize($jogo['video_trailer']); ?>" target="_blank" style="color: var(--accent); font-size: 13px;">
                                            <i class="fas fa-play-circle"></i> <?= sanitize($jogo['video_trailer']); ?>
                                        </a>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if (count($screenshots) > 0): ?>
                                <div>
                                    <div class="label" style="font-size: 11px; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 6px;">Screenshots (<?= count($screenshots) ?>)</div>
                                    <div class="screenshots-mini">
                                        <?php foreach ($screenshots as $shot): 
                                            $img_url = $shot['imagem'] ?? $shot['url'] ?? $shot['caminho'] ?? '';
                                            if ($img_url):
                                        ?>
                                        <div class="screenshot-thumb" onclick="openLightbox('<?= SITE_URL . $img_url ?>')">
                                            <img src="<?= SITE_URL . $img_url; ?>" alt="Screenshot">
                                        </div>
                                        <?php endif; endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Arquivos -->
                        <?php if (count($arquivos) > 0): ?>
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-file-archive"></i> Arquivos (<?= count($arquivos) ?>)</h3>
                            </div>
                            <div class="card-body">
                                <?php foreach ($arquivos as $arquivo): ?>
                                <div class="file-row">
                                    <div class="file-icon"><i class="fas fa-file-archive"></i></div>
                                    <div class="file-info">
                                        <div class="name"><?= sanitize($arquivo['nome_arquivo'] ?? $arquivo['nome'] ?? 'Arquivo'); ?></div>
                                        <div class="meta">
                                            <?= $arquivo['plataforma_nome'] ?? 'N/A' ?> • 
                                            v<?= sanitize($arquivo['versao'] ?? '1.0'); ?> • 
                                            <?= formatFileSizeCustom($arquivo['tamanho_bytes'] ?? $arquivo['tamanho'] ?? 0); ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Sidebar -->
                    <div class="side-column">
                        <div class="side_section">
                            <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-bolt"></i> Ações</h3>
                            </div>
                            <div class="card-body">
                                <div class="btn-group">
                                    <?php if (($jogo['status'] ?? '') === 'em_revisao'): ?>
                                        <button type="button" class="btn btn-success btn-block" onclick="showModal('aprovar')">
                                            <i class="fas fa-check"></i> Aprovar
                                        </button>
                                        <button type="button" class="btn btn-danger btn-block" onclick="showModal('rejeitar')">
                                            <i class="fas fa-times"></i> Rejeitar
                                        </button>
                                    <?php endif; ?>

                                    <?php if (($jogo['status'] ?? '') === 'publicado'): ?>
                                        <form method="POST" style="width: 100%;">
                                            <input type="hidden" name="action" value="toggle_destaque">
                                            <button type="submit" class="btn btn-<?= !empty($jogo['destaque']) ? 'warning' : 'secondary' ?> btn-block">
                                                <i class="fas fa-star"></i> <?= !empty($jogo['destaque']) ? 'Remover Destaque' : 'Destacar' ?>
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-outline-danger btn-block" onclick="showModal('suspender')">
                                            <i class="fas fa-ban"></i> Suspender
                                        </button>
                                    <?php endif; ?>

                                    <?php if (($jogo['status'] ?? '') === 'suspenso'): ?>
                                        <form method="POST" style="width: 100%;">
                                            <input type="hidden" name="action" value="reativar">
                                            <button type="submit" class="btn btn-success btn-block">
                                                <i class="fas fa-redo"></i> Reativar
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if (($jogo['status'] ?? '') !== 'removido'): ?>
                                        <button type="button" class="btn btn-outline-danger btn-block" onclick="showModal('remover')">
                                            <i class="fas fa-trash"></i> Remover
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Desenvolvedor -->
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-user-tie"></i> Desenvolvedor</h3>
                            </div>
                            <div class="card-body">
                                <div class="dev-card">
                                    <div class="dev-avatar">
                                        <?php if (!empty($jogo['dev_logo'])): ?>
                                            <img src="<?= SITE_URL . $jogo['dev_logo']; ?>" alt="">
                                        <?php else: ?>
                                            <?= strtoupper(substr($jogo['nome_estudio'] ?? 'D', 0, 1)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="dev-info">
                                        <div class="name">
                                            <?= sanitize($jogo['nome_estudio'] ?? 'N/A'); ?>
                                            <?php if (!empty($jogo['dev_verificado'])): ?>
                                                <i class="fas fa-check-circle verified" title="Verificado"></i>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($dev_email): ?>
                                            <div class="email"><?= sanitize($dev_email); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php 
                                $has_social = !empty($jogo['dev_website']) || !empty($jogo['dev_twitter']) || 
                                              !empty($jogo['dev_instagram']) || !empty($jogo['dev_discord']) || 
                                              !empty($jogo['dev_youtube']);
                                if ($has_social): 
                                ?>
                                <div class="dev-socials">
                                    <?php if (!empty($jogo['dev_website'])): ?>
                                        <a href="<?= sanitize($jogo['dev_website']); ?>" target="_blank" title="Website"><i class="fas fa-globe"></i></a>
                                    <?php endif; ?>
                                    <?php if (!empty($jogo['dev_twitter'])): ?>
                                        <a href="<?= sanitize($jogo['dev_twitter']); ?>" target="_blank" title="Twitter"><i class="fab fa-twitter"></i></a>
                                    <?php endif; ?>
                                    <?php if (!empty($jogo['dev_instagram'])): ?>
                                        <a href="<?= sanitize($jogo['dev_instagram']); ?>" target="_blank" title="Instagram"><i class="fab fa-instagram"></i></a>
                                    <?php endif; ?>
                                    <?php if (!empty($jogo['dev_discord'])): ?>
                                        <a href="<?= sanitize($jogo['dev_discord']); ?>" target="_blank" title="Discord"><i class="fab fa-discord"></i></a>
                                    <?php endif; ?>
                                    <?php if (!empty($jogo['dev_youtube'])): ?>
                                        <a href="<?= sanitize($jogo['dev_youtube']); ?>" target="_blank" title="YouTube"><i class="fab fa-youtube"></i></a>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Datas -->
                        <div class="card card-compact">
                            <div class="card-header">
                                <h3><i class="fas fa-calendar-alt"></i> Histórico</h3>
                            </div>
                            <div class="card-body">
                                <div class="info-grid cols-1" style="gap: 8px;">
                                    <?php if (!empty($jogo['criado_em'])): ?>
                                    <div class="info-item">
                                        <div class="label">Criado em</div>
                                        <div class="value"><?= date('d/m/Y H:i', strtotime($jogo['criado_em'])); ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($jogo['publicado_em'])): ?>
                                    <div class="info-item">
                                        <div class="label">Publicado em</div>
                                        <div class="value"><?= date('d/m/Y H:i', strtotime($jogo['publicado_em'])); ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($jogo['atualizado_em'])): ?>
                                    <div class="info-item">
                                        <div class="label">Atualizado em</div>
                                        <div class="value"><?= date('d/m/Y H:i', strtotime($jogo['atualizado_em'])); ?></div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Confirmação -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal-container">
        <div class="modal-top">
            <div class="modal-icon" id="modalIcon"><i class="fas fa-question"></i></div>
            <div>
                <div class="modal-title" id="modalTitle">Confirmar Ação</div>
                <div class="modal-subtitle" id="modalSubtitle">Jogo: <?= sanitize($jogo['titulo'] ?? ''); ?></div>
            </div>
        </div>
        <form method="POST" id="modalForm">
            <input type="hidden" name="action" id="modalAction" value="">
            <div class="modal-body">
                <p id="modalText">Tem certeza que deseja realizar esta ação?</p>
                <div id="modalMotivo" style="display: none;">
                    <label for="motivoInput">Motivo</label>
                    <textarea id="motivoInput" name="motivo" placeholder="Descreva o motivo..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="modalConfirm">Confirmar</button>
            </div>
        </form>
    </div>
</div>

<!-- Lightbox -->
<div class="lightbox" id="lightbox" onclick="closeLightbox()">
    <button class="lightbox-close"><i class="fas fa-times"></i></button>
    <img id="lightboxImg" src="" alt="">
</div>

<script>
// Modal Functions
function showModal(action) {
    const overlay = document.getElementById('modalOverlay');
    const icon = document.getElementById('modalIcon');
    const title = document.getElementById('modalTitle');
    const text = document.getElementById('modalText');
    const motivoDiv = document.getElementById('modalMotivo');
    const motivoInput = document.getElementById('motivoInput');
    const actionInput = document.getElementById('modalAction');
    const confirmBtn = document.getElementById('modalConfirm');

    actionInput.value = action;
    motivoDiv.style.display = 'none';
    motivoInput.required = false;
    motivoInput.value = '';

    const configs = {
        aprovar: {
            iconClass: 'success',
            icon: 'fa-check',
            title: 'Aprovar Jogo',
            text: 'O jogo será publicado e ficará visível na loja imediatamente.',
            btnClass: 'btn-success',
            btnText: 'Aprovar'
        },
        rejeitar: {
            iconClass: 'danger',
            icon: 'fa-times',
            title: 'Rejeitar Jogo',
            text: 'O jogo voltará para rascunho e o desenvolvedor será notificado.',
            btnClass: 'btn-danger',
            btnText: 'Rejeitar',
            motivo: true,
            motivoRequired: true,
            motivoPlaceholder: 'Explique o motivo da rejeição para o desenvolvedor...'
        },
        suspender: {
            iconClass: 'warning',
            icon: 'fa-ban',
            title: 'Suspender Jogo',
            text: 'O jogo será removido da loja temporariamente.',
            btnClass: 'btn-warning',
            btnText: 'Suspender',
            motivo: true,
            motivoPlaceholder: 'Motivo da suspensão (opcional)...'
        },
        remover: {
            iconClass: 'danger',
            icon: 'fa-trash',
            title: 'Remover Jogo',
            text: 'O jogo será marcado como removido permanentemente.',
            btnClass: 'btn-danger',
            btnText: 'Remover'
        }
    };

    const config = configs[action];
    if (config) {
        icon.className = `modal-icon ${config.iconClass}`;
        icon.innerHTML = `<i class="fas ${config.icon}"></i>`;
        title.textContent = config.title;
        text.textContent = config.text;
        confirmBtn.className = `btn ${config.btnClass}`;
        confirmBtn.textContent = config.btnText;

        if (config.motivo) {
            motivoDiv.style.display = 'block';
            motivoInput.required = config.motivoRequired || false;
            motivoInput.placeholder = config.motivoPlaceholder || '';
        }
    }

    overlay.classList.add('active');
    if (config.motivo) motivoInput.focus();
}

function closeModal() {
    document.getElementById('modalOverlay').classList.remove('active');
}

document.getElementById('modalOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
        closeLightbox();
    }
});

// Expand/Collapse Text
function toggleExpand(id, btn) {
    const el = document.getElementById(id);
    const isCollapsed = el.classList.contains('collapsed');
    
    if (isCollapsed) {
        el.classList.remove('collapsed');
        el.classList.add('expanded');
        btn.innerHTML = '<i class="fas fa-chevron-up"></i> <span>Ler menos</span>';
    } else {
        el.classList.remove('expanded');
        el.classList.add('collapsed');
        btn.innerHTML = '<i class="fas fa-chevron-down"></i> <span>Ler mais</span>';
    }
}

// Requirements Tabs
function switchReqTab(tab, btn) {
    document.querySelectorAll('.req-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.req-content').forEach(c => c.classList.remove('active'));
    
    btn.classList.add('active');
    document.getElementById('req-' + tab).classList.add('active');
}

// Lightbox
function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightbox').classList.add('active');
}

function closeLightbox() {
    document.getElementById('lightbox').classList.remove('active');
}
</script>

<?php require_once '../includes/footer.php'; ?>