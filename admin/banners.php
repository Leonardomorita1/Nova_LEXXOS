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
        $stmt = $pdo->prepare("INSERT INTO banner (titulo, subtitulo, imagem_desktop, url_destino, ordem, ativo) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['titulo'], $_POST['subtitulo'], $_POST['imagem_desktop'], $_POST['url_destino'], $_POST['ordem'], $_POST['ativo']]);
        $message = 'Banner adicionado!';
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

<div class="container">
    <div class="admin-layout">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="admin-header">
                <h1 class="admin-title"><i class="fas fa-image"></i> Gerenciar Banners</h1>
                <button onclick="document.getElementById('formNovo').style.display='block'" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Novo Banner
                </button>
            </div>

            <?php if ($message): ?>
            <div style="background: rgba(40,167,69,0.1); border: 1px solid var(--success); color: var(--success); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <!-- Formulário Novo Banner -->
            <div id="formNovo" class="form-section" style="display: none; margin-bottom: 30px;">
                <h2>Novo Banner</h2>
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Título</label>
                            <input type="text" name="titulo" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Subtítulo</label>
                            <input type="text" name="subtitulo" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>URL da Imagem</label>
                            <input type="text" name="imagem_desktop" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>URL de Destino</label>
                            <input type="text" name="url_destino" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Ordem</label>
                            <input type="number" name="ordem" class="form-control" value="0">
                        </div>
                        <div class="form-group">
                            <label>Ativo</label>
                            <select name="ativo" class="form-control">
                                <option value="1">Sim</option>
                                <option value="0">Não</option>
                            </select>
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" name="add" class="btn btn-primary">Adicionar</button>
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('formNovo').style.display='none'">Cancelar</button>
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
                            <th>Ordem</th>
                            <th>Status</th>
                            <th>Criado em</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($banners as $banner): ?>
                        <tr>
                            <td>
                                <img src="<?php echo SITE_URL . $banner['imagem_desktop']; ?>" 
                                     style="width: 120px; height: 40px; object-fit: cover; border-radius: 4px;">
                            </td>
                            <td>
                                <strong><?php echo sanitize($banner['titulo']); ?></strong>
                                <?php if ($banner['subtitulo']): ?>
                                    <br><small style="color: var(--text-secondary);"><?php echo sanitize($banner['subtitulo']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $banner['ordem']; ?></td>
                            <td>
                                <span class="badge badge-<?php echo $banner['ativo'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $banner['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($banner['criado_em'])); ?></td>
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
