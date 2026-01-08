<?php
// developer/editar-jogo.php
require_once '../config/config.php';
require_once '../config/database.php';

requireLogin();

$database = new Database();
$pdo = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Dev check
$stmt = $pdo->prepare("SELECT * FROM desenvolvedor WHERE usuario_id = ?");
$stmt->execute([$user_id]);
$dev = $stmt->fetch();

if (!$dev) {
    header('Location: ' . SITE_URL . '/developer/dashboard.php');
    exit;
}

$jogo_id = $_GET['id'] ?? null;
if (!$jogo_id) {
    header('Location: jogos.php');
    exit;
}

// Fetch Game
$stmt = $pdo->prepare("SELECT * FROM jogo WHERE id = ? AND desenvolvedor_id = ?");
$stmt->execute([$jogo_id, $dev['id']]);
$jogo = $stmt->fetch();

if (!$jogo) {
    header('Location: jogos.php');
    exit;
}

// --- ACTIONS ---

// 1. DELETE DRAFT
if (isset($_POST['action']) && $_POST['action'] == 'delete' && $jogo['status'] == 'rascunho') {
    $pdo->prepare("DELETE FROM jogo WHERE id = ?")->execute([$jogo_id]);
    // Optional: Delete files from folder here
    header('Location: jogos.php');
    exit;
}

// 2. SAVE UPDATE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'save') {
    try {
        $pdo->beginTransaction();

        $slug = $jogo['slug']; // Keep slug
        $upload_dir = '../uploads/jogos/' . $slug;

        // Basic Data
        $sql = "UPDATE jogo SET titulo=?, descricao_curta=?, descricao_completa=?, video_trailer=?, requisitos_minimos=?, requisitos_recomendados=?, preco_centavos=?, classificacao_etaria=?, atualizado_em=NOW() WHERE id=?";
        $pdo->prepare($sql)->execute([
            trim($_POST['titulo']),
            trim($_POST['descricao_curta']),
            trim($_POST['descricao_completa']),
            trim($_POST['video_trailer']),
            trim($_POST['requisitos_minimos']),
            trim($_POST['requisitos_recomendados']),
            (int)($_POST['preco'] * 100),
            $_POST['classificacao'],
            $jogo_id
        ]);

        // Images Update
        if (!empty($_FILES['capa']['name'])) {
            $ext = pathinfo($_FILES['capa']['name'], PATHINFO_EXTENSION);
            move_uploaded_file($_FILES['capa']['tmp_name'], "$upload_dir/capa.$ext");
            $pdo->prepare("UPDATE jogo SET imagem_capa=? WHERE id=?")->execute(["/uploads/jogos/$slug/capa.$ext", $jogo_id]);
        }
        if (!empty($_FILES['banner']['name'])) {
            $ext = pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION);
            move_uploaded_file($_FILES['banner']['tmp_name'], "$upload_dir/banner.$ext");
            $pdo->prepare("UPDATE jogo SET imagem_banner=? WHERE id=?")->execute(["/uploads/jogos/$slug/banner.$ext", $jogo_id]);
        }

        // New Screenshots
        if (!empty($_FILES['screenshots']['name'][0])) {
            $next_ordem = $pdo->query("SELECT MAX(ordem) FROM jogo_imagens WHERE jogo_id=$jogo_id")->fetchColumn() + 1;
            foreach ($_FILES['screenshots']['tmp_name'] as $k => $tmp) {
                if ($_FILES['screenshots']['error'][$k] == 0) {
                    $fname = time() . "-$k." . pathinfo($_FILES['screenshots']['name'][$k], PATHINFO_EXTENSION);
                    move_uploaded_file($tmp, "$upload_dir/screenshots/$fname");
                    $pdo->prepare("INSERT INTO jogo_imagens (jogo_id, imagem, ordem) VALUES (?,?,?)")->execute([$jogo_id, "/uploads/jogos/$slug/screenshots/$fname", $next_ordem++]);
                }
            }
        }

        // Delete Marked Screenshots
        if (isset($_POST['delete_imgs'])) {
            foreach ($_POST['delete_imgs'] as $img_id) {
                // Ideally remove file too
                $pdo->prepare("DELETE FROM jogo_imagens WHERE id=? AND jogo_id=?")->execute([$img_id, $jogo_id]);
            }
        }

        // Relations Update (Wipe & Re-insert)
        $pdo->prepare("DELETE FROM jogo_categoria WHERE jogo_id=?")->execute([$jogo_id]);
        if (!empty($_POST['cats_selecionadas'])) {
            foreach (explode(',', $_POST['cats_selecionadas']) as $c)
                $pdo->prepare("INSERT INTO jogo_categoria (jogo_id, categoria_id) VALUES (?,?)")->execute([$jogo_id, (int)$c]);
        }

        $pdo->prepare("DELETE FROM jogo_tag WHERE jogo_id=?")->execute([$jogo_id]);
        if (!empty($_POST['tags_selecionadas'])) {
            foreach (explode(',', $_POST['tags_selecionadas']) as $t)
                $pdo->prepare("INSERT INTO jogo_tag (jogo_id, tag_id) VALUES (?,?)")->execute([$jogo_id, (int)$t]);
        }

        $pdo->prepare("DELETE FROM jogo_plataforma WHERE jogo_id=?")->execute([$jogo_id]);
        if (!empty($_POST['plats_selecionadas'])) {
            foreach (explode(',', $_POST['plats_selecionadas']) as $p)
                $pdo->prepare("INSERT INTO jogo_plataforma (jogo_id, plataforma_id) VALUES (?,?)")->execute([$jogo_id, (int)$p]);
        }

        $pdo->commit();
        // Refresh data
        header("Location: editar-jogo.php?id=$jogo_id&success=1");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// --- DATA FETCHING ---
