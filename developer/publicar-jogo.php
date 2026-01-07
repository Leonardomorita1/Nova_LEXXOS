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

$success = '';
$error = '';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Dados básicos
        $titulo = trim($_POST['titulo']);
        $descricao_curta = trim($_POST['descricao_curta']);
        $descricao_completa = trim($_POST['descricao_completa']);
        $preco_centavos = (int)($_POST['preco'] * 100);
        $classificacao = $_POST['classificacao'];
        $video_trailer = trim($_POST['video_trailer']);
        $requisitos_minimos = trim($_POST['requisitos_minimos']);
        $requisitos_recomendados = trim($_POST['requisitos_recomendados']);
        
        // Validações
        if (empty($titulo) || empty($descricao_curta)) {
            throw new Exception('Preencha todos os campos obrigatórios');
        }
        
        // Criar slug
        $slug = generateSlug($titulo);
        
        // Verificar slug único
        $stmt = $pdo->prepare("SELECT id FROM jogo WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetch()) {
            $slug = $slug . '-' . uniqid();
        }
        
        // Diretório de uploads
        $upload_dir = '../uploads/jogos/' . $slug;
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Upload da capa (PROPORÇÃO 3:4 sugerida - ex: 600x800px)
        $capa_path = null;
        if (isset($_FILES['capa']) && $_FILES['capa']['error'] == 0) {
            $ext = pathinfo($_FILES['capa']['name'], PATHINFO_EXTENSION);
            $capa_filename = 'capa.' . $ext;
            $capa_path = $upload_dir . '/' . $capa_filename;
            
            if (!move_uploaded_file($_FILES['capa']['tmp_name'], $capa_path)) {
                throw new Exception('Erro ao fazer upload da capa');
            }
            $capa_path = '/uploads/jogos/' . $slug . '/' . $capa_filename;
        }
        
        // Upload do banner (PROPORÇÃO 16:9 sugerida - ex: 1920x1080px)
        $banner_path = null;
        if (isset($_FILES['banner']) && $_FILES['banner']['error'] == 0) {
            $ext = pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION);
            $banner_filename = 'banner.' . $ext;
            $banner_path = $upload_dir . '/' . $banner_filename;
            
            if (!move_uploaded_file($_FILES['banner']['tmp_name'], $banner_path)) {
                throw new Exception('Erro ao fazer upload do banner');
            }
            $banner_path = '/uploads/jogos/' . $slug . '/' . $banner_filename;
        }
        
        // Inserir jogo
        $stmt = $pdo->prepare("
            INSERT INTO jogo (
                desenvolvedor_id, titulo, slug, descricao_curta, descricao_completa,
                preco_centavos, imagem_capa, imagem_banner, video_trailer,
                requisitos_minimos, requisitos_recomendados, classificacao_etaria,
                status, criado_em
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'rascunho', NOW())
        ");
        
        $stmt->execute([
            $dev['id'], $titulo, $slug, $descricao_curta, $descricao_completa,
            $preco_centavos, $capa_path, $banner_path, $video_trailer,
            $requisitos_minimos, $requisitos_recomendados, $classificacao
        ]);
        
        $jogo_id = $pdo->lastInsertId();
        
        // Upload de screenshots/imagens (PROPORÇÃO 16:9 sugerida - ex: 1920x1080px)
        if (isset($_FILES['screenshots']) && !empty($_FILES['screenshots']['name'][0])) {
            $screenshots_dir = $upload_dir . '/screenshots';
            if (!file_exists($screenshots_dir)) {
                mkdir($screenshots_dir, 0755, true);
            }
            
            $ordem = 1;
            foreach ($_FILES['screenshots']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['screenshots']['error'][$key] == 0) {
                    $ext = pathinfo($_FILES['screenshots']['name'][$key], PATHINFO_EXTENSION);
                    $filename = time() . '-' . $ordem . '.' . $ext;
                    $file_path = $screenshots_dir . '/' . $filename;
                    
                    if (move_uploaded_file($tmp_name, $file_path)) {
                        $db_path = '/uploads/jogos/' . $slug . '/screenshots/' . $filename;
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO jogo_imagens (jogo_id, imagem, ordem)
                            VALUES (?, ?, ?)
                        ");
                        $stmt->execute([$jogo_id, $db_path, $ordem]);
                        $ordem++;
                    }
                }
            }
        }
        
        // Adicionar categorias
        if (isset($_POST['categorias']) && is_array($_POST['categorias'])) {
            foreach ($_POST['categorias'] as $cat_id) {
                $stmt = $pdo->prepare("INSERT INTO jogo_categoria (jogo_id, categoria_id) VALUES (?, ?)");
                $stmt->execute([$jogo_id, $cat_id]);
            }
        }
        
        // Adicionar tags
        if (isset($_POST['tags']) && is_array($_POST['tags'])) {
            foreach ($_POST['tags'] as $tag_id) {
                $stmt = $pdo->prepare("INSERT INTO jogo_tag (jogo_id, tag_id) VALUES (?, ?)");
                $stmt->execute([$jogo_id, $tag_id]);
            }
        }
        
        // Adicionar plataformas
        if (isset($_POST['plataformas']) && is_array($_POST['plataformas'])) {
            foreach ($_POST['plataformas'] as $plat_id) {
                $stmt = $pdo->prepare("INSERT INTO jogo_plataforma (jogo_id, plataforma_id) VALUES (?, ?)");
                $stmt->execute([$jogo_id, $plat_id]);
            }
        }
        
        $pdo->commit();
        
        $_SESSION['success'] = 'Jogo criado com sucesso! Você pode editá-lo ou enviar para revisão.';
        header('Location: ' . SITE_URL . '/developer/editar-jogo.php?id=' . $jogo_id);
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Buscar categorias, tags e plataformas
$categorias = $pdo->query("SELECT * FROM categoria WHERE ativa = 1 ORDER BY nome")->fetchAll();
$tags = $pdo->query("SELECT * FROM tag ORDER BY nome")->fetchAll();
$plataformas = $pdo->query("SELECT * FROM plataforma WHERE ativa = 1 ORDER BY ordem")->fetchAll();

