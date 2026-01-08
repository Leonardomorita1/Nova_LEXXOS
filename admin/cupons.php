<?php
// ============================================
// admin/cupons.php (COMPLETO - Editar e Deletar)
// ============================================
require_once '../config/config.php';
require_once '../config/database.php';
requireLogin();
if (getUserType() !== 'admin') { header('Location: ' . SITE_URL . '/pages/home.php'); exit; }

$database = new Database();
$pdo = $database->getConnection();
$message = '';

// Adicionar
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add'])) {
    $stmt = $pdo->prepare("INSERT INTO cupom (codigo, tipo_desconto, valor_desconto, valor_minimo_centavos, usos_maximos, validade, ativo) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([strtoupper($_POST['codigo']), $_POST['tipo_desconto'], $_POST['valor_desconto'], $_POST['valor_minimo_centavos'], $_POST['usos_maximos'] ?: null, $_POST['validade'] ?: null, $_POST['ativo']]);
    $message = 'Cupom criado!';
}

// Editar
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit'])) {
    $stmt = $pdo->prepare("UPDATE cupom SET codigo=?, tipo_desconto=?, valor_desconto=?, valor_minimo_centavos=?, usos_maximos=?, validade=?, ativo=? WHERE id=?");
    $stmt->execute([strtoupper($_POST['codigo']), $_POST['tipo_desconto'], $_POST['valor_desconto'], $_POST['valor_minimo_centavos'], $_POST['usos_maximos'] ?: null, $_POST['validade'] ?: null, $_POST['ativo'], $_POST['cupom_id']]);
    $message = 'Cupom atualizado!';
}

// Deletar
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM cupom WHERE id = ?");
    $stmt->execute([$_POST['cupom_id']]);
    $message = 'Cupom excluído!';
}

// Toggle
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle'])) {
    $stmt = $pdo->prepare("UPDATE cupom SET ativo = NOT ativo WHERE id = ?");
    $stmt->execute([$_POST['cupom_id']]);
    $message = 'Status atualizado!';
}

$stmt = $pdo->query("SELECT * FROM cupom ORDER BY criado_em DESC");
$cupons = $stmt->fetchAll();

$page_title = 'Gerenciar Cupons - Admin - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo SITE_URL; ?>/admin/assets/css/admin.css">

