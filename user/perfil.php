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

// Upload de avatar
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'upload_avatar') {
    try {
        $upload_dir = __DIR__ . '/../uploads/avatars';
        $upload_url = '/uploads/avatars';
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            
            if (!in_array($ext, $allowed)) {
                throw new Exception('Tipo de arquivo não permitido');
            }
            
            if ($_FILES['avatar']['size'] > 5242880) {
                throw new Exception('Imagem muito grande (máx 5MB)');
            }
            
            $avatar_name = 'user-' . $user_id . '-' . time() . '.' . $ext;
            $avatar_path = $upload_dir . '/' . $avatar_name;
            
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $avatar_path)) {
                // Remove avatar antigo
                if ($usuario['avatar_url']) {
                    $old_file = __DIR__ . '/..' . $usuario['avatar_url'];
                    if (file_exists($old_file)) unlink($old_file);
                }
                
                $stmt = $pdo->prepare("UPDATE usuario SET avatar_url = ? WHERE id = ?");
                $stmt->execute([$upload_url . '/' . $avatar_name, $user_id]);
                $usuario['avatar_url'] = $upload_url . '/' . $avatar_name;
                $success = 'Avatar atualizado!';
            } else {
                throw new Exception('Erro ao fazer upload');
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
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

<style>
    .avatar-container {
        position: relative;
        width: 150px;
        height: 150px;
        margin: 0 auto 20px;
    }
    .avatar-preview {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid var(--border);
        background: var(--bg-primary);
    }
    .avatar-overlay {
        position: absolute;
        inset: 0;
        background: rgba(0,0,0,0.6);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: 0.3s;
        cursor: pointer;
        color: white;
        flex-direction: column;
        gap: 5px;
    }
    .avatar-container:hover .avatar-overlay {
        opacity: 1;
    }
</style>

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
            
            <!-- Avatar -->
            <form method="POST" enctype="multipart/form-data" id="avatarForm">
                <input type="hidden" name="action" value="upload_avatar">
                <div class="avatar-container" onclick="document.getElementById('avatarInput').click()">
                    <img src="<?php echo $usuario['avatar_url'] ? SITE_URL . $usuario['avatar_url'] : SITE_URL . '/assets/images/default-avatar.png'; ?>" 
                         class="avatar-preview" id="avatarPreview">
                    <div class="avatar-overlay">
                        <i class="fas fa-camera fa-2x"></i>
                        <span>Alterar</span>
                    </div>
                </div>
                <input type="file" name="avatar" id="avatarInput" hidden accept="image/*" onchange="previewAndSubmitAvatar(this)">
            </form>
            
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
                    <input type="text" name="nome_completo" class="form-control" value="<?php echo sanitize($usuario['nome_completo'] ?? ''); ?>" maxlength="200">
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
            
            <hr style="border-color: var(--border); margin: 30px 0;">
            
            <h2 style="margin-bottom: 20px;"><i class="fas fa-info"></i> Informações da Conta</h2>
            <div style="color: var(--text-secondary); line-height: 1.8;">
                <p><strong>Tipo de Conta:</strong> <?php echo ucfirst($usuario['tipo']); ?></p>
                <p><strong>Status:</strong> <?php echo ucfirst($usuario['status']); ?></p>
                <p><strong>Cadastrado em:</strong> <?php echo date('d/m/Y', strtotime($usuario['criado_em'])); ?></p>
                <p><strong>Último Login:</strong> <?php echo $usuario['ultimo_login'] ? date('d/m/Y H:i', strtotime($usuario['ultimo_login'])) : 'Nunca'; ?></p>
            </div>
        </div>
    </div>
</div>

<script>
function previewAndSubmitAvatar(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('avatarPreview').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
        
        // Auto submit
        setTimeout(() => {
            document.getElementById('avatarForm').submit();
        }, 100);
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>