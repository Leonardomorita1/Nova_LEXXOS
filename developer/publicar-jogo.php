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
            $dev['id'],
            $titulo,
            $slug,
            $descricao_curta,
            $descricao_completa,
            $preco_centavos,
            $capa_path,
            $banner_path,
            $video_trailer,
            $requisitos_minimos,
            $requisitos_recomendados,
            $classificacao
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

        // Adicionar categorias (Vindo do campo oculto via JS)
        if (!empty($_POST['categorias_selecionadas'])) {
            $cats = explode(',', $_POST['categorias_selecionadas']);
            foreach ($cats as $cat_id) {
                $stmt = $pdo->prepare("INSERT INTO jogo_categoria (jogo_id, categoria_id) VALUES (?, ?)");
                $stmt->execute([$jogo_id, (int)$cat_id]);
            }
        }

        // Adicionar tags (Vindo do campo oculto via JS)
        if (!empty($_POST['tags_selecionadas'])) {
            $tgs = explode(',', $_POST['tags_selecionadas']);
            foreach ($tgs as $tag_val) {
                // Se for um ID numérico, salva. Se sua tabela de tags aceitar texto, ajuste aqui.
                $stmt = $pdo->prepare("INSERT INTO jogo_tag (jogo_id, tag_id) VALUES (?, ?)");
                $stmt->execute([$jogo_id, (int)$tag_val]);
            }
        }

        // Adicionar plataformas (Vindo do campo oculto via JS)
        if (!empty($_POST['plataformas_selecionadas'])) {
            $plats = explode(',', $_POST['plataformas_selecionadas']);
            foreach ($plats as $plat_id) {
                $stmt = $pdo->prepare("INSERT INTO jogo_plataforma (jogo_id, plataforma_id) VALUES (?, ?)");
                $stmt->execute([$jogo_id, (int)$plat_id]);
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
    /* Layout e Limitação de Largura */
    .publish-wrapper {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }

    .publish-container {
        display: grid;
        grid-template-columns: 1fr 380px;
        gap: 30px;
        align-items: start;
    }

    .form-section {
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 25px;
    }

    .section-title {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--primary);
    }

    /* Uploads com Proporção 3:4 e Banner */
    .upload-box {
        position: relative;
        width: 100%;
        border: 2px dashed var(--border);
        border-radius: 10px;
        overflow: hidden;
        background: var(--bg-primary);
        cursor: pointer;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        transition: 0.3s;
    }

    .upload-box.capa-box {
        aspect-ratio: 3 / 4;
    }

    .upload-box.banner-box {
        aspect-ratio: 16 / 9;
    }

    .upload-box:hover {
        border-color: var(--primary);
    }

    .preview-img {
        position: absolute;
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: none;
    }

    .btn-remove-img {
        position: absolute;
        top: 8px;
        right: 8px;
        background: #ff4757;
        color: white;
        border: none;
        border-radius: 6px;
        padding: 5px 8px;
        cursor: pointer;
        display: none;
        z-index: 10;
        font-size: 12px;
    }

    /* Grid de Screenshots Compacto */
    .screenshots-wrapper {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
        gap: 12px;
    }

    .screenshot-item,
    .add-screenshot-btn {
        aspect-ratio: 1;
        border-radius: 8px;
        position: relative;
        border: 1px solid var(--border);
    }

    .add-screenshot-btn {
        border: 2px dashed var(--border);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }

    .screenshot-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 8px;
    }

    /* Requisitos e Inputs */
    .requisitos-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }

    .hidden-input {
        display: none;
    }

    /* Estilo das Tags/Chips */
    .tags-container {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        padding: 10px;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: var(--bg-primary);
        min-height: 45px;
        cursor: pointer;
    }

    .tag-chip {
        background: var(--primary);
        color: white;
        padding: 4px 10px;
        border-radius: 20px;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        animation: fadeIn 0.2s ease;
    }

    .tag-chip i {
        cursor: pointer;
        font-size: 11px;
    }

    .tag-chip i:hover {
        color: #ff4757;
    }

    /* Container Flutuante (Popover) */
    .tags-popover {
        position: absolute;
        z-index: 1000;
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        width: 100%;
        max-height: 300px;
        overflow-y: auto;
        display: none;
        /* Escondido por padrão */
        padding: 15px;
        margin-top: 5px;
    }

    .popover-section-title {
        font-size: 11px;
        text-transform: uppercase;
        color: var(--text-secondary);
        margin: 10px 0 5px 0;
    }

    .opt-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
    }

    .opt-item {
        padding: 8px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        transition: 0.2s;
        border: 1px solid transparent;
    }

    .opt-item:hover {
        background: var(--bg-primary);
        border-color: var(--primary);
    }

    .opt-item.selected {
        background: var(--primary-low);
        /* Um tom opaco da sua cor primária */
        color: var(--primary);
        pointer-events: none;
        opacity: 0.6;
    }

    /* Estilo dos Botões de Plataforma */
    .platform-grid {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }

    .platform-chip {
        padding: 10px 20px;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: var(--bg-primary);
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: all 0.2s ease;
        user-select: none;
        font-weight: 500;
    }

    .platform-chip i {
        font-size: 1.1rem;
        color: var(--text-secondary);
    }

    .platform-chip:hover {
        border-color: var(--primary);
        background: var(--bg-secondary);
    }

    .platform-chip.active {
        background: var(--primary);
        border-color: var(--primary);
        color: white;
    }

    .platform-chip.active i {
        color: white;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: scale(0.9);
        }

        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    @media (max-width: 992px) {
        .publish-container {
            grid-template-columns: 1fr;
        }
    }
