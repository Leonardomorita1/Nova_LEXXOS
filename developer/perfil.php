<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireLogin();

$database = new Database();
$pdo = $database->getConnection();
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM desenvolvedor WHERE usuario_id = ?");
$stmt->execute([$user_id]);
$dev = $stmt->fetch();

if (!$dev || $dev['status'] != 'ativo') {
    header('Location: ' . SITE_URL . '/developer/dashboard.php');
    exit;
}

$success = '';
$error = '';
$section = $_GET['section'] ?? 'info';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    
    try {
        if ($action == 'info_basica') {
            $stmt = $pdo->prepare("UPDATE desenvolvedor SET nome_estudio = ?, descricao_curta = ?, descricao = ? WHERE id = ?");
            $stmt->execute([trim($_POST['nome_estudio']), trim($_POST['descricao_curta']), trim($_POST['descricao']), $dev['id']]);
            $success = 'Informações atualizadas!';
            
        } elseif ($action == 'imagens') {
            $upload_dir = '../uploads/desenvolvedores';
            $upload_url = '/uploads/desenvolvedores';
            
            // Criar diretório se não existir
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
                chmod($upload_dir, 0777);
            }
            
            // Upload de Logo
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] == UPLOAD_ERR_OK) {
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                $file = $_FILES['logo'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                if (!in_array($ext, $allowed)) {
                    throw new Exception('Formato inválido para logo. Use JPG, PNG ou WEBP');
                }
                
                if ($file['size'] > 5242880) {
                    throw new Exception('Logo muito grande (máx 5MB)');
                }
                
                $logo_name = $dev['slug'] . '-logo-' . time() . '.' . $ext;
                $logo_path = $upload_dir . '/' . $logo_name;
                
                if (move_uploaded_file($file['tmp_name'], $logo_path)) {
                    // Remove logo antigo
                    if ($dev['logo_url']) {
                        $old_file = $base_path . $dev['logo_url'];
                        if (file_exists($old_file) && is_file($old_file)) {
                            @unlink($old_file);
                        }
                    }
                    
                    $stmt = $pdo->prepare("UPDATE desenvolvedor SET logo_url = ? WHERE id = ?");
                    $stmt->execute([$upload_url . '/' . $logo_name, $dev['id']]);
                    $success = 'Logo atualizado com sucesso!';
                } else {
                    throw new Exception('Erro ao salvar logo');
                }
            }
            
            // Upload de Banner
            if (isset($_FILES['banner']) && $_FILES['banner']['error'] == UPLOAD_ERR_OK) {
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                $file = $_FILES['banner'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                if (!in_array($ext, $allowed)) {
                    throw new Exception('Formato inválido para banner. Use JPG, PNG ou WEBP');
                }
                
                if ($file['size'] > 10485760) {
                    throw new Exception('Banner muito grande (máx 10MB)');
                }
                
                $banner_name = $dev['slug'] . '-banner-' . time() . '.' . $ext;
                $banner_path = $upload_dir . '/' . $banner_name;
                
                if (move_uploaded_file($file['tmp_name'], $banner_path)) {
                    // Remove banner antigo
                    if ($dev['banner_url']) {
                        $old_file = $base_path . $dev['banner_url'];
                        if (file_exists($old_file) && is_file($old_file)) {
                            @unlink($old_file);
                        }
                    }
                    
                    $stmt = $pdo->prepare("UPDATE desenvolvedor SET banner_url = ? WHERE id = ?");
                    $stmt->execute([$upload_url . '/' . $banner_name, $dev['id']]);
                    
                    if (!$success) {
                        $success = 'Banner atualizado com sucesso!';
                    } else {
                        $success = 'Imagens atualizadas com sucesso!';
                    }
                } else {
                    throw new Exception('Erro ao salvar banner');
                }
            }
            
            if (!$success) {
                $error = 'Nenhuma imagem foi selecionada';
            }
            
        } elseif ($action == 'redes_sociais') {
            $stmt = $pdo->prepare("UPDATE desenvolvedor SET website=?, twitter=?, instagram=?, discord=?, youtube=? WHERE id=?");
            $stmt->execute([
                trim($_POST['website']), 
                trim($_POST['twitter']), 
                trim($_POST['instagram']), 
                trim($_POST['discord']), 
                trim($_POST['youtube']), 
                $dev['id']
            ]);
            $success = 'Redes sociais atualizadas!';
            
        } elseif ($action == 'financeiro') {
            $stmt = $pdo->prepare("UPDATE desenvolvedor SET tipo_pessoa=?, cpf_cnpj=?, chave_pix=?, banco_codigo=?, banco_agencia=?, banco_conta=? WHERE id=?");
            $stmt->execute([
                $_POST['tipo_pessoa'], 
                trim($_POST['cpf_cnpj']), 
                trim($_POST['chave_pix']), 
                trim($_POST['banco_codigo']), 
                trim($_POST['banco_agencia']), 
                trim($_POST['banco_conta']), 
                $dev['id']
            ]);
            $success = 'Dados financeiros atualizados!';
        }
        
        // Refresh dos dados
        $stmt = $pdo->prepare("SELECT * FROM desenvolvedor WHERE id = ?");
        $stmt->execute([$dev['id']]);
        $dev = $stmt->fetch();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$page_title = 'Editar Perfil - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<style>
.profile-container { display: grid; grid-template-columns: 260px 1fr; gap: 30px; }
.settings-nav { background: var(--bg-secondary); border-radius: 12px; overflow: hidden; border: 1px solid var(--border); position: sticky; top: 100px; }
.settings-link { display: flex; align-items: center; gap: 15px; padding: 18px 25px; color: var(--text-secondary); transition: 0.3s; border-left: 3px solid transparent; text-decoration: none; font-weight: 500; }
.settings-link:hover { background: var(--bg-primary); color: var(--text-primary); }
.settings-link.active { background: linear-gradient(90deg, rgba(76, 139, 245, 0.1) 0%, transparent 100%); color: var(--accent); border-left-color: var(--accent); }
.settings-link i { width: 20px; text-align: center; }
.settings-card { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; padding: 35px; }
.settings-header { margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid var(--border); }
.settings-header h2 { font-size: 24px; margin: 0; }
.form-group { margin-bottom: 25px; }
.form-label { display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-secondary); font-size: 14px; }
.form-control { background: var(--bg-primary); border: 1px solid var(--border); color: var(--text-primary); padding: 12px 15px; border-radius: 8px; width: 100%; transition: 0.3s; }
.form-control:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(76, 139, 245, 0.15); outline: none; }
.img-upload-box { position: relative; border-radius: 12px; overflow: hidden; cursor: pointer; border: 2px dashed var(--border); transition: 0.3s; background: var(--bg-primary); }
.img-upload-box:hover { border-color: var(--accent); }
.img-upload-box img { width: 100%; height: 100%; object-fit: cover; display: block; }
.img-overlay { position: absolute; inset: 0; background: rgba(0,0,0,0.6); display: flex; flex-direction: column; align-items: center; justify-content: center; opacity: 0; transition: 0.3s; color: white; }
.img-upload-box:hover .img-overlay { opacity: 1; }
.banner-box { height: 200px; }
.logo-box { width: 150px; height: 150px; border-radius: 50%; margin: 0 auto; }
.img-placeholder { height:100%; display:flex; align-items:center; justify-content:center; color:var(--text-secondary); flex-direction: column; gap: 10px; }
@media (max-width: 992px) { 
    .profile-container { grid-template-columns: 1fr; } 
    .settings-nav { position: static; display: flex; overflow-x: auto; } 
    .settings-link { border-left: none; border-bottom: 2px solid transparent; white-space: nowrap; } 
    .settings-link.active { border-bottom-color: var(--accent); background: none; } 
}
</style>

