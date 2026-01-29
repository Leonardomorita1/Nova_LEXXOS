<?php
// admin/banners.php - GESTÃO UNIFICADA DE BANNERS E EVENTOS
require_once '../config/config.php';
require_once '../config/database.php';

requireLogin();
if (getUserType() !== 'admin') {
    header('Location: ' . SITE_URL . '/pages/home.php');
    exit;
}

$database = new Database();
$pdo = $database->getConnection();
$message = '';
$error = '';
$activeTab = $_GET['tab'] ?? 'eventos';

// ============================================
// FUNÇÕES DE UPLOAD
// ============================================
function uploadAndCompressImage($file, $tipo = 'banner', $maxWidth = 1920, $quality = 80)
{
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/assets/images/banners/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $fileType = mime_content_type($file['tmp_name']);

    if (!in_array($fileType, $allowedTypes)) {
        throw new Exception('Tipo de arquivo não permitido. Use JPG, PNG, WebP ou GIF.');
    }

    if ($file['size'] > 10 * 1024 * 1024) {
        throw new Exception('Arquivo muito grande. Máximo 10MB.');
    }

    $filename = $tipo . '-' . uniqid() . '-' . time() . '.jpg';
    $filepath = $uploadDir . $filename;

    switch ($fileType) {
        case 'image/jpeg':
            $source = imagecreatefromjpeg($file['tmp_name']);
            break;
        case 'image/png':
            $source = imagecreatefrompng($file['tmp_name']);
            break;
        case 'image/webp':
            $source = imagecreatefromwebp($file['tmp_name']);
            break;
        case 'image/gif':
            $source = imagecreatefromgif($file['tmp_name']);
            break;
        default:
            throw new Exception('Formato não suportado.');
    }

    if (!$source) {
        throw new Exception('Erro ao processar imagem.');
    }

    $origWidth = imagesx($source);
    $origHeight = imagesy($source);

    if ($origWidth > $maxWidth) {
        $ratio = $maxWidth / $origWidth;
        $newWidth = $maxWidth;
        $newHeight = (int)($origHeight * $ratio);
    } else {
        $newWidth = $origWidth;
        $newHeight = $origHeight;
    }

    $resized = imagecreatetruecolor($newWidth, $newHeight);
    $white = imagecolorallocate($resized, 255, 255, 255);
    imagefill($resized, 0, 0, $white);
    imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
    imagejpeg($resized, $filepath, $quality);

    imagedestroy($source);
    imagedestroy($resized);

    return '/assets/images/banners/' . $filename;
}

function uploadOverlayImage($file, $maxWidth = 800)
{
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/assets/images/banners/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $allowedTypes = ['image/png', 'image/webp', 'image/gif'];
    $fileType = mime_content_type($file['tmp_name']);

    if (!in_array($fileType, $allowedTypes)) {
        throw new Exception('Overlay deve ser PNG, WebP ou GIF.');
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('Overlay muito grande. Máximo 5MB.');
    }

    $filename = 'overlay-' . uniqid() . '-' . time() . '.png';
    $filepath = $uploadDir . $filename;

    switch ($fileType) {
        case 'image/png':
            $source = imagecreatefrompng($file['tmp_name']);
            break;
        case 'image/webp':
            $source = imagecreatefromwebp($file['tmp_name']);
            break;
        case 'image/gif':
            $source = imagecreatefromgif($file['tmp_name']);
            break;
    }

    $origWidth = imagesx($source);
    $origHeight = imagesy($source);

    if ($origWidth > $maxWidth) {
        $ratio = $maxWidth / $origWidth;
        $newWidth = $maxWidth;
        $newHeight = (int)($origHeight * $ratio);
    } else {
        $newWidth = $origWidth;
        $newHeight = $origHeight;
    }

    $resized = imagecreatetruecolor($newWidth, $newHeight);
    imagesavealpha($resized, true);
    $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
    imagefill($resized, 0, 0, $transparent);
    imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
    imagepng($resized, $filepath, 8);

    imagedestroy($source);
    imagedestroy($resized);

    return '/assets/images/banners/' . $filename;
}

function deleteImageFile($path) {
    if ($path && strpos($path, 'default') === false) {
        $filepath = $_SERVER['DOCUMENT_ROOT'] . $path;
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }
}

// ============================================
// AÇÕES - EVENTOS PROMOCIONAIS
// ============================================