</style>
<div class="container">
    <div class="dev-layout">
        <?php require_once 'includes/sidebar.php'; ?>

        <div class="dev-content">

            <div class="publish-wrapper">

                <form id="publishForm" method="POST" enctype="multipart/form-data">
                    <div class="publish-container">

                        <div class="main-column">
                            <div class="form-section">
                                <div class="section-title"><i class="fas fa-keyboard"></i> Detalhes do Jogo</div>
                                <div class="form-group">
                                    <label>Título</label>
                                    <input type="text" name="titulo" class="form-control" placeholder="Nome do seu jogo" required>
                                </div>
                                <div class="form-group">
                                    <label>Descrição Curta</label>
                                    <input type="text" name="descricao_curta" class="form-control" maxlength="150" placeholder="Uma frase chamativa">
                                </div>
                                <div class="form-group">
                                    <label>Descrição Completa</label>
                                    <textarea name="descricao_completa" class="form-control" rows="6"></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Link do Trailer (YouTube)</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fab fa-youtube"></i></span></div>
                                        <input type="url" name="video_trailer" class="form-control" placeholder="https://youtube.com/watch?v=...">
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <div class="section-title"><i class="fas fa-tags"></i> Classificação do Jogo</div>

                                <div class="row" style="display: flex; gap: 20px;">
                                    <div style="flex: 1; position: relative;">
                                        <label>Categorias Principais</label>
                                        <div class="tags-container" id="catsTrigger">
                                            <span class="placeholder-text">Selecionar categorias...</span>
                                        </div>
                                        <div class="tags-popover" id="catsPopover">
                                            <div class="popover-section-title">Disponíveis</div>
                                            <div class="opt-grid">
                                                <?php foreach ($categorias as $cat): ?>
                                                    <div class="opt-item" data-id="<?= $cat['id'] ?>" data-name="<?= $cat['nome'] ?>" data-target="cats">
                                                        <?= $cat['nome'] ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <input type="hidden" name="categorias_selecionadas" id="inputCats">
                                    </div>

                                    <div style="flex: 1; position: relative;">
                                        <label>Tags Relacionadas</label>
                                        <div class="tags-container" id="tagsTrigger">
                                            <span class="placeholder-text">Selecionar tags...</span>
                                        </div>
                                        <div class="tags-popover" id="tagsPopover">
                                            <div class="popover-section-title">Tags Sugeridas</div>
                                            <div class="opt-grid">
                                                <?php foreach ($tags as $tag): ?>
                                                    <div class="opt-item" data-id="<?= $tag['id'] ?>" data-name="<?= $tag['nome'] ?>" data-target="tags">
                                                        <?= $tag['nome'] ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <input type="hidden" name="tags_selecionadas" id="inputTags">
                                    </div>
                                </div>
                            </div>
                            <div class="form-section">
                                <div class="section-title"><i class="fas fa-laptop-code"></i> Disponibilidade</div>
                                <label style="margin-bottom: 15px; display: block;">Selecione as plataformas suportadas pelo jogo:</label>

                                <div class="platform-grid" id="platformContainer">
                                    <?php foreach ($plataformas as $plat): ?>
                                        <div class="platform-chip" data-id="<?= $plat['id'] ?>">
                                            <?php
                                            // Ícones automáticos baseados no nome
                                            $icon = 'fa-desktop';
                                            if (stripos($plat['nome'], 'win') !== false) $icon = 'fab fa-windows';
                                            if (stripos($plat['nome'], 'mac') !== false) $icon = 'fab fa-apple';
                                            if (stripos($plat['nome'], 'lin') !== false) $icon = 'fab fa-linux';
                                            ?>
                                            <i class="<?= $icon ?>"></i>
                                            <?= $plat['nome'] ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <input type="hidden" name="plataformas_selecionadas" id="inputPlataformas">
                            </div>

                            <div class="form-section">
                                <div class="section-title"><i class="fas fa-images"></i> Screenshots</div>
                                <div class="screenshots-wrapper" id="screenshotsContainer">
                                    <label class="add-screenshot-btn" for="screenshots">
                                        <i class="fas fa-plus"></i>
                                    </label>
                                </div>
                                <input type="file" name="screenshots[]" id="screenshots" class="hidden-input" multiple accept="image/*">
                            </div>

                            <div class="form-section">
                                <div class="section-title"><i class="fas fa-microchip"></i> Requisitos</div>
                                <div class="requisitos-grid">
                                    <textarea name="requisitos_minimos" class="form-control" placeholder="Requisitos Mínimos" rows="4"></textarea>
                                    <textarea name="requisitos_recomendados" class="form-control" placeholder="Requisitos Recomendados" rows="4"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="side-column">
                            <div class="form-section">
                                <div class="section-title"><i class="fas fa-image"></i> Identidade Visual</div>

                                <label>Capa (Proporção 3:4)</label>
                                <div class="upload-box capa-box" onclick="document.getElementById('capa').click()">
                                    <img id="preview-capa" class="preview-img">
                                    <button type="button" class="btn-remove-img" id="remove-capa">Remover</button>
                                    <div id="placeholder-capa"><i class="fas fa-file-upload"></i><br>Capa 3:4</div>
                                </div>
                                <input type="file" name="capa" id="capa" class="hidden-input" accept="image/*">

                                <br>

                                <label>Banner (16:9)</label>
                                <div class="upload-box banner-box" onclick="document.getElementById('banner').click()">
                                    <img id="preview-banner" class="preview-img">
                                    <button type="button" class="btn-remove-img" id="remove-banner">Remover</button>
                                    <div id="placeholder-banner"><i class="fas fa-image"></i><br>Banner 16:9</div>
                                </div>
                                <input type="file" name="banner" id="banner" class="hidden-input" accept="image/*">
                            </div>

                            <div class="form-section">
                                <div class="section-title"><i class="fas fa-dollar-sign"></i> Preço e Classificação</div>
                                <div class="form-group">
                                    <input type="number" step="0.01" name="preco" class="form-control" placeholder="R$ 0,00 (0 para grátis)">
                                </div>
                                <div class="form-group">
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

                            <button type="submit" class="btn btn-primary btn-block btn-lg">
                                <i class="fas fa-rocket"></i> PUBLICAR AGORA
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Lógica de Preview e Auto-Save
    function setupPreview(inputId, previewId, placeholderId, removeId) {
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewId);
        const placeholder = document.getElementById(placeholderId);
        const removeBtn = document.getElementById(removeId);

        input.addEventListener('change', function() {
            if (this.files[0]) {
                const reader = new FileReader();
                reader.onload = e => {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    placeholder.style.display = 'none';
                    removeBtn.style.display = 'block';
                }
                reader.readAsDataURL(this.files[0]);
            }
        });

        removeBtn.addEventListener('click', e => {
            e.stopPropagation();
            input.value = "";
            preview.style.display = 'none';
            placeholder.style.display = 'block';
            removeBtn.style.display = 'none';
        });
    }

    setupPreview('capa', 'preview-capa', 'placeholder-capa', 'remove-capa');
    setupPreview('banner', 'preview-banner', 'placeholder-banner', 'remove-banner');

    // Screenshots
    document.getElementById('screenshots').addEventListener('change', function() {
        const container = document.getElementById('screenshotsContainer');
        const addBtn = container.querySelector('.add-screenshot-btn');
        Array.from(this.files).forEach(file => {
            const reader = new FileReader();
            reader.onload = e => {
                const div = document.createElement('div');
                div.className = 'screenshot-item';
                div.innerHTML = `<img src="${e.target.result}"><button type="button" class="btn-remove-img" style="display:block" onclick="this.parentElement.remove()">X</button>`;
                container.insertBefore(div, addBtn);
            }
            reader.readAsDataURL(file);
        });
    });

    // Auto-save no LocalStorage
    const form = document.getElementById('publishForm');
    const fields = form.querySelectorAll('input:not([type="file"]), textarea, select');

    fields.forEach(f => {
        f.value = localStorage.getItem('pub_cache_' + f.name) || f.value;
        f.addEventListener('input', () => localStorage.setItem('pub_cache_' + f.name, f.value));
    });

    form.onsubmit = () => fields.forEach(f => localStorage.removeItem('pub_cache_' + f.name));

    document.addEventListener('DOMContentLoaded', function() {
        function initMultiSelect(triggerId, popoverId, inputId, storageKey) {
            const trigger = document.getElementById(triggerId);
            const popover = document.getElementById(popoverId);
            const input = document.getElementById(inputId);
            let selected = [];

            // Recuperar do cache (Auto-save)
            const cached = localStorage.getItem(storageKey);
            if (cached) {
                const ids = cached.split(',');
                popover.querySelectorAll('.opt-item').forEach(item => {
                    if (ids.includes(item.dataset.id)) {
                        selected.push({
                            id: item.dataset.id,
                            name: item.dataset.name
                        });
                        item.classList.add('selected');
                    }
                });
                render();
            }

            trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                document.querySelectorAll('.tags-popover').forEach(p => p !== popover ? p.style.display = 'none' : null);
                popover.style.display = popover.style.display === 'block' ? 'none' : 'block';
            });

            popover.querySelectorAll('.opt-item').forEach(item => {
                item.addEventListener('click', () => {
                    const id = item.dataset.id;
                    if (!selected.find(i => i.id === id)) {
                        selected.push({
                            id,
                            name: item.dataset.name
                        });
                        item.classList.add('selected');
                        render();
                    }
                    popover.style.display = 'none';
                });
            });

            function render() {
                trigger.innerHTML = selected.length === 0 ? '<span class="placeholder-text">Selecionar...</span>' : '';
                selected.forEach(item => {
                    const chip = document.createElement('div');
                    chip.className = 'tag-chip';
                    chip.innerHTML = `${item.name} <i class="fas fa-times"></i>`;
                    chip.querySelector('i').onclick = (e) => {
                        e.stopPropagation();
                        remove(item.id);
                    };
                    trigger.appendChild(chip);
                });
                const val = selected.map(i => i.id).join(',');
                input.value = val;
                localStorage.setItem(storageKey, val); // Salva no cache
            }

            function remove(id) {
                selected = selected.filter(i => i.id !== id);
                const opt = popover.querySelector(`[data-id="${id}"]`);
                if (opt) opt.classList.remove('selected');
                render();
            }

            document.addEventListener('click', () => popover.style.display = 'none');
        }

        // Inicializa os dois seletores
        initMultiSelect('catsTrigger', 'catsPopover', 'inputCats', 'pub_cache_cats');
        initMultiSelect('tagsTrigger', 'tagsPopover', 'inputTags', 'pub_cache_tags');
    });
    // Lógica de Plataformas
    const platformChips = document.querySelectorAll('.platform-chip');
    const inputPlataformas = document.getElementById('inputPlataformas');
    let selectedPlats = [];

    // Recuperar do LocalStorage
    const savedPlats = localStorage.getItem('pub_cache_plats');
    if (savedPlats) {
        selectedPlats = savedPlats.split(',');
        platformChips.forEach(chip => {
            if (selectedPlats.includes(chip.dataset.id)) {
                chip.classList.add('active');
            }
        });
        inputPlataformas.value = savedPlats;
    }

    platformChips.forEach(chip => {
        chip.addEventListener('click', function() {
            const id = this.dataset.id;

            if (this.classList.contains('active')) {
                this.classList.remove('active');
                selectedPlats = selectedPlats.filter(item => item !== id);
            } else {
                this.classList.add('active');
                selectedPlats.push(id);
            }

            const val = selectedPlats.join(',');
            inputPlataformas.value = val;
            localStorage.setItem('pub_cache_plats', val);
        });
    });
</script>

<?php require_once '../includes/footer.php'; ?>