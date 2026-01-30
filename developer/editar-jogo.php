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
function compressImage($source, $destination, $maxWidth, $quality = 80)
{
    $info = getimagesize($source);
    if ($info === false) return false;

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

    if (!$image) return false;

    if ($width > $maxWidth) {
        $ratio = $maxWidth / $width;
        $newWidth = $maxWidth;
        $newHeight = (int)($height * $ratio);
    } else {
        $newWidth = $width;
        $newHeight = $height;
    }

    $newImage = imagecreatetruecolor($newWidth, $newHeight);

    if ($mime === 'image/png' || $mime === 'image/webp') {
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
        imagefill($newImage, 0, 0, $transparent);
    }

    imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

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
// VALIDAÇÃO DE TIPO
// ===========================================
function isValidImage($file, $allowed_types, $allowed_ext)
{
    if ($file['error'] !== UPLOAD_ERR_OK) return false;

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    return in_array($mime, $allowed_types) && in_array($ext, $allowed_ext);
}

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

            $cat_count = $pdo->query("SELECT COUNT(*) FROM jogo_categoria WHERE jogo_id = $jogo_id")->fetchColumn();
            if ($cat_count == 0) {
                $errors[] = 'Selecione pelo menos uma categoria';
            }

            if (!empty($errors)) {
                $error = implode('<br>', $errors);
            } else {
                $stmt = $pdo->prepare("UPDATE jogo SET status = 'em_revisao', atualizado_em = NOW() WHERE id = ?");
                $stmt->execute([$jogo_id]);
                $success = 'Jogo enviado para revisão com sucesso!';
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

            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            if (!is_dir($upload_dir . '/screenshots')) mkdir($upload_dir . '/screenshots', 0755, true);

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

            // Upload Capa (com compressão)
            if (!empty($_FILES['capa']['name']) && $_FILES['capa']['error'] === UPLOAD_ERR_OK) {
                if (isValidImage($_FILES['capa'], $allowed_types, $allowed_ext)) {
                    $ext = strtolower(pathinfo($_FILES['capa']['name'], PATHINFO_EXTENSION));
                    $target = "$upload_dir/capa.$ext";

                    if (compressImage($_FILES['capa']['tmp_name'], $target, $image_config['capa']['max_width'], COMPRESSION_QUALITY)) {
                        $pdo->prepare("UPDATE jogo SET imagem_capa=? WHERE id=?")->execute(["/uploads/jogos/$slug/capa.$ext", $jogo_id]);
                    }
                }
            }

            // Upload Banner (com compressão)
            if (!empty($_FILES['banner']['name']) && $_FILES['banner']['error'] === UPLOAD_ERR_OK) {
                if (isValidImage($_FILES['banner'], $allowed_types, $allowed_ext)) {
                    $ext = strtolower(pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION));
                    $target = "$upload_dir/banner.$ext";

                    if (compressImage($_FILES['banner']['tmp_name'], $target, $image_config['banner']['max_width'], COMPRESSION_QUALITY)) {
                        $pdo->prepare("UPDATE jogo SET imagem_banner=? WHERE id=?")->execute(["/uploads/jogos/$slug/banner.$ext", $jogo_id]);
                    }
                }
            }

            // New Screenshots (com compressão)
            if (!empty($_FILES['screenshots']['name'][0])) {
                $next_ordem = $pdo->query("SELECT COALESCE(MAX(ordem), 0) FROM jogo_imagens WHERE jogo_id=$jogo_id")->fetchColumn() + 1;

                foreach ($_FILES['screenshots']['tmp_name'] as $k => $tmp) {
                    if ($_FILES['screenshots']['error'][$k] === UPLOAD_ERR_OK) {
                        $shot_file = [
                            'name' => $_FILES['screenshots']['name'][$k],
                            'tmp_name' => $tmp,
                            'error' => $_FILES['screenshots']['error'][$k]
                        ];

                        if (isValidImage($shot_file, $allowed_types, $allowed_ext)) {
                            $ext = strtolower(pathinfo($_FILES['screenshots']['name'][$k], PATHINFO_EXTENSION));
                            $fname = time() . "-$k.$ext";
                            $target = "$upload_dir/screenshots/$fname";

                            if (compressImage($tmp, $target, $image_config['screenshots']['max_width'], COMPRESSION_QUALITY)) {
                                $pdo->prepare("INSERT INTO jogo_imagens (jogo_id, imagem, ordem) VALUES (?,?,?)")
                                    ->execute([$jogo_id, "/uploads/jogos/$slug/screenshots/$fname", $next_ordem++]);
                            }
                        }
                    }
                }
            }

            // Delete Marked Screenshots
            if (isset($_POST['delete_imgs']) && is_array($_POST['delete_imgs'])) {
                foreach ($_POST['delete_imgs'] as $img_id) {
                    $img_stmt = $pdo->prepare("SELECT imagem FROM jogo_imagens WHERE id = ? AND jogo_id = ?");
                    $img_stmt->execute([$img_id, $jogo_id]);
                    $img_data = $img_stmt->fetch();
                    if ($img_data && file_exists('..' . $img_data['imagem'])) {
                        unlink('..' . $img_data['imagem']);
                    }
                    $pdo->prepare("DELETE FROM jogo_imagens WHERE id=? AND jogo_id=?")->execute([$img_id, $jogo_id]);
                }
            }

            // Relations Update
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
$stmt = $pdo->prepare("SELECT * FROM jogo WHERE id = ? AND desenvolvedor_id = ?");
$stmt->execute([$jogo_id, $dev['id']]);
$jogo = $stmt->fetch();

$categorias = $pdo->query("SELECT * FROM categoria WHERE ativa=1 ORDER BY nome")->fetchAll();
$tags = $pdo->query("SELECT * FROM tag ORDER BY nome")->fetchAll();
$plataformas = $pdo->query("SELECT * FROM plataforma WHERE ativa=1 ORDER BY ordem")->fetchAll();
$imgs = $pdo->prepare("SELECT * FROM jogo_imagens WHERE jogo_id=? ORDER BY ordem");
$imgs->execute([$jogo_id]);
$screenshots = $imgs->fetchAll();

$my_cats = $pdo->query("SELECT categoria_id FROM jogo_categoria WHERE jogo_id=$jogo_id")->fetchAll(PDO::FETCH_COLUMN);
$my_tags = $pdo->query("SELECT tag_id FROM jogo_tag WHERE jogo_id=$jogo_id")->fetchAll(PDO::FETCH_COLUMN);
$my_plats = $pdo->query("SELECT plataforma_id FROM jogo_plataforma WHERE jogo_id=$jogo_id")->fetchAll(PDO::FETCH_COLUMN);

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

    .publish-grid {
        display: grid;
        grid-template-columns: 1fr 380px;
        gap: 30px;
        align-items: start;
    }

    /* Header */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .page-header h1 {
        font-size: 28px;
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 8px;
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

    /* Form Box */
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

    /* Uploads */
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
        transition: 0.3s;
        overflow: hidden;
    }

    .upload-zone:hover {
        border-color: var(--accent);
        background: rgba(var(--accent-rgb), 0.05);
    }

    .upload-zone.capa {
        aspect-ratio: 1/1;
    }

    .upload-zone.banner {
        aspect-ratio: 16/9;
    }

    .upload-zone.has-image {
        border-style: solid;
        border-color: var(--accent);
    }

    .preview-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        position: absolute;
        top: 0;
        left: 0;
    }

    .upload-placeholder {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        color: var(--text-secondary);
    }

    .upload-placeholder i {
        font-size: 2rem;
    }

    .upload-placeholder span {
        font-size: 12px;
    }

    .upload-info {
        font-size: 11px;
        color: var(--text-secondary);
        margin-top: 8px;
        text-align: center;
    }

    /* Tags & Chips */
    .multi-select-wrapper {
        position: relative;
    }

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
        align-items: center;
    }

    .chips-input:hover {
        border-color: var(--accent);
    }

    .chips-placeholder {
        color: #888;
        padding: 5px;
        font-size: 14px;
    }

    .chip {
        background: var(--accent);
        color: white;
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 13px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .chip i {
        cursor: pointer;
        opacity: 0.8;
        transition: 0.2s;
    }

    .chip i:hover {
        opacity: 1;
    }

    .popover-list {
        display: none;
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 8px;
        z-index: 100;
        max-height: 250px;
        overflow-y: auto;
        margin-top: 5px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }

    .popover-item {
        padding: 10px 15px;
        cursor: pointer;
        transition: 0.2s;
        font-size: 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .popover-item:hover {
        background: var(--bg-primary);
        color: var(--accent);
    }

    .popover-item.selected {
        background: rgba(var(--accent-rgb), 0.1);
        color: var(--accent);
    }

    .popover-item.selected::after {
        content: '✓';
        font-weight: bold;
    }

    /* Plataformas */
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
        display: flex;
        align-items: center;
        gap: 8px;
        transition: 0.2s;
        background: var(--bg-primary);
        user-select: none;
    }

    .plat-btn:hover {
        border-color: var(--accent);
    }

    .plat-btn.active {
        background: var(--accent);
        color: white;
        border-color: var(--accent);
    }

    /* Screenshots */
    .shots-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 10px;
    }

    .shot-item {
        aspect-ratio: 16/9;
        position: relative;
        border-radius: 6px;
        overflow: hidden;
        border: 1px solid var(--border);
        cursor: pointer;
    }

    .shot-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .shot-cb {
        display: none;
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
    }

    .shot-cb:checked+.del-shot-overlay {
        opacity: 1;
    }

    .add-shot {
        border: 2px dashed var(--border);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        aspect-ratio: 16/9;
        border-radius: 6px;
        transition: 0.3s;
        gap: 5px;
    }

    .add-shot:hover {
        border-color: var(--accent);
        color: var(--accent);
    }

    .add-shot i {
        font-size: 24px;
    }

    .add-shot span {
        font-size: 10px;
        color: var(--text-secondary);
    }

    /* Requisitos Grid */
    .req-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    /* Checklist */
    .checklist {
        display: flex;
        flex-direction: column;
        gap: 8px;
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

    /* Alerts */
    .alert {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }

    .alert-success {
        background: rgba(16, 185, 129, 0.1);
        border: 1px solid #10b981;
        color: #10b981;
    }

    .alert-error {
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid #ef4444;
        color: #ef4444;
    }

    .alert-info {
        background: rgba(59, 130, 246, 0.1);
        border: 1px solid #3b82f6;
        color: #3b82f6;
    }

    .alert-warning {
        background: rgba(245, 158, 11, 0.1);
        border: 1px solid #f59e0b;
        color: #f59e0b;
    }

    /* Buttons */
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
</style>

<div class="container">
    <div class="dev-layout">
        <?php require_once 'includes/sidebar.php'; ?>

        <div class="dev-content">
            <div class="publish-wrapper">
                <!-- Page Header -->
                <div class="page-header">
                    <div>
                        <h1><i class="fas fa-edit"></i> Editar Jogo</h1>
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
                    <a href="<?= SITE_URL ?>/pages/jogo.php?slug=<?= $jogo['slug'] ?>" target="_blank" class="btn btn-secondary">
                        <i class="fas fa-external-link-alt"></i> Ver Página
                    </a>
                </div>

                <!-- Alerts -->
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> Alterações salvas com sucesso!</div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
                <?php endif; ?>
                <?php if ($jogo['status'] == 'em_revisao'): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-clock"></i>
                        <div><strong>Jogo em análise</strong><br><span style="font-size: 13px;">Seu jogo está sendo revisado pela nossa equipe.</span></div>
                    </div>
                <?php endif; ?>
                <?php if ($jogo['status'] == 'suspenso' && $jogo['motivo_rejeicao']): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div><strong>Jogo suspenso</strong><br><span style="font-size: 13px;">Motivo: <?= sanitize($jogo['motivo_rejeicao']) ?></span></div>
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
                                    <input type="text" name="titulo" class="form-control" value="<?= sanitize($jogo['titulo']) ?>" required placeholder="Ex: A Lenda do Herói">
                                </div>
                                <div class="form-group">
                                    <label>Descrição Curta * (150 caracteres)</label>
                                    <input type="text" name="descricao_curta" class="form-control" maxlength="150" value="<?= sanitize($jogo['descricao_curta']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Descrição Completa</label>
                                    <textarea name="descricao_completa" class="form-control" rows="6"><?= sanitize($jogo['descricao_completa']) ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Trailer (Embed YouTube)</label>
                                    <input type="url" name="video_trailer" class="form-control" value="<?= sanitize($jogo['video_trailer']) ?>" placeholder="https://youtube.com/...">
                                </div>
                            </div>

                            <!-- Classificação -->
                            <div class="form-box">
                                <div class="box-title"><i class="fas fa-tags"></i> Classificação</div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                    <!-- Categorias -->
                                    <div class="multi-select-wrapper">
                                        <label>Categorias *</label>
                                        <div class="chips-input" data-target="cat">
                                            <span class="chips-placeholder">Selecionar categorias...</span>
                                        </div>
                                        <div class="popover-list" id="popover-cat">
                                            <?php foreach ($categorias as $c): ?>
                                                <div class="popover-item" data-id="<?= $c['id'] ?>" data-name="<?= sanitize($c['nome']) ?>">
                                                    <?= sanitize($c['nome']) ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <input type="hidden" name="cats_selecionadas" id="input-cat" value="<?= implode(',', $my_cats) ?>">
                                    </div>

                                    <!-- Tags -->
                                    <div class="multi-select-wrapper">
                                        <label>Tags</label>
                                        <div class="chips-input" data-target="tag">
                                            <span class="chips-placeholder">Selecionar tags...</span>
                                        </div>
                                        <div class="popover-list" id="popover-tag">
                                            <?php foreach ($tags as $t): ?>
                                                <div class="popover-item" data-id="<?= $t['id'] ?>" data-name="<?= sanitize($t['nome']) ?>">
                                                    <?= sanitize($t['nome']) ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <input type="hidden" name="tags_selecionadas" id="input-tag" value="<?= implode(',', $my_tags) ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- Requisitos e Plataformas -->
                            <div class="form-box">
                                <div class="box-title"><i class="fas fa-desktop"></i> Requisitos e Plataformas</div>
                                <label style="margin-bottom: 10px; display:block;">Plataformas Suportadas</label>
                                <div class="plat-grid" style="margin-bottom: 20px;">
                                    <?php foreach ($plataformas as $p): ?>
                                        <div class="plat-btn <?= in_array($p['id'], $my_plats) ? 'active' : '' ?>" data-id="<?= $p['id'] ?>">
                                            <i class="<?= $p['icone'] ?>"></i> <?= $p['nome'] ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" name="plats_selecionadas" id="input-plat" value="<?= implode(',', $my_plats) ?>">
                                <div class="req-grid">
                                    <div>
                                        <label>Requisitos Mínimos</label>
                                        <textarea name="requisitos_minimos" class="form-control" rows="4"><?= sanitize($jogo['requisitos_minimos']) ?></textarea>
                                    </div>
                                    <div>
                                        <label>Requisitos Recomendados</label>
                                        <textarea name="requisitos_recomendados" class="form-control" rows="4"><?= sanitize($jogo['requisitos_recomendados']) ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Screenshots -->
                            <div class="form-box">
                                <div class="box-title"><i class="fas fa-images"></i> Screenshots</div>
                                <p class="upload-info" style="text-align:left; margin-bottom:15px;">
                                    <i class="fas fa-info-circle"></i> Clique nas imagens para marcar para exclusão • Imagens serão otimizadas automaticamente
                                </p>
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
                                    <label class="add-shot" id="addShotBtn">
                                        <i class="fas fa-plus"></i>
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
                                <label>Capa (1:1) *</label>
                                <div class="upload-zone capa <?= $jogo['imagem_capa'] ? 'has-image' : '' ?>" id="zone-capa">
                                    <?php if ($jogo['imagem_capa']): ?>
                                        <img id="prev-capa" class="preview-img" src="<?= SITE_URL . $jogo['imagem_capa'] ?>">
                                    <?php endif; ?>
                                    <div class="upload-placeholder" id="place-capa" style="<?= $jogo['imagem_capa'] ? 'display:none' : '' ?>">
                                        <i class="fas fa-upload"></i>
                                        <span>Clique para enviar</span>
                                    </div>
                                </div>
                                <input type="file" name="capa" id="input-file-capa" style="display:none" accept="image/jpeg,image/png,image/webp">
                                <p class="upload-info">JPG, PNG, WebP • Será otimizada automaticamente</p>

                                <!-- Banner -->
                                <label style="margin-top: 20px; display:block;">Banner (16:9)</label>
                                <div class="upload-zone banner <?= $jogo['imagem_banner'] ? 'has-image' : '' ?>" id="zone-banner">
                                    <?php if ($jogo['imagem_banner']): ?>
                                        <img id="prev-banner" class="preview-img" src="<?= SITE_URL . $jogo['imagem_banner'] ?>">
                                    <?php endif; ?>
                                    <div class="upload-placeholder" id="place-banner" style="<?= $jogo['imagem_banner'] ? 'display:none' : '' ?>">
                                        <i class="fas fa-panorama"></i>
                                        <span>Clique para enviar</span>
                                    </div>
                                </div>
                                <input type="file" name="banner" id="input-file-banner" style="display:none" accept="image/jpeg,image/png,image/webp">
                                <p class="upload-info">JPG, PNG, WebP • Será otimizada automaticamente</p>
                            </div>

                            <!-- Venda -->
                            <div class="form-box">
                                <div class="box-title"><i class="fas fa-dollar-sign"></i> Venda</div>
                                <div class="form-group">
                                    <label>Preço (R$)</label>
                                    <input type="number" name="preco" class="form-control" step="0.01" min="0" value="<?= number_format($jogo['preco_centavos'] / 100, 2, '.', '') ?>" placeholder="0.00">
                                </div>
                                <div class="form-group">
                                    <label>Classificação Indicativa</label>
                                    <select name="classificacao" class="form-control">
                                        <?php
                                        $classificacoes = ['L' => 'Livre', '10' => '10+', '12' => '12+', '14' => '14+', '16' => '16+', '18' => '18+'];
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
                            <button type="submit" class="btn btn-primary btn-lg" style="width:100%">
                                <i class="fas fa-save"></i> Salvar Alterações
                            </button>

                            <?php if ($jogo['status'] == 'rascunho'): ?>
                                <button type="button" id="btnPublish" class="btn btn-success btn-lg" style="width:100%; margin-top: 12px;">
                                    <i class="fas fa-paper-plane"></i> Enviar para Revisão
                                </button>
                                <button type="button" id="btnDelete" class="btn-danger-outline">
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

<!-- Modal de Confirmação -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal-box">
        <div class="modal-icon" id="modalIcon"><i class="fas fa-question"></i></div>
        <h3 class="modal-title" id="modalTitle">Confirmação</h3>
        <p class="modal-text" id="modalText">Tem certeza?</p>
        <div class="modal-actions">
            <button class="btn btn-secondary" id="modalCancel">Cancelar</button>
            <button class="btn btn-primary" id="modalConfirm">Confirmar</button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

        function isValidType(file) {
            return allowedTypes.includes(file.type);
        }

        // ===========================================
        // MODAL
        // ===========================================
        function showModal(title, text, iconClass, iconType, onConfirm, confirmText = 'Confirmar', confirmClass = 'btn-primary') {
            const overlay = document.getElementById('modalOverlay');
            const modalIcon = document.getElementById('modalIcon');

            modalIcon.className = `modal-icon ${iconType}`;
            modalIcon.innerHTML = `<i class="fas ${iconClass}"></i>`;
            document.getElementById('modalTitle').textContent = title;
            document.getElementById('modalText').innerHTML = text;

            const modalConfirm = document.getElementById('modalConfirm');
            modalConfirm.textContent = confirmText;
            modalConfirm.className = `btn ${confirmClass}`;

            overlay.classList.add('active');

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

        // ===========================================
        // UPLOAD DE CAPA E BANNER
        // ===========================================
        function setupImageUpload(type) {
            const zone = document.getElementById(`zone-${type}`);
            const input = document.getElementById(`input-file-${type}`);
            const placeholder = document.getElementById(`place-${type}`);

            zone.addEventListener('click', () => input.click());

            input.addEventListener('change', function() {
                const file = this.files[0];
                if (!file) return;

                if (!isValidType(file)) {
                    alert('Formato inválido. Use JPG, PNG ou WebP.');
                    this.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    let img = document.getElementById(`prev-${type}`);
                    if (!img) {
                        img = document.createElement('img');
                        img.id = `prev-${type}`;
                        img.className = 'preview-img';
                        zone.appendChild(img);
                    }
                    img.src = e.target.result;
                    if (placeholder) placeholder.style.display = 'none';
                    zone.classList.add('has-image');
                };
                reader.readAsDataURL(file);
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

        // DataTransfer para acumular arquivos de múltiplas seleções
        const screenshotFiles = new DataTransfer();

        addShotBtn.addEventListener('click', () => shotInput.click());

        shotInput.addEventListener('change', function() {
            Array.from(this.files).forEach((file, index) => {
                if (!isValidType(file)) return;

                // Adiciona ao DataTransfer
                screenshotFiles.items.add(file);
                const fileIndex = screenshotFiles.files.length - 1;

                const reader = new FileReader();
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.className = 'shot-item shot-new';
                    div.dataset.fileIndex = fileIndex;
                    div.innerHTML = `
                <img src="${e.target.result}">
                <button type="button" class="remove-new-shot" style="position:absolute;top:5px;right:5px;background:#ef4444;color:white;border:none;border-radius:50%;width:24px;height:24px;cursor:pointer;font-size:12px;">
                    <i class="fas fa-times"></i>
                </button>
            `;
                    shotContainer.insertBefore(div, addShotBtn);
                };
                reader.readAsDataURL(file);
            });

            // Atualiza o input com todos os arquivos acumulados
            this.files = screenshotFiles.files;
        });

        // Remover screenshot nova (ainda não salva)
        shotContainer.addEventListener('click', function(e) {
            const removeBtn = e.target.closest('.remove-new-shot');
            if (!removeBtn) return;

            const shotItem = removeBtn.closest('.shot-item');
            const fileIndex = parseInt(shotItem.dataset.fileIndex);

            // Recria o DataTransfer sem o arquivo removido
            const newDt = new DataTransfer();
            Array.from(screenshotFiles.files).forEach((file, idx) => {
                if (idx !== fileIndex) {
                    newDt.items.add(file);
                }
            });

            // Atualiza os índices dos elementos restantes
            document.querySelectorAll('.shot-new').forEach(item => {
                const idx = parseInt(item.dataset.fileIndex);
                if (idx > fileIndex) {
                    item.dataset.fileIndex = idx - 1;
                }
            });

            // Atualiza referências
            screenshotFiles.items.clear();
            Array.from(newDt.files).forEach(file => screenshotFiles.items.add(file));
            shotInput.files = screenshotFiles.files;

            // Remove o elemento visual
            shotItem.remove();
        });

        // ===========================================
        // MULTISELECT (TAGS/CATEGORIAS)
        // ===========================================
        function initMultiSelect(type) {
            const wrapper = document.querySelector(`.chips-input[data-target="${type}"]`).closest('.multi-select-wrapper');
            const chipsContainer = wrapper.querySelector('.chips-input');
            const popover = document.getElementById(`popover-${type}`);
            const hiddenInput = document.getElementById(`input-${type}`);

            let selected = new Map();

            // Carregar valores existentes
            if (hiddenInput.value) {
                hiddenInput.value.split(',').forEach(id => {
                    const item = popover.querySelector(`[data-id="${id}"]`);
                    if (item) selected.set(id, item.dataset.name);
                });
                render();
            }

            chipsContainer.addEventListener('click', function(e) {
                if (e.target.closest('.chip i')) return;
                e.stopPropagation();
                document.querySelectorAll('.popover-list').forEach(p => {
                    if (p !== popover) p.style.display = 'none';
                });
                popover.style.display = popover.style.display === 'block' ? 'none' : 'block';
            });

            popover.addEventListener('click', function(e) {
                const item = e.target.closest('.popover-item');
                if (!item) return;
                e.stopPropagation();

                const id = item.dataset.id;
                if (selected.has(id)) {
                    selected.delete(id);
                } else {
                    selected.set(id, item.dataset.name);
                }
                render();
            });

            chipsContainer.addEventListener('click', function(e) {
                if (e.target.closest('.chip i')) {
                    e.stopPropagation();
                    const id = e.target.closest('.chip i').dataset.id;
                    selected.delete(id);
                    render();
                }
            });

            function render() {
                if (selected.size === 0) {
                    chipsContainer.innerHTML = `<span class="chips-placeholder">Selecionar ${type === 'cat' ? 'categorias' : 'tags'}...</span>`;
                } else {
                    chipsContainer.innerHTML = Array.from(selected).map(([id, name]) =>
                        `<div class="chip">${name} <i class="fas fa-times" data-id="${id}"></i></div>`
                    ).join('');
                }

                hiddenInput.value = Array.from(selected.keys()).join(',');

                popover.querySelectorAll('.popover-item').forEach(item => {
                    item.classList.toggle('selected', selected.has(item.dataset.id));
                });
            }
        }

        initMultiSelect('cat');
        initMultiSelect('tag');

        document.addEventListener('click', function() {
            document.querySelectorAll('.popover-list').forEach(p => p.style.display = 'none');
        });

        // ===========================================
        // PLATAFORMAS
        // ===========================================
        const platBtns = document.querySelectorAll('.plat-btn');
        const platInput = document.getElementById('input-plat');
        let selectedPlats = new Set(platInput.value ? platInput.value.split(',').filter(p => p) : []);

        function updatePlats() {
            platBtns.forEach(btn => {
                btn.classList.toggle('active', selectedPlats.has(btn.dataset.id));
            });
            platInput.value = Array.from(selectedPlats).join(',');
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

        // ===========================================
        // BOTÕES DE AÇÃO
        // ===========================================
        const btnPublish = document.getElementById('btnPublish');
        if (btnPublish) {
            btnPublish.onclick = function() {
                showModal(
                    'Enviar para Revisão',
                    'Tem certeza que deseja enviar seu jogo para revisão?<br><br><small style="color:var(--text-muted)">Nossa equipe verificará se o jogo atende às diretrizes da plataforma.</small>',
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
    });
</script>

<?php require_once '../includes/footer.php'; ?>