$page_title = 'Publicar Novo Jogo - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<style>
.publish-page {
    padding: 30px 0;
    max-width: 1000px;
    margin: 0 auto;
}

.form-section {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 30px;
}

.form-section h2 {
    font-size: 22px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-section h2 i {
    color: var(--accent);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.checkbox-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-top: 10px;
}

.checkbox-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background: var(--bg-primary);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s;
}

.checkbox-item:hover {
    background: var(--accent);
    color: white;
}

.checkbox-item input {
    cursor: pointer;
}

.upload-area {
    border: 2px dashed var(--border);
    border-radius: 10px;
    padding: 30px;
    text-align: center;
    transition: all 0.3s;
    cursor: pointer;
}

.upload-area:hover {
    border-color: var(--accent);
    background: rgba(76,139,245,0.05);
}

.upload-area i {
    font-size: 48px;
    color: var(--accent);
    margin-bottom: 15px;
}

.upload-area input[type="file"] {
    display: none;
}

.upload-hint {
    font-size: 13px;
    color: var(--text-secondary);
    margin-top: 10px;
}

.proportion-hint {
    background: rgba(76,139,245,0.1);
    border: 1px solid var(--accent);
    border-radius: 6px;
    padding: 10px;
    font-size: 13px;
    margin-top: 10px;
}

.screenshots-preview {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-top: 15px;
}

.screenshot-preview {
    position: relative;
    aspect-ratio: 16/9;
    border-radius: 8px;
    overflow: hidden;
    background: var(--bg-primary);
}

