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

if (!$dev) {
    header('Location: ' . SITE_URL . '/user/seja-dev.php');
    exit;
}

// Verificar status pendente
if ($dev['status'] == 'pendente') {
    header('Location: ' . SITE_URL . '/developer/dashboard.php');
    exit;
}

$errors = [];
$success = '';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_basic') {
        // Atualizar informações básicas
        $nome_estudio = trim($_POST['nome_estudio'] ?? '');
        $descricao_curta = trim($_POST['descricao_curta'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $twitter = trim($_POST['twitter'] ?? '');
        $instagram = trim($_POST['instagram'] ?? '');
        $discord = trim($_POST['discord'] ?? '');
        $youtube = trim($_POST['youtube'] ?? '');
        
        // Validações
        if (empty($nome_estudio)) {
            $errors[] = 'Nome do estúdio é obrigatório.';
        } elseif (strlen($nome_estudio) > 150) {
            $errors[] = 'Nome do estúdio deve ter no máximo 150 caracteres.';
        }
        
        if (strlen($descricao_curta) > 300) {
            $errors[] = 'Descrição curta deve ter no máximo 300 caracteres.';
        }
        
        // Validar URLs
        if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
            $errors[] = 'URL do website inválida.';
        }
        
        // Verificar se nome do estúdio já existe (exceto o próprio)
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id FROM desenvolvedor WHERE nome_estudio = ? AND id != ?");
            $stmt->execute([$nome_estudio, $dev['id']]);
            if ($stmt->fetch()) {
                $errors[] = 'Este nome de estúdio já está em uso.';
            }
        }
        
        if (empty($errors)) {
            // Gerar novo slug se nome mudou
            $slug = $dev['slug'];
            if ($nome_estudio !== $dev['nome_estudio']) {
                $slug = generateUniqueSlug($pdo, $nome_estudio, 'desenvolvedor', $dev['id']);
            }
            
            $stmt = $pdo->prepare("
                UPDATE desenvolvedor SET 
                    nome_estudio = ?,
                    slug = ?,
                    descricao_curta = ?,
                    descricao = ?,
                    website = ?,
                    twitter = ?,
                    instagram = ?,
                    discord = ?,
                    youtube = ?,
                    atualizado_em = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $nome_estudio,
                $slug,
                $descricao_curta ?: null,
                $descricao ?: null,
                $website ?: null,
                $twitter ?: null,
                $instagram ?: null,
                $discord ?: null,
                $youtube ?: null,
                $dev['id']
            ]);
            
            $success = 'Informações básicas atualizadas com sucesso!';
            
            // Recarregar dados
            $stmt = $pdo->prepare("SELECT * FROM desenvolvedor WHERE id = ?");
            $stmt->execute([$dev['id']]);
            $dev = $stmt->fetch();
        }
        
    } elseif ($action === 'update_financial') {
        // Atualizar informações financeiras
        $tipo_pessoa = $_POST['tipo_pessoa'] ?? 'fisica';
        $cpf_cnpj = preg_replace('/[^0-9]/', '', $_POST['cpf_cnpj'] ?? '');
        $chave_pix = trim($_POST['chave_pix'] ?? '');
        $banco_codigo = trim($_POST['banco_codigo'] ?? '');
        $banco_agencia = trim($_POST['banco_agencia'] ?? '');
        $banco_conta = trim($_POST['banco_conta'] ?? '');
        
        // Validações
        if (!in_array($tipo_pessoa, ['fisica', 'juridica'])) {
            $errors[] = 'Tipo de pessoa inválido.';
        }
        
        if (!empty($cpf_cnpj)) {
            if ($tipo_pessoa === 'fisica' && strlen($cpf_cnpj) !== 11) {
                $errors[] = 'CPF deve ter 11 dígitos.';
            } elseif ($tipo_pessoa === 'juridica' && strlen($cpf_cnpj) !== 14) {
                $errors[] = 'CNPJ deve ter 14 dígitos.';
            }
        }
        
        if (empty($errors)) {
            $stmt = $pdo->prepare("
                UPDATE desenvolvedor SET 
                    tipo_pessoa = ?,
                    cpf_cnpj = ?,
                    chave_pix = ?,
                    banco_codigo = ?,
                    banco_agencia = ?,
                    banco_conta = ?,
                    atualizado_em = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $tipo_pessoa,
                $cpf_cnpj ?: null,
                $chave_pix ?: null,
                $banco_codigo ?: null,
                $banco_agencia ?: null,
                $banco_conta ?: null,
                $dev['id']
            ]);
            
            $success = 'Informações financeiras atualizadas com sucesso!';
            
            // Recarregar dados
            $stmt = $pdo->prepare("SELECT * FROM desenvolvedor WHERE id = ?");
            $stmt->execute([$dev['id']]);
            $dev = $stmt->fetch();
        }
        
    } elseif ($action === 'update_images') {
        // Upload de imagens
        $upload_dir = BASE_PATH . '/uploads/developers/';
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Upload da logo
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $result = uploadImage($_FILES['logo'], $upload_dir, 'logo_' . $dev['id'], 500, 500);
            if ($result['success']) {
                // Deletar logo antiga
                if ($dev['logo_url'] && file_exists(BASE_PATH . $dev['logo_url'])) {
                    unlink(BASE_PATH . $dev['logo_url']);
                }
                
                $stmt = $pdo->prepare("UPDATE desenvolvedor SET logo_url = ? WHERE id = ?");
                $stmt->execute(['/uploads/developers/' . $result['filename'], $dev['id']]);
                $dev['logo_url'] = '/uploads/developers/' . $result['filename'];
            } else {
                $errors[] = 'Erro no upload da logo: ' . $result['error'];
            }
        }
        
        // Upload do banner
        if (isset($_FILES['banner']) && $_FILES['banner']['error'] === UPLOAD_ERR_OK) {
            $result = uploadImage($_FILES['banner'], $upload_dir, 'banner_' . $dev['id'], 1920, 400);
            if ($result['success']) {
                // Deletar banner antigo
                if ($dev['banner_url'] && file_exists(BASE_PATH . $dev['banner_url'])) {
                    unlink(BASE_PATH . $dev['banner_url']);
                }
                
                $stmt = $pdo->prepare("UPDATE desenvolvedor SET banner_url = ? WHERE id = ?");
                $stmt->execute(['/uploads/developers/' . $result['filename'], $dev['id']]);
                $dev['banner_url'] = '/uploads/developers/' . $result['filename'];
            } else {
                $errors[] = 'Erro no upload do banner: ' . $result['error'];
            }
        }
        
        if (empty($errors)) {
            $success = 'Imagens atualizadas com sucesso!';
        }
    }
}

