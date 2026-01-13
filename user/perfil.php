<?php
// user/perfil.php
require_once '../config/config.php';
require_once '../config/database.php';
requireLogin();

$database = new Database();
$pdo = $database->getConnection();
$user_id = $_SESSION['user_id'];

$success = '';
$error = '';
$active_tab = $_GET['tab'] ?? 'pessoal';

// ==========================================================
// PROCESSAMENTO DO FORMULÁRIO
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $active_tab = $_POST['tab_origin'] ?? 'pessoal';

    try {
        if ($action == 'update_info') {
            $nome_completo = trim($_POST['nome_completo']);
            $cpf = trim($_POST['cpf']);
            $data_nascimento = $_POST['data_nascimento'];
            $bio = trim($_POST['bio']);

            if (!empty($cpf)) {
                $stmt = $pdo->prepare("SELECT id FROM usuario WHERE cpf = ? AND id != ?");
                $stmt->execute([$cpf, $user_id]);
                if ($stmt->fetch()) throw new Exception("Este CPF já está em uso.");
            }

            $stmt = $pdo->prepare("UPDATE usuario SET nome_completo = ?, cpf = ?, data_nascimento = ?, bio = ? WHERE id = ?");
            $stmt->execute([$nome_completo, $cpf, $data_nascimento, $bio, $user_id]);
            $success = "Dados atualizados com sucesso!";
        }

        elseif ($action == 'update_password') {
            $senha_atual = $_POST['senha_atual'];
            $senha_nova = $_POST['senha_nova'];
            $senha_confirma = $_POST['senha_confirma'];

            $stmt = $pdo->prepare("SELECT senha_hash FROM usuario WHERE id = ?");
            $stmt->execute([$user_id]);
            $current = $stmt->fetch();

            if (!password_verify($senha_atual, $current['senha_hash'])) {
                throw new Exception("Senha atual incorreta.");
            }
            if ($senha_nova !== $senha_confirma) {
                throw new Exception("As senhas não coincidem.");
            }
            if (strlen($senha_nova) < 6) {
                throw new Exception("Mínimo 6 caracteres.");
            }

            $novo_hash = password_hash($senha_nova, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE usuario SET senha_hash = ? WHERE id = ?");
            $stmt->execute([$novo_hash, $user_id]);
            $success = "Senha alterada com sucesso!";
        }

        elseif ($action == 'upload_avatar') {
            if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] != 0) {
                throw new Exception("Selecione uma imagem válida.");
            }

            $file = $_FILES['avatar'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($ext, $allowed)) throw new Exception("Formato não permitido.");
            if ($file['size'] > 5 * 1024 * 1024) throw new Exception("Máximo 5MB.");

            $upload_dir = '../uploads/avatars/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            $new_name = 'user_' . $user_id . '_' . time() . '.' . $ext;
            
            if (move_uploaded_file($file['tmp_name'], $upload_dir . $new_name)) {
                $stmt = $pdo->prepare("SELECT avatar_url FROM usuario WHERE id = ?");
                $stmt->execute([$user_id]);
                $old = $stmt->fetchColumn();
                if ($old && file_exists(__DIR__ . '/../' . $old) && strpos($old, 'default') === false) {
                    @unlink(__DIR__ . '/../' . $old);
                }

                $db_path = '/uploads/avatars/' . $new_name;
                $stmt = $pdo->prepare("UPDATE usuario SET avatar_url = ? WHERE id = ?");
                $stmt->execute([$db_path, $user_id]);
                $success = "Foto atualizada!";
            }
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Buscar dados atualizados
$stmt = $pdo->prepare("SELECT * FROM usuario WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Stats do usuário
$stmt = $pdo->prepare("SELECT COUNT(*) FROM biblioteca WHERE usuario_id = ?");
$stmt->execute([$user_id]);
$total_jogos = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM avaliacao WHERE usuario_id = ?");
$stmt->execute([$user_id]);
$total_avaliacoes = $stmt->fetchColumn();

$membro_desde = date('M Y', strtotime($user['criado_em']));

$page_title = "Meu Perfil - " . SITE_NAME;
require_once '../includes/header.php';
?>

<style>
/* ============================================
   PROFILE PAGE - LAYOUT
   ============================================ */
.profile-page {
    padding: 40px 0;
    min-height: calc(100vh - 80px);
}

.profile-container {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 30px;
    max-width: 1200px;
    margin: 0 auto;
}

/* ============================================
   SIDEBAR CARD
   ============================================ */
.profile-sidebar {
    position: sticky;
    top: 100px;
    height: fit-content;
}

.profile-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 20px;
    overflow: hidden;
}

.profile-card-header {
    position: relative;
    padding: 30px;
    background: linear-gradient(135deg, rgba(14, 165, 233, 0.15), rgba(6, 182, 212, 0.05));
    text-align: center;
}

.profile-card-header::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--border), transparent);
}