// Criar Evento + Banner Promocional
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['criar_evento'])) {
    try {
        $pdo->beginTransaction();

        $nome = trim($_POST['nome']);
        $descricao = trim($_POST['descricao'] ?? '');
        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $nome)));
        $slug = trim($slug, '-');
        $data_inicio = $_POST['data_inicio'];
        $data_fim = $_POST['data_fim'];
        $texto_destaque = trim($_POST['texto_destaque'] ?? '');
        $cor_fundo = $_POST['cor_fundo'] ?? '#131314';
        $cor_texto = $_POST['cor_texto'] ?? '#E3E3E3';
        $cor_destaque = $_POST['cor_destaque'] ?? '#4C8BF5';

        if (empty($nome) || empty($data_inicio) || empty($data_fim)) {
            throw new Exception('Preencha todos os campos obrigatórios.');
        }

        // Upload imagens
        $imagem_banner = '/assets/images/default-banner.jpg';
        if (!empty($_FILES['imagem_banner']['tmp_name'])) {
            $imagem_banner = uploadAndCompressImage($_FILES['imagem_banner'], 'evento', 1920, 85);
        }

        $imagem_overlay = null;
        if (!empty($_FILES['imagem_overlay']['tmp_name'])) {
            $imagem_overlay = uploadOverlayImage($_FILES['imagem_overlay'], 800);
        }

        // Verificar slug único
        $stmt = $pdo->prepare("SELECT id FROM evento WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetch()) {
            $slug .= '-' . rand(100, 999);
        }

        // Criar evento
        $stmt = $pdo->prepare("
            INSERT INTO evento (nome, slug, descricao, imagem_banner, data_inicio, data_fim, ativo) 
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([$nome, $slug, $descricao, $imagem_banner, $data_inicio, $data_fim]);

        // URL do evento
        $url_evento = '/pages/evento.php?slug=' . $slug;

        // Criar banner promocional (apenas se não existir)
        $stmt = $pdo->prepare("SELECT id FROM banner WHERE url_destino = ?");
        $stmt->execute([$url_evento]);
        
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("
                INSERT INTO banner (
                    titulo, subtitulo, texto_destaque,
                    imagem_desktop, imagem_overlay,
                    cor_fundo, cor_texto, cor_destaque,
                    estilo_banner, url_destino, ordem, ativo, 
                    data_inicio, data_fim
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'promocional', ?, 0, 1, ?, ?)
            ");
            $stmt->execute([
                $nome,
                $descricao ?: 'Aproveite as ofertas especiais!',
                $texto_destaque,
                $imagem_banner,
                $imagem_overlay,
                $cor_fundo,
                $cor_texto,
                $cor_destaque,
                $url_evento,
                date('Y-m-d', strtotime($data_inicio)),
                date('Y-m-d', strtotime($data_fim))
            ]);
        }

        $pdo->commit();
        $message = 'Evento e banner promocional criados com sucesso!';
        $activeTab = 'eventos';
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Atualizar Evento + Banner
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['atualizar_evento'])) {
    try {
        $pdo->beginTransaction();

        $evento_id = (int)$_POST['evento_id'];
        $nome = trim($_POST['nome']);
        $descricao = trim($_POST['descricao'] ?? '');
        $data_inicio = $_POST['data_inicio'];
        $data_fim = $_POST['data_fim'];
        $texto_destaque = trim($_POST['texto_destaque'] ?? '');
        $cor_fundo = $_POST['cor_fundo'] ?? '#131314';
        $cor_texto = $_POST['cor_texto'] ?? '#E3E3E3';
        $cor_destaque = $_POST['cor_destaque'] ?? '#4C8BF5';

        if (empty($nome) || empty($data_inicio) || empty($data_fim)) {
            throw new Exception('Preencha todos os campos obrigatórios.');
        }

        // Buscar evento atual
        $stmt = $pdo->prepare("SELECT * FROM evento WHERE id = ?");
        $stmt->execute([$evento_id]);
        $eventoAtual = $stmt->fetch();

        if (!$eventoAtual) {
            throw new Exception('Evento não encontrado.');
        }

        $url_evento = '/pages/evento.php?slug=' . $eventoAtual['slug'];

        // Buscar banner atual
        $stmt = $pdo->prepare("SELECT * FROM banner WHERE url_destino = ? AND estilo_banner = 'promocional'");
        $stmt->execute([$url_evento]);
        $bannerAtual = $stmt->fetch();

        // Processar imagens
        $imagem_banner = $eventoAtual['imagem_banner'];
        if (!empty($_FILES['imagem_banner']['tmp_name'])) {
            deleteImageFile($eventoAtual['imagem_banner']);
            $imagem_banner = uploadAndCompressImage($_FILES['imagem_banner'], 'evento', 1920, 85);
        }

        $imagem_overlay = $bannerAtual['imagem_overlay'] ?? null;
        if (!empty($_FILES['imagem_overlay']['tmp_name'])) {
            if ($bannerAtual && $bannerAtual['imagem_overlay']) {
                deleteImageFile($bannerAtual['imagem_overlay']);
            }
            $imagem_overlay = uploadOverlayImage($_FILES['imagem_overlay'], 800);
        }

        if (isset($_POST['remover_overlay']) && $_POST['remover_overlay'] == '1') {
            if ($bannerAtual && $bannerAtual['imagem_overlay']) {
                deleteImageFile($bannerAtual['imagem_overlay']);
            }
            $imagem_overlay = null;
        }

        // Atualizar evento (SEM atualizado_em)
        $stmt = $pdo->prepare("
            UPDATE evento SET 
                nome = ?, descricao = ?, imagem_banner = ?, 
                data_inicio = ?, data_fim = ?
            WHERE id = ?
        ");
        $stmt->execute([$nome, $descricao, $imagem_banner, $data_inicio, $data_fim, $evento_id]);

        // Atualizar ou criar banner
        if ($bannerAtual) {
            $stmt = $pdo->prepare("
                UPDATE banner SET 
                    titulo = ?, subtitulo = ?, texto_destaque = ?,
                    imagem_desktop = ?, imagem_overlay = ?,
                    cor_fundo = ?, cor_texto = ?, cor_destaque = ?,
                    data_inicio = ?, data_fim = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $nome,
                $descricao ?: 'Aproveite as ofertas especiais!',
                $texto_destaque,
                $imagem_banner,
                $imagem_overlay,
                $cor_fundo,
                $cor_texto,
                $cor_destaque,
                date('Y-m-d', strtotime($data_inicio)),
                date('Y-m-d', strtotime($data_fim)),
                $bannerAtual['id']
            ]);
        }

        $pdo->commit();
        $message = 'Evento e banner atualizados com sucesso!';
        $activeTab = 'eventos';
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Toggle Evento
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_evento'])) {
    $evento_id = $_POST['evento_id'];

    $stmt = $pdo->prepare("SELECT slug, ativo FROM evento WHERE id = ?");
    $stmt->execute([$evento_id]);
    $evento = $stmt->fetch();

    if ($evento) {
        $novo_status = $evento['ativo'] ? 0 : 1;

        $stmt = $pdo->prepare("UPDATE evento SET ativo = ? WHERE id = ?");
        $stmt->execute([$novo_status, $evento_id]);

        $url_evento = '/pages/evento.php?slug=' . $evento['slug'];
        $stmt = $pdo->prepare("UPDATE banner SET ativo = ? WHERE url_destino = ?");
        $stmt->execute([$novo_status, $url_evento]);

        $message = 'Status atualizado!';
    }
    $activeTab = 'eventos';
}

// Deletar Evento
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['deletar_evento'])) {
    $evento_id = $_POST['evento_id'];

    $stmt = $pdo->prepare("SELECT slug, imagem_banner FROM evento WHERE id = ?");
    $stmt->execute([$evento_id]);
    $evento = $stmt->fetch();

    if ($evento) {
        $url_evento = '/pages/evento.php?slug=' . $evento['slug'];

        $stmt = $pdo->prepare("SELECT imagem_overlay FROM banner WHERE url_destino = ?");
        $stmt->execute([$url_evento]);
        $banner = $stmt->fetch();

        deleteImageFile($evento['imagem_banner']);
        if ($banner && $banner['imagem_overlay']) {
            deleteImageFile($banner['imagem_overlay']);
        }

        $stmt = $pdo->prepare("DELETE FROM banner WHERE url_destino = ?");
        $stmt->execute([$url_evento]);

        $stmt = $pdo->prepare("DELETE FROM evento WHERE id = ?");
        $stmt->execute([$evento_id]);

        $message = 'Evento e banner removidos!';
    }
    $activeTab = 'eventos';
}

