<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireLogin();
if (getUserType() !== 'admin') { header('Location: ' . SITE_URL . '/pages/home.php'); exit; }

$database = new Database();
$pdo = $database->getConnection();
$message = '';
$error = '';

// Criar/Editar Categoria
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        if ($action == 'create') {
            $nome = trim($_POST['nome']);
            $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9-]/', '', str_replace(' ', '-', $_POST['slug'] ?: $nome))));
            $descricao = trim($_POST['descricao']);
            $icone = trim($_POST['icone']);
            $ordem = (int)$_POST['ordem'];
            $ativa = isset($_POST['ativa']) ? 1 : 0;
            
            // Verifica se slug já existe
            $stmt = $pdo->prepare("SELECT id FROM categoria WHERE slug = ?");
            $stmt->execute([$slug]);
            if ($stmt->fetch()) {
                throw new Exception('Este slug já está em uso');
            }
            
            $stmt = $pdo->prepare("INSERT INTO categoria (nome, slug, descricao, icone, ordem, ativa) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nome, $slug, $descricao, $icone, $ordem, $ativa]);
            $message = 'Categoria criada com sucesso!';
            
        } elseif ($action == 'edit') {
            $id = (int)$_POST['id'];
            $nome = trim($_POST['nome']);
            $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9-]/', '', str_replace(' ', '-', $_POST['slug']))));
            $descricao = trim($_POST['descricao']);
            $icone = trim($_POST['icone']);
            $ordem = (int)$_POST['ordem'];
            $ativa = isset($_POST['ativa']) ? 1 : 0;
            
            // Verifica se slug já existe (exceto o próprio)
            $stmt = $pdo->prepare("SELECT id FROM categoria WHERE slug = ? AND id != ?");
            $stmt->execute([$slug, $id]);
            if ($stmt->fetch()) {
                throw new Exception('Este slug já está em uso');
            }
            
            $stmt = $pdo->prepare("UPDATE categoria SET nome=?, slug=?, descricao=?, icone=?, ordem=?, ativa=? WHERE id=?");
            $stmt->execute([$nome, $slug, $descricao, $icone, $ordem, $ativa, $id]);
            $message = 'Categoria atualizada com sucesso!';
            
        } elseif ($action == 'delete') {
            $id = (int)$_POST['id'];
            
            // Verifica se há jogos usando esta categoria
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM jogo_categoria WHERE categoria_id = ?");
            $stmt->execute([$id]);
            $total = $stmt->fetch()['total'];
            
            if ($total > 0) {
                throw new Exception("Não é possível excluir. Existem $total jogo(s) usando esta categoria.");
            }
            
            $stmt = $pdo->prepare("DELETE FROM categoria WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Categoria excluída com sucesso!';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Buscar todas as categorias
$stmt = $pdo->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM jogo_categoria jc WHERE jc.categoria_id = c.id) as total_jogos
    FROM categoria c
    ORDER BY c.ordem ASC, c.nome ASC
");
$categorias = $stmt->fetchAll();

$page_title = 'Gerenciar Categorias - Admin - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<link rel="stylesheet" href="<?= SITE_URL ?>/admin/assets/css/admin.css">

<div class="container">
    <div class="admin-layout">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="admin-header">
                <h1 class="admin-title"><i class="fas fa-folder"></i> Gerenciar Categorias</h1>
                <button onclick="openModal('create')" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nova Categoria
                </button>
            </div>

            <?php if ($message): ?>
            <div style="background: rgba(40,167,69,0.1); border: 1px solid var(--success); color: var(--success); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i> <?= $message ?>
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div style="background: rgba(220,53,69,0.1); border: 1px solid var(--danger); color: var(--danger); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-circle"></i> <?= $error ?>
            </div>
            <?php endif; ?>

            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th width="50">Ícone</th>
                            <th>Nome</th>
                            <th>Slug</th>
                            <th width="100">Ordem</th>
                            <th width="100">Jogos</th>
                            <th width="80">Status</th>
                            <th width="120">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categorias as $cat): ?>
                        <tr>
                            <td style="text-align: center;">
                                <i class="fas fa-<?= sanitize($cat['icone']) ?>" style="font-size: 20px; color: var(--accent);"></i>
                            </td>
                            <td><strong><?= sanitize($cat['nome']) ?></strong></td>
                            <td><code style="background: var(--bg-primary); padding: 4px 8px; border-radius: 4px;"><?= $cat['slug'] ?></code></td>
                            <td style="text-align: center;"><?= $cat['ordem'] ?></td>
                            <td style="text-align: center;">
                                <?php if ($cat['total_jogos'] > 0): ?>
                                <span class="badge badge-info"><?= $cat['total_jogos'] ?></span>
                                <?php else: ?>
                                <span style="color: var(--text-secondary);">0</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge badge-<?= $cat['ativa'] ? 'success' : 'secondary' ?>">
                                    <?= $cat['ativa'] ? 'Ativa' : 'Inativa' ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button onclick='editCategoria(<?= json_encode($cat) ?>)' class="btn-icon edit" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="deleteCategoria(<?= $cat['id'] ?>, '<?= sanitize($cat['nome']) ?>')" 
                                            class="btn-icon delete" title="Excluir">
                                        <i class="fas fa-trash"></i>
                                    </button>
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