/* Avatar */
.avatar-container {
    position: relative;
    width: 120px;
    height: 120px;
    margin: 0 auto 20px;
}

.avatar-ring {
    position: absolute;
    inset: -4px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--accent), #06b6d4);
    animation: rotate 8s linear infinite;
}

@keyframes rotate {
    to { transform: rotate(360deg); }
}

.avatar-inner {
    position: relative;
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: var(--bg-secondary);
    padding: 4px;
}

.avatar-img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}

.avatar-edit {
    position: absolute;
    bottom: 4px;
    right: 4px;
    width: 36px;
    height: 36px;
    background: var(--accent);
    border: 3px solid var(--bg-secondary);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #000;
    cursor: pointer;
    transition: transform 0.2s;
    z-index: 2;
}

.avatar-edit:hover {
    transform: scale(1.1);
}

.avatar-edit input {
    display: none;
}

/* User Info */
.user-name {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 4px;
}

.user-handle {
    color: var(--text-secondary);
    font-size: 14px;
    margin-bottom: 12px;
}

.user-badges {
    display: flex;
    justify-content: center;
    gap: 8px;
    flex-wrap: wrap;
}

.badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.badge-verified {
    background: rgba(16, 185, 129, 0.15);
    color: var(--success);
}

.badge-member {
    background: rgba(14, 165, 233, 0.15);
    color: var(--accent);
}

/* Stats */
.profile-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    padding: 20px;
    background: var(--bg-primary);
    margin: 16px;
    border-radius: 12px;
}

.profile-stat {
    text-align: center;
    padding: 8px;
}

.profile-stat:not(:last-child) {
    border-right: 1px solid var(--border);
}

.profile-stat .value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
}

.profile-stat .label {
    font-size: 11px;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Navigation Menu */
.profile-nav {
    padding: 12px;
}

.profile-nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    padding: 14px 16px;
    background: transparent;
    border: none;
    border-radius: 10px;
    color: var(--text-secondary);
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    text-align: left;
    margin-bottom: 4px;
}

.profile-nav-item:hover {
    background: var(--bg-primary);
    color: var(--text-primary);
}

.profile-nav-item.active {
    background: var(--accent);
    color: #000;
}

.profile-nav-item i {
    width: 20px;
    text-align: center;
    font-size: 15px;
}

.profile-nav-divider {
    height: 1px;
    background: var(--border);
    margin: 12px 4px;
}

.profile-nav-item.danger {
    color: var(--danger);
}

.profile-nav-item.danger:hover {
    background: rgba(239, 68, 68, 0.1);
}

/* ============================================
   MAIN CONTENT
   ============================================ */
.profile-content {
    min-height: 600px;
}

.content-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 20px;
    overflow: hidden;
}

.content-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 24px 30px;
    border-bottom: 1px solid var(--border);
}

.content-header h2 {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 1.25rem;
    font-weight: 700;
}

.content-header h2 i {
    width: 40px;
    height: 40px;
    background: rgba(14, 165, 233, 0.1);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--accent);
}