// ============================================
// AÇÕES - BANNERS SIMPLES
// ============================================

// Criar Banner Simples
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['criar_banner'])) {
    try {
        $titulo = trim($_POST['titulo']);
        $subtitulo = trim($_POST['subtitulo'] ?? '');
        $url_destino = trim($_POST['url_destino']);
        $ordem = (int)$_POST['ordem'];
        $data_inicio = !empty($_POST['data_inicio']) ? $_POST['data_inicio'] : null;
        $data_fim = !empty($_POST['data_fim']) ? $_POST['data_fim'] : null;

        if (empty($titulo) || empty($url_destino)) {
            throw new Exception('Título e URL de destino são obrigatórios.');
        }

        $imagem_desktop = '/assets/images/default-banner.jpg';
        if (!empty($_FILES['imagem_desktop']['tmp_name'])) {
            $imagem_desktop = uploadAndCompressImage($_FILES['imagem_desktop'], 'banner', 1920, 85);
        }

        $stmt = $pdo->prepare("
            INSERT INTO banner (
                titulo, subtitulo, imagem_desktop,
                estilo_banner, url_destino, ordem, ativo, 
                data_inicio, data_fim
            ) VALUES (?, ?, ?, 'simples', ?, ?, 1, ?, ?)
        ");
        $stmt->execute([$titulo, $subtitulo, $imagem_desktop, $url_destino, $ordem, $data_inicio, $data_fim]);

        $message = 'Banner criado com sucesso!';
        $activeTab = 'banners';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Atualizar Banner Simples
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['atualizar_banner'])) {
    try {
        $banner_id = (int)$_POST['banner_id'];
        $titulo = trim($_POST['titulo']);
        $subtitulo = trim($_POST['subtitulo'] ?? '');
        $url_destino = trim($_POST['url_destino']);
        $ordem = (int)$_POST['ordem'];
        $data_inicio = !empty($_POST['data_inicio']) ? $_POST['data_inicio'] : null;
        $data_fim = !empty($_POST['data_fim']) ? $_POST['data_fim'] : null;

        // Buscar banner atual
        $stmt = $pdo->prepare("SELECT * FROM banner WHERE id = ? AND estilo_banner = 'simples'");
        $stmt->execute([$banner_id]);
        $bannerAtual = $stmt->fetch();

        if (!$bannerAtual) {
            throw new Exception('Banner não encontrado.');
        }

        $imagem_desktop = $bannerAtual['imagem_desktop'];
        if (!empty($_FILES['imagem_desktop']['tmp_name'])) {
            deleteImageFile($bannerAtual['imagem_desktop']);
            $imagem_desktop = uploadAndCompressImage($_FILES['imagem_desktop'], 'banner', 1920, 85);
        }

        $stmt = $pdo->prepare("
            UPDATE banner SET 
                titulo = ?, subtitulo = ?, imagem_desktop = ?,
                url_destino = ?, ordem = ?, data_inicio = ?, data_fim = ?
            WHERE id = ?
        ");
        $stmt->execute([$titulo, $subtitulo, $imagem_desktop, $url_destino, $ordem, $data_inicio, $data_fim, $banner_id]);

        $message = 'Banner atualizado!';
        $activeTab = 'banners';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Toggle Banner
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_banner'])) {
    $stmt = $pdo->prepare("UPDATE banner SET ativo = NOT ativo WHERE id = ?");
    $stmt->execute([$_POST['banner_id']]);
    $message = 'Status atualizado!';
    $activeTab = 'banners';
}

// Deletar Banner
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['deletar_banner'])) {
    $stmt = $pdo->prepare("SELECT imagem_desktop FROM banner WHERE id = ? AND estilo_banner = 'simples'");
    $stmt->execute([$_POST['banner_id']]);
    $banner = $stmt->fetch();

    if ($banner) {
        deleteImageFile($banner['imagem_desktop']);
        $stmt = $pdo->prepare("DELETE FROM banner WHERE id = ? AND estilo_banner = 'simples'");
        $stmt->execute([$_POST['banner_id']]);
        $message = 'Banner excluído!';
    }
    $activeTab = 'banners';
}

// ============================================
// BUSCAR DADOS
// ============================================

// Eventos com dados do banner
$eventos = $pdo->query("
    SELECT e.*, 
           b.id as banner_id,
           b.texto_destaque,
           b.cor_fundo,
           b.cor_texto,
           b.cor_destaque,
           b.imagem_overlay
    FROM evento e 
    LEFT JOIN banner b ON b.url_destino = CONCAT('/pages/evento.php?slug=', e.slug) 
                       AND b.estilo_banner = 'promocional'
    ORDER BY e.data_inicio DESC
")->fetchAll();

// Banners simples
$banners = $pdo->query("
    SELECT * FROM banner 
    WHERE estilo_banner = 'simples' OR estilo_banner IS NULL 
    ORDER BY ordem, id DESC
")->fetchAll();

// Contadores
$totalEventos = count($eventos);
$eventosAtivos = count(array_filter($eventos, fn($e) => $e['ativo']));
$totalBanners = count($banners);
$bannersAtivos = count(array_filter($banners, fn($b) => $b['ativo']));

$page_title = 'Banners & Eventos - Admin';
require_once '../includes/header.php';
?>

<style>
:root {
    --bg-primary: #0a0a0b;
    --bg-secondary: #131314;
    --bg-tertiary: #1a1a1b;
    --border: #2a2a2b;
    --text-primary: #e3e3e3;
    --text-secondary: #8b8b8b;
    --accent: #4C8BF5;
    --success: #2ecc71;
    --warning: #f39c12;
    --danger: #e74c3c;
}

/* Layout Base */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border);
}

.page-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 12px;
}

.page-title i { color: var(--accent); }

/* Stats Cards */
.stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 30px;
}

.stat-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 20px;
    text-align: center;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
}

.stat-label {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin-top: 4px;
}

.stat-card.accent { border-color: var(--accent); }
.stat-card.accent .stat-value { color: var(--accent); }

/* Tabs */
.tabs-nav {
    display: flex;
    gap: 0;
    margin-bottom: 30px;
    border-bottom: 2px solid var(--border);
}

.tab-btn {
    padding: 14px 28px;
    background: transparent;
    border: none;
    color: var(--text-secondary);
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    position: relative;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 10px;
}

.tab-btn:hover { color: var(--text-primary); }

.tab-btn.active {
    color: var(--accent);
}

.tab-btn.active::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    right: 0;
    height: 2px;
    background: var(--accent);
}

