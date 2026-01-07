<?php
// developer/editar-jogo.php
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

$jogo_id = $_GET['id'] ?? null;
if (!$jogo_id) {
    header('Location: ' . SITE_URL . '/developer/jogos.php');
    exit;
}

// Buscar jogo e verificar propriedade
$stmt = $pdo->prepare("SELECT * FROM jogo WHERE id = ? AND desenvolvedor_id = ?");
$stmt->execute([$jogo_id, $dev['id']]);
$jogo = $stmt->fetch();

if (!$jogo) {
    $_SESSION['error'] = 'Jogo não encontrado';
    header('Location: ' . SITE_URL . '/developer/jogos.php');
    exit;
}

$success = $_SESSION['success'] ?? '';
$error = '';
unset($_SESSION['success']);

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();
        
        $action = $_POST['action'] ?? 'save';
        
        // Dados básicos
        $titulo = trim($_POST['titulo']);
        $descricao_curta = trim($_POST['descricao_curta']);
        $descricao_completa = trim($_POST['descricao_completa']);
        $preco_centavos = (int)($_POST['preco'] * 100);
        $classificacao = $_POST['classificacao'];
        $video_trailer = trim($_POST['video_trailer']);
        $requisitos_minimos = trim($_POST['requisitos_minimos']);
        $requisitos_recomendados = trim($_POST['requisitos_recomendados']);
        
        if (empty($titulo) || empty($descricao_curta)) {
            throw new Exception('Preencha todos os campos obrigatórios');
        }
        
        $upload_dir = '../uploads/jogos/' . $jogo['slug'];
        
        // Upload da capa
        $capa_path = $jogo['imagem_capa'];
        if (isset($_FILES['capa']) && $_FILES['capa']['error'] == 0) {
            $ext = pathinfo($_FILES['capa']['name'], PATHINFO_EXTENSION);
            $capa_filename = 'capa.' . $ext;
            $new_capa = $upload_dir . '/' . $capa_filename;
            
            if (move_uploaded_file($_FILES['capa']['tmp_name'], $new_capa)) {
                // Remover antiga se existir
                if ($capa_path && file_exists('..' . $capa_path)) {
                    unlink('..' . $capa_path);
                }
                $capa_path = '/uploads/jogos/' . $jogo['slug'] . '/' . $capa_filename;
            }
        }
        
        // Upload do banner
        $banner_path = $jogo['imagem_banner'];
        if (isset($_FILES['banner']) && $_FILES['banner']['error'] == 0) {
            $ext = pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION);
            $banner_filename = 'banner.' . $ext;
            $new_banner = $upload_dir . '/' . $banner_filename;
            
            if (move_uploaded_file($_FILES['banner']['tmp_name'], $new_banner)) {
                if ($banner_path && file_exists('..' . $banner_path)) {
                    unlink('..' . $banner_path);
                }
                $banner_path = '/uploads/jogos/' . $jogo['slug'] . '/' . $banner_filename;
            }
        }
        
        // Atualizar jogo
        $stmt = $pdo->prepare("
            UPDATE jogo SET
                titulo = ?, descricao_curta = ?, descricao_completa = ?,
                preco_centavos = ?, imagem_capa = ?, imagem_banner = ?,
                video_trailer = ?, requisitos_minimos = ?, requisitos_recomendados = ?,
                classificacao_etaria = ?, atualizado_em = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $titulo, $descricao_curta, $descricao_completa,
            $preco_centavos, $capa_path, $banner_path,
            $video_trailer, $requisitos_minimos, $requisitos_recomendados,
            $classificacao, $jogo_id
        ]);
        
        // Remover screenshots marcadas
        if (isset($_POST['remove_screenshots']) && is_array($_POST['remove_screenshots'])) {
            foreach ($_POST['remove_screenshots'] as $img_id) {
                $stmt = $pdo->prepare("SELECT imagem FROM jogo_imagens WHERE id = ? AND jogo_id = ?");
                $stmt->execute([$img_id, $jogo_id]);
                $img = $stmt->fetch();
                
                if ($img) {
                    if (file_exists('..' . $img['imagem'])) {
                        unlink('..' . $img['imagem']);
                    }
                    $stmt = $pdo->prepare("DELETE FROM jogo_imagens WHERE id = ?");
                    $stmt->execute([$img_id]);
                }
            }
        }
        
        // Upload de novas screenshots
        if (isset($_FILES['screenshots']) && !empty($_FILES['screenshots']['name'][0])) {
            $screenshots_dir = $upload_dir . '/screenshots';
            if (!file_exists($screenshots_dir)) {
                mkdir($screenshots_dir, 0755, true);
            }
            
            // Buscar próxima ordem
            $stmt = $pdo->prepare("SELECT MAX(ordem) as max_ordem FROM jogo_imagens WHERE jogo_id = ?");
            $stmt->execute([$jogo_id]);
            $ordem = ($stmt->fetch()['max_ordem'] ?? 0) + 1;
            
            foreach ($_FILES['screenshots']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['screenshots']['error'][$key] == 0) {
                    $ext = pathinfo($_FILES['screenshots']['name'][$key], PATHINFO_EXTENSION);
                    $filename = time() . '-' . $ordem . '.' . $ext;
                    $file_path = $screenshots_dir . '/' . $filename;
                    
                    if (move_uploaded_file($tmp_name, $file_path)) {
                        $db_path = '/uploads/jogos/' . $jogo['slug'] . '/screenshots/' . $filename;
                        $stmt = $pdo->prepare("INSERT INTO jogo_imagens (jogo_id, imagem, ordem) VALUES (?, ?, ?)");
                        $stmt->execute([$jogo_id, $db_path, $ordem]);
                        $ordem++;
                    }
                }
            }
        }
        
        // Atualizar categorias
        $stmt = $pdo->prepare("DELETE FROM jogo_categoria WHERE jogo_id = ?");
        $stmt->execute([$jogo_id]);
        
        if (isset($_POST['categorias']) && is_array($_POST['categorias'])) {
            foreach ($_POST['categorias'] as $cat_id) {
                $stmt = $pdo->prepare("INSERT INTO jogo_categoria (jogo_id, categoria_id) VALUES (?, ?)");
                $stmt->execute([$jogo_id, $cat_id]);
            }
        }
        
        // Atualizar tags
        $stmt = $pdo->prepare("DELETE FROM jogo_tag WHERE jogo_id = ?");
        $stmt->execute([$jogo_id]);
        
        if (isset($_POST['tags']) && is_array($_POST['tags'])) {
            foreach ($_POST['tags'] as $tag_id) {
                $stmt = $pdo->prepare("INSERT INTO jogo_tag (jogo_id, tag_id) VALUES (?, ?)");
                $stmt->execute([$jogo_id, $tag_id]);
            }
        }
        
        // Atualizar plataformas
        $stmt = $pdo->prepare("DELETE FROM jogo_plataforma WHERE jogo_id = ?");
        $stmt->execute([$jogo_id]);
        
        if (isset($_POST['plataformas']) && is_array($_POST['plataformas'])) {
            foreach ($_POST['plataformas'] as $plat_id) {
                $stmt = $pdo->prepare("INSERT INTO jogo_plataforma (jogo_id, plataforma_id) VALUES (?, ?)");
                $stmt->execute([$jogo_id, $plat_id]);
            }
        }
        
        // Se ação for "publish", mudar status para em_revisao
        if ($action == 'publish' && $jogo['status'] == 'rascunho') {
            $stmt = $pdo->prepare("UPDATE jogo SET status = 'em_revisao' WHERE id = ?");
            $stmt->execute([$jogo_id]);
            $success = 'Jogo enviado para revisão!';
        } else {
            $success = 'Jogo atualizado com sucesso!';
        }
        
        $pdo->commit();
        
        // Recarregar jogo
        $stmt = $pdo->prepare("SELECT * FROM jogo WHERE id = ?");
        $stmt->execute([$jogo_id]);
        $jogo = $stmt->fetch();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Buscar dados relacionados
