<?php
// ============================================
// admin/configuracoes.php
// ============================================
require_once '../config/config.php';
require_once '../config/database.php';
requireLogin();
if (getUserType() !== 'admin') { header('Location: ' . SITE_URL . '/pages/home.php'); exit; }

$database = new Database();
$pdo = $database->getConnection();
$message = '';

// Atualizar configurações
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    foreach ($_POST as $chave => $valor) {
        if ($chave != 'submit') {
            $stmt = $pdo->prepare("UPDATE configuracao SET valor = ? WHERE chave = ?");
            $stmt->execute([$valor, $chave]);
        }
    }
    $message = 'Configurações atualizadas com sucesso!';
}

// Buscar configurações
$stmt = $pdo->query("SELECT * FROM configuracao ORDER BY id");
$configuracoes = $stmt->fetchAll();

$page_title = 'Configurações do Site - Admin - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo SITE_URL; ?>/admin/assets/css/admin.css">

<div class="container">
    <div class="admin-layout">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <div class="admin-content">
            <div class="admin-header">
                <h1 class="admin-title"><i class="fas fa-cog"></i> Configurações do Site</h1>
            </div>

            <?php if ($message): ?>
            <div style="background: rgba(40,167,69,0.1); border: 1px solid var(--success); color: var(--success); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-section">
                    <h2>Informações do Site</h2>
                    <div class="form-grid">
                        <?php foreach ($configuracoes as $config): ?>
                        <div class="form-group">
                            <label class="form-label">
                                <?php echo ucfirst(str_replace('_', ' ', $config['chave'])); ?>
                            </label>
                            <?php if ($config['descricao']): ?>
                                <small style="color: var(--text-secondary); display: block; margin-bottom: 5px;">
                                    <?php echo $config['descricao']; ?>
                                </small>
                            <?php endif; ?>
                            
                            <?php if ($config['tipo'] == 'bool'): ?>
                                <select name="<?php echo $config['chave']; ?>" class="form-control">
                                    <option value="0" <?php echo $config['valor'] == '0' ? 'selected' : ''; ?>>Não</option>
                                    <option value="1" <?php echo $config['valor'] == '1' ? 'selected' : ''; ?>>Sim</option>
                                </select>
                            <?php elseif ($config['tipo'] == 'int'): ?>
                                <input type="number" name="<?php echo $config['chave']; ?>" 
                                       class="form-control" value="<?php echo $config['valor']; ?>">
                            <?php else: ?>
                                <input type="text" name="<?php echo $config['chave']; ?>" 
                                       class="form-control" value="<?php echo $config['valor']; ?>">
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <button type="submit" name="submit" class="btn btn-primary btn-lg" style="margin-top: 20px;">
                        <i class="fas fa-save"></i> Salvar Configurações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>