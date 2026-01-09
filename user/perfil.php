<?php
// user/perfil.php
require_once '../config/config.php';
require_once '../config/database.php';
requireLogin(); // Garante que tá logado

$database = new Database();
$pdo = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Inicializa variáveis de feedback
$success = '';
$error = '';
$active_tab = 'pessoal'; // Aba padrão

// ==========================================================
// PROCESSAMENTO DO FORMULÁRIO (BACKEND)
// ==========================================================

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $active_tab = $_POST['tab_origin'] ?? 'pessoal'; // Mantém a aba aberta após salvar

    try {
        // --- 1. ATUALIZAR DADOS PESSOAIS ---
        if ($action == 'update_info') {
            $nome_completo = trim($_POST['nome_completo']);
            $cpf = trim($_POST['cpf']); // Ideal: Adicionar validação de CPF real aqui
            $data_nascimento = $_POST['data_nascimento'];
            $bio = trim($_POST['bio']);

            // Verifica duplicidade de CPF (se preenchido)
            if (!empty($cpf)) {
                $stmt = $pdo->prepare("SELECT id FROM usuario WHERE cpf = ? AND id != ?");
                $stmt->execute([$cpf, $user_id]);
                if ($stmt->fetch()) throw new Exception("Este CPF já está em uso por outro usuário.");
            }

            $stmt = $pdo->prepare("UPDATE usuario SET nome_completo = ?, cpf = ?, data_nascimento = ?, bio = ? WHERE id = ?");
            if ($stmt->execute([$nome_completo, $cpf, $data_nascimento, $bio, $user_id])) {
                $success = "Dados atualizados com sucesso!";
            }
        }

        // --- 2. ALTERAR SENHA ---
        elseif ($action == 'update_password') {
            $senha_atual = $_POST['senha_atual'];
            $senha_nova = $_POST['senha_nova'];
            $senha_confirma = $_POST['senha_confirma'];

            // Busca hash atual
            $stmt = $pdo->prepare("SELECT senha_hash FROM usuario WHERE id = ?");
            $stmt->execute([$user_id]);
            $current = $stmt->fetch();

            if (!password_verify($senha_atual, $current['senha_hash'])) {
                throw new Exception("Sua senha atual está incorreta.");
            }
            if ($senha_nova !== $senha_confirma) {
                throw new Exception("As novas senhas não coincidem.");
            }
            if (strlen($senha_nova) < 6) {
                throw new Exception("A nova senha deve ter no mínimo 6 caracteres.");
            }

            $novo_hash = password_hash($senha_nova, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE usuario SET senha_hash = ? WHERE id = ?");
            $stmt->execute([$novo_hash, $user_id]);
            $success = "Senha alterada com segurança!";
        }

        // --- 3. UPLOAD DE AVATAR ---
        elseif ($action == 'upload_avatar') {
            if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] != 0) {
                throw new Exception("Selecione uma imagem válida.");
            }

            $file = $_FILES['avatar'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($ext, $allowed)) throw new Exception("Formato de imagem não permitido.");
            if ($file['size'] > 5 * 1024 * 1024) throw new Exception("A imagem deve ter no máximo 5MB.");

            // Cria diretório se não existir
            $upload_dir = '../uploads/avatars/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            // Nome único para evitar cache
            $new_name = 'user_' . $user_id . '_' . time() . '.' . $ext;
            
            if (move_uploaded_file($file['tmp_name'], $upload_dir . $new_name)) {
                // Remove avatar antigo se não for o padrão
                $stmt = $pdo->prepare("SELECT avatar_url FROM usuario WHERE id = ?");
                $stmt->execute([$user_id]);
                $old = $stmt->fetchColumn();
                if ($old && file_exists(__DIR__ . '/../' . $old) && strpos($old, 'default') === false) {
                    unlink(__DIR__ . '/../' . $old);
                }

                // Salva no banco (caminho relativo)
                $db_path = '/uploads/avatars/' . $new_name;
                $stmt = $pdo->prepare("UPDATE usuario SET avatar_url = ? WHERE id = ?");
                $stmt->execute([$db_path, $user_id]);
                $success = "Foto de perfil atualizada!";
            } else {
                throw new Exception("Erro ao salvar o arquivo.");
            }
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Buscar dados frescos do usuário
$stmt = $pdo->prepare("SELECT * FROM usuario WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$page_title = "Meu Perfil - Lexxos";
require_once '../includes/header.php';
?>

<style>
/* CSS Específico do Perfil (Injetado aqui ou mova para style.css) */
.profile-container {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 30px;
    margin: 40px auto;
    max-width: 1100px;
}

/* Card Sidebar */
.profile-sidebar {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 30px 20px;
    text-align: center;
    height: fit-content;
    position: sticky;
    top: 100px;
}

.avatar-wrapper {
    position: relative;
    width: 120px;
    height: 120px;
    margin: 0 auto 15px;
}

.avatar-img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--accent);
    padding: 3px;
    background: var(--bg-primary);
}

