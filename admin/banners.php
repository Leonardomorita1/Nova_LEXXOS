<?php
// ============================================
// admin/banners.php - APENAS BANNERS SIMPLES
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
        
        $stmt = $pdo->prepare("
            INSERT INTO banner (
                titulo, subtitulo, 
                imagem_desktop,
                estilo_banner, url_destino, ordem, ativo, 
                data_inicio, data_fim
            ) VALUES (?, ?, ?, 'simples', ?, ?, ?, ?, ?)
        ");
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
    } elseif (isset($_POST['edit'])) {
        $data_inicio = !empty($_POST['data_inicio']) ? $_POST['data_inicio'] : null;
        $data_fim = !empty($_POST['data_fim']) ? $_POST['data_fim'] : null;
        
        $stmt = $pdo->prepare("
            UPDATE banner SET 
                titulo = ?, subtitulo = ?, imagem_desktop = ?,
                url_destino = ?, ordem = ?, ativo = ?,
                data_inicio = ?, data_fim = ?
            WHERE id = ? AND estilo_banner = 'simples'
        ");
        $stmt->execute([
            $_POST['titulo'], 
            $_POST['subtitulo'],
            $_POST['imagem_desktop'],
            $_POST['url_destino'], 
            $_POST['ordem'], 
            $_POST['ativo'],
            $data_inicio,
            $data_fim,
            $_POST['banner_id']
        ]);
        $message = 'Banner atualizado com sucesso!';
    } elseif (isset($_POST['toggle'])) {
        $stmt = $pdo->prepare("UPDATE banner SET ativo = NOT ativo WHERE id = ?");
        $stmt->execute([$_POST['banner_id']]);
        $message = 'Status atualizado!';
    } elseif (isset($_POST['delete'])) {
        // Só pode deletar banners simples (promocionais são gerenciados por eventos)
        $stmt = $pdo->prepare("DELETE FROM banner WHERE id = ? AND estilo_banner = 'simples'");
        $stmt->execute([$_POST['banner_id']]);
        $message = 'Banner excluído!';
    }
}

// Buscar apenas banners SIMPLES (promocionais são gerenciados em eventos)
$stmt = $pdo->query("SELECT * FROM banner WHERE estilo_banner = 'simples' OR estilo_banner IS NULL ORDER BY ordem, id DESC");
$banners = $stmt->fetchAll();

// Contar banners promocionais (de eventos)
$stmt = $pdo->query("SELECT COUNT(*) FROM banner WHERE estilo_banner = 'promocional'");
$total_promocionais = $stmt->fetchColumn();

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