.tab-btn .badge {
    background: var(--bg-tertiary);
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.75rem;
}

.tab-btn.active .badge {
    background: var(--accent);
    color: white;
}

.tab-content { display: none; }
.tab-content.active { display: block; }

/* Cards de Item */
.item-grid {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.item-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    display: flex;
    gap: 20px;
    align-items: flex-start;
    transition: border-color 0.2s;
}

.item-card:hover { border-color: var(--accent); }

.item-thumb {
    width: 160px;
    height: 90px;
    border-radius: 8px;
    object-fit: cover;
    background: #000;
    border: 1px solid var(--border);
    flex-shrink: 0;
}

.item-content { flex: 1; min-width: 0; }

.item-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
    flex-wrap: wrap;
}

.item-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-primary);
}

.item-desc {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin-bottom: 12px;
    line-height: 1.4;
}

.item-meta {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.item-meta span {
    display: flex;
    align-items: center;
    gap: 6px;
}

.item-meta i { color: var(--accent); width: 14px; }

.item-tags {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 12px;
}

.tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.75rem;
    background: var(--bg-tertiary);
    color: var(--text-secondary);
    border: 1px solid var(--border);
}

.tag.highlight {
    background: rgba(243, 156, 18, 0.1);
    border-color: rgba(243, 156, 18, 0.3);
    color: var(--warning);
}

.color-dot {
    width: 14px;
    height: 14px;
    border-radius: 4px;
    border: 1px solid rgba(255,255,255,0.2);
}

.item-actions {
    display: flex;
    gap: 8px;
    flex-shrink: 0;
}

/* Status Badge */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.status-badge.active {
    background: rgba(46, 204, 113, 0.15);
    color: var(--success);
}

.status-badge.inactive {
    background: rgba(149, 165, 166, 0.15);
    color: #95a5a6;
}

.status-badge.promo {
    background: rgba(243, 156, 18, 0.15);
    color: var(--warning);
}