// Função auxiliar para gerar slug único
function generateUniqueSlug($pdo, $text, $table, $exclude_id = null) {
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text)));
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    
    $original_slug = $slug;
    $counter = 1;
    
    while (true) {
        $sql = "SELECT id FROM $table WHERE slug = ?";
        $params = [$slug];
        
        if ($exclude_id) {
            $sql .= " AND id != ?";
            $params[] = $exclude_id;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        if (!$stmt->fetch()) {
            break;
        }
        
        $slug = $original_slug . '-' . $counter;
        $counter++;
    }
    
    return $slug;
}

// Função auxiliar para upload de imagem
function uploadImage($file, $upload_dir, $prefix, $max_width, $max_height) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'error' => 'Tipo de arquivo não permitido. Use JPG, PNG ou WebP.'];
    }
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'Arquivo muito grande. Máximo 5MB.'];
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $prefix . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => $filename];
    }
    
    return ['success' => false, 'error' => 'Erro ao salvar arquivo.'];
}

// Lista de bancos
$bancos = [
    '001' => 'Banco do Brasil',
    '033' => 'Santander',
    '104' => 'Caixa Econômica',
    '237' => 'Bradesco',
    '341' => 'Itaú',
    '260' => 'Nubank',
    '077' => 'Inter',
    '336' => 'C6 Bank',
    '290' => 'PagBank',
    '323' => 'Mercado Pago',
    '380' => 'PicPay',
    '756' => 'Sicoob',
    '748' => 'Sicredi',
    '212' => 'Original',
    '655' => 'Neon',
    '070' => 'BRB',
    '000' => 'Outro'
];