$stmt = $pdo->prepare("SELECT categoria_id FROM jogo_categoria WHERE jogo_id = ?");
$stmt->execute([$jogo_id]);
$cats_selecionadas = array_column($stmt->fetchAll(), 'categoria_id');

$stmt = $pdo->prepare("SELECT tag_id FROM jogo_tag WHERE jogo_id = ?");
$stmt->execute([$jogo_id]);
$tags_selecionadas = array_column($stmt->fetchAll(), 'tag_id');

$stmt = $pdo->prepare("SELECT plataforma_id FROM jogo_plataforma WHERE jogo_id = ?");
$stmt->execute([$jogo_id]);
$plats_selecionadas = array_column($stmt->fetchAll(), 'plataforma_id');

$stmt = $pdo->prepare("SELECT * FROM jogo_imagens WHERE jogo_id = ? ORDER BY ordem");
$stmt->execute([$jogo_id]);
$imagens_existentes = $stmt->fetchAll();

$categorias = $pdo->query("SELECT * FROM categoria WHERE ativa = 1 ORDER BY nome")->fetchAll();
$tags = $pdo->query("SELECT * FROM tag ORDER BY nome")->fetchAll();
$plataformas = $pdo->query("SELECT * FROM plataforma WHERE ativa = 1 ORDER BY ordem")->fetchAll();

