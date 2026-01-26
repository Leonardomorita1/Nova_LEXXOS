<?php
// ============================================
// admin/banners.php
// ============================================
require_once '../config/config.php';
require_once '../config/database.php';
requireLogin();
if (getUserType() !== 'admin') { header('Location: ' . SITE_URL . '/pages/home.php'); exit; }

$database = new Database();
$pdo = $database->getConnection();
$message = '';

// Adicionar/Editar Banner
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add'])) {
        $data_inicio = !empty($_POST['data_inicio']) ? $_POST['data_inicio'] : null;
        $data_fim = !empty($_POST['data_fim']) ? $_POST['data_fim'] : null;
        
        $stmt = $pdo->prepare("INSERT INTO banner (titulo, subtitulo, imagem_desktop, url_destino, ordem, ativo, data_inicio, data_fim) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['titulo'], 
            $_POST['subtitulo'], 
            $_POST['imagem_desktop'], 
            $_POST['url_destino'], 
            $_POST['ordem'], 
            $_POST['ativo'],
            $data_inicio,
            $data_fim
        ]);
        $message = 'Banner adicionado com sucesso!';
    } elseif (isset($_POST['toggle'])) {
        $stmt = $pdo->prepare("UPDATE banner SET ativo = NOT ativo WHERE id = ?");
        $stmt->execute([$_POST['banner_id']]);
        $message = 'Status atualizado!';
    } elseif (isset($_POST['delete'])) {
        $stmt = $pdo->prepare("DELETE FROM banner WHERE id = ?");
        $stmt->execute([$_POST['banner_id']]);
        $message = 'Banner excluído!';
    }
}

$stmt = $pdo->query("SELECT * FROM banner ORDER BY ordem, id DESC");
$banners = $stmt->fetchAll();

$page_title = 'Gerenciar Banners - Admin - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo SITE_URL; ?>/admin/assets/css/admin.css">

<style>
.banner-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}

.banner-status-badge.active {
    background: rgba(46, 204, 113, 0.15);
    color: #2ecc71;
}

.banner-status-badge.inactive {
    background: rgba(149, 165, 166, 0.15);
    color: #95a5a6;
}

.banner-status-badge.scheduled {
    background: rgba(76, 139, 245, 0.15);
    color: #4c8bf5;
}

.banner-status-badge.expired {
    background: rgba(220, 53, 69, 0.15);
    color: #dc3545;
}

.banner-dates {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 12px;
    color: var(--text-secondary);
}

.banner-date-item {
    display: flex;
    align-items: center;
    gap: 6px;
}

.banner-date-item i {
    width: 14px;
    color: var(--accent);
}
</style>