.banner-status-badge.active { background: rgba(46, 204, 113, 0.15); color: #2ecc71; }
.banner-status-badge.inactive { background: rgba(149, 165, 166, 0.15); color: #95a5a6; }
.banner-status-badge.scheduled { background: rgba(76, 139, 245, 0.15); color: #4c8bf5; }
.banner-status-badge.expired { background: rgba(220, 53, 69, 0.15); color: #dc3545; }

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

.info-card {
    background: rgba(76, 139, 245, 0.1);
    border: 1px solid rgba(76, 139, 245, 0.3);
    border-radius: 8px;
    padding: 16px 20px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 16px;
}

.info-card i {
    font-size: 1.5rem;
    color: var(--accent);
}

.info-card-content {
    flex: 1;
}

.info-card-title {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.info-card-text {
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.info-card .btn {
    flex-shrink: 0;
}

.helper-text {
    font-size: 12px;
    color: var(--text-secondary);
    margin-top: 6px;
    display: block;
}

.form-section-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-primary);
    margin: 24px 0 12px;
    padding-bottom: 8px;
    border-bottom: 2px solid var(--border);
    display: flex;
    align-items: center;
    gap: 8px;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 3rem;
    opacity: 0.3;
    margin-bottom: 16px;
}

.empty-state h3 {
    color: var(--text-primary);
    margin-bottom: 8px;
}
</style>

<div class="container">
    <div class="admin-layout">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="admin-header">
                <div>
                    <h1 class="admin-title"><i class="fas fa-image"></i> Banners da Loja</h1>
                    <p style="color: var(--text-secondary); margin-top: 8px;">
                        Gerencie os banners do carrossel principal
                    </p>
                </div>
                <button onclick="document.getElementById('formNovo').style.display='block'" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Novo Banner
                </button>
            </div>

            <!-- Info sobre banners promocionais -->
            <?php if ($total_promocionais > 0): ?>
            <div class="info-card">
                <i class="fas fa-calendar-star"></i>
                <div class="info-card-content">
                    <div class="info-card-title"><?= $total_promocionais ?> Banner(s) Promocional(is) Ativo(s)</div>
                    <div class="info-card-text">
                        Banners promocionais são criados automaticamente junto com eventos sazonais.
                    </div>
                </div>
                <a href="<?= SITE_URL ?>/admin/eventos.php" class="btn btn-outline">
                    <i class="fas fa-external-link-alt"></i> Gerenciar Eventos
                </a>
            </div>
            <?php endif; ?>

            <?php if ($message): ?>
            <div style="background: rgba(46, 204, 113, 0.1); border: 1px solid #2ecc71; color: #2ecc71; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <!-- Formulário Novo Banner -->
            <div id="formNovo" class="form-section" style="display: none; margin-bottom: 30px; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; padding: 24px;">
                <h2 style="margin-bottom: 20px; color: var(--text-primary);">
                    <i class="fas fa-plus-circle"></i> Novo Banner
                </h2>
                <form method="POST">
                    
                    <!-- INFORMAÇÕES BÁSICAS -->
                    <div class="form-section-title">
                        <i class="fas fa-info-circle"></i> Informações
                    </div>

                    <div class="form-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-primary);">
                                Título *
                            </label>
                            <input type="text" name="titulo" class="form-control" required
                                   placeholder="Ex: Novos Lançamentos"
                                   style="width: 100%; padding: 10px; background: var(--bg-primary); border: 1px solid var(--border); border-radius: 6px; color: var(--text-primary);">
                        </div>
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-primary);">
                                Subtítulo
                            </label>
                            <input type="text" name="subtitulo" class="form-control"
                                   placeholder="Ex: Confira os jogos mais recentes"
                                   style="width: 100%; padding: 10px; background: var(--bg-primary); border: 1px solid var(--border); border-radius: 6px; color: var(--text-primary);">
                        </div>
                    </div>

                    <!-- IMAGEM -->
                    <div class="form-section-title">
                        <i class="fas fa-image"></i> Imagem
                    </div>

                    <div class="form-group">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-primary);">
                            URL da Imagem *
                        </label>
                        <input type="text" name="imagem_desktop" class="form-control" required
                               placeholder="/assets/images/banners/meu-banner.jpg"
                               style="width: 100%; padding: 10px; background: var(--bg-primary); border: 1px solid var(--border); border-radius: 6px; color: var(--text-primary);">
                        <small class="helper-text">Recomendado: 1920x500px, formato JPG/PNG</small>
                    </div>

                    <!-- CONFIGURAÇÕES -->
                    <div class="form-section-title">
                        <i class="fas fa-cog"></i> Configurações
                    </div>

                    <div class="form-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-primary);">
                                URL de Destino *
                            </label>
                            <input type="text" name="url_destino" class="form-control" required
                                   placeholder="/pages/catalogo.php?categoria=novos"
                                   style="width: 100%; padding: 10px; background: var(--bg-primary); border: 1px solid var(--border); border-radius: 6px; color: var(--text-primary);">
                        </div>
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-primary);">
                                Ordem de Exibição
                            </label>
                            <input type="number" name="ordem" class="form-control" value="0"
                                   style="width: 100%; padding: 10px; background: var(--bg-primary); border: 1px solid var(--border); border-radius: 6px; color: var(--text-primary);">
                            <small class="helper-text">Menor número = aparece primeiro</small>
                        </div>
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-primary);">
                                Data de Início (Opcional)
                            </label>
                            <input type="date" name="data_inicio" class="form-control"
                                   style="width: 100%; padding: 10px; background: var(--bg-primary); border: 1px solid var(--border); border-radius: 6px; color: var(--text-primary);">
                        </div>
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-primary);">
                                Data de Término (Opcional)
                            </label>
                            <input type="date" name="data_fim" class="form-control"
                                   style="width: 100%; padding: 10px; background: var(--bg-primary); border: 1px solid var(--border); border-radius: 6px; color: var(--text-primary);">
                        </div>
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-primary);">
                                Status Inicial
                            </label>
                            <select name="ativo" class="form-control"
                                    style="width: 100%; padding: 10px; background: var(--bg-primary); border: 1px solid var(--border); border-radius: 6px; color: var(--text-primary);">
                                <option value="1">Ativo</option>
                                <option value="0">Inativo</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 24px;">
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
            <?php if (empty($banners)): ?>
                <div class="empty-state">
                    <i class="fas fa-image"></i>
                    <h3>Nenhum banner cadastrado</h3>
                    <p>Clique em "Novo Banner" para criar seu primeiro banner.</p>
                </div>
            <?php else: ?>
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Preview</th>
                            <th>Título</th>
                            <th>Destino</th>
                            <th>Período</th>
                            <th>Ordem</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($banners as $banner): 
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
                                     style="width: 120px; height: 40px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border);"
                                     onerror="this.src='<?= SITE_URL ?>/assets/images/placeholder-banner.jpg'">
                            </td>
                            <td>
                                <strong style="color: var(--text-primary);"><?php echo sanitize($banner['titulo']); ?></strong>
                                <?php if ($banner['subtitulo']): ?>
                                    <br><small style="color: var(--text-secondary);"><?php echo sanitize($banner['subtitulo']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small style="color: var(--text-secondary); font-family: monospace;">
                                    <?= strlen($banner['url_destino']) > 30 ? substr($banner['url_destino'], 0, 30) . '...' : $banner['url_destino'] ?>
                                </small>
                            </td>
                            <td>
                                <div class="banner-dates">
                                    <?php if ($banner['data_inicio']): ?>
                                        <div class="banner-date-item">
                                            <i class="fas fa-calendar-plus"></i>
                                            <span><?= date('d/m/Y', strtotime($banner['data_inicio'])) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($banner['data_fim']): ?>
                                        <div class="banner-date-item">
                                            <i class="fas fa-calendar-times"></i>
                                            <span><?= date('d/m/Y', strtotime($banner['data_fim'])) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div class="banner-date-item">
                                            <i class="fas fa-infinity"></i>
                                            <span>Permanente</span>
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
            <?php endif; ?>
            <?php require_once '../includes/footer.php'; ?>
        </div>
    </div>
</div>