$page_title = 'Editar: ' . $jogo['titulo'] . ' - ' . SITE_NAME;
require_once '../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/main.css">
<style>
.edit-page {
    padding: 30px 0;
    max-width: 1000px;
    margin: 0 auto;
}

.status-bar {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.status-badge {
    padding: 8px 16px;
    border-radius: 6px;
    font-weight: 600;
}

.existing-screenshots {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.screenshot-item {
    position: relative;
    aspect-ratio: 16/9;
    border-radius: 8px;
    overflow: hidden;
    border: 2px solid var(--border);
}

.screenshot-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.screenshot-checkbox {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 30px;
    height: 30px;
    cursor: pointer;
}

.screenshot-order {
    position: absolute;
    bottom: 10px;
    left: 10px;
    background: rgba(0,0,0,0.7);
    color: white;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 12px;
}

/* Estilos do publicar-jogo.php */
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

.proportion-hint {
    background: rgba(76,139,245,0.1);
    border: 1px solid var(--accent);
    border-radius: 6px;
    padding: 10px;
    font-size: 13px;
    margin-top: 10px;
}

@media (max-width: 768px) {
    .form-row, .checkbox-grid, .existing-screenshots {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="container">
    <div class="edit-page">
        <div style="margin-bottom: 30px;">
            <h1 style="font-size: 32px; margin-bottom: 10px;">
                <i class="fas fa-edit"></i> Editar Jogo
            </h1>
            <p style="color: var(--text-secondary);"><?php echo sanitize($jogo['titulo']); ?></p>
        </div>
        
        <!-- Status Bar -->
        <div class="status-bar">
            <div>
                <strong>Status:</strong>
                <span class="status-badge status-<?php echo $jogo['status']; ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $jogo['status'])); ?>
                </span>
            </div>
            <div>
                <a href="<?php echo SITE_URL; ?>/pages/jogo.php?slug=<?php echo $jogo['slug']; ?>" 
                   class="btn btn-secondary" target="_blank">
                    <i class="fas fa-eye"></i> Ver Página
                </a>
            </div>
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
        
        <form method="POST" enctype="multipart/form-data">
            <!-- Informações Básicas -->
            <div class="form-section">
                <h2><i class="fas fa-info-circle"></i> Informações Básicas</h2>
                
                <div class="form-group">
                    <label class="form-label">Título do Jogo *</label>
                    <input type="text" name="titulo" class="form-control" required 
                           value="<?php echo sanitize($jogo['titulo']); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Descrição Curta *</label>
                    <input type="text" name="descricao_curta" class="form-control" required 
                           maxlength="500"
                           value="<?php echo sanitize($jogo['descricao_curta']); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Descrição Completa *</label>
                    <textarea name="descricao_completa" class="form-control" rows="8" required><?php echo sanitize($jogo['descricao_completa']); ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Preço (R$) *</label>
                        <input type="number" name="preco" class="form-control" required 
                               min="0" step="0.01" value="<?php echo $jogo['preco_centavos'] / 100; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Classificação Etária *</label>
                        <select name="classificacao" class="form-control" required>
                            <?php foreach (['L', '10', '12', '14', '16', '18'] as $idade): ?>
                                <option value="<?php echo $idade; ?>" <?php echo $jogo['classificacao_etaria'] == $idade ? 'selected' : ''; ?>>
                                    <?php echo $idade == 'L' ? 'Livre' : $idade . ' anos'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Mídia -->
            <div class="form-section">
                <h2><i class="fas fa-images"></i> Mídia do Jogo</h2>
                
                <?php if ($jogo['imagem_capa']): ?>
                    <div style="margin-bottom: 15px;">
                        <strong>Capa Atual:</strong><br>
                        <img src="<?php echo SITE_URL . $jogo['imagem_capa']; ?>" style="width: 200px; border-radius: 8px; margin-top: 10px;">
                    </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label class="form-label">Nova Capa (deixe vazio para manter atual)</label>
                    <div class="upload-area" onclick="document.getElementById('capa').click()">
                        <i class="fas fa-image"></i>
                        <p>Clique para trocar a capa</p>
                        <input type="file" id="capa" name="capa" accept="image/*" style="display:none;">
                    </div>
                    <div class="proportion-hint">
                        <i class="fas fa-info-circle"></i> <strong>Proporção sugerida: 3:4</strong>
                    </div>
                </div>
                
                <?php if ($jogo['imagem_banner']): ?>
                    <div style="margin-bottom: 15px;">
                        <strong>Banner Atual:</strong><br>
                        <img src="<?php echo SITE_URL . $jogo['imagem_banner']; ?>" style="width: 400px; border-radius: 8px; margin-top: 10px;">
                    </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label class="form-label">Novo Banner (deixe vazio para manter atual)</label>
                    <div class="upload-area" onclick="document.getElementById('banner').click()">
                        <i class="fas fa-panorama"></i>
                        <p>Clique para trocar o banner</p>
                        <input type="file" id="banner" name="banner" accept="image/*" style="display:none;">
                    </div>
                    <div class="proportion-hint">
                        <i class="fas fa-info-circle"></i> <strong>Proporção sugerida: 16:9</strong>
                    </div>
                </div>
                
                <!-- Screenshots Existentes -->
                <?php if (count($imagens_existentes) > 0): ?>
                <div class="form-group">
                    <label class="form-label">Screenshots Atuais (marque para remover)</label>
                    <div class="existing-screenshots">
                        <?php foreach ($imagens_existentes as $img): ?>
                        <div class="screenshot-item">
                            <img src="<?php echo SITE_URL . $img['imagem']; ?>">
                            <input type="checkbox" name="remove_screenshots[]" 
                                   value="<?php echo $img['id']; ?>" 
                                   class="screenshot-checkbox">
                            <div class="screenshot-order">#<?php echo $img['ordem']; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <small style="color: var(--text-secondary);">
                        Marque as screenshots que deseja remover
                    </small>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label class="form-label">Adicionar Novas Screenshots</label>
                    <div class="upload-area" onclick="document.getElementById('screenshots').click()">
                        <i class="fas fa-images"></i>
                        <p>Clique para adicionar screenshots</p>
                        <input type="file" id="screenshots" name="screenshots[]" accept="image/*" multiple style="display:none;">
                    </div>
                    <div class="proportion-hint">
                        <i class="fas fa-info-circle"></i> <strong>Proporção sugerida: 16:9</strong>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Trailer do YouTube</label>
                    <input type="url" name="video_trailer" class="form-control"
                           value="<?php echo sanitize($jogo['video_trailer']); ?>"
                           placeholder="https://www.youtube.com/embed/VIDEO_ID">
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
                            <input type="checkbox" name="categorias[]" value="<?php echo $cat['id']; ?>"
                                   <?php echo in_array($cat['id'], $cats_selecionadas) ? 'checked' : ''; ?>>
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
                            <input type="checkbox" name="tags[]" value="<?php echo $tag['id']; ?>"
                                   <?php echo in_array($tag['id'], $tags_selecionadas) ? 'checked' : ''; ?>>
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
                        <input type="checkbox" name="plataformas[]" value="<?php echo $plat['id']; ?>"
                               <?php echo in_array($plat['id'], $plats_selecionadas) ? 'checked' : ''; ?>>
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
                        <textarea name="requisitos_minimos" class="form-control" rows="8"><?php echo sanitize($jogo['requisitos_minimos']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Requisitos Recomendados</label>
                        <textarea name="requisitos_recomendados" class="form-control" rows="8"><?php echo sanitize($jogo['requisitos_recomendados']); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Ações -->
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <button type="submit" name="action" value="save" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> Salvar Alterações
                </button>
                
                <?php if ($jogo['status'] == 'rascunho'): ?>
                <button type="submit" name="action" value="publish" class="btn btn-success btn-lg">
                    <i class="fas fa-paper-plane"></i> Enviar para Revisão
                </button>
                <?php endif; ?>
                
                <a href="<?php echo SITE_URL; ?>/developer/jogos.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
                
                <a href="<?php echo SITE_URL; ?>/developer/arquivo-jogo.php?jogo=<?php echo $jogo_id; ?>" 
                   class="btn btn-secondary">
                    <i class="fas fa-file-archive"></i> Gerenciar Arquivos
                </a>
            </div>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.checkbox-item').forEach(item => {
    item.addEventListener('click', function(e) {
        if (e.target.tagName !== 'INPUT') {
            const checkbox = this.querySelector('input[type="checkbox"]');
            checkbox.checked = !checkbox.checked;
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>