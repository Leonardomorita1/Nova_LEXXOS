<?php
// developer/perfil.php
require_once '../config/config.php';
require_once '../config/database.php';

requireLogin();

$database = new Database();
$pdo = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Buscar desenvolvedor
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

// Processar atualizações
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    
    try {
        if ($action == 'info_basica') {
            $nome_estudio = trim($_POST['nome_estudio']);
            $descricao_curta = trim($_POST['descricao_curta']);
            $descricao = trim($_POST['descricao']);
            
            $stmt = $pdo->prepare("UPDATE desenvolvedor SET nome_estudio = ?, descricao_curta = ?, descricao = ? WHERE id = ?");
            $stmt->execute([$nome_estudio, $descricao_curta, $descricao, $dev['id']]);
            
            $success = 'Informações atualizadas!';
            
        } elseif ($action == 'imagens') {
            $upload_dir = '../uploads/desenvolvedores';
            
            // Logo
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
                $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                $logo_name = $dev['slug'] . '-logo-' . time() . '.' . $ext;
                $logo_path = $upload_dir . '/' . $logo_name;
                
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $logo_path)) {
                    if ($dev['logo_url'] && file_exists('..' . $dev['logo_url'])) {
                        unlink('..' . $dev['logo_url']);
                    }
                    $stmt = $pdo->prepare("UPDATE desenvolvedor SET logo_url = ? WHERE id = ?");
                    $stmt->execute(['/uploads/desenvolvedores/' . $logo_name, $dev['id']]);
                }
            }
            
            // Banner
            if (isset($_FILES['banner']) && $_FILES['banner']['error'] == 0) {
                $ext = pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION);
                $banner_name = $dev['slug'] . '-banner-' . time() . '.' . $ext;
                $banner_path = $upload_dir . '/' . $banner_name;
                
                if (move_uploaded_file($_FILES['banner']['tmp_name'], $banner_path)) {
                    if ($dev['banner_url'] && file_exists('..' . $dev['banner_url'])) {
                        unlink('..' . $dev['banner_url']);
                    }
                    $stmt = $pdo->prepare("UPDATE desenvolvedor SET banner_url = ? WHERE id = ?");
                    $stmt->execute(['/uploads/desenvolvedores/' . $banner_name, $dev['id']]);
                }
            }
            
            $success = 'Imagens atualizadas!';
            
        } elseif ($action == 'redes_sociais') {
            $website = trim($_POST['website']);
            $twitter = trim($_POST['twitter']);
            $instagram = trim($_POST['instagram']);
            $discord = trim($_POST['discord']);
            $youtube = trim($_POST['youtube']);
            
            $stmt = $pdo->prepare("
                UPDATE desenvolvedor SET 
                    website = ?, twitter = ?, instagram = ?, discord = ?, youtube = ?
                WHERE id = ?
            ");
            $stmt->execute([$website, $twitter, $instagram, $discord, $youtube, $dev['id']]);
            
            $success = 'Redes sociais atualizadas!';
            
        } elseif ($action == 'financeiro') {
            $tipo_pessoa = $_POST['tipo_pessoa'];
            $cpf_cnpj = trim($_POST['cpf_cnpj']);
            $chave_pix = trim($_POST['chave_pix']);
            $banco_codigo = trim($_POST['banco_codigo']);
            $banco_agencia = trim($_POST['banco_agencia']);
            $banco_conta = trim($_POST['banco_conta']);
            
            $stmt = $pdo->prepare("
                UPDATE desenvolvedor SET 
                    tipo_pessoa = ?, cpf_cnpj = ?, chave_pix = ?,
                    banco_codigo = ?, banco_agencia = ?, banco_conta = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $tipo_pessoa, $cpf_cnpj, $chave_pix,
                $banco_codigo, $banco_agencia, $banco_conta, $dev['id']
            ]);
            
            $success = 'Dados financeiros atualizados!';
        }
        
        // Recarregar desenvolvedor
        $stmt = $pdo->prepare("SELECT * FROM desenvolvedor WHERE id = ?");
        $stmt->execute([$dev['id']]);
        $dev = $stmt->fetch();
        
    } catch (Exception $e) {
        $error = 'Erro ao atualizar: ' . $e->getMessage();
    }
}