<div class="container" style="padding: 40px 0;">
    <div class="dev-layout">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <div class="dev-content">
            <?php if ($success): ?>
            <div style="background: rgba(46, 204, 113, 0.15); color: #2ecc71; padding: 15px 20px; border-radius: 8px; margin-bottom: 25px; border: 1px solid #2ecc71;">
                <i class="fas fa-check-circle"></i> <?= $success ?>
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div style="background: rgba(231, 76, 60, 0.15); color: #e74c3c; padding: 15px 20px; border-radius: 8px; margin-bottom: 25px; border: 1px solid #e74c3c;">
                <i class="fas fa-exclamation-circle"></i> <?= $error ?>
            </div>
            <?php endif; ?>

            <div class="profile-container">
                <div class="settings-nav">
                    <a href="?section=info" class="settings-link <?= $section == 'info' ? 'active' : '' ?>">
                        <i class="fas fa-user-circle"></i> Informações
                    </a>
                    <a href="?section=imagens" class="settings-link <?= $section == 'imagens' ? 'active' : '' ?>">
                        <i class="fas fa-image"></i> Imagens
                    </a>
                    <a href="?section=redes" class="settings-link <?= $section == 'redes' ? 'active' : '' ?>">
                        <i class="fas fa-share-alt"></i> Redes Sociais
                    </a>
                    <a href="?section=financeiro" class="settings-link <?= $section == 'financeiro' ? 'active' : '' ?>">
                        <i class="fas fa-wallet"></i> Financeiro
                    </a>
                </div>

                <div class="settings-card">
                    <?php if ($section == 'info'): ?>
                        <div class="settings-header">
                            <h2>Informações Básicas</h2>
                            <p style="color: var(--text-secondary); font-size: 14px;">Dados públicos do seu estúdio</p>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="action" value="info_basica">
                            <div class="form-group">
                                <label class="form-label">Nome do Estúdio</label>
                                <input type="text" name="nome_estudio" class="form-control" required value="<?= sanitize($dev['nome_estudio']) ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Descrição Curta</label>
                                <input type="text" name="descricao_curta" class="form-control" maxlength="300" value="<?= sanitize($dev['descricao_curta']) ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Sobre o Estúdio</label>
                                <textarea name="descricao" class="form-control" rows="6"><?= sanitize($dev['descricao']) ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Salvar Alterações</button>
                        </form>

                    <?php elseif ($section == 'imagens'): ?>
                        <div class="settings-header">
                            <h2>Identidade Visual</h2>
                            <p style="color: var(--text-secondary); font-size: 14px;">Formatos aceitos: JPG, PNG, WEBP</p>
                        </div>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="imagens">
                            
                            <div class="form-group" style="text-align: center;">
                                <label class="form-label">Logo (500x500px, máx 5MB)</label>
                                <div class="img-upload-box logo-box" onclick="document.getElementById('logoInput').click()">
                                    <?php if ($dev['logo_url']): ?>
                                        <img src="<?= SITE_URL . $dev['logo_url'] ?>?v=<?= time() ?>" id="logoPreview" alt="Logo">
                                    <?php else: ?>
                                        <div class="img-placeholder" id="logoPlaceholder">
                                            <i class="fas fa-upload fa-2x"></i>
                                            <span>Clique para enviar</span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="img-overlay"><i class="fas fa-camera fa-2x"></i><br><small>Alterar Logo</small></div>
                                </div>
                                <input type="file" name="logo" id="logoInput" hidden accept="image/jpeg,image/jpg,image/png,image/webp" onchange="previewImage(this, 'logoPreview', 'logoPlaceholder')">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Banner (1920x400px, máx 10MB)</label>
                                <div class="img-upload-box banner-box" onclick="document.getElementById('bannerInput').click()">
                                    <?php if ($dev['banner_url']): ?>
                                        <img src="<?= SITE_URL . $dev['banner_url'] ?>?v=<?= time() ?>" id="bannerPreview" alt="Banner">
                                    <?php else: ?>
                                        <div class="img-placeholder" id="bannerPlaceholder">
                                            <i class="fas fa-image fa-3x"></i>
                                            <span>Clique para enviar</span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="img-overlay"><i class="fas fa-camera fa-2x"></i><br><small>Alterar Banner</small></div>
                                </div>
                                <input type="file" name="banner" id="bannerInput" hidden accept="image/jpeg,image/jpg,image/png,image/webp" onchange="previewImage(this, 'bannerPreview', 'bannerPlaceholder')">
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-cloud-upload-alt"></i> Atualizar Imagens</button>
                        </form>

                    <?php elseif ($section == 'redes'): ?>
                        <div class="settings-header"><h2>Redes Sociais</h2></div>
                        <form method="POST">
                            <input type="hidden" name="action" value="redes_sociais">
                            <div class="form-group"><label class="form-label"><i class="fas fa-globe"></i> Website</label><input type="url" name="website" class="form-control" placeholder="https://seusite.com" value="<?= sanitize($dev['website']) ?>"></div>
                            <div class="form-group"><label class="form-label"><i class="fab fa-twitter"></i> Twitter/X</label><input type="url" name="twitter" class="form-control" placeholder="https://twitter.com/seu_usuario" value="<?= sanitize($dev['twitter']) ?>"></div>
                            <div class="form-group"><label class="form-label"><i class="fab fa-instagram"></i> Instagram</label><input type="url" name="instagram" class="form-control" placeholder="https://instagram.com/seu_usuario" value="<?= sanitize($dev['instagram']) ?>"></div>
                            <div class="form-group"><label class="form-label"><i class="fab fa-discord"></i> Discord</label><input type="url" name="discord" class="form-control" placeholder="https://discord.gg/seu_servidor" value="<?= sanitize($dev['discord']) ?>"></div>
                            <div class="form-group"><label class="form-label"><i class="fab fa-youtube"></i> YouTube</label><input type="url" name="youtube" class="form-control" placeholder="https://youtube.com/@seu_canal" value="<?= sanitize($dev['youtube']) ?>"></div>
                            <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Salvar Alterações</button>
                        </form>

                    <?php elseif ($section == 'financeiro'): ?>
                        <div class="settings-header">
                            <h2>Dados Financeiros</h2>
                            <p style="color: var(--text-secondary); font-size: 14px;">Informações para recebimento de pagamentos</p>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="action" value="financeiro">
                            <div class="form-group">
                                <label class="form-label">Tipo de Pessoa</label>
                                <select name="tipo_pessoa" class="form-control">
                                    <option value="fisica" <?= $dev['tipo_pessoa']=='fisica'?'selected':'' ?>>Pessoa Física (CPF)</option>
                                    <option value="juridica" <?= $dev['tipo_pessoa']=='juridica'?'selected':'' ?>>Pessoa Jurídica (CNPJ)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">CPF/CNPJ</label>
                                <input type="text" name="cpf_cnpj" class="form-control" placeholder="000.000.000-00" value="<?= sanitize($dev['cpf_cnpj']) ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Chave PIX</label>
                                <input type="text" name="chave_pix" class="form-control" placeholder="email@exemplo.com ou telefone" value="<?= sanitize($dev['chave_pix']) ?>">
                            </div>
                            <hr style="border-color: var(--border); margin: 30px 0;">
                            <h3 style="font-size: 18px; margin-bottom: 20px;">Dados Bancários (Opcional)</h3>
                            <div style="display:grid; grid-template-columns: 120px 120px 1fr; gap:15px;">
                                <div class="form-group">
                                    <label class="form-label">Código Banco</label>
                                    <input type="text" name="banco_codigo" class="form-control" placeholder="001" value="<?= sanitize($dev['banco_codigo']) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Agência</label>
                                    <input type="text" name="banco_agencia" class="form-control" placeholder="0001" value="<?= sanitize($dev['banco_agencia']) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Conta</label>
                                    <input type="text" name="banco_conta" class="form-control" placeholder="00000-0" value="<?= sanitize($dev['banco_conta']) ?>">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-lock"></i> Salvar com Segurança</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function previewImage(input, imgId, placeholderId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            let img = document.getElementById(imgId);
            const placeholder = document.getElementById(placeholderId);
            
            if (!img) {
                img = document.createElement('img');
                img.id = imgId;
                if (placeholder) {
                    placeholder.parentElement.replaceChild(img, placeholder);
                } else {
                    input.parentElement.insertBefore(img, input.parentElement.firstChild);
                }
            }
            img.src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>