<div class="container">
    <div class="admin-layout">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="admin-header">
                <div>
                    <h1 class="admin-title"><i class="fas fa-image"></i> Gerenciar Banners</h1>
                    <p style="color: var(--text-secondary); margin-top: 8px;">
                        Banners com datas são desativados automaticamente quando expiram
                    </p>
                </div>
                <button onclick="document.getElementById('formNovo').style.display='block'" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Novo Banner
                </button>
            </div>

            <?php if ($message): ?>
            <div style="background: rgba(46, 204, 113, 0.1); border: 1px solid #2ecc71; color: #2ecc71; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <!-- Formulário Novo Banner -->
            <div id="formNovo" class="form-section" style="display: none; margin-bottom: 30px; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; padding: 24px;">
                <h2 style="margin-bottom: 20px; color: var(--text-primary);">Novo Banner</h2>
                <form method="POST">
                    <div class="form-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-primary);">Título</label>
                            <input type="text" name="titulo" class="form-control" style="width: 100%; padding: 10px; background: var(--bg-primary); border: 1px solid var(--border); border-radius: 6px; color: var(--text-primary);">
                        </div>
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-primary);">Subtítulo</label>
                            <input type="text" name="subtitulo" class="form-control">
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-primary);">URL da Imagem</label>
                            <input type="text" name="imagem_desktop" class="form-control" required placeholder="/assets/images/banner.jpg">
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-primary);">URL de Destino</label>
                            <input type="text" name="url_destino" class="form-control" placeholder="/pages/evento.php?slug=promocao">
                        </div>
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-primary);">Data de Início (Opcional)</label>
                            <input type="date" name="data_inicio" class="form-control">
                            <small style="color: var(--text-secondary); font-size: 12px; margin-top: 4px; display: block;">
                                Deixe vazio para ativar imediatamente
                            </small>
                        </div>
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-primary);">Data de Término (Opcional)</label>
                            <input type="date" name="data_fim" class="form-control">
                            <small style="color: var(--text-secondary); font-size: 12px; margin-top: 4px; display: block;">
                                Banner será desativado automaticamente nesta data
                            </small>
                        </div>
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-primary);">Ordem</label>
                            <input type="number" name="ordem" class="form-control" value="0">
                        </div>
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-primary);">Status Inicial</label>
                            <select name="ativo" class="form-control">
                                <option value="1">Ativo</option>
                                <option value="0">Inativo</option>
                            </select>
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" name="add" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Adicionar Banner
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('formNovo').style.display='none'">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                    </div>
                </form>
            </div>

            <!-- Lista de Banners -->
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Preview</th>
                            <th>Título</th>
                            <th>Período</th>
                            <th>Ordem</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($banners as $banner): 
                            // Determinar status do banner
                            $hoje = new DateTime();
                            $data_inicio = $banner['data_inicio'] ? new DateTime($banner['data_inicio']) : null;
                            $data_fim = $banner['data_fim'] ? new DateTime($banner['data_fim']) : null;
                            
                            $status = 'active';
                            $status_text = 'Ativo';
                            $status_icon = 'fa-check-circle';
                            
                            if (!$banner['ativo']) {
                                $status = 'inactive';
                                $status_text = 'Inativo';
                                $status_icon = 'fa-times-circle';
                            } elseif ($data_inicio && $hoje < $data_inicio) {
                                $status = 'scheduled';
                                $status_text = 'Agendado';
                                $status_icon = 'fa-clock';
                            } elseif ($data_fim && $hoje > $data_fim) {
                                $status = 'expired';
                                $status_text = 'Expirado';
                                $status_icon = 'fa-exclamation-circle';
                            }
                        ?>
                        <tr>
                            <td>
                                <img src="<?php echo SITE_URL . $banner['imagem_desktop']; ?>" 
                                     style="width: 120px; height: 40px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border);">
                            </td>
                            <td>
                                <strong style="color: var(--text-primary);"><?php echo sanitize($banner['titulo']); ?></strong>
                                <?php if ($banner['subtitulo']): ?>
                                    <br><small style="color: var(--text-secondary);"><?php echo sanitize($banner['subtitulo']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="banner-dates">
                                    <?php if ($banner['data_inicio']): ?>
                                        <div class="banner-date-item">
                                            <i class="fas fa-calendar-plus"></i>
                                            <span>Início: <?php echo date('d/m/Y', strtotime($banner['data_inicio'])); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($banner['data_fim']): ?>
                                        <div class="banner-date-item">
                                            <i class="fas fa-calendar-times"></i>
                                            <span>Fim: <?php echo date('d/m/Y', strtotime($banner['data_fim'])); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div class="banner-date-item">
                                            <i class="fas fa-infinity"></i>
                                            <span>Sem data de término</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="text-align: center; font-weight: 600;"><?php echo $banner['ordem']; ?></td>
                            <td>
                                <span class="banner-status-badge <?php echo $status; ?>">
                                    <i class="fas <?php echo $status_icon; ?>"></i>
                                    <?php echo $status_text; ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="banner_id" value="<?php echo $banner['id']; ?>">
                                        <button type="submit" name="toggle" class="btn-icon edit" title="Ativar/Desativar">
                                            <i class="fas fa-toggle-<?php echo $banner['ativo'] ? 'on' : 'off'; ?>"></i>
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="banner_id" value="<?php echo $banner['id']; ?>">
                                        <button type="submit" name="delete" class="btn-icon delete" title="Excluir" 
                                                onclick="return confirm('Excluir este banner?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>