.content-body {
    padding: 30px;
}

/* Tab Panels */
.tab-panel {
    display: none;
    animation: fadeIn 0.3s ease;
}

.tab-panel.active {
    display: block;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ============================================
   FORMS
   ============================================ */
.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 24px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-group label {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-secondary);
}

.form-control {
    width: 100%;
    padding: 14px 16px;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: 10px;
    color: var(--text-primary);
    font-size: 15px;
    transition: all 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
}

.form-control:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.form-control::placeholder {
    color: var(--text-muted);
}

textarea.form-control {
    resize: vertical;
    min-height: 120px;
}

.form-hint {
    font-size: 12px;
    color: var(--text-muted);
}

.form-row {
    display: flex;
    gap: 12px;
    align-items: center;
}

.form-row .form-control {
    flex: 1;
}

.input-lock {
    width: 44px;
    height: 44px;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px 24px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
}

.btn-primary {
    background: var(--accent);
    color: #000;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(14, 165, 233, 0.3);
}

.btn-outline {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text-primary);
}

.btn-outline:hover {
    border-color: var(--accent);
    color: var(--accent);
}

.form-actions {
    margin-top: 30px;
    padding-top: 24px;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

/* ============================================
   ALERTS
   ============================================ */
.alert {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    font-size: 14px;
}

.alert-success {
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: var(--success);
}

.alert-error {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: var(--danger);
}

/* ============================================
   SECURITY TAB
   ============================================ */
.security-card {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
}

.security-card h4 {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 15px;
    font-weight: 600;
    margin-bottom: 12px;
}

.security-card h4 i {
    color: var(--accent);
}

.security-card p {
    font-size: 13px;
    color: var(--text-secondary);
    line-height: 1.5;
}

.danger-zone {
    background: rgba(239, 68, 68, 0.05);
    border-color: rgba(239, 68, 68, 0.2);
    margin-top: 30px;
}

.danger-zone h4 {
    color: var(--danger);
}

.danger-zone h4 i {
    color: var(--danger);
}

/* ============================================
   PREFERENCES TAB
   ============================================ */
.preference-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: 12px;
    margin-bottom: 12px;
}

.preference-info h4 {
    font-size: 15px;
    font-weight: 600;
    margin-bottom: 4px;
}

.preference-info p {
    font-size: 13px;
    color: var(--text-secondary);
}

.toggle-switch {
    position: relative;
    width: 52px;
    height: 28px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    inset: 0;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 50px;
    transition: 0.3s;
}

.toggle-slider::before {
    content: '';
    position: absolute;
    width: 20px;
    height: 20px;
    left: 4px;
    top: 50%;
    transform: translateY(-50%);
    background: var(--text-secondary);
    border-radius: 50%;
    transition: 0.3s;
}

.toggle-switch input:checked + .toggle-slider {
    background: var(--accent);
    border-color: var(--accent);
}

.toggle-switch input:checked + .toggle-slider::before {
    transform: translateY(-50%) translateX(24px);
    background: #000;
}

/* Theme Buttons */
.theme-options {
    display: flex;
    gap: 8px;
}

.theme-btn {
    padding: 10px 16px;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 6px;
}

.theme-btn:hover,
.theme-btn.active {
    border-color: var(--accent);
    color: var(--accent);
}

/* ============================================
   RESPONSIVE
   ============================================ */
@media (max-width: 900px) {
    .profile-container {
        grid-template-columns: 1fr;
    }
    
    .profile-sidebar {
        position: static;
    }
    
    .profile-card-header {
        padding: 24px;
    }
    
    .content-body {
        padding: 20px;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .profile-stats {
        grid-template-columns: 1fr;
    }
    
    .profile-stat:not(:last-child) {
        border-right: none;
        border-bottom: 1px solid var(--border);
        padding-bottom: 12px;
        margin-bottom: 12px;
    }
    
    .preference-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
    }
}
</style>