/* Buttons */
.btn {
    padding: 10px 18px;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    border: 1px solid transparent;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn-primary {
    background: var(--accent);
    color: white;
}

.btn-primary:hover { filter: brightness(1.1); }

.btn-outline {
    background: transparent;
    border-color: var(--border);
    color: var(--text-secondary);
}

.btn-outline:hover {
    border-color: var(--text-primary);
    color: var(--text-primary);
}

.btn-warning {
    background: rgba(243, 156, 18, 0.1);
    border-color: rgba(243, 156, 18, 0.3);
    color: var(--warning);
}

.btn-danger {
    background: rgba(231, 76, 60, 0.1);
    border-color: rgba(231, 76, 60, 0.3);
    color: var(--danger);
}

.btn-sm { padding: 6px 12px; font-size: 0.8rem; }
.btn-icon { width: 36px; height: 36px; padding: 0; justify-content: center; }

/* Modal */
.modal {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(4px);
    z-index: 1000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal.active { display: flex; }

.modal-box {
    background: var(--bg-secondary);
    width: 680px;
    max-width: 100%;
    max-height: 90vh;
    border-radius: 16px;
    border: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
    background: var(--bg-secondary);
}

.modal-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-title i { color: var(--accent); }

.modal-close {
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    font-size: 1.25rem;
    padding: 5px;
    transition: color 0.2s;
}

.modal-close:hover { color: var(--text-primary); }

.modal-body {
    padding: 24px;
    overflow-y: auto;
    flex: 1;
    max-height: calc(90vh - 140px);
    
    /* Scroll invisível - Webkit (Chrome, Safari, Edge) */
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* IE/Edge antigo */
}

/* Esconde scrollbar no Webkit */
.modal-body::-webkit-scrollbar {
    width: 0;
    height: 0;
    display: none;
}

/* Alternativa: Scroll fino e discreto (descomente se preferir) */
/*
.modal-body {
    scrollbar-width: thin;
    scrollbar-color: rgba(255,255,255,0.1) transparent;
}

.modal-body::-webkit-scrollbar {
    width: 6px;
}

.modal-body::-webkit-scrollbar-track {
    background: transparent;
}

.modal-body::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.1);
    border-radius: 3px;
}

.modal-body::-webkit-scrollbar-thumb:hover {
    background: rgba(255,255,255,0.2);
}
*/

.modal-footer {
    padding: 16px 24px;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    background: var(--bg-tertiary);
    flex-shrink: 0;
}

/* Form */
.form-section {
    margin-bottom: 28px;
}

.form-section-title {
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--accent);
    margin-bottom: 16px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-group { margin-bottom: 16px; }

.form-label {
    display: block;
    margin-bottom: 8px;
    font-size: 0.9rem;
    color: var(--text-primary);
    font-weight: 500;
}

.form-label .required { color: var(--danger); margin-left: 2px; }

.form-input {
    width: 100%;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    padding: 12px 14px;
    border-radius: 8px;
    color: var(--text-primary);
    font-size: 0.95rem;
    transition: border-color 0.2s;
}

.form-input:focus {
    outline: none;
    border-color: var(--accent);
}

.form-hint {
    font-size: 0.75rem;
    color: var(--text-secondary);
    margin-top: 6px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.form-row-3 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 16px;
}

/* File Upload */
.file-upload {
    border: 2px dashed var(--border);
    border-radius: 10px;
    padding: 24px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    background: var(--bg-primary);
}

.file-upload:hover {
    border-color: var(--accent);
    background: rgba(76, 139, 245, 0.05);
}

.file-upload.has-file {
    border-color: var(--success);
    border-style: solid;
}

.file-upload-icon {
    font-size: 2rem;
    color: var(--text-secondary);
    margin-bottom: 10px;
}

.file-upload.has-file .file-upload-icon { color: var(--success); }

.file-upload-text { color: var(--text-secondary); font-size: 0.9rem; }
.file-upload-text strong { color: var(--accent); }

.file-preview {
    margin-top: 12px;
    display: none;
}

.file-preview.visible { display: block; }

.file-preview img {
    max-width: 100%;
    max-height: 100px;
    border-radius: 6px;
    border: 1px solid var(--border);
}

.file-info {
    margin-top: 8px;
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.current-image {
    margin-bottom: 12px;
    padding: 12px;
    background: var(--bg-primary);
    border-radius: 8px;
    border: 1px solid var(--border);
}

.current-image-label {
    font-size: 0.75rem;
    color: var(--text-secondary);
    margin-bottom: 8px;
    display: block;
}

.current-image img {
    max-width: 100%;
    max-height: 80px;
    border-radius: 4px;
}

/* Color Picker */
.color-picker-group {
    display: flex;
    align-items: center;
    gap: 12px;
}

.color-input {
    width: 44px;
    height: 44px;
    border-radius: 8px;
    border: 2px solid var(--border);
    cursor: pointer;
    padding: 0;
    overflow: hidden;
}

.color-input::-webkit-color-swatch-wrapper { padding: 0; }
.color-input::-webkit-color-swatch { border: none; }

.color-value {
    font-family: monospace;
    font-size: 0.85rem;
    color: var(--text-secondary);
    background: var(--bg-primary);
    padding: 6px 10px;
    border-radius: 4px;
    border: 1px solid var(--border);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    border: 2px dashed var(--border);
    border-radius: 12px;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 3rem;
    opacity: 0.3;
    margin-bottom: 16px;
    display: block;
}

.empty-state h3 {
    color: var(--text-primary);
    margin-bottom: 8px;
}

/* Alert Messages */
.alert {
    padding: 14px 18px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-success {
    background: rgba(46, 204, 113, 0.1);
    border: 1px solid var(--success);
    color: var(--success);
}

.alert-error {
    background: rgba(231, 76, 60, 0.1);
    border: 1px solid var(--danger);
    color: var(--danger);
}

/* Checkbox */
.checkbox-option {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 10px;
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.checkbox-option input { width: 16px; height: 16px; }

/* Responsive */
@media (max-width: 900px) {
    .stats-row { grid-template-columns: repeat(2, 1fr); }
    .item-card { flex-direction: column; }
    .item-thumb { width: 100%; height: 140px; }
    .item-actions { width: 100%; justify-content: flex-end; }
    .form-row, .form-row-3 { grid-template-columns: 1fr; }
}
</style>

<div class="admin-layout">
    <?php require_once 'includes/sidebar.php'; ?>
    
    <main class="admin-content">
        <!-- Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-images"></i> Banners & Eventos
            </h1>
            <div style="display: flex; gap: 12px;">
                <button onclick="openModal('modalEvento')" class="btn btn-primary">
                    <i class="fas fa-calendar-plus"></i> Novo Evento
                </button>
                <button onclick="openModal('modalBanner')" class="btn btn-outline">
                    <i class="fas fa-plus"></i> Novo Banner
                </button>
            </div>
        </div>

        <!-- Mensagens -->
        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= $message ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-card accent">
                <div class="stat-value"><?= $eventosAtivos ?></div>
                <div class="stat-label">Eventos Ativos</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $totalEventos ?></div>
                <div class="stat-label">Total Eventos</div>
            </div>
            <div class="stat-card accent">
                <div class="stat-value"><?= $bannersAtivos ?></div>
                <div class="stat-label">Banners Ativos</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $totalBanners ?></div>
                <div class="stat-label">Total Banners</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs-nav">
            <button class="tab-btn <?= $activeTab === 'eventos' ? 'active' : '' ?>" onclick="switchTab('eventos')">
                <i class="fas fa-calendar-star"></i> Eventos Promocionais
                <span class="badge"><?= $totalEventos ?></span>
            </button>
            <button class="tab-btn <?= $activeTab === 'banners' ? 'active' : '' ?>" onclick="switchTab('banners')">
                <i class="fas fa-image"></i> Banners Simples
                <span class="badge"><?= $totalBanners ?></span>
            </button>
        </div>

        <!-- Tab: Eventos -->
        <div id="tab-eventos" class="tab-content <?= $activeTab === 'eventos' ? 'active' : '' ?>">
            <?php if (empty($eventos)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-plus"></i>
                    <h3>Nenhum evento cadastrado</h3>
                    <p>Crie seu primeiro evento promocional com banner personalizado.</p>
                </div>
            <?php else: ?>
                <div class="item-grid">
                    <?php foreach ($eventos as $evento): ?>
                        <div class="item-card">
                            <img src="<?= SITE_URL . $evento['imagem_banner'] ?>" class="item-thumb" alt="Banner"
                                 onerror="this.src='<?= SITE_URL ?>/assets/images/placeholder.jpg'">
                            
                            <div class="item-content">
                                <div class="item-header">
                                    <span class="item-title"><?= htmlspecialchars($evento['nome']) ?></span>
                                    <span class="status-badge <?= $evento['ativo'] ? 'active' : 'inactive' ?>">
                                        <i class="fas fa-circle" style="font-size: 6px;"></i>
                                        <?= $evento['ativo'] ? 'Ativo' : 'Inativo' ?>
                                    </span>
                                    <?php if ($evento['banner_id']): ?>
                                        <span class="status-badge promo">
                                            <i class="fas fa-star"></i> Banner
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($evento['descricao']): ?>
                                    <div class="item-desc"><?= htmlspecialchars($evento['descricao']) ?></div>
                                <?php endif; ?>

                                <div class="item-meta">
                                    <span>
                                        <i class="fas fa-clock"></i>
                                        <?= date('d/m/Y H:i', strtotime($evento['data_inicio'])) ?> - <?= date('d/m/Y H:i', strtotime($evento['data_fim'])) ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-link"></i>
                                        /<?= $evento['slug'] ?>
                                    </span>
                                </div>

                                <div class="item-tags">
                                    <?php if ($evento['texto_destaque']): ?>
                                        <span class="tag highlight">
                                            <i class="fas fa-percentage"></i> <?= htmlspecialchars($evento['texto_destaque']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="tag">
                                        <span class="color-dot" style="background: <?= $evento['cor_fundo'] ?? '#131314' ?>"></span>
                                        Fundo
                                    </span>
                                    <span class="tag">
                                        <span class="color-dot" style="background: <?= $evento['cor_texto'] ?? '#E3E3E3' ?>"></span>
                                        Texto
                                    </span>
                                    <span class="tag">
                                        <span class="color-dot" style="background: <?= $evento['cor_destaque'] ?? '#4C8BF5' ?>"></span>
                                        Destaque
                                    </span>
                                    <?php if ($evento['imagem_overlay']): ?>
                                        <span class="tag">
                                            <i class="fas fa-layer-group"></i> Overlay
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="item-actions">
                                <button onclick='openEditEvento(<?= json_encode($evento) ?>)' class="btn btn-warning btn-icon" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="<?= SITE_URL ?>/pages/evento.php?slug=<?= $evento['slug'] ?>" target="_blank" class="btn btn-outline btn-icon" title="Visualizar">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="evento_id" value="<?= $evento['id'] ?>">
                                    <button type="submit" name="toggle_evento" class="btn btn-outline btn-icon" title="<?= $evento['ativo'] ? 'Desativar' : 'Ativar' ?>">
                                        <i class="fas fa-power-off"></i>
                                    </button>
                                </form>
                                <form method="POST" onsubmit="return confirm('Excluir evento e banner?')" style="display:inline;">
                                    <input type="hidden" name="evento_id" value="<?= $evento['id'] ?>">
                                    <button type="submit" name="deletar_evento" class="btn btn-danger btn-icon" title="Excluir">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tab: Banners -->
        <div id="tab-banners" class="tab-content <?= $activeTab === 'banners' ? 'active' : '' ?>">
            <?php if (empty($banners)): ?>
                <div class="empty-state">
                    <i class="fas fa-image"></i>
                    <h3>Nenhum banner cadastrado</h3>
                    <p>Crie banners simples para o carrossel da loja.</p>
                </div>
            <?php else: ?>
                <div class="item-grid">
                    <?php foreach ($banners as $banner): ?>
                        <div class="item-card">
                            <img src="<?= SITE_URL . $banner['imagem_desktop'] ?>" class="item-thumb" alt="Banner"
                                 onerror="this.src='<?= SITE_URL ?>/assets/images/placeholder.jpg'">
                            
                            <div class="item-content">
                                <div class="item-header">
                                    <span class="item-title"><?= htmlspecialchars($banner['titulo']) ?></span>
                                    <span class="status-badge <?= $banner['ativo'] ? 'active' : 'inactive' ?>">
                                        <i class="fas fa-circle" style="font-size: 6px;"></i>
                                        <?= $banner['ativo'] ? 'Ativo' : 'Inativo' ?>
                                    </span>
                                </div>

                                <?php if ($banner['subtitulo']): ?>
                                    <div class="item-desc"><?= htmlspecialchars($banner['subtitulo']) ?></div>
                                <?php endif; ?>

                                <div class="item-meta">
                                    <span>
                                        <i class="fas fa-link"></i>
                                        <?= htmlspecialchars($banner['url_destino']) ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-sort"></i>
                                        Ordem: <?= $banner['ordem'] ?>
                                    </span>
                                    <?php if ($banner['data_inicio'] || $banner['data_fim']): ?>
                                        <span>
                                            <i class="fas fa-calendar"></i>
                                            <?= $banner['data_inicio'] ? date('d/m/Y', strtotime($banner['data_inicio'])) : '∞' ?>
                                            -
                                            <?= $banner['data_fim'] ? date('d/m/Y', strtotime($banner['data_fim'])) : '∞' ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="item-actions">
                                <button onclick='openEditBanner(<?= json_encode($banner) ?>)' class="btn btn-warning btn-icon" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="banner_id" value="<?= $banner['id'] ?>">
                                    <button type="submit" name="toggle_banner" class="btn btn-outline btn-icon" title="<?= $banner['ativo'] ? 'Desativar' : 'Ativar' ?>">
                                        <i class="fas fa-power-off"></i>
                                    </button>
                                </form>
                                <form method="POST" onsubmit="return confirm('Excluir banner?')" style="display:inline;">
                                    <input type="hidden" name="banner_id" value="<?= $banner['id'] ?>">
                                    <button type="submit" name="deletar_banner" class="btn btn-danger btn-icon" title="Excluir">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- ============================================
     MODAL: CRIAR/EDITAR EVENTO
     ============================================ -->
<div id="modalEvento" class="modal">
    <div class="modal-box">
        <form method="POST" enctype="multipart/form-data" id="formEvento">
            <input type="hidden" name="evento_id" id="eventoId">
            
            <div class="modal-header">
                <h3 class="modal-title" id="modalEventoTitle">
                    <i class="fas fa-calendar-plus"></i> Novo Evento Promocional
                </h3>
                <button type="button" onclick="closeModal('modalEvento')" class="modal-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">
                <!-- Info -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-info-circle"></i> Informações do Evento
                    </div>

                    <div class="form-group">
                        <label class="form-label">Nome do Evento <span class="required">*</span></label>
                        <input type="text" name="nome" id="eventoNome" class="form-input" required placeholder="Ex: Black Friday 2026">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" id="eventoDescricao" class="form-input" rows="2" placeholder="Subtítulo do banner"></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Data Início <span class="required">*</span></label>
                            <input type="datetime-local" name="data_inicio" id="eventoDataInicio" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Data Fim <span class="required">*</span></label>
                            <input type="datetime-local" name="data_fim" id="eventoDataFim" class="form-input" required>
                        </div>
                    </div>
                </div>

                <!-- Imagens -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-image"></i> Imagens do Banner
                    </div>

                    <div class="form-group">
                        <label class="form-label">Imagem de Fundo</label>
                        <div class="current-image" id="eventoCurrentBanner" style="display:none;">
                            <span class="current-image-label">Imagem atual:</span>
                            <img src="" id="eventoCurrentBannerImg" alt="Atual">
                        </div>
                        <div class="file-upload" id="eventoBannerArea">
                            <input type="file" name="imagem_banner" id="eventoBannerInput" accept="image/*" style="display:none;">
                            <div class="file-upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                            <div class="file-upload-text"><strong>Clique para enviar</strong> ou arraste</div>
                            <div class="file-preview" id="eventoBannerPreview"><img src="" alt="Preview"></div>
                            <div class="file-info" id="eventoBannerInfo"></div>
                        </div>
                        <p class="form-hint">Recomendado: 1920x500px • JPG/PNG • Máx 10MB</p>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Imagem Overlay (Opcional)</label>
                        <div class="current-image" id="eventoCurrentOverlay" style="display:none;">
                            <span class="current-image-label">Overlay atual:</span>
                            <img src="" id="eventoCurrentOverlayImg" alt="Atual">
                            <label class="checkbox-option">
                                <input type="checkbox" name="remover_overlay" value="1">
                                <span>Remover overlay</span>
                            </label>
                        </div>
                        <div class="file-upload" id="eventoOverlayArea">
                            <input type="file" name="imagem_overlay" id="eventoOverlayInput" accept="image/png,image/webp,image/gif" style="display:none;">
                            <div class="file-upload-icon"><i class="fas fa-layer-group"></i></div>
                            <div class="file-upload-text"><strong>PNG com transparência</strong></div>
                            <div class="file-preview" id="eventoOverlayPreview"><img src="" alt="Preview"></div>
                            <div class="file-info" id="eventoOverlayInfo"></div>
                        </div>
                        <p class="form-hint">Imagem sobreposta ao fundo (personagem, produto)</p>
                    </div>
                </div>

                <!-- Texto -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-font"></i> Texto Promocional
                    </div>

                    <div class="form-group">
                        <label class="form-label">Texto Destaque</label>
                        <input type="text" name="texto_destaque" id="eventoTextoDestaque" class="form-input" placeholder="Ex: ATÉ 75% OFF">
                        <p class="form-hint">Aparece em destaque no banner</p>
                    </div>
                </div>

                <!-- Cores -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-palette"></i> Cores do Banner
                    </div>

                    <div class="form-row-3">
                        <div class="form-group">
                            <label class="form-label">Fundo</label>
                            <div class="color-picker-group">
                                <input type="color" name="cor_fundo" value="#131314" class="color-input" id="eventoCorFundo">
                                <span class="color-value" id="eventoCorFundoVal">#131314</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Texto</label>
                            <div class="color-picker-group">
                                <input type="color" name="cor_texto" value="#E3E3E3" class="color-input" id="eventoCorTexto">
                                <span class="color-value" id="eventoCorTextoVal">#E3E3E3</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Destaque</label>
                            <div class="color-picker-group">
                                <input type="color" name="cor_destaque" value="#4C8BF5" class="color-input" id="eventoCorDestaque">
                                <span class="color-value" id="eventoCorDestaqueVal">#4C8BF5</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" onclick="closeModal('modalEvento')" class="btn btn-outline">Cancelar</button>
                <button type="submit" name="criar_evento" id="btnSubmitEvento" class="btn btn-primary">
                    <i class="fas fa-rocket"></i> Criar Evento
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================
     MODAL: CRIAR/EDITAR BANNER SIMPLES
     ============================================ -->
<div id="modalBanner" class="modal">
    <div class="modal-box">
        <form method="POST" enctype="multipart/form-data" id="formBanner">
            <input type="hidden" name="banner_id" id="bannerId">
            
            <div class="modal-header">
                <h3 class="modal-title" id="modalBannerTitle">
                    <i class="fas fa-plus"></i> Novo Banner
                </h3>
                <button type="button" onclick="closeModal('modalBanner')" class="modal-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">
                <!-- Info -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-info-circle"></i> Informações
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Título <span class="required">*</span></label>
                            <input type="text" name="titulo" id="bannerTitulo" class="form-input" required placeholder="Ex: Novos Lançamentos">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Subtítulo</label>
                            <input type="text" name="subtitulo" id="bannerSubtitulo" class="form-input" placeholder="Texto complementar">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">URL de Destino <span class="required">*</span></label>
                        <input type="text" name="url_destino" id="bannerUrl" class="form-input" required placeholder="/pages/catalogo.php">
                    </div>
                </div>

                <!-- Imagem -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-image"></i> Imagem
                    </div>

                    <div class="form-group">
                        <div class="current-image" id="bannerCurrentImage" style="display:none;">
                            <span class="current-image-label">Imagem atual:</span>
                            <img src="" id="bannerCurrentImageImg" alt="Atual">
                        </div>
                        <div class="file-upload" id="bannerImageArea">
                            <input type="file" name="imagem_desktop" id="bannerImageInput" accept="image/*" style="display:none;">
                            <div class="file-upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                            <div class="file-upload-text"><strong>Clique para enviar</strong> ou arraste</div>
                            <div class="file-preview" id="bannerImagePreview"><img src="" alt="Preview"></div>
                            <div class="file-info" id="bannerImageInfo"></div>
                        </div>
                        <p class="form-hint">Recomendado: 1920x500px • JPG/PNG • Máx 10MB</p>
                    </div>
                </div>

                <!-- Config -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-cog"></i> Configurações
                    </div>

                    <div class="form-row-3">
                        <div class="form-group">
                            <label class="form-label">Ordem</label>
                            <input type="number" name="ordem" id="bannerOrdem" class="form-input" value="0">
                            <p class="form-hint">Menor = primeiro</p>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Data Início</label>
                            <input type="date" name="data_inicio" id="bannerDataInicio" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Data Fim</label>
                            <input type="date" name="data_fim" id="bannerDataFim" class="form-input">
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" onclick="closeModal('modalBanner')" class="btn btn-outline">Cancelar</button>
                <button type="submit" name="criar_banner" id="btnSubmitBanner" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Criar Banner
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// ============================================
// TABS
// ============================================
function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    document.querySelector(`[onclick="switchTab('${tab}')"]`).classList.add('active');
    document.getElementById(`tab-${tab}`).classList.add('active');
    
    // Update URL
    const url = new URL(window.location);
    url.searchParams.set('tab', tab);
    window.history.replaceState({}, '', url);
}

// ============================================
// MODAIS
// ============================================
function openModal(id) {
    document.getElementById(id).classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
    
    // Reset forms
    if (id === 'modalEvento') {
        resetEventoForm();
    } else if (id === 'modalBanner') {
        resetBannerForm();
    }
}

// Fechar com ESC ou clique fora
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.active').forEach(m => m.classList.remove('active'));
    }
});

document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', e => {
        if (e.target === modal) closeModal(modal.id);
    });
});

