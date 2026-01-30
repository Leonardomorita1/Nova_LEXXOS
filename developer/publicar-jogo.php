<?php
// developer/publicar-jogo.php
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

$error = '';

// ===========================================
// CONFIGURAÇÕES DE COMPRESSÃO
// ===========================================
define('COMPRESSION_QUALITY', 80);

$image_config = [
    'capa' => ['max_width' => 378],
    'banner' => ['max_width' => 1456],
    'screenshots' => ['max_width' => 746]
];

$allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
$allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];

// ===========================================
// FUNÇÃO DE COMPRESSÃO DE IMAGEM
// ===========================================
function compressImage($source, $destination, $maxWidth, $quality = 80) {
    $info = getimagesize($source);
    if ($info === false) {
        return false;
    }
    
    $mime = $info['mime'];
    $width = $info[0];
    $height = $info[1];
    
    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $image = imagecreatefrompng($source);
            break;
        case 'image/webp':
            $image = imagecreatefromwebp($source);
            break;
        default:
            return false;
    }
    
    if (!$image) {
        return false;
    }
    
    // Calcular novas dimensões mantendo proporção
    if ($width > $maxWidth) {
        $ratio = $maxWidth / $width;
        $newWidth = $maxWidth;
        $newHeight = (int)($height * $ratio);
    } else {
        $newWidth = $width;
        $newHeight = $height;
    }
    
    $newImage = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preservar transparência para PNG e WebP
    if ($mime === 'image/png' || $mime === 'image/webp') {
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
        imagefill($newImage, 0, 0, $transparent);
    }
    
    imagecopyresampled(
        $newImage, $image,
        0, 0, 0, 0,
        $newWidth, $newHeight,
        $width, $height
    );
    
    $ext = strtolower(pathinfo($destination, PATHINFO_EXTENSION));
    
    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            $result = imagejpeg($newImage, $destination, $quality);
            break;
        case 'png':
            $pngQuality = (int)((100 - $quality) / 10);
            $result = imagepng($newImage, $destination, $pngQuality);
            break;
        case 'webp':
            $result = imagewebp($newImage, $destination, $quality);
            break;
        default:
            $result = imagejpeg($newImage, $destination, $quality);
    }
    
    imagedestroy($image);
    imagedestroy($newImage);
    
    return $result;
}