$page_title = 'Perfil do Estúdio - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<style>
    .profile-header {
        position: relative;
        margin-bottom: 30px;
        border-radius: 15px;
        overflow: hidden;
        background: var(--bg-secondary);
        border: 1px solid var(--border);
    }
    
    .profile-banner {
        width: 100%;
        height: 200px;
        object-fit: cover;
        background: linear-gradient(135deg, var(--accent) 0%, #6ba3ff 100%);
    }
    
    .profile-banner-placeholder {
        width: 100%;
        height: 200px;
        background: linear-gradient(135deg, var(--accent) 0%, #6ba3ff 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 48px;
    }
    
    .profile-info {
        display: flex;
        align-items: flex-end;
        gap: 20px;
        padding: 0 30px 30px;
        margin-top: -60px;
        position: relative;
    }
    
    .profile-logo {
        width: 120px;
        height: 120px;
        border-radius: 15px;
        border: 4px solid var(--bg-secondary);
        object-fit: cover;
        background: var(--bg-tertiary);
    }
    
    .profile-logo-placeholder {
        width: 120px;
        height: 120px;
        border-radius: 15px;
        border: 4px solid var(--bg-secondary);
        background: var(--bg-tertiary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 48px;
        color: var(--text-secondary);
    }
    
    .profile-details h1 {
        font-size: 28px;
        margin-bottom: 5px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .profile-details h1 .verified {
        color: var(--accent);
        font-size: 20px;
    }
    
    .profile-details .slug {
        color: var(--text-secondary);
        font-size: 14px;
    }
    
    .profile-tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 30px;
        border-bottom: 1px solid var(--border);
        padding-bottom: 15px;
        flex-wrap: wrap;
    }
    
    .profile-tab {
        padding: 12px 24px;
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--text-primary);
        cursor: pointer;
        transition: all 0.3s;
        font-weight: 500;
    }
    
    .profile-tab:hover {
        border-color: var(--accent);
    }
    
    .profile-tab.active {
        background: var(--accent);
        border-color: var(--accent);
        color: white;
    }
    
    .profile-section {
        display: none;
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 15px;
        padding: 30px;
    }
    
    .profile-section.active {
        display: block;
    }
    
    .profile-section h2 {
        font-size: 22px;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .profile-section h2 i {
        color: var(--accent);
    }
    
    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }
    
    .form-grid .full-width {
        grid-column: 1 / -1;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: var(--text-primary);
    }
    
    .form-group label span {
        color: var(--danger);
    }
    
    .form-group input,
    .form-group textarea,
    .form-group select {
        width: 100%;
        padding: 12px 15px;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--text-primary);
        font-size: 15px;
        transition: all 0.3s;
    }
    
    .form-group input:focus,
    .form-group textarea:focus,
    .form-group select:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(79, 134, 247, 0.1);
    }
    
    .form-group textarea {
        resize: vertical;
        min-height: 120px;
    }
    
    .form-group small {
        display: block;
        margin-top: 6px;
        color: var(--text-secondary);
        font-size: 13px;
    }
    
    .char-counter {
        text-align: right;
        font-size: 12px;
        color: var(--text-secondary);
        margin-top: 5px;
    }
    
    .char-counter.warning {
        color: var(--warning);
    }
    
    .char-counter.danger {
        color: var(--danger);
    }
    
    .social-input {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .social-input .prefix {
        background: var(--bg-tertiary);
        padding: 12px 15px;
        border: 1px solid var(--border);
        border-radius: 8px 0 0 8px;
        color: var(--text-secondary);
        font-size: 14px;
        white-space: nowrap;
    }
    
    .social-input input {
        border-radius: 0 8px 8px 0;
        border-left: none;
    }
    
    .image-upload {
        border: 2px dashed var(--border);
        border-radius: 10px;
        padding: 30px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
        position: relative;
    }
    
    .image-upload:hover {
        border-color: var(--accent);
        background: rgba(79, 134, 247, 0.05);
    }
    
    .image-upload input {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
    }
    
    .image-upload i {
        font-size: 48px;
        color: var(--text-secondary);
        margin-bottom: 15px;
    }
    
    .image-upload p {
        color: var(--text-secondary);
        margin-bottom: 5px;
    }
    
    .image-upload small {
        color: var(--text-tertiary);
        font-size: 12px;
    }
    
    .image-preview {
        margin-top: 15px;
        position: relative;
        display: inline-block;
    }
    
    .image-preview img {
        max-width: 200px;
        max-height: 150px;
        border-radius: 8px;
        object-fit: cover;
    }
    
    .current-image {
        margin-top: 15px;
        padding: 15px;
        background: var(--bg-primary);
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .current-image img {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border-radius: 8px;
    }
    
    .current-image .info {
        flex: 1;
    }
    
    .current-image .info p {
        font-size: 14px;
        color: var(--text-secondary);
    }
    
    .financial-notice {
        background: rgba(255, 193, 7, 0.1);
        border: 1px solid var(--warning);
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 25px;
    }
    
    .financial-notice h4 {
        color: var(--warning);
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .financial-notice p {
        color: var(--text-secondary);
        font-size: 14px;
        line-height: 1.6;
    }
    
    .pix-types {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 10px;
        margin-bottom: 15px;
    }
    
    .pix-type {
        padding: 10px;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 8px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
        font-size: 13px;
    }
    
    .pix-type:hover,
    .pix-type.active {
        border-color: var(--accent);
        background: rgba(79, 134, 247, 0.1);
    }
    
    .btn-save {
        padding: 14px 30px;
        font-size: 16px;
    }
    
    .alert {
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    
    .alert-success {
        background: rgba(40, 167, 69, 0.1);
        border: 1px solid var(--success);
        color: var(--success);
    }
    
    .alert-danger {
        background: rgba(220, 53, 69, 0.1);
        border: 1px solid var(--danger);
        color: var(--danger);
    }
    
    .preview-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: var(--bg-tertiary);
        border-radius: 8px;
        color: var(--text-primary);
        font-size: 14px;
        margin-top: 15px;
        transition: all 0.3s;
    }
    
    .preview-link:hover {
        background: var(--accent);
        color: white;
    }
    
    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
        }
        
        .profile-info {
            flex-direction: column;
            align-items: center;
            text-align: center;
            margin-top: -40px;
        }
        
        .profile-logo,
        .profile-logo-placeholder {
            width: 100px;
            height: 100px;
        }
        
        .pix-types {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .profile-tabs {
            justify-content: center;
        }
    }
</style>

<div class="container">
    <div class="dev-layout">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <div class="dev-content">
            <!-- Header do Perfil -->
            <div class="profile-header">
                <?php if ($dev['banner_url']): ?>
                    <img src="<?php echo SITE_URL . $dev['banner_url']; ?>" alt="Banner" class="profile-banner">
                <?php else: ?>
                    <div class="profile-banner-placeholder">
                        <i class="fas fa-image"></i>
                    </div>
                <?php endif; ?>
                
                <div class="profile-info">
                    <?php if ($dev['logo_url']): ?>
                        <img src="<?php echo SITE_URL . $dev['logo_url']; ?>" alt="Logo" class="profile-logo">
                    <?php else: ?>
                        <div class="profile-logo-placeholder">
                            <i class="fas fa-building"></i>
                        </div>
                    <?php endif; ?>
                    
                    <div class="profile-details">
                        <h1>
                            <?php echo sanitize($dev['nome_estudio']); ?>
                            <?php if ($dev['verificado']): ?>
                                <i class="fas fa-check-circle verified" title="Desenvolvedor Verificado"></i>
                            <?php endif; ?>
                        </h1>
                        <p class="slug">
                            <i class="fas fa-link"></i> 
                            <?php echo SITE_URL; ?>/desenvolvedor/<?php echo $dev['slug']; ?>
                        </p>
                        <a href="<?php echo SITE_URL; ?>/pages/desenvolvedor.php?slug=<?php echo $dev['slug']; ?>" 
                           class="preview-link" target="_blank">
                            <i class="fas fa-external-link-alt"></i> Ver Página Pública
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Alertas -->
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo $error; ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Tabs -->
            <div class="profile-tabs">
                <button class="profile-tab active" data-tab="basic">
                    <i class="fas fa-info-circle"></i> Informações Básicas
                </button>
                <button class="profile-tab" data-tab="images">
                    <i class="fas fa-image"></i> Imagens
                </button>
                <button class="profile-tab" data-tab="social">
                    <i class="fas fa-share-alt"></i> Redes Sociais
                </button>
                <button class="profile-tab" data-tab="financial">
                    <i class="fas fa-university"></i> Dados Financeiros
                </button>
            </div>
            
            <!-- Seção: Informações Básicas -->
            <div class="profile-section active" id="tab-basic">
                <h2><i class="fas fa-info-circle"></i> Informações Básicas</h2>
                
                <form method="POST">
                    <input type="hidden" name="action" value="update_basic">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nome do Estúdio <span>*</span></label>
                            <input type="text" 
                                   name="nome_estudio" 
                                   value="<?php echo sanitize($dev['nome_estudio']); ?>" 
                                   maxlength="150"
                                   required>
                            <small>Nome que será exibido publicamente</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Website</label>
                            <input type="url" 
                                   name="website" 
                                   value="<?php echo sanitize($dev['website'] ?? ''); ?>" 
                                   placeholder="https://seusite.com">
                        </div>
                        
                        <div class="form-group full-width">
                            <label>Descrição Curta</label>
                            <input type="text" 
                                   name="descricao_curta" 
                                   value="<?php echo sanitize($dev['descricao_curta'] ?? ''); ?>" 
                                   maxlength="300"
                                   id="descricao_curta"
                                   placeholder="Uma breve descrição do seu estúdio">
                            <div class="char-counter">
                                <span id="curta-count"><?php echo strlen($dev['descricao_curta'] ?? ''); ?></span>/300
                            </div>
                        </div>
                        
                        <div class="form-group full-width">
                            <label>Descrição Completa</label>
                            <textarea name="descricao" 
                                      rows="6"
                                      placeholder="Conte a história do seu estúdio, sua missão, tipos de jogos que desenvolvem..."><?php echo sanitize($dev['descricao'] ?? ''); ?></textarea>
                            <small>Será exibida na sua página de desenvolvedor</small>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-save">
                        <i class="fas fa-save"></i> Salvar Informações
                    </button>
                </form>
            </div>
            
            <!-- Seção: Imagens -->
            <div class="profile-section" id="tab-images">
                <h2><i class="fas fa-image"></i> Imagens do Estúdio</h2>
                
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_images">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Logo do Estúdio</label>
                            <div class="image-upload" id="logo-upload">
                                <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" id="logo-input">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Clique ou arraste para enviar</p>
                                <small>JPG, PNG ou WebP • Máx 5MB • Recomendado: 500x500px</small>
                            </div>
                            
                            <?php if ($dev['logo_url']): ?>
                                <div class="current-image">
                                    <img src="<?php echo SITE_URL . $dev['logo_url']; ?>" alt="Logo atual">
                                    <div class="info">
                                        <strong>Logo Atual</strong>
                                        <p>Envie uma nova imagem para substituir</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="image-preview" id="logo-preview" style="display: none;">
                                <img src="" alt="Preview">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Banner do Perfil</label>
                            <div class="image-upload" id="banner-upload">
                                <input type="file" name="banner" accept="image/jpeg,image/png,image/webp" id="banner-input">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Clique ou arraste para enviar</p>
                                <small>JPG, PNG ou WebP • Máx 5MB • Recomendado: 1920x400px</small>
                            </div>
                            
                            <?php if ($dev['banner_url']): ?>
                                <div class="current-image">
                                    <img src="<?php echo SITE_URL . $dev['banner_url']; ?>" alt="Banner atual">
                                    <div class="info">
                                        <strong>Banner Atual</strong>
                                        <p>Envie uma nova imagem para substituir</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="image-preview" id="banner-preview" style="display: none;">
                                <img src="" alt="Preview">
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-save">
                        <i class="fas fa-upload"></i> Enviar Imagens
                    </button>
                </form>
            </div>
            
            <!-- Seção: Redes Sociais -->
            <div class="profile-section" id="tab-social">
                <h2><i class="fas fa-share-alt"></i> Redes Sociais</h2>
                
                <form method="POST">
                    <input type="hidden" name="action" value="update_basic">
                    <input type="hidden" name="nome_estudio" value="<?php echo sanitize($dev['nome_estudio']); ?>">
                    <input type="hidden" name="descricao_curta" value="<?php echo sanitize($dev['descricao_curta'] ?? ''); ?>">
                    <input type="hidden" name="descricao" value="<?php echo sanitize($dev['descricao'] ?? ''); ?>">
                    <input type="hidden" name="website" value="<?php echo sanitize($dev['website'] ?? ''); ?>">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fab fa-twitter" style="color: #1DA1F2;"></i> Twitter / X</label>
                            <div class="social-input">
                                <span class="prefix">twitter.com/</span>
                                <input type="text" 
                                       name="twitter" 
                                       value="<?php echo sanitize($dev['twitter'] ?? ''); ?>"
                                       placeholder="seu_usuario">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fab fa-instagram" style="color: #E4405F;"></i> Instagram</label>
                            <div class="social-input">
                                <span class="prefix">instagram.com/</span>
                                <input type="text" 
                                       name="instagram" 
                                       value="<?php echo sanitize($dev['instagram'] ?? ''); ?>"
                                       placeholder="seu_usuario">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fab fa-discord" style="color: #5865F2;"></i> Discord</label>
                            <div class="social-input">
                                <span class="prefix">discord.gg/</span>
                                <input type="text" 
                                       name="discord" 
                                       value="<?php echo sanitize($dev['discord'] ?? ''); ?>"
                                       placeholder="seu_servidor">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fab fa-youtube" style="color: #FF0000;"></i> YouTube</label>
                            <div class="social-input">
                                <span class="prefix">youtube.com/</span>
                                <input type="text" 
                                       name="youtube" 
                                       value="<?php echo sanitize($dev['youtube'] ?? ''); ?>"
                                       placeholder="@seu_canal">
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-save">
                        <i class="fas fa-save"></i> Salvar Redes Sociais
                    </button>
                </form>
            </div>
            
            <!-- Seção: Dados Financeiros -->
            <div class="profile-section" id="tab-financial">
                <h2><i class="fas fa-university"></i> Dados Financeiros</h2>
                
                <div class="financial-notice">
                    <h4><i class="fas fa-shield-alt"></i> Seus dados estão seguros</h4>
                    <p>
                        Suas informações financeiras são criptografadas e utilizadas apenas para processar seus pagamentos.
                        Os saques são processados em até 7 dias úteis após a solicitação.
                    </p>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="update_financial">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Tipo de Pessoa</label>
                            <select name="tipo_pessoa" id="tipo_pessoa">
                                <option value="fisica" <?php echo ($dev['tipo_pessoa'] ?? '') === 'fisica' ? 'selected' : ''; ?>>
                                    Pessoa Física (CPF)
                                </option>
                                <option value="juridica" <?php echo ($dev['tipo_pessoa'] ?? '') === 'juridica' ? 'selected' : ''; ?>>
                                    Pessoa Jurídica (CNPJ)
                                </option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label id="cpf_cnpj_label">CPF</label>
                            <input type="text" 
                                   name="cpf_cnpj" 
                                   id="cpf_cnpj"
                                   value="<?php echo sanitize($dev['cpf_cnpj'] ?? ''); ?>"
                                   placeholder="000.000.000-00">
                        </div>
                        
                        <div class="form-group full-width">
                            <label>Chave PIX</label>
                            <input type="text" 
                                   name="chave_pix" 
                                   value="<?php echo sanitize($dev['chave_pix'] ?? ''); ?>"
                                   placeholder="CPF, CNPJ, e-mail, telefone ou chave aleatória">
                            <small>Pode ser CPF, CNPJ, e-mail, telefone ou chave aleatória</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Banco</label>
                            <select name="banco_codigo">
                                <option value="">Selecione um banco</option>
                                <?php foreach ($bancos as $codigo => $nome): ?>
                                    <option value="<?php echo $codigo; ?>" 
                                            <?php echo ($dev['banco_codigo'] ?? '') === $codigo ? 'selected' : ''; ?>>
                                        <?php echo $codigo; ?> - <?php echo $nome; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Agência</label>
                            <input type="text" 
                                   name="banco_agencia" 
                                   value="<?php echo sanitize($dev['banco_agencia'] ?? ''); ?>"
                                   placeholder="0000">
                        </div>
                        
                        <div class="form-group">
                            <label>Conta (com dígito)</label>
                            <input type="text" 
                                   name="banco_conta" 
                                   value="<?php echo sanitize($dev['banco_conta'] ?? ''); ?>"
                                   placeholder="00000-0">
                        </div>
                    </div>
                    
                    <div style="background: var(--bg-primary); padding: 20px; border-radius: 10px; margin: 20px 0;">
                        <h4 style="margin-bottom: 10px;">
                            <i class="fas fa-info-circle" style="color: var(--accent);"></i> 
                            Taxa da Plataforma
                        </h4>
                        <p style="color: var(--text-secondary); font-size: 14px;">
                            A taxa atual da plataforma é de 
                            <strong style="color: var(--accent);"><?php echo number_format($dev['percentual_plataforma'], 0); ?>%</strong> 
                            sobre cada venda. Você recebe 
                            <strong style="color: var(--success);"><?php echo 100 - $dev['percentual_plataforma']; ?>%</strong> 
                            do valor de cada jogo vendido.
                        </p>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-save">
                        <i class="fas fa-save"></i> Salvar Dados Financeiros
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Tabs
document.querySelectorAll('.profile-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        // Remove active de todas as tabs
        document.querySelectorAll('.profile-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.profile-section').forEach(s => s.classList.remove('active'));
        
        // Ativa a tab clicada
        this.classList.add('active');
        document.getElementById('tab-' + this.dataset.tab).classList.add('active');
    });
});