// ============================================
// EVENTO - CRIAR/EDITAR
// ============================================
function resetEventoForm() {
    document.getElementById('formEvento').reset();
    document.getElementById('eventoId').value = '';
    document.getElementById('modalEventoTitle').innerHTML = '<i class="fas fa-calendar-plus"></i> Novo Evento Promocional';
    document.getElementById('btnSubmitEvento').name = 'criar_evento';
    document.getElementById('btnSubmitEvento').innerHTML = '<i class="fas fa-rocket"></i> Criar Evento';
    
    document.getElementById('eventoCurrentBanner').style.display = 'none';
    document.getElementById('eventoCurrentOverlay').style.display = 'none';
    document.getElementById('eventoBannerPreview').classList.remove('visible');
    document.getElementById('eventoOverlayPreview').classList.remove('visible');
    document.getElementById('eventoBannerArea').classList.remove('has-file');
    document.getElementById('eventoOverlayArea').classList.remove('has-file');
    
    // Reset colors
    document.getElementById('eventoCorFundo').value = '#131314';
    document.getElementById('eventoCorFundoVal').textContent = '#131314';
    document.getElementById('eventoCorTexto').value = '#E3E3E3';
    document.getElementById('eventoCorTextoVal').textContent = '#E3E3E3';
    document.getElementById('eventoCorDestaque').value = '#4C8BF5';
    document.getElementById('eventoCorDestaqueVal').textContent = '#4C8BF5';
}