// ===========================================
// VALIDAÇÃO SIMPLES DE TIPO
// ===========================================
function isValidImage($file, $allowed_types, $allowed_ext) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    return in_array($mime, $allowed_types) && in_array($ext, $allowed_ext);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();

        // 1. Dados Básicos
        $titulo = trim($_POST['titulo']);
        $descricao_curta = trim($_POST['descricao_curta']);
        $descricao_completa = trim($_POST['descricao_completa']);
        $preco_centavos = (int)($_POST['preco'] * 100);
        $classificacao = $_POST['classificacao'];
        $video_trailer = trim($_POST['video_trailer']);
        $req_min = trim($_POST['requisitos_minimos']);
        $req_rec = trim($_POST['requisitos_recomendados']);

        if (empty($titulo) || empty($descricao_curta)) {
            throw new Exception('Título e Descrição Curta são obrigatórios.');
        }

        // 2. Slug
        $slug = generateSlug($titulo);
        $stmt_check = $pdo->prepare("SELECT id FROM jogo WHERE slug = ?");
        $stmt_check->execute([$slug]);
        if ($stmt_check->fetch()) {
            $slug .= '-' . uniqid();
        }

        // 3. Diretório
        $upload_dir = '../uploads/jogos/' . $slug;
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0755, true);

        // 4. Upload Capa (com compressão)
        $capa_path = null;
        if (!empty($_FILES['capa']['name']) && $_FILES['capa']['error'] === UPLOAD_ERR_OK) {
            if (isValidImage($_FILES['capa'], $allowed_types, $allowed_ext)) {
                $ext = strtolower(pathinfo($_FILES['capa']['name'], PATHINFO_EXTENSION));
                $target = $upload_dir . '/capa.' . $ext;
                
                if (compressImage($_FILES['capa']['tmp_name'], $target, $image_config['capa']['max_width'], COMPRESSION_QUALITY)) {
                    $capa_path = '/uploads/jogos/' . $slug . '/capa.' . $ext;
                }
            } else {
                throw new Exception('Capa: formato inválido. Use JPG, PNG ou WebP.');
            }
        }

        // 5. Upload Banner (com compressão)
        $banner_path = null;
        if (!empty($_FILES['banner']['name']) && $_FILES['banner']['error'] === UPLOAD_ERR_OK) {
            if (isValidImage($_FILES['banner'], $allowed_types, $allowed_ext)) {
                $ext = strtolower(pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION));
                $target = $upload_dir . '/banner.' . $ext;
                
                if (compressImage($_FILES['banner']['tmp_name'], $target, $image_config['banner']['max_width'], COMPRESSION_QUALITY)) {
                    $banner_path = '/uploads/jogos/' . $slug . '/banner.' . $ext;
                }
            } else {
                throw new Exception('Banner: formato inválido. Use JPG, PNG ou WebP.');
            }
        }

        // 6. Inserir Jogo
        $sql = "INSERT INTO jogo (desenvolvedor_id, titulo, slug, descricao_curta, descricao_completa, preco_centavos, imagem_capa, imagem_banner, video_trailer, requisitos_minimos, requisitos_recomendados, classificacao_etaria, status, criado_em) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'rascunho', NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$dev['id'], $titulo, $slug, $descricao_curta, $descricao_completa, $preco_centavos, $capa_path, $banner_path, $video_trailer, $req_min, $req_rec, $classificacao]);
        $jogo_id = $pdo->lastInsertId();

        // 7. Screenshots (com compressão)
        if (!empty($_FILES['screenshots']['name'][0])) {
            $shot_dir = $upload_dir . '/screenshots';
            if (!file_exists($shot_dir)) mkdir($shot_dir, 0755, true);
            
            foreach ($_FILES['screenshots']['tmp_name'] as $k => $tmp) {
                if ($_FILES['screenshots']['error'][$k] === UPLOAD_ERR_OK) {
                    $shot_file = [
                        'name' => $_FILES['screenshots']['name'][$k],
                        'tmp_name' => $tmp,
                        'error' => $_FILES['screenshots']['error'][$k]
                    ];
                    
                    if (isValidImage($shot_file, $allowed_types, $allowed_ext)) {
                        $ext = strtolower(pathinfo($_FILES['screenshots']['name'][$k], PATHINFO_EXTENSION));
                        $fname = time() . "-$k." . $ext;
                        $target = "$shot_dir/$fname";
                        
                        if (compressImage($tmp, $target, $image_config['screenshots']['max_width'], COMPRESSION_QUALITY)) {
                            $pdo->prepare("INSERT INTO jogo_imagens (jogo_id, imagem, ordem) VALUES (?, ?, ?)")
                                ->execute([$jogo_id, "/uploads/jogos/$slug/screenshots/$fname", $k+1]);
                        }
                    }
                }
            }
        }

        // 8. Processar Tags/Categorias/Plataformas
        if (!empty($_POST['cats_selecionadas'])) {
            $ids = array_filter(explode(',', $_POST['cats_selecionadas']), 'is_numeric');
            $stmt = $pdo->prepare("INSERT INTO jogo_categoria (jogo_id, categoria_id) VALUES (?, ?)");
            foreach ($ids as $id) $stmt->execute([$jogo_id, (int)$id]);
        }

        if (!empty($_POST['tags_selecionadas'])) {
            $ids = array_filter(explode(',', $_POST['tags_selecionadas']), 'is_numeric');
            $stmt = $pdo->prepare("INSERT INTO jogo_tag (jogo_id, tag_id) VALUES (?, ?)");
            foreach ($ids as $id) $stmt->execute([$jogo_id, (int)$id]);
        }

        if (!empty($_POST['plats_selecionadas'])) {
            $ids = array_filter(explode(',', $_POST['plats_selecionadas']), 'is_numeric');
            $stmt = $pdo->prepare("INSERT INTO jogo_plataforma (jogo_id, plataforma_id) VALUES (?, ?)");
            foreach ($ids as $id) $stmt->execute([$jogo_id, (int)$id]);
        }

        $pdo->commit();
        $_SESSION['success'] = 'Rascunho criado com sucesso!';
        header('Location: ' . SITE_URL . '/developer/editar-jogo.php?id=' . $jogo_id);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Dados para os selects