<!-- Modal Criar/Editar -->
<div id="categoriaModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; max-width: 600px; width: 100%; max-height: 90vh; overflow-y: auto;">
        <div style="padding: 25px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <h2 id="modalTitle">Nova Categoria</h2>
            <button onclick="closeModal()" style="background: none; border: none; color: var(--text-primary); font-size: 24px; cursor: pointer;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST" style="padding: 25px;">
            <input type="hidden" name="action" id="modalAction" value="create">
            <input type="hidden" name="id" id="categoriaId">
            
            <div class="form-group">
                <label class="form-label">Nome da Categoria *</label>
                <input type="text" name="nome" id="categoriaNome" class="form-control" required maxlength="100">
            </div>
            
            <div class="form-group">
                <label class="form-label">Slug (URL amigável)</label>
                <input type="text" name="slug" id="categoriaSlug" class="form-control" maxlength="100">
                <small style="color: var(--text-secondary);">Deixe em branco para gerar automaticamente</small>
            </div>
            
            <div class="form-group">
                <label class="form-label">Descrição</label>
                <textarea name="descricao" id="categoriaDescricao" class="form-control" rows="3"></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">Ícone Font Awesome *</label>
                <div style="display: flex; gap: 10px;">
                    <input type="text" name="icone" id="categoriaIcone" class="form-control" placeholder="gamepad" required 
                           style="flex: 1;" onkeyup="previewIcon()">
                    <div style="width: 50px; height: 50px; border: 1px solid var(--border); border-radius: 8px; display: flex; align-items: center; justify-content: center; background: var(--bg-primary);">
                        <i id="iconPreview" class="fas fa-gamepad" style="font-size: 24px; color: var(--accent);"></i>
                    </div>
                </div>
                <small style="color: var(--text-secondary);">
                    Digite apenas o nome (ex: gamepad, dragon, chess). 
                    <a href="https://fontawesome.com/icons" target="_blank" style="color: var(--accent);">Ver ícones disponíveis</a>
                </small>
            </div>
            
            <div class="form-group">
                <label class="form-label">Ordem de Exibição</label>
                <input type="number" name="ordem" id="categoriaOrdem" class="form-control" value="0" min="0">
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="ativa" id="categoriaAtiva" checked>
                    <span>Categoria ativa</span>
                </label>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 25px;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">
                    <i class="fas fa-save"></i> Salvar
                </button>
                <button type="button" onclick="closeModal()" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Excluir -->
<div id="deleteModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; max-width: 400px; width: 100%;">
        <div style="padding: 25px;">
            <h2 style="margin-bottom: 15px;"><i class="fas fa-exclamation-triangle" style="color: var(--danger);"></i> Confirmar Exclusão</h2>
            <p id="deleteMessage" style="color: var(--text-secondary); margin-bottom: 25px;"></p>
            <form method="POST" style="display: flex; gap: 10px;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteId">
                <button type="submit" class="btn btn-danger" style="flex: 1;">
                    <i class="fas fa-trash"></i> Excluir
                </button>
                <button type="button" onclick="closeDeleteModal()" class="btn btn-secondary">
                    Cancelar
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function openModal(action) {
    document.getElementById('categoriaModal').style.display = 'flex';
    document.getElementById('modalAction').value = action;
    
    if (action === 'create') {
        document.getElementById('modalTitle').textContent = 'Nova Categoria';
        document.getElementById('categoriaId').value = '';
        document.getElementById('categoriaNome').value = '';
        document.getElementById('categoriaSlug').value = '';
        document.getElementById('categoriaDescricao').value = '';
        document.getElementById('categoriaIcone').value = 'gamepad';
        document.getElementById('categoriaOrdem').value = '0';
        document.getElementById('categoriaAtiva').checked = true;
        previewIcon();
    }
}

function closeModal() {
    document.getElementById('categoriaModal').style.display = 'none';
}

function editCategoria(cat) {
    openModal('edit');
    document.getElementById('modalTitle').textContent = 'Editar Categoria';
    document.getElementById('modalAction').value = 'edit';
    document.getElementById('categoriaId').value = cat.id;
    document.getElementById('categoriaNome').value = cat.nome;
    document.getElementById('categoriaSlug').value = cat.slug;
    document.getElementById('categoriaDescricao').value = cat.descricao || '';
    document.getElementById('categoriaIcone').value = cat.icone;
    document.getElementById('categoriaOrdem').value = cat.ordem;
    document.getElementById('categoriaAtiva').checked = cat.ativa == 1;
    previewIcon();
}

function deleteCategoria(id, nome) {
    document.getElementById('deleteModal').style.display = 'flex';
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteMessage').textContent = `Tem certeza que deseja excluir a categoria "${nome}"?`;
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

function previewIcon() {
    const iconName = document.getElementById('categoriaIcone').value.trim() || 'gamepad';
    document.getElementById('iconPreview').className = 'fas fa-' + iconName;
}

// Fechar modal ao clicar fora
document.getElementById('categoriaModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

document.getElementById('deleteModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});
</script>

<?php require_once '../includes/footer.php'; ?>