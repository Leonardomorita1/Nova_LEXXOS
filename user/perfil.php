<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireLogin();

$database = new Database();
$pdo = $database->getConnection();
$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Buscar dados do usuário
$stmt = $pdo->prepare("SELECT * FROM usuario WHERE id = ?");
$stmt->execute([$user_id]);
$usuario = $stmt->fetch();

// Atualizar perfil
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_profile') {
    $nome_completo = trim($_POST['nome_completo']);
    $bio = trim($_POST['bio']);
    
    $stmt = $pdo->prepare("UPDATE usuario SET nome_completo = ?, bio = ? WHERE id = ?");
    if ($stmt->execute([$nome_completo, $bio, $user_id])) {
        $success = 'Perfil atualizado com sucesso!';
        $usuario['nome_completo'] = $nome_completo;
        $usuario['bio'] = $bio;
    }
}

// Alterar senha
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'change_password') {
    $senha_atual = $_POST['senha_atual'];
    $senha_nova = $_POST['senha_nova'];
    $senha_confirmar = $_POST['senha_confirmar'];
    
    if (password_verify($senha_atual, $usuario['senha_hash'])) {
        if ($senha_nova === $senha_confirmar && strlen($senha_nova) >= 6) {
            $senha_hash = password_hash($senha_nova, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE usuario SET senha_hash = ? WHERE id = ?");
            if ($stmt->execute([$senha_hash, $user_id])) {
                $success = 'Senha alterada com sucesso!';
            }
        } else {
            $error = 'Senhas não coincidem ou muito curta';
        }
    } else {
        $error = 'Senha atual incorreta';
    }
}

$page_title = 'Perfil - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<div class="container" style="padding: 30px 0;">
    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-user"></i> Meu Perfil</h1>
    </div>
    
    <?php if ($success): ?>
        <div style="background: rgba(40,167,69,0.1); border: 1px solid var(--success); color: var(--success); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div style="background: rgba(220,53,69,0.1); border: 1px solid var(--danger); color: var(--danger); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
        <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; padding: 30px;">
            <h2 style="margin-bottom: 20px;"><i class="fas fa-info-circle"></i> Informações Pessoais</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-group">
                    <label class="form-label">Nome de Usuário</label>
                    <input type="text" class="form-control" value="<?php echo sanitize($usuario['nome_usuario']); ?>" disabled>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" value="<?php echo sanitize($usuario['email']); ?>" disabled>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Nome Completo</label>
                    <input type="text" name="nome_completo" class="form-control" value="<?php echo sanitize($usuario['nome_completo'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Bio</label>
                    <textarea name="bio" class="form-control" rows="4"><?php echo sanitize($usuario['bio'] ?? ''); ?></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salvar Alterações
                </button>
            </form>
        </div>
        
        <div style="background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 10px; padding: 30px;">
            <h2 style="margin-bottom: 20px;"><i class="fas fa-lock"></i> Alterar Senha</h2>
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label class="form-label">Senha Atual</label>
                    <input type="password" name="senha_atual" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Nova Senha</label>
                    <input type="password" name="senha_nova" class="form-control" minlength="6" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Confirmar Nova Senha</label>
                    <input type="password" name="senha_confirmar" class="form-control" minlength="6" required>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-key"></i> Alterar Senha
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>