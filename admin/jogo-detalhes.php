<?php
// ============================================
// admin/jogo-detalhes.php
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 1); // Temporário para debug

require_once '../config/config.php';
require_once '../config/database.php';

requireLogin();

if (getUserType() !== 'admin') { 
    header('Location: ' . SITE_URL . '/pages/home.php'); 
    exit; 
}

$database = new Database();
$pdo = $database->getConnection();

// Verificar se o ID foi passado
$jogo_id = $_GET['id'] ?? $_GET['jogo_id'] ?? null;

if (!$jogo_id || !is_numeric($jogo_id)) {
    $_SESSION['admin_error'] = 'ID do jogo não informado ou inválido.';
    header('Location: ' . SITE_URL . '/admin/jogos.php');
    exit;
}

$success = '';
$error = '';

// Função auxiliar para formatar tamanho de arquivo
function formatFileSizeCustom($bytes) {
    if (!$bytes) return '0 bytes';
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' bytes';
}

// Função para formatar preço (caso não exista)
if (!function_exists('formatPrice')) {
    function formatPrice($centavos) {
        if ($centavos == 0) return 'Gratuito';
        return 'R$ ' . number_format($centavos / 100, 2, ',', '.');
    }
}

// Função sanitize (caso não exista)
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
                $stmt = $pdo->prepare("UPDATE jogo SET status = 'publicado', atualizado_em = NOW(), motivo_rejeicao = NULL WHERE id = ?");
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
                // Verificar se coluna destaque existe
                try {
                    $stmt = $pdo->prepare("UPDATE jogo SET destaque = NOT destaque, atualizado_em = NOW() WHERE id = ?");
                    $stmt->execute([$jogo_id]);
                    $success = 'Destaque atualizado!';
                } catch (PDOException $e) {
                    $error = 'Coluna destaque não existe no banco.';
                }
                break;
        }
    } catch (Exception $e) {
        $error = 'Erro ao processar ação: ' . $e->getMessage();
    }
}

// Buscar jogo - Query simplificada primeiro
try {
    $stmt = $pdo->prepare("
        SELECT j.*, 
               d.nome_estudio, 
               d.slug as dev_slug,
               d.id as dev_id
        FROM jogo j
        LEFT JOIN desenvolvedor d ON j.desenvolvedor_id = d.id
        WHERE j.id = ?
    ");
    $stmt->execute([$jogo_id]);
    $jogo = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro na query do jogo: " . $e->getMessage());
}

if (!$jogo) {
    $_SESSION['admin_error'] = 'Jogo não encontrado (ID: ' . $jogo_id . ')';
    header('Location: ' . SITE_URL . '/admin/jogos.php');
    exit;
}

// Buscar email do desenvolvedor separadamente
$dev_email = '';
if ($jogo['dev_id']) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.email 
            FROM desenvolvedor d 
            JOIN usuario u ON d.usuario_id = u.id 
            WHERE d.id = ?
        ");
        $stmt->execute([$jogo['dev_id']]);
        $dev = $stmt->fetch(PDO::FETCH_ASSOC);
        $dev_email = $dev['email'] ?? '';
    } catch (PDOException $e) {
        // Ignorar se falhar
    }
}

// Buscar categorias
$categorias = [];
try {
    $stmt = $pdo->prepare("
        SELECT c.id, c.nome
        FROM categoria c
        JOIN jogo_categoria jc ON c.id = jc.categoria_id
        WHERE jc.jogo_id = ?
        ORDER BY c.nome
    ");
    $stmt->execute([$jogo_id]);
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Tabela pode não existir
}

// Buscar plataformas
$plataformas = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.nome
        FROM plataforma p
        JOIN jogo_plataforma jp ON p.id = jp.plataforma_id
        WHERE jp.jogo_id = ?
    ");
    $stmt->execute([$jogo_id]);
    $plataformas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Tabela pode não existir
}