<div class="profile-page">
    <div class="container">
        <!-- Alerts -->
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <div class="profile-container">
            <!-- ============================================
                 SIDEBAR
                 ============================================ -->
            <aside class="profile-sidebar">
                <div class="profile-card">
                    <div class="profile-card-header">
                        <!-- Avatar -->
                        <div class="avatar-container">
                            <div class="avatar-ring"></div>
                            <div class="avatar-inner">
                                <img src="<?= SITE_URL ?><?= $user['avatar_url'] ?? '/assets/images/default-avatar.png' ?>" 
                                     class="avatar-img" 
                                     id="avatarPreview"
                                     alt="Avatar">
                            </div>
                            <form action="" method="POST" enctype="multipart/form-data" id="avatarForm">
                                <input type="hidden" name="action" value="upload_avatar">
                                <input type="hidden" name="tab_origin" value="<?= $active_tab ?>">
                                <label class="avatar-edit" title="Alterar foto">
                                    <i class="fas fa-camera"></i>
                                    <input type="file" name="avatar" accept="image/*" onchange="document.getElementById('avatarForm').submit()">
                                </label>
                            </form>
                        </div>
                        
                        <!-- User Info -->
                        <h3 class="user-name"><?= htmlspecialchars($user['nome_completo'] ?: $user['nome_usuario']) ?></h3>
                        <p class="user-handle">@<?= htmlspecialchars($user['nome_usuario']) ?></p>
                        
                        <div class="user-badges">
                            <?php if ($user['email_verificado']): ?>
                                <span class="badge badge-verified">
                                    <i class="fas fa-check-circle"></i>
                                    Verificado
                                </span>
                            <?php endif; ?>
                            <span class="badge badge-member">
                                <i class="fas fa-user"></i>
                                Desde <?= $membro_desde ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Stats -->
                    <div class="profile-stats">
                        <div class="profile-stat">
                            <div class="value"><?= $total_jogos ?></div>
                            <div class="label">Jogos</div>
                        </div>
                        <div class="profile-stat">
                            <div class="value"><?= $total_avaliacoes ?></div>
                            <div class="label">Reviews</div>
                        </div>
                        <div class="profile-stat">
                            <div class="value">0</div>
                            <div class="label">Amigos</div>
                        </div>
                    </div>
                    
                    <!-- Navigation -->
                    <nav class="profile-nav">
                        <button class="profile-nav-item <?= $active_tab == 'pessoal' ? 'active' : '' ?>" 
                                onclick="switchTab('pessoal')">
                            <i class="fas fa-user"></i>
                            Dados Pessoais
                        </button>
                        
                        <button class="profile-nav-item <?= $active_tab == 'seguranca' ? 'active' : '' ?>" 
                                onclick="switchTab('seguranca')">
                            <i class="fas fa-shield-alt"></i>
                            Segurança
                        </button>
                        
                        <button class="profile-nav-item <?= $active_tab == 'preferencias' ? 'active' : '' ?>" 
                                onclick="switchTab('preferencias')">
                            <i class="fas fa-sliders-h"></i>
                            Preferências
                        </button>
                        
                        <div class="profile-nav-divider"></div>
                        
                        <a href="<?= SITE_URL ?>/user/biblioteca.php" class="profile-nav-item">
                            <i class="fas fa-gamepad"></i>
                            Minha Biblioteca
                        </a>
                        
                        <a href="<?= SITE_URL ?>/user/pedidos.php" class="profile-nav-item">
                            <i class="fas fa-shopping-bag"></i>
                            Meus Pedidos
                        </a>
                        
                        <div class="profile-nav-divider"></div>
                        
                        <a href="<?= SITE_URL ?>/auth/logout.php" class="profile-nav-item danger">
                            <i class="fas fa-sign-out-alt"></i>
                            Sair da Conta
                        </a>
                    </nav>
                </div>
            </aside>
            
            <!-- ============================================
                 MAIN CONTENT
                 ============================================ -->
            <main class="profile-content">
                <!-- Tab: Dados Pessoais -->
                <div class="tab-panel <?= $active_tab == 'pessoal' ? 'active' : '' ?>" id="tab-pessoal">
                    <div class="content-card">
                        <div class="content-header">
                            <h2>
                                <i class="fas fa-id-card"></i>
                                Informações Pessoais
                            </h2>
                        </div>
                        
                        <div class="content-body">
                            <form action="" method="POST">
                                <input type="hidden" name="action" value="update_info">
                                <input type="hidden" name="tab_origin" value="pessoal">
                                
                                <div class="form-grid">
                                    <div class="form-group full-width">
                                        <label>Nome Completo</label>
                                        <input type="text" 
                                               name="nome_completo" 
                                               class="form-control" 
                                               value="<?= htmlspecialchars($user['nome_completo'] ?? '') ?>"
                                               placeholder="Seu nome completo">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>CPF</label>
                                        <input type="text" 
                                               name="cpf" 
                                               id="cpfInput"
                                               class="form-control" 
                                               value="<?= htmlspecialchars($user['cpf'] ?? '') ?>"
                                               placeholder="000.000.000-00"
                                               maxlength="14">
                                        <span class="form-hint">Necessário para compras</span>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Data de Nascimento</label>
                                        <input type="date" 
                                               name="data_nascimento" 
                                               class="form-control" 
                                               value="<?= $user['data_nascimento'] ?? '' ?>">
                                    </div>
                                    
                                    <div class="form-group full-width">
                                        <label>Nome de Usuário</label>
                                        <div class="form-row">
                                            <input type="text" 
                                                   class="form-control" 
                                                   value="<?= htmlspecialchars($user['nome_usuario']) ?>"
                                                   disabled>
                                            <span class="input-lock" title="Não pode ser alterado">
                                                <i class="fas fa-lock"></i>
                                            </span>
                                        </div>
                                        <span class="form-hint">O nome de usuário não pode ser alterado</span>
                                    </div>
                                    
                                    <div class="form-group full-width">
                                        <label>Sobre Mim</label>
                                        <textarea name="bio" 
                                                  class="form-control" 
                                                  placeholder="Conte um pouco sobre você..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                                        <span class="form-hint">Visível no seu perfil público</span>
                                    </div>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i>
                                        Salvar Alterações
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Tab: Segurança -->
                <div class="tab-panel <?= $active_tab == 'seguranca' ? 'active' : '' ?>" id="tab-seguranca">
                    <div class="content-card">
                        <div class="content-header">
                            <h2>
                                <i class="fas fa-shield-alt"></i>
                                Segurança da Conta
                            </h2>
                        </div>
                        
                        <div class="content-body">
                            <div class="security-card">
                                <h4><i class="fas fa-envelope"></i> Email da Conta</h4>
                                <p>
                                    <strong><?= htmlspecialchars($user['email']) ?></strong>
                                    <?php if ($user['email_verificado']): ?>
                                        <span style="color: var(--success); margin-left: 8px;">
                                            <i class="fas fa-check-circle"></i> Verificado
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--warning); margin-left: 8px;">
                                            <i class="fas fa-exclamation-circle"></i> Pendente
                                        </span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            
                            <form action="" method="POST">
                                <input type="hidden" name="action" value="update_password">
                                <input type="hidden" name="tab_origin" value="seguranca">
                                
                                <h4 style="margin-bottom: 20px; font-size: 16px;">
                                    <i class="fas fa-key" style="color: var(--accent); margin-right: 8px;"></i>
                                    Alterar Senha
                                </h4>
                                
                                <div class="form-grid">
                                    <div class="form-group full-width">
                                        <label>Senha Atual</label>
                                        <input type="password" 
                                               name="senha_atual" 
                                               class="form-control" 
                                               required
                                               placeholder="Digite sua senha atual">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Nova Senha</label>
                                        <input type="password" 
                                               name="senha_nova" 
                                               class="form-control" 
                                               required
                                               minlength="6"
                                               placeholder="Mínimo 6 caracteres">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Confirmar Nova Senha</label>
                                        <input type="password" 
                                               name="senha_confirma" 
                                               class="form-control" 
                                               required
                                               placeholder="Repita a nova senha">
                                    </div>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-lock"></i>
                                        Atualizar Senha
                                    </button>
                                </div>
                            </form>
                            
                            <div class="security-card danger-zone">
                                <h4><i class="fas fa-exclamation-triangle"></i> Zona de Perigo</h4>
                                <p>
                                    Deseja excluir sua conta? Esta ação é irreversível e todos os seus dados serão perdidos.
                                </p>
                                <button class="btn btn-outline" style="margin-top: 12px; color: var(--danger); border-color: var(--danger);">
                                    <i class="fas fa-trash"></i>
                                    Excluir Conta
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tab: Preferências -->
                <div class="tab-panel <?= $active_tab == 'preferencias' ? 'active' : '' ?>" id="tab-preferencias">
                    <div class="content-card">
                        <div class="content-header">
                            <h2>
                                <i class="fas fa-sliders-h"></i>
                                Preferências
                            </h2>
                        </div>
                        
                        <div class="content-body">
                            <div class="preference-item">
                                <div class="preference-info">
                                    <h4>Tema da Interface</h4>
                                    <p>Escolha entre modo claro ou escuro</p>
                                </div>
                                <div class="theme-options">
                                    <button class="theme-btn" onclick="setTheme('light')">
                                        <i class="fas fa-sun"></i>
                                        Claro
                                    </button>
                                    <button class="theme-btn active" onclick="setTheme('dark')">
                                        <i class="fas fa-moon"></i>
                                        Escuro
                                    </button>
                                </div>
                            </div>
                            
                            <div class="preference-item">
                                <div class="preference-info">
                                    <h4>Notificações por Email</h4>
                                    <p>Receba novidades, promoções e atualizações</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" checked>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            
                            <div class="preference-item">
                                <div class="preference-info">
                                    <h4>Perfil Público</h4>
                                    <p>Permitir que outros vejam sua biblioteca</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox">
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            
                            <div class="preference-item">
                                <div class="preference-info">
                                    <h4>Exibir Conquistas</h4>
                                    <p>Mostrar suas conquistas no perfil público</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" checked>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</div>