.screenshot-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.remove-screenshot {
    position: absolute;
    top: 5px;
    right: 5px;
    background: var(--danger);
    color: white;
    border: none;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .checkbox-grid {
        grid-template-columns: 1fr;
    }
    
    .screenshots-preview {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="container">
    <div class="dev-layout">
        <?php require_once 'includes/sidebar.php'; ?>
        <div style="margin-bottom: 30px;">
            <h1 style="font-size: 32px; margin-bottom: 10px;">
                <i class="fas fa-plus-circle"></i> Publicar Novo Jogo
            </h1>
            <p style="color: var(--text-secondary);">
                Preencha as informações do seu jogo
            </p>
        </div>
        
        <?php if ($error): ?>
            <div style="background: rgba(220,53,69,0.1); border: 1px solid var(--danger); color: var(--danger); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data" >
            <!-- Informações Básicas -->
            <div class="form-section">
                <h2><i class="fas fa-info-circle"></i> Informações Básicas</h2>
                
                <div class="form-group">
                    <label class="form-label">Título do Jogo *</label>
                    <input type="text" name="titulo" class="form-control" required 
                           placeholder="Ex: Meu Jogo Incrível">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Descrição Curta * (máx. 500 caracteres)</label>
                    <input type="text" name="descricao_curta" class="form-control" required 
                           maxlength="500"
                           placeholder="Uma breve descrição que aparecerá nos cards">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Descrição Completa *</label>
                    <textarea name="descricao_completa" class="form-control" rows="8" required
                              placeholder="Descreva seu jogo em detalhes..."></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Preço (R$) *</label>
                        <input type="number" name="preco" class="form-control" required 
                               min="0" step="0.01" value="0"
                               placeholder="0.00">
                        <small style="color: var(--text-secondary);">Use 0 para jogos gratuitos</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Classificação Etária *</label>
                        <select name="classificacao" class="form-control" required>
                            <option value="L">Livre</option>
                            <option value="10">10 anos</option>
                            <option value="12">12 anos</option>
                            <option value="14">14 anos</option>
                            <option value="16">16 anos</option>
                            <option value="18">18 anos</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Mídia -->
            <div class="form-section">
                <h2><i class="fas fa-images"></i> Mídia do Jogo</h2>
                
                <div class="form-group">
                    <label class="form-label">Capa do Jogo * (Aparece nos cards)</label>
                    <div class="upload-area" onclick="document.getElementById('capa').click()">
                        <i class="fas fa-image"></i>
                        <p>Clique para selecionar a capa</p>
                        <input type="file" id="capa" name="capa" accept="image/*" required>
                    </div>
                    <div class="proportion-hint">
                        <i class="fas fa-info-circle"></i> <strong>Proporção sugerida: 3:4</strong> 
                        (ex: 600x800px, 900x1200px)
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Banner do Jogo (Aparece na página de detalhes)</label>
                    <div class="upload-area" onclick="document.getElementById('banner').click()">
                        <i class="fas fa-panorama"></i>
                        <p>Clique para selecionar o banner</p>
                        <input type="file" id="banner" name="banner" accept="image/*">
                    </div>
                    <div class="proportion-hint">
                        <i class="fas fa-info-circle"></i> <strong>Proporção sugerida: 16:9</strong> 
                        (ex: 1920x1080px, 1280x720px)
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Screenshots do Jogo (Até 10 imagens)</label>
                    <div class="upload-area" onclick="document.getElementById('screenshots').click()">
                        <i class="fas fa-images"></i>
                        <p>Clique para selecionar screenshots</p>
                        <input type="file" id="screenshots" name="screenshots[]" accept="image/*" multiple>
                    </div>
                    <div class="proportion-hint">
                        <i class="fas fa-info-circle"></i> <strong>Proporção sugerida: 16:9</strong> 
                        (ex: 1920x1080px) - Aparecerão no carrossel
                    </div>
                    <div id="screenshotsPreview" class="screenshots-preview"></div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Trailer do YouTube (URL embed)</label>
                    <input type="url" name="video_trailer" class="form-control"
                           placeholder="https://www.youtube.com/embed/VIDEO_ID">
                    <small style="color: var(--text-secondary);">
                        Cole o link de incorporação do YouTube. 
                        Será o primeiro item do carrossel se fornecido.
                    </small>
                </div>
            </div>
            
            <!-- Categorias e Tags -->
            <div class="form-section">
                <h2><i class="fas fa-th"></i> Categorias e Tags</h2>
                
                <div class="form-group">
                    <label class="form-label">Categorias *</label>
                    <div class="checkbox-grid">
                        <?php foreach ($categorias as $cat): ?>
                        <label class="checkbox-item">
                            <input type="checkbox" name="categorias[]" value="<?php echo $cat['id']; ?>">
                            <i class="fas fa-<?php echo $cat['icone']; ?>"></i>
                            <?php echo sanitize($cat['nome']); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Tags</label>
                    <div class="checkbox-grid">
                        <?php foreach ($tags as $tag): ?>
                        <label class="checkbox-item">
                            <input type="checkbox" name="tags[]" value="<?php echo $tag['id']; ?>">
                            <?php echo sanitize($tag['nome']); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Plataformas -->
            <div class="form-section">
                <h2><i class="fas fa-laptop"></i> Plataformas *</h2>
                
                <div class="checkbox-grid">
                    <?php foreach ($plataformas as $plat): ?>
                    <label class="checkbox-item">
                        <input type="checkbox" name="plataformas[]" value="<?php echo $plat['id']; ?>">
                        <i class="<?php echo $plat['icone']; ?>"></i>
                        <?php echo sanitize($plat['nome']); ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Requisitos -->
            <div class="form-section">
                <h2><i class="fas fa-desktop"></i> Requisitos do Sistema</h2>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Requisitos Mínimos</label>
                        <textarea name="requisitos_minimos" class="form-control" rows="8"
                                  placeholder="SO: Windows 10&#10;Processador: Intel i3&#10;Memória: 4 GB RAM&#10;Placa de vídeo: GTX 750"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Requisitos Recomendados</label>
                        <textarea name="requisitos_recomendados" class="form-control" rows="8"
                                  placeholder="SO: Windows 11&#10;Processador: Intel i5&#10;Memória: 8 GB RAM&#10;Placa de vídeo: GTX 1060"></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Ações -->
            <div style="display: flex; gap: 15px;">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> Salvar como Rascunho
                </button>
                
                <a href="<?php echo SITE_URL; ?>/developer/jogos.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<script>
// Preview de screenshots
document.getElementById('screenshots').addEventListener('change', function(e) {
    const preview = document.getElementById('screenshotsPreview');
    preview.innerHTML = '';
    
    const files = Array.from(e.target.files).slice(0, 10);
    
    files.forEach((file, index) => {
        const reader = new FileReader();
        reader.onload = function(e) {
            const div = document.createElement('div');
            div.className = 'screenshot-preview';
            div.innerHTML = `
                <img src="${e.target.result}" alt="Screenshot ${index + 1}">
                <button type="button" class="remove-screenshot" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;
            preview.appendChild(div);
        };
        reader.readAsDataURL(file);
    });
});

// Hover effect nos checkboxes
document.querySelectorAll('.checkbox-item').forEach(item => {
    item.addEventListener('click', function() {
        const checkbox = this.querySelector('input[type="checkbox"]');
        checkbox.checked = !checkbox.checked;
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>