$page_title = 'Editar Perfil - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<style>
.profile-layout {
    display: grid;
    grid-template-columns: 240px 1fr;
    gap: 30px;
}

.profile-sidebar {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 20px;
    height: fit-content;
    position: sticky;
    top: 90px;
}

.profile-sidebar a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    color: var(--text-primary);
    border-radius: 6px;
    transition: all 0.3s;
    margin-bottom: 5px;
}

.profile-sidebar a:hover {
    background: var(--bg-primary);
}

.profile-sidebar a.active {
    background: var(--accent);
    color: white;
}

.profile-content {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 15px;
    padding: 30px;
}

.image-preview {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    overflow: hidden;
    margin-bottom: 15px;
    border: 3px solid var(--accent);
}

.image-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.banner-preview {
    width: 100%;
    height: 200px;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 15px;
    border: 2px solid var(--border);
}

.banner-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

@media (max-width: 992px) {
    .profile-layout {
        grid-template-columns: 1fr;
    }
    
    .profile-sidebar {
        position: static;
    }
}
</style>

<div class="container">
    <div class="dev-layout">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <div class="dev-content">
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-user-edit"></i> Editar Perfil
                </h1>
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
            
            <div class="profile-layout">
                <!-- Sidebar de Seções -->
                <div class="profile-sidebar">
                    <a href="?section=info" class="<?php echo $section == 'info' ? 'active' : ''; ?>">
                        <i class="fas fa-info-circle"></i> Informações Básicas
                    </a>
                    <a href="?section=imagens" class="<?php echo $section == 'imagens' ? 'active' : ''; ?>">
                        <i class="fas fa-images"></i> Logo e Banner
                    </a>
                    <a href="?section=redes" class="<?php echo $section == 'redes' ? 'active' : ''; ?>">
                        <i class="fas fa-share-alt"></i> Redes Sociais
                    </a>
                    <a href="?section=financeiro" class="<?php echo $section == 'financeiro' ? 'active' : ''; ?>">
                        <i class="fas fa-dollar-sign"></i> Dados Financeiros
                    </a>
                </div>
                
                <!-- Conteúdo -->
                <div class="profile-content">
                    <?php if ($section == 'info'): ?>
                        <h2 style="margin-bottom: 25px;"><i class="fas fa-info-circle"></i> Informações Básicas</h2>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="info_basica">
                            
                            <div class="form-group">
                                <label class="form-label">Nome do Estúdio *</label>
                                <input type="text" name="nome_estudio" class="form-control" required
                                       value="<?php echo sanitize($dev['nome_estudio']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Descrição Curta (máx. 300 caracteres)</label>
                                <input type="text" name="descricao_curta" class="form-control" maxlength="300"
                                       value="<?php echo sanitize($dev['descricao_curta']); ?>"
                                       placeholder="Uma breve descrição sobre seu estúdio">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Descrição Completa</label>
                                <textarea name="descricao" class="form-control" rows="8"
                                          placeholder="Conte mais sobre seu estúdio, sua história e missão..."><?php echo sanitize($dev['descricao']); ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Salvar Alterações
                            </button>
                        </form>
                        
                    <?php elseif ($section == 'imagens'): ?>
                        <h2 style="margin-bottom: 25px;"><i class="fas fa-images"></i> Logo e Banner</h2>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="imagens">
                            
                            <div class="form-group">
                                <label class="form-label">Logo do Estúdio (Quadrado, 500x500px recomendado)</label>
                                
                                <?php if ($dev['logo_url']): ?>
                                    <div style="margin-bottom: 15px;">
                                        <strong>Logo Atual:</strong>
                                        <div class="image-preview">
                                            <img src="<?php echo SITE_URL . $dev['logo_url']; ?>" alt="Logo">
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <input type="file" name="logo" class="form-control" accept="image/*">
                                <small style="color: var(--text-secondary);">Deixe vazio para manter o atual</small>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Banner (1920x400px recomendado)</label>
                                
                                <?php if ($dev['banner_url']): ?>
                                    <div style="margin-bottom: 15px;">
                                        <strong>Banner Atual:</strong>
                                        <div class="banner-preview">
                                            <img src="<?php echo SITE_URL . $dev['banner_url']; ?>" alt="Banner">
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <input type="file" name="banner" class="form-control" accept="image/*">
                                <small style="color: var(--text-secondary);">Deixe vazio para manter o atual</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload"></i> Atualizar Imagens
                            </button>
                        </form>
                        
                    <?php elseif ($section == 'redes'): ?>
                        <h2 style="margin-bottom: 25px;"><i class="fas fa-share-alt"></i> Redes Sociais</h2>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="redes_sociais">
                            
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-globe"></i> Website</label>
                                <input type="url" name="website" class="form-control"
                                       value="<?php echo sanitize($dev['website']); ?>"
                                       placeholder="https://seusite.com">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label"><i class="fab fa-twitter"></i> Twitter/X</label>
                                <input type="url" name="twitter" class="form-control"
                                       value="<?php echo sanitize($dev['twitter']); ?>"
                                       placeholder="https://twitter.com/seuestudio">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label"><i class="fab fa-instagram"></i> Instagram</label>
                                <input type="url" name="instagram" class="form-control"
                                       value="<?php echo sanitize($dev['instagram']); ?>"
                                       placeholder="https://instagram.com/seuestudio">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label"><i class="fab fa-discord"></i> Discord</label>
                                <input type="url" name="discord" class="form-control"
                                       value="<?php echo sanitize($dev['discord']); ?>"
                                       placeholder="https://discord.gg/seuservidor">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label"><i class="fab fa-youtube"></i> YouTube</label>
                                <input type="url" name="youtube" class="form-control"
                                       value="<?php echo sanitize($dev['youtube']); ?>"
                                       placeholder="https://youtube.com/@seuestudio">
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Salvar Alterações
                            </button>
                        </form>
                        
                    <?php else: // financeiro ?>
                        <h2 style="margin-bottom: 25px;"><i class="fas fa-dollar-sign"></i> Dados Financeiros</h2>
                        
                        <div style="background: rgba(76,139,245,0.1); border: 1px solid var(--accent); border-radius: 8px; padding: 15px; margin-bottom: 25px;">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Importante:</strong> Esses dados são usados para processar seus saques. Mantenha sempre atualizados.
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="financeiro">
                            
                            <div class="form-group">
                                <label class="form-label">Tipo de Pessoa *</label>
                                <select name="tipo_pessoa" class="form-control" required>
                                    <option value="fisica" <?php echo $dev['tipo_pessoa'] == 'fisica' ? 'selected' : ''; ?>>Pessoa Física</option>
                                    <option value="juridica" <?php echo $dev['tipo_pessoa'] == 'juridica' ? 'selected' : ''; ?>>Pessoa Jurídica</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">CPF/CNPJ *</label>
                                <input type="text" name="cpf_cnpj" class="form-control" required
                                       value="<?php echo sanitize($dev['cpf_cnpj']); ?>"
                                       placeholder="Apenas números">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Chave PIX</label>
                                <input type="text" name="chave_pix" class="form-control"
                                       value="<?php echo sanitize($dev['chave_pix']); ?>"
                                       placeholder="CPF, email, telefone ou chave aleatória">
                                <small style="color: var(--text-secondary);">Necessária para saques via PIX</small>
                            </div>
                            
                            <hr style="border-color: var(--border); margin: 30px 0;">
                            
                            <h3 style="margin-bottom: 20px;">Dados Bancários (Opcional)</h3>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                                <div class="form-group">
                                    <label class="form-label">Código do Banco</label>
                                    <input type="text" name="banco_codigo" class="form-control"
                                           value="<?php echo sanitize($dev['banco_codigo']); ?>"
                                           placeholder="001">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Agência</label>
                                    <input type="text" name="banco_agencia" class="form-control"
                                           value="<?php echo sanitize($dev['banco_agencia']); ?>"
                                           placeholder="0001">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Conta</label>
                                    <input type="text" name="banco_conta" class="form-control"
                                           value="<?php echo sanitize($dev['banco_conta']); ?>"
                                           placeholder="12345-6">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Salvar Alterações
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>