// Contador de caracteres
const descricaoCurta = document.getElementById('descricao_curta');
const curtaCount = document.getElementById('curta-count');

if (descricaoCurta && curtaCount) {
    descricaoCurta.addEventListener('input', function() {
        const count = this.value.length;
        curtaCount.textContent = count;
        
        const counter = curtaCount.parentElement;
        counter.classList.remove('warning', 'danger');
        
        if (count > 280) {
            counter.classList.add('danger');
        } else if (count > 250) {
            counter.classList.add('warning');
        }
    });
}

// Preview de imagens
function setupImagePreview(inputId, previewId) {
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    
    if (input && preview) {
        input.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.querySelector('img').src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    }
}

setupImagePreview('logo-input', 'logo-preview');
setupImagePreview('banner-input', 'banner-preview');

// Máscara de CPF/CNPJ
const tipoPessoa = document.getElementById('tipo_pessoa');
const cpfCnpj = document.getElementById('cpf_cnpj');
const cpfCnpjLabel = document.getElementById('cpf_cnpj_label');

function updateCpfCnpjMask() {
    const tipo = tipoPessoa.value;
    
    if (tipo === 'fisica') {
        cpfCnpjLabel.textContent = 'CPF';
        cpfCnpj.placeholder = '000.000.000-00';
        cpfCnpj.maxLength = 14;
    } else {
        cpfCnpjLabel.textContent = 'CNPJ';
        cpfCnpj.placeholder = '00.000.000/0000-00';
        cpfCnpj.maxLength = 18;
    }
}

