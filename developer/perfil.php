<?php
// developer/perfil.php
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
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/desenvolvedores';
            $upload_url = '/uploads/desenvolvedores';
            
            // Criar diretório se não existir
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Logo
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                
                if (!in_array($ext, $allowed)) {
                    throw new Exception('Tipo de arquivo não permitido para logo');
                }
                
                if ($_FILES['logo']['size'] > 5242880) {
                    throw new Exception('Logo muito grande (máx 5MB)');
                }
                
                $logo_name = $dev['slug'] . '-logo-' . time() . '.' . $ext;
                $logo_path = $upload_dir . '/' . $logo_name;
                
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $logo_path)) {
                    if ($dev['logo_url']) {
                        $old_file = $_SERVER['DOCUMENT_ROOT'] . $dev['logo_url'];
                        if (file_exists($old_file)) @unlink($old_file);
                    }
                    
                    $stmt = $pdo->prepare("UPDATE desenvolvedor SET logo_url = ? WHERE id = ?");
                    $stmt->execute([$upload_url . '/' . $logo_name, $dev['id']]);
                    $success = 'Logo atualizado!';
                } else {
                    throw new Exception('Erro ao fazer upload do logo');
                }
            }
            
            // Banner
            if (isset($_FILES['banner']) && $_FILES['banner']['error'] == 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                $ext = strtolower(pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION));
                
                if (!in_array($ext, $allowed)) {
                    throw new Exception('Tipo de arquivo não permitido para banner');
                }
                
                if ($_FILES['banner']['size'] > 10485760) {
                    throw new Exception('Banner muito grande (máx 10MB)');
                }
                
                $banner_name = $dev['slug'] . '-banner-' . time() . '.' . $ext;
                $banner_path = $upload_dir . '/' . $banner_name;
                
                if (move_uploaded_file($_FILES['banner']['tmp_name'], $banner_path)) {
                    if ($dev['banner_url']) {
                        $old_file = $_SERVER['DOCUMENT_ROOT'] . $dev['banner_url'];
                        if (file_exists($old_file)) @unlink($old_file);
                    }
                    
                    $stmt = $pdo->prepare("UPDATE desenvolvedor SET banner_url = ? WHERE id = ?");
                    $stmt->execute([$upload_url . '/' . $banner_name, $dev['id']]);
                    $success = 'Banner atualizado!';
                } else {
                    throw new Exception('Erro ao fazer upload do banner');
                }
            }
            
            if (!$success) $success = 'Imagens atualizadas!';
            
        } elseif ($action == 'redes_sociais') {
            $stmt = $pdo->prepare("UPDATE desenvolvedor SET website=?, twitter=?, instagram=?, discord=?, youtube=? WHERE id=?");
            $stmt->execute([trim($_POST['website']), trim($_POST['twitter']), trim($_POST['instagram']), trim($_POST['discord']), trim($_POST['youtube']), $dev['id']]);
            $success = 'Redes sociais atualizadas!';
            
        } elseif ($action == 'financeiro') {
            $stmt = $pdo->prepare("UPDATE desenvolvedor SET tipo_pessoa=?, cpf_cnpj=?, chave_pix=?, banco_codigo=?, banco_agencia=?, banco_conta=? WHERE id=?");
            $stmt->execute([$_POST['tipo_pessoa'], trim($_POST['cpf_cnpj']), trim($_POST['chave_pix']), trim($_POST['banco_codigo']), trim($_POST['banco_agencia']), trim($_POST['banco_conta']), $dev['id']]);
            $success = 'Dados financeiros atualizados!';
        }
        
        // Refresh
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
    .settings-link { display: flex; align-items: center; gap: 15px; padding: 18px 25px; color: var(--text-secondary); transition: all 0.3s; border-left: 3px solid transparent; text-decoration: none; font-weight: 500; }
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
    @media (max-width: 992px) { .profile-container { grid-template-columns: 1fr; } .settings-nav { position: static; display: flex; overflow-x: auto; } .settings-link { border-left: none; border-bottom: 2px solid transparent; } .settings-link.active { border-bottom-color: var(--accent); background: none; } }
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
                            <p style="color: var(--text-secondary); font-size: 14px;">Dados públicos do seu estúdio.</p>
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
                            <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Salvar</button>
                        </form>

                    <?php elseif ($section == 'imagens'): ?>
                        <div class="settings-header">
                            <h2>Identidade Visual</h2>
                            <p style="color: var(--text-secondary); font-size: 14px;">JPG, PNG ou WEBP</p>
                        </div>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="imagens">
                            
                            <div class="form-group" style="text-align: center;">
                                <label class="form-label">Logo (500x500, máx 5MB)</label>
                                <div class="img-upload-box logo-box" onclick="document.getElementById('logoInput').click()">
                                    <?php if ($dev['logo_url']): ?>
                                        <img src="<?= SITE_URL . $dev['logo_url'] ?>" id="logoPreview">
                                    <?php else: ?>
                                        <div class="img-placeholder">
                                            <i class="fas fa-upload fa-2x"></i>
                                            <span>Clique para enviar</span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="img-overlay"><i class="fas fa-camera"></i><br>Alterar</div>
                                </div>
                                <input type="file" name="logo" id="logoInput" hidden accept="image/jpeg,image/jpg,image/png,image/webp" onchange="previewImage(this, 'logoPreview')">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Banner (1920x400, máx 10MB)</label>
                                <div class="img-upload-box banner-box" onclick="document.getElementById('bannerInput').click()">
                                    <?php if ($dev['banner_url']): ?>
                                        <img src="<?= SITE_URL . $dev['banner_url'] ?>" id="bannerPreview">
                                    <?php else: ?>
                                        <div class="img-placeholder">
                                            <i class="fas fa-image fa-3x"></i>
                                            <span>Clique para enviar</span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="img-overlay"><i class="fas fa-camera"></i><br>Alterar Banner</div>
                                </div>
                                <input type="file" name="banner" id="bannerInput" hidden accept="image/jpeg,image/jpg,image/png,image/webp" onchange="previewImage(this, 'bannerPreview')">
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-cloud-upload-alt"></i> Atualizar Imagens</button>
                        </form>

                    <?php elseif ($section == 'redes'): ?>
                        <div class="settings-header"><h2>Redes Sociais</h2></div>
                        <form method="POST">
                            <input type="hidden" name="action" value="redes_sociais">
                            <div class="form-group"><label class="form-label">Website</label><input type="url" name="website" class="form-control" value="<?= sanitize($dev['website']) ?>"></div>
                            <div class="form-group"><label class="form-label">Twitter/X</label><input type="url" name="twitter" class="form-control" value="<?= sanitize($dev['twitter']) ?>"></div>
                            <div class="form-group"><label class="form-label">Instagram</label><input type="url" name="instagram" class="form-control" value="<?= sanitize($dev['instagram']) ?>"></div>
                            <div class="form-group"><label class="form-label">Discord</label><input type="url" name="discord" class="form-control" value="<?= sanitize($dev['discord']) ?>"></div>
                            <div class="form-group"><label class="form-label">YouTube</label><input type="url" name="youtube" class="form-control" value="<?= sanitize($dev['youtube']) ?>"></div>
                            <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Salvar</button>
                        </form>

                    <?php elseif ($section == 'financeiro'): ?>
                        <div class="settings-header"><h2>Dados Financeiros</h2></div>
                        <form method="POST">
                            <input type="hidden" name="action" value="financeiro">
                            <div class="form-group">
                                <label class="form-label">Tipo</label>
                                <select name="tipo_pessoa" class="form-control">
                                    <option value="fisica" <?= $dev['tipo_pessoa']=='fisica'?'selected':'' ?>>Pessoa Física</option>
                                    <option value="juridica" <?= $dev['tipo_pessoa']=='juridica'?'selected':'' ?>>Pessoa Jurídica</option>
                                </select>
                            </div>
                            <div class="form-group"><label class="form-label">CPF/CNPJ</label><input type="text" name="cpf_cnpj" class="form-control" value="<?= sanitize($dev['cpf_cnpj']) ?>"></div>
                            <div class="form-group"><label class="form-label">Chave PIX</label><input type="text" name="chave_pix" class="form-control" value="<?= sanitize($dev['chave_pix']) ?>"></div>
                            <hr style="border-color: var(--border); margin: 30px 0;">
                            <div style="display:grid; grid-template-columns: 100px 100px 1fr; gap:15px;">
                                <div class="form-group"><label class="form-label">Banco</label><input type="text" name="banco_codigo" class="form-control" value="<?= sanitize($dev['banco_codigo']) ?>"></div>
                                <div class="form-group"><label class="form-label">Agência</label><input type="text" name="banco_agencia" class="form-control" value="<?= sanitize($dev['banco_agencia']) ?>"></div>
                                <div class="form-group"><label class="form-label">Conta</label><input type="text" name="banco_conta" class="form-control" value="<?= sanitize($dev['banco_conta']) ?>"></div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-lock"></i> Salvar</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function previewImage(input, imgId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            let img = document.getElementById(imgId);
            if(!img) {
                img = document.createElement('img');
                img.id = imgId;
                const placeholder = input.parentElement.querySelector('.img-placeholder');
                if(placeholder) placeholder.remove();
                input.parentElement.insertBefore(img, input.parentElement.firstChild);
            }
            img.src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
<?php require_once '../includes/footer.php'; ?>