<div class="container">
    <div class="admin-layout">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="admin-header">
                <h1 class="admin-title"><i class="fas fa-ticket-alt"></i> Gerenciar Cupons</h1>
                <button onclick="document.getElementById('formNovo').style.display='block'" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Novo Cupom
                </button>
            </div>

            <?php if ($message): ?>
            <div style="background: rgba(40,167,69,0.1); border: 1px solid var(--success); color: var(--success); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <!-- Form Novo -->
            <div id="formNovo" class="form-section" style="display: none; margin-bottom: 30px;">
                <h2>Novo Cupom</h2>
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Código</label>
                            <input type="text" name="codigo" class="form-control" required style="text-transform: uppercase;">
                        </div>
                        <div class="form-group">
                            <label>Tipo</label>
                            <select name="tipo_desconto" class="form-control">
                                <option value="percentual">Percentual</option>
                                <option value="fixo">Fixo (R$)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Valor do Desconto</label>
                            <input type="number" name="valor_desconto" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Valor Mínimo (centavos)</label>
                            <input type="number" name="valor_minimo_centavos" class="form-control" value="0">
                        </div>
                        <div class="form-group">
                            <label>Usos Máximos</label>
                            <input type="number" name="usos_maximos" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Validade</label>
                            <input type="date" name="validade" class="form-control">
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
                        <button type="submit" name="add" class="btn btn-primary">Criar</button>
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('formNovo').style.display='none'">Cancelar</button>
                    </div>
                </form>
            </div>

            <!-- Lista -->
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Desconto</th>
                            <th>Valor Mínimo</th>
                            <th>Usos</th>
                            <th>Validade</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cupons as $cupom): ?>
                        <tr>
                            <td><strong><code><?php echo $cupom['codigo']; ?></code></strong></td>
                            <td>
                                <?php if ($cupom['tipo_desconto'] == 'percentual'): ?>
                                    <?php echo $cupom['valor_desconto']; ?>%
                                <?php else: ?>
                                    <?php echo formatPrice($cupom['valor_desconto']); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo formatPrice($cupom['valor_minimo_centavos']); ?></td>
                            <td><?php echo $cupom['usos_atuais']; ?>/<?php echo $cupom['usos_maximos'] ?? '∞'; ?></td>
                            <td><?php echo $cupom['validade'] ? date('d/m/Y', strtotime($cupom['validade'])) : 'Sem validade'; ?></td>
                            <td><span class="badge badge-<?php echo $cupom['ativo'] ? 'success' : 'secondary'; ?>"><?php echo $cupom['ativo'] ? 'Ativo' : 'Inativo'; ?></span></td>
                            <td>
                                <div class="action-buttons">
                                    <button onclick="editarCupom(<?php echo htmlspecialchars(json_encode($cupom)); ?>)" class="btn-icon edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="cupom_id" value="<?php echo $cupom['id']; ?>">
                                        <button type="submit" name="toggle" class="btn-icon edit">
                                            <i class="fas fa-toggle-<?php echo $cupom['ativo'] ? 'on' : 'off'; ?>"></i>
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="cupom_id" value="<?php echo $cupom['id']; ?>">
                                        <button type="submit" name="delete" class="btn-icon delete" onclick="return confirm('Excluir?')">
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

<!-- Modal Editar -->
<div id="modalEdit" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 9999; padding: 20px; align-items: center; justify-content: center; overflow-y: auto;">
    <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; max-width: 700px; width: 100%;">
        <div style="padding: 20px; border-bottom: 1px solid var(--border);"><h2>Editar Cupom</h2></div>
        <form method="POST" style="padding: 20px;">
            <input type="hidden" name="cupom_id" id="edit_id">
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                <div class="form-group"><label>Código</label><input type="text" name="codigo" id="edit_codigo" class="form-control" required></div>
                <div class="form-group"><label>Tipo</label><select name="tipo_desconto" id="edit_tipo" class="form-control"><option value="percentual">Percentual</option><option value="fixo">Fixo</option></select></div>
                <div class="form-group"><label>Valor</label><input type="number" name="valor_desconto" id="edit_valor" class="form-control" required></div>
                <div class="form-group"><label>Mínimo (centavos)</label><input type="number" name="valor_minimo_centavos" id="edit_minimo" class="form-control"></div>
                <div class="form-group"><label>Usos Máx</label><input type="number" name="usos_maximos" id="edit_usos" class="form-control"></div>
                <div class="form-group"><label>Validade</label><input type="date" name="validade" id="edit_validade" class="form-control"></div>
                <div class="form-group"><label>Ativo</label><select name="ativo" id="edit_ativo" class="form-control"><option value="1">Sim</option><option value="0">Não</option></select></div>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" name="edit" class="btn btn-primary">Salvar</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalEdit').style.display='none'">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
function editarCupom(cupom) {
    document.getElementById('edit_id').value = cupom.id;
    document.getElementById('edit_codigo').value = cupom.codigo;
    document.getElementById('edit_tipo').value = cupom.tipo_desconto;
    document.getElementById('edit_valor').value = cupom.valor_desconto;
    document.getElementById('edit_minimo').value = cupom.valor_minimo_centavos;
    document.getElementById('edit_usos').value = cupom.usos_maximos || '';
    document.getElementById('edit_validade').value = cupom.validade || '';
    document.getElementById('edit_ativo').value = cupom.ativo;
    document.getElementById('modalEdit').style.display = 'flex';
}
</script>

<?php require_once '../includes/footer.php'; ?>