<script>
// Tab Switching
function switchTab(tabName) {
    // Update URL without reload
    const url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    history.pushState({}, '', url);
    
    // Update nav items
    document.querySelectorAll('.profile-nav-item').forEach(item => {
        item.classList.remove('active');
    });
    event.currentTarget.classList.add('active');
    
    // Update panels
    document.querySelectorAll('.tab-panel').forEach(panel => {
        panel.classList.remove('active');
    });
    document.getElementById('tab-' + tabName).classList.add('active');
}

// CPF Mask
const cpfInput = document.getElementById('cpfInput');
if (cpfInput) {
    cpfInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 11) value = value.slice(0, 11);
        
        if (value.length > 9) {
            value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
        } else if (value.length > 6) {
            value = value.replace(/(\d{3})(\d{3})(\d+)/, "$1.$2.$3");
        } else if (value.length > 3) {
            value = value.replace(/(\d{3})(\d+)/, "$1.$2");
        }
        
        e.target.value = value;
    });
}

// Theme Toggle
function setTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);
    
    // Update button states
    document.querySelectorAll('.theme-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.currentTarget.classList.add('active');
}

// Preview avatar before upload
document.querySelector('input[name="avatar"]')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('avatarPreview').src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
});

// Auto-hide alerts
document.querySelectorAll('.alert').forEach(alert => {
    setTimeout(() => {
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-10px)';
        setTimeout(() => alert.remove(), 300);
    }, 5000);
});
</script>

<?php require_once '../includes/footer.php'; ?>