$categorias = $pdo->query("SELECT * FROM categoria WHERE ativa = 1 ORDER BY nome")->fetchAll();
$tags = $pdo->query("SELECT * FROM tag ORDER BY nome")->fetchAll();
$plataformas = $pdo->query("SELECT * FROM plataforma WHERE ativa = 1 ORDER BY ordem")->fetchAll();

$page_title = 'Publicar Jogo - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<style>
    .dev-layout { display: grid; grid-template-columns: 260px 1fr; gap: 30px; padding: 30px 0; }
    .dev-content { min-width: 0; }
    .publish-wrapper { max-width: 1200px; margin: 0 auto; }
    .publish-grid { display: grid; grid-template-columns: 1fr 380px; gap: 30px; align-items: start; }
    
    .form-box { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 12px; padding: 25px; margin-bottom: 25px; }
    .box-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 20px; color: var(--accent); display: flex; align-items: center; gap: 10px; }
    
    /* Uploads */
    .upload-zone { position: relative; width: 100%; background: var(--bg-primary); border: 2px dashed var(--border); border-radius: 10px; cursor: pointer; display: flex; flex-direction: column; align-items: center; justify-content: center; transition: 0.3s; overflow: hidden; }
    .upload-zone:hover { border-color: var(--accent); background: rgba(var(--accent-rgb), 0.05); }
    .upload-zone.capa { aspect-ratio: 1/1; }
    .upload-zone.banner { aspect-ratio: 16/9; }
    .upload-zone.error { border-color: #ff4757 !important; background: rgba(255, 71, 87, 0.1) !important; }
    .upload-zone.success { border-color: #2ed573 !important; }
    .preview-img { width: 100%; height: 100%; object-fit: cover; position: absolute; top:0; left:0; display: none; }
    .remove-btn { position: absolute; top: 10px; right: 10px; background: #ff4757; color: white; border: none; padding: 5px 10px; border-radius: 6px; cursor: pointer; z-index: 10; display: none; font-size: 12px; }
    .upload-info { font-size: 11px; color: var(--text-secondary); margin-top: 8px; text-align: center; }
    .upload-error { color: #ff4757; font-size: 12px; margin-top: 5px; display: none; }
    .upload-placeholder { display: flex; flex-direction: column; align-items: center; gap: 8px; color: var(--text-secondary); }
    .upload-placeholder i { font-size: 2rem; }
    .upload-placeholder span { font-size: 12px; }
    
    /* Tags & Chips */
    .multi-select-wrapper { position: relative; }
    .chips-input { display: flex; flex-wrap: wrap; gap: 8px; padding: 10px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-primary); min-height: 45px; cursor: pointer; align-items: center; }
    .chips-input:hover { border-color: var(--accent); }
    .chips-placeholder { color: #888; padding: 5px; font-size: 14px; }
    .chip { background: var(--accent); color: white; padding: 5px 10px; border-radius: 20px; font-size: 13px; display: inline-flex; align-items: center; gap: 8px; }
    .chip-remove { cursor: pointer; font-size: 11px; opacity: 0.8; transition: 0.2s; background: none; border: none; color: white; padding: 0; line-height: 1; }
    .chip-remove:hover { opacity: 1; }
    .popover-list { display: none; position: absolute; top: 100%; left: 0; right: 0; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 8px; z-index: 100; max-height: 250px; overflow-y: auto; margin-top: 5px; box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
    .popover-list.open { display: block; }
    .popover-item { padding: 10px 15px; cursor: pointer; transition: 0.2s; font-size: 14px; display: flex; align-items: center; justify-content: space-between; }
    .popover-item:hover { background: var(--bg-primary); color: var(--accent); }
    .popover-item.selected { background: rgba(var(--accent-rgb), 0.1); color: var(--accent); }
    .popover-item.selected::after { content: '✓'; font-weight: bold; }

    /* Plataformas */
    .plat-grid { display: flex; gap: 10px; flex-wrap: wrap; }
    .plat-btn { padding: 8px 16px; border: 1px solid var(--border); border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.2s; background: var(--bg-primary); user-select: none; }
    .plat-btn:hover { border-color: var(--accent); }
    .plat-btn.active { background: var(--accent); color: white; border-color: var(--accent); }

    /* Screenshots */
    .shots-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; }
    .shot-item { aspect-ratio: 16/9; position: relative; border-radius: 6px; overflow: hidden; border: 1px solid var(--border); }
    .shot-item img { width: 100%; height: 100%; object-fit: cover; }
    .shot-remove { position: absolute; top: 5px; right: 5px; background: #ff4757; color: white; border: none; width: 22px; height: 22px; border-radius: 50%; cursor: pointer; font-size: 11px; display: flex; align-items: center; justify-content: center; }
    .add-shot { border: 2px dashed var(--border); display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; aspect-ratio: 16/9; border-radius: 6px; transition: 0.3s; gap: 5px; }
    .add-shot:hover { border-color: var(--accent); color: var(--accent); }
    .add-shot span { font-size: 10px; color: var(--text-secondary); }

    .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    .alert-danger { background: rgba(255, 71, 87, 0.1); border: 1px solid #ff4757; color: #ff4757; }

    @media(max-width: 992px) { 
        .dev-layout { grid-template-columns: 1fr; } 
        .publish-grid { grid-template-columns: 1fr; } 
    }
</style>

<div class="container">
    <div class="dev-layout">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <div class="dev-content">
            <div class="publish-wrapper">
                <div style="margin-bottom: 25px;">
                    <h1 style="font-size: 28px;"><i class="fas fa-magic"></i> Publicar Novo Jogo</h1>
                    <p style="color: var(--text-secondary);">Preencha os detalhes para criar seu rascunho.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= $error ?></div>
                <?php endif; ?>

                <form id="pubForm" method="POST" enctype="multipart/form-data">
                    <div class="publish-grid">
                        <div class="main-col">
                            <!-- Detalhes Básicos -->
                            <div class="form-box">
                                <div class="box-title"><i class="fas fa-info-circle"></i> Detalhes Básicos</div>
                                <div class="form-group">
                                    <label>Título do Jogo *</label>
                                    <input type="text" name="titulo" class="form-control" required placeholder="Ex: A Lenda do Herói">
                                </div>
                                <div class="form-group">
                                    <label>Descrição Curta * (150 caracteres)</label>
                                    <input type="text" name="descricao_curta" class="form-control" maxlength="150" required>
                                </div>
                                <div class="form-group">
                                    <label>Descrição Completa</label>
                                    <textarea name="descricao_completa" class="form-control" rows="6"></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Trailer (Embed YouTube)</label>
                                    <input type="url" name="video_trailer" class="form-control" placeholder="https://youtube.com/...">
                                </div>
                            </div>

                            <!-- Classificação -->
                            <div class="form-box">
                                <div class="box-title"><i class="fas fa-tags"></i> Classificação</div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                    <!-- Categorias -->
                                    <div class="multi-select-wrapper">
                                        <label>Categorias</label>
                                        <div class="chips-input" data-target="cat">
                                            <span class="chips-placeholder">Selecionar categorias...</span>
                                        </div>
                                        <div class="popover-list" id="popover-cat">
                                            <?php foreach($categorias as $c): ?>
                                                <div class="popover-item" data-id="<?= $c['id'] ?>" data-name="<?= htmlspecialchars($c['nome']) ?>">
                                                    <?= htmlspecialchars($c['nome']) ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <input type="hidden" name="cats_selecionadas" id="input-cat">
                                    </div>
                                    
                                    <!-- Tags -->
                                    <div class="multi-select-wrapper">
                                        <label>Tags</label>
                                        <div class="chips-input" data-target="tag">
                                            <span class="chips-placeholder">Selecionar tags...</span>
                                        </div>
                                        <div class="popover-list" id="popover-tag">
                                            <?php foreach($tags as $t): ?>
                                                <div class="popover-item" data-id="<?= $t['id'] ?>" data-name="<?= htmlspecialchars($t['nome']) ?>">
                                                    <?= htmlspecialchars($t['nome']) ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <input type="hidden" name="tags_selecionadas" id="input-tag">
                                    </div>
                                </div>
                            </div>

                            <!-- Requisitos e Plataformas -->
                            <div class="form-box">
                                <div class="box-title"><i class="fas fa-desktop"></i> Requisitos e Plataformas</div>
                                <label style="margin-bottom: 10px; display:block;">Plataformas Suportadas</label>
                                <div class="plat-grid" style="margin-bottom: 20px;">
                                    <?php foreach($plataformas as $p): ?>
                                        <div class="plat-btn" data-id="<?= $p['id'] ?>">
                                            <i class="<?= $p['icone'] ?>"></i> <?= htmlspecialchars($p['nome']) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" name="plats_selecionadas" id="input-plat">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                    <div>
                                        <label>Requisitos Mínimos</label>
                                        <textarea name="requisitos_minimos" class="form-control" rows="4" placeholder="Mínimos..."></textarea>
                                    </div>
                                    <div>
                                        <label>Requisitos Recomendados</label>
                                        <textarea name="requisitos_recomendados" class="form-control" rows="4" placeholder="Recomendados..."></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Screenshots -->
                            <div class="form-box">
                                <div class="box-title"><i class="fas fa-images"></i> Screenshots</div>
                                <p class="upload-info" style="text-align:left; margin-bottom:15px;">
                                    <i class="fas fa-info-circle"></i> Formatos: JPG, PNG, WebP • Imagens serão otimizadas automaticamente
                                </p>
                                <div class="shots-grid" id="shotContainer">
                                    <label class="add-shot" id="addShotBtn">
                                        <i class="fas fa-plus fa-lg"></i>
                                        <span>Adicionar</span>
                                    </label>
                                </div>
                                <input type="file" name="screenshots[]" multiple accept="image/jpeg,image/png,image/webp" style="display:none" id="shotInput">
                            </div>
                        </div>

                        <div class="side-col">
                            <!-- Mídia Principal -->
                            <div class="form-box">
                                <div class="box-title"><i class="fas fa-image"></i> Mídia Principal</div>
                                
                                <!-- Capa -->
                                <label>Capa (1:1)</label>
                                <div class="upload-zone capa" id="zone-capa">
                                    <img id="prev-capa" class="preview-img">
                                    <button type="button" class="remove-btn" id="remove-capa">Remover</button>
                                    <div class="upload-placeholder" id="place-capa">
                                        <i class="fas fa-upload"></i>
                                        <span>Clique para enviar</span>
                                    </div>
                                </div>
                                <input type="file" name="capa" id="input-file-capa" style="display:none" accept="image/jpeg,image/png,image/webp">
                                <p class="upload-info">JPG, PNG, WebP • Será otimizada automaticamente</p>
                                <div class="upload-error" id="error-capa"></div>

                                <!-- Banner -->
                                <label style="margin-top: 20px; display:block;">Banner (16:9)</label>
                                <div class="upload-zone banner" id="zone-banner">
                                    <img id="prev-banner" class="preview-img">
                                    <button type="button" class="remove-btn" id="remove-banner">Remover</button>
                                    <div class="upload-placeholder" id="place-banner">
                                        <i class="fas fa-panorama"></i>
                                        <span>Clique para enviar</span>
                                    </div>
                                </div>
                                <input type="file" name="banner" id="input-file-banner" style="display:none" accept="image/jpeg,image/png,image/webp">
                                <p class="upload-info">JPG, PNG, WebP • Será otimizada automaticamente</p>
                                <div class="upload-error" id="error-banner"></div>
                            </div>

                            <!-- Venda -->
                            <div class="form-box">
                                <div class="box-title"><i class="fas fa-dollar-sign"></i> Venda</div>
                                <div class="form-group">
                                    <label>Preço (R$)</label>
                                    <input type="number" name="preco" class="form-control" step="0.01" min="0" placeholder="0.00">
                                </div>
                                <div class="form-group">
                                    <label>Classificação Indicativa</label>
                                    <select name="classificacao" class="form-control">
                                        <option value="L">Livre</option>
                                        <option value="10">10+</option>
                                        <option value="12">12+</option>
                                        <option value="14">14+</option>
                                        <option value="16">16+</option>
                                        <option value="18">18+</option>
                                    </select>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg" style="width:100%" id="submitBtn">
                                <i class="fas fa-save"></i> Salvar Rascunho
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

    // ===========================================
    // VALIDAÇÃO SIMPLES DE TIPO
    // ===========================================
    function isValidType(file) {
        return allowedTypes.includes(file.type);
    }

    // ===========================================
    // UPLOAD DE CAPA E BANNER
    // ===========================================
    function setupImageUpload(type) {
        const zone = document.getElementById(`zone-${type}`);
        const input = document.getElementById(`input-file-${type}`);
        const preview = document.getElementById(`prev-${type}`);
        const placeholder = document.getElementById(`place-${type}`);
        const removeBtn = document.getElementById(`remove-${type}`);
        const errorDiv = document.getElementById(`error-${type}`);
        
        zone.addEventListener('click', function(e) {
            if (e.target !== removeBtn && !removeBtn.contains(e.target)) {
                input.click();
            }
        });
        
        input.addEventListener('change', function() {
            const file = this.files[0];
            if (!file) return;
            
            if (!isValidType(file)) {
                zone.classList.add('error');
                zone.classList.remove('success');
                errorDiv.textContent = 'Formato inválido. Use JPG, PNG ou WebP.';
                errorDiv.style.display = 'block';
                this.value = '';
                return;
            }
            
            zone.classList.remove('error');
            zone.classList.add('success');
            errorDiv.style.display = 'none';
            
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
                placeholder.style.display = 'none';
                removeBtn.style.display = 'block';
            };
            reader.readAsDataURL(file);
        });
        
        removeBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            input.value = '';
            preview.style.display = 'none';
            preview.src = '';
            placeholder.style.display = 'flex';
            removeBtn.style.display = 'none';
            zone.classList.remove('success', 'error');
            errorDiv.style.display = 'none';
        });
    }
    
    setupImageUpload('capa');
    setupImageUpload('banner');

    // ===========================================
    // SCREENSHOTS
    // ===========================================
    const shotInput = document.getElementById('shotInput');
    const shotContainer = document.getElementById('shotContainer');
    const addShotBtn = document.getElementById('addShotBtn');
    let screenshotFiles = new DataTransfer();
    
    addShotBtn.addEventListener('click', () => shotInput.click());
    
    shotInput.addEventListener('change', function() {
        Array.from(this.files).forEach((file) => {
            if (!isValidType(file)) return;
            
            screenshotFiles.items.add(file);
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const div = document.createElement('div');
                div.className = 'shot-item';
                div.innerHTML = `
                    <img src="${e.target.result}">
                    <button type="button" class="shot-remove" data-index="${screenshotFiles.files.length - 1}">×</button>
                `;
                shotContainer.insertBefore(div, addShotBtn);
            };
            reader.readAsDataURL(file);
        });
        
        shotInput.files = screenshotFiles.files;
    });
    
    shotContainer.addEventListener('click', function(e) {
        if (e.target.classList.contains('shot-remove')) {
            const item = e.target.closest('.shot-item');
            const index = parseInt(e.target.dataset.index);
            
            const newDT = new DataTransfer();
            Array.from(screenshotFiles.files).forEach((file, i) => {
                if (i !== index) newDT.items.add(file);
            });
            screenshotFiles = newDT;
            shotInput.files = screenshotFiles.files;
            
            item.remove();
            
            document.querySelectorAll('.shot-remove').forEach((btn, i) => {
                btn.dataset.index = i;
            });
        }
    });

    // ===========================================
    // MULTISELECT (TAGS/CATEGORIAS)
    // ===========================================
    function initMultiSelect(type) {
        const wrapper = document.querySelector(`.chips-input[data-target="${type}"]`).closest('.multi-select-wrapper');
        const chipsContainer = wrapper.querySelector('.chips-input');
        const popover = document.getElementById(`popover-${type}`);
        const hiddenInput = document.getElementById(`input-${type}`);
        const cacheKey = `draft_${type}s`;
        
        let selected = new Map();
        
        const cached = localStorage.getItem(cacheKey);
        if (cached && cached.length > 0) {
            cached.split(',').forEach(id => {
                const item = popover.querySelector(`[data-id="${id}"]`);
                if (item) {
                    selected.set(id, item.dataset.name);
                }
            });
            render();
        }
        
        chipsContainer.addEventListener('click', function(e) {
            if (e.target.classList.contains('chip-remove')) return;
            
            e.stopPropagation();
            
            document.querySelectorAll('.popover-list.open').forEach(p => {
                if (p !== popover) p.classList.remove('open');
            });
            
            popover.classList.toggle('open');
        });
        
        popover.addEventListener('click', function(e) {
            const item = e.target.closest('.popover-item');
            if (!item) return;
            
            e.stopPropagation();
            
            const id = item.dataset.id;
            const name = item.dataset.name;
            
            if (selected.has(id)) {
                selected.delete(id);
            } else {
                selected.set(id, name);
            }
            
            render();
        });
        
        chipsContainer.addEventListener('click', function(e) {
            if (e.target.classList.contains('chip-remove')) {
                e.stopPropagation();
                const id = e.target.dataset.id;
                selected.delete(id);
                render();
            }
        });
        
        function render() {
            chipsContainer.innerHTML = '';
            
            if (selected.size === 0) {
                chipsContainer.innerHTML = `<span class="chips-placeholder">Selecionar ${type === 'cat' ? 'categorias' : 'tags'}...</span>`;
            } else {
                selected.forEach((name, id) => {
                    const chip = document.createElement('div');
                    chip.className = 'chip';
                    chip.innerHTML = `
                        ${name}
                        <button type="button" class="chip-remove" data-id="${id}">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    chipsContainer.appendChild(chip);
                });
            }
            
            hiddenInput.value = Array.from(selected.keys()).join(',');
            localStorage.setItem(cacheKey, hiddenInput.value);
            
            popover.querySelectorAll('.popover-item').forEach(item => {
                item.classList.toggle('selected', selected.has(item.dataset.id));
            });
        }
    }
    
    initMultiSelect('cat');
    initMultiSelect('tag');
    
    document.addEventListener('click', function() {
        document.querySelectorAll('.popover-list.open').forEach(p => p.classList.remove('open'));
    });

    // ===========================================
    // PLATAFORMAS
    // ===========================================
    const platBtns = document.querySelectorAll('.plat-btn');
    const platInput = document.getElementById('input-plat');
    let selectedPlats = new Set();
    
    const cachedPlats = localStorage.getItem('draft_plats');
    if (cachedPlats) {
        cachedPlats.split(',').forEach(id => {
            if (id) selectedPlats.add(id);
        });
        updatePlats();
    }
    
    platBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            if (selectedPlats.has(id)) {
                selectedPlats.delete(id);
            } else {
                selectedPlats.add(id);
            }
            updatePlats();
        });
    });
    
    function updatePlats() {
        platBtns.forEach(btn => {
            btn.classList.toggle('active', selectedPlats.has(btn.dataset.id));
        });
        platInput.value = Array.from(selectedPlats).join(',');
        localStorage.setItem('draft_plats', platInput.value);
    }

    // ===========================================
    // AUTO-SAVE CAMPOS DE TEXTO
    // ===========================================
    document.querySelectorAll('input[type=text], textarea, input[type=number], input[type=url], select').forEach(el => {
        if (el.name) {
            const cached = localStorage.getItem('draft_' + el.name);
            if (cached) el.value = cached;
            
            el.addEventListener('input', function() {
                localStorage.setItem('draft_' + el.name, el.value);
            });
            el.addEventListener('change', function() {
                localStorage.setItem('draft_' + el.name, el.value);
            });
        }
    });

    // ===========================================
    // LIMPAR CACHE AO SUBMETER
    // ===========================================
    document.getElementById('pubForm').addEventListener('submit', function() {
        const keysToRemove = [];
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key && key.startsWith('draft_')) {
                keysToRemove.push(key);
            }
        }
        keysToRemove.forEach(key => localStorage.removeItem(key));
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>