function openEditEvento(evento) {
    document.getElementById('eventoId').value = evento.id;
    document.getElementById('eventoNome').value = evento.nome || '';
    document.getElementById('eventoDescricao').value = evento.descricao || '';
    document.getElementById('eventoTextoDestaque').value = evento.texto_destaque || '';
    
    // Datas
    if (evento.data_inicio) {
        document.getElementById('eventoDataInicio').value = new Date(evento.data_inicio).toISOString().slice(0, 16);
    }
    if (evento.data_fim) {
        document.getElementById('eventoDataFim').value = new Date(evento.data_fim).toISOString().slice(0, 16);
    }
    
    // Cores
    const corFundo = evento.cor_fundo || '#131314';
    const corTexto = evento.cor_texto || '#E3E3E3';
    const corDestaque = evento.cor_destaque || '#4C8BF5';
    
    document.getElementById('eventoCorFundo').value = corFundo;
    document.getElementById('eventoCorFundoVal').textContent = corFundo.toUpperCase();
    document.getElementById('eventoCorTexto').value = corTexto;
    document.getElementById('eventoCorTextoVal').textContent = corTexto.toUpperCase();
    document.getElementById('eventoCorDestaque').value = corDestaque;
    document.getElementById('eventoCorDestaqueVal').textContent = corDestaque.toUpperCase();
    
    // Imagens atuais
    if (evento.imagem_banner && !evento.imagem_banner.includes('default')) {
        document.getElementById('eventoCurrentBannerImg').src = '<?= SITE_URL ?>' + evento.imagem_banner;
        document.getElementById('eventoCurrentBanner').style.display = 'block';
    }
    
    if (evento.imagem_overlay) {
        document.getElementById('eventoCurrentOverlayImg').src = '<?= SITE_URL ?>' + evento.imagem_overlay;
        document.getElementById('eventoCurrentOverlay').style.display = 'block';
    }
    
    // Update modal
    document.getElementById('modalEventoTitle').innerHTML = '<i class="fas fa-edit" style="color:#f39c12"></i> Editar Evento';
    document.getElementById('btnSubmitEvento').name = 'atualizar_evento';
    document.getElementById('btnSubmitEvento').innerHTML = '<i class="fas fa-save"></i> Salvar Alterações';
    
    openModal('modalEvento');
}