$categorias = $pdo->query("SELECT * FROM categoria WHERE ativa=1 ORDER BY nome")->fetchAll();
$tags = $pdo->query("SELECT * FROM tag ORDER BY nome")->fetchAll();
$plataformas = $pdo->query("SELECT * FROM plataforma WHERE ativa=1 ORDER BY ordem")->fetchAll();
$imgs = $pdo->prepare("SELECT * FROM jogo_imagens WHERE jogo_id=? ORDER BY ordem");
$imgs->execute([$jogo_id]);
$screenshots = $imgs->fetchAll();

// Pre-filled relations (Arrays of IDs)
$my_cats = $pdo->query("SELECT categoria_id FROM jogo_categoria WHERE jogo_id=$jogo_id")->fetchAll(PDO::FETCH_COLUMN);
$my_tags = $pdo->query("SELECT tag_id FROM jogo_tag WHERE jogo_id=$jogo_id")->fetchAll(PDO::FETCH_COLUMN);
$my_plats = $pdo->query("SELECT plataforma_id FROM jogo_plataforma WHERE jogo_id=$jogo_id")->fetchAll(PDO::FETCH_COLUMN);

$page_title = 'Editar ' . sanitize($jogo['titulo']);
require_once '../includes/header.php';
?>

<style>
    .dev-layout {
        display: grid;
        grid-template-columns: 260px 1fr;
        gap: 30px;
        padding: 30px 0;
    }

    .dev-content {
        min-width: 0;
    }

    .publish-wrapper {
        max-width: 1200px;
        margin: 0 auto;
    }

    .publish-grid {
        display: grid;
        grid-template-columns: 1fr 380px;
        gap: 30px;
        align-items: start;
    }

    .form-box {
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 25px;
    }

    .box-title {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 20px;
        color: var(--accent);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .upload-zone {
        position: relative;
        width: 100%;
        background: var(--bg-primary);
        border: 2px dashed var(--border);
        border-radius: 10px;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .upload-zone.capa {
        aspect-ratio: 3/4;
    }

    .upload-zone.banner {
        aspect-ratio: 16/9;
    }

    .preview-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        position: absolute;
    }

    .upload-icon {
        display: none;
    }

    /* Hide icon if image exists */

    .chips-input {
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

    .chip {
        background: var(--accent);
        color: white;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 13px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .popover-list {
        display: none;
        position: absolute;
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 8px;
        width: 100%;
        z-index: 100;
        max-height: 250px;
        overflow-y: auto;
        margin-top: 5px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }

    .popover-item {
        padding: 8px 12px;
        cursor: pointer;
    }

    .popover-item:hover {
        background: var(--bg-primary);
    }

    .plat-grid {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .plat-btn {
        padding: 8px 16px;
        border: 1px solid var(--border);
        border-radius: 8px;
        cursor: pointer;
        background: var(--bg-primary);
        user-select: none;
    }

    .plat-btn.active {
        background: var(--accent);
        color: white;
        border-color: var(--accent);
    }

    .btn-danger-outline {
        width: 100%;
        background: transparent;
        border: 1px solid #dc3545;
        color: #dc3545;
        padding: 12px;
        border-radius: 8px;
        margin-top: 15px;
        cursor: pointer;
        transition: 0.3s;
    }

    .btn-danger-outline:hover {
        background: #dc3545;
        color: white;
    }

    /* Edit Specific: Existing Screenshots */
    .shots-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        gap: 10px;
    }

    .shot-item {
        aspect-ratio: 16/9;
        position: relative;
        border-radius: 6px;
        overflow: hidden;
        border: 1px solid var(--border);
    }

    .shot-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .del-shot-overlay {
        position: absolute;
        inset: 0;
        background: rgba(220, 53, 69, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: 0.2s;
        cursor: pointer;
    }

    .shot-cb:checked+.del-shot-overlay {
        opacity: 1;
    }

    .shot-cb {
        display: none;
    }

    .add-shot {
        border: 2px dashed var(--border);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        aspect-ratio: 16/9;
        border-radius: 6px;
    }

    @media(max-width: 992px) {
        .dev-layout {
            grid-template-columns: 1fr;
        }

        .publish-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="container">
    <div class="dev-layout">
        <?php require_once 'includes/sidebar.php'; ?>

        <div class="dev-content">
            <div class="publish-wrapper">
                <div style="margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h1 style="font-size: 28px;"><i class="fas fa-edit"></i> Editar Jogo</h1>
                        <p style="color: var(--text-secondary);">Atualizando: <?= sanitize($jogo['titulo']) ?></p>
                    </div>
                    <a href="<?= SITE_URL ?>/pages/jogo.php?slug=<?= $jogo['slug'] ?>" target="_blank" class="btn btn-secondary">
                        <i class="fas fa-eye"></i> Ver Página
                    </a>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success">Jogo atualizado com sucesso!</div>
                <?php endif; ?>

                <form id="editForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save">

                    <div class="publish-grid">
                        <div class="main-col">
                            <div class="form-box">
                                <div class="box-title"><i class="fas fa-info-circle"></i> Informações</div>
                                <div class="form-group">
                                    <label>Título</label>
                                    <input type="text" name="titulo" class="form-control" value="<?= sanitize($jogo['titulo']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Descrição Curta</label>
                                    <input type="text" name="descricao_curta" class="form-control" value="<?= sanitize($jogo['descricao_curta']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Descrição Completa</label>
                                    <textarea name="descricao_completa" class="form-control" rows="8"><?= sanitize($jogo['descricao_completa']) ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Trailer (YouTube)</label>
                                    <input type="url" name="video_trailer" class="form-control" value="<?= sanitize($jogo['video_trailer']) ?>">
                                </div>
                            </div>

                            <div class="form-box">
                                <div class="box-title"><i class="fas fa-tags"></i> Classificação</div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                    <div style="position: relative;">
                                        <label>Categorias</label>
                                        <div class="chips-input" id="triggerCat"></div>
                                        <div class="popover-list" id="popCat">
                                            <?php foreach ($categorias as $c): ?>
                                                <div class="popover-item" data-id="<?= $c['id'] ?>" data-name="<?= $c['nome'] ?>"><?= $c['nome'] ?></div>
                                            <?php endforeach; ?>
                                        </div>
                                        <input type="hidden" name="cats_selecionadas" id="inputCat" value="<?= implode(',', $my_cats) ?>">
                                    </div>
                                    <div style="position: relative;">
                                        <label>Tags</label>
                                        <div class="chips-input" id="triggerTag"></div>
                                        <div class="popover-list" id="popTag">
                                            <?php foreach ($tags as $t): ?>
                                                <div class="popover-item" data-id="<?= $t['id'] ?>" data-name="<?= $t['nome'] ?>"><?= $t['nome'] ?></div>
                                            <?php endforeach; ?>
                                        </div>
                                        <input type="hidden" name="tags_selecionadas" id="inputTag" value="<?= implode(',', $my_tags) ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="form-box">
                                <div class="box-title"><i class="fas fa-desktop"></i> Requisitos e Plataformas</div>
                                <label style="margin-bottom: 10px; display:block;">Plataformas</label>
                                <div class="plat-grid" style="margin-bottom: 20px;">
                                    <?php foreach ($plataformas as $p): ?>
                                        <div class="plat-btn" data-id="<?= $p['id'] ?>">
                                            <i class="<?= $p['icone'] ?>"></i> <?= $p['nome'] ?>
                                        </div>
                                    <?php endforeach; ?>
                                    <input type="hidden" name="plats_selecionadas" id="inputPlat" value="<?= implode(',', $my_plats) ?>">
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                    <textarea name="requisitos_minimos" class="form-control" rows="4"><?= sanitize($jogo['requisitos_minimos']) ?></textarea>
                                    <textarea name="requisitos_recomendados" class="form-control" rows="4"><?= sanitize($jogo['requisitos_recomendados']) ?></textarea>
                                </div>
                            </div>

                            <div class="form-box">
                                <div class="box-title"><i class="fas fa-images"></i> Screenshots</div>
                                <div class="shots-grid" id="shotContainer">
                                    <?php foreach ($screenshots as $img): ?>
                                        <label class="shot-item">
                                            <img src="<?= SITE_URL . $img['imagem'] ?>">
                                            <input type="checkbox" name="delete_imgs[]" value="<?= $img['id'] ?>" class="shot-cb">
                                            <div class="del-shot-overlay"><i class="fas fa-trash fa-lg" style="color:white"></i></div>
                                        </label>
                                    <?php endforeach; ?>

                                    <label class="add-shot">
                                        <i class="fas fa-plus fa-lg"></i>
                                        <input type="file" name="screenshots[]" multiple accept="image/*" style="display:none" id="shotInput">
                                    </label>
                                </div>
                                <small style="display:block; margin-top:10px; color:var(--text-secondary)">Clique nas imagens para marcar para exclusão.</small>
                            </div>
                        </div>

                        <div class="side-col">
                            <div class="form-box">
                                <div class="box-title"><i class="fas fa-image"></i> Mídia</div>
                                <label>Capa (3:4)</label>
                                <div class="upload-zone capa" onclick="document.getElementById('capa').click()">
                                    <?php if ($jogo['imagem_capa']): ?>
                                        <img src="<?= SITE_URL . $jogo['imagem_capa'] ?>" id="prev-capa" class="preview-img">
                                    <?php else: ?>
                                        <div class="upload-icon"><i class="fas fa-upload fa-2x"></i></div>
                                    <?php endif; ?>
                                </div>
                                <input type="file" name="capa" id="capa" style="display:none" accept="image/*">

                                <label style="margin-top: 20px; display:block;">Banner (16:9)</label>
                                <div class="upload-zone banner" onclick="document.getElementById('banner').click()">
                                    <?php if ($jogo['imagem_banner']): ?>
                                        <img src="<?= SITE_URL . $jogo['imagem_banner'] ?>" id="prev-banner" class="preview-img">
                                    <?php else: ?>
                                        <div class="upload-icon"><i class="fas fa-panorama fa-2x"></i></div>
                                    <?php endif; ?>
                                </div>
                                <input type="file" name="banner" id="banner" style="display:none" accept="image/*">
                            </div>

                            <div class="form-box">
                                <div class="box-title"><i class="fas fa-dollar-sign"></i> Preço</div>
                                <input type="number" name="preco" class="form-control" step="0.01" value="<?= $jogo['preco_centavos'] / 100 ?>">

                                <label style="margin-top: 15px; display:block">Classificação</label>
                                <select name="classificacao" class="form-control">
                                    <?php foreach (['L', '10', '12', '14', '16', '18'] as $opt): ?>
                                        <option value="<?= $opt ?>" <?= $jogo['classificacao_etaria'] == $opt ? 'selected' : '' ?>><?= $opt ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg" style="width:100%">
                                <i class="fas fa-sync"></i> Atualizar Jogo
                            </button>



                            <?php if ($jogo['status'] == 'rascunho'): ?>
                                <button type="submit" name="action" value="publish" class="btn btn-success btn-lg" style="width:100%; margin-top: 10px; background-color: #2ecc71; border: none;">
                                    <i class="fas fa-paper-plane"></i> Enviar para Revisão
                                </button>
                            <?php endif; ?>

                            <?php if ($jogo['status'] == 'rascunho'): ?>
                                <button type="submit" name="action" value="delete" class="btn-danger-outline" onclick="return confirm('Tem certeza? Isso apagará o rascunho permanentemente.')">
                                    <i class="fas fa-trash"></i> Deletar Rascunho
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // 1. Img Previews (Simple Replace)
    function setupPrev(id) {
        document.getElementById(id).onchange = function() {
            if (this.files[0]) {
                const r = new FileReader();
                r.onload = e => {
                    let img = document.getElementById('prev-' + id);
                    if (!img) {
                        // If no image existed before
                        img = document.createElement('img');
                        img.id = 'prev-' + id;
                        img.className = 'preview-img';
                        this.parentElement.querySelector('.upload-zone').appendChild(img);
                    }
                    img.src = e.target.result;
                };
                r.readAsDataURL(this.files[0]);
            }
        };
    }
    setupPrev('capa');
    setupPrev('banner');

    // 2. New Screenshots
    document.getElementById('shotInput').onchange = function() {
        Array.from(this.files).forEach(f => {
            const r = new FileReader();
            r.onload = e => {
                const d = document.createElement('div');
                d.className = 'shot-item';
                d.innerHTML = `<img src="${e.target.result}"><button type="button" style="position:absolute;top:5px;right:5px;background:red;color:white;border:none" onclick="this.parentElement.remove()">X</button>`;
                // Insert before the add button
                const container = document.getElementById('shotContainer');
                container.insertBefore(d, container.lastElementChild);
            };
            r.readAsDataURL(f);
        });
    };

    // 3. MultiSelect with Pre-Load
    function initEditMulti(trigId, popId, inpId) {
        const trig = document.getElementById(trigId);
        const pop = document.getElementById(popId);
        const inp = document.getElementById(inpId);
        let selected = [];

        // Initialize from Hidden Input (PHP Data)
        if (inp.value) {
            const ids = inp.value.split(',');
            pop.querySelectorAll('.popover-item').forEach(i => {
                if (ids.includes(i.dataset.id)) selected.push({
                    id: i.dataset.id,
                    name: i.dataset.name
                });
            });
            render();
        }

        trig.onclick = e => {
            e.stopPropagation();
            pop.style.display = (pop.style.display == 'block' ? 'none' : 'block');
        };

        pop.querySelectorAll('.popover-item').forEach(item => {
            item.onclick = function() {
                const id = this.dataset.id;
                if (!selected.find(i => i.id == id)) {
                    selected.push({
                        id,
                        name: this.dataset.name
                    });
                    render();
                }
                pop.style.display = 'none';
            }
        });

        function render() {
            trig.innerHTML = selected.length ? '' : '<span style="color:#888; padding:5px">Selecionar...</span>';
            selected.forEach(s => {
                trig.innerHTML += `<div class="chip">${s.name} <i class="fas fa-times" onclick="remove('${s.id}', '${trigId}')"></i></div>`;
            });
            inp.value = selected.map(s => s.id).join(',');

            // Visual Feedback in List
            pop.querySelectorAll('.popover-item').forEach(i => {
                i.style.background = selected.find(s => s.id == i.dataset.id) ? 'var(--bg-primary)' : '';
                i.style.opacity = selected.find(s => s.id == i.dataset.id) ? '0.5' : '1';
            });
        }

        // Assign remove handler to this specific instance
        trig.removeHandler = function(id) {
            selected = selected.filter(s => s.id !== id);
            render();
        };
    }

    // Global Delegator for Removes
    window.remove = function(id, trigId) {
        document.getElementById(trigId).removeHandler(id);
    };
    document.addEventListener('click', e => {
        if (!e.target.closest('.chips-input')) document.querySelectorAll('.popover-list').forEach(p => p.style.display = 'none');
    });

    initEditMulti('triggerCat', 'popCat', 'inputCat');
    initEditMulti('triggerTag', 'popTag', 'inputTag');

    // 4. Platforms Pre-load
    const platBtns = document.querySelectorAll('.plat-btn');
    const platInp = document.getElementById('inputPlat');
    let plats = platInp.value ? platInp.value.split(',') : [];

    function updatePlats() {
        platBtns.forEach(b => {
            if (plats.includes(b.dataset.id)) b.classList.add('active');
            else b.classList.remove('active');
        });
        platInp.value = plats.join(',');
    }
    updatePlats();

    platBtns.forEach(b => {
        b.onclick = function() {
            const id = this.dataset.id;
            if (plats.includes(id)) plats = plats.filter(p => p !== id);
            else plats.push(id);
            updatePlats();
        }
    });
</script>
<?php require_once '../includes/footer.php'; ?>