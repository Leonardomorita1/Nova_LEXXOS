
<?php
// ============================================
// admin/faq.php
// ============================================
require_once '../config/config.php';
require_once '../config/database.php';
requireLogin();
if (getUserType() !== 'admin') { header('Location: ' . SITE_URL . '/pages/home.php'); exit; }

$database = new Database();
$pdo = $database->getConnection();
$message = '';

// Adicionar FAQ
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add'])) {
    $stmt = $pdo->prepare("INSERT INTO faq (pergunta, resposta, categoria, ordem, ativo) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$_POST['pergunta'], $_POST['resposta'], $_POST['categoria'], $_POST['ordem'], $_POST['ativo']]);
    $message = 'FAQ adicionado!';
}

// Editar FAQ
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit'])) {
    $stmt = $pdo->prepare("UPDATE faq SET pergunta = ?, resposta = ?, categoria = ?, ordem = ?, ativo = ? WHERE id = ?");
    $stmt->execute([$_POST['pergunta'], $_POST['resposta'], $_POST['categoria'], $_POST['ordem'], $_POST['ativo'], $_POST['faq_id']]);
    $message = 'FAQ atualizado!';
}

// Excluir FAQ
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM faq WHERE id = ?");
    $stmt->execute([$_POST['faq_id']]);
    $message = 'FAQ excluído!';
}

$stmt = $pdo->query("SELECT * FROM faq ORDER BY categoria, ordem, id");
$faqs = $stmt->fetchAll();

$page_title = 'Gerenciar FAQ - Admin - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo SITE_URL; ?>/admin/assets/css/admin.css">

<div class="container">
    <div class="admin-layout">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="admin-header">
                <h1 class="admin-title"><i class="fas fa-question-circle"></i> Gerenciar FAQ</h1>
                <button onclick="document.getElementById('formNovo').style.display='block'" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nova Pergunta
                </button>
            </div>

            <?php if ($message): ?>
            <div style="background: rgba(40,167,69,0.1); border: 1px solid var(--success); color: var(--success); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <!-- Formulário Novo FAQ -->
            <div id="formNovo" class="form-section" style="display: none; margin-bottom: 30px;">
                <h2>Nova Pergunta</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Categoria</label>
                        <input type="text" name="categoria" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Pergunta</label>
                        <input type="text" name="pergunta" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Resposta</label>
                        <textarea name="resposta" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="form-grid">
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

            <!-- Lista de FAQs -->
            <?php
            $categorias = array_unique(array_column($faqs, 'categoria'));
            foreach ($categorias as $categoria):
                $faqs_categoria = array_filter($faqs, function($f) use ($categoria) {
                    return $f['categoria'] == $categoria;
                });
            ?>
            <div class="form-section" style="margin-bottom: 20px;">
                <h2><?php echo sanitize($categoria); ?></h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Pergunta</th>
                            <th>Ordem</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($faqs_categoria as $faq): ?>
                        <tr>
                            <td>
                                <strong><?php echo sanitize($faq['pergunta']); ?></strong>
                                <br><small style="color: var(--text-secondary);"><?php echo sanitize(substr($faq['resposta'], 0, 100)); ?>...</small>
                            </td>
                            <td><?php echo $faq['ordem']; ?></td>
                            <td>
                                <span class="badge badge-<?php echo $faq['ativo'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $faq['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button onclick="editarFaq(<?php echo htmlspecialchars(json_encode($faq)); ?>)" 
                                            class="btn-icon edit" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="faq_id" value="<?php echo $faq['id']; ?>">
                                        <button type="submit" name="delete" class="btn-icon delete" title="Excluir" 
                                                onclick="return confirm('Excluir este FAQ?')">
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
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Modal Editar -->
<div id="modalEditar" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 9999; padding: 20px; align-items: center; justify-content: center; overflow-y: auto;">
    <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; max-width: 600px; width: 100%;">
        <div style="padding: 20px; border-bottom: 1px solid var(--border);">
            <h2>Editar FAQ</h2>
        </div>
        <form method="POST" style="padding: 20px;">
            <input type="hidden" name="faq_id" id="edit_id">
            <div class="form-group">
                <label>Categoria</label>
                <input type="text" name="categoria" id="edit_categoria" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Pergunta</label>
                <input type="text" name="pergunta" id="edit_pergunta" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Resposta</label>
                <textarea name="resposta" id="edit_resposta" class="form-control" rows="4" required></textarea>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Ordem</label>
                    <input type="number" name="ordem" id="edit_ordem" class="form-control">
                </div>
                <div class="form-group">
                    <label>Ativo</label>
                    <select name="ativo" id="edit_ativo" class="form-control">
                        <option value="1">Sim</option>
                        <option value="0">Não</option>
                    </select>
                </div>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" name="edit" class="btn btn-primary">Salvar</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalEditar').style.display='none'">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
function editarFaq(faq) {
    document.getElementById('edit_id').value = faq.id;
    document.getElementById('edit_categoria').value = faq.categoria;
    document.getElementById('edit_pergunta').value = faq.pergunta;
    document.getElementById('edit_resposta').value = faq.resposta;
    document.getElementById('edit_ordem').value = faq.ordem;
    document.getElementById('edit_ativo').value = faq.ativo;
    document.getElementById('modalEditar').style.display = 'flex';
}
</script>

<?php require_once '../includes/footer.php'; ?>