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

$success = '';
$error = '';

// --- ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? 'save';

    // 1. DELETE DRAFT
    if ($action == 'delete' && $jogo['status'] == 'rascunho') {
        $pdo->prepare("DELETE FROM jogo WHERE id = ?")->execute([$jogo_id]);
        header('Location: jogos.php?deleted=1');
        exit;
    }

    // 2. PUBLISH - Enviar para Revisão
    if ($action == 'publish' && $jogo['status'] == 'rascunho') {
        try {
            // Validações antes de enviar para revisão
            $errors = [];
            
            if (empty($jogo['titulo']) || strlen($jogo['titulo']) < 3) {
                $errors[] = 'Título deve ter pelo menos 3 caracteres';
            }
            if (empty($jogo['descricao_curta']) || strlen($jogo['descricao_curta']) < 20) {
                $errors[] = 'Descrição curta deve ter pelo menos 20 caracteres';
            }
            if (empty($jogo['descricao_completa'])) {
                $errors[] = 'Descrição completa é obrigatória';
            }
            if (empty($jogo['imagem_capa'])) {
                $errors[] = 'Imagem de capa é obrigatória';
            }
            
            // Verificar categorias
            $cat_count = $pdo->query("SELECT COUNT(*) FROM jogo_categoria WHERE jogo_id = $jogo_id")->fetchColumn();
            if ($cat_count == 0) {
                $errors[] = 'Selecione pelo menos uma categoria';
            }
            
            if (!empty($errors)) {
                $error = implode('<br>', $errors);
            } else {
                $stmt = $pdo->prepare("UPDATE jogo SET status = 'em_revisao', atualizado_em = NOW() WHERE id = ?");
                $stmt->execute([$jogo_id]);
                $success = 'Jogo enviado para revisão com sucesso! Você será notificado quando a análise for concluída.';
                
                // Atualizar dados do jogo na memória
                $jogo['status'] = 'em_revisao';
            }
        } catch (Exception $e) {
            $error = 'Erro ao enviar para revisão: ' . $e->getMessage();
        }
    }

    // 3. SAVE UPDATE
    if ($action == 'save') {
        try {
            $pdo->beginTransaction();

            $slug = $jogo['slug'];
            $upload_dir = '../uploads/jogos/' . $slug;

            // Criar diretório se não existir
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            if (!is_dir($upload_dir . '/screenshots')) {
                mkdir($upload_dir . '/screenshots', 0755, true);
            }

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
            if (!empty($_FILES['capa']['name']) && $_FILES['capa']['error'] == 0) {
                $ext = strtolower(pathinfo($_FILES['capa']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                    $capa_path = "$upload_dir/capa.$ext";
                    move_uploaded_file($_FILES['capa']['tmp_name'], $capa_path);
                    $pdo->prepare("UPDATE jogo SET imagem_capa=? WHERE id=?")->execute(["/uploads/jogos/$slug/capa.$ext", $jogo_id]);
                }
            }
            
            if (!empty($_FILES['banner']['name']) && $_FILES['banner']['error'] == 0) {
                $ext = strtolower(pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                    $banner_path = "$upload_dir/banner.$ext";
                    move_uploaded_file($_FILES['banner']['tmp_name'], $banner_path);
                    $pdo->prepare("UPDATE jogo SET imagem_banner=? WHERE id=?")->execute(["/uploads/jogos/$slug/banner.$ext", $jogo_id]);
                }
            }

            // New Screenshots
            if (!empty($_FILES['screenshots']['name'][0])) {
                $next_ordem = $pdo->query("SELECT COALESCE(MAX(ordem), 0) FROM jogo_imagens WHERE jogo_id=$jogo_id")->fetchColumn() + 1;
                foreach ($_FILES['screenshots']['tmp_name'] as $k => $tmp) {
                    if ($_FILES['screenshots']['error'][$k] == 0) {
                        $ext = strtolower(pathinfo($_FILES['screenshots']['name'][$k], PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                            $fname = time() . "-$k.$ext";
                            move_uploaded_file($tmp, "$upload_dir/screenshots/$fname");
                            $pdo->prepare("INSERT INTO jogo_imagens (jogo_id, imagem, ordem) VALUES (?,?,?)")->execute([$jogo_id, "/uploads/jogos/$slug/screenshots/$fname", $next_ordem++]);
                        }
                    }
                }
            }

            // Delete Marked Screenshots
            if (isset($_POST['delete_imgs']) && is_array($_POST['delete_imgs'])) {
                foreach ($_POST['delete_imgs'] as $img_id) {
                    // Buscar e deletar arquivo
                    $img_stmt = $pdo->prepare("SELECT imagem FROM jogo_imagens WHERE id = ? AND jogo_id = ?");
                    $img_stmt->execute([$img_id, $jogo_id]);
                    $img_data = $img_stmt->fetch();
                    if ($img_data && file_exists('..' . $img_data['imagem'])) {
                        unlink('..' . $img_data['imagem']);
                    }
                    $pdo->prepare("DELETE FROM jogo_imagens WHERE id=? AND jogo_id=?")->execute([$img_id, $jogo_id]);
                }
            }

            // Relations Update (Wipe & Re-insert)
            $pdo->prepare("DELETE FROM jogo_categoria WHERE jogo_id=?")->execute([$jogo_id]);
            if (!empty($_POST['cats_selecionadas'])) {
                foreach (explode(',', $_POST['cats_selecionadas']) as $c) {
                    if ((int)$c > 0) {
                        $pdo->prepare("INSERT INTO jogo_categoria (jogo_id, categoria_id) VALUES (?,?)")->execute([$jogo_id, (int)$c]);
                    }
                }
            }

            $pdo->prepare("DELETE FROM jogo_tag WHERE jogo_id=?")->execute([$jogo_id]);
            if (!empty($_POST['tags_selecionadas'])) {
                foreach (explode(',', $_POST['tags_selecionadas']) as $t) {
                    if ((int)$t > 0) {
                        $pdo->prepare("INSERT INTO jogo_tag (jogo_id, tag_id) VALUES (?,?)")->execute([$jogo_id, (int)$t]);
                    }
                }
            }

            $pdo->prepare("DELETE FROM jogo_plataforma WHERE jogo_id=?")->execute([$jogo_id]);
            if (!empty($_POST['plats_selecionadas'])) {
                foreach (explode(',', $_POST['plats_selecionadas']) as $p) {
                    if ((int)$p > 0) {
                        $pdo->prepare("INSERT INTO jogo_plataforma (jogo_id, plataforma_id) VALUES (?,?)")->execute([$jogo_id, (int)$p]);
                    }
                }
            }

            $pdo->commit();
            header("Location: editar-jogo.php?id=$jogo_id&success=1");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Erro ao salvar: ' . $e->getMessage();
        }
    }
}

// --- DATA FETCHING ---
// Recarregar dados do jogo após possíveis alterações
$stmt = $pdo->prepare("SELECT * FROM jogo WHERE id = ? AND desenvolvedor_id = ?");
$stmt->execute([$jogo_id, $dev['id']]);
$jogo = $stmt->fetch();

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

// Status labels e cores
$status_info = [
    'rascunho' => ['label' => 'Rascunho', 'color' => '#6b7280', 'icon' => 'fa-file-alt'],
    'em_revisao' => ['label' => 'Em Revisão', 'color' => '#f59e0b', 'icon' => 'fa-clock'],
    'publicado' => ['label' => 'Publicado', 'color' => '#10b981', 'icon' => 'fa-check-circle'],
    'suspenso' => ['label' => 'Suspenso', 'color' => '#ef4444', 'icon' => 'fa-ban'],
    'removido' => ['label' => 'Removido', 'color' => '#dc2626', 'icon' => 'fa-trash']
];
$current_status = $status_info[$jogo['status']] ?? $status_info['rascunho'];

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

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .page-header-info h1 {
        font-size: 26px;
        font-weight: 700;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .page-header-meta {
        display: flex;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
        color: white;
    }

    .page-header-actions {
        display: flex;
        gap: 10px;
        align-items: center;
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
        border-radius: 16px;
        padding: 25px;
        margin-bottom: 25px;
        transition: border-color 0.2s;
    }

    .form-box:hover {
        border-color: var(--accent);
    }

    .box-title {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 20px;
        color: var(--accent);
        display: flex;
        align-items: center;
        gap: 10px;
        padding-bottom: 12px;
        border-bottom: 1px solid var(--border);
    }

    .form-group {
        margin-bottom: 18px;
    }

    .form-group:last-child {
        margin-bottom: 0;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        font-size: 14px;
        color: var(--text-secondary);
    }

    .form-control {
        width: 100%;
        padding: 12px 16px;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 10px;
        color: var(--text-primary);
        font-size: 14px;
        transition: all 0.2s;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.15);
    }

    .form-control::placeholder {
        color: var(--text-muted);
    }

    textarea.form-control {
        resize: vertical;
        min-height: 100px;
    }

    .char-counter {
        text-align: right;
        font-size: 12px;
        color: var(--text-muted);
        margin-top: 5px;
    }

    .char-counter.warning {
        color: #f59e0b;
    }

    .char-counter.error {
        color: #ef4444;
    }

    .upload-zone {
        position: relative;
        width: 100%;
        background: var(--bg-primary);
        border: 2px dashed var(--border);
        border-radius: 12px;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        transition: all 0.3s;
    }

    .upload-zone:hover {
        border-color: var(--accent);
        background: rgba(139, 92, 246, 0.05);
    }

    .upload-zone.has-image {
        border-style: solid;
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

    .upload-placeholder {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        color: var(--text-muted);
        padding: 20px;
        text-align: center;
    }

    .upload-placeholder i {
        font-size: 32px;
        opacity: 0.5;
    }

    .upload-placeholder span {
        font-size: 13px;
    }

    .chips-input {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        padding: 12px;
        border: 1px solid var(--border);
        border-radius: 10px;
        background: var(--bg-primary);
        min-height: 50px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .chips-input:hover {
        border-color: var(--accent);
    }

    .chips-placeholder {
        color: var(--text-muted);
        font-size: 14px;
        padding: 4px;
    }

    .chip {
        background: var(--accent);
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 13px;
        display: flex;
        align-items: center;
        gap: 8px;
        animation: chipIn 0.2s ease;
    }

    @keyframes chipIn {
        from {
            transform: scale(0.8);
            opacity: 0;
        }
        to {
            transform: scale(1);
            opacity: 1;
        }
    }

    .chip i {
        cursor: pointer;
        opacity: 0.8;
        transition: opacity 0.2s;
    }

    .chip i:hover {
        opacity: 1;
    }

    .popover-list {
        display: none;
        position: absolute;
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 12px;
        width: 100%;
        z-index: 100;
        max-height: 280px;
        overflow-y: auto;
        margin-top: 8px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        animation: popIn 0.2s ease;
    }

    @keyframes popIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .popover-list::-webkit-scrollbar {
        width: 6px;
    }

    .popover-list::-webkit-scrollbar-thumb {
        background: var(--border);
        border-radius: 3px;
    }

    .popover-item {
        padding: 12px 16px;
        cursor: pointer;
        transition: all 0.15s;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .popover-item:hover {
        background: var(--bg-primary);
    }

    .popover-item.selected {
        background: rgba(139, 92, 246, 0.1);
        color: var(--accent);
    }

    .popover-item.selected::after {
        content: '\f00c';
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        margin-left: auto;
        color: var(--accent);
    }

    .plat-grid {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .plat-btn {
        padding: 10px 18px;
        border: 2px solid var(--border);
        border-radius: 10px;
        cursor: pointer;
        background: var(--bg-primary);
        user-select: none;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s;
        font-size: 14px;
    }

    .plat-btn:hover {
        border-color: var(--accent);
    }

    .plat-btn.active {
        background: var(--accent);
        color: white;
        border-color: transparent;
    }

    /* Screenshots */
    .shots-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 12px;
    }

    .shot-item {
        aspect-ratio: 16/9;
        position: relative;
        border-radius: 10px;
        overflow: hidden;
        border: 2px solid var(--border);
        transition: all 0.2s;
    }

    .shot-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .shot-item:hover {
        border-color: var(--accent);
        transform: scale(1.02);
    }

    .del-shot-overlay {
        position: absolute;
        inset: 0;
        background: rgba(239, 68, 68, 0.9);
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: 0.2s;
        cursor: pointer;
    }

    .shot-cb:checked + .del-shot-overlay {
        opacity: 1;
    }

    .shot-cb {
        display: none;
    }

    .add-shot {
        border: 2px dashed var(--border);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        cursor: pointer;
        aspect-ratio: 16/9;
        border-radius: 10px;
        transition: all 0.2s;
        color: var(--text-muted);
    }

    .add-shot:hover {
        border-color: var(--accent);
        color: var(--accent);
        background: rgba(139, 92, 246, 0.05);
    }

    .add-shot i {
        font-size: 24px;
    }

    .add-shot span {
        font-size: 12px;
    }

    /* Buttons */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px 24px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        border: none;
        text-decoration: none;
    }

    .btn-primary {
        background: var(--accent);
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 20px rgba(46, 213, 255, 0.3);
    }

    .btn-success {
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
    }

    .btn-success:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 20px rgba(16, 185, 129, 0.4);
    }

    .btn-secondary {
        background: var(--bg-tertiary);
        color: var(--text-primary);
        border: 1px solid var(--border);
    }

    .btn-secondary:hover {
        background: var(--border);
    }

    .btn-danger-outline {
        width: 100%;
        background: transparent;
        border: 2px solid #ef4444;
        color: #ef4444;
        padding: 12px;
        border-radius: 10px;
        margin-top: 15px;
        cursor: pointer;
        transition: 0.3s;
        font-weight: 600;
    }

    .btn-danger-outline:hover {
        background: #ef4444;
        color: white;
    }

    .btn-lg {
        padding: 14px 28px;
        font-size: 15px;
    }

    /* Alerts */
    .alert {
        padding: 16px 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .alert i {
        font-size: 18px;
        margin-top: 2px;
    }

    .alert-success {
        background: rgba(16, 185, 129, 0.15);
        border: 1px solid rgba(16, 185, 129, 0.3);
        color: #10b981;
    }

    .alert-error {
        background: rgba(239, 68, 68, 0.15);
        border: 1px solid rgba(239, 68, 68, 0.3);
        color: #ef4444;
    }

    .alert-warning {
        background: rgba(245, 158, 11, 0.15);
        border: 1px solid rgba(245, 158, 11, 0.3);
        color: #f59e0b;
    }

    .alert-info {
        background: rgba(59, 130, 246, 0.15);
        border: 1px solid rgba(59, 130, 246, 0.3);
        color: #3b82f6;
    }

    /* Checklist */
    .checklist {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-top: 15px;
    }

    .checklist-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
        background: var(--bg-primary);
        border-radius: 8px;
        font-size: 13px;
    }

    .checklist-item.complete {
        color: #10b981;
    }

    .checklist-item.incomplete {
        color: var(--text-muted);
    }

    .checklist-item i {
        width: 18px;
    }

    /* Requirements Grid */
    .req-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .req-grid .form-group label {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    /* Toast Notification */
    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
    }

    .toast {
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 16px 20px;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        animation: toastIn 0.3s ease;
        min-width: 300px;
    }

    @keyframes toastIn {
        from {
            opacity: 0;
            transform: translateX(100px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .toast.success {
        border-left: 4px solid #10b981;
    }

    .toast.error {
        border-left: 4px solid #ef4444;
    }

    .toast.warning {
        border-left: 4px solid #f59e0b;
    }

    /* Modal */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.7);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s;
    }

    .modal-overlay.active {
        opacity: 1;
        visibility: visible;
    }

    .modal-box {
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 30px;
        max-width: 450px;
        width: 90%;
        text-align: center;
        transform: scale(0.9);
        transition: transform 0.3s;
    }

    .modal-overlay.active .modal-box {
        transform: scale(1);
    }

    .modal-icon {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        font-size: 30px;
    }

    .modal-icon.warning {
        background: rgba(245, 158, 11, 0.15);
        color: #f59e0b;
    }

    .modal-icon.danger {
        background: rgba(239, 68, 68, 0.15);
        color: #ef4444;
    }

    .modal-icon.success {
        background: rgba(16, 185, 129, 0.15);
        color: #10b981;
    }

    .modal-title {
        font-size: 20px;
        font-weight: 700;
        margin-bottom: 10px;
    }

    .modal-text {
        color: var(--text-secondary);
        margin-bottom: 25px;
        line-height: 1.6;
    }

    .modal-actions {
        display: flex;
        gap: 12px;
        justify-content: center;
    }

    .modal-actions .btn {
        min-width: 120px;
    }

    @media(max-width: 992px) {
        .dev-layout {
            grid-template-columns: 1fr;
        }

        .publish-grid {
            grid-template-columns: 1fr;
        }

        .req-grid {
            grid-template-columns: 1fr;
        }
    }

    @media(max-width: 576px) {
        .page-header {
            flex-direction: column;
        }

        .page-header-actions {
            width: 100%;
        }

        .page-header-actions .btn {
            flex: 1;
        }
    }
</style>

<div class="container">
    <div class="dev-layout">
        <?php require_once 'includes/sidebar.php'; ?>

        <div class="dev-content">
            <div class="publish-wrapper">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="page-header-info">
                        <h1>
                            <i class="fas fa-edit"></i>
                            Editar Jogo
                        </h1>
                        <div class="page-header-meta">
                            <span class="status-badge" style="background: <?= $current_status['color'] ?>">
                                <i class="fas <?= $current_status['icon'] ?>"></i>
                                <?= $current_status['label'] ?>
                            </span>
                            <span style="color: var(--text-secondary); font-size: 14px;">
                                Atualizado: <?= date('d/m/Y H:i', strtotime($jogo['atualizado_em'])) ?>
                            </span>
                        </div>
                    </div>
                    <div class="page-header-actions">
                        <a href="<?= SITE_URL ?>/pages/jogo.php?slug=<?= $jogo['slug'] ?>" target="_blank" class="btn btn-secondary">
                            <i class="fas fa-external-link-alt"></i> Ver Página
                        </a>
                    </div>
                </div>

                <!-- Alerts -->
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <span>Alterações salvas com sucesso!</span>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <span><?= $success ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?= $error ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($jogo['status'] == 'em_revisao'): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-clock"></i>
                        <div>
                            <strong>Jogo em análise</strong><br>
                            <span style="font-size: 13px;">Seu jogo está sendo revisado pela nossa equipe. Você será notificado quando a análise for concluída.</span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($jogo['status'] == 'suspenso' && $jogo['motivo_rejeicao']): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <strong>Jogo suspenso</strong><br>
                            <span style="font-size: 13px;">Motivo: <?= sanitize($jogo['motivo_rejeicao']) ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <form id="editForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" id="formAction" value="save">

                    <div class="publish-grid">
                        <div class="main-col">
                            <!-- Informações Básicas -->
                            <div class="form-box">
                                <div class="box-title"><i class="fas fa-info-circle"></i> Informações Básicas</div>
                                <div class="form-group">
                                    <label>Título do Jogo *</label>
                                    <input type="text" name="titulo" class="form-control" value="<?= sanitize($jogo['titulo']) ?>" required maxlength="200" placeholder="Nome do seu jogo">
                                </div>
                                <div class="form-group">
                                    <label>Descrição Curta *</label>
                                    <input type="text" name="descricao_curta" id="descCurta" class="form-control" value="<?= sanitize($jogo['descricao_curta']) ?>" required maxlength="500" placeholder="Uma breve descrição que aparece nos cards">
                                    <div class="char-counter" id="descCurtaCounter"><?= strlen($jogo['descricao_curta']) ?>/500</div>
                                </div>
                                <div class="form-group">
                                    <label>Descrição Completa</label>
                                    <textarea name="descricao_completa" class="form-control" rows="8" placeholder="Descreva detalhadamente seu jogo, história, mecânicas..."><?= sanitize($jogo['descricao_completa']) ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label><i class="fab fa-youtube" style="color: #ff0000;"></i> Trailer (YouTube)</label>
                                    <input type="url" name="video_trailer" class="form-control" value="<?= sanitize($jogo['video_trailer']) ?>" placeholder="https://www.youtube.com/watch?v=...">
                                </div>
                            </div>

                            <!-- Categorias e Tags -->
                            <div class="form-box">
                                <div class="box-title"><i class="fas fa-tags"></i> Categorias e Tags</div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                    <div class="form-group" style="position: relative;">
                                        <label>Categorias *</label>
                                        <div class="chips-input" id="triggerCat">
                                            <span class="chips-placeholder">Clique para selecionar...</span>
                                        </div>
                                        <div class="popover-list" id="popCat">
                                            <?php foreach ($categorias as $c): ?>
                                                <div class="popover-item" data-id="<?= $c['id'] ?>" data-name="<?= sanitize($c['nome']) ?>"><?= sanitize($c['nome']) ?></div>
                                            <?php endforeach; ?>
                                        </div>
                                        <input type="hidden" name="cats_selecionadas" id="inputCat" value="<?= implode(',', $my_cats) ?>">
                                    </div>
                                    <div class="form-group" style="position: relative;">
                                        <label>Tags</label>
                                        <div class="chips-input" id="triggerTag">
                                            <span class="chips-placeholder">Clique para selecionar...</span>
                                        </div>
                                        <div class="popover-list" id="popTag">
                                            <?php foreach ($tags as $t): ?>
                                                <div class="popover-item" data-id="<?= $t['id'] ?>" data-name="<?= sanitize($t['nome']) ?>"><?= sanitize($t['nome']) ?></div>
                                            <?php endforeach; ?>
                                        </div>
                                        <input type="hidden" name="tags_selecionadas" id="inputTag" value="<?= implode(',', $my_tags) ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- Plataformas e Requisitos -->
                            <div class="form-box">
                                <div class="box-title"><i class="fas fa-desktop"></i> Plataformas e Requisitos</div>
                                <div class="form-group">
                                    <label>Plataformas Suportadas</label>
                                    <div class="plat-grid">
                                        <?php foreach ($plataformas as $p): ?>
                                            <div class="plat-btn <?= in_array($p['id'], $my_plats) ? 'active' : '' ?>" data-id="<?= $p['id'] ?>">
                                                <i class="<?= $p['icone'] ?>"></i> <?= $p['nome'] ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="hidden" name="plats_selecionadas" id="inputPlat" value="<?= implode(',', $my_plats) ?>">
                                </div>
                                <div class="req-grid">
                                    <div class="form-group">
                                        <label><i class="fas fa-microchip"></i> Requisitos Mínimos</label>
                                        <textarea name="requisitos_minimos" class="form-control" rows="5" placeholder="SO: Windows 10&#10;Processador: Intel i3&#10;Memória: 4 GB RAM&#10;GPU: GTX 750"><?= sanitize($jogo['requisitos_minimos']) ?></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label><i class="fas fa-rocket"></i> Requisitos Recomendados</label>
                                        <textarea name="requisitos_recomendados" class="form-control" rows="5" placeholder="SO: Windows 11&#10;Processador: Intel i5&#10;Memória: 8 GB RAM&#10;GPU: GTX 1060"><?= sanitize($jogo['requisitos_recomendados']) ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Screenshots -->
                            <div class="form-box">
                                <div class="box-title"><i class="fas fa-images"></i> Screenshots</div>
                                <div class="shots-grid" id="shotContainer">
                                    <?php foreach ($screenshots as $img): ?>
                                        <label class="shot-item">
                                            <img src="<?= SITE_URL . $img['imagem'] ?>" alt="">
                                            <input type="checkbox" name="delete_imgs[]" value="<?= $img['id'] ?>" class="shot-cb">
                                            <div class="del-shot-overlay">
                                                <i class="fas fa-trash fa-lg" style="color:white"></i>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>

                                    <label class="add-shot">
                                        <i class="fas fa-plus"></i>
                                        <span>Adicionar</span>
                                        <input type="file" name="screenshots[]" multiple accept="image/*" style="display:none" id="shotInput">
                                    </label>
                                </div>
                                <small style="display:block; margin-top:12px; color:var(--text-muted)">
                                    <i class="fas fa-info-circle"></i> Clique nas imagens para marcar para exclusão.
                                </small>
                            </div>
                        </div>

                        <!-- Sidebar -->
                        <div class="side-col">
                            <!-- Mídia -->
                            <div class="form-box">
                                <div class="box-title"><i class="fas fa-image"></i> Imagens</div>
                                <div class="form-group">
                                    <label>Capa do Jogo (3:4) *</label>
                                    <div class="upload-zone capa <?= $jogo['imagem_capa'] ? 'has-image' : '' ?>" onclick="document.getElementById('capa').click()">
                                        <?php if ($jogo['imagem_capa']): ?>
                                            <img src="<?= SITE_URL . $jogo['imagem_capa'] ?>" id="prev-capa" class="preview-img">
                                        <?php else: ?>
                                            <div class="upload-placeholder" id="placeholder-capa">
                                                <i class="fas fa-cloud-upload-alt"></i>
                                                <span>Clique para enviar</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <input type="file" name="capa" id="capa" style="display:none" accept="image/*">
                                </div>

                                <div class="form-group">
                                    <label>Banner (16:9)</label>
                                    <div class="upload-zone banner <?= $jogo['imagem_banner'] ? 'has-image' : '' ?>" onclick="document.getElementById('banner').click()">
                                        <?php if ($jogo['imagem_banner']): ?>
                                            <img src="<?= SITE_URL . $jogo['imagem_banner'] ?>" id="prev-banner" class="preview-img">
                                        <?php else: ?>
                                            <div class="upload-placeholder" id="placeholder-banner">
                                                <i class="fas fa-panorama"></i>
                                                <span>Clique para enviar</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <input type="file" name="banner" id="banner" style="display:none" accept="image/*">
                                </div>
                            </div>

                            <!-- Preço -->
                            <div class="form-box">
                                <div class="box-title"><i class="fas fa-tag"></i> Preço e Classificação</div>
                                <div class="form-group">
                                    <label>Preço (R$)</label>
                                    <input type="number" name="preco" class="form-control" step="0.01" min="0" value="<?= number_format($jogo['preco_centavos'] / 100, 2, '.', '') ?>" placeholder="0.00">
                                    <small style="color: var(--text-muted); display: block; margin-top: 5px;">Deixe 0 para gratuito</small>
                                </div>

                                <div class="form-group">
                                    <label>Classificação Etária</label>
                                    <select name="classificacao" class="form-control">
                                        <?php 
                                        $classificacoes = ['L' => 'Livre', '10' => '10 anos', '12' => '12 anos', '14' => '14 anos', '16' => '16 anos', '18' => '18 anos'];
                                        foreach ($classificacoes as $val => $label): 
                                        ?>
                                            <option value="<?= $val ?>" <?= $jogo['classificacao_etaria'] == $val ? 'selected' : '' ?>><?= $label ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Checklist (apenas para rascunhos) -->
                            <?php if ($jogo['status'] == 'rascunho'): ?>
                            <div class="form-box">
                                <div class="box-title"><i class="fas fa-clipboard-check"></i> Checklist</div>
                                <small style="color: var(--text-muted); display: block; margin-bottom: 10px;">Complete antes de enviar:</small>
                                <?php
                                $checks = [
                                    ['check' => !empty($jogo['titulo']) && strlen($jogo['titulo']) >= 3, 'text' => 'Título (mín. 3 caracteres)'],
                                    ['check' => !empty($jogo['descricao_curta']) && strlen($jogo['descricao_curta']) >= 20, 'text' => 'Descrição curta (mín. 20)'],
                                    ['check' => !empty($jogo['descricao_completa']), 'text' => 'Descrição completa'],
                                    ['check' => !empty($jogo['imagem_capa']), 'text' => 'Imagem de capa'],
                                    ['check' => !empty($my_cats), 'text' => 'Pelo menos 1 categoria'],
                                ];
                                ?>
                                <div class="checklist">
                                    <?php foreach ($checks as $item): ?>
                                    <div class="checklist-item <?= $item['check'] ? 'complete' : 'incomplete' ?>">
                                        <i class="fas <?= $item['check'] ? 'fa-check-circle' : 'fa-circle' ?>"></i>
                                        <span><?= $item['text'] ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Ações -->
                            <div class="form-box" style="background: linear-gradient(135deg, var(--bg-secondary), var(--bg-tertiary));">
                                <button type="submit" class="btn btn-primary btn-lg" style="width:100%">
                                    <i class="fas fa-save"></i> Salvar Alterações
                                </button>

                                <?php if ($jogo['status'] == 'rascunho'): ?>
                                    <button type="button" id="btnPublish" class="btn btn-success btn-lg" style="width:100%; margin-top: 12px;">
                                        <i class="fas fa-paper-plane"></i> Enviar para Revisão
                                    </button>
                                <?php endif; ?>

                                <?php if ($jogo['status'] == 'rascunho'): ?>
                                    <button type="button" id="btnDelete" class="btn-danger-outline">
                                        <i class="fas fa-trash"></i> Deletar Rascunho
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<!-- Modal de Confirmação -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal-box">
        <div class="modal-icon" id="modalIcon">
            <i class="fas fa-question"></i>
        </div>
        <h3 class="modal-title" id="modalTitle">Confirmação</h3>
        <p class="modal-text" id="modalText">Tem certeza?</p>
        <div class="modal-actions">
            <button class="btn btn-secondary" id="modalCancel">Cancelar</button>
            <button class="btn btn-primary" id="modalConfirm">Confirmar</button>
        </div>
    </div>
</div>

<script>
    // Toast notification
    function showToast(message, type = 'success') {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
            <span>${message}</span>
        `;
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100px)';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    // Modal
    function showModal(title, text, iconClass, iconType, onConfirm, confirmText = 'Confirmar', confirmClass = 'btn-primary') {
        const overlay = document.getElementById('modalOverlay');
        const modalIcon = document.getElementById('modalIcon');
        const modalTitle = document.getElementById('modalTitle');
        const modalText = document.getElementById('modalText');
        const modalConfirm = document.getElementById('modalConfirm');
        
        modalIcon.className = `modal-icon ${iconType}`;
        modalIcon.innerHTML = `<i class="fas ${iconClass}"></i>`;
        modalTitle.textContent = title;
        modalText.innerHTML = text;
        modalConfirm.textContent = confirmText;
        modalConfirm.className = `btn ${confirmClass}`;
        
        overlay.classList.add('active');
        
        // Remove old listeners
        const newConfirm = modalConfirm.cloneNode(true);
        modalConfirm.parentNode.replaceChild(newConfirm, modalConfirm);
        
        newConfirm.onclick = () => {
            overlay.classList.remove('active');
            if (onConfirm) onConfirm();
        };
        
        document.getElementById('modalCancel').onclick = () => overlay.classList.remove('active');
    }

    document.getElementById('modalOverlay').addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('active');
    });

    // Character counter
    const descCurta = document.getElementById('descCurta');
    const descCurtaCounter = document.getElementById('descCurtaCounter');
    if (descCurta) {
        descCurta.addEventListener('input', function() {
            const len = this.value.length;
            descCurtaCounter.textContent = `${len}/500`;
            descCurtaCounter.className = 'char-counter';
            if (len > 450) descCurtaCounter.classList.add('warning');
            if (len >= 500) descCurtaCounter.classList.add('error');
        });
    }

    // Image Previews
    function setupPrev(id) {
        const input = document.getElementById(id);
        if (!input) return;
        
        input.onchange = function() {
            if (this.files[0]) {
                const reader = new FileReader();
                reader.onload = e => {
                    let img = document.getElementById('prev-' + id);
                    const zone = this.closest('.form-group').querySelector('.upload-zone');
                    const placeholder = document.getElementById('placeholder-' + id);
                    
                    if (!img) {
                        img = document.createElement('img');
                        img.id = 'prev-' + id;
                        img.className = 'preview-img';
                        zone.appendChild(img);
                    }
                    
                    img.src = e.target.result;
                    zone.classList.add('has-image');
                    if (placeholder) placeholder.style.display = 'none';
                };
                reader.readAsDataURL(this.files[0]);
            }
        };
    }
    setupPrev('capa');
    setupPrev('banner');

    // New Screenshots Preview
    document.getElementById('shotInput').onchange = function() {
        Array.from(this.files).forEach(f => {
            const reader = new FileReader();
            reader.onload = e => {
                const div = document.createElement('div');
                div.className = 'shot-item';
                div.style.position = 'relative';
                div.innerHTML = `
                    <img src="${e.target.result}" alt="">
                    <button type="button" style="position:absolute;top:5px;right:5px;background:#ef4444;color:white;border:none;border-radius:50%;width:24px;height:24px;cursor:pointer;font-size:12px;" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                const container = document.getElementById('shotContainer');
                container.insertBefore(div, container.lastElementChild);
            };
            reader.readAsDataURL(f);
        });
    };

    // MultiSelect with Chips (Categories/Tags)
    function initEditMulti(trigId, popId, inpId) {
        const trig = document.getElementById(trigId);
        const pop = document.getElementById(popId);
        const inp = document.getElementById(inpId);
        let selected = [];

        // Initialize from PHP data
        if (inp.value) {
            const ids = inp.value.split(',').filter(id => id);
            pop.querySelectorAll('.popover-item').forEach(item => {
                if (ids.includes(item.dataset.id)) {
                    selected.push({ id: item.dataset.id, name: item.dataset.name });
                }
            });
            render();
        }

        trig.onclick = e => {
            e.stopPropagation();
            // Close other popovers
            document.querySelectorAll('.popover-list').forEach(p => {
                if (p !== pop) p.style.display = 'none';
            });
            pop.style.display = pop.style.display === 'block' ? 'none' : 'block';
        };

        pop.querySelectorAll('.popover-item').forEach(item => {
            item.onclick = function(e) {
                e.stopPropagation();
                const id = this.dataset.id;
                const existing = selected.find(s => s.id === id);
                
                if (existing) {
                    selected = selected.filter(s => s.id !== id);
                } else {
                    selected.push({ id, name: this.dataset.name });
                }
                render();
            };
        });

        function render() {
            if (selected.length === 0) {
                trig.innerHTML = '<span class="chips-placeholder">Clique para selecionar...</span>';
            } else {
                trig.innerHTML = selected.map(s => 
                    `<div class="chip">${s.name} <i class="fas fa-times" data-id="${s.id}"></i></div>`
                ).join('');
                
                // Add remove handlers
                trig.querySelectorAll('.chip i').forEach(icon => {
                    icon.onclick = function(e) {
                        e.stopPropagation();
                        selected = selected.filter(s => s.id !== this.dataset.id);
                        render();
                    };
                });
            }
            
            inp.value = selected.map(s => s.id).join(',');

            // Update popover visual
            pop.querySelectorAll('.popover-item').forEach(item => {
                const isSelected = selected.find(s => s.id === item.dataset.id);
                item.classList.toggle('selected', !!isSelected);
            });
        }
    }

    // Close popovers on outside click
    document.addEventListener('click', e => {
        if (!e.target.closest('.chips-input') && !e.target.closest('.popover-list')) {
            document.querySelectorAll('.popover-list').forEach(p => p.style.display = 'none');
        }
    });

    initEditMulti('triggerCat', 'popCat', 'inputCat');
    initEditMulti('triggerTag', 'popTag', 'inputTag');

    // Platforms
    const platBtns = document.querySelectorAll('.plat-btn');
    const platInp = document.getElementById('inputPlat');
    let plats = platInp.value ? platInp.value.split(',').filter(p => p) : [];

    function updatePlats() {
        platBtns.forEach(btn => {
            btn.classList.toggle('active', plats.includes(btn.dataset.id));
        });
        platInp.value = plats.join(',');
    }
    updatePlats();

    platBtns.forEach(btn => {
        btn.onclick = function() {
            const id = this.dataset.id;
            if (plats.includes(id)) {
                plats = plats.filter(p => p !== id);
            } else {
                plats.push(id);
            }
            updatePlats();
        };
    });

    // Publish Button - Enviar para Revisão
    const btnPublish = document.getElementById('btnPublish');
    if (btnPublish) {
        btnPublish.onclick = function() {
            showModal(
                'Enviar para Revisão',
                'Tem certeza que deseja enviar seu jogo para revisão?<br><br><small style="color:var(--text-muted)">Após enviado, você não poderá editar até a análise ser concluída. Nossa equipe verificará se o jogo atende às diretrizes da plataforma.</small>',
                'fa-paper-plane',
                'success',
                () => {
                    document.getElementById('formAction').value = 'publish';
                    document.getElementById('editForm').submit();
                },
                'Enviar',
                'btn-success'
            );
        };
    }

    // Delete Button
    const btnDelete = document.getElementById('btnDelete');
    if (btnDelete) {
        btnDelete.onclick = function() {
            showModal(
                'Deletar Rascunho',
                'Tem certeza que deseja deletar este rascunho?<br><br><strong style="color:#ef4444;">Esta ação não pode ser desfeita!</strong>',
                'fa-trash',
                'danger',
                () => {
                    document.getElementById('formAction').value = 'delete';
                    document.getElementById('editForm').submit();
                },
                'Deletar',
                'btn-danger-outline'
            );
        };
    }
</script>

<?php require_once '../includes/footer.php'; ?>