// ============================================
// BANNER - CRIAR/EDITAR
// ============================================
function resetBannerForm() {
    document.getElementById('formBanner').reset();
    document.getElementById('bannerId').value = '';
    document.getElementById('modalBannerTitle').innerHTML = '<i class="fas fa-plus"></i> Novo Banner';
    document.getElementById('btnSubmitBanner').name = 'criar_banner';
    document.getElementById('btnSubmitBanner').innerHTML = '<i class="fas fa-plus"></i> Criar Banner';
    
    document.getElementById('bannerCurrentImage').style.display = 'none';
    document.getElementById('bannerImagePreview').classList.remove('visible');
    document.getElementById('bannerImageArea').classList.remove('has-file');
}

function openEditBanner(banner) {
    document.getElementById('bannerId').value = banner.id;
    document.getElementById('bannerTitulo').value = banner.titulo || '';
    document.getElementById('bannerSubtitulo').value = banner.subtitulo || '';
    document.getElementById('bannerUrl').value = banner.url_destino || '';
    document.getElementById('bannerOrdem').value = banner.ordem || 0;
    
    if (banner.data_inicio) {
        document.getElementById('bannerDataInicio').value = banner.data_inicio;
    }
    if (banner.data_fim) {
        document.getElementById('bannerDataFim').value = banner.data_fim;
    }
    
    // Imagem atual
    if (banner.imagem_desktop && !banner.imagem_desktop.includes('default')) {
        document.getElementById('bannerCurrentImageImg').src = '<?= SITE_URL ?>' + banner.imagem_desktop;
        document.getElementById('bannerCurrentImage').style.display = 'block';
    }
    
    // Update modal
    document.getElementById('modalBannerTitle').innerHTML = '<i class="fas fa-edit" style="color:#f39c12"></i> Editar Banner';
    document.getElementById('btnSubmitBanner').name = 'atualizar_banner';
    document.getElementById('btnSubmitBanner').innerHTML = '<i class="fas fa-save"></i> Salvar Alterações';
    
    openModal('modalBanner');
}

// ============================================
// FILE UPLOAD HANDLERS
// ============================================
function setupFileUpload(inputId, areaId, previewId, infoId) {
    const input = document.getElementById(inputId);
    const area = document.getElementById(areaId);
    const preview = document.getElementById(previewId);
    const info = document.getElementById(infoId);
    
    if (!input || !area) return;

    area.addEventListener('click', e => {
        if (e.target.tagName !== 'INPUT') input.click();
    });

    input.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const file = this.files[0];
            const reader = new FileReader();

            reader.onload = e => {
                if (preview) {
                    preview.querySelector('img').src = e.target.result;
                    preview.classList.add('visible');
                }
                area.classList.add('has-file');

                if (info) {
                    const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
                    info.textContent = `${file.name} (${sizeMB} MB)`;
                }
            };

            reader.readAsDataURL(file);
        }
    });

    // Drag and drop
    area.addEventListener('dragover', e => {
        e.preventDefault();
        area.style.borderColor = 'var(--accent)';
    });

    area.addEventListener('dragleave', () => {
        area.style.borderColor = '';
    });

    area.addEventListener('drop', e => {
        e.preventDefault();
        area.style.borderColor = '';
        if (e.dataTransfer.files.length) {
            input.files = e.dataTransfer.files;
            input.dispatchEvent(new Event('change'));
        }
    });
}

// Setup uploads
setupFileUpload('eventoBannerInput', 'eventoBannerArea', 'eventoBannerPreview', 'eventoBannerInfo');
setupFileUpload('eventoOverlayInput', 'eventoOverlayArea', 'eventoOverlayPreview', 'eventoOverlayInfo');
setupFileUpload('bannerImageInput', 'bannerImageArea', 'bannerImagePreview', 'bannerImageInfo');

// ============================================
// COLOR PICKERS
// ============================================
['eventoCorFundo', 'eventoCorTexto', 'eventoCorDestaque'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
        el.addEventListener('input', function() {
            document.getElementById(id + 'Val').textContent = this.value.toUpperCase();
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>