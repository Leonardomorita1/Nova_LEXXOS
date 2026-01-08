<?php
// ============================================
// admin/cupons.php
// ============================================
require_once '../config/config.php';
require_once '../config/database.php';
requireLogin();
if (getUserType() !== 'admin') { header('Location: ' . SITE_URL . '/pages/home.php'); exit; }

$database = new Database();
$pdo = $database->getConnection();
$message = '';

// Adicionar cupom
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add'])) {
    $stmt = $pdo->prepare("INSERT INTO cupom (codigo, tipo_desconto, valor_desconto, valor_minimo_centavos, usos_maximos, validade, ativo) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        strtoupper($_POST['codigo']),
        $_POST['tipo_desconto'],
        $_POST['valor_desconto'],
        $_POST['valor_minimo_centavos'],
        $_POST['usos_maximos'] ?: null,
        $_POST['validade'] ?: null,
        $_POST['ativo']
    ]);
    $message = 'Cupom criado!';
}

// Desativar cupom
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

            <!-- Formulário Novo Cupom -->
            <div id="formNovo" class="form-section" style="display: none; margin-bottom: 30px;">
                <h2>Novo Cupom</h2>
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Código</label>
                            <input type="text" name="codigo" class="form-control" required style="text-transform: uppercase;">
                        </div>
                        <div class="form-group">
                            <label>Tipo de Desconto</label>
                            <select name="tipo_desconto" class="form-control">
                                <option value="percentual">Percentual</option>
                                <option value="fixo">Fixo (R$)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Valor do Desconto</label>
                            <input type="number" name="valor_desconto" class="form-control" required>
                            <small style="color: var(--text-secondary);">Se percentual: número de 1-100. Se fixo: centavos</small>
                        </div>
                        <div class="form-group">
                            <label>Valor Mínimo (centavos)</label>
                            <input type="number" name="valor_minimo_centavos" class="form-control" value="0">
                        </div>
                        <div class="form-group">
                            <label>Usos Máximos</label>
                            <input type="number" name="usos_maximos" class="form-control">
                            <small style="color: var(--text-secondary);">Deixe vazio para ilimitado</small>
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
                        <button type="submit" name="add" class="btn btn-primary">Criar Cupom</button>
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('formNovo').style.display='none'">Cancelar</button>
                    </div>
                </form>
            </div>

            <!-- Lista de Cupons -->
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
                            <td>
                                <?php echo $cupom['usos_atuais']; ?>/<?php echo $cupom['usos_maximos'] ?? '∞'; ?>
                            </td>
                            <td>
                                <?php echo $cupom['validade'] ? date('d/m/Y', strtotime($cupom['validade'])) : 'Sem validade'; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $cupom['ativo'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $cupom['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="cupom_id" value="<?php echo $cupom['id']; ?>">
                                    <button type="submit" name="toggle" class="btn-icon edit" title="Ativar/Desativar">
                                        <i class="fas fa-toggle-<?php echo $cupom['ativo'] ? 'on' : 'off'; ?>"></i>
                                    </button>
                                </form>
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