.avatar-edit-btn {
    position: absolute;
    bottom: 5px;
    right: 5px;
    background: var(--accent);
    color: #000;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: transform 0.2s;
    border: 2px solid var(--bg-secondary);
}

.avatar-edit-btn:hover { transform: scale(1.1); }

.user-identity h3 { font-size: 1.2rem; margin-bottom: 5px; color: #fff; }
.user-identity p { font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 20px; }

/* Menu Lateral */
.profile-menu {
    list-style: none;
    text-align: left;
    margin-top: 20px;
}

.profile-menu button {
    width: 100%;
    background: none;
    border: none;
    padding: 12px 15px;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 0.95rem;
    cursor: pointer;
    border-radius: 8px;
    transition: all 0.3s;
}

.profile-menu button:hover { background: rgba(255,255,255,0.05); color: #fff; }
.profile-menu button.active { background: var(--accent); color: #000; font-weight: 600; }

/* Área de Conteúdo */
.profile-content {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 30px;
    min-height: 500px;
}

.tab-pane { display: none; animation: fadeIn 0.4s ease; }
.tab-pane.active { display: block; }

@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

.section-header {
    border-bottom: 1px solid var(--border);
    padding-bottom: 20px;
    margin-bottom: 25px;
}

.section-header h2 { font-size: 1.5rem; color: #fff; display: flex; align-items: center; gap: 10px; }

/* Formulários */
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
.full-width { grid-column: 1 / -1; }

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: var(--text-secondary);
    font-size: 0.9rem;
    font-weight: 500;
}

.form-control {
    width: 100%;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    padding: 12px;
    border-radius: 8px;
    color: #fff;
    font-size: 1rem;
    transition: border-color 0.3s;
}

.form-control:focus {
    border-color: var(--accent);
    outline: none;
    box-shadow: 0 0 0 3px rgba(var(--accent-rgb), 0.1);
}

.btn-save {
    background: var(--accent);
    color: #000;
    border: none;
    padding: 12px 30px;
    border-radius: 8px;
    font-weight: 700;
    cursor: pointer;
    margin-top: 20px;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    transition: transform 0.2s;
}

.btn-save:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(var(--accent-rgb), 0.3); }

/* Responsivo */
@media (max-width: 900px) {
    .profile-container { grid-template-columns: 1fr; }
    .profile-sidebar { position: static; }
    .form-grid { grid-template-columns: 1fr; }
}
</style>

<div class="container">
    <?php if ($success): ?>
        <div class="alert alert-success mt-4"><i class="fas fa-check-circle"></i> <?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger mt-4"><i class="fas fa-exclamation-triangle"></i> <?= $error ?></div>
    <?php endif; ?>

    <div class="profile-container">
        <aside class="profile-sidebar">
            <div class="avatar-wrapper">
                <img src="<?= SITE_URL ?><?= $user['avatar_url'] ?? 'assets/images/default-avatar.png' ?>" 
                     class="avatar-img" id="avatarPreview">
                
                <form action="" method="POST" enctype="multipart/form-data" id="avatarForm">
                    <input type="hidden" name="action" value="upload_avatar">
                    <input type="hidden" name="tab_origin" id="avatarTabOrigin">
                    <label for="avatarInput" class="avatar-edit-btn" title="Alterar Foto">
                        <i class="fas fa-camera"></i>
                    </label>
                    <input type="file" id="avatarInput" name="avatar" hidden accept="image/*" onchange="submitAvatar()">
                </form>
            </div>

            <div class="user-identity">
                <h3><?= sanitize($user['nome_usuario']) ?></h3>
                <p><?= sanitize($user['email']) ?></p>
                <?php if ($user['email_verificado']): ?>
                    <span class="badge bg-success"><i class="fas fa-check"></i> Verificado</span>
                <?php else: ?>
                    <span class="badge bg-warning text-dark"><i class="fas fa-envelope"></i> Pendente</span>
                <?php endif; ?>
            </div>

            <ul class="profile-menu">
                <li><button class="tab-btn <?= $active_tab == 'pessoal' ? 'active' : '' ?>" onclick="openTab('pessoal')"><i class="fas fa-user"></i> Dados Pessoais</button></li>
                <li><button class="tab-btn <?= $active_tab == 'seguranca' ? 'active' : '' ?>" onclick="openTab('seguranca')"><i class="fas fa-lock"></i> Segurança</button></li>
                <li><button class="tab-btn <?= $active_tab == 'preferencias' ? 'active' : '' ?>" onclick="openTab('preferencias')"><i class="fas fa-palette"></i> Preferências</button></li>
                <li style="margin-top: 15px; border-top: 1px solid var(--border); padding-top: 15px;">
                    <a href="<?= SITE_URL ?>/auth/logout.php" style="color: #ff5555; text-decoration: none; padding-left: 15px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-sign-out-alt"></i> Sair da Conta
                    </a>
                </li>
            </ul>
        </aside>

        <main class="profile-content">
            
            <div id="pessoal" class="tab-pane <?= $active_tab == 'pessoal' ? 'active' : '' ?>">
                <div class="section-header">
                    <h2><i class="fas fa-id-card"></i> Informações Pessoais</h2>
                </div>
                
                <form action="" method="POST">
                    <input type="hidden" name="action" value="update_info">
                    <input type="hidden" name="tab_origin" value="pessoal">

                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label>Nome Completo</label>
                            <input type="text" name="nome_completo" class="form-control" value="<?= sanitize($user['nome_completo']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>CPF</label>
                            <input type="text" name="cpf" id="cpfInput" class="form-control" value="<?= sanitize($user['cpf']) ?>" placeholder="000.000.000-00" maxlength="14">
                        </div>

                        <div class="form-group">
                            <label>Data de Nascimento</label>
                            <input type="date" name="data_nascimento" class="form-control" value="<?= $user['data_nascimento'] ?>">
                        </div>

                        <div class="form-group full-width">
                            <label>Nome de Usuário (Público)</label>
                            <div style="display: flex; gap: 10px;">
                                <input type="text" class="form-control" value="<?= sanitize($user['nome_usuario']) ?>" disabled style="opacity: 0.7; cursor: not-allowed;">
                                <span title="O nome de usuário não pode ser alterado" style="padding: 10px; color: var(--text-secondary);"><i class="fas fa-info-circle"></i></span>
                            </div>
                        </div>

                        <div class="form-group full-width">
                            <label>Sobre Mim (Bio)</label>
                            <textarea name="bio" class="form-control" rows="4" placeholder="Conte um pouco sobre você..."><?= sanitize($user['bio']) ?></textarea>
                        </div>
                    </div>

                    <button type="submit" class="btn-save"><i class="fas fa-save"></i> Salvar Alterações</button>
                </form>
            </div>

            <div id="seguranca" class="tab-pane <?= $active_tab == 'seguranca' ? 'active' : '' ?>">
                <div class="section-header">
                    <h2><i class="fas fa-shield-alt"></i> Senha e Segurança</h2>
                </div>

                <form action="" method="POST">
                    <input type="hidden" name="action" value="update_password">
                    <input type="hidden" name="tab_origin" value="seguranca">

                    <div class="form-group">
                        <label>Senha Atual</label>
                        <input type="password" name="senha_atual" class="form-control" required placeholder="Digite sua senha atual">
                    </div>

                    <div class="form-grid" style="margin-top: 20px;">
                        <div class="form-group">
                            <label>Nova Senha</label>
                            <input type="password" name="senha_nova" class="form-control" required minlength="6" placeholder="Mínimo 6 caracteres">
                        </div>
                        <div class="form-group">
                            <label>Confirmar Nova Senha</label>
                            <input type="password" name="senha_confirma" class="form-control" required placeholder="Repita a nova senha">
                        </div>
                    </div>

                    <div class="mt-4 p-3" style="background: rgba(255, 68, 68, 0.1); border-radius: 8px; border: 1px solid rgba(255,68,68,0.3);">
                        <h4 style="color: #ff6666; font-size: 1rem; margin-bottom: 5px;"><i class="fas fa-exclamation-triangle"></i> Zona de Perigo</h4>
                        <p style="font-size: 0.9rem; color: var(--text-secondary);">Ao alterar sua senha, você poderá ser desconectado de outros dispositivos.</p>
                    </div>

                    <button type="submit" class="btn-save"><i class="fas fa-key"></i> Atualizar Senha</button>
                </form>
            </div>

            <div id="preferencias" class="tab-pane <?= $active_tab == 'preferencias' ? 'active' : '' ?>">
                <div class="section-header">
                    <h2><i class="fas fa-sliders-h"></i> Preferências da Conta</h2>
                </div>
                
                <div class="preference-item" style="display: flex; justify-content: space-between; align-items: center; padding: 20px 0; border-bottom: 1px solid var(--border);">
                    <div>
                        <strong style="display: block; color: #fff; margin-bottom: 5px;">Tema do Sistema</strong>
                        <p style="font-size: 0.9rem; color: var(--text-secondary);">Alterne entre modo claro e escuro.</p>
                    </div>
                    <div class="btn-group">
                        <button onclick="changeTheme('light')" class="btn btn-sm btn-outline-light"><i class="fas fa-sun"></i> Claro</button>
                        <button onclick="changeTheme('dark')" class="btn btn-sm btn-outline-dark"><i class="fas fa-moon"></i> Escuro</button>
                    </div>
                </div>

                <div class="preference-item" style="margin-top: 20px;">
                    <strong style="display: block; color: #fff; margin-bottom: 15px;">Privacidade</strong>
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; color: var(--text-secondary);">
                        <input type="checkbox" disabled checked> Exibir meus jogos na biblioteca (Em breve)
                    </label>
                </div>
            </div>

        </main>
    </div>
</div>

<script>
// 1. Gerenciamento de Abas
function openTab(tabName) {
    // Esconde todos os conteúdos
    document.querySelectorAll('.tab-pane').forEach(el => el.classList.remove('active'));
    // Remove classe active dos botões
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    
    // Mostra o selecionado
    document.getElementById(tabName).classList.add('active');
    // Marca botão como ativo (procura o botão que chamou a função ou faz via seletor)
    event.currentTarget.classList.add('active');
}

// 2. Upload Automático de Avatar
function submitAvatar() {
    // Pega a aba ativa atual para reabrir ela depois do refresh (embora avatar seja na sidebar)
    const activeTab = document.querySelector('.tab-pane.active').id;
    document.getElementById('avatarTabOrigin').value = activeTab;
    document.getElementById('avatarForm').submit();
}

// 3. Máscara de CPF (Vanilla JS simples)
const cpfInput = document.getElementById('cpfInput');
if(cpfInput) {
    cpfInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, ''); // Remove tudo que não é número
        if (value.length > 11) value = value.slice(0, 11); // Limita a 11 números
        
        // Aplica a máscara 000.000.000-00
        if (value.length > 9) {
            value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
        } else if (value.length > 6) {
            value = value.replace(/(\d{3})(\d{3})(\d{3})/, "$1.$2.$3");
        } else if (value.length > 3) {
            value = value.replace(/(\d{3})(\d{3})/, "$1.$2");
        }
        
        e.target.value = value;
    });
}

// 4. Troca de Tema (Simulação visual + AJAX se tiver backend pronto)
function changeTheme(theme) {
    // Aqui você pode fazer um fetch para salvar no banco
    document.documentElement.setAttribute('data-theme', theme);
    // Exemplo de fetch (precisa criar endpoint update_theme.php se quiser persistir)
    /*
    fetch('api/update_theme.php', {
        method: 'POST',
        body: JSON.stringify({theme: theme})
    });
    */
    alert('Tema alterado para ' + theme + '. (Implementar persistência no DB)');
}
</script>

<?php require_once '../includes/footer.php'; ?>