// Buscar tags
$tags = [];
try {
    $stmt = $pdo->prepare("
        SELECT t.id, t.nome
        FROM tag t
        JOIN jogo_tag jt ON t.id = jt.tag_id
        WHERE jt.jogo_id = ?
        ORDER BY t.nome
    ");
    $stmt->execute([$jogo_id]);
    $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Tabela pode não existir
}

// Buscar screenshots (tabela pode ter nome diferente)
$screenshots = [];
try {
    // Tentar nome comum
    $stmt = $pdo->prepare("SELECT * FROM jogo_imagens WHERE jogo_id = ? ORDER BY id");
    $stmt->execute([$jogo_id]);
    $screenshots = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    try {
        // Tentar outro nome
        $stmt = $pdo->prepare("SELECT * FROM jogo_screenshots WHERE jogo_id = ? ORDER BY id");
        $stmt->execute([$jogo_id]);
        $screenshots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {
        // Tabela não existe
    }
}

// Buscar arquivos
$arquivos = [];
try {
    $stmt = $pdo->prepare("
        SELECT ja.*, p.nome as plataforma_nome
        FROM arquivo_jogo ja
        LEFT JOIN plataforma p ON ja.plataforma_id = p.id
        WHERE ja.jogo_id = ?
        ORDER BY ja.id DESC
    ");
    $stmt->execute([$jogo_id]);
    $arquivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Tabela pode não existir
}

// Status config
$status_config = [
    'rascunho' => ['label' => 'Rascunho', 'color' => '#6b7280', 'icon' => 'fa-file-alt', 'bg' => 'rgba(107, 114, 128, 0.15)'],
    'em_revisao' => ['label' => 'Em Revisão', 'color' => '#f59e0b', 'icon' => 'fa-clock', 'bg' => 'rgba(245, 158, 11, 0.15)'],
    'publicado' => ['label' => 'Publicado', 'color' => '#10b981', 'icon' => 'fa-check-circle', 'bg' => 'rgba(16, 185, 129, 0.15)'],
    'suspenso' => ['label' => 'Suspenso', 'color' => '#ef4444', 'icon' => 'fa-ban', 'bg' => 'rgba(239, 68, 68, 0.15)'],
    'removido' => ['label' => 'Removido', 'color' => '#dc2626', 'icon' => 'fa-trash', 'bg' => 'rgba(220, 38, 38, 0.15)']
];
$current_status = $status_config[$jogo['status']] ?? $status_config['rascunho'];

// Verificar se coluna destaque existe
$tem_destaque = isset($jogo['destaque']);

$page_title = 'Detalhes: ' . ($jogo['titulo'] ?? 'Jogo') . ' - Admin';
require_once '../includes/header.php';
?>

<link rel="stylesheet" href="<?= SITE_URL; ?>/admin/assets/css/admin.css">

<style>
    .detail-grid {
        display: grid;
        grid-template-columns: 1fr 380px;
        gap: 25px;
        align-items: start;
    }

    .detail-card {
        background: var(--bg-secondary, #1a1a2e);
        border: 1px solid var(--border, #2a2a4a);
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 20px;
    }

    .detail-card-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--border, #2a2a4a);
    }

    .detail-card-header h2 {
        font-size: 18px;
        font-weight: 600;
        margin: 0;
    }

    .detail-card-header i {
        color: var(--accent, #8b5cf6);
        font-size: 20px;
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 20px;
    }

    .page-header-info h1 {
        font-size: 26px;
        font-weight: 700;
        margin: 0 0 10px 0;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: var(--text-secondary, #a0a0a0);
        text-decoration: none;
        font-size: 14px;
        margin-bottom: 15px;
        transition: color 0.2s;
    }

    .back-link:hover {
        color: var(--accent, #8b5cf6);
    }

    .status-badge-lg {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border-radius: 25px;
        font-size: 15px;
        font-weight: 600;
    }

    .info-row {
        margin-bottom: 18px;
    }

    .info-row:last-child {
        margin-bottom: 0;
    }

    .info-label {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-muted, #666);
        margin-bottom: 6px;
        display: block;
    }

    .info-value {
        font-size: 15px;
        color: var(--text-primary, #fff);
    }

    .info-value.large {
        font-size: 24px;
        font-weight: 700;
    }

    .tag-list {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .tag-item {
        background: var(--bg-tertiary, #252540);
        color: var(--text-secondary, #a0a0a0);
        padding: 5px 12px;
        border-radius: 15px;
        font-size: 13px;
    }

    .tag-item.accent {
        background: rgba(139, 92, 246, 0.15);
        color: var(--accent, #8b5cf6);
    }

    .media-preview {
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 15px;
    }

    .media-preview img {
        max-width: 100%;
        height: auto;
        display: block;
    }

    .screenshots-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 10px;
    }

    .screenshot-item {
        aspect-ratio: 16/9;
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid var(--border, #2a2a4a);
        cursor: pointer;
        transition: transform 0.2s, border-color 0.2s;
    }

    .screenshot-item:hover {
        transform: scale(1.02);
        border-color: var(--accent, #8b5cf6);
    }

    .screenshot-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        margin-bottom: 20px;
    }

    .stat-box {
        background: var(--bg-tertiary, #252540);
        border-radius: 10px;
        padding: 18px;
        text-align: center;
    }

    .stat-box .stat-value {
        font-size: 28px;
        font-weight: 700;
        color: var(--text-primary, #fff);
    }

    .stat-box .stat-label {
        font-size: 12px;
        color: var(--text-muted, #666);
        text-transform: uppercase;
        margin-top: 5px;
    }

    .action-buttons {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px 20px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        border: none;
        text-decoration: none;
    }

    .btn-block { width: 100%; }

    .btn-success {
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
    }

    .btn-danger {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
    }

    .btn-warning {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
    }

    .btn-primary {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        color: white;
    }

    .btn-secondary {
        background: var(--bg-tertiary, #252540);
        color: var(--text-primary, #fff);
        border: 1px solid var(--border, #2a2a4a);
    }

    .btn-outline-danger {
        background: transparent;
        color: #ef4444;
        border: 2px solid #ef4444;
    }

    .btn-outline-danger:hover {
        background: #ef4444;
        color: white;
    }

    .alert {
        padding: 16px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .alert-success {
        background: rgba(16, 185, 129, 0.15);
        border: 1px solid rgba(16, 185, 129, 0.3);
        color: #10b981;
    }

    .alert-error {
        background: rgba(239, 68, 68, 0.15);
        border: 1px solid rgba(239, 68, 68, 0.3);
        color: #ef4444;
    }

    .alert-warning {
        background: rgba(245, 158, 11, 0.15);
        border: 1px solid rgba(245, 158, 11, 0.3);
        color: #f59e0b;
    }

    .rejection-box {
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid rgba(239, 68, 68, 0.3);
        border-radius: 10px;
        padding: 18px;
        margin-top: 15px;
    }

    .rejection-box h4 {
        color: #ef4444;
        margin: 0 0 10px 0;
        font-size: 14px;
    }

    .description-text {
        white-space: pre-wrap;
        line-height: 1.7;
        color: var(--text-secondary, #a0a0a0);
    }

    .dev-info {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        background: var(--bg-tertiary, #252540);
        border-radius: 8px;
    }

    .dev-avatar {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background: var(--accent, #8b5cf6);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
    }

    .files-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .file-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 15px;
        background: var(--bg-tertiary, #252540);
        border-radius: 8px;
    }

    .file-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        background: var(--bg-primary, #0f0f1a);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--accent, #8b5cf6);
    }

    /* Modal */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.7);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s;
    }

    .modal-overlay.active {
        opacity: 1;
        visibility: visible;
    }

    .modal-box {
        background: var(--bg-secondary, #1a1a2e);
        border: 1px solid var(--border, #2a2a4a);
        border-radius: 16px;
        padding: 30px;
        max-width: 500px;
        width: 90%;
        transform: scale(0.9);
        transition: transform 0.3s;
    }

    .modal-overlay.active .modal-box {
        transform: scale(1);
    }

    .modal-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
    }

    .modal-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
    }

    .modal-icon.warning { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
    .modal-icon.danger { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
    .modal-icon.success { background: rgba(16, 185, 129, 0.15); color: #10b981; }

    .modal-title { font-size: 20px; font-weight: 700; }

    .modal-body { margin-bottom: 25px; }
    .modal-body p { color: var(--text-secondary, #a0a0a0); margin-bottom: 15px; }
    .modal-body textarea {
        width: 100%;
        padding: 12px 15px;
        background: var(--bg-primary, #0f0f1a);
        border: 1px solid var(--border, #2a2a4a);
        border-radius: 8px;
        color: var(--text-primary, #fff);
        resize: vertical;
        min-height: 100px;
    }

    .modal-actions { display: flex; gap: 12px; justify-content: flex-end; }

    @media (max-width: 992px) {
        .detail-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="container">
    <div class="admin-layout">
        <?php require_once 'includes/sidebar.php'; ?>

        <div class="admin-content">
            <a href="<?= SITE_URL; ?>/admin/jogos.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Voltar para Jogos
            </a>

            <div class="page-header">
                <div class="page-header-info">
                    <h1>
                        <i class="fas fa-gamepad"></i>
                        <?= sanitize($jogo['titulo'] ?? 'Sem título'); ?>
                        <?php if ($tem_destaque && $jogo['destaque']): ?>
                            <i class="fas fa-star" style="color: #f59e0b; font-size: 20px;" title="Em Destaque"></i>
                        <?php endif; ?>
                    </h1>
                    <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                        <span class="status-badge-lg" style="background: <?= $current_status['bg'] ?>; color: <?= $current_status['color'] ?>;">
                            <i class="fas <?= $current_status['icon'] ?>"></i>
                            <?= $current_status['label'] ?>
                        </span>
                        <?php if (isset($jogo['criado_em'])): ?>
                        <span style="color: var(--text-muted, #666); font-size: 14px;">
                            <i class="fas fa-clock"></i> Criado em <?= date('d/m/Y H:i', strtotime($jogo['criado_em'])) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?= $success ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= $error ?></span>
                </div>
            <?php endif; ?>

            <?php if (($jogo['status'] ?? '') === 'em_revisao'): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><strong>Este jogo está aguardando sua revisão.</strong> Analise as informações e aprove ou rejeite.</span>
                </div>
            <?php endif; ?>

            <div class="detail-grid">
                <!-- Coluna Principal -->
                <div class="main-col">
                    <div class="detail-card">
                        <div class="detail-card-header">
                            <i class="fas fa-info-circle"></i>
                            <h2>Informações do Jogo</h2>
                        </div>

                        <?php if (!empty($jogo['imagem_capa'])): ?>
                            <div class="media-preview" style="max-width: 280px;">
                                <img src="<?= SITE_URL . $jogo['imagem_capa']; ?>" alt="Capa">
                            </div>
                        <?php endif; ?>

                        <div class="info-row">
                            <span class="info-label">Slug</span>
                            <span class="info-value" style="font-family: monospace; color: var(--text-muted, #666);">
                                <?= sanitize($jogo['slug'] ?? ''); ?>
                            </span>
                        </div>

                        <?php if (!empty($jogo['descricao_curta'])): ?>
                        <div class="info-row">
                            <span class="info-label">Descrição Curta</span>
                            <span class="info-value"><?= sanitize($jogo['descricao_curta']); ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($jogo['descricao_completa'])): ?>
                        <div class="info-row">
                            <span class="info-label">Descrição Completa</span>
                            <div class="description-text"><?= nl2br(sanitize($jogo['descricao_completa'])); ?></div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($jogo['motivo_rejeicao'])): ?>
                            <div class="rejection-box">
                                <h4><i class="fas fa-exclamation-circle"></i> Motivo da Rejeição/Suspensão</h4>
                                <p style="margin: 0; color: var(--text-secondary, #a0a0a0);">
                                    <?= nl2br(sanitize($jogo['motivo_rejeicao'])); ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Screenshots -->
                    <?php if (count($screenshots) > 0): ?>
                    <div class="detail-card">
                        <div class="detail-card-header">
                            <i class="fas fa-images"></i>
                            <h2>Screenshots (<?= count($screenshots) ?>)</h2>
                        </div>

                        <div class="screenshots-grid">
                            <?php foreach ($screenshots as $shot): 
                                $img_url = $shot['imagem'] ?? $shot['url'] ?? $shot['caminho'] ?? '';
                                if ($img_url):
                            ?>
                                <div class="screenshot-item" onclick="window.open('<?= SITE_URL . $img_url ?>', '_blank')">
                                    <img src="<?= SITE_URL . $img_url; ?>" alt="Screenshot">
                                </div>
                            <?php endif; endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Arquivos -->
                    <?php if (count($arquivos) > 0): ?>
                    <div class="detail-card">
                        <div class="detail-card-header">
                            <i class="fas fa-file-archive"></i>
                            <h2>Arquivos (<?= count($arquivos) ?>)</h2>
                        </div>

                        <div class="files-list">
                            <?php foreach ($arquivos as $arquivo): ?>
                                <div class="file-item">
                                    <div class="file-icon">
                                        <i class="fas fa-file-archive"></i>
                                    </div>
                                    <div style="flex: 1;">
                                        <div style="font-weight: 500; font-size: 14px;">
                                            <?= sanitize($arquivo['nome_arquivo'] ?? $arquivo['nome'] ?? 'Arquivo'); ?>
                                        </div>
                                        <div style="font-size: 12px; color: var(--text-muted, #666);">
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
                <div class="side-col">
                    <!-- Estatísticas -->
                    <div class="detail-card">
                        <div class="detail-card-header">
                            <i class="fas fa-chart-bar"></i>
                            <h2>Estatísticas</h2>
                        </div>

                        <div class="stats-grid">
                            <div class="stat-box">
                                <div class="stat-value"><?= number_format($jogo['total_vendas'] ?? 0); ?></div>
                                <div class="stat-label">Vendas</div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-value" style="display: flex; align-items: center; justify-content: center; gap: 5px;">
                                    <i class="fas fa-star" style="color: #f59e0b; font-size: 18px;"></i>
                                    <?= number_format($jogo['nota_media'] ?? 0, 1); ?>
                                </div>
                                <div class="stat-label"><?= $jogo['total_avaliacoes'] ?? 0 ?> avaliações</div>
                            </div>
                        </div>
                    </div>

                    <!-- Desenvolvedor -->
                    <div class="detail-card">
                        <div class="detail-card-header">
                            <i class="fas fa-user-tie"></i>
                            <h2>Desenvolvedor</h2>
                        </div>

                        <div class="dev-info">
                            <div class="dev-avatar">
                                <?= strtoupper(substr($jogo['nome_estudio'] ?? 'D', 0, 1)); ?>
                            </div>
                            <div style="flex: 1;">
                                <div style="font-weight: 600; font-size: 15px;">
                                    <?= sanitize($jogo['nome_estudio'] ?? 'N/A'); ?>
                                </div>
                                <?php if ($dev_email): ?>
                                <div style="font-size: 13px; color: var(--text-muted, #666);">
                                    <?= sanitize($dev_email); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Detalhes -->
                    <div class="detail-card">
                        <div class="detail-card-header">
                            <i class="fas fa-tag"></i>
                            <h2>Detalhes</h2>
                        </div>

                        <div class="info-row">
                            <span class="info-label">Preço</span>
                            <span class="info-value large">
                                <?= formatPrice($jogo['preco_centavos'] ?? 0); ?>
                            </span>
                        </div>

                        <?php if (!empty($jogo['em_promocao']) && !empty($jogo['preco_promocional_centavos'])): ?>
                            <div class="info-row">
                                <span class="info-label">Preço Promocional</span>
                                <span class="info-value large" style="color: #10b981;">
                                    <?= formatPrice($jogo['preco_promocional_centavos']); ?>
                                </span>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($jogo['classificacao_etaria'])): ?>
                        <div class="info-row">
                            <span class="info-label">Classificação Etária</span>
                            <span class="tag-item accent"><?= $jogo['classificacao_etaria']; ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if (count($categorias) > 0): ?>
                            <div class="info-row">
                                <span class="info-label">Categorias</span>
                                <div class="tag-list">
                                    <?php foreach ($categorias as $cat): ?>
                                        <span class="tag-item accent"><?= sanitize($cat['nome']); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (count($plataformas) > 0): ?>
                            <div class="info-row">
                                <span class="info-label">Plataformas</span>
                                <div class="tag-list">
                                    <?php foreach ($plataformas as $plat): ?>
                                        <span class="tag-item"><?= sanitize($plat['nome']); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (count($tags) > 0): ?>
                            <div class="info-row">
                                <span class="info-label">Tags</span>
                                <div class="tag-list">
                                    <?php foreach ($tags as $tag): ?>
                                        <span class="tag-item"><?= sanitize($tag['nome']); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Ações -->
                    <div class="detail-card">
                        <div class="detail-card-header">
                            <i class="fas fa-cog"></i>
                            <h2>Ações</h2>
                        </div>

                        <div class="action-buttons">
                            <?php if (($jogo['status'] ?? '') === 'em_revisao'): ?>
                                <button type="button" class="btn btn-success btn-block" onclick="showModal('aprovar')">
                                    <i class="fas fa-check"></i> Aprovar e Publicar
                                </button>
                                <button type="button" class="btn btn-danger btn-block" onclick="showModal('rejeitar')">
                                    <i class="fas fa-times"></i> Rejeitar
                                </button>
                            <?php endif; ?>

                            <?php if (($jogo['status'] ?? '') === 'publicado'): ?>
                                <button type="button" class="btn btn-warning btn-block" onclick="showModal('suspender')">
                                    <i class="fas fa-ban"></i> Suspender
                                </button>
                            <?php endif; ?>

                            <?php if (($jogo['status'] ?? '') === 'suspenso'): ?>
                                <form method="POST">
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

                            <hr style="border-color: var(--border, #2a2a4a); margin: 10px 0;">

                            <?php if (!empty($jogo['slug'])): ?>
                            <a href="<?= SITE_URL; ?>/pages/jogo.php?slug=<?= $jogo['slug']; ?>" class="btn btn-primary btn-block" target="_blank">
                                <i class="fas fa-external-link-alt"></i> Ver na Loja
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-icon" id="modalIcon"><i class="fas fa-question"></i></div>
            <h3 class="modal-title" id="modalTitle">Confirmação</h3>
        </div>
        <div class="modal-body">
            <p id="modalText">Tem certeza?</p>
            <div id="modalMotivo" style="display: none;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500;">Motivo:</label>
                <textarea id="motivoInput" placeholder="Descreva o motivo..." form="modalForm" name="motivo"></textarea>
            </div>
        </div>
        <form method="POST" id="modalForm">
            <input type="hidden" name="action" id="modalAction" value="">
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="modalConfirm">Confirmar</button>
            </div>
        </form>
    </div>
</div>

<script>
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
    
    switch(action) {
        case 'aprovar':
            icon.className = 'modal-icon success';
            icon.innerHTML = '<i class="fas fa-check"></i>';
            title.textContent = 'Aprovar Jogo';
            text.textContent = 'O jogo será publicado e ficará visível na loja.';
            confirmBtn.className = 'btn btn-success';
            confirmBtn.textContent = 'Aprovar';
            break;
            
        case 'rejeitar':
            icon.className = 'modal-icon danger';
            icon.innerHTML = '<i class="fas fa-times"></i>';
            title.textContent = 'Rejeitar Jogo';
            text.textContent = 'O jogo voltará para rascunho.';
            motivoDiv.style.display = 'block';
            motivoInput.required = true;
            motivoInput.placeholder = 'Explique o motivo da rejeição...';
            confirmBtn.className = 'btn btn-danger';
            confirmBtn.textContent = 'Rejeitar';
            break;
            
        case 'suspender':
            icon.className = 'modal-icon warning';
            icon.innerHTML = '<i class="fas fa-ban"></i>';
            title.textContent = 'Suspender Jogo';
            text.textContent = 'O jogo será removido da loja temporariamente.';
            motivoDiv.style.display = 'block';
            motivoInput.placeholder = 'Motivo da suspensão (opcional)...';
            confirmBtn.className = 'btn btn-warning';
            confirmBtn.textContent = 'Suspender';
            break;
            
        case 'remover':
            icon.className = 'modal-icon danger';
            icon.innerHTML = '<i class="fas fa-trash"></i>';
            title.textContent = 'Remover Jogo';
            text.textContent = 'Esta ação marcará o jogo como removido.';
            confirmBtn.className = 'btn btn-danger';
            confirmBtn.textContent = 'Remover';
            break;
    }
    
    overlay.classList.add('active');
}

function closeModal() {
    document.getElementById('modalOverlay').classList.remove('active');
}

document.getElementById('modalOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});
</script>

<?php require_once '../includes/footer.php'; ?>