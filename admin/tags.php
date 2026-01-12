<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireLogin();
if (getUserType() !== 'admin') { header('Location: ' . SITE_URL . '/pages/home.php'); exit; }

$database = new Database();
$pdo = $database->getConnection();
$message = '';
$error = '';

// Criar/Editar Tag
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        if ($action == 'create') {
            $nome = trim($_POST['nome']);
            $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9-]/', '', str_replace(' ', '-', $_POST['slug'] ?: $nome))));
            
            // Verifica se slug já existe
            $stmt = $pdo->prepare("SELECT id FROM tag WHERE slug = ?");
            $stmt->execute([$slug]);
            if ($stmt->fetch()) {
                throw new Exception('Este slug já está em uso');
            }
            
            $stmt = $pdo->prepare("INSERT INTO tag (nome, slug) VALUES (?, ?)");
            $stmt->execute([$nome, $slug]);
            $message = 'Tag criada com sucesso!';
            
        } elseif ($action == 'edit') {
            $id = (int)$_POST['id'];
            $nome = trim($_POST['nome']);
            $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9-]/', '', str_replace(' ', '-', $_POST['slug']))));
            
            // Verifica se slug já existe (exceto o próprio)
            $stmt = $pdo->prepare("SELECT id FROM tag WHERE slug = ? AND id != ?");
            $stmt->execute([$slug, $id]);
            if ($stmt->fetch()) {
                throw new Exception('Este slug já está em uso');
            }
            
            $stmt = $pdo->prepare("UPDATE tag SET nome=?, slug=? WHERE id=?");
            $stmt->execute([$nome, $slug, $id]);
            $message = 'Tag atualizada com sucesso!';
            
        } elseif ($action == 'delete') {
            $id = (int)$_POST['id'];
            
            // Verifica se há jogos usando esta tag
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM jogo_tag WHERE tag_id = ?");
            $stmt->execute([$id]);
            $total = $stmt->fetch()['total'];
            
            if ($total > 0) {
                throw new Exception("Não é possível excluir. Existem $total jogo(s) usando esta tag.");
            }
            
            $stmt = $pdo->prepare("DELETE FROM tag WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Tag excluída com sucesso!';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Filtros e Paginação
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 30;
$offset = ($page - 1) * $per_page;

$where = ['1=1'];
$params = [];

if ($search) {
    $where[] = 'nome LIKE ?';
    $params[] = '%' . $search . '%';
}

$where_sql = implode(' AND ', $where);

// Total de tags
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tag WHERE $where_sql");
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$total_pages = ceil($total / $per_page);

// Buscar tags
$stmt = $pdo->prepare("
    SELECT t.*, 
           (SELECT COUNT(*) FROM jogo_tag jt WHERE jt.tag_id = t.id) as total_jogos
    FROM tag t
    WHERE $where_sql
    ORDER BY t.nome ASC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$tags = $stmt->fetchAll();

$page_title = 'Gerenciar Tags - Admin - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<link rel="stylesheet" href="<?= SITE_URL ?>/admin/assets/css/admin.css">

<div class="container">
    <div class="admin-layout">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="admin-header">
                <div>
                    <h1 class="admin-title"><i class="fas fa-tags"></i> Gerenciar Tags</h1>
                    <p style="color: var(--text-secondary); margin-top: 5px;">Total: <?= $total ?> tags</p>
                </div>
                <button onclick="openModal('create')" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nova Tag
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

            <!-- Filtro de Busca -->
            <form method="GET" class="filters" style="margin-bottom: 20px;">
                <div style="display: flex; gap: 10px;">
                    <input type="text" name="search" placeholder="Buscar tags..." class="form-control" 
                           value="<?= sanitize($search) ?>" style="flex: 1;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                    <?php if ($search): ?>
                    <a href="<?= SITE_URL ?>/admin/tags.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </form>

            <!-- Grid de Tags -->
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px;">
                <?php foreach ($tags as $tag): ?>
                <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; padding: 20px; position: relative;">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                        <div style="flex: 1;">
                            <h3 style="font-size: 18px; margin: 0 0 5px 0; color: var(--text-primary);">
                                <i class="fas fa-tag" style="color: var(--accent); margin-right: 8px;"></i>
                                <?= sanitize($tag['nome']) ?>
                            </h3>
                            <code style="font-size: 12px; background: var(--bg-primary); padding: 3px 8px; border-radius: 4px; color: var(--text-secondary);">
                                <?= $tag['slug'] ?>
                            </code>
                        </div>
                        <div style="display: flex; gap: 5px;">
                            <button onclick='editTag(<?= json_encode($tag) ?>)' 
                                    class="btn-icon edit" title="Editar" 
                                    style="width: 32px; height: 32px;">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteTag(<?= $tag['id'] ?>, '<?= sanitize($tag['nome']) ?>')" 
                                    class="btn-icon delete" title="Excluir"
                                    style="width: 32px; height: 32px;">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 15px; border-top: 1px solid var(--border);">
                        <span style="color: var(--text-secondary); font-size: 13px;">
                            <i class="fas fa-gamepad"></i> Jogos
                        </span>
                        <?php if ($tag['total_jogos'] > 0): ?>
                        <span class="badge badge-info"><?= $tag['total_jogos'] ?></span>
                        <?php else: ?>
                        <span style="color: var(--text-secondary);">0</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (empty($tags)): ?>
            <div style="text-align: center; padding: 60px 20px; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px;">
                <i class="fas fa-tags" style="font-size: 48px; color: var(--text-secondary); margin-bottom: 15px;"></i>
                <p style="color: var(--text-secondary); font-size: 16px;">
                    <?= $search ? 'Nenhuma tag encontrada' : 'Nenhuma tag cadastrada ainda' ?>
                </p>
                <?php if (!$search): ?>
                <button onclick="openModal('create')" class="btn btn-primary" style="margin-top: 15px;">
                    <i class="fas fa-plus"></i> Criar Primeira Tag
                </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Paginação -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination" style="margin-top: 30px;">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                       class="<?= $i == $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Criar/Editar -->
<div id="tagModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; max-width: 500px; width: 100%;">
        <div style="padding: 25px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <h2 id="modalTitle">Nova Tag</h2>
            <button onclick="closeModal()" style="background: none; border: none; color: var(--text-primary); font-size: 24px; cursor: pointer;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST" style="padding: 25px;">
            <input type="hidden" name="action" id="modalAction" value="create">
            <input type="hidden" name="id" id="tagId">
            
            <div class="form-group">
                <label class="form-label">Nome da Tag *</label>
                <input type="text" name="nome" id="tagNome" class="form-control" required maxlength="100" 
                       placeholder="Ex: Multiplayer, RPG, Pixel Art...">
            </div>
            
            <div class="form-group">
                <label class="form-label">Slug (URL amigável)</label>
                <input type="text" name="slug" id="tagSlug" class="form-control" maxlength="100"
                       placeholder="Ex: multiplayer, rpg, pixel-art">
                <small style="color: var(--text-secondary);">Deixe em branco para gerar automaticamente</small>
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
    document.getElementById('tagModal').style.display = 'flex';
    document.getElementById('modalAction').value = action;
    
    if (action === 'create') {
        document.getElementById('modalTitle').textContent = 'Nova Tag';
        document.getElementById('tagId').value = '';
        document.getElementById('tagNome').value = '';
        document.getElementById('tagSlug').value = '';
    }
}

function closeModal() {
    document.getElementById('tagModal').style.display = 'none';
}

function editTag(tag) {
    openModal('edit');
    document.getElementById('modalTitle').textContent = 'Editar Tag';
    document.getElementById('modalAction').value = 'edit';
    document.getElementById('tagId').value = tag.id;
    document.getElementById('tagNome').value = tag.nome;
    document.getElementById('tagSlug').value = tag.slug;
}

function deleteTag(id, nome) {
    document.getElementById('deleteModal').style.display = 'flex';
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteMessage').textContent = `Tem certeza que deseja excluir a tag "${nome}"?`;
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

// Fechar modal ao clicar fora
document.getElementById('tagModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

document.getElementById('deleteModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});

// Auto-gerar slug ao digitar o nome
document.getElementById('tagNome')?.addEventListener('input', function(e) {
    if (!document.getElementById('tagSlug').value) {
        const slug = e.target.value.toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
        document.getElementById('tagSlug').value = slug;
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>