if (tipoPessoa) {
    tipoPessoa.addEventListener('change', updateCpfCnpjMask);
    updateCpfCnpjMask();
}

// Máscara para CPF/CNPJ
if (cpfCnpj) {
    cpfCnpj.addEventListener('input', function() {
        let value = this.value.replace(/D/g, '');
        const tipo = tipoPessoa.value;
        
        if (tipo === 'fisica') {
            // Máscara CPF: 000.000.000-00
            if (value.length > 11) value = value.slice(0, 11);
            value = value.replace(/(d{3})(d)/, '$1.$2');
            value = value.replace(/(d{3})(d)/, '$1.$2');
            value = value.replace(/(d{3})(d{1,2})$/, '$1-$2');
        } else {
            // Máscara CNPJ: 00.000.000/0000-00
            if (value.length > 14) value = value.slice(0, 14);
            value = value.replace(/(d{2})(d)/, '$1.$2');
            value = value.replace(/(d{3})(d)/, '$1.$2');
            value = value.replace(/(d{3})(d)/, '$1/$2');
            value = value.replace(/(d{4})(d{1,2})$/, '$1-$2');
        }
        
        this.value = value;
    });
}

// Verificar URL hash para abrir tab específica
if (window.location.hash) {
    const tabName = window.location.hash.replace('#', '');
    const tab = document.querySelector(`.profile-tab[data-tab="${tabName}"]`);
    if (tab